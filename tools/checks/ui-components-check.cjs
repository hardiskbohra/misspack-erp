/* Shared drawer and DataTable behavior contracts. No dependencies required. */
'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..', '..');
const read = (relative) => fs.readFileSync(path.join(ROOT, relative), 'utf8');
const checks = [];
const check = (name, ok, detail = '') => checks.push([name, Boolean(ok), detail]);

const drawerView = read('resources/views/components/drawer.blade.php');
const drawerCss = read('resources/css/components/drawers.css');
const drawerJs = read('public/assets/js/master-drawer.js');
const vendorView = read('resources/views/vendors/index.blade.php');
const projectView = read('resources/views/projects/index.blade.php');
const adminLayout = read('resources/views/layouts/app.blade.php');
const portalLayout = read('resources/views/client_portal/layouts/app.blade.php');
const listJs = read('public/assets/js/master-list.js');
const tableCss = read('resources/css/components/tables.css');
const responsiveCss = read('resources/css/layout/responsive.css');
const inventory = read('docs/ui-component-inventory.md');

check('the shared drawer is an accessible labelled dialog',
    /role="dialog" aria-modal="true"/.test(drawerView)
    && /aria-labelledby="\{\{ \$id \}\}-title"/.test(drawerView)
    && /data-drawer-layer hidden aria-hidden="true"/.test(drawerView));
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
check('admin and portal layouts both load the shared drawer behavior',
    /assets\/js\/master-drawer\.js/.test(adminLayout)
    && /assets\/js\/master-drawer\.js/.test(portalLayout));
check('vendor and project indexes provide a quick-detail drawer example',
    /data-drawer-open="vendorQuickDetails"/.test(vendorView)
    && /<x-drawer id="vendorQuickDetails"/.test(vendorView)
    && /data-drawer-open="projectQuickDetails"/.test(projectView)
    && /<x-drawer id="projectQuickDetails"/.test(projectView));

check('DataTable settings are opt-in and column state is persisted per key',
    /table\[data-table-settings/.test(listJs)
    && /data-table-key/.test(listJs)
    && /localStorage\.setItem\(key, JSON\.stringify\(visibility\)\)/.test(listJs));
check('column chooser protects the action column, keeps a visible column, and can reset',
    /actions\?/.test(listJs)
    && /if \(!visibility\.some\(Boolean\)\)/.test(listJs)
    && /Restore defaults/.test(listJs)
    && /localStorage\.removeItem\(key\)/.test(listJs));
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
