/* ==========================================================================
   BLADE CHECK — templates that parse
   --------------------------------------------------------------------------
   Run:  node tools/checks/blade-check.cjs
   A broken @if in one partial takes a whole page down at render time, which
   the PHP test suite does not catch on screens it never renders. This walks
   every blade file and checks:
     1. directive blocks are balanced (@if/@endif, @foreach/@endforeach, …)
     2. every @include('a.b') resolves to a view file
     3. every <x-name> component has a view or a class
   ========================================================================== */
'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..', '..');
const VIEWS = path.join(ROOT, 'resources/views');

const out = [];
const check = (name, ok, detail = '') => out.push([name, !!ok, detail]);

const walk = (dir, acc = []) => {
    for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, e.name);
        e.isDirectory() ? walk(full, acc) : acc.push(full);
    }
    return acc;
};

const files = walk(VIEWS).filter(f => f.endsWith('.blade.php'));
const rel = f => path.relative(VIEWS, f).replace(/\\/g, '/');

/* ----------------------------------------------------------- 1. directives */

/* Some directives share one closer: Blade closes @hasSection and
   @sectionMissing with @endif, so they are counted with @if rather than on
   their own or the pairing reports a false imbalance. */
const BLOCKS = [
    [['@if', '@hasSection', '@sectionMissing'], '@endif'],
    ['@foreach', '@endforeach'],
    ['@forelse', '@endforelse'],
    ['@for', '@endfor'],
    ['@while', '@endwhile'],
    ['@section', '@endsection'],
    ['@push', '@endpush'],
    ['@prepend', '@endprepend'],
    ['@once', '@endonce'],
    ['@php', '@endphp'],
    ['@can', '@endcan'],
    ['@canany', '@endcanany'],
    ['@cannot', '@endcannot'],
    ['@auth', '@endauth'],
    ['@guest', '@endguest'],
    ['@error', '@enderror'],
    ['@verbatim', '@endverbatim'],
];

/* the matching ")" of a "(" — quotes and nesting aware, so a comma inside a
   nested call is not mistaken for the inline form's separator */
function argList(text, openIdx) {
    let depth = 0, quote = null;
    for (let i = openIdx; i < text.length; i++) {
        const ch = text[i];
        if (quote) {
            if (ch === '\\') { i++; continue; }
            if (ch === quote) quote = null;
            continue;
        }
        if (ch === '"' || ch === "'") { quote = ch; continue; }
        if (ch === '(') depth++;
        else if (ch === ')') { depth--; if (depth === 0) return text.slice(openIdx + 1, i); }
    }
    return null;
}

/* @section('title', 'x') and @php($x = 1) close themselves — they are one-liners
   with no @endsection/@endphp, so they must not be counted as blocks */
