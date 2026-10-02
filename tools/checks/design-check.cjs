/* ==========================================================================
   DESIGN CHECK — shared UI guard rails
   --------------------------------------------------------------------------
   Run:  node tools/checks/design-check.js
   No dependencies. Exits non-zero on failure.

   Sections
     1. stylesheet wiring   — every sheet a layout links exists, in the
                              intended order, master-detail.css in the right
                              slot
     2. cascade             — the record-page rules must actually win; the
                              declaration is resolved against the real load
                              order, so a rule placed before the one it
                              replaces fails here instead of on screen
     3. design rules        — one primary action per card, hairline facts,
                              aligned money columns, labelled controls, empty
                              states, dark-theme pairs
     4. layout hazards      — the two bugs that produced visible breakage:
                              a length flex-basis on a control inside a column
                              flex (becomes a height) and a negative margin on
                              a title/hint pair (overlaps the title)
   ========================================================================== */
'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..', '..');
const CSS = path.join(ROOT, 'public/assets/css');
const VIEWS = path.join(ROOT, 'resources/views');

const out = [];
const check = (name, ok, detail = '') => out.push([name, !!ok, detail]);

const walk = (dir, acc = []) => {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);
        entry.isDirectory() ? walk(full, acc) : acc.push(full);
    }
    return acc;
};

