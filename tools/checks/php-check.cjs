/* ==========================================================================
   PHP CHECK — syntax, for a project that never had it
   --------------------------------------------------------------------------
   Run:  node tools/checks/php-check.cjs

   This repository has no PHP interpreter in its checking environment, so until
   now the only PHP verification was a brace count — which is exactly the class
   of check that cannot tell a missing semicolon from a nested array. This tool
   parses every .php file and every PHP island inside a Blade template with a
   real grammar (php-parser) and reports what does not parse.

   Install the parser once with:  npm install        (it is a devDependency)

   Without it the tool still runs its dependency-free guards and says so, in the
   same spirit as mark-check skipping QR decoding when jsqr is absent.
   ========================================================================== */
'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..', '..');

const out = [];
const check = (name, ok, detail = '') => out.push([name, !!ok, detail]);

/* The parser is a devDependency, but a machine can also have it installed
   elsewhere (a sandbox, a global install): PHP_PARSER_PATH points at a directory
   whose node_modules holds it. Everything below still runs without it — the
   dependency-free guards are the fallback, and the report says which mode ran. */
let Engine = null;
const parserCandidates = [
    'php-parser',
    process.env.PHP_PARSER_PATH && path.join(process.env.PHP_PARSER_PATH, 'node_modules', 'php-parser'),
].filter(Boolean);

for (const candidate of parserCandidates) {
    try {
        Engine = require(candidate).Engine;
        break;
    } catch (error) {
        Engine = null;
    }
}

/* ------------------------------------------------------- what we look at */

function walk(dir, acc = []) {
    if (!fs.existsSync(dir)) return acc;

    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        if (entry.name === 'vendor' || entry.name === 'node_modules') continue;
        const full = path.join(dir, entry.name);
        entry.isDirectory() ? walk(full, acc) : acc.push(full);
    }

    return acc;
}

const phpFiles = ['app', 'routes', 'database', 'config', 'tests']
    .flatMap(dir => walk(path.join(ROOT, dir)))
    .filter(file => file.endsWith('.php'));

const bladeFiles = walk(path.join(ROOT, 'resources/views'))
    .filter(file => file.endsWith('.blade.php'));

const rel = file => path.relative(ROOT, file).split(path.sep).join('/');

/* The PHP a Blade template really contains: @php … @endphp islands, @php(…)
   one-liners, and the {{ … }} / {!! … !!} echoes. Blade comments are removed
   first — inside one, "-- … --" reads as a PHP decrement and a bare quote
   unbalanced every "expression" after it. */
