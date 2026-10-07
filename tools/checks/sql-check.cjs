/* ==========================================================================
   SQL CHECK — the SQL MySQL can actually run
   --------------------------------------------------------------------------
   Run:  node tools/checks/sql-check.cjs
   No dependencies. Exits non-zero on failure.

   The tests run on SQLite (`phpunit.xml`) and the office runs MySQL. Where the
   two disagree, a page that passes every test is a 500 on the first real
   install — and nothing in this repository said so until this tool, which is
   why the recurring desk shipped one:

     SQLSTATE[42000]: Syntax error or access violation: 1235 This version of
     MySQL doesn't yet support 'LIMIT & IN/ALL/ANY/SOME subquery'

   One mistake, three shapes, and they are all the same mistake: **a LIMIT that
   arrives somewhere MySQL refuses it.**

     - **inside an IN subquery.** SQLite runs
       `… where rule_id in (select id from rules limit 20 offset 0)`;
       MySQL 8 does not. The refusal is not about the LIMIT on its own — a plain
       `where rule_id in (select id from rules)` is fine, and this application
       uses that shape on purpose (see `RecurrenceFigures`, `SalesInvoiceController`);
     - **because the builder was shared.** The LIMIT above was never typed. It
       came from `paginate()`, which writes the page onto the builder it is
       called on — and that builder was the one the page's other reads borrow.
       Written out or inherited, MySQL refuses the same thing;
     - **through a service.** The page is taken inside a service that was handed
       the caller's builder. One call away, the same inheritance.

   So the house rule these guards hold is one sentence:
   **a builder is either a filter or a page, never both.** Borrow a clone; page
   a clone. `RecurrenceFilters::page()` is where the recurring desk says it.

   Narrow on purpose, and honest about the edges: a guard cannot see execution
   order, so it reads *shape* — a builder borrowed into a subquery and a page
   taken from that builder in the same file is flagged whether or not the reads
   happen to be ordered safely. The fix the message names (clone) is always
   harmless, which is what makes that acceptable.
   ========================================================================== */

'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..', '..');
const read = relative => fs.readFileSync(path.join(ROOT, relative), 'utf8');
const exists = relative => fs.existsSync(path.join(ROOT, relative));

let passed = 0;
let failed = 0;
const check = (name, ok, detail = '') => {
    console.log(`  ${ok ? 'ok  ' : 'FAIL'} ${name}${ok || !detail ? '' : ` -> ${detail}`}`);
    ok ? passed++ : failed++;
};

/* ------------------------------------------------------------- the readers */

function walk(dir, acc = []) {
    if (!fs.existsSync(dir)) return acc;

    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        if (entry.name === 'vendor' || entry.name === 'node_modules') continue;
        const full = path.join(dir, entry.name);
        entry.isDirectory() ? walk(full, acc) : acc.push(full);
    }

    return acc;
}

/* The code a file really contains: comments and strings out, newlines kept so a
   line number still points at the line a reader sees. A needle that reads prose
   is how a check starts failing on a comment — and this module's own docblocks
   talk about LIMIT and IN subqueries by name. */
