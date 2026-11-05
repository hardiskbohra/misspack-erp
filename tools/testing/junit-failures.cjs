/* ==========================================================================
   JUNIT FAILURES → GitHub annotations
   --------------------------------------------------------------------------
   Run:  node tools/testing/junit-failures.cjs path/to/phpunit.xml
   Exits 0 always (it reports; it is not itself a gate).

   Why this exists: the phpunit step writes a JUnit report, but a red build in
   the Actions UI shows raw console scrollback. This script turns every failing
   or erroring testcase into a compact `::notice title=FAILED ...::` annotation
   (message = the assertion detail, truncated to GitHub's per-annotation
   budget), so the failure list is readable on the run summary page itself —
   important here, where job logs are not always fetchable.

   PHPUnit's junit pins file/line on the *suite* element (one suite per test
   class), with bare testcases inside; both levels are consulted.
   ========================================================================== */

'use strict';

const fs = require('fs');

const file = process.argv[2];
if (!file || !fs.existsSync(file)) {
    console.log('::error::phpunit produced no JUnit report — treat as a bootstrap crash, re-run without --coverage for the plain output');
    process.exit(0);
}

const xml = fs.readFileSync(file, 'utf8');

const attr = (tag, key) => {
    const m = new RegExp(key + '="([^"]*)"').exec(tag);
    return m ? m[1] : null;
};

const suiteRe = /<testsuite\b([^>]*)>([\s\S]*?)<\/testsuite>/g;
const caseRe = /<testcase\b([^>]*?)(?:\/>|>([\s\S]*?)<\/testcase>)/g;

const decode = (s) => s
    .replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"')
    .replace(/&#39;|&apos;/g, "'").replace(/&amp;/g, '&');

/* Gather every failing testcase, carrying the suite-level file/line down. */
const failures = [];
for (const s of xml.matchAll(suiteRe)) {
    const sFile = attr(s[1], 'file');
    const sLine = attr(s[1], 'line');
    for (const c of s[2].matchAll(caseRe)) {
        const body = c[2] || '';
        const frag = /<(?:failure|error)\b[^>]*>([\s\S]*?)<\/(?:failure|error)>/.exec(body);
        if (!frag) continue;
        failures.push({
            className: attr(c[1], 'classname') || attr(c[1], 'class') || 'unknown',
            name: attr(c[1], 'name') || 'unknown',
            file: attr(c[1], 'file') || sFile,
            line: attr(c[1], 'line') || sLine,
            detail: frag[1],
        });
    }
}

const MAX = 45;      // "Too many annotations" guard rail
const BUDGET = 2400; // GitHub truncates annotation messages past ~3KB

if (failures.length === 0) {
    console.log('::error::PHPUnit failed but the JUnit report lists no failing test — inspect the job log (fatal during bootstrap?)');
}

failures.slice(0, MAX).forEach((f, i) => {
    if (i === MAX - 1 && failures.length > MAX) {
        console.log(`::notice title=…${failures.length - MAX} further failures elided — open the job log::`);
        return;
    }
    const where = f.file
        ? f.file.replace(/^\/home\/runner\/work\/[^/]+\/[^/]+\//, '') + (f.line ? ':' + f.line : '')
        : '';
    /* PHPUnit inlines whole pages into assertSee failures: the needle sits at
       the END, past any head-budget — so trim the middle, not the message. */
    const plain = decode(f.detail).replace(/\r/g, '')
        .replace(/[ \t]+\n/g, '\n').replace(/\n{3,}/g, '\n\n');
    const trimmed = plain.length > BUDGET
        ? plain.slice(0, 500) + '\n…[response body trimmed]…\n' + plain.slice(-1700)
        : plain;
    const detail = trimmed.replace(/%/g, '%25').replace(/\n/g, '%0A');
    console.log(`::notice title=FAILED ${f.className}::${f.name}${where ? ' — ' + where : ''}::${detail}`);
});
