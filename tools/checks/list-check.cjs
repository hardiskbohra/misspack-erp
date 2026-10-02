/* ==========================================================================
   LIST CHECK — the listing page's standards
   --------------------------------------------------------------------------
   Run:  node tools/checks/list-check.cjs
   No dependencies. Exits non-zero on failure.

   A list page is where an operator spends the day, so the things that make it
   usable are worth guarding: one primary action, one toolbar geometry, money
   that lines up, badges that can be read in both themes, a row you can click,
   an empty state with a way out, and no inline styling drifting the page away
   from the shared vocabulary.

   The colour rules are derived from the model: every status in
   Shipment::statusOptions() must have a tint and a dark-theme foreground, so
   adding a status without colours fails here instead of printing an
   unstyled pill.
   ========================================================================== */
'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..', '..');
const VIEW = path.join(ROOT, 'resources/views/shipments/index.blade.php');
const CSS = path.join(ROOT, 'public/assets/css/shipments.css');
const JS = path.join(ROOT, 'public/assets/js/shipments.js');
const MODEL = path.join(ROOT, 'app/Models/Shipment.php');

const out = [];
const check = (name, ok, detail = '') => out.push([name, !!ok, detail]);

const view = fs.readFileSync(VIEW, 'utf8');
const css = fs.readFileSync(CSS, 'utf8').replace(/\/\*[\s\S]*?\*\//g, '');
const js = fs.readFileSync(JS, 'utf8');
const model = fs.readFileSync(MODEL, 'utf8');

/* ------------------------------------------------------- 1. markup hygiene */

check('the view carries no inline styles',
    !/style="/.test(view),
    [...view.matchAll(/style="[^"]*"/g)].map(m => m[0]).slice(0, 3).join(' | '));

const headers = [...view.matchAll(/<th[^>]*>[\s\S]*?<\/th>/g)].map(m => m[0]);
check('every column header is a scope="col" header cell',
    headers.length > 0 && headers.every(h => /scope="col"/.test(h)), `${headers.length} headers`);

const bodyColspan = [...view.matchAll(/<td colspan="(\d+)"/g)].map(m => parseInt(m[1], 10));
check('no cell spans more columns than the table has',
    bodyColspan.every(span => span <= headers.length),
    `spans ${bodyColspan.join(', ')} vs ${headers.length} columns`);

/* the money column */
check('the charges column is marked as numeric',
    /<th scope="col" class="is-num">Charges<\/th>/.test(view)
    && /class="ship-money is-num"/.test(view)
    && /<td class="is-num">\s*<strong>\{\{ \\App\\Models\\Shipment::formatTotals/.test(view));
check('the totals row keeps the money in the charges column',
    /<td class="is-num">[\s\S]{0,200}Filtered total/.test(view));

/* the record number is the link, the product is the second line */
const cell = /<a class="ship-cell"[\s\S]*?<\/a>/.exec(view);
check('the shipment number is the link and the identity name the second line',
    !!cell && /ship-cell-id/.test(cell[0]) && /shipment_number/.test(cell[0])
    && cell[0].indexOf('ship-cell-id') < cell[0].indexOf('ship-cell-name'));

/* clickable rows */
check('the row carries the record URL as a click target',
    /<tr class="ship-row[^"]*is-clickable"[\s\S]{0,120}data-href="\{\{ route\('shipments\.show'/.test(view));
check('the row click skips interactive elements and text selections',
    /closest\('a, button, input, select, textarea, label, form'\)/.test(js)
    && /getSelection\(\)[\s\S]{0,40}length/.test(js));

/* one primary action for the page, in the header */
/* Modals have their own footer buttons; the rule is about the page chrome. */
const chrome = view.slice(0, view.indexOf('id="quickShipmentModal"'));
const header = /@section\('page-actions'\)([\s\S]*?)@endsection/.exec(chrome)?.[1] ?? '';
const toolbar = chrome.slice(chrome.indexOf('ship-chip-bar'));
check('the header carries the page primary action',
    (header.match(/master-btn-primary/g) || []).length === 1,
    `${(header.match(/master-btn-primary/g) || []).length} in the header`);
/* The empty state's CTA and the save-view form's Save button are excluded:
   the first is the same action as the header's, shown only when the list is
   empty, and the second lives in a form that is hidden until asked for. */
const toolbarOnly = toolbar
    .replace(/@empty[\s\S]*?@endforelse/, '')
    .replace(/<form[^>]*id="saveViewForm"[\s\S]*?<\/form>/, '');
check('the toolbar carries at most one primary action (the filter submit)',
    (toolbarOnly.match(/master-btn-primary/g) || []).length <= 1,
    `${(toolbarOnly.match(/master-btn-primary/g) || []).length} in the toolbar`);
check('the primary action is bound by attribute, not by a single id',
    /\[data-quick-shipment\]/.test(js) && (view.match(/data-quick-shipment/g) || []).length >= 2);

/* toolbar */
check('the filter row has no inline padding of its own',
    /\.ship-index \.master-filter-row \{[\s\S]{0,200}padding: 14px 16px/.test(css));
check('the chips row and filter row share one inset',
    /\.ship-index \.ship-chip-bar \{[\s\S]{0,120}padding: 16px/.test(css));
check('Reset only renders when something is filtered',
    /\$filtersActive/.test(view) && /@if \(\$filtersActive\)\s*<a class="master-btn master-btn-soft"/.test(view));

/* accessibility of the controls */
const labelBlocks = [...view.matchAll(/<label\b[\s\S]*?<\/label>/g)].map(m => m[0]);
const controls = [...view.matchAll(/<(input|select|textarea)\b[\s\S]*?(?<!-)>/g)]
    .map(m => m[0])
    .filter(tag => !/type="hidden"|hidden>/.test(tag));
/* a control is labelled when it has an aria-label, a <label for> pointing at
   its id, or a wrapping <label> */
const unlabelled = controls.filter(tag => {
    const id = /id="([^"]+)"/.exec(tag)?.[1];
    const labelledFor = id && labelBlocks.some(block =>
        new RegExp(`<label[^>]*for="${id}"`).test(block));
    const wrapped = labelBlocks.some(block => block.includes(tag));

    return !/aria-label=/.test(tag) && !labelledFor && !wrapped;
});
check('every toolbar control has an aria-label', unlabelled.length === 0,
    unlabelled.map(t => t.slice(0, 60)).join(' | '));
check('the row action button names its record',
    /aria-label="Actions for \{\{ \$shipment->shipment_number \}\}"/.test(view)
    && /aria-haspopup="true" aria-expanded="false"/.test(view));
check('the menu reports its state and closes on Escape',
    /setAttribute\('aria-expanded'/.test(fs.readFileSync(path.join(ROOT, 'public/assets/js/app-layout.js'), 'utf8'))
    && /e\.key !== 'Escape'/.test(fs.readFileSync(path.join(ROOT, 'public/assets/js/app-layout.js'), 'utf8')));

/* empty state */
check('the empty state explains itself and offers a way out',
    /ship-empty-title/.test(view) && /Clear filters/.test(view) && /data-quick-shipment/.test(view));

/* ------------------------------------------------------- 2. status colours */

const STATUSES = [...model.matchAll(/public const STATUS_(\w+) = '([^']+)'/g)].map(m => m[2]);
check('the model exposes its statuses', STATUSES.length >= 6, STATUSES.join(', '));

const slug = status => status.replace(/_/g, '-');
const missingTone = STATUSES.filter(status =>
    !new RegExp(`\\.ship-status\\.status-${slug(status)}\\s*\\{[^}]*--tone-bg`).test(css));
check('every status has a tint', missingTone.length === 0, missingTone.join(', '));

const missingDark = STATUSES.filter(status =>
    !new RegExp(`:root\\[data-theme="dark"\\] \\.ship-index \\.ship-status\\.status-${slug(status)}`).test(css));
check('every status has a dark-theme foreground', missingDark.length === 0, missingDark.join(', '));

check('status chips are tinted, not filled',
    /\.ship-index \.ship-status,[\s\S]{0,400}background: var\(--tone-bg\)/.test(css)
    && /background: var\(--tone-bg\)[\s\S]{0,200}color: var\(--tone-fg\)/.test(css));
check('status chips carry a colour dot as well as the label',
    /\.ship-index \.ship-status::before/.test(css));
check('the raw label palette is gone (no unmixed CSS colours)',
    !/\.label-\d \{[^}]*background:(red|green|yellow|pink|blue|orange)/.test(css));

const missingLabel = [0, 1, 2, 3, 4, 5].filter(n =>
    !new RegExp(`\\.label-${n} \\{[^}]*--tone-bg`).test(css)
    || !new RegExp(`:root\\[data-theme="dark"\\] \\.label-${n} \\{[^}]*--tone-fg`).test(css));
check('every shipment-label tone has a light and dark value', missingLabel.length === 0,
    missingLabel.map(n => `label-${n}`).join(', '));

const TYPES = [...model.matchAll(/public const TYPE_(\w+) = '([^']+)'/g)].map(m => m[2]);
const missingType = TYPES.filter(type =>
    !new RegExp(`\\.ship-type\\.type-${type}\\s*\\{[^}]*--tone-bg`).test(css)
    || !new RegExp(`:root\\[data-theme="dark"\\] \\.ship-index \\.ship-type\\.type-${type}`).test(css));
check('every shipment type has a light and dark tone', missingType.length === 0,
    missingType.join(', '));

/* ------------------------------------------------------------ 3. the rows */

check('rows are dense enough to scan',
    /\.ship-index \.master-table th,\s*\n?\.ship-index \.master-table td \{\s*\n?\s*padding: 12px 14px/.test(css));
check('the money cell uses tabular figures through .is-num',
    /\.master-table td\.is-num \{[\s\S]{0,80}font-variant-numeric: tabular-nums/.test(
        fs.readFileSync(path.join(ROOT, 'public/assets/css/master-detail.css'), 'utf8')));
check('the action column is right-aligned', /\.ship-index \.master-table td:last-child \{\s*\n?\s*text-align: right/.test(css));
check('the order caption is one short line with the full rule as a tooltip',
    /<p class="ship-order-hint"\s*\n?\s*title="Open shipments first/.test(view)
    && /Open shipments first &middot; closed block below/.test(view)
    && !/Order: <strong>/.test(view));
check('the closed divider carries a count',
    /ship-group-count/.test(view) && /\.ship-index \.ship-group-count/.test(css));

/* dark theme: every new surface uses a token, none a light-only literal */
const listSection = css.slice(css.indexOf('4. Shipment List'));
const lightLiterals = [...listSection.matchAll(/#[0-9a-f]{3,8}\b/gi)]
    .map(m => m[0])
    .filter(hex => !/^#fff$/i.test(hex));
check('the list rules use theme tokens rather than fixed colours',
    lightLiterals.length === 0, lightLiterals.join(', '));

/* --------------------------------------------- 4. the working surface */

/* applied filters — a filter nobody can see is a filter nobody can undo */
const appliedChips = [...view.matchAll(/\$chipUrl\('(\w+)'\)/g)].map(m => m[1]);
check('every filter the page can hold is shown as an applied chip',
    ['search', 'status', 'currency', 'from_date', 'attention'].every(key => appliedChips.includes(key)),
    appliedChips.join(', '));
check('an applied chip removes only its own filter and keeps the rest',
    /request\(\)->except\(\[\$key, 'page', 'saved_view'\]\)/.test(view)
    && /'saved_view'/.test(view));
check('the applied strip carries a clear-all escape',
    /class="ship-applied-clear"/.test(view) && /Clear all filters/.test(view));
check('the applied strip is themed rather than light-only',
    /\.ship-index \.ship-applied-chip \{[\s\S]{0,400}var\(--mc-card-soft\)/.test(css)
    && /\.ship-applied-x:hover \{[\s\S]{0,80}var\(--danger-light\)/.test(css));

/* row density — a long list should let the reader decide how long */
check('the density control states and carries its own state',
    /class="ship-density desktop-only" role="group" aria-label="Row density"/.test(view)
    && (view.match(/class="ship-density-btn"/g) || []).length === 2
    && (view.match(/aria-pressed="(true|false)"/g) || []).length >= 2);
check('the density choice is remembered on the device',
    /misspack\.shipments\.density/.test(js) && /localStorage/.test(js)
    && /setAttribute\('data-density'/.test(js) || /attribute\('data-density'/.test(js));
check('both densities actually change the row geometry',
    /\[data-density="compact"\] \.master-table th,[\s\S]{0,120}padding: 7px 14px/.test(css)
    && /--ship-line: 18px/.test(css));
check('the density is applied before the table paints',
    /document\.readyState === 'loading'/.test(js));

/* the desktop grid — header and totals stay with the reader */
check('the desktop list scrolls with a pinned header and pinned totals',
    /@media \(min-width: 1200px\) \{[\s\S]{0,260}\.ship-index \.master-table-wrap \{[\s\S]{0,120}max-height: calc\(100vh - 320px\)/.test(css)
    && /\.ship-index \.master-table thead th \{[\s\S]{0,160}position: sticky;\s*\n?\s*top: 0/.test(css)
    && /\.ship-index \.master-table tfoot td \{[\s\S]{0,160}position: sticky;\s*\n?\s*bottom: 0/.test(css));
check('the sticky header keeps its hairline (borders separated, not collapsed)',
    /@media \(min-width: 1200px\)[\s\S]{0,600}border-collapse: separate/.test(css));
check('the header lifts off the rows once the list is scrolled',
    /addEventListener\('scroll', sync, \{ passive: true \}\)/.test(js)
    && /\.is-scrolled thead th/.test(css));

/* the stacked mobile card: the same row, still labelled */
const mobileLabels = [...view.matchAll(/data-label="([^"]+)"/g)].map(m => m[1]);
check('the stacked rows keep their column names',
    mobileLabels.length >= 6 && /content:attr\(data-label\)/.test(css),
    mobileLabels.join(', '));
/* the one placeholder rule left in the file belongs to the *form* page's items
   table, so this looks for the list-page signature specifically */
check('the mobile card no longer relies on empty ::before placeholders',
    /attr\(data-label\)/.test(css)
    && !/\.master-table td:nth-child\(\d\)::before\{content:"";\}/.test(css));
check('the mobile card shadow is a theme token, not a fixed black',
    !/box-shadow:0 4px 18px rgba\(0,0,0,\.06\)/.test(css));

/* one rhythm: every first line on the same baseline, every second line too */
const lineUsers = (() => {
    const at = css.indexOf('min-height: var(--ship-line)');
    if (at < 0) return 0;
    const brace = css.lastIndexOf('{', at);
    if (brace < 0) return 0;
    const selectors = css.slice(Math.max(css.lastIndexOf('}', brace), 0) + 1, brace);
    return selectors.split(',').filter(sel => sel.trim()).length;
})();
check('every first line in a row shares one line box',
    /--ship-line: 20px/.test(css) && lineUsers >= 6, `${lineUsers} cells`);
check('every second line shares its own line box',
    /--ship-sub: 16px/.test(css) && /\.ship-index td \.master-sub \{[\s\S]{0,80}line-height: var\(--ship-sub\)/.test(css));

/* the closed divider is a row that spans the table: it must stay a table cell
   or the browser confines it to the first column */
/* read the real bodies: a fixed-width window over the file would reach into the
   next rule and "find" the flex that legitimately sits in .ship-group-inner */
const dividerCellBodies = [];
for (let at = css.indexOf('.ship-index .ship-group-row td {');
     at > -1;
     at = css.indexOf('.ship-index .ship-group-row td {', at + 1)) {
    dividerCellBodies.push(css.slice(at, css.indexOf('}', at)));
}
check('the closed divider spans the whole row',
    /<td colspan="8">[\s\S]{0,500}ship-group-inner/.test(view)
    && /\.ship-index \.ship-group-inner \{[\s\S]{0,120}display: flex/.test(css)
    && dividerCellBodies.length > 0
    && dividerCellBodies.every(body => !/display\s*:\s*(inline-)?(flex|grid)/.test(body)),
    `${dividerCellBodies.length} rule(s)`);
check('the closed divider keeps its count at the far edge',
    /\.ship-index \.ship-group-inner \{[\s\S]{0,160}justify-content: space-between/.test(css));
check('the divider stays a hairline band, not a tinted block',
    /\.ship-index \.ship-group-row td \{[\s\S]{0,400}background: var\(--mc-card\)/.test(css));

/* ---------------------------------------------------------------- report */

const failed = out.filter(([, ok]) => !ok);
out.forEach(([name, ok, detail]) =>
    console.log((ok ? '  ok   ' : '  FAIL ') + name + (ok || !detail ? '' : '  -> ' + detail)));
console.log('\nlist: ' + (out.length - failed.length) + ' passed, ' + failed.length + ' failed');
process.exit(failed.length ? 1 : 0);
