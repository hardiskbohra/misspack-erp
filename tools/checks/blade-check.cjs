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

const BLOCKS = [
    ['@if', '@endif'],
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
    BLOCKS.forEach(([open, close]) => {
        const openRe = new RegExp(open.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\s*(\\(|$)', 'gm');
        const closeRe = new RegExp(close.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\b', 'g');
        const opens = [...text.matchAll(openRe)]
            .filter(m => !['@section', '@php'].includes(open) || !isSelfClosing(text, open, m.index)).length;
        const closes = (text.match(closeRe) || []).length;
        if (opens !== closes) unbalanced.push(`${rel(file)}: ${open} ×${opens} vs ${close} ×${closes}`);
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

/* ---------------------------------------------------------------- report */

const failed = out.filter(([, ok]) => !ok);
out.forEach(([name, ok, detail]) =>
    console.log((ok ? '  ok   ' : '  FAIL ') + name + (ok || !detail ? '' : '  -> ' + detail)));
console.log('\nblade: ' + (out.length - failed.length) + ' passed, ' + failed.length + ' failed'
    + ` (${files.length} templates)`);
process.exit(failed.length ? 1 : 0);
