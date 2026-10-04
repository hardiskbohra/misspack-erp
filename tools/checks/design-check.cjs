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

const responsiveSource = read(path.join(ROOT, 'resources/css/layout/responsive.css'));
const masterEntry = read(path.join(ROOT, 'resources/css/master.css'));
check('responsive rules are the final design-system import',
    masterEntry.lastIndexOf("@import './layout/responsive.css';") > masterEntry.lastIndexOf("@import './pages/module-adapters.css';"));
check('all project breakpoint bands are defined',
    ['min-width: 1440px', 'min-width: 1200px', 'max-width: 1439px', 'min-width: 992px', 'max-width: 1199px', 'min-width: 768px', 'max-width: 991px', 'max-width: 767px']
        .every(band => responsiveSource.includes(band)));
check('compact forms preserve intentional two-column variants',
    /\.cf-form-grid:not\(\.two\)/.test(responsiveSource));
check('mobile secondary data is opt-in, not globally discarded',
    /\.ui-mobile-secondary/.test(responsiveSource) && /data-mobile-priority="secondary"/.test(responsiveSource));
check('mobile card tables use labels and an opt-in wrapper',
    /\.ui-mobile-cards/.test(responsiveSource) && /td\[data-label\]/.test(responsiveSource));
check('secondary cells remain hidden after mobile-card display rules',
    /\.ui-mobile-cards td\.ui-mobile-secondary/.test(responsiveSource)
    && /\.ui-mobile-cards td\[data-mobile-priority="secondary"\]/.test(responsiveSource));

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
    'payslip.css',            /* the payslip sheet: pushed in the app, linked by the print view */
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

/* A label is one line. .master-sub is display:block, so a hint written with it
   inside a <label> prints on the line below and pushes that field's control out
   of line with the ones beside it — the row looks broken although nothing is
   wrong with the grid. The app's own convention is plain text in the label:
   "Title (optional)". */
const blockHintInLabel = walk(VIEWS).filter(f => f.endsWith('.blade.php'))
    .filter(f => /<label[^>]*class="[^"]*\bmaster-label\b[^"]*"[^>]*>[^<]*<span[^>]*class="[^"]*\bmaster-sub\b/.test(read(f)));
check('no field label carries a block hint on its own line', blockHintInLabel.length === 0,
    blockHintInLabel.map(f => path.relative(ROOT, f)).join(', '));

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

/* a flex/grid cell leaves the table layout: the browser wraps it in an
   anonymous cell in the first column, so colspan quietly stops spanning and the
   row's own layout is gone. Inside a media query it can be a deliberate stacked
   card, so only the page layout is judged here. */
/* every sheet, not just the shared ones: this is a module hazard too (the users
   table grids its cells on a phone, which is where it was found) */
const ALL_RULES = fs.readdirSync(CSS).filter(f => f.endsWith('.css'))
    .flatMap(f => parseRules(f, strip(read(path.join(CSS, f)))));
const flexCells = ALL_RULES.filter(r => /(^|[\s,>])t[dh](?![\w-])/.test(r.selector)
    && /display\s*:\s*(inline-)?(flex|grid)/.test(r.body)
    /* inside a media query a stacked card may grid its cells, but only if it
       leaves the spanning ones alone */
    && (!r.inAtRule || !/:not\(\[colspan\]\)/.test(r.selector)))
    .map(r => `${r.file}: ${r.selector.replace(/\s+/g, ' ').trim()}`);
check('no table cell is turned into a flex/grid box (a spanning cell stops spanning)',
    flexCells.length === 0, flexCells.join(' | '));

/* opacity below 1 on a table row or cell makes a stacking context: everything
   inside it paints in that row's turn, so a row menu is covered by the rows
   after it and comes out see-through. Muting belongs in colour, not alpha. */
const fadedCells = ALL_RULES.filter(r => /(^|[\s,>\.])t[dh](?![\w-])|\btr\b|\.master-table/.test(r.selector)
    && /opacity\s*:\s*(0?\.\d+)/.test(r.body))
    .map(r => `${r.file}: ${r.selector.replace(/\s+/g, ' ').trim()}`);