function bare(text) {
    let out = '';
    let i = 0;
    let quote = null;
    let heredoc = null;

    while (i < text.length) {
        const ch = text[i];
        const next = text[i + 1];

        if (heredoc) {
            const end = text.indexOf(heredoc, i);
            if (end === -1) break;
            out += text.slice(i, end).replace(/[^\n]/g, ' ');
            out += heredoc;
            i = end + heredoc.length;
            heredoc = null;
            continue;
        }

        if (quote) {
            if (ch === '\\') { out += '  '; i += 2; continue; }
            if (ch === quote) { quote = null; out += ch; i += 1; continue; }
            out += ch === '\n' ? '\n' : ' ';
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

        /* `#` is a comment; `#[` is an attribute */
        if (ch === '#' && next !== '[') {
            const end = text.indexOf('\n', i);
            i = end === -1 ? text.length : end;
            out += '\n';
            continue;
        }

        if (ch === '/' && next === '*') {
            const end = text.indexOf('*/', i + 2);
            const body = text.slice(i, end === -1 ? text.length : end);
            out += body.replace(/[^\n]/g, ' ');
            i = end === -1 ? text.length : end + 2;
            continue;
        }

        if (text.startsWith('<<<', i)) {
            const marker = /^<<<[ \t]*(?:'([A-Za-z_]\w*)'|"([A-Za-z_]\w*)"|([A-Za-z_]\w*))/.exec(text.slice(i));
            if (marker) {
                heredoc = marker[1] || marker[2] || marker[3];
                out += marker[0];
                i += marker[0].length;
                continue;
            }
        }

        out += ch;
        i += 1;
    }

    return out;
}

const lineAt = (text, index) => text.slice(0, index).split('\n').length;

/* The text inside the call whose `(` sits at `open` — code in, code out. */
function callSpan(text, open) {
    let depth = 0;

    for (let i = open; i < text.length; i++) {
        if (text[i] === '(') depth++;
        else if (text[i] === ')' && --depth === 0) return text.slice(open + 1, i);
    }

    return text.slice(open + 1);
}

/* The arguments of that call, split at its own top-level commas. */
function argsOf(span) {
    const args = [];
    let depth = 0;
    let start = 0;

    for (let i = 0; i < span.length; i++) {
        const ch = span[i];
        if ('([{'.includes(ch)) depth++;
        else if (')]}'.includes(ch)) depth--;
        else if (ch === ',' && depth === 0) { args.push(span.slice(start, i)); start = i + 1; }
    }

    args.push(span.slice(start));

    return args;
}

/* The statement a call sits in: back to the previous `;`, `{` or `}`, forward
   to the next `;` — enough to see what a fluent chain starts from. */
function statementAt(text, index) {
    const before = text.slice(0, index);
    const start = Math.max(before.lastIndexOf(';'), before.lastIndexOf('{'), before.lastIndexOf('}'));
    const end = text.indexOf(';', index);

    return text.slice(start + 1, end === -1 ? text.length : end + 1);
}

/* Where `$name` was last assigned before `index` — one level deep, which is
   as far as a reader's eye goes: `$ids = (clone $q)->select('id')`. */
function assignmentOf(text, name, index) {
    const before = text.slice(0, index);
    const pattern = new RegExp('\\$' + name + '\\s*=(?!=)', 'g');
    let last = null;

    for (const match of before.matchAll(pattern)) last = match;

    return last ? statementAt(text, last.index) : null;
}

/* Which function a call sits in. Two methods of one controller both calling
   their builder `$query` are not borrowing each other's — a borrow is something
   two reads inside the *same* body do, so the scope of a name is its function. */
const functionAt = (text, index) => text.slice(0, index).lastIndexOf('function ');

const varsIn = text => [...text.matchAll(/\$([A-Za-z_]\w*)/g)]
    .map(match => match[1])
    .filter(name => name !== 'this');

/* Builder-shaped: something whose SQL is written later, not an array of values.
   A plain `whereIn('status', ['open', 'waiting'])` cannot carry a LIMIT. */
const BUILDER = /(?:^|[^\w$])clone\s+\$|\w[\w\\]*::query\s*\(|\bDB::table\s*\(|\bnew\s+[\w\\]*Query\b/;

const IN_CALL = /(?:->|::)(?:whereIn|whereNotIn|whereInRaw|whereNotInRaw|whereIntegerInRaw|whereIntegerNotInRaw)\s*\(/g;
const LIMIT_CALL = /->(?:limit|take|forPage|paginate|simplePaginate)\s*\(/g;
const LIMIT_ANY = /->(?:limit|take|forPage|paginate|simplePaginate)\s*\(/;

/**
 * What a file does with its builders.
 *
 * Scoped to the function a call sits in: a name is borrowed by the reads that
 * share a body with it, not by every method of a long controller.
 *
 * @returns {{literal: string[], inherited: string[]}}
 */
function scan(relative, text) {
    const literal = [];
    const inherited = [];
    const borrowed = new Map();

    const borrow = (key, name, where) => {
        if (!borrowed.has(key)) borrowed.set(key, new Map());
        if (!borrowed.get(key).has(name)) borrowed.get(key).set(name, where);
    };

    for (const match of text.matchAll(IN_CALL)) {
        const span = callSpan(text, match.index + match[0].length - 1);
        const args = argsOf(span);

        if (args.length < 2) continue;

        /* One level of `$ids = (clone $q)->select('id')`. */
        const value = args[1].trim();
        const single = /^\$([A-Za-z_]\w*)$/.exec(value);
        const expression = (single ? assignmentOf(text, single[1], match.index) : null) || value;

        if (!BUILDER.test(expression)) continue;

        const line = lineAt(text, match.index);
        const call = match[0].replace(/->|\(/g, '');

        /* (a) a LIMIT written inside it. There is no legal reading of this. */
        if (LIMIT_ANY.test(expression)) {
            literal.push(`${relative}:${line} — ${call}() over a subquery carrying a LIMIT`);
        }

        /* (b) the builder it borrows, kept for the other half of the rule. */
        for (const name of varsIn(expression)) {
            borrow(functionAt(text, match.index), name, `${relative}:${line}`);
        }
    }

    for (const match of text.matchAll(LIMIT_CALL)) {
        const names = borrowed.get(functionAt(text, match.index));
        if (!names) continue;

        /* `clone $q` at the page is the fix, so it is also the exemption. */
        const statement = statementAt(text, match.index);
        const paged = statement.replace(new RegExp('clone\\s+\\$(?:' + [...names.keys()].join('|') + ')', 'g'), '');

        for (const [name, where] of names) {
            if (!new RegExp('\\$' + name + '\\b').test(paged)) continue;
            inherited.push(`${relative}:${lineAt(text, match.index)} — $${name} is borrowed at ${where} and the page is taken from it here`);
        }
    }

    return { literal, inherited };
}

/* Builder parameters of the function a call sits in. Typed on purpose: a scalar
   parameter cannot be a query, and a guard that flagged one would be noise. */
function builderParams(text, index) {
    const head = text.slice(0, index);
    const fn = head.lastIndexOf('function ');
    if (fn === -1) return [];

    const open = text.indexOf('(', fn);
    if (open === -1 || open > index) return [];

    const close = open + callSpan(text, open).length + 1;
    const params = text.slice(open + 1, close);

    return [...params.matchAll(/([\w\\]+)\s+\$([A-Za-z_]\w*)/g)]
        .filter(match => /(Builder|Relation)$/.test(match[1]))
        .map(match => match[2]);
}

/* ---------------------------------------------------------------- the guard */

const phpFiles = ['app', 'database', 'routes']
    .flatMap(dir => walk(path.join(ROOT, dir)))
    .filter(file => file.endsWith('.php'));

const blFiles = walk(path.join(ROOT, 'resources/views')).filter(file => file.endsWith('.blade.php'));
const rel = file => path.relative(ROOT, file).split(path.sep).join('/');

const literal = [];
const inherited = [];
const uncloned = [];

for (const file of phpFiles) {
    const text = bare(fs.readFileSync(file, 'utf8'));
    const found = scan(rel(file), text);
    literal.push(...found.literal);
    inherited.push(...found.inherited);
}

/* A query can hide in a Blade island too. Only the written-out shape is read
   there: a statement and a function are things a template does not have. */
for (const file of blFiles) {
    const text = bare(fs.readFileSync(file, 'utf8'));
    literal.push(...scan(rel(file), text).literal);
}

/* A service that is handed a builder must not hand the LIMIT back to its
   caller: the caller's builder is a filter, and a filter that quietly became a
   page is the same 500 one call away. */
for (const file of walk(path.join(ROOT, 'app/Services')).filter(f => f.endsWith('.php'))) {
    const text = bare(fs.readFileSync(file, 'utf8'));

    for (const match of text.matchAll(LIMIT_CALL)) {
        const statement = statementAt(text, match.index);
        const params = builderParams(text, match.index);

        for (const name of params) {
            if (!new RegExp('\\$' + name + '\\b').test(statement)) continue;
            if (/clone\s/.test(statement)) continue;
            uncloned.push(`${rel(file)}:${lineAt(text, match.index)} — $${name} arrives as a builder and leaves with a LIMIT`);
        }
    }
}

check('no LIMIT is written inside an IN subquery',
    literal.length === 0,
    literal.slice(0, 3).join(' | ') + ' — MySQL error 1235; narrow the read instead of limiting it');

check('no builder a read borrows is the builder a page is taken from',
    inherited.length === 0,
    inherited.slice(0, 3).join(' | ') + ' — page a clone (see RecurrenceFilters::page)');

check('no service pages a builder it was handed without cloning it',
    uncloned.length === 0,
    uncloned.slice(0, 3).join(' | '));

/* ------------------------------------------------------------ the needles */

/* A guard has to read the thing it is about. These four samples run on every
   pass: the two shapes that must be caught, and their nearest legal
   neighbours — a value list, the same borrow with the page taken from a clone,
   and the same LIMIT hidden in a comment. */
const SAMPLES = [
    {
        wants: 'literal',
        name: 'a LIMIT written inside an IN subquery',
        code: "$due = Occurrence::query()->whereIn('rule_id', (clone $q)->select('id')->limit(5))->get();",
    },
    {
        wants: null,
        name: 'a value list of statuses',
        code: "$rows = Occurrence::query()->whereIn('status', ['pending', 'held'])->orderBy('id')->get();",
    },
    {
        wants: 'inherited',
        name: 'a borrowed builder with the page taken from it',
        code: "$q = Rule::query()->where('status', 'active');\n"
            + "$a = Occ::query()->whereIn('rule_id', (clone $q)->select('id'))->get();\n"
            + "$b = $q->paginate(20);",
    },
    {
        wants: null,
        name: 'the same borrow, the page taken from a clone',
        code: "$q = Rule::query()->where('status', 'active');\n"
            + "$a = Occ::query()->whereIn('rule_id', (clone $q)->select('id'))->get();\n"
            + "$b = (clone $q)->paginate(20);",
    },
    {
        wants: null,
        name: 'a LIMIT in a comment about the rule',
        code: "$ids = (clone $q)->select('id'); // never write ->limit(5) in here\n"
            + "$rows = Occurrence::query()->whereIn('rule_id', $ids)->get();",
    },
];

const sampleReport = SAMPLES.map((sample) => {
    const found = scan('sample.php', bare(sample.code));
    const got = found.literal.length ? 'literal' : (found.inherited.length ? 'inherited' : null);

    return { sample, got, ok: got === sample.wants };
});

check('the needles read the shape they are about, on samples',
    sampleReport.every(entry => entry.ok),
    sampleReport.filter(entry => !entry.ok)
        .map(entry => `${entry.sample.name}: wanted ${entry.sample.wants || 'nothing'}, got ${entry.got || 'nothing'}`)
        .join(' | '));

/* --------------------------------------------------------- the paperwork */

check('the check itself is listed with its siblings',
    /sql-check\.cjs/.test(read('tools/checks/README.md')));

/* The whole reason these guards exist: the tests are not running the database
   the office runs. If that ever changes, this tool has less to stand in for —
   and the line below says so rather than pretending otherwise. */
const testsOnSqlite = /DB_CONNECTION"\s+value="sqlite"/.test(read('phpunit.xml'));

console.log(testsOnSqlite
    ? '  (the tests run on sqlite; the office runs MySQL — that gap is what these guards stand in for)'
    : '  (the tests no longer run on sqlite — MySQL itself is now part of every run)');

/* ------------------------------------------------------------------ report */

console.log(`\nsql: ${passed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);
