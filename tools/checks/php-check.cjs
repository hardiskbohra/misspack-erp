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

let Engine = null;
try {
    Engine = require('php-parser').Engine;
} catch (error) {
    Engine = null;
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
