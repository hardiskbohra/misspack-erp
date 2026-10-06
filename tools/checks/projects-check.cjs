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
     - every route the screen links to is registered;
     - every class a module screen names is a class with rules — the shell's,
       another screen's, or the module's own sheet — so the private vocabulary
       of a page that no longer exists cannot come back;
     - every panel the record renders wears the module's own card class, because
       the shared surface carries no padding and a record panel that does not
       bring its own runs every row into the border.
   ========================================================================== */

'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..', '..');
const read = relative => fs.readFileSync(path.join(ROOT, relative), 'utf8');
const exists = relative => fs.existsSync(path.join(ROOT, relative));
const plain = text => text.replace(/\/\*[\s\S]*?\*\//g, '');

const walk = (dir, out = []) => {
    for (const entry of fs.readdirSync(path.join(ROOT, dir), { withFileTypes: true })) {
        const rel = path.join(dir, entry.name);
        entry.isDirectory() ? walk(rel, out) : out.push(rel.replaceAll(path.sep, '/'));
    }

    return out;
};

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

/* The sheet is read by section, not by position: every banner is a slice, and
   the slices are named, so moving a section cannot silently hand a check the
   wrong rules — a list check reading the record page's sheet is a check that
   passes for the wrong reason. Prose is never an answer: the slices are read
   with the comments stripped. */
const sections = (() => {
    const marks = [...sheet.matchAll(/\/\* ={10,}\n {3}([A-Z][^\n]*)\n/g)]
        .map(match => ({ title: match[1].split(' — ')[0], start: match.index }));
    const out = {};

    marks.forEach((mark, index) => {
        const from = sheet.indexOf('*/', mark.start) + 2;
        const to = index + 1 < marks.length ? marks[index + 1].start : sheet.length;
        out[mark.title] = plain(sheet.slice(from, to));
    });

    return out;
})();

const sectionRules = text => text.split('\n').map(line => line.trim())
    .filter(line => /^[^@\s{}][^{}]*\{\s*$/.test(line));

const listSection = sections['PROJECT LIST'] || '';
const recordSection = sections['PROJECT RECORD'] || '';
const listRules = sectionRules(listSection);
const recordRules = sectionRules(recordSection);

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
    && /<table class="master-table">/.test(view)
    && /MasterList\.rowNavigation\(\{ root: '\.project-index' \}\)/.test(script)
    && /MasterList\.gridShadow\(\{ root: '\.project-index' \}\)/.test(script));

/* The density switch and the column chooser were removed ERP-wide: one
   comfortable rhythm for every table, and the columns the controller sends, so
   a reader cannot be looking at a different table than the office. The list
   keeps no hook for either. */
check('the list offers no density or column switch',
    !/master-list-density|data-density|data-table-settings|data-table-key/.test(view)
    && !/MasterList\.density/.test(script)
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
    listRules.length > 12
    && listRules.every(rule => rule.includes('.project-index'))
    && listRules.some(rule => rule.includes('.project-table-name'))
    && listRules.some(rule => rule.includes('.project-health-dot'))
    && !listRules.some(rule => rule.includes('.status-')),
    listRules.filter(rule => !rule.includes('.project-index')).join(' | '));

/* One vocabulary for the two pages: the same tone answers for a status on the
   list and on the record, so a badge cannot mean one thing in one place. Every
   state the record can print — a project's status, a milestone's stage, an
   activity's state, a shipment's leg — has a tone, and a tone for the dark
   theme, because a badge with no tone is a state the reader cannot scan. */
const optionKeys = (file, method) => {
    const body = read(file).match(new RegExp(`function ${method}\\(\\): array\\s*\\{([\\s\\S]*?)\\n    \\}`));

    return body ? [...body[1].matchAll(/'([a-z_]+)'\s*=>/g)].map(match => match[1]) : [];
};
const STATE_TONES = [...new Set([
    ...optionKeys('app/Models/Project.php', 'statusOptions'),
    ...optionKeys('app/Models/ProjectMilestone.php', 'statusOptions'),
    ...optionKeys('app/Models/ProjectTrackingUpdate.php', 'statusOptions'),
    ...[...read('app/Models/Shipment.php').matchAll(/const STATUS_[A-Z_]+ = '([a-z_]+)';/g)]
        .map(match => match[1]),
])].map(state => state.replace(/_/g, '-'));
const HEALTH_TONES = optionKeys('app/Models/Project.php', 'healthOptions');
/* The band a client put us in is the feedback module's vocabulary, not this
   one's: the words come from `FeedbackVocabulary` and the record page only
   lends them a tone (`band-promoter` / `band-passive` / `band-detractor`). */
const BAND_TONES = [...read('app/Services/FeedbackVocabulary.php')
    .matchAll(/const BAND_[A-Z_]+ = '([a-z_]+)';/g)].map(match => match[1]);
const darkRules = [...sheetCode.matchAll(/:root\[data-theme="dark"\][^{]*\{[^}]*\}/g)].map(match => match[0]);
const darkTones = new Set([...darkRules.join('\n').matchAll(/\.(status|health|band)-([a-z-]+)/g)]
    .map(match => `${match[1]}-${match[2]}`));
/* The light rules are read with the dark ones taken out: a tone that only
   reached the sheet as a dark-theme selector is a tone the light page has not
   got, and reading the sheet whole hides exactly that. */
const lightCode = darkRules.reduce((text, rule) => text.replace(rule, ''), sheetCode);
const missingTones = ['status', 'health'].flatMap(prefix => (prefix === 'status' ? STATE_TONES : HEALTH_TONES)
    .map(tone => `${prefix}-${tone}`))
    .concat(BAND_TONES.map(band => `band-${band}`))
    .filter(name => !new RegExp(`\\.project \\.${name}\\b`).test(lightCode) || !darkTones.has(name));

check('the status and health tones are the module\'s, and both themes have one',
    missingTones.length === 0
    && /\.project-index \.status-completed/.test(sheetCode) === false
    && /\.project \.project-priority-chip\.is-high/.test(sheetCode),
    missingTones.join(' | '));

/* A module sheet that places a shared class on its own is how a control
   becomes a different size on one module's screens: `.master-field input`
   (`padding: 10px 11px`) beat the shell's `.master-input`. Every rule naming a
   `master-*` / `core-*` class must be scoped to something this module owns —
   a module class or a module id — so the shell stays the only writer. */
const bareShell = [];
sheetCode.split('}').forEach(chunk => {
    const head = chunk.split('{')[0].split('@')[0].trim();

    head.split(',').forEach(selector => {
        selector = selector.trim();

        if (!selector) return;

        const classes = [...selector.matchAll(/\.([a-zA-Z][\w-]*)/g)].map(match => match[1]);
        const shell = classes.filter(name => /^(master|core)-/.test(name));
        const mine = classes.filter(name => !/^(master|core)-/.test(name));

        if (shell.length && ! mine.length && ! /#[\w-]+/.test(selector)) {
            bareShell.push(selector);
        }
    });
});

check('the sheet places a shell class only inside this module\'s own scopes',
    bareShell.length === 0, [...new Set(bareShell)].slice(0, 3).join(' | '));

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

/* ------------------------------------------------- 8. the record page */

const showView = read('resources/views/projects/show.blade.php');
const recordFiles = walk('resources/views/projects/partials')
    .filter(file => /record-[a-z-]+\.blade\.php$/.test(file));
const recordSource = Object.fromEntries(recordFiles.map(file => [file, read(file)]));
const recordViews = showView + '\n' + Object.values(recordSource).join('\n');
const milestoneViews = read('resources/views/projects/partials/milestones-tab.blade.php')
    + read('resources/views/projects/partials/milestone-product-block.blade.php');
/* The feedback tab is a record panel too: the ask is issued from the project,
   so its chrome is the record page's, not the feedback sheet's. */
const feedbackTab = read('resources/views/projects/partials/feedback-tab.blade.php');
const showIncludes = [...showView.matchAll(/@include\('(projects\.partials\.[a-z-]+)'/g)].map(m => m[1]);

check('the record page is the shared shell',
    /<div class="project project-show master-list" data-project-id=/.test(showView)
    && /<header class="master-card master-header project-record-header">/.test(showView)
    && /<div class="master-stats desktop-only" aria-label="Project figures">/.test(showView)
    && /<div class="master-tabs-card">/.test(showView)
    && /<nav class="master-tabs" role="tablist" aria-label="Project sections">/.test(showView));

/* The record's panels are the shared surface, and the shared surface carries no
   padding of its own — the list pages inset their bars instead, which is why a
   list card can be flush and still read correctly. A record panel holds facts,
   stats or a table directly, so it must carry the inset itself; round 10 shipped
   them without it and every row ran into the border. The card class is the
   module's (`.project-detail-card`, the sibling of the client and vendor
   records' own card classes) and it is the one place the inset is written. */
const panelFiles = walk('resources/views/projects')
    .filter(file => file.endsWith('.blade.php'))
    .filter(file => file !== 'resources/views/projects/index.blade.php'
        && file !== 'resources/views/projects/form.blade.php');
const panelCards = panelFiles.flatMap(file => [...read(file).matchAll(/class="([^"]*)"/g)]
    .map(match => match[1])
    .filter(classes => /(^|\s)master-card(\s|$)/.test(classes))
    .map(classes => ({ file, classes })));
const uninsetPanels = panelCards
    .filter(({ classes }) => ! /(^|\s)project-detail-card(\s|$)/.test(classes)
        && ! /(^|\s)master-header(\s|$)/.test(classes))
    .map(({ file }) => file);

check('every panel on the record wears the module card, and the card carries its inset',
    panelCards.length >= 20
    && uninsetPanels.length === 0
    && /\.project-detail-card\s*\{[^}]*padding:\s*22px 24px/.test(sheetCode)
    && /max-width: 767px[\s\S]{0,900}?\.project-detail-card\s*\{[^}]*padding:\s*16px 18px/.test(sheetCode),
    uninsetPanels.slice(0, 3).join(' | '));

/* The record mark's initials, in the house idiom (the client and vendor pages
   collect the parts and map them). A `Str::of()` chain is a string: the one
   collection method it cannot answer throws BadMethodCallException only when a
   browser opens the page, which is a check no template reader would catch. */
const viewCode = plain((showView + recordViews + milestoneViews + feedbackTab).replace(/\{\{--[\s\S]*?--\}\}/g, ''));

check('the record mark is built the way the client pages build theirs',
    /collect\(explode\(' ', trim\(\(string\) \$project->name\)\)\)/.test(showView)
    && /mb_substr\(\$word, 0, 1\)/.test(showView)
    && /Str::of\(/.test(viewCode) === false,
    'the initials are read with the prose stripped: a comment cannot answer it');

/* A panel from a module's own sheet would arrive unstyled: `feedback.css` is
   loaded by the feedback pages, never by the project record. This tab is the
   record page's like the other nine — and it owns its wrapper, so the include
   site cannot wrap it twice or forget to. */
check('the feedback tab speaks the record page, not the feedback sheet',
    /<section class="master-tab-panel" id="project-panel-feedback"/.test(feedbackTab)
    && /@include\('projects\.partials\.feedback-tab'\)/.test(showView)
    && !/project-panel-feedback/.test(showView)
    && /master-empty-state/.test(feedbackTab)
    && /master-badge band-\{\{ \$response->band\(\) \}\}/.test(feedbackTab)
    && /master-badge status-\{\{/.test(feedbackTab) === false
    && !/(^|[^-\w])(fb|pd)-[a-z-]+/.test(feedbackTab));

check('the tabs are links drawn from one list, with their counts',
    /@foreach \(\$tabs as \$key => \$label\)/.test(showView)
    && /<a class="master-tab \{\{ \$tab === \$key/.test(showView)
    && !/<button[^>]*class="master-tab/.test(showView)
    && /href="\{\{ \$recordUrl\(\$key\) \}\}"/.test(showView)
    && /is-active/.test(showView)
    && /<span class="master-tab-count">/.test(showView)
    && /'tabs' => self::SHOW_TABS/.test(controller)
    && /'recordUrl' => fn \(string \$key\) => route\('projects\.show'/.test(controller)
    && /'tabCounts' => \[/.test(controller));

check('a tab the URL invented lands on the overview',
    /array_key_exists\(\$tab, self::SHOW_TABS\) \? \$tab : 'overview'/.test(controller));

/* The button strip rendered all ten panels on every request and pushed the
   active one into localStorage. A panel is a URL now: one per response, each
   shareable, and a form posted from a tab returns to it through `back()`. */
check('one panel is rendered per request',
    !/data-tab-panel=/.test(showView)
    && !/pd-tab-(panel|btn)/.test(showView + recordViews + milestoneViews)
    && !/function initTabs/.test(script)
    && showIncludes.length === 10
    && ['record-overview', 'record-products', 'record-money', 'record-shipments', 'record-documents',
        'record-comments', 'record-activity', 'record-logs', 'milestones-tab', 'feedback-tab']
        .every(name => showIncludes.includes('projects.partials.' + name))
    && (showView.match(/@elseif \(\$tab === '/g) || []).length === 8);

check('every panel is a panel of the shared kind',
    recordFiles.length === 8
    && Object.values(recordSource).every(text => /class="master-tab-panel"/.test(text))
    && Object.values(recordSource).every(text => /id="project-panel-[a-z]+"/.test(text))
    && Object.values(recordSource).every(text => /role="tabpanel"/.test(text))
    && /<section class="master-tab-panel" id="project-panel-milestones"/.test(milestoneViews));

check('the record page declares no copy of the shell',
    !/pd-[a-z]/.test(showView + recordViews)
    && !/\bpd-[a-z]/.test(milestoneViews)
    && !/pmile-page|pmile-stats|pmile-actions-card|pmile-product-block|pmile-empty|pmile-status/.test(milestoneViews)
    && !/class="master-empty"/.test(recordViews + milestoneViews));

/* The shell owns the shape of every card, badge and button; the record
   section may place them (a phone breakpoint flexing an action row) but never
   restyle them from the top. */
check('the record section declares no shared class',
    recordRules.length > 20
    && !recordRules.some(rule => /^\.(master|core)-/.test(rule))
    && recordRules.some(rule => rule.includes('.project-record-header'))
    && recordRules.some(rule => rule.includes('.project-blocks')));

check('the cards are the shell\'s, and the rhythm between them is the module\'s',
    /\.project-blocks \{\s*display: grid;\s*gap: 16px/.test(recordSection)
    && !/\.master-card/.test(recordSection)
    && (recordViews.match(/class="master-card master-card--flat/g) || []).length >= 10);

/* A timeline is wider than any card: five 285px steps and their connectors do
   not fit a laptop, let alone the panel they sit in. The strip is meant to
   scroll inside itself — but a bare `auto` grid track may not shrink below
   its content, so the strip's max-content width walked up through the card
   and widened the page. The reader then got a page-wide horizontal scrollbar
   and cropped panels (the figures row included) instead of a scrolling
   timeline. The fix is structural, so all four parts of it are checked: the
   explicit zero track, the zero minimums down the chain, the scroll container
   and the scrollbar it draws. */
check('a timeline wider than its card scrolls inside the card',
    /\.project-blocks \{[\s\S]{0,200}grid-template-columns: minmax\(0, 1fr\)/.test(sheet)
    && /\.project-blocks > \*,[\s\S]{0,200}\.pmile-step-scroll \{\s*min-width: 0/.test(sheet)
    && /\.pmile-step-scroll \{[\s\S]{0,140}overflow-x: auto/.test(sheet)
    && /\.pmile-step-scroll::-webkit-scrollbar \{[\s\S]{0,80}height: 6px/.test(sheet));

/* The framework never reads the markup, so a panel that closes its wrapper one
   line early puts every card after it outside the grid and nothing complains:
   the tag stack is the only reader that can see it. */
const VOID_TAGS = new Set(['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link',
    'meta', 'param', 'source', 'track', 'wbr']);
const TAGS = /<(\/?)([a-zA-Z][a-zA-Z0-9-]*)((?:"[^"]*"|'[^']*'|[^>"'])*?)(\/?)>/gs;

const nestingErrors = (file, source) => {
    const text = plain(source.replace(/\{\{--[\s\S]*?--\}\}/g, '').replace(/@php[\s\S]*?@endphp/g, ''));
    const stack = [];
    const errors = [];

    [...text.matchAll(TAGS)].forEach(match => {
        const [, closing, rawName, , selfClosing] = match;
        const name = rawName.toLowerCase();
        const line = text.slice(0, match.index).split('\n').length;

        if (VOID_TAGS.has(name) || selfClosing) return;

        if (!closing) {
            stack.push({ name, line });
            return;
        }

        const open = stack.pop();
        if (!open || open.name !== name) {
            errors.push(`${file}:${line} closes ${open ? `<${open.name}>` : 'nothing'}`);
        }
    });

    return errors.concat(stack.map(open => `${file}:${open.line} <${open.name}> is never closed`));
};

const nesting = recordFiles.concat(['resources/views/projects/partials/milestones-tab.blade.php',
    'resources/views/projects/partials/milestone-product-block.blade.php'])
    .flatMap(file => nestingErrors(file, read(file)));

check('every panel is well formed',
    recordFiles.length === 8 && nesting.length === 0,
    nesting.slice(0, 3).join(' | '));

/* The wrapper is the panel's only child: the cards inside it stack on the
   module's 16px. A card left outside it is a card with no rhythm. */
check('the card wrapper is the panel\'s own child',
    recordFiles.every(file => {
        const lines = read(file).split('\n');
        const panel = lines.findIndex(line => line.includes('<section class="master-tab-panel"'));

        return panel !== -1
            && lines[panel] === lines[panel].trimStart()
            && lines[panel + 1] === '    <div class="project-blocks">'
            && lines.some((line, index) => line === '    </div>' && lines[index + 1] === '</section>');
    }));

/* The other direction: a tone the page prints that the sheet does not own is a
   badge with no colour, which is how the record page shipped a page of grey
   pills the first time. */
const printedTones = new Set([...(showView + recordViews + milestoneViews + feedbackTab)
    .matchAll(/[\s"']status-([a-z-]+)/g)].map(match => match[1]));

check('every tone the page prints is one the sheet owns',
    printedTones.size >= 3
    && [...printedTones].every(tone => new RegExp(`\\.project \\.status-${tone}\\b`).test(sheetCode)),
    [...printedTones].filter(tone => !new RegExp(`\\.project \\.status-${tone}\\b`).test(sheetCode)).join(' | '));

check('the facts, the notes and the empty blocks are the shell\'s',
    (recordViews.match(/class="master-facts"/g) || []).length >= 4
    && (recordViews.match(/class="master-info"/g) || []).length >= 18
    && (recordViews.match(/class="master-empty-state"/g) || []).length >= 8
    && !/master-info-list|pd-info-list/.test(recordViews));

check('every table on the record is the shared table in a wrapper',
    (recordViews.match(/<table class="master-table">/g) || []).length === 5
    && (recordViews.match(/class="master-table-wrap"/g) || []).length === 5
    && (recordViews.match(/<th scope="col"/g) || []).length === (recordViews.match(/<th[\s>]/g) || []).length
    && !/style="color:(green|red)"/.test(recordViews));

check('the update path is the server\'s, not the script\'s',
    ['products', 'tracking', 'comments', 'payments']
        .every(name => new RegExp(`data-update-url="\\{\\{ route\\('projects\\.${name}\\.update'`).test(recordViews))
    && /__ID__/.test(recordViews)
    && /function bindUpdateUrl/.test(script)
    && !/'\/project-/.test(plain(script)));

check('the second door into a dialog exists',
    /querySelectorAll\('\[data-modal-open\]'\)/.test(script)
    && [...recordViews.matchAll(/data-modal-open="([A-Za-z]+)"/g)]
        .every(match => recordViews.includes(`id="${match[1]}"`)));

/* The record page is a hub: it opens the product dialogs, the money dialogs,
   the shipment record, the portal and the PDFs. A name typo is a 500 here, and
   a panel is only rendered when its tab is asked for — so the routes the views
   name are checked against the routes the file declares, not against a request
   someone happened to make. */
const webRoutes = read('routes/web.php');
const declaredRoutes = new Set([...webRoutes.matchAll(/name\('([a-z0-9_.-]+)'\)/g)].map(m => m[1]));
/* A route written inside a `Route::prefix(…)->name('group.')` group is declared
   with the short name and referenced with the full one — `client-portal.login`
   for a `->name('login')` inside that group — so the group's prefix is opened
   against every name the file declares. Only a name built from a real prefix
   and a real name is accepted; a typo matches nothing. */
const routeNamePrefixes = [...webRoutes.matchAll(/->name\('([a-z0-9_.-]+\.)'\)/g)].map(m => m[1]);
routeNamePrefixes.forEach(prefix => [...declaredRoutes].forEach(name => declaredRoutes.add(prefix + name)));
const resourceBases = [...webRoutes.matchAll(/Route::resource\('([a-z-]+)'/g)].map(m => m[1]);
const doorsUsed = [...(recordViews + milestoneViews).matchAll(/route\('([a-z0-9_.-]+)'/g)]
    .map(m => m[1].replace(/['\s)]+$/, ''));

check('every door a panel opens exists',
    doorsUsed.length >= 25
    && doorsUsed.every(name => declaredRoutes.has(name) || resourceBases.includes(name.split('.')[0])),
    [...new Set(doorsUsed)].filter(name => !declaredRoutes.has(name) && !resourceBases.includes(name.split('.')[0])).join(' | '));

/* A record page is a place where things are removed. The shared confirm layer
   is `data-confirm` on the form; a form that already sits in its own modal
   footer has asked the question, and asking twice is how people learn to click
   through the question. */
const deleteForms = [...(recordViews + milestoneViews).matchAll(/<form[\s\S]*?<\/form>/g)]
    .map(form => form[0])
    .filter(form => /@method\('DELETE'\)/.test(form));

check('a destructive action asks first',
    deleteForms.length >= 5
    && deleteForms.every(form => /data-confirm=/.test(form) || /master-modal-footer/.test(form)));

/* The public project portal is gone — the client follows a project by signing
   in, and `projects.show_client_portal` is the door the login portal reads. A
   token link left anywhere (a route name, a column, a copy button) is a door
   that 404s, so the removal is checked rather than assumed, and the flag is
   checked as still read where the portal decides what to show. */
const portalCorpus = [
    read('routes/web.php'),
    read('app/Models/Project.php'),
    read('app/Http/Controllers/ProjectController.php'),
    showView,
    read('resources/views/projects/partials/record-overview.blade.php'),
    read('public/assets/js/projects.js'),
].join('\n');

check('the client follows a project in the login portal, not through a token link',
    ! /project-portal|projects\.public|public_token|publicPayments/.test(portalCorpus)
    && ! exists('app/Http/Controllers/PublicProjectController.php')
    && ! exists('resources/views/projects/public.blade.php')
    && /show_client_portal/.test(portalCorpus + read('resources/views/projects/form.blade.php'))
    && /route\('client-portal\.login'\)/.test(recordViews)
    && /where\('show_client_portal', true\)/.test(read('app/Http/Controllers/ClientPortalProjectController.php')));

check('the status form keeps its four facts, in the shared drawer',
    /<x-drawer id="projectStatusDrawer"/.test(showView)
    && /action="\{\{ route\('projects\.status\.update', \$project\) \}\}"/.test(showView)
    && /@method\('PATCH'\)/.test(showView)
    && ['status', 'stage', 'health', 'progress_percent']
        .every(name => new RegExp(`name="${name}"`).test(showView)));

check('the record page is written down',
    /record page/i.test(read('docs/projects-module.md'))
    && /SHOW_TABS/.test(read('docs/projects-module.md')));

/* ------------------------------------- a class the module names is a class with rules */

/* The module rebuilt its screens on the shell, and each rebuild moved the markup
   off the sheet of the day: the form's root is `.project-form-page` because the
   sheet scopes the form's own field spacing to that name, the record's thumbs
   are `.project-thumb` because the sheet draws one, and a health chip is a
   `.master-list-chip` because the shell owns the chip. A class left behind in
   the markup is invisible — it renders as nothing, and no reviewer reading the
   Blade can tell. This guard is exact rather than lexical: a class a module
   screen names is defined when a sheet declares a rule for it or when another
   screen names it, and dynamic compositions (`project-health-dot--{{ $key }}`)
   are not names at all. */
const moduleScreenFiles = (function walk(dir, out = []) {
    for (const entry of fs.readdirSync(path.join(ROOT, dir), { withFileTypes: true })) {
        const relative = `${dir}/${entry.name}`;
        if (entry.isDirectory()) walk(relative, out);
        else if (relative.endsWith('.blade.php')) out.push(relative);
    }
    return out;
})('resources/views/projects');

const everyTemplateFile = moduleScreenFiles.slice();
(function walk(dir) {
    for (const entry of fs.readdirSync(path.join(ROOT, dir), { withFileTypes: true })) {
        const relative = `${dir}/${entry.name}`;
        if (entry.isDirectory()) walk(relative);
        else if (relative.endsWith('.blade.php') && ! everyTemplateFile.includes(relative)) everyTemplateFile.push(relative);
    }
})('resources/views');

const moduleOwnedClass = /^(?:project|pd|pmile|fb)-/;
const classTokens = (text) => text.split(/[^A-Za-z0-9_-]+/)
    .filter((name) => name && moduleOwnedClass.test(name) && ! name.endsWith('-') && ! /[{}]/.test(name));
const eachNamedClass = (source, visit) => {
    for (const match of source.matchAll(/class="([^"]*)"/g)) classTokens(match[1]).forEach(visit);
    /* In @class([...]) a quoted string is the class — a key with a condition, or
       a plain list item; an unquoted value (a variable) is not a name. */
    for (const match of source.matchAll(/@class\(\[([\s\S]*?)\]\)/g)) {
        for (const literal of match[1].matchAll(/'([^']*)'/g)) classTokens(literal[1]).forEach(visit);
    }
};

const classSites = new Map();
for (const file of everyTemplateFile) {
    eachNamedClass(plain(read(file)), (name) => {
        if (! classSites.has(name)) classSites.set(name, new Set());
        classSites.get(name).add(file);
    });
}

const appSheetCorpus = fs.readdirSync(path.join(ROOT, 'public/assets/css'))
    .filter((file) => file.endsWith('.css'))
    .map((file) => read(`public/assets/css/${file}`)).join('\n');
const sheetDeclares = (name) => new RegExp(`\\.${name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}(?![A-Za-z0-9_-])`)
    .test(appSheetCorpus);

const orphanClassSites = [];
for (const file of moduleScreenFiles) {
    const spelled = new Set();
    eachNamedClass(plain(read(file)), (name) => spelled.add(name));
    for (const name of spelled) {
        const namedElsewhere = [...(classSites.get(name) || [])].some((other) => other !== file);
        if (! namedElsewhere && ! sheetDeclares(name)) {
            orphanClassSites.push(`${file} → .${name}`);
        }
    }
}

check('every class a module screen names is a class with rules',
    orphanClassSites.length === 0,
    orphanClassSites.slice(0, 6).join(' | '));

/* ---------------------------------------------------------------- report */

const failedChecks = failed;
console.log(`\nprojects: ${passed} passed, ${failedChecks} failed`);
process.exit(failedChecks ? 1 : 0);
