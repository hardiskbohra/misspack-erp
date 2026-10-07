/* ==========================================================================
   SETTINGS CHECK — one module, one list, and every door that used to lead
   elsewhere still open
   --------------------------------------------------------------------------
   Run:  node tools/checks/settings-check.cjs
   No dependencies. Exits non-zero on failure.

   The settings of this ERP were scattered the way they usually are: the
   organisation profile was a sidebar item, the briefing rules were another, the
   cashflow accounts were a button on the ledger, the lead dropdowns were a link
   on a list, and the feedback scorecard was a third page nobody could find. The
   round that fixed it built one module with a rail of areas — which only stays
   fixed while three things hold:

     - **one list** — the hub and the rail are drawn from `SettingsDirectory`,
       so an area cannot exist in the menu and not on the hub, or be renamed in
       one and not the other. Neither view may name an area by hand;
     - **nothing moved twice** — a setting's rule, its validation and its writes
       stay with the module that owns it (`SettingsController` is a page, not a
       second writer), and the five pages that used to *be* the settings do not
       exist any more, so a stale copy cannot drift;
     - **the old doors still open** — `/organisation`, `/cashflows/settings`,
       `/leads/settings`, `/feedback/settings` and `/office-alerts/settings`
       are somebody's bookmarks, and the tab in the query string is part of the
       address. Every one of them is a redirect that carries it.

   Plus the house rules for anything new: the shell's components and no others,
   tokens and no literals, the office door on the whole group, and a boundary
   rule for what counts as a setting at all — written down, because "is this a
   setting?" is the question this module gets asked for ever.
   ========================================================================== */

