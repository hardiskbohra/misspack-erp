/* Shared drawer and DataTable behavior contracts. No dependencies required. */
'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..', '..');
const read = (relative) => fs.readFileSync(path.join(ROOT, relative), 'utf8');
const checks = [];
const check = (name, ok, detail = '') => checks.push([name, Boolean(ok), detail]);

const drawerView = read('resources/views/components/drawer.blade.php');
const filterTrigger = read('resources/views/components/filter-trigger.blade.php');
const drawerCss = read('resources/css/components/drawers.css');
const dropdownCss = read('resources/css/components/dropdowns.css');
const elevationCss = read('resources/css/tokens/elevation.css');
const drawerJs = read('public/assets/js/master-drawer.js');
const vendorView = read('resources/views/vendors/index.blade.php');
const projectView = read('resources/views/projects/index.blade.php');
const adminLayout = read('resources/views/layouts/app.blade.php');
const portalLayout = read('resources/views/client_portal/layouts/app.blade.php');
const appLayoutJs = read('public/assets/js/app-layout.js');
const listJs = read('public/assets/js/master-list.js');
const tableCss = read('resources/css/components/tables.css');
const formCss = read('resources/css/components/forms.css');
const legacyFormCss = read('public/assets/css/master-form.css');
const responsiveCss = read('resources/css/layout/responsive.css');
const inventory = read('docs/ui-component-inventory.md');

check('the shared drawer is an accessible labelled dialog',
    /role="dialog" aria-modal="true"/.test(drawerView)
    && /aria-labelledby="\{\{ \$id \}\}-title"/.test(drawerView)
    && /data-drawer-layer hidden aria-hidden="true"/.test(drawerView));
check('the shared filter trigger names its drawer and announces the active count',
    /data-drawer-open="\{\{ \$drawer \}\}"/.test(filterTrigger)
    && /aria-haspopup="dialog"/.test(filterTrigger)
    && /aria-controls="\{\{ \$drawer \}\}"/.test(filterTrigger)
    && /aria-expanded="false"/.test(filterTrigger)
    && /active ' \./.test(filterTrigger)
    && /core-filter-count/.test(filterTrigger));
check('drawer behavior supports Escape, backdrop, close controls, and focus return',
    /event\.key === 'Escape'/.test(drawerJs)
    && /event\.key !== 'Tab'/.test(drawerJs)
    && /data-drawer-close/.test(drawerJs)
    && /trigger\.focus\(\)/.test(drawerJs)
    && /focusables/.test(drawerJs));
const safeHrefBody = drawerJs.match(/function safeHref\(value\) \{([\s\S]*?)\n    \}/);
check('drawer dynamic content is text-bound and rejects unsafe URL schemes',
    /node\.textContent = value/.test(drawerJs)
    && Boolean(safeHrefBody)
    && /https\?:\\\/\\\//.test(safeHrefBody?.[1] || '')
    && /mailto:/.test(safeHrefBody?.[1] || '')
    && /tel:/.test(safeHrefBody?.[1] || '')
    && /return ''/.test(safeHrefBody?.[1] || ''));
check('drawer surfaces use shared theme tokens and respect reduced motion',
    /background: var\(--ui-card-surface\)/.test(drawerCss)
    && /border: 1px solid var\(--ui-border\)/.test(drawerCss)
    && /prefers-reduced-motion/.test(drawerCss));
const zLayers = Object.fromEntries([...elevationCss.matchAll(/--ui-z-(dropdown|drawer|modal|toast|select):\s*(\d+)/g)]
    .map(([, name, value]) => [name, Number(value)]));
check('right drawers cover page popovers while remaining below modals and toasts',
    zLayers.drawer > zLayers.dropdown && zLayers.drawer < zLayers.modal && zLayers.modal < zLayers.toast);