function isSelfClosing(text, open, index) {
    const rest = text.slice(index + open.length);
    const paren = /^\s*\(/.exec(rest);
    if (!paren) return false;
    const args = argList(text, index + open.length + paren[0].length - 1);
    if (args === null) return false;
    if (open === '@php') return true;
    let depth = 0, quote = null;
    for (const ch of args) {
        if (quote) { if (ch === quote) quote = null; continue; }
        if (ch === '"' || ch === "'") { quote = ch; continue; }
        if (ch === '(') depth++;
        else if (ch === ')') depth--;
        else if (ch === ',' && depth === 0) return true;
    }
    return false;
}

const unbalanced = [];
files.forEach(file => {
    const text = fs.readFileSync(file, 'utf8').replace(/@verbatim[\s\S]*?@endverbatim/g, '');
    BLOCKS.forEach(([names, close]) => {
        const list = Array.isArray(names) ? names : [names];
        const primary = list[0];
        const escape = name => name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const openRe = new RegExp('(?:' + list.map(escape).join('|') + ')\\s*(\\(|$)', 'gm');
        const closeRe = new RegExp(escape(close) + '\\b', 'g');
        const opened = [...text.matchAll(openRe)]
            .filter(m => !['@section', '@php'].includes(primary) || !isSelfClosing(text, primary, m.index)).length;
        const closed = (text.match(closeRe) || []).length;
        if (opened !== closed) unbalanced.push(`${rel(file)}: ${primary} ×${opened} vs ${close} ×${closed}`);
    });
});
check('every blade directive block is balanced', unbalanced.length === 0, unbalanced.join(' | '));

/* ------------------------------------------------------------- 2. includes */

const missingIncludes = [];
const included = new Set();
files.forEach(file => {
    const text = fs.readFileSync(file, 'utf8');
    for (const m of text.matchAll(/@include\(\s*'([^']+)'/g)) {
        const target = path.join(VIEWS, m[1].replace(/\./g, '/') + '.blade.php');
        included.add(target);
        if (!fs.existsSync(target)) missingIncludes.push(`${rel(file)} → ${m[1]}`);
    }
});
check('every @include resolves to a view', missingIncludes.length === 0, missingIncludes.join(' | '));

/* ----------------------------------------------------------- 3. components */

const COMPONENTS = path.join(VIEWS, 'components');
const componentView = name => {
    const kebab = name.replace(/\./g, '/');
    return fs.existsSync(path.join(COMPONENTS, kebab + '.blade.php'))
        || fs.existsSync(path.join(VIEWS, kebab + '.blade.php'))
        || fs.existsSync(path.join(ROOT, 'app/View/Components/' + name + '.php'));
};

const unknown = [];
files.forEach(file => {
    const text = fs.readFileSync(file, 'utf8');
    for (const m of text.matchAll(/<x-([a-z0-9.\-]+)(?=[\s/>])/g)) {
        const name = m[1];
        /* <x-slot:*> is a slot marker, anonymous components may be inline */
        if (name.startsWith('slot')) continue;
        if (!componentView(name)) unknown.push(`${rel(file)} → <x-${name}>`);
    }
});
check('every <x-component> resolves to a view or class', unknown.length === 0,
    [...new Set(unknown)].join(' | '));

/* ------------------------------------------------------------- 4. layout */

const layouts = ['resources/views/layouts/app.blade.php',
                 'resources/views/client_portal/layouts/app.blade.php'];
const missingStack = [];
layouts.forEach(layout => {
    const text = fs.readFileSync(path.join(ROOT, layout), 'utf8');
    const pushed = new Set([...text.matchAll(/@stack\(\s*'([^']+)'/g)].map(m => m[1]));
    files.forEach(file => {
        const body = fs.readFileSync(file, 'utf8');
        for (const m of body.matchAll(/@push\(\s*'([^']+)'/g)) {
            if (!pushed.has(m[1])) missingStack.push(`${rel(file)} @push('${m[1]}')`);
        }
    });
});
check('every @push targets a stack the layouts render', missingStack.length === 0,
    [...new Set(missingStack)].join(' | '));

/* ------------------------------------------------- 5. component contracts */

/* A row menu is one component, and its items have one shape: an icon and a
   label from the same left edge. A `.master-btn` dropped inside it wears its
   own background, centres itself and lines up with nothing — that is how the
   shipments menu came out with a centred, icon-less "Public Link" while the
   items above it were left-aligned. The panel owns the shape, not the button. */
const menuBlocks = [];

for (const file of files) {
    const lines = fs.readFileSync(file, 'utf8').split('\n');

    lines.forEach((line, index) => {
        if (!/class="master-dropdown-menu"/.test(line)) return;

        const indent = line.match(/^\s*/)[0];
        const body = [];
        let closed = false;

        for (let i = index + 1; i < lines.length; i++) {
            if (lines[i] === indent + '</div>') { closed = true; break; }
            body.push(lines[i]);
        }

        if (closed) menuBlocks.push({ file: rel(file), line: index + 1, body: body.join('\n') });
    });
}

const menuItems = (body) => {
    const items = [];

    for (const m of body.matchAll(/<(a|button)\b[^>]*>/g)) {
        const rest = body.slice(m.index);
        const close = rest.indexOf('</' + m[1] + '>');
        items.push(close === -1 ? rest : rest.slice(0, close));
    }

    return items;
};

const buttonedMenus = menuBlocks
    .filter(menu => /master-btn/.test(menu.body))
    .map(menu => `${menu.file}:${menu.line}`);

check('no row-menu item carries button styling (the panel owns the item shape)',
    buttonedMenus.length === 0, buttonedMenus.join(' | '));

const iconless = [];
let itemsSeen = 0;

for (const menu of menuBlocks) {
    for (const item of menuItems(menu.body)) {
        itemsSeen++;
        if (!/<i\s/.test(item)) iconless.push(`${menu.file}:${menu.line}`);
    }
}

check('every row-menu item has an icon',
    menuBlocks.length > 0 && iconless.length === 0,
    `${menuBlocks.length} menu(s), ${itemsSeen} item(s)`
        + (iconless.length ? ' — missing in ' + iconless.join(', ') : ''));

/* ------------------------------------------------- 6. expression sanity */

/* One stray bracket in an expression takes the page down at render time, and
   a big find-and-replace is exactly when one appears. Brackets are counted
   outside quoted strings, per {{ … }} expression. */
const unbalancedExpressions = [];

/* same counting for a whole @php … @endphp island: a stray bracket there is a
   parse error on every page that renders the view, and it hides in a diff */
/* One small scanner rather than three regexes: it knows a quoted string from a
   comment, so an apostrophe in a comment ("the paginator's page") does not
   silently swallow the rest of the block, and a // inside a string ("https://…")
   is not mistaken for a comment. */
const bracketDepth = code => {
    let depth = 0;
    let quote = null;
    let comment = null;      // '//' to end of line, or '/*' to the closing */

    for (let i = 0; i < code.length; i++) {
        const char = code[i];
        const next = code[i + 1];

        if (comment === '//') {
            if (char === '\n') comment = null;
            continue;
        }

        if (comment === '/*') {
            if (char === '*' && next === '/') { comment = null; i++; }
            continue;
        }

        if (quote) {
            if (char === '\\') { i++; continue; }
            if (char === quote) quote = null;
            continue;
        }

        if (char === '/' && next === '/') { comment = '//'; i++; continue; }
        if (char === '/' && next === '*') { comment = '/*'; i++; continue; }
        if (char === "'" || char === '"') { quote = char; continue; }
        if (char === '(' || char === '[') depth++;
        if (char === ')' || char === ']') depth--;

        if (depth < 0) return { depth, broken: true };
    }

    return { depth, broken: false };
};

for (const file of files) {
    const text = fs.readFileSync(file, 'utf8');

    for (const m of text.matchAll(/\{\{(?!--)[\s\S]*?\}\}/g)) {
        const expression = m[0].slice(2, -2);
        const { depth, broken } = bracketDepth(expression);

        if (/\\\\[A-Z]/.test(expression)) {
            unbalancedExpressions.push(`${rel(file)}: doubled namespace in ${m[0].slice(0, 50)}`);
            continue;
        }

        if (broken || depth !== 0) {
            unbalancedExpressions.push(`${rel(file)}: ${m[0].slice(0, 60)}`);
        }
    }
}

check('every {{ … }} expression has balanced brackets',
    unbalancedExpressions.length === 0, [...new Set(unbalancedExpressions)].slice(0, 3).join(' | '));

const unbalancedPhpBlocks = [];

for (const file of files) {
    const text = fs.readFileSync(file, 'utf8');

    for (const m of text.matchAll(/@php(?!\()([\s\S]*?)@endphp/g)) {
        const { depth, broken } = bracketDepth(m[1]);

        if (broken || depth !== 0) {
            unbalancedPhpBlocks.push(`${rel(file)}: @php block at line ${text.slice(0, m.index).split('\n').length}`);
        }
    }
}

check('every @php block has balanced brackets',
    unbalancedPhpBlocks.length === 0, [...new Set(unbalancedPhpBlocks)].slice(0, 3).join(' | '));

/* A Blade directive inside a PHP island is not a directive. Blade turns the
   whole island into PHP first, then walks the directives it can still see — so
   "@if" written inside @php … @endphp is compiled a second time into PHP syntax
   inside a PHP syntax, and the view is a parse error the first time it renders.
   Strings and comments are blanked first: "user@example.com" is not a directive. */
const phpIslands = [];

const literalFree = code => {
    let out = '';
    let quote = null;
    let comment = null;

    for (let i = 0; i < code.length; i++) {
        const char = code[i];
        const next = code[i + 1];

        if (comment === '//') { if (char === '\n') { comment = null; out += char; } continue; }
        if (comment === '/*') { if (char === '*' && next === '/') { comment = null; i++; } continue; }
        if (quote) { if (char === '\\') { i++; continue; } if (char === quote) quote = null; continue; }

        if (char === '/' && next === '/') { comment = '//'; i++; continue; }
        if (char === '/' && next === '*') { comment = '/*'; i++; continue; }
        if (char === "'" || char === '"') { quote = char; out += ' '; continue; }

        out += char;
    }

    return out;
};

for (const file of files) {
    const text = fs.readFileSync(file, 'utf8');
    const islands = [
        ...text.matchAll(/@php(?!\()([\s\S]*?)@endphp/g),
        ...text.matchAll(/@php\(([\s\S]*?)\)(?=\s*(?:<|\n|@))/g),
        ...text.matchAll(/\{\{(?!--)([\s\S]*?)\}\}/g),
    ];

    for (const island of islands) {
        const found = literalFree(island[1]).match(/@[A-Za-z_]\w*/g);

        if (found) {
            const line = text.slice(0, island.index).split('\n').length;
            phpIslands.push(`${rel(file)}:${line} has ${[...new Set(found)].join(', ')} inside PHP`);
        }
    }
}

check('no blade directive is written inside PHP',
    phpIslands.length === 0, [...new Set(phpIslands)].slice(0, 3).join(' | '));

/* ------------------------------------------------- 7. structural tags balance */

/* A stray </div> or </td> is invisible in a diff and obvious in a browser: the
   markup reflows, a cell appears in the wrong column, or a modal's footer lands
   outside its card. Blade comments are stripped first — several of them talk
   about <td> on purpose. */
const STRUCTURAL = ['div', 'form', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th',
    'section', 'nav', 'main', 'button', 'a'];
const unbalancedTags = [];

for (const file of files) {
    const text = fs.readFileSync(file, 'utf8').replace(/\{\{--[\s\S]*?--\}\}/g, '').replace(/<!--[\s\S]*?-->/g, '');

    for (const tag of STRUCTURAL) {
        const opened = (text.match(new RegExp('<' + tag + '\\b', 'g')) || []).length;
        const closed = (text.match(new RegExp('</' + tag + '>', 'g')) || []).length;
        if (opened !== closed) {
            unbalancedTags.push(`${rel(file)}: <${tag}> ${opened} open, ${closed} closed`);
        }
    }
}

check('every structural tag is opened and closed the same number of times',
    unbalancedTags.length === 0, unbalancedTags.slice(0, 3).join(' | '));

/* ---------------------------------------------------------------- report */

const failed = out.filter(([, ok]) => !ok);
out.forEach(([name, ok, detail]) =>
    console.log((ok ? '  ok   ' : '  FAIL ') + name + (ok || !detail ? '' : '  -> ' + detail)));
console.log('\nblade: ' + (out.length - failed.length) + ' passed, ' + failed.length + ' failed'
    + ` (${files.length} templates)`);
process.exit(failed.length ? 1 : 0);
