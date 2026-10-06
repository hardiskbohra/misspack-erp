/* ==========================================================================
   PROJECTS CHECK — the list is the shell, and the shell is not ours
   --------------------------------------------------------------------------
   Run:  node tools/checks/projects-check.cjs
   No dependencies. Exits non-zero on failure.

   Projects was the last list in the ERP carrying its own composition: its own
   page wrapper, its own stat cards, its own filter card, its own dialog and
   its own money colours. That copy is why the module never looked like the
   ledger, the vendors or the clients next door, and it is why this file
   exists. What has to stay true now:

     - the page is the shared master-list: the figures, then two flat cards —
       the chips, search and the filter drawer in the first, the records in
       the second — with the gap between them coming from master-list.css and
       never from the module's sheet;
     - the figures, the chip tallies and the drawer are one aggregate: two
       grouped queries, not four COUNT(*) round trips, and the tiles are
       derived from the grouped rows;
     - a row asks for what it draws: the relations the row reads are eager
       loaded, and paymentTotals() reuses that load instead of querying the
       ledger once per project;
     - the module's sheet declares no shared class in its list section, and it
       keeps the eight classes the client portal still renders;
     - a linked row looks linked (the cursor is the module's to say);
     - the quick-create dialog is the shared .master-modal opened through
       MasterModal, and the list's toolkit is MasterList — the module's own
       modal wiring is gone;
     - every route the screen links to is registered.
   ========================================================================== */