check('no table row or cell is made translucent (it swallows the row menu)',
    fadedCells.length === 0, fadedCells.join(' | '));

/* the row menu is one component: a sheet that loads after master-index.css and
   re-declares it silently wins the cascade (master-show.css did exactly that,
   putting the dark-theme menu back to fixed light-theme colours) */
const owner = LOAD_ORDER.indexOf('master-index.css');
const laterSheets = [...new Set([
    ...LOAD_ORDER.slice(owner + 1),
    'master-media.css', 'shipments.css', 'shipping-mark.css', 'tasks.css',
    'users.css', 'vendors.css', 'clients.css',
])].filter(f => f !== 'master-index.css' && fs.existsSync(path.join(CSS, f)));
const reDeclared = laterSheets.filter(f => /^\s*\.master-dropdown-menu\s*\{/m.test(strip(read(path.join(CSS, f)))));
check('only master-index.css declares the row menu (no later sheet re-declares it)',
    owner > -1 && reDeclared.length === 0, reDeclared.join(', '));

const indexMenu = strip(read(path.join(CSS, 'master-index.css')));
const panelRule = (indexMenu.match(/\.master-dropdown-menu\s*\{[\s\S]{0,400}?\}/) || [''])[0];
const panelZ = Number((panelRule.match(/z-index:\s*(\d+)/) || [])[1]);
check('the row menu is placed against the viewport, above the sticky header and the topbar',
    /position:\s*fixed/.test(panelRule) && panelZ >= 1100 && panelZ < 9999, `z-index ${panelZ || '?'}`);
check('the row menu needs no row-raising or flip class any more',
    !/is-menu-open/.test(indexMenu) && !/\.master-dropdown\.drop-up/.test(indexMenu));

check('the floating panel is opaque, so no row can read through it',
    /background-color:\s*var\(--mc-card,\s*#[0-9a-f]{3,6}\)/i.test(panelRule)
    && !/opacity/.test(panelRule));

/* every item in the panel has one shape: an icon slot and a label from the
   same left edge. A `.master-btn` inside the menu centres itself and wears its
   own background, which is how "Public Link" ended up centre-aligned and
   icon-less among left-aligned siblings. */
check('every row-menu item starts at the same left edge, with an icon slot',
    /\.master-dropdown-menu a,\s*\n\.master-dropdown-menu button\{[\s\S]{0,700}justify-content:flex-start/.test(indexMenu)
    && /\.master-dropdown-menu i\{[\s\S]{0,160}flex:0 0 18px/.test(indexMenu)
    && /\.master-dropdown-menu a,[\s\S]{0,700}border-radius:0/.test(indexMenu));

/* the shared row-action menu is drawn on --mc-card, so it must not be painted
   with fixed light-theme values: #2b3445 menu text on the dark card is
   invisible, and a #e9efff hover is a light chip on a dark toolbar */
const indexCss = read(path.join(CSS, 'master-index.css'));
const menuLiterals = [
    /\.master-dropdown-toggle \{[\s\S]{0,240}color:\s*#[0-9a-f]{3,6}/i,
    /\.master-dropdown-menu a,[\s\S]{0,240}color:\s*#[0-9a-f]{3,6}/i,
].filter(re => re.test(indexCss));
check('the shared row-action menu is themed, not fixed to a light palette',
    menuLiterals.length === 0
    && /:root\[data-theme="dark"\] \.master-dropdown-menu \.danger/.test(indexCss),
    String(menuLiterals.length));

/* ------------------------------------------------------- 5. modal contract
   Every dialog in the app is .master-modal > .master-modal-card, and the card
   is a screen-bounded column: the form fills it, and the body — the only
   flexible child — is what scrolls. That chain used to hang on :has(); where
   the browser does not support it, a tall quick-entry form stayed
   content-sized, overflowed the card's hidden overflow, and the last fields
   could not be reached at all (no scrollbar, nothing to drag). */

const modalCss = indexCss.slice(indexCss.indexOf('.master-modal {'), indexCss.indexOf('.master-modal-grid'));

check('the dialog card is a screen-bounded flex column',
    winner('.master-modal-card', 'display').value === 'flex'
    && winner('.master-modal-card', 'flex-direction').value === 'column'
    && winner('.master-modal-card', 'overflow').value === 'hidden'
    && /vh/.test(winner('.master-modal-card', 'max-height').value || ''));

check('the card scrolls through its body: the form fills the card and may shrink',
    winner('.master-modal-card > form', 'display').value === 'flex'
    && winner('.master-modal-card > form', 'flex-direction').value === 'column'
    && winner('.master-modal-card > form', 'flex').value === '1 1 auto'
    && winner('.master-modal-card > form', 'min-height').value === '0'
    /* both halves of the platform gate: with :has() the old rule handed the
       form the same value, without it the tall form did not scroll. Comments
       are stripped first — this very explanation mentions the selector. */
    && !/:has\(/.test(strip(modalCss)));

check('the dialog body is the scroll region and can shrink below its content',
    winner('.master-modal-body', 'overflow-y').value === 'auto'
    && winner('.master-modal-body', 'flex').value === '1 1 auto'
    && winner('.master-modal-body', 'min-height').value === '0');

check('a trailing action row stays at the bottom of the card',
    winner('.master-modal-card > form > .master-modal-footer:last-child', 'margin-top').value === 'auto');

/* the markup half of the contract: the body is a child of the card or of the
   card's form. One level deeper and no rule can hand it the overflow. */
const VOID_TAGS = new Set(['input', 'br', 'hr', 'img', 'meta', 'link', 'source', 'area', 'col', 'embed', 'track', 'wbr']);
const modalShapeHazards = [];
walk(VIEWS).filter(f => f.endsWith('.blade.php')).forEach(file => {
    const text = read(file)
        .replace(/\{\{--[\s\S]*?--\}\}/g, '')
        .replace(/\{\{[\s\S]*?\}\}|\{!![\s\S]*?!!\}/g, '');
    let m;
    const re = /<div class="master-modal-card[^"]*"[^>]*>/g;
    while ((m = re.exec(text)) !== null) {
        const rest = text.slice(m.index + m[0].length);
        const at = rest.search(/<div class="master-modal-body["']/);
        if (at === -1) continue;
        let depth = 0;
        for (const tag of rest.slice(0, at).matchAll(/<(\/?)([a-z][\w-]*)((?:"[^"]*"|'[^']*'|[^>"'])*?)(\/?)>/gi)) {
            if (VOID_TAGS.has(tag[2].toLowerCase()) || tag[4]) continue;
            depth += tag[1] ? -1 : 1;
        }
        if (depth > 1) {
            modalShapeHazards.push(`${path.relative(ROOT, file)} @${text.slice(0, m.index).split('\n').length} (body nested ${depth} deep)`);
        }
    }
});

check('every dialog keeps its body inside the card or the card\'s form',
    modalShapeHazards.length === 0, modalShapeHazards.join(' | '));

/* ------------------------------------ 6. choice chips and select lists
   Two small components, and each one has exactly one owner. `.master-chip` is
   the record page's neutral meta chip (master-detail.css). The segmented
   control that picks one of a few options is `.master-choice-*`, written in
   master-form.css and master-index.css and kept identical in both. While the
   two shared the name `master-chip`, master-detail.css loaded last and drew
   its bordered meta box — padding, border, radius eight — around every
   Credit/Debit choice, so the choice looked like a framed button inside a
   box. A shared name is not a style bug you can override away: the next sheet
   to load moves it again. */

const choiceSheets = ['master-form.css', 'master-index.css'];
const choiceOwners = SHEETS.filter(f => /\.master-choice-/.test(strip(read(path.join(CSS, f)))));

check('the choice chip has exactly one owner and the meta chip keeps its name',
    choiceOwners.length === 2 && choiceSheets.every(f => choiceOwners.includes(f)),
    choiceOwners.join(', '));

/* the old shared name must not survive anywhere — in a sheet or in a view */
const sharedName = [read(path.join(CSS, 'master-detail.css'))]
    .concat(walk(VIEWS).filter(f => f.endsWith('.blade.php')).map(read))
    .filter(t => /master-chip-group|\.master-chip\s+(input|span)|(^|[\s"])master-chip-group/.test(t));
check('no view or sheet still calls the choice chip by the meta chip\u2019s name',
    sharedName.length === 0, sharedName.length + ' file(s)');

const metaAsControl = walk(VIEWS).filter(f => f.endsWith('.blade.php')).filter(f =>
    /<label[^>]*class="[^"]*\bmaster-chip\b[^"]*"/.test(read(f)));
check('no choice label wears the meta chip class', metaAsControl.length === 0,
    metaAsControl.map(f => path.relative(ROOT, f)).join(', '));

/* the choice itself: no box, a themed fill, a keyboard ring */
expect('.master-choice-chip span', 'border', v => /^0$/.test(v.trim()),
    'the choice chip draws no border box');
expect('.master-choice-chip span', 'background', v => /var\(--/.test(v),
    'the unselected choice chip is filled from a theme token');
expect('.master-choice-chip:hover span', 'background', v => /var\(--/.test(v),
    'the hover is a themed fill step, not a literal');
expect('.master-choice-chip input:focus-visible + span', 'outline',
    v => /^2px solid var\(--/.test(v.trim()),
    'the choice chip keeps a visible keyboard focus ring');

/* the two copies are one component: identical rules, in the same order */
const choiceRules = file => RULES
    .filter(r => r.file === file && /master-choice|(credit|debit|green|yellow|blue|red)-chip/.test(r.selector))
    .map(r => r.selector.replace(/\s+/g, ' ').trim() + '{' + r.body.replace(/\s+/g, ' ').trim() + '}');
const copies = choiceSheets.map(choiceRules);
check('the two copies of the choice chip are identical',
    copies[0].length > 0 && copies[0].join('|') === copies[1].join('|'),
    copies.map(c => c.length + ' rules').join(' vs '));

/* the option list: portaled to the page, and above the dialog it belongs to */
const selects = strip(read(path.join(ROOT, 'public/assets/js/master-selects.js')))
    .split('\n').filter(l => !/^\s*\/\//.test(l)).join('\n');
const parentLine = (selects.match(/dropdownParent:[^\n]*/) || [''])[0];
check('a select\u2019s option list is parented to the page, not to the dialog',
    /\$\('body'\)/.test(parentLine) && !/master-modal/.test(parentLine), parentLine.trim());

const modalZ = winner('.master-modal', 'z-index');
const dropdownZ = winner('.select2-dropdown', 'z-index');
const dropdownsSheet = strip(read(path.join(ROOT, 'resources/css/components/dropdowns.css')));
const elevationSheet = strip(read(path.join(ROOT, 'resources/css/tokens/elevation.css')));
const selectLayer = Number((/--ui-z-select:\s*(\d+)/.exec(elevationSheet) || [])[1] || 0);
const drawerLayer = Number((/--ui-z-drawer:\s*(\d+)/.exec(elevationSheet) || [])[1] || 0);
const select2Override = /body\[data-ui-shell\]\s+\.select2-dropdown\s*\{[^}]*z-index:\s*var\(--ui-z-select/.test(dropdownsSheet);
check("a select's list stays above drawers and dialogs it was opened from",
    modalZ.value !== null && dropdownZ.value !== null
    && parseInt(dropdownZ.value, 10) > parseInt(modalZ.value, 10)
    && selectLayer > modalZ.value && selectLayer > drawerLayer && select2Override,
    'Select2 layer ' + selectLayer + ' vs drawer ' + drawerLayer + ' / dialog ' + modalZ.value);

/* --------------------------------------------------------------------------
   5. the shared vocabulary — a class a page wears must be a class a sheet owns
   The same fault has now cost three rounds: `.master-form-grid` (named by five
   dialogs, defined only inside the price-calculator sheet), `.master-section-
   label` / `.master-info-box` (named, never defined anywhere) and
   `.master-detail-list` (named by three record pages, never defined). Each one
   renders as an unstyled block and reads as "the layout is broken", and none of
   them is visible in a diff.

   The rule is therefore mechanical: every `master-*` class in a `class="…"`
   attribute must be declared by a stylesheet — or toggled by a script, which is
   how a state class like `.is-open` is a legitimate name. `KNOWN` is the debt
   that predates this rule, one module per line. It is **not** an ignore list: a
   known offender that has since been defined or removed fails the check as
   stale, so the list can only get shorter.
   -------------------------------------------------------------------------- */

const declaredClasses = new Set();
const jsClassNames = new Set();

for (const file of walk(CSS).filter(f => f.endsWith('.css'))) {
    for (const m of strip(read(file)).matchAll(/\.(master-[a-z0-9-]+)/g)) {
        declaredClasses.add(m[1]);
    }
}

for (const file of walk(path.join(ROOT, 'public/assets/js')).filter(f => f.endsWith('.js'))) {
    for (const m of read(file).matchAll(/['"`](master-[a-z0-9-]+)/g)) {
        jsClassNames.add(m[1]);
    }
}

const wornClasses = new Map();
for (const file of walk(VIEWS).filter(f => f.endsWith('.blade.php'))) {
    for (const m of read(file).matchAll(/class="([^"]*)"/g)) {
        for (const token of m[1].split(/\s+/)) {
            if (!/^master-[a-z0-9-]+$/.test(token)) continue;
            if (!wornClasses.has(token)) wornClasses.set(token, new Set());
            wornClasses.get(token).add(path.relative(ROOT, file));
        }
    }
}

/* Where a `master-*` name is worn but no sheet owns it, today. Modules already
   shipped keep their own naming until their turn; nothing new may join them. */
const KNOWN = [
    'master-attention-card',
    'master-calc-input', 'master-delete-btn',
    'master-form-group',
    'master-save', 'master-text', 'master-wrap',
];

const undressed = [...wornClasses.keys()]
    .filter(c => !declaredClasses.has(c) && !jsClassNames.has(c) && !KNOWN.includes(c))
    .sort();

check('every master-* class a view wears is declared by a sheet',
    undressed.length === 0,
    undressed.map(c => c + ' (' + [...wornClasses.get(c)].join(', ') + ')').join(' | '));

const mended = KNOWN.filter(c => declaredClasses.has(c) || jsClassNames.has(c) || !wornClasses.has(c));

check('no mended class is still carried as debt',
    mended.length === 0,
    mended.join(', ') + ' — remove from KNOWN, it is defined or gone now');

/* A fact is a hairline row. `.master-info` on its own is a bordered box, and
   `.master-card--flat .master-info` re-surfaces it — so the facts rule has to
   repeat itself with the card in front, or a flat card turns a record back
   into a wall of boxes. That is exactly what the employee record looked like. */
const facts = strip(read(path.join(CSS, 'master-detail.css')));
const factsBody = (facts.match(/\.master-facts > \.master-info,[\s\S]*?\{([^}]*)\}/) || [, ''])[1];

check('a fact is a hairline row, not a box per field',
    /border:\s*0/.test(factsBody) && /background:\s*none/.test(factsBody),
    factsBody.trim().replace(/\s+/g, ' '));

check('and the flat card cannot re-surface it as a panel row',
    /\.master-card--flat \.master-facts > \.master-info/.test(facts),
    'the compound selector is missing — a later file wins ties');

/* `2fr 1fr` is a main column and an aside. A tab that is four cards of the same
   weight is not that, and read as 3:1 — an equal variant exists for it, and the
   record's details tab wears it. */
check('the grid has an equal-columns variant for cards of the same weight',
    /\.master-grid\.is-even \{[^}]*grid-template-columns: repeat\(2, minmax\(0, 1fr\)\)/.test(
        strip(read(path.join(CSS, 'master-detail.css')))),
    'expected `.master-grid.is-even` with two equal columns in master-detail.css');

/* Three up, on a high breakpoint only: a third column at 900px is narrower than
   its own label. */
const form = strip(read(path.join(CSS, 'master-form.css')));
check('the field grid has a three-up variant behind a wide breakpoint',
    /@media \(min-width: 1200px\) \{\s*\.master-form-grid\.is-three/.test(form),
    'expected `.master-form-grid.is-three` inside `@media (min-width: 1200px)`');

check('a long form can pin its action bar to the foot of the viewport',
    /\.master-actions\.is-sticky\s*\{[^}]*position:\s*sticky/.test(form),
    'expected `.master-actions.is-sticky { position: sticky }`');

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