function phpIslands(text) {
    const clean = text.replace(/\{\{--[\s\S]*?--\}\}/g, '');
    const lineAt = index => clean.slice(0, index).split('\n').length;
    const islands = [];

    for (const m of clean.matchAll(/@php\b(?!\s*\()([\s\S]*?)@endphp/g)) {
        islands.push({ kind: '@php block', code: '<?php ' + m[1], line: lineAt(m.index) });
    }
    for (const m of clean.matchAll(/@php\s*\(([\s\S]*?)\)\s*(?=\n|@|<)/g)) {
        islands.push({ kind: '@php(…)', code: '<?php (' + m[1] + ');', line: lineAt(m.index) });
    }
    for (const m of clean.matchAll(/\{\{([\s\S]*?)\}\}/g)) {
        islands.push({ kind: 'echo', code: '<?php echo (' + m[1] + ');', line: lineAt(m.index) });
    }
    for (const m of clean.matchAll(/\{!!([\s\S]*?)!!\}/g)) {
        islands.push({ kind: 'raw echo', code: '<?php echo (' + m[1] + ');', line: lineAt(m.index) });
    }

    return islands;
}

/* ------------------------------------------------------------ the parse */

if (Engine) {
    const engine = new Engine({ parser: { suppressErrors: false } });
    const broken = [];

    for (const file of phpFiles) {
        try {
            engine.parseCode(fs.readFileSync(file, 'utf8'), rel(file));
        } catch (error) {
            const line = error.loc ? ':' + error.loc.start.line : '';
            broken.push(rel(file) + line + ' — ' + error.message.replace(/\s+/g, ' '));
        }
    }

    check('every PHP file parses', broken.length === 0, broken.slice(0, 3).join(' | '));

    const brokenIslands = [];
    let islands = 0;

    for (const file of bladeFiles) {
        for (const island of phpIslands(fs.readFileSync(file, 'utf8'))) {
            islands++;
            try {
                engine.parseCode(island.code, rel(file));
            } catch (error) {
                brokenIslands.push(`${rel(file)}:${island.line} (${island.kind}) `
                    + error.message.replace(/\s+/g, ' '));
            }
        }
    }

    check('every PHP island inside a Blade template parses',
        brokenIslands.length === 0, brokenIslands.slice(0, 3).join(' | '));

    console.log(`  (parser: php-parser · ${phpFiles.length} PHP files · ${islands} Blade islands)`);
} else {
    console.log('  (php-parser not installed — run `npm install`; dependency-free guards only)');
}

/* ------------------------------------------------- dependency-free guards */

/* A control file that echoes text before its opening tag sends its own source
   to the browser. */
const strayOutput = phpFiles.filter(file => {
    const text = fs.readFileSync(file, 'utf8');
    return /^[^\s<]/.test(text) || /^<\?php/.test(text) === false;
});
check('every PHP file opens with a php tag',
    strayOutput.length === 0, strayOutput.slice(0, 3).map(rel).join(' | '));

/* A closing tag in a class, migration or config file is legal and pointless:
   anything after it — a stray newline, a BOM — is sent to the client. */
const closingTag = phpFiles.filter(file => /\?>\s*$/.test(fs.readFileSync(file, 'utf8')));
check('no PHP file ends with a closing tag',
    closingTag.length === 0, closingTag.slice(0, 3).map(rel).join(' | '));

/* A namespace separator is one backslash. Written as two — `namespace App\\Models;`
   — the file parses under some tools and dies under PHP, which is a class of
   mistake this project has now made: a heredoc that doubles escapes produces a
   file that looks right and cannot run. Read outside strings and comments, the
   only place a doubled backslash belongs is a regex character class. */
const codeOutsideStrings = text => {
    let code = '';
    let state = null;

    for (let i = 0; i < text.length; i++) {
        const two = text.slice(i, i + 2);
        const char = text[i];

        if (state === 'block') { if (two === '*/') { state = null; i++; } continue; }
        if (state === 'line') { if (char === '\n') { state = null; code += char; } continue; }
        if (state === 'single' || state === 'double') {
            if (char === '\\') { i++; continue; }
            if ((state === 'single' && char === "'") || (state === 'double' && char === '"')) state = null;
            continue;
        }
        if (two === '/*') { state = 'block'; i++; continue; }
        if (two === '//') { state = 'line'; i++; continue; }
        if (char === "'") { state = 'single'; continue; }
        if (char === '"') { state = 'double'; continue; }

        code += char;
    }

    return code;
};

const doubledSeparator = phpFiles.filter(file =>
    /\\{2,}/.test(codeOutsideStrings(fs.readFileSync(file, 'utf8'))));

check('every namespace separator is a single backslash',
    doubledSeparator.length === 0,
    doubledSeparator.slice(0, 3).map(rel).join(' | ') + ' — PHP fatals on these');

/* An app class called statically has to be imported (or be in this file's own
   namespace). php-parser checks grammar, not names: a bare `DateRanges::normalise()`
   in a controller that forgot its `use App\Helpers\DateRanges;` parses perfectly
   and then fatals on the first request — which is exactly how a one-line fix
   becomes a second bug. Blade templates are not checked: they are not PHP files,
   and the App\…\Class::… form they use is already absolute. */
const appClasses = ['Helpers', 'Services'].flatMap(dir => {
    const full = path.join(ROOT, 'app', dir);
    if (!fs.existsSync(full)) return [];

    return fs.readdirSync(full)
        .filter(entry => entry.endsWith('.php'))
        .map(entry => ({ name: entry.replace(/\.php$/, ''), dir }));
});

/* Comments are not code. A docblock that names `EmployeeAccess::ownEditableFields()`
   while explaining a rule is not a static call, and a guard that reads it as one
   turns prose into a failing gate — which is exactly what happened, twice, to the
   `@media print` guard before it (a needle has to read the thing it is about).
   This strips comments and leaves strings, heredocs, escapes and PHP 8
   attributes (`#[…]`) alone: a stripper that is cleverer than the language is
   how a real call gets hidden. */
const stripPhpComments = (text) => {
    let out = '';
    let i = 0;
    let quote = null;
    let heredoc = null;

    while (i < text.length) {
        const ch = text[i];
        const next = text[i + 1];

        if (heredoc) {
            const end = text.indexOf(heredoc, i);
            if (end === -1) { out += text.slice(i); break; }
            out += text.slice(i, end + heredoc.length);
            i = end + heredoc.length;
            heredoc = null;
            continue;
        }

        if (quote) {
            out += ch;
            if (ch === '\\') { out += next === undefined ? '' : next; i += 2; continue; }
            if (ch === quote) quote = null;
            i += 1;
            continue;
        }

        if (ch === "'" || ch === '"' || ch === '`') { quote = ch; out += ch; i += 1; continue; }

        if (ch === '/' && next === '/') {
            const end = text.indexOf('\n', i);
            i = end === -1 ? text.length : end;
            out += '\n';
            continue;
        }

        /* `#` is a comment, `#[` is an attribute */
        if (ch === '#' && next !== '[') {
            const end = text.indexOf('\n', i);
            i = end === -1 ? text.length : end;
            out += '\n';
            continue;
        }

        if (ch === '/' && next === '*') {
            const end = text.indexOf('*/', i + 2);
            i = end === -1 ? text.length : end + 2;
            out += ' ';
            continue;
        }

        if (text.startsWith('<<<', i)) {
            const marker = /^<<<[ \t]*(?:'([A-Za-z_]\w*)'|"([A-Za-z_]\w*)"|([A-Za-z_]\w*))/.exec(text.slice(i));
            if (marker) {
                heredoc = marker[1] || marker[2] || marker[3];
                out += text.slice(i, i + marker[0].length);
                i += marker[0].length;
                continue;
            }
        }

        out += ch;
        i += 1;
    }

    return out;
};

const unimported = [];
for (const file of phpFiles) {
    const text = stripPhpComments(fs.readFileSync(file, 'utf8'));
    const own = (text.match(/^namespace\s+([^;]+);/m) || [, ''])[1].trim();

    for (const { name, dir } of appClasses) {
        if (own === 'App\\' + dir) continue;
        if (!new RegExp('(?<![\\\\\\w])' + name + '::').test(text)) continue;
        if (!new RegExp('use\\s+[^;]*\\b' + name + '\\b[^;]*;').test(text)) {
            unimported.push(rel(file) + ' → ' + name);
        }
    }
}
check('every app class a file calls statically is imported there',
    unimported.length === 0, unimported.slice(0, 5).join(' | '));

/* The fallback for a machine that never ran npm install: brackets per file,
   read outside strings and comments, and flagged only when they do not close. */
function balance(text) {
    let depth = 0, min = 0, quote = null, comment = null;

    for (let i = 0; i < text.length; i++) {
        const char = text[i], next = text[i + 1];

        if (comment === '//' || comment === '#') { if (char === '\n') comment = null; continue; }
        if (comment === '/*') { if (char === '*' && next === '/') { comment = null; i++; } continue; }
        if (quote) { if (char === '\\') { i++; continue; } if (char === quote) quote = null; continue; }

        if (char === '/' && next === '/') { comment = '//'; i++; continue; }
        if (char === '#') { comment = '#'; continue; }
        if (char === '/' && next === '*') { comment = '/*'; i++; continue; }
        if (char === "'" || char === '"') { quote = char; continue; }

        if (char === '(' || char === '[' || char === '{') depth++;
        if (char === ')' || char === ']' || char === '}') { depth--; min = Math.min(min, depth); }
    }

    return depth === 0 && min === 0;
}

const unbalanced = phpFiles.filter(file => !balance(fs.readFileSync(file, 'utf8')));
check('every PHP file has balanced groups', unbalanced.length === 0,
    unbalanced.slice(0, 3).map(rel).join(' | '));

/* ---------------------------------------------------------------- report */

const failed = out.filter(([, ok]) => !ok);
out.forEach(([name, ok, detail]) =>
    console.log((ok ? '  ok   ' : '  FAIL ') + name + (ok || !detail ? '' : '  -> ' + detail)));
console.log('\nphp: ' + (out.length - failed.length) + ' passed, ' + failed.length + ' failed'
    + (Engine ? '' : ' (parser missing — syntax not verified)'));
process.exit(failed.length ? 1 : 0);