'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..', '..');
const read = relative => fs.readFileSync(path.join(ROOT, relative), 'utf8');
const exists = relative => fs.existsSync(path.join(ROOT, relative));
const plain = text => text.replace(/\/\*[\s\S]*?\*\//g, '');

let passed = 0;
let failed = 0;
const check = (name, ok, detail = '') => {
    console.log(`  ${ok ? 'ok  ' : 'FAIL'} ${name}${ok || !detail ? '' : ` -> ${detail}`}`);
    ok ? passed++ : failed++;
};

/* ----------------------------------------------------------------- sources */

const view = read('resources/views/projects/index.blade.php');
const sheet = read('public/assets/css/projects.css');
const sheetCode = plain(sheet);
const script = read('public/assets/js/projects.js');
const controller = read('app/Http/Controllers/ProjectController.php');
const model = read('app/Models/Project.php');
const routes = read('routes/web.php');
const portalView = read('resources/views/client_portal/projects/index.blade.php');

const index = controller.slice(
    controller.indexOf('public function index('),
    controller.indexOf('public function create('),
);

/* The list's own section: from its banner down to the detail page's. Sliced
   off the raw sheet (the banners are comments) and read with the comments
   stripped, so a selector can never be answered for by prose. */
const LIST_MARK = 'PROJECT LIST — resources/views/projects/index.blade.php';
const DETAIL_MARK = 'PROJECT DETAIL — resources/views/projects/show.blade.php';
const listSection = (() => {
    const start = sheet.lastIndexOf(LIST_MARK);
    if (start === -1) return '';

    const end = sheet.indexOf(DETAIL_MARK, start);
    const from = sheet.indexOf('*/', start) + 2;
    const to = end === -1 ? sheet.length : sheet.lastIndexOf('/*', end);

    return plain(sheet.slice(from, to));
})();

const listRules = listSection.split('\n').map(line => line.trim())
    .filter(line => /^[^@\s{}][^{}]*\{\s*$/.test(line));

/* ------------------------------------------------- 1. the shared shell */

check('the page is the shared master-list root',
    /<div class="project project-index master-list">/.test(view));

const filterCard = view.indexOf('aria-label="Search and filter projects"');
const recordsCard = view.indexOf('aria-label="Project records"');

check('the page is two blocks: what you search with, then what you read',
    /<section class="master-card master-card--flat" aria-label="Search and filter projects">/.test(view)
    && /<section class="master-card master-table-card master-card--flat" aria-label="Project records">/.test(view)
    && filterCard !== -1 && recordsCard > filterCard
    && (view.match(/<section class="master-card /g) || []).length === 2);

/* The 24px rhythm between two cards is `.master-list > .master-card + .master-card`
   in the shared sheet. A margin here is a second definition of it, and the two
   drift the moment one of them is tuned. */
check('the gap between the cards is the shell\'s, not the module\'s',
    !/\.master-card/.test(listSection) && !/\.master-list\b/.test(listSection));

check('the module\'s sheet never re-declares a shell class',
    !/^[ \t]*\.(master|core)-(list|stat|table|modal|card|badge|chip|drawer)(-[a-z-]+)?[\s,:{]/m.test(sheetCode));

check('the four figures are the shared flat tile',
    (view.match(/<div class="master-stat master-stat--flat [a-z]+">/g) || []).length === 4
    && /class="master-stat-title"/.test(view) && /class="master-stat-value"/.test(view));

/* ------------------------------------------------- 2. the filter card */

const chipAnchors = [...view.matchAll(/<a class="master-list-chip[^"]*"[\s\S]*?<\/a>/g)].map(m => m[0]);
check('every chip is the shared chip and carries its own tally',
    chipAnchors.length === 3
    && chipAnchors.every(chip => /master-list-chip-count/.test(chip))
    && /aria-label="Filter projects by status"/.test(view)
    && /aria-label="Filter projects by health"/.test(view));

check('a chip changes what it owns and carries the rest of the search with it',
    /\$statusUrl = function \(\$value\) use \(\$keep\)/.test(view)
    && /\$healthUrl = function \(\$value\) use \(\$keep\)/.test(view)
    && /\$keep\(\['status'\]\)/.test(view)
    && /\$keep\(\['health'\]\)/.test(view)
    && /\$chipUrl = fn \(string \$key\) => route\('projects\.index', \$keep\(\[\$key\]\)->all\(\)\)/.test(view));

check('the search sits in the GET form and the rest of the criteria in the drawer',
    /<form method="GET" action="\{\{ route\('projects\.index'\) \}\}">/.test(view)
    && /class="master-search"/.test(view)
    && /<x-filter-trigger drawer="projectFiltersDrawer" label="Filters" :count="\$activeFilterCount" \/>/.test(view)
    && /<x-drawer id="projectFiltersDrawer"/.test(view)
    && /<\/x-drawer>\s*<\/form>/.test(view));

check('one removable chip per active filter, each dropping only its own key',
    /@if \(\$filtersActive\)/.test(view)
    && ['search', 'status', 'client_id', 'health']
        .every(key => view.includes(`$chipUrl('${key}')`))
    && /class="master-list-applied-clear" href="\{\{ route\('projects\.index'\) \}\}"/.test(view));

/* ------------------------------------------------- 3. the records card */

check('the records card carries the table the toolkit keys on',
    /class="master-table-wrap ui-mobile-cards"/.test(view)
    && /<table class="master-table" data-table-settings data-table-key="projects">/.test(view)
    && /MasterList\.density\(\{ root: '\.project-index', key: 'misspack\.projects\.density' \}\)/.test(script));

check('the density group is the shell\'s markup, declared before the toolkit runs',
    (view.match(/class="master-list-density-btn"/g) || []).length === 3
    && /data-density="standard" aria-pressed="true"/.test(view)
    && !/density/.test(listSection));

const headers = [...view.matchAll(/<th scope="col"([^>]*)>\s*([^<]+?)\s*<\/th>/g)]
    .map(m => ({ attrs: m[1], label: m[2] }));

check('the columns are named in the order the table draws them',
    headers.map(header => header.label).join(' | ')
        === 'Project | Client | Stage | Owner | Target | Value | Status | Action'
    && headers.filter(header => header.attrs.includes('class="is-num"')).length === 1
    && headers[headers.length - 1].attrs.includes('project-col-actions'));

check('the toolbar says which rows are on screen',
    /Newest first · Showing '\.\$firstProject\.'–'\.\$lastProject\.' of '\."\$projectCount'|Newest first · Showing/.test(view)
    && /\$firstProject = \$projects->firstItem\(\) \?\? 0;/.test(view)
    && /\$lastProject = \$projects->lastItem\(\) \?\? 0;/.test(view));

check('a linked row says it is one',
    /<tr class="project-row is-clickable" data-href="\{\{ route\('projects\.show', \$project\) \}\}">/.test(view)
    && /\.project-index \.project-row\.is-clickable \{\s*cursor: pointer/.test(sheetCode));

check('the empty state keeps a way out and names the real one',
    /@forelse \(\$projects as \$project\)/.test(view)
    && /class="master-list-empty"/.test(view)
    && /@if \(\$filtersActive\)[\s\S]*?Clear filters[\s\S]*?@else[\s\S]*?Add first project/.test(view));

/* ------------------------------------------------- 4. one number, one query */

check('the tiles, the chip tallies and the drawer read the same grouped rows',
    (index.match(/selectRaw\('[a-z]+, COUNT\(\*\) as aggregate'\)/g) || []).length === 2
    && (index.match(/groupBy\('[a-z]+'\)/g) || []).length === 2
    && /->pluck\('aggregate', 'status'\)/.test(index)
    && /->pluck\('aggregate', 'health'\)/.test(index));

check('the four figures are derived, not counted again',
    /'total' => \(int\) \$statusCounts->sum\(\)/.test(index)
    && /'waiting' => \(int\) \(\$statusCounts\['waiting_client'\] \?\? 0\) \+ \(int\) \(\$statusCounts\['waiting_vendor'\] \?\? 0\)/.test(index)
    && /compact\(\s*'projects', 'stats', 'statusCounts', 'healthCounts'/.test(index)
    && !/Project::(where|query\(\)->where)\(/.test(index));

check('the list is a page of rows, and the module agreed on the size',
    /paginate\(25\)/.test(index) && /->withQueryString\(\)/.test(index));

check('the row\'s relations are loaded, and the totals reuse them',
    /'cashflowEntries'/.test(index)
    && /'assignedUser'/.test(index)
    && /relationLoaded\('cashflowEntries'\)/.test(model)
    && /\? \$this->cashflowEntries\b/.test(model)
    && /\$this->cashflowEntries\(\)->get\(\)/.test(model)
    && !/function cashflows\(/.test(model));

/* ------------------------------------------------- 5. the module's own sheet */

const portalClasses = ['projects-project-card', 'projects-mini-grid', 'projects-progress',
    'projects-chip', 'projects-top-bar', 'projects-number', 'projects-meta-row',
    'projects-project-footer', 'projects-project-top', 'projects-footer-actions'];

check('the sheet keeps every class the client portal still renders',
    portalClasses.every(name => sheetCode.includes('.' + name)),
    portalClasses.filter(name => !sheetCode.includes('.' + name)).join(', '));

const deadClasses = ['projects-page', 'projects-hero', 'projects-stat-card', 'projects-filter-card',
    'projects-filter-form', 'projects-two-col', 'projects-search-field', 'projects-tag',
    'projects-pagination', 'projects-money-green', 'projects-modal'];

check('the copy of the pattern is gone from the sheet',
    deadClasses.every(name => !sheetCode.includes('.' + name)),
    deadClasses.filter(name => sheetCode.includes('.' + name)).join(', '));

check('the list section is scoped to the page it styles',
    listRules.length > 20
    && listRules.every(rule => rule.includes('.project-index'))
    && listRules.some(rule => rule.includes('.project-table-name'))
    && listRules.some(rule => rule.includes('.project-health-dot')),
    listRules.filter(rule => !rule.includes('.project-index')).join(' | '));

check('the status and health tones are the module\'s, and both themes have one',
    /\.project-index \.status-completed/.test(sheetCode)
    && /\.project-index \.health-amber/.test(sheetCode)
    && (sheetCode.match(/:root\[data-theme="dark"\] \.project-index/g) || []).length >= 4);

/* ------------------------------------------------- 6. the dialog and the script */

check('the quick-create dialog is the shared modal, opened the shared way',
    (view.match(/class="master-modal"/g) || []).length === 1
    && /id="quickProjectModal"/.test(view)
    && /id="openQuickProjectModal"/.test(view)
    && /window\.MasterModal\.open\(dialog\)/.test(script)
    && !/projects-modal/.test(script)
    && !/projects-modal/.test(view));

check('the list drives the shared toolkit, and the page scripts are wired',
    /MasterList\.rowNavigation\(\{ root: '\.project-index' \}\)/.test(script)
    && /MasterList\.gridShadow\(\{ root: '\.project-index' \}\)/.test(script)
    && /initProjectList\(\);/.test(script)
    && /assets\/js\/projects\.js/.test(view));

/* ------------------------------------------------- 7. the doors it opens */

const restNames = ['projects.index', 'projects.create', 'projects.store', 'projects.show',
    'projects.edit', 'projects.update', 'projects.destroy'];
const usedRoutes = [...new Set([...view.matchAll(/route\('(projects\.[A-Za-z.]+)'/g)].map(m => m[1]))];
const unregistered = usedRoutes.filter(name => (restNames.includes(name)
    ? !/Route::resource\('projects',/.test(routes)
    : !new RegExp(`->name\\('${name.replace(/\./g, '\\.')}'\\)`).test(routes)));

check('every route the screen links to is registered',
    usedRoutes.length > 0 && unregistered.length === 0, unregistered.join(', '));

check('the module is written down',
    exists('docs/projects-module.md')
    && /tools\/checks\/projects-check\.cjs/.test(read('docs/projects-module.md'))
    && /master-list\.css/.test(read('docs/projects-module.md')));

check('the check itself is listed with its siblings',
    /projects-check\.cjs/.test(read('tools/checks/README.md')));

check('the portal page this rule protects still exists',
    /project-index|projects-project-card/.test(portalView));

/* ---------------------------------------------------------------- report */

const failedChecks = failed;
console.log(`\nprojects: ${passed} passed, ${failedChecks} failed`);
process.exit(failedChecks ? 1 : 0);