const read = file => fs.readFileSync(file, 'utf8');
const strip = text => text.replace(/\/\*[\s\S]*?\*\//g, '');

/* ---------------------------------------------------------------- parsing */

/* Single-pass rule parser. A regex that anchors a rule on the previous "}"
   misses any rule that directly follows another rule, and a brace *count*
   cannot tell a media-query rule from a base rule — both cost real time to
   debug, so the rules are parsed properly and at-rule depth is tracked. */
function parseRules(file, text) {
    const rules = [];
    let i = 0, buf = '', insideRule = false, atDepth = 0;
    while (i < text.length) {
        const ch = text[i];
        if (ch === '{') {
            const selector = buf.trim();
            buf = '';
            if (selector.startsWith('@')) { atDepth++; insideRule = false; }
            else { rules.push({ file, selector, body: '', inAtRule: atDepth > 0 }); insideRule = true; }
            i++; continue;
        }
        if (ch === '}') {
            if (insideRule) insideRule = false;
            else if (atDepth > 0) atDepth--;
            i++; continue;
        }
        if (insideRule && rules.length) rules[rules.length - 1].body += ch;
        else buf += ch;
        i++;
    }
    return rules;
}

const layouts = ['resources/views/layouts/app.blade.php',
                 'resources/views/client_portal/layouts/app.blade.php'];

/* --------------------------------------------------------- 1. sheet wiring */

const linked = [];
layouts.forEach(layout => {
    const text = read(path.join(ROOT, layout));
    linked.push(...[...text.matchAll(/assets\/css\/([a-z0-9._-]+\.css)/g)].map(m => m[1]));
});
const missing = [...new Set(linked)].filter(f => !fs.existsSync(path.join(CSS, f)));
check('every stylesheet a layout links exists', missing.length === 0, missing.join(', '));

layouts.forEach(layout => {
    const sheets = [...read(path.join(ROOT, layout)).matchAll(/assets\/css\/([a-z0-9._-]+\.css)/g)]
        .map(m => m[1]);
    const name = layout.includes('client_portal') ? 'portal layout' : 'admin layout';
    const i = sheets.indexOf('master-detail.css');
    check(`${name}: master-detail.css sits between master-form.css and master-flat.css`,
        i > -1 && i > sheets.indexOf('master-form.css') && i < sheets.indexOf('master-flat.css'),
        sheets.join(' → '));
});

/* ------------------------------------------------------------- 2. cascade */

const LOAD_ORDER = [...new Set([
    ...read(path.join(ROOT, 'resources/views/layouts/app.blade.php'))
        .matchAll(/assets\/css\/([a-z0-9._-]+\.css)/g),
].map(m => m[1]))];

const SHEETS = [
    ...LOAD_ORDER,
    'master-media.css',       /* pushed by record pages */
    'shipments.css',
    'shipping-mark.css',      /* print document, standalone */
    'tasks.css',
].filter((f, i, arr) => arr.indexOf(f) === i && fs.existsSync(path.join(CSS, f)));

const RULES = SHEETS.flatMap(f => parseRules(f, strip(read(path.join(CSS, f)))));

const rulesFor = selector => RULES.filter(r =>
    r.selector.split(',').map(s => s.trim()).includes(selector));

function winner(selector, property) {
    let value = null, owner = null;
    rulesFor(selector).filter(r => !r.inAtRule).forEach(r => {
        const re = new RegExp('(^|[;{])\\s*' + property.replace(/-/g, '\\-') + '\\s*:\\s*([^;}]+)', 'g');
        let m, last = null;
        while ((m = re.exec(r.body)) !== null) last = m[2].trim();
        if (last !== null) { value = last; owner = r.file; }
    });
    return { value, owner, count: rulesFor(selector).length };
}

function expect(selector, property, test, label) {
    const w = winner(selector, property);
    check(label, w.value !== null && test(w.value),
        `${property}: ${w.value === null ? 'not declared' : w.value} (${w.owner || '—'})`);
}

expect('.master-section-title', 'text-transform', v => v === 'none',
    'card titles are sentence case');
expect('.master-section-title', 'font-size', v => parseFloat(v) >= 15,
    'card titles use the 15px title size');
expect('.master-section-title:after', 'content', v => v === 'none',
    'no decorative rule runs from a card title to the card edge');
expect('.master-form-card .master-section-title:after', 'content', v => v === '""',
    'form cards keep their section divider');
expect('.master-facts > .master-info', 'border-top', v => /^1px solid/.test(v),
    'facts are separated by a hairline');
expect('.master-facts > .master-info', 'border-radius', v => v === '0',
    'fact cells are not rounded boxes');
expect('.master-facts > .master-info', 'background', v => v === 'none',
    'facts sit on the card surface');
expect('.master-facts .master-info > span:first-child', 'text-transform', v => v === 'uppercase',
    'field labels stay uppercase micro-labels');
expect('.master-grid', 'gap', v => v === '20px', 'the two columns are 20px apart');
expect('.master-section', 'margin-bottom', v => v === '16px', 'stacked cards are 16px apart');
expect('.ship-track li', 'background', v => v === 'none' || /transparent/.test(v),
    'stepper steps are not boxes');
expect('.ship-track li::before', 'top', v => v === '0', 'the step marker sits above its label');
expect('.ship-doc', 'border-radius', v => v === '0', 'paperwork rows are not cards');
expect('.ship-cost-totals > div', 'border-left', v => /1px solid/.test(v),
    'the cost figures form one band with hairline separators');
expect('.ship-head .record-head-actions .master-btn', 'height',
    v => parseFloat(v) <= 40, 'header actions are 38-40px');
expect('.timeline-item:before', 'width', v => parseFloat(v) <= 12, 'timeline markers are small');
expect('.master-form-card .master-section-title:after', 'background',
    v => /var\(--mc-border\)/.test(v), 'form dividers are a quiet hairline');

/* -------------------------------------------------------- 3. design rules */

const recordFiles = ['show.blade.php', 'partials/costs-card.blade.php', 'partials/documents-card.blade.php']
    .map(f => path.join(VIEWS, 'shipments', f));
const [showBlade, costsBlade, docsBlade] = recordFiles.map(read);

/* one primary action per card */
const cardPrimaries = [];
recordFiles.forEach(file => {
    const text = read(file);
    text.split(/(?=<(?:div|section)[^>]*class="[^"]*master-card)/).forEach((chunk, i) => {
        if (!/master-card/.test(chunk)) return;
        const primaries = (chunk.match(/master-btn-primary/g) || []).length;
        if (primaries > 1) cardPrimaries.push(`${path.basename(file)} card#${i}: ${primaries}`);
    });
});
check('at most one primary action per card', cardPrimaries.length === 0, cardPrimaries.join(' | '));

/* values and empty states */
const dashes = [...showBlade.matchAll(/\?:\s*'-'|\?\?\s*'-'|>-\s*</g)].map(m => m[0].trim());
check('no bare "-" value placeholders on the record page', dashes.length === 0, dashes.join(', '));
check('the overview uses the shared facts grid',
    /class="master-facts"/.test(showBlade) && fs.existsSync(path.join(VIEWS, 'components/fact.blade.php')));
check('empty lists use the shared empty state',
    (showBlade.match(/master-empty-state/g) || []).length >= 3);

/* numeric alignment */
check('the cost table aligns its money columns',
    (costsBlade.match(/class="is-num"/g) || []).length >= 4 && /th class="is-num"/.test(costsBlade));
check('the product table aligns its numeric columns',
    (showBlade.match(/class="is-num"/g) || []).length >= 6);