check('Select2 options remain usable above right drawers and modal dialogs',
    zLayers.select > zLayers.drawer && zLayers.select > zLayers.modal
    && /body\[data-ui-shell\]\s+\.select2-dropdown\s*\{[^}]*z-index:\s*var\(--ui-z-select/.test(dropdownCss));
check('drawer field rows stay top-aligned and collapse to one column on phones',
    /align-content:\s*start/.test(drawerCss)
    && /grid-template-columns:\s*repeat\(2,\s*minmax\(0,\s*1fr\)\)/.test(drawerCss)
    && /grid-template-columns:\s*minmax\(0,\s*1fr\)/.test(drawerCss));
check('form labels use the field gap without stacked legacy margins',
    /\.master-label\s*\{\s*display:\s*block;\s*margin:\s*0;/.test(legacyFormCss)
    && /\.master-field\s*\{[^}]*gap:\s*7px/.test(legacyFormCss)
    && /body\[data-ui-shell\] \.master-field > \.master-label\s*\{\s*margin:\s*0;/.test(formCss));
check('admin and portal layouts both load the shared drawer behavior',
    /assets\/js\/master-drawer\.js/.test(adminLayout)
    && /assets\/js\/master-drawer\.js/.test(portalLayout));
const responsiveStateStart = appLayoutJs.indexOf('function applyResponsiveState()');
const responsiveStateEnd = appLayoutJs.indexOf('\n        if (sidebarToggle)', responsiveStateStart);
const responsiveState = responsiveStateStart >= 0 && responsiveStateEnd > responsiveStateStart
    ? appLayoutJs.slice(responsiveStateStart, responsiveStateEnd)
    : '';
check('the active route is revealed inside the shared sidebar scroll area',
    /function keepActiveSidebarItemVisible\(\)/.test(appLayoutJs)
    && /document\.querySelector\('\.sidebar-nav'\)/.test(appLayoutJs)
    && /nav\.querySelector\('\.sidebar-item\.active'\)/.test(appLayoutJs)
    && /nav\.scrollTop -= clippedAtTop/.test(appLayoutJs)
    && /nav\.scrollTop \+= clippedAtBottom/.test(appLayoutJs)
    && /keepActiveSidebarItemVisible\(\)/.test(responsiveState));
check('vendor and project indexes provide a quick-detail drawer example',
    /data-drawer-open="vendorQuickDetails"/.test(vendorView)
    && /<x-drawer id="vendorQuickDetails"/.test(vendorView)
    && /data-drawer-open="projectQuickDetails"/.test(projectView)
    && /<x-drawer id="projectQuickDetails"/.test(projectView));

const filterDrawerPages = [
    'resources/views/clients/index.blade.php',
    'resources/views/users/index.blade.php',
    'resources/views/vendors/index.blade.php',
    'resources/views/leads/index.blade.php',
    'resources/views/lead_quotes/index.blade.php',
    'resources/views/projects/index.blade.php',
    'resources/views/tasks/index.blade.php',
    'resources/views/products/index.blade.php',
    'resources/views/shipments/index.blade.php',
    'resources/views/sales_invoices/index.blade.php',
    'resources/views/cashflows/index.blade.php',
    'resources/views/cashflows/documents.blade.php',
    'resources/views/cashflows/statements.blade.php',
    'resources/views/cashflows/statement-show.blade.php',
    'resources/views/cashflows/reports.blade.php',
    'resources/views/clients/partials/statement.blade.php',
    'resources/views/clients/portal-support/index.blade.php',
    'resources/views/client_portal/attachments/index.blade.php',
    'resources/views/client_portal/payments/index.blade.php',
    'resources/views/client_portal/support/index.blade.php',
    'resources/views/client_portal/statements/index.blade.php',
    'resources/views/client_portal/products/index.blade.php',
    'resources/views/client_portal/quotes/index.blade.php',
];
const missingFilterDrawers = filterDrawerPages.filter((file) => {
    const source = read(file);
    return ! source.includes('<x-filter-trigger') || ! source.includes('<x-drawer');
});
check('module filters use the shared right drawer across office and portal screens',
    missingFilterDrawers.length === 0,
    missingFilterDrawers.join(', '));
const taskView = read('resources/views/tasks/index.blade.php');
const taskDrawerStart = taskView.indexOf('<x-drawer id="taskFiltersDrawer"');
const taskDrawerEnd = taskView.indexOf('</x-drawer>', taskDrawerStart);
const taskDrawer = taskDrawerStart >= 0 && taskDrawerEnd > taskDrawerStart
    ? taskView.slice(taskDrawerStart, taskDrawerEnd)
    : '';
check('task scope and completion controls stay with the rest of the task filters',
    ['scope', 'category', 'status', 'priority', 'show_completed', 'assignee_id']
        .every(name => taskDrawer.includes('name="' + name + '"'))
    && /name="search"/.test(taskView.slice(0, taskDrawerStart)));

const statementFilterContracts = [
    ['resources/views/clients/partials/statement.blade.php', 'clientStatementFiltersDrawer', ['period', 'date_from', 'date_to', 'currency']],
    ['resources/views/client_portal/statements/index.blade.php', 'portalStatementFiltersDrawer', ['period', 'date_from', 'date_to', 'currency']],
    ['resources/views/cashflows/statement-show.blade.php', 'statementDetailFiltersDrawer', ['period', 'date_from', 'date_to', 'currency']],
    ['resources/views/cashflows/statements.blade.php', 'statementListFiltersDrawer', ['date_from', 'date_to', 'currency']],
];
const statementFiltersStayInDrawers = statementFilterContracts.every(([file, drawerId, fields]) => {
    const source = read(file);
    const start = source.indexOf('<x-drawer id="' + drawerId + '"');
    const end = source.indexOf('</x-drawer>', start);
    if (start < 0 || end <= start) return false;
    const drawer = source.slice(start, end);
    return fields.every(name => drawer.includes('name="' + name + '"'));
});
check('statement period and date/currency filters remain together in their drawers',
    statementFiltersStayInDrawers);
const clientStatementView = read('resources/views/clients/partials/statement.blade.php');
const statementShowView = read('resources/views/cashflows/statement-show.blade.php');
const documentsView = read('resources/views/cashflows/documents.blade.php');
check('statement PDF and document-pack downloads remain available with their filters',
    /cashflows\.statements\.pdf/.test(clientStatementView)
    && /Download PDF/.test(clientStatementView)
    && /cashflows\.statements\.pdf/.test(statementShowView)
    && /cashflows\.documents\.pack/.test(documentsView)
    && /Download pack/.test(documentsView));

check('DataTable settings are opt-in and column state is persisted per key',
    /table\[data-table-settings/.test(listJs)
    && /data-table-key/.test(listJs)
    && /localStorage\.setItem\(key, JSON\.stringify\(visibility\)\)/.test(listJs));
check('column chooser protects the action column, keeps a visible column, and can reset',
    /actions\?/.test(listJs)
    && /if \(!visibility\.some\(Boolean\)\)/.test(listJs)
    && /Restore defaults/.test(listJs)
    && /localStorage\.removeItem\(key\)/.test(listJs));
check('visible table columns reclaim the grid width and restore its original sizing',
    /function captureColumnSizing\(table, count\)/.test(listJs)
    && /sizing\.widths\[index\] \/ visibleWidth\) \* 100/.test(listJs)
    && /sizing\.inlineWidths\[index\]/.test(listJs)
    && /sizing\.minWidth \* visibleWidth \/ sizing\.totalWidth/.test(listJs)
    && /applyColumnVisibility\(table, visibility, columnSizing\)/.test(listJs));
check('column chooser exposes labelled controls and state to assistive technology',
    /aria-haspopup', 'dialog'/.test(listJs)
    && /aria-expanded', open \? 'true' : 'false'/.test(listJs)
    && /role', 'dialog'/.test(listJs)
    && /Show ' \+ column\.label \+ ' column/.test(listJs));
check('the three density presets are available and persisted',
    /\['comfortable', 'standard', 'compact'\]/.test(listJs)
    && /localStorage\.setItem\(key, value\)/.test(listJs)
    && /\[data-density="comfortable"\]/.test(tableCss)
    && /\[data-density="standard"\]/.test(tableCss)
    && /\[data-density="compact"\]/.test(tableCss));
check('responsive table controls preserve mobile essentials and tablet column reduction',
    /@media \(max-width: 991px\)[\s\S]*?core-column-chooser/.test(tableCss)
    && /@media screen and \(min-width: 768px\) and \(max-width: 991px\)[\s\S]*?ui-mobile-secondary/.test(responsiveCss)
    && /data-column-hidden/.test(responsiveCss));

const viewFiles = [];
function walk(directory) {
    for (const entry of fs.readdirSync(path.join(ROOT, directory), { withFileTypes: true })) {
        const relative = path.join(directory, entry.name);
        if (entry.isDirectory()) walk(relative);
        else if (entry.isFile() && entry.name.endsWith('.blade.php')) viewFiles.push(relative);
    }
}
walk('resources/views');
const tableKeys = viewFiles.flatMap((file) => {
    const source = read(file);
    return [...source.matchAll(/data-table-key="([^"]+)"/g)].map((match) => [match[1], file]);
});
const duplicates = tableKeys.filter(([key], index) => tableKeys.findIndex(([other]) => other === key) !== index);
check('every opted-in listing uses a unique stable table preference key',
    tableKeys.length >= 12 && duplicates.length === 0,
    duplicates.map(([key, file]) => `${key} (${file})`).join(', '));
check('the component inventory marks drawers, column chooser, and density modes shared',
    /\| Drawer \| Shared \|/.test(inventory)
    && /\| Column chooser \| Shared \|/.test(inventory)
    && /\| Table density \| Shared \|/.test(inventory));

let failures = 0;
for (const [name, ok, detail] of checks) {
    console.log(`  ${ok ? 'ok  ' : 'FAIL'} ${name}${ok || !detail ? '' : ` — ${detail}`}`);
    if (!ok) failures++;
}
console.log(`\nui components: ${checks.length - failures} passed, ${failures} failed`);
if (failures) process.exitCode = 1;