'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..', '..');
const read = relative => fs.readFileSync(path.join(ROOT, relative), 'utf8');
const exists = relative => fs.existsSync(path.join(ROOT, relative));
const plain = text => text.replace(/\/\*[\s\S]*?\*\//g, '').replace(/^\s*\/\/.*$/gm, '');
const blade = text => text.replace(/\{\{--[\s\S]*?--\}\}/g, '');
const has = (text, needle) => text.includes(needle);
const times = (text, needle) => text.split(needle).length - 1;

let passed = 0;
let failed = 0;
const check = (name, ok, detail = '') => {
    console.log(`  ${ok ? 'ok  ' : 'FAIL'} ${name}${ok || !detail ? '' : ` -> ${detail}`}`);
    ok ? passed++ : failed++;
};

/* ----------------------------------------------------------------- sources */

const directorySource = read('app/Services/SettingsDirectory.php');
const directory = plain(directorySource);
const controller = plain(read('app/Http/Controllers/SettingsController.php'));
const routes = read('routes/web.php');
const hub = blade(read('resources/views/settings/index.blade.php'));
const rail = blade(read('resources/views/settings/partials/nav.blade.php'));
const sheet = read('public/assets/css/settings.css');
const script = read('public/assets/js/settings.js');
const layout = blade(read('resources/views/layouts/app.blade.php'));
const userMenu = blade(read('resources/views/layouts/partials/user-menu.blade.php'));
const search = plain(read('app/Services/GlobalSearch.php'));
const docs = read('docs/settings-module.md');

const areas = ['organisation', 'cashflow', 'leads', 'feedback', 'briefings', 'assets'];
const areaView = key => `resources/views/settings/${key}.blade.php`;

/* ------------------------------------------------------------- one list */

check('the areas are defined once, in the directory and nowhere else',
    areas.every(key => has(directory, `public const ${key.toUpperCase()} = '${key}';`))
    && has(directory, 'public function areas(): array')
    && has(directory, 'public function area(string $key)'));

check('the hub is drawn from the directory, not from a list of its own',
    has(controller, 'private SettingsDirectory $directory')
    && has(controller, "'areas' => $this->directory->areas()")
    && has(controller, "'counts' => $this->directory->counts()")
    && has(hub, '@foreach ($areas as $area)'));

check('the rail is drawn from the same list, so the two can never disagree',
    has(rail, '@foreach ($areas as $area)')
    && has(rail, 'route($area[\'route\'])')
    && has(hub, "@include('settings.partials.nav'")
    && has(hub, '@foreach ($areas as $area)'));

check('neither the hub nor the rail names an area by hand',
    !areas.some(key => has(hub, `route('settings.${key}` ) || has(rail, `settings.${key}'`)),
    'an area label, icon or route written into a view is a second definition');

check('an area carries what a menu and a card both need',
    ['key', 'label', 'icon', 'blurb', 'holds', 'route', 'active', 'links', 'keywords', 'counts']
        .every(key => times(directory, `'${key}' =>`) >= areas.length - 1),
    'label/icon/route drive the rail, blurb/holds/links drive the hub card');

check('a count never breaks the hub: every number is guarded and null means "no badge"',
    has(directory, 'Schema::hasTable')
    && times(directory, 'catch (Throwable') >= 2
    && has(directory, '?int'));

check('the page the office opens owns no setting of its own',
    !has(plain(read('app/Http/Controllers/SettingsController.php')), 'App\\Models')
    && !/->(?:save|update|delete)\(/.test(controller)
    && !/::(?:create|update|destroy)\(/.test(controller),
    'SettingsController is a page; each module keeps its own validation and writes');

/* ------------------------------------------------------------- the rail */

check('every area screen wears the same rail, with its own key',
    areas.filter((key, index) => index >= 0)
        .every(key => has(blade(read(areaView(key))), `@include('settings.partials.nav', ['current' => '${key}'])`))
    && areas.length === 6);

check('the hub marks no area, because the hub is not one',
    has(hub, "@include('settings.partials.nav', ['current' => null])")
    && has(rail, "$current === $area['key']"));

check('the rail is fed by the shell, not by six screens remembering',
    has(plain(read('app/Providers/AppServiceProvider.php')), "View::composer('settings.*'")
    && has(rail, '$areas ?? app('),
    'a screen that forgets the list must still get the module-wise menu, not an empty one');

check('the rail says where the reader is, out loud',
    has(rail, 'aria-current="page"')
    && has(rail, 'is-active')
    && has(rail, 'aria-label="Settings areas"'));

/* --------------------------------------------------- the module's own sheet */

check('the sheet defines the module chrome and no shared component',
    /^\s*\.[a-z-]+/m.test(sheet)
    && [...`${sheet}`.matchAll(/^\s*(\.set[a-z-]*)\s*[,{]/gm)].length >= 3
    && !areas.some(key => new RegExp(`^\\s*\\.(master|cf|ls|fb|ob)-`, 'm').test(sheet)),
    'a module-local copy of a shared card, tab or field is how two screens stop matching');

check('the module spends tokens and no literals',
    !/#[0-9a-fA-F]{3,6}/.test(sheet) && !/rgba?\(/.test(sheet)
    && has(sheet, 'var(--ui-'));

check('the sheet owns a dark theme by not needing one',
    has(sheet, '--ui-text') && has(sheet, '--ui-border'));

const hidesRail = /\.set-nav\s*\{[^}]*display:\s*none|\.set-nav-list\s*\{[^}]*display:\s*none|\.set-nav-link\s*\{[^}]*display:\s*none/;

check('the rail is navigation, so it is never hidden on a phone',
    !hidesRail.test(sheet)
    && /@media screen and \(max-width: 991px\)/.test(sheet)
    && has(script, 'set-nav-list'));

check('the area column spaces every area page, once',
    /\.set-area\s*>\s*\*\s*\+\s*\*\s*\{/.test(sheet),
    'the rhythm used to live on each page\'s own header, which left with the page');

/* ---------------------------------------------------------- the old doors */

const aliases = [
    ['/organisation', 'settings.organisation', 'organisation.settings'],
    ['/cashflows/settings', 'settings.cashflow', 'cashflows.settings.index'],
    ['/leads/settings', 'settings.leads', 'leads.settings.index'],
    ['/feedback/settings', 'settings.feedback', 'feedback.settings'],
    ['/office-alerts/settings', 'settings.briefings', 'office-alerts.settings'],
];

check('every URL these settings used to live at still opens them',
    aliases.every(([old, to]) => routes.includes(`Route::get('${old}',`) && routes.includes(`redirect()->to(route('${to}'`)),
    aliases.filter(([old]) => !routes.includes(`Route::get('${old}',`)).map(([old]) => old).join(', '));

check('the redirect carries the tab with it',
    aliases.every(([, , name]) => routes.includes(`$request->query()), 301))->name('${name}')`)),
    'a bookmark of ?tab=categories must not land on Accounts');

check('the old route names are kept, because three checks and every installed copy use them',
    aliases.every(([, , name]) => routes.includes(`->name('${name}')`)));

check('the settings routes sit inside the office door',
    routes.indexOf("'/settings'") > routes.indexOf("Route::middleware('office')")
    && routes.indexOf("'/settings'") < routes.indexOf("Route::middleware('client.portal')"),
    'a settings screen is the office\'s, as it always was');

/* The move renamed routes, and a view that still names the old one throws the
   first time somebody opens the page it is on — the modal partials of the
   organisation screen did. So the walk follows what the module renders: the six
   screens, every partial they include, and every route name in them, against the
   names the file actually declares (resource routes included). */
const declared = new Set([...routes.matchAll(/->name\('([^']+)'\)/g)].map(m => m[1]));

[...routes.matchAll(/Route::resource\('([^']+)'/g)].map(m => m[1]).forEach(base =>
    ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'].forEach(verb => declared.add(`${base}.${verb}`)));

const rendered = [];
const walked = new Set();
const walk = name => {
    if (walked.has(name)) return;
    walked.add(name);

    const file = `resources/views/${name.split('.').join('/')}.blade.php`;
    if (!exists(file)) return;
    rendered.push(file);

    [...blade(read(file)).matchAll(/@include(?:If)?\('([^']+)'/g)].forEach(m => walk(m[1]));
};

walk('settings.index');
areas.forEach(key => walk(`settings.${key}`));

const named = [...new Set(rendered.flatMap(file =>
    [...read(file).matchAll(/route\('([a-z0-9._-]+)'/g)].map(m => m[1])))];
const undeclared = named.filter(name => !declared.has(name));

check('every route the settings screens and their partials name exists',
    rendered.length >= 10 && named.length >= 8 && undeclared.length === 0,
    undeclared.join(', ') || 'the module renders too few views to have walked the graph');

/* --------------------------------------------------- the pages that moved */

check('the five pages that used to be "the settings" are gone',
    !exists('resources/views/organisation/settings.blade.php')
    && !exists('resources/views/cashflows/settings.blade.php')
    && !exists('resources/views/leads/settings.blade.php')
    && !exists('resources/views/feedback/settings.blade.php')
    && !exists('resources/views/office_briefings/settings.blade.php'));

check('no controller still renders a view that moved',
    !/view\('(organisation\.settings|cashflows\.settings|leads\.settings|feedback\.settings|office_briefings\.settings)'/.test(
        ['OrganisationController', 'CashflowSettingController', 'LeadSettingController', 'FeedbackController', 'OfficeBriefingSettingController']
            .map(name => read(`app/Http/Controllers/${name}.php`)).join('\n')));

check('the sheets no longer style a page that is not there',
    !/^[ \t]*\.cf-setting/m.test(read('public/assets/css/cashflows.css'))
    && !/^[ \t]*\.cf-tabs?\b/m.test(read('public/assets/css/cashflows.css'))
    && !/^[ \t]*\.ls-tabs?\b/m.test(read('public/assets/css/leads.css'))
    && !/^[ \t]*\.ls-header\b/m.test(read('public/assets/css/leads.css')),
    'a second, stale definition of the head or the tab strip is the drift this round removed');

check('the two moved rows pages wear the shared tab strip, as links with their own URL',
    has(blade(read(areaView('cashflow'))), 'class="master-tabs"')
    && has(blade(read(areaView('leads'))), 'class="master-tabs"')
    && has(blade(read(areaView('cashflow'))), 'role="tab" aria-selected=')
    && has(blade(read(areaView('leads'))), 'role="tab" aria-selected=')
    && !has(blade(read(areaView('cashflow'))), 'cf-tab')
    && !has(blade(read(areaView('leads'))), 'ls-tab '));

/* ------------------------------------------------------------- the doors in */

check('the sidebar has one settings door, not a second tree of areas',
    times(layout, "route' => 'settings.") === 1
    && has(layout, "'label' => 'Settings', 'route' => 'settings.index', 'active' => 'settings.*'")
    && !has(layout, "'route' => 'organisation.settings'")
    && !has(layout, "'route' => 'office-alerts.settings'"));

check('the account menu points at the same door',
    has(userMenu, "route('settings.index')")
    && !has(userMenu, "route('organisation.settings')")
    && !has(userMenu, "route('office-alerts.settings')"));

check('the modules that linked to their own settings page link here now',
    /* The register is the sixth module with a settings area, and the round trip
       matters as much as the first five: a reader in the register who wonders
       what a class is must land in Settings, and the classes page must land back
       in the register. */
    has(read('resources/views/assets/index.blade.php'), "route('settings.assets')")
    && has(read('resources/views/settings/assets.blade.php'), "route('assets.index')")
    && has(read('resources/views/cashflows/index.blade.php'), "route('settings.cashflow')")
    && has(read('resources/views/leads/index.blade.php'), "route('settings.leads')")
    && has(read('resources/views/feedback/index.blade.php'), "route('settings.feedback')")
    && has(read('resources/views/layouts/partials/office-briefing.blade.php'), "route('settings.briefings')"));

check('the ledger list check names the route that exists',
    has(read('tools/checks/list-check.cjs'), "'settings.cashflow'")
    && !has(read('tools/checks/list-check.cjs'), "'cashflows.settings.index'"));

/* -------------------------------------------------------------- searching */

check('a setting is findable by the words a person uses for it',
    has(directory, 'public function searchHits(string $query')
    && has(directory, 'keywords')
    && has(search, 'searchHits($query')
    && has(search, "'key' => 'settings'")
    && has(search, "'label' => 'Settings'"));

check('the search box says it searches settings too',
    has(read('resources/views/layouts/partials/global-search.blade.php'), 'and settings.')
    && has(read('public/assets/js/global-search.js'), 'and settings.'));

/* ----------------------------------------------------------- the boundary */

check('the boundary rule is written down, not remembered',
    exists('docs/settings-module.md')
    && has(docs, 'A setting is a rule or a master list')
    && areas.every(key => has(docs, key))
    && has(docs, 'not settings'),
    'a module\'s own records — a product, a retainer, a user, a note — are not settings');

check('the module explains itself where the list is read',
    has(directorySource, 'docs/settings-module.md') && has(hub, 'Every module'),
    'a comment is where the next reader looks; the check reads the file, not the stripped code');

/* ------------------------------------------------------------------ report */

console.log(`\nsettings: ${passed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);
