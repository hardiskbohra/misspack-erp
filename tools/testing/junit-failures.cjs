/* ==========================================================================
   JUNIT FAILURES → GitHub annotations
   --------------------------------------------------------------------------
   Run:  node tools/testing/junit-failures.cjs path/to/phpunit.xml
   Exits 0 always (it reports; it is not itself a gate).

   Why this exists: the workflow step runs PHPUnit with --log-junit, but a red
   build in the Actions UI shows the raw console for everyone browsing and
   nothing structured for automation. This script turns each failing/erroring
   test in the JUnit report into a compact `::error` annotation (title = the
   test, message = the assertion detail, truncated to GitHub's per-annotation
   budget), so the failure list renders on the run summary page itself.
   ========================================================================== */

'use strict';

const fs = require('fs');

const file = process.argv[2];
if (!file || !fs.existsSync(file)) {
    console.log('::error::phpunit produced no JUnit report — treat as a bootstrap crash, re-run without --coverage for the plain output');
    process.exit(0);
}

const xml = fs.readFileSync(file, 'utf8');

// Every testcase carrying a <failure> or <error> child. Attribute order is
// PHPUnit-version-dependent, so pull the attributes out of the tag loosely.
const testcaseRe = /<testcase\b([^>]*)>([\s\S]*?)<\/testcase>/g;
const attr = (tag, key) => {
    const m = new RegExp(key + '="([^"]*)"').exec(tag);
    return m ? m[1] : null;
};

let count = 0;
const MAX = 45;      // "Too many annotations" guard rail
const BUDGET = 2800; // GitHub truncates annotation messages past ~3KB

const decode = (s) => s
    .replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"')
    .replace(/&#39;|&apos;/g, "'").replace(/&amp;/g, '&');

for (const match of xml.matchAll(testcaseRe)) {
    const [full, attrs, body] = match;
    const className = attr(attrs, 'classname') || attr(attrs, 'class') || 'unknown';
    const name = attr(attrs, 'name') || 'unknown';
    const attrFile = attr(attrs, 'file');
    const attrLine = attr(attrs, 'line');
    const frag = /<(?:failure|error)\b[^>]*>([\s\S]*?)<\/(?:failure|error)>/.exec(body);
    if (!frag) continue;

    if (count >= MAX) {
        console.log('::error::…further failures elided after ' + MAX + ' annotations — open the job log for the rest');
        break;
    }
    count++;

    const detail = decode(frag[1]).replace(/\r/g, '').replace(/[ \t]+\n/g, '\n')
        .replace(/\n{3,}/g, '\n\n').slice(0, BUDGET).replace(/%/g, '%25')
        .replace(/\n/g, '%0A').replace(/\r/g, '%0D');

    const fileAttr = attrFile ? ` file=${attrFile.replace(/^\/home\/runner\/work\/[^/]+\/[^/]+\//, '')}` : '';
    const lineAttr = attrFile && attrLine ? `,line=${attrLine}` : '';
    console.log(`::error${fileAttr}${lineAttr},title=${className}::${name}::${detail}`);
}

if (count === 0) {
    console.log('::error::PHPUnit failed but the JUnit report lists no failing test — inspect the job log (fatal during bootstrap?)');
}
