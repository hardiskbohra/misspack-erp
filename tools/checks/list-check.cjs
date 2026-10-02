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
/* the list chrome is shared: chips, applied strip, density, pinned grid, the
   mobile card, the divider row, the totals row and the empty state all live
   here so every module list is the same surface */
const LIST_CSS = path.join(ROOT, 'public/assets/css/master-list.css');
const CASHFLOW_VIEW = path.join(ROOT, 'resources/views/cashflows/index.blade.php');
/* the archive is the second table of the same module: same shell, its own
   columns, and it has to hold its grid for the same reason */
const DOCUMENTS_VIEW = path.join(ROOT, 'resources/views/cashflows/documents.blade.php');
const CASHFLOW_CSS = path.join(ROOT, 'public/assets/css/cashflows.css');
const JS = path.join(ROOT, 'public/assets/js/shipments.js');
const LAYOUT_JS = path.join(ROOT, 'public/assets/js/app-layout.js');
const MASTER_INDEX = path.join(ROOT, 'public/assets/css/master-index.css');
const MODEL = path.join(ROOT, 'app/Models/Shipment.php');

const out = [];
const check = (name, ok, detail = '') => out.push([name, !!ok, detail]);

const view = fs.readFileSync(VIEW, 'utf8');
const documentsView = fs.readFileSync(DOCUMENTS_VIEW, 'utf8');
const css = fs.readFileSync(CSS, 'utf8').replace(/\/\*[\s\S]*?\*\//g, '');
const js = fs.readFileSync(JS, 'utf8');
const listJs = fs.readFileSync(path.join(ROOT, 'public/assets/js/master-list.js'), 'utf8');
const cashflowJs = fs.readFileSync(path.join(ROOT, 'public/assets/js/cashflows.js'), 'utf8');
const layoutJs = fs.readFileSync(LAYOUT_JS, 'utf8');
const layoutCss = fs.readFileSync(MASTER_INDEX, 'utf8').replace(/\/\*[\s\S]*?\*\//g, '');
const listCss = fs.readFileSync(LIST_CSS, 'utf8').replace(/\/\*[\s\S]*?\*\//g, '');
const cashView = fs.readFileSync(CASHFLOW_VIEW, 'utf8');
const cashCss = fs.readFileSync(CASHFLOW_CSS, 'utf8').replace(/\/\*[\s\S]*?\*\//g, '');
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
    && /<td class="is-num">\s*<strong>\{\{ \\App\\Models\\Shipment::formatInr\(\$pageSpendInr\)/.test(view));
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
    /closest\('a, button, input, select, textarea, label, form, \.master-dropdown'\)/.test(listJs)
    && /getSelection\(\)[\s\S]{0,40}length/.test(listJs)
    && /MasterList\.rowNavigation/.test(js));

/* one primary action for the page, in the header */
/* Modals have their own footer buttons; the rule is about the page chrome. */
const chrome = view.slice(0, view.indexOf('id="quickShipmentModal"'));
const header = /@section\('page-actions'\)([\s\S]*?)@endsection/.exec(chrome)?.[1] ?? '';
const toolbar = chrome.slice(chrome.indexOf('master-list-bar'));
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
    /\.master-list \.master-list-bar \{[\s\S]{0,120}padding: 16px/.test(listCss));
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
    /master-list-empty-title/.test(view) && /Clear filters/.test(view) && /data-quick-shipment/.test(view));

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
    /<p class="master-list-hint"\s*\n?\s*title="Open shipments first/.test(view)
    && /Open shipments first &middot; closed block below/.test(view)
    && !/Order: <strong>/.test(view));
check('the closed divider carries a count',
    /master-list-group-count/.test(view) && /\.master-list-group-count/.test(listCss));

/* dark theme: every new surface uses a token, none a light-only literal */
/* a raw colour is a fixed colour; a fallback inside var() is the token
   system's safety net and stays allowed */
const listSection = (listCss + css.slice(css.indexOf('4. Shipment List')))
    .replace(/var\(\s*--[\w-]+\s*,[^()]*\)/g, 'var(--token)');
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
    /class="master-list-applied-clear"/.test(view) && /Clear all filters/.test(view));
check('the applied strip is themed rather than light-only',
    /\.master-list \.master-list-applied-chip \{[\s\S]{0,400}var\(--mc-card-soft\)/.test(listCss)
    && /\.master-list-applied-x:hover \{[\s\S]{0,80}var\(--danger-light\)/.test(listCss));

/* row density — a long list should let the reader decide how long */
const densityContract = view => /role="group" aria-label="Row density"/.test(view)
    && (view.match(/class="master-list-density-btn"/g) || []).length === 2
    && (view.match(/aria-pressed="(true|false)"/g) || []).length >= 2;

check('the density control states and carries its own state',
    densityContract(view) && /class="master-list-density desktop-only"/.test(view));

/* one implementation: the module passes its root and its storage key to the
   shared toolkit, so the two lists cannot behave differently */
check('the density choice is remembered on the device by the shared toolkit',
    /misspack\.shipments\.density/.test(js) && /misspack\.cashflows\.density/.test(cashflowJs)
    && /localStorage/.test(listJs) && /setAttribute\('data-density'/.test(listJs)
    && /MasterList\.density/.test(js) && /MasterList\.density/.test(cashflowJs));
check('both densities actually change the row geometry',
    /\[data-density="compact"\] \.master-table th,[\s\S]{0,120}padding: 7px 14px/.test(listCss)
    && /--ship-line: 18px/.test(css));
check('the density is applied before the table paints',
    /document\.readyState === 'loading'/.test(js + cashflowJs)
    && /MasterList\.density/.test(js) && /MasterList\.density/.test(cashflowJs));

/* the desktop grid — header and totals stay with the reader */
check('the desktop list scrolls with a pinned header and pinned totals',
    /@media \(min-width: 1200px\) \{[\s\S]{0,260}\.master-list \.master-table-wrap \{[\s\S]{0,120}max-height: calc\(100vh - 320px\)/.test(listCss)
    && /\.master-list \.master-table thead th \{[\s\S]{0,160}position: sticky;\s*\n?\s*top: 0/.test(listCss)
    && /\.master-list \.master-table tfoot td \{[\s\S]{0,160}position: sticky;\s*\n?\s*bottom: 0/.test(listCss));
check('the sticky header keeps its hairline (borders separated, not collapsed)',
    /@media \(min-width: 1200px\)[\s\S]{0,600}border-collapse: separate/.test(listCss));
check('the header lifts off the rows once the list is scrolled',
    /addEventListener\('scroll', sync, \{ passive: true \}\)/.test(listJs)
    && /\.is-scrolled thead th/.test(listCss));

/* the stacked mobile card: the same row, still labelled */
const mobileLabels = [...view.matchAll(/data-label="([^"]+)"/g)].map(m => m[1]);
check('the stacked rows keep their column names',
    mobileLabels.length >= 6 && /content:attr\(data-label\)/.test(listCss),
    mobileLabels.join(', '));
/* the one placeholder rule left in the file belongs to the *form* page's items
   table, so this looks for the list-page signature specifically */
check('the mobile card no longer relies on empty ::before placeholders',
    /attr\(data-label\)/.test(listCss)
    && !/\.master-table td:nth-child\(\d\)::before\{content:"";\}/.test(listCss));
check('the mobile card shadow is a theme token, not a fixed black',
    !/box-shadow:0 4px 18px rgba\(0,0,0,\.06\)/.test(listCss));

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
for (let at = listCss.indexOf('.master-list .master-list-group td {');
     at > -1;
     at = listCss.indexOf('.master-list .master-list-group td {', at + 1)) {
    dividerCellBodies.push(listCss.slice(at, listCss.indexOf('}', at)));
}
check('the closed divider spans the whole row',
    /<td colspan="8">[\s\S]{0,500}master-list-group-inner/.test(view)
    && /\.master-list \.master-list-group-inner \{[\s\S]{0,120}display: flex/.test(listCss)
    && dividerCellBodies.length > 0
    && dividerCellBodies.every(body => !/display\s*:\s*(inline-)?(flex|grid)/.test(body)),
    `${dividerCellBodies.length} rule(s)`);
check('the closed divider keeps its count at the far edge',
    /\.master-list \.master-list-group-inner \{[\s\S]{0,160}justify-content: space-between/.test(listCss));
check('the divider stays a hairline band, not a tinted block',
    /\.master-list \.master-list-group td \{[\s\S]{0,400}background: var\(--mc-card\)/.test(listCss));

/* The row menu is placed against the viewport: a panel inside a table cell is
   painted in its row's pass and clipped by the scroll box, so the placement
   has to measure the button and cap the panel, and it has to follow the page
   while it scrolls — or the panel detaches from the button it belongs to. */
check('the panel is measured and placed against the viewport, not the row',
    /getBoundingClientRect\(\)/.test(layoutJs)
    && /menu\.style\.left = left \+ 'px'/.test(layoutJs)
    && /menu\.style\.top = \(button\.bottom \+ MENU_GAP\)/.test(layoutJs));
check('the panel flips above the button when there is more room there',
    /const up = height > below && above > below/.test(layoutJs)
    && /menu\.style\.bottom = \(viewport - button\.top \+ MENU_GAP\)/.test(layoutJs));
check('the panel is capped to the room it actually has',
    /menu\.style\.maxHeight = room \+ 'px'/.test(layoutJs)
    && /Math\.max\(MENU_MIN, up \? above : below\)/.test(layoutJs));
check('an open panel follows the page while it scrolls',
    /document\.addEventListener\('scroll', placeOpenMenus, true\)/.test(layoutJs)
    && /window\.addEventListener\('resize', placeOpenMenus\)/.test(layoutJs));

/* the panel is portaled to <body> while it is open: inside the table it is
   painted in its row's pass, clipped by the scroll box, and trapped by any
   ancestor that creates a stacking context (a sticky cell, an opacity group),
   whatever z-index it asks for */
check('the open panel is moved out of the table and into the body',
    /document\.body\.appendChild\(menu\)/.test(layoutJs)
    && /menuHomes\.set\(menu, \{ parent: menu\.parentNode, next: menu\.nextSibling \}\)/.test(layoutJs));
check('it goes back where it came from when it closes',
    /home\.parent\.insertBefore\(menu, home\.next\)/.test(layoutJs)
    && /restoreMenu\(menu\)/.test(layoutJs));
check('a portaled panel is shown and hidden explicitly',
    /menu\.style\.display = 'block';/.test(layoutJs)
    && /menu\.style\.display = '';/.test(layoutJs));
check('the panel is remembered per dropdown, not found again after portaling',
    /const dropdownMenus = new WeakMap\(\)/.test(layoutJs)
    && /dropdownMenus\.set\(dropdown, menu\)/.test(layoutJs)
    && /const menuOf = \(dropdown\) => dropdownMenus\.get\(dropdown\)/.test(layoutJs));
check('a click on the panel belongs to the panel, not to the row under it',
    /closest\('a, button, input, select, textarea, label, form, \.master-dropdown'\)/.test(listJs));
check('the menu is only declared once, in master-index.css',
    /^\s*\.master-dropdown-menu\s*\{/m.test(layoutCss)
    && !/\.master-dropdown-menu/.test(
        fs.readFileSync(path.join(ROOT, 'public/assets/css/master-show.css'), 'utf8')
            .replace(/\/\*[\s\S]*?\*\//g, '')));

/* a closed row is muted in colour: opacity would stack the cell and swallow
   the row's own menu */
check('the closed rows are muted in colour, not in alpha',
    !/\.ship-index \.ship-row-closed td \{[\s\S]{0,300}opacity/.test(css)
    && /\.ship-index \.ship-row-closed td \{[\s\S]{0,300}color: var\(--mc-text-2\)/.test(css));

/* ------------------------------------------- 5. one list standard */

/* The cashflow list is the same surface as the shipment list: the same chrome
   classes, the same behaviour from the shared toolkit, and the same
   guarantees. A fix to one is a fix to both — and a change that only lands in
   one of them fails here. */
const CHROME = [
    ['root opts in', /\bmaster-list\b/],
    ['chip bar', /class="master-list-bar"/],
    ['quick-view chips', /class="master-list-chip /],
    ['chip counts', /class="master-list-chip-count"/],
    ['saved views', /class="master-list-saved"/],
    ['save-view form', /class="master-list-save-view"/],
    ['applied strip', /class="master-list-applied"/],
    ['applied chips', /class="master-list-applied-chip"/],
    ['clear-all escape', /class="master-list-applied-clear"/],
    ['table bar + order hint', /class="master-list-toolbar"[\s\S]{0,600}class="master-list-hint"/],
    ['density control', /class="master-list-density desktop-only" role="group" aria-label="Row density"/],
    ['group divider', /class="master-list-group"/],
    ['group count', /class="master-list-group-count"/],
    ['totals row', /class="master-list-total"/],
    ['empty state', /class="master-list-empty-title"/],
    ['empty-state actions', /class="master-list-empty-actions"/],
];

const missingChrome = [];
for (const [label, rx] of CHROME) {
    if (!rx.test(cashView)) missingChrome.push('cashflow: ' + label);
    if (!rx.test(view)) missingChrome.push('shipment: ' + label);
}
check('both list screens carry the same chrome', missingChrome.length === 0,
    missingChrome.join(' | '));

/* every chrome class a view uses must exist in the shared sheet, so a list can
   never render an unstyled block */
const definedChrome = new Set([...listCss.matchAll(/\.(master-list[a-z-]*)/g)].map(m => m[1]));
const usedChrome = new Set(
    [...view.matchAll(/master-list[a-z-]+/g), ...cashView.matchAll(/master-list[a-z-]+/g)].map(m => m[0])
);
const undefinedChrome = [...usedChrome].filter(name => !definedChrome.has(name)).sort();
check('every chrome class the lists use is defined in the shared sheet',
    undefinedChrome.length === 0, undefinedChrome.join(', '));

/* one owner: the module sheets keep their own cells, not a second copy of the
   chrome */
check('no module sheet re-declares the list chrome',
    !/\.master-list/.test(css) && !/\.master-list/.test(cashCss));

/* the same behaviour, from one toolkit */
const toolkitCalls = ['rowNavigation', 'gridShadow', 'saveViewToggle', 'density']
    .filter(name => js.includes('MasterList.' + name) && cashflowJs.includes('MasterList.' + name));
check('both lists drive the shared list toolkit',
    toolkitCalls.length === 4, 'missing in one list: ' + toolkitCalls.join(', '));

/* the list is the module's front door: the pages it links to and the dialogs
   it opens must stay reachable from it (a rebuild once shipped a list that
   rendered two modals nobody could open) */
const moduleRoutes = ['cashflows.create', 'cashflows.reports', 'cashflows.settings.index'];
const missingRoutes = moduleRoutes.filter(name => !cashView.includes("route('" + name + "'"));
check("the cashflow list keeps the module's destinations", missingRoutes.length === 0,
    missingRoutes.join(', '));

const cashModals = [...cashView.matchAll(/id="((?!open)\w*Modal)"/g)].map(m => m[1]);
const orphanModals = cashModals.filter(id =>
    !cashView.includes('id="open' + id[0].toUpperCase() + id.slice(1) + '"'));
check("every dialog the cashflow list renders has a way to open it",
    cashModals.length >= 2 && orphanModals.length === 0, orphanModals.join(', '));

/* the mobile card contract: every cell keeps the column name it had */
const cashLabels = [...cashView.matchAll(/data-label="([^"]+)"/g)].map(m => m[1]);
check('the cashflow list keeps its column names on mobile too',
    cashLabels.length >= 6, cashLabels.join(', '));

/* the ledger's own cells: money lines up, and credit and debit are told apart
   by colour that exists in both themes */
check('the ledger columns line up like the shipment list',
    /class="is-num">Credit</.test(cashView)
    && /class="is-num">Debit</.test(cashView)
    && /class="is-num">Balance</.test(cashView)
    && (cashView.match(/class="cf-money is-num/g) || []).length === 3
    && /class="ship-money is-num"/.test(view));

const cashStatuses = [...fs.readFileSync(path.join(ROOT, 'app/Models/CashflowEntry.php'), 'utf8')
    .matchAll(/function accountingStatusOptions\(\): array[\s\S]*?return \[([\s\S]*?)\];/g)]
    .flatMap(m => [...m[1].matchAll(/'(\w+)' =>/g)].map(x => x[1]));

/* plain string lookup: no escapes to get wrong, and the sheet is ours */
const statusTone = (status, prefix) => {
    const at = cashCss.indexOf(prefix + '.cashflow-index .cf-status.status-' + status + ' {');
    return at === -1 ? '' : cashCss.slice(at, at + 160);
};

const missingCashStatus = cashStatuses.filter(status =>
    !statusTone(status, '').includes('--tone-bg')
    || !statusTone(status, ':root[data-theme="dark"] ').includes('--tone-fg'));

check('every settlement status has a light and a dark tone',
    cashStatuses.length >= 2 && missingCashStatus.length === 0,
    cashStatuses.join(', ') + (missingCashStatus.length ? ' — missing ' + missingCashStatus.join(', ') : ''));

/* and the same for the money colours: a tint that only works on white is a
   light-theme-only fix */
check('credit and debit keep their colour on the dark panel',
    /\.cashflow-index \.cf-credit/.test(cashCss)
    && cashCss.includes(':root[data-theme="dark"] .cashflow-index .cf-credit')
    && cashCss.includes(':root[data-theme="dark"] .cashflow-index .cf-debit'));

/* chrome that only makes sense at one width must stay inside that breakpoint:
   a mobile card label that leaks onto the desktop table is exactly the kind of
   change that used to pass review and break the list */
const mediaBlocks = (cssText, query) => {
    const blocks = [];
    let from = 0;
    for (;;) {
        const at = cssText.indexOf('@media (' + query + ')', from);
        if (at === -1) return blocks;
        const open = cssText.indexOf('{', at);
        let depth = 0;
        for (let i = open; i < cssText.length; i++) {
            if (cssText[i] === '{') depth++;
            else if (cssText[i] === '}') {
                depth--;
                if (depth === 0) {
                    blocks.push(cssText.slice(open, i));
                    from = i;
                    break;
                }
            }
        }
        if (from === 0) return blocks;
    }
};

/* the needle must live inside a block for that width, and nowhere else */
const onlyInMedia = (cssText, query, needle) => {
    const blocks = mediaBlocks(cssText, query);
    let rest = cssText;
    blocks.forEach(b => { rest = rest.split(b).join(''); });
    return blocks.some(b => b.includes(needle)) && !rest.includes(needle);
};

check('the mobile card labels only exist below the card breakpoint',
    onlyInMedia(listCss, 'max-width: 768px', 'td[data-label]::before'));

check('the density chrome only exists above the card breakpoint',
    onlyInMedia(listCss, 'min-width: 769px', 'data-density="compact"'));

/* ---- table shell integrity ----
   A table lays out as one box: the header and the body share a column grid
   only while the <table> keeps its own display. Rewriting that display to
   block/flex/grid makes the browser wrap thead and tbody in two anonymous
   tables, the two stop sharing a grid, and every cell lands in the wrong
   column — the misplaced-card render. Only the phone band may restack the
   shell; above it the table stays a table and its wrapper does the scrolling. */

const CSS_DIR = path.join(ROOT, 'public/assets/css');
const readSheet = file => fs.readFileSync(path.join(CSS_DIR, file), 'utf8')
    .replace(/\/\*[\s\S]*?\*\//g, '');

const cssRules = (text, query = '') => {
    const rules = [];
    let i = 0;
    while (i < text.length) {
        const at = text.indexOf('@media', i);
        const brace = text.indexOf('{', i);
        if (at !== -1 && (brace === -1 || at < brace)) {
            const open = text.indexOf('{', at);
            let depth = 0, end = open;
            for (; end < text.length; end++) {
                if (text[end] === '{') depth++;
                else if (text[end] === '}') {
                    depth--;
                    if (depth === 0) break;
                }
            }
            rules.push(...cssRules(text.slice(open + 1, end), text.slice(at + 6, open).trim()));
            i = end + 1;
            continue;
        }
        if (brace === -1) break;
        const close = text.indexOf('}', brace);
        if (close === -1) break;
        rules.push({ query, sel: text.slice(i, brace).trim(), body: text.slice(brace + 1, close) });
        i = close + 1;
    }
    return rules;
};

/* the shell nodes: the table itself and the boxes the browser lays out inside
   it. A <span> inside a cell is the module's business, not the shell's — only
   the compound a rule ends on decides which box gets the display. */
const shellSelector = sel => sel.split(',').some(part => {
    const p = part.trim().replace(/::?[\w-]+(\([^)]*\))?/g, '').trim();
    if (!p) return false;
    const last = p.split(/[\s>+~]+/).pop();
    return /\.master-table(\.[\w-]+)*$/.test(last) || /^(table|tbody|thead|tfoot|tr|td|th)$/i.test(last);
});

/* exactly one restack is legitimate: the labelled-card layout, below the phone
   breakpoint, owned by the shared sheet and scoped to the list surface */
const restacked = sheet => cssRules(readSheet(sheet))
    .filter(r => shellSelector(r.sel)
        && /(^|;)\s*display\s*:\s*(block|flex|grid|inline-block|inline-flex|inline)\b/i.test(r.body))
    .map(r => {
        const max = /max-width:\s*(\d+)px/.exec(r.query);
        return {
            query: r.query || 'top level',
            sel: r.sel,
            phone: !!(max && parseInt(max[1], 10) <= 768),
            list: /\.master-table\b/.test(r.sel)
        };
    });

const CSS_SHEETS = fs.readdirSync(CSS_DIR).filter(f => f.endsWith('.css'));
const wideRestacks = CSS_SHEETS
    .flatMap(f => restacked(f).filter(r => !r.phone).map(r => f + ' ' + r.query + ' { ' + r.sel + ' }'));

check('no sheet restacks the table shell outside the phone band',
    wideRestacks.length === 0, wideRestacks.join(' | '));

const moduleRestacks = restacked('shipments.css').filter(r => r.list).map(r => r.query + ' { ' + r.sel + ' }');

check('the module sheet leaves the list shell alone; the shared sheet owns it',
    moduleRestacks.length === 0
    && onlyInMedia(listCss, 'max-width: 768px', '.master-list .master-table tbody'),
    moduleRestacks.join(' | '));

/* the shell is pinned node by node: table, then the boxes inside it. Each pin
   is a full declaration, so `display: table-header-group` cannot answer for
   `display: table`. */
const SHELL_PINS = ['display: table;', 'display: table-header-group;', 'display: table-row-group;',
    'display: table-row;', 'display: table-cell;'];

check('the shared sheet pins the shell above the phone band',
    SHELL_PINS.every(pin => onlyInMedia(listCss, 'min-width: 769px', pin)));

/* a table wider than its card scrolls in its wrapper; a table without one has
   nowhere to scroll, so the shell is part of the markup contract too */
const bladeFiles = [];
const walkViews = dir => fs.readdirSync(dir, { withFileTypes: true }).forEach(entry => {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) walkViews(full);
    else if (entry.name.endsWith('.blade.php')) bladeFiles.push(full);
});
walkViews(path.join(ROOT, 'resources/views'));

const bareTables = [];
bladeFiles.forEach(file => {
    const text = fs.readFileSync(file, 'utf8');
    let from = 0;
    for (;;) {
        const at = text.indexOf('<table class="master-table', from);
        if (at === -1) break;
        const wrapAt = text.lastIndexOf('master-table-wrap', at);
        if (wrapAt === -1 || wrapAt < text.lastIndexOf('</table>', at)) {
            bareTables.push(path.relative(ROOT, file) + ' line ' + (text.slice(0, at).split('\n').length));
        }
        from = at + 1;
    }
});

check('every table in a view sits in a .master-table-wrap',
    bareTables.length === 0, bareTables.join(', '));

/* ---- quick date ranges ----
   The period chips are one shared definition (App\Helpers\DateRanges): the key,
   the label and the closed from/to pair behind the link. The count on a chip is
   the number of rows that chip would show, and the lit state is the range that
   is actually applied — so a chip can never name a month its link does not
   filter by. */
const rangeHelper = fs.readFileSync(path.join(ROOT, 'app/Helpers/DateRanges.php'), 'utf8');
const cashController = fs.readFileSync(path.join(ROOT, 'app/Http/Controllers/CashflowController.php'), 'utf8');
const rangeLabels = /public const LABELS = \[([\s\S]*?)\];/.exec(rangeHelper);

check('the quick date ranges are declared once, in render order',
    !!rangeLabels
    && ['this_month', 'last_month', 'this_year', 'last_year']
        .every((key, i, all) => rangeLabels[1].indexOf(`'${key}'`) > (i ? rangeLabels[1].indexOf(`'${all[i - 1]}'`) : -1))
    && /public static function presets\(/.test(rangeHelper)
    && /public static function keyOf\(/.test(rangeHelper));

check('the list reads its periods from the shared helper, not from month math',
    /DateRanges::presets\(\)/.test(cashController)
    && /DateRanges::LABELS/.test(cashController)
    && /DateRanges::keyOf\(/.test(cashController)
    && !/startOfMonth|endOfMonth/.test(cashView)
    && !/\$monthFrom|\$monthTo/.test(cashView));

check('a count is computed for every period chip',
    /foreach \(DateRanges::presets\(\) as \$key => \$range\)/.test(cashController)
    && /\$counts\[\$key\] = \$count\(/.test(cashController));

check('every period chip carries its label, its range, its count and its lit state',
    (cashView.match(/@foreach \(\$dateRanges as /g) || []).length === 1
    && /\$chipActive\[\$rangeKey\] = \$activeRange === \$rangeKey;/.test(cashView)
    && /\$dateRangeLabels\[\$rangeKey\]/.test(cashView)
    && /\['date_from' => \$range\['from'\], 'date_to' => \$range\['to'\]\]/.test(cashView)
    && /\$chipCounts\[\$rangeKey\]/.test(cashView)
    && /\$chipActive\[\$rangeKey\]/.test(cashView));

check('the applied-filters chip names the period it stands for',
    /\$activeRange \? 'Period' : 'Dates'/.test(cashView)
    && /\$dateRangeLabels\[\$activeRange\]/.test(cashView));

/* ---- same opening strip ----
   Both lists open with five flat tiles in the same shape: a title and a value,
   and only the last one carries a second line. A tile that grows a sub-line is
   a visible difference between two screens that are meant to be one design. */
const statsBlock = text => {
    const from = text.indexOf('<div class="master-stats');
    const to = text.indexOf('<div class="master-card', from);
    return from === -1 || to === -1 ? '' : text.slice(from, to);
};

const shipStats = statsBlock(view);
const cashStats = statsBlock(cashView);
check('both lists open with the same strip of tiles',
    (shipStats.match(/master-stat--flat/g) || []).length === 5
    && (cashStats.match(/master-stat--flat/g) || []).length === 5
    && (shipStats.match(/class="master-sub"/g) || []).length === 1
    && (cashStats.match(/class="master-sub"/g) || []).length === 1,
    'shipments ' + (shipStats.match(/master-stat--flat/g) || []).length
    + ' tiles / ' + (shipStats.match(/class="master-sub"/g) || []).length + ' with a sub-line'
    + ', cashflow ' + (cashStats.match(/master-stat--flat/g) || []).length
    + ' / ' + (cashStats.match(/class="master-sub"/g) || []).length);

/* ---- the ledger keeps every door open ----
   Both ways to record an entry, and the account an entry lands in, stay one
   click from the list: the quick dialog, the detailed form, and the add-account
   button sitting with the account filter it belongs to. */
const accountFilterAt = cashView.indexOf('class="cf-account-filter"');
const accountButtonAt = cashView.indexOf('id="openAccountModal"');
const applyGroupAt = cashView.indexOf('class="master-list-filter-group"');
check('the ledger keeps every door open',
    cashView.includes("route('cashflows.create')")
    && cashView.includes("route('cashflows.quickStore')")
    && accountFilterAt !== -1 && accountButtonAt > accountFilterAt && accountButtonAt < applyGroupAt,
    'add-account button sits in the control row: ' + accountFilterAt + ' < ' + accountButtonAt + ' < ' + applyGroupAt);

/* ---- same composition, same order ----
   The two lists are the same screen with different data, so they render the
   same components in the same sequence. Blade comments are stripped (a mention
   of a javascript file is not markup) and repeats collapse to first appearance,
   because one list has six chips and the other five. */
const composition = text => {
    const markup = text.replace(/\{\{--[\s\S]*?--\}\}/g, '');
    const seen = [];
    for (const name of markup.match(/master-list[a-z-]*/g) || []) {
        if (!seen.includes(name)) seen.push(name);
    }
    return seen;
};

const shipComposition = composition(view);
const cashComposition = composition(cashView);
const firstDiff = shipComposition.findIndex((name, i) => name !== cashComposition[i]);
check('the two lists are the same composition in the same order',
    shipComposition.length >= 25 && firstDiff === -1
    && shipComposition.length === cashComposition.length,
    firstDiff === -1
        ? 'shipments ' + shipComposition.length + ' vs cashflow ' + cashComposition.length + ' components'
        : 'first difference at ' + firstDiff + ': ' + shipComposition[firstDiff] + ' vs ' + cashComposition[firstDiff]);

/* ---- same geometry ----
   A row on one list has to be the same height, with its second line starting at
   the same place, as a row on the other. Both module sheets carry the same
   measurements; this compares them instead of trusting a comment. */
const ruleBody = (text, selector) => {
    const at = text.indexOf(selector);
    if (at === -1) return '';
    const open = text.indexOf('{', at);
    const close = text.indexOf('}', open);
    return open === -1 || close === -1 ? '' : text.slice(open + 1, close);
};

const shipCell = ruleBody(css, '.ship-index .master-table th,');
const cashCell = ruleBody(cashCss, '.cashflow-index .master-table th,');

check('both lists measure their table the same way',
    /padding:\s*12px 14px/.test(shipCell) && /padding:\s*12px 14px/.test(cashCell)
    && /padding:\s*14px 16px 16px/.test(ruleBody(css, '.ship-index .master-filter-row {'))
    && /padding:\s*14px 16px 16px/.test(ruleBody(cashCss, '.cashflow-index .master-filter-row {'))
    && /margin-top:\s*4px/.test(ruleBody(css, '.ship-index td .master-sub {'))
    && /margin-top:\s*4px/.test(ruleBody(cashCss, '.cashflow-index td .master-sub {')));

const lineToken = sheet => /--\w+-line:\s*(\d+)px/.exec(sheet)?.[1];
const subToken = sheet => /--\w+-sub:\s*(\d+)px/.exec(sheet)?.[1];
const compactToken = sheet => [...sheet.matchAll(/\[data-density="compact"\][\s\S]{0,200}?--\w+-line:\s*(\d+)px/g)].map(m => m[1]);

check('both lists share one row rhythm',
    lineToken(css) === lineToken(cashCss) && lineToken(css) === '20'
    && subToken(css) === subToken(cashCss) && subToken(css) === '16'
    && /min-height:\s*var\(--\w+-line\)/.test(css) && /min-height:\s*var\(--\w+-line\)/.test(cashCss)
    && compactToken(css).join() === compactToken(cashCss).join() && compactToken(css).length === 1,
    'line ' + lineToken(css) + '/' + lineToken(cashCss) + ' sub ' + subToken(css) + '/' + subToken(cashCss)
    + ' compact ' + compactToken(css) + '/' + compactToken(cashCss));

/* Every table names a width per column, and the widths are 1..n with no gap:
   a column without one is the column that reflows. The count is read per root
   rather than per sheet, so a second table in the same module — the document
   archive — is measured on its own columns, not on its neighbour's. */
const columnHints = (sheet, root) => [
    ...sheet.matchAll(new RegExp('\\.' + root + ' \\.master-table th:nth-child\\((\\d+)\\) \\{ width:', 'g')),
].map(match => Number(match[1]));

const contiguous = columns => columns.length > 0
    && columns.every((column, index) => column === index + 1);

const headingCount = (source, root) => (source.match(/<th scope="col"/g) || []).length;

const shipColumns = columnHints(css, 'ship-index');
const ledgerColumns = columnHints(cashCss, 'cashflow-index');
const archiveColumns = columnHints(cashCss, 'cashflow-documents');
const alignsLast = sheet => /th:last-child,[\s\S]{0,60}td:last-child \{[\s\S]{0,40}text-align: right/.test(sheet);

check('every list table stops its columns from reflowing',
    shipColumns.length >= 7 && contiguous(shipColumns)
    && contiguous(ledgerColumns) && ledgerColumns.length === headingCount(cashView, 'cashflow-index')
    && contiguous(archiveColumns) && archiveColumns.length === headingCount(documentsView, 'cashflow-documents')
    && alignsLast(css) && alignsLast(cashCss),
    'ship ' + shipColumns.length + ' ledger ' + ledgerColumns.length + ' archive ' + archiveColumns.length
    + ' headings ' + headingCount(cashView) + '/' + headingCount(documentsView));

/* each list loads the shared sheet after its own, so the chrome wins its own
   properties without out-specifying the module cells */
const sheetOrder = (text, moduleSheet) => {
    const moduleAt = text.indexOf(moduleSheet);
    const sharedAt = text.indexOf('master-list.css');
    return moduleAt !== -1 && sharedAt > moduleAt;
};
check('the shared sheet loads after the module sheet on both lists',
    sheetOrder(cashView, 'cashflows.css') && sheetOrder(view, 'shipments.css'));

/* ---------------------------------------------------------------- report */

const failed = out.filter(([, ok]) => !ok);
out.forEach(([name, ok, detail]) =>
    console.log((ok ? '  ok   ' : '  FAIL ') + name + (ok || !detail ? '' : '  -> ' + detail)));
console.log('\nlist: ' + (out.length - failed.length) + ' passed, ' + failed.length + ' failed');
process.exit(failed.length ? 1 : 0);