/* labelled controls on record views and the cost modal */
const controls = [];
[...recordFiles, path.join(VIEWS, 'shipments/partials/cost-modal.blade.php')].forEach(file => {
    const text = read(file);
    /* (?<!-) so the ">" of a PHP arrow ($x->y) does not end the tag early */
    for (const m of text.matchAll(/<(input|select|textarea)\b[\s\S]*?(?<!-)>/g)) {
        const tag = m[0];
        const type = /type="([^"]+)"/.exec(tag)?.[1] || 'text';
        if (['hidden', 'submit', 'button', 'checkbox', 'radio', 'file'].includes(type)) continue;
        const id = /id="([^"]+)"/.exec(tag)?.[1];
        const aria = /aria-label="([^"]+)"/.exec(tag)?.[1];
        const labelled = id && new RegExp(`for="${id}"`).test(text);
        if (!labelled && !aria) controls.push(`${path.basename(file)}: <${m[1]} ${id || '(no id)'}>`);
    }
});
check('every text control has a label or an aria-label', controls.length === 0, controls.join(' | '));

check('no inline font styles on the record page',
    recordFiles.every(f => !/style="[^"]*font[^"]*"/.test(read(f))));

/* dark theme pairs for semantic hues */
const shipCss = read(path.join(CSS, 'shipments.css'));
const darkBlock = shipCss.slice(shipCss.indexOf('Dark theme pairs'));
check('semantic colours have dark-theme counterparts',
    (darkBlock.match(/:root\[data-theme="dark"\]/g) || []).length >= 6,
    String((darkBlock.match(/:root\[data-theme="dark"\]/g) || []).length));

const etaBlock = shipCss.slice(shipCss.indexOf('.ship-eta-overdue'), shipCss.indexOf('.ship-eway {'));
check('ETA tints are translucent, not solid light pastels',
    (etaBlock.match(/rgba\(/g) || []).length >= 3 && !/#f[0-9a-f]{5}/i.test(etaBlock));

/* the fact value rule must not reach the badges */
const detailCss = read(path.join(CSS, 'master-detail.css'));
const unscoped = [...detailCss.matchAll(/\.master-facts[^{}]*strong[^{}]*\{/g)]
    .map(m => m[0].trim())
    .filter(sel => !/:not\(\[class\]\)/.test(sel));
check('fact value styling cannot flatten a badge (scoped :not([class]))',
    unscoped.length === 0, unscoped.join(' | '));

/* ------------------------------------------------------- 4. layout hazards */

const CONTROLS = ['input', 'select', 'textarea'];
const basisHazards = [];
RULES.forEach(r => {
    const m = /(?:^|;|\s)flex\s*:\s*\d+\s+\d+\s+([0-9.]+)(mm|px|rem|em|%)/.exec(r.body);
    if (!m) return;
    const targetsControl = CONTROLS.some(t => new RegExp(`(^|[ >.])${t}\\b|type="file"`).test(r.selector));
    const inColumn = /ship-form-field|master-field|mark-|history-form/.test(r.selector);
    if (targetsControl && inColumn) basisHazards.push(`${r.file}: ${r.selector} → ${m[1]}${m[2]}`);
});
check('no length flex-basis on a control inside a column flex (it becomes a height)',
    basisHazards.length === 0, basisHazards.join(' | '));

const negativeHints = RULES.filter(r => !r.inAtRule
    && /master-section-title\s*\+\s*\.master-sub|master-sub[^{]*\+/.test(r.selector)
    && /margin[^:]*:\s*[^;]*-/.test(r.body)).map(r => `${r.file}: ${r.selector.trim()}`);
check('no negative margin on a title/hint pair (it overlaps the title)',
    negativeHints.length === 0, negativeHints.join(' | '));

check('the document-upload controls are field-height',
    /\.ship-doc-upload \.master-select,\s*[\s\S]{0,200}height:\s*44px/.test(shipCss));

/* balanced braces everywhere */
const unbalanced = fs.readdirSync(CSS).filter(f => f.endsWith('.css')).filter(f => {
    const t = read(path.join(CSS, f));
    return t.split('{').length !== t.split('}').length;
});
check('every stylesheet has balanced braces', unbalanced.length === 0, unbalanced.join(', '));

/* ---------------------------------------------------------------- report */

const failed = out.filter(([, ok]) => !ok);
out.forEach(([name, ok, detail]) =>
    console.log((ok ? '  ok   ' : '  FAIL ') + name + (ok || !detail ? '' : '  -> ' + detail)));
console.log('\ndesign: ' + (out.length - failed.length) + ' passed, ' + failed.length + ' failed');
process.exit(failed.length ? 1 : 0);
