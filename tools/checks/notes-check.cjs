/* ==========================================================================
   NOTES CHECK — a private thing, and the six ways it stops being private
   --------------------------------------------------------------------------
   Run:  node tools/checks/notes-check.cjs
   No dependencies. Exits non-zero on failure.

   Notes is the only screen in the application whose whole point is *who may
   read it*: an employee keeps stickies here and so does the office, and the
   failure that matters is not cosmetic — it is one person reading another
   person's notes. So the assertions below are about the ways that breaks:

     - **the reader** — every note is reached through `Note::ownedBy()`, a note
       that is not yours is a 404, and no route in the module takes a user id;
       the employee's menu may name this screen precisely because the screen
       asks the session and nobody else;
     - **the writer** — `NoteIntake` is the only thing that saves a note; the
       owner column is written in exactly one line, in `create()`; the colour
       written is always a colour the vocabulary knows; an unticked pin box
       un-pins the note (the checkbox nobody sends);
     - **the vocabulary** — every colour in `NoteVocabulary::COLOURS` has a rule
       in `notes.css` and a dark-theme value, and no view re-lists the colours;
     - **one query, two readouts** — the board and the table are one builder and
       a clone of it, the figures come out of one row of sums, and the chip
       counts are counted with the chip's own filter lifted;
     - **the page** — the shared shell, the shared drawer, a form for every
       write, and an empty state with a way out;
     - **the module sheet** — it declares no `master-*` class and no list chrome
       (those belong to the surface), and it is loaded where it is used.

   Every source assertion reads the file with its comments stripped: the prose
   in these files explains the rules, and a comment must never answer a check
   either way. (A round-17 guard matched its own docblock and "passed" on the
   sentence that described the bug.)
   ========================================================================== */

'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..', '..');
const read = relative => fs.readFileSync(path.join(ROOT, relative), 'utf8');
const exists = relative => fs.existsSync(path.join(ROOT, relative));
const plain = text => text.replace(/\/\*[\s\S]*?\*\//g, '').replace(/^\s*\/\/.*$/gm, '');
const has = (text, needle) => text.includes(needle);
const times = (text, needle) => text.split(needle).length - 1;

let passed = 0;
let failed = 0;
const check = (name, ok, detail = '') => {
    console.log(`  ${ok ? 'ok  ' : 'FAIL'} ${name}${ok || !detail ? '' : ` -> ${detail}`}`);
    ok ? passed++ : failed++;
};

/* ----------------------------------------------------------------- sources */

const controller = plain(read('app/Http/Controllers/NoteController.php'));
const model = plain(read('app/Models/Note.php'));
const vocabulary = plain(read('app/Services/NoteVocabulary.php'));
const filters = plain(read('app/Services/NoteFilters.php'));
const figures = plain(read('app/Services/NoteFigures.php'));
const intake = plain(read('app/Services/NoteIntake.php'));
const indexView = plain(read('resources/views/notes/index.blade.php'));
const editView = plain(read('resources/views/notes/edit.blade.php'));
const card = plain(read('resources/views/notes/partials/note-card.blade.php'));
const sheet = plain(read('public/assets/css/notes.css'));
const routes = plain(read('routes/web.php'));
const shell = plain(read('resources/views/layouts/app.blade.php'));
const migration = plain(read('database/migrations/2026_10_07_120000_create_notes_table.php'));

/* ------------------------------------------------------ 1. the reader (privacy) */

check('the note table belongs to a person, and goes when they do',
    /Schema::create\('notes'/.test(migration)
    && /foreignId\('user_id'\)->constrained\('users'\)->cascadeOnDelete\(\)/.test(migration),
    'expected a non-null user_id cascading from users');

const ownedBy = model.match(/public function scopeOwnedBy\([\s\S]*?\n    \}/);
check('ownership is a scope, and no owner means no rows',
    !!ownedBy
    && /where\('user_id', \$userId\)/.test(ownedBy[0])
    && /whereRaw\('1 = 0'\)/.test(ownedBy[0]),
    ownedBy ? 'the null-owner branch is missing' : 'scopeOwnedBy not found');

/* Every `Note::query()` in the controller has to grow an `->ownedBy(…)` before
   it can find anything — the module has three builders and all three are
   owned. A builder added without it would read the whole table. */
const builders = [...controller.matchAll(/Note::query\(\)/g)].map(match => match.index);
const unowned = builders.filter(at => ! /^[\s\S]{0,160}?->ownedBy\(/.test(
    controller.slice(at + 'Note::query()'.length)));
check('the controller reads notes only through the owner scope',
    builders.length >= 3 && unowned.length === 0,
    `${builders.length} builder(s), ${unowned.length} without ownedBy()`);

const mine = controller.match(/private function mine\([\s\S]*?\n    \}/);
check('one door to one row: an id, looked up inside the owner\'s notes',
    !!mine
    && /ownedBy\(\$request->user\(\)->id\)/.test(mine[0])
    && /findOrFail\(\$id\)/.test(mine[0]),
    mine ? '' : 'mine() not found');

check('and it is the door every action uses — never inline, never bound',
    times(controller, '$this->mine($request, $note)') === 5
    && ! /Note \$note/.test(controller)
    && ! /\$note->user_id/.test(controller),
    `${times(controller, '$this->mine($request, $note)')} call(s), type-hint ${/Note \$note/.test(controller) ? 'present' : 'absent'}`);

const officeStart = routes.indexOf("Route::middleware('office')->group(function () {");
const officeEnd = routes.indexOf('\n    });', officeStart);
const notesStart = routes.indexOf("Route::prefix('notes')->name('notes.')->group(function () {");
check('the notes routes are the one page both halves may open',
    officeStart > -1 && officeEnd > officeStart
    && notesStart > officeEnd
    && routes.indexOf("Route::middleware('auth')->group(function () {") < notesStart,
    `office ${officeStart}–${officeEnd}, notes ${notesStart}`);

const notesGroup = notesStart > -1 ? routes.slice(notesStart, routes.indexOf('\n    });', notesStart)) : '';
check('no route in the module takes a user id — the person is the session',
    notesGroup !== '' && ! /\{user\}/.test(notesGroup) && /\{note\}/.test(notesGroup),
    notesGroup === '' ? 'the notes route group was not found' : notesGroup.slice(0, 80));

check('no note view names its owner or asks for one',
    ! /\$note->user\b/.test(indexView + editView + card)
    && ! /user_id/.test(indexView + editView + card));

/* -------------------------------------------------------- 2. the writer (one) */

check('the intake is the only thing that saves a note',
    ['create', 'update', 'pin', 'fileAway', 'restore', 'delete']
        .every(method => new RegExp(`function ${method}\\(`).test(intake))
    && /\$note->save\(\);/.test(intake)
    && ! /\$note->save\(\)|Note::create\(|\$note->update\(|\$note->delete\(/.test(controller),
    'the controller touches the row itself');

check('and the controller hands every write to it',
    ['create', 'update', 'pin', 'fileAway', 'restore', 'delete']
        .every(method => has(controller, `$this->intake->${method}`)),
    'a write that skipped the intake is a write with no rules');

check('the owner is written once, in create(), from a person and not an id',
    /\$note->user_id = \$owner->id;/.test(intake)
    && times(intake, 'user_id') === 1
    && /public function create\(User \$owner, array \$facts\): Note/.test(intake),
    `${times(intake, 'user_id')} mention(s) of user_id`);

check('a colour the vocabulary does not know is the default colour',
    has(intake, 'NoteVocabulary::normalise(')
    && /return self::hasColour\(\$colour\) \? \$colour : self::DEFAULT_COLOUR;/.test(vocabulary)
    && /Rule::in\(array_keys\(NoteVocabulary::colours\(\)\)\)/.test(controller));

check('and every colour lives in one list — the table carries no default',
    /string\('colour', 20\)->nullable\(\);/.test(migration)
    && ! /string\('colour'[^;]*default\(/.test(migration));

check('an empty body is no body, not a body of spaces',
    /return \$body === '' \? null : \$body;/.test(intake));

check('an unticked pin box un-pins the note',
    /\$validated\['is_pinned'\] = \$request->boolean\('is_pinned'\);/.test(controller)
    && has(intake, "array_key_exists('is_pinned', \$facts)"),
    'a checkbox that is not sent must read as false, not as "unchanged"');

check('clearing a field is different from never mentioning it',
    times(intake, 'array_key_exists(') >= 4);

/* --------------------------------------------------- 3. the vocabulary ↔ css */

const colourBlock = vocabulary.match(/const COLOURS = \[([\s\S]*?)\];/);
const colours = colourBlock ? [...colourBlock[1].matchAll(/'([a-z0-9_]+)'\s*=>/g)].map(m => m[1]) : [];
check('the vocabulary lists colours at all', colours.length >= 4, `${colours.length} colour(s)`);

const missingRules = colours.filter(colour => ! new RegExp(`\\.nt-note--${colour}\\b`).test(sheet));
check('every colour has a rule on the board', missingRules.length === 0, missingRules.join(', '));

const missingDots = colours.filter(colour => ! new RegExp(`\\.nt-dot--${colour}\\b`).test(sheet));
check('and a dot in the chips and the table', missingDots.length === 0, missingDots.join(', '));

const missingDark = colours.filter(colour => ! new RegExp(`\\[data-theme="dark"\\][^{]*\\.nt-note--${colour}\\b`).test(sheet));
check('and a value for the dark theme, or the colour is invisible there', missingDark.length === 0, missingDark.join(', '));

check('the default colour is one of the colours',
    /const DEFAULT_COLOUR = '([a-z0-9_]+)';/.test(vocabulary)
    && colours.includes(vocabulary.match(/const DEFAULT_COLOUR = '([a-z0-9_]+)';/)[1]));

check('no view re-lists the colours',
    has(indexView, '@foreach ($colourOptions as $key => $label)')
    && has(editView, '@foreach ($colourOptions as $key => $label)')
    && ! /'yellow'/.test(indexView + editView + card));

/* ------------------------------------------- 4. one query, two readouts (the page) */

check('the board and the table are one builder and a clone of it',
    /\$query = \$this->filters->apply\(\s*Note::query\(\)->ownedBy\(\$owner->id\),\s*\$filters\s*\)/.test(controller)
    && /\$board = \(clone \$query\)->deskOrder\(\)->limit\(NoteVocabulary::BOARD_LIMIT\)->get\(\);/.test(controller)
    && /\$notes = \$this->filters->order\(\$query, \$filters\)->paginate\(20\)->withQueryString\(\);/.test(controller));

check('the figures are one row of sums, cloned and de-ordered',
    has(figures, '(clone $query)') && has(figures, '->reorder()')
    && times(figures, 'selectRaw(') >= 5
    && ! /->get\(\)|->count\(\)/.test(figures));

check('the chip counts are counted with the chip\'s own filter lifted',
    /withoutState\(\$filters\)/.test(controller)
    && /array_merge\(\$filters, \['state' => 'everything'\]\)/.test(filters),
    'a chip that counts its own state reads 0 while you stand on it');

check('the board is capped, and the page says so',
    has(vocabulary, 'const BOARD_LIMIT = 24;')
    && has(indexView, '$boardIsFull')
    && has(indexView, 'Showing the first {{ number_format($boardLimit) }}'),
    'a capped board that does not say it is capped is a lie about the pile');

check('the filters narrow through the model\'s own scopes, not raw columns',
    ['->onTheDesk()', '->pinned()', '->filedAway()', '->inColour($filters[\'colour\'])', '->editedSince($moment)']
        .every(scope => has(filters, scope))
    && ! /->where\('archived_at'/.test(filters),
    'the module re-writes SQL the model already owns');

const sortBlock = filters.match(/const SORT_SCOPES = \[([\s\S]*?)\];/);
const sortScopes = sortBlock ? [...sortBlock[1].matchAll(/'[a-z0-9_]+'\s*=>\s*'([a-zA-Z]+)'/g)].map(m => m[1]) : [];
const missingScopes = sortScopes.filter(scope => ! new RegExp(`function scope${scope[0].toUpperCase()}${scope.slice(1)}\\(`).test(model));
check('every order a reader can choose is a scope the model owns',
    sortScopes.length >= 3 && missingScopes.length === 0, missingScopes.join(', '));

check('the board keeps its own order, whatever the table is sorted by',
    has(model, 'public function scopeDeskOrder')
    && has(model, "->orderByDesc('is_pinned')")
    && ! has(filters, 'deskOrder'));

const stateWords = filters.match(/const STATE_LABELS = \[([\s\S]*?)\];/);
const stateKeys = stateWords ? [...stateWords[1].matchAll(/'([a-z]+)'\s*=>/g)].map(m => m[1]) : [];
check('every state chip the screen offers is a state the query understands',
    ['desk', 'pinned', 'filed', 'everything'].every(key => stateKeys.includes(key))
    && ['desk', 'pinned', 'filed'].every(key => has(filters, `=== '${key}'`)),
    stateKeys.join(', '));

check('a hand-typed filter value falls back to the default, never to a 500',
    ['$state', '$period', '$sort'].every(name => new RegExp(`array_key_exists\\(\\${name}, self::[A-Z_]+`).test(filters))
    && /! NoteVocabulary::hasColour\(\$colour\)/.test(filters));

check('the periods reach back from the office\'s today, not the server\'s',
    has(filters, 'DateRanges::today()')
    && ['today', 'week', 'month'].every(period => has(filters, `'${period}' =>`)));

/* ---------------------------------------------------------- 5. the page itself */

check('the page is the shared master-list, chips and all',
    ['master-list', 'master-list-bar', 'master-list-chip', 'master-list-applied', 'master-search',
        'master-filter-row', 'master-table-card', 'master-table-wrap', 'master-table', 'master-list-empty']
        .every(className => has(indexView, className)));

check('the secondary criteria open in the shared right drawer',
    has(indexView, '<x-filter-trigger drawer="notesFiltersDrawer"')
    && has(indexView, '<x-drawer id="notesFiltersDrawer"')
    && has(indexView, '<x-slot:footer>'));

check('a criterion is asked once: the chips carry state and colour, the drawer the rest',
    has(indexView, 'name="period"') && has(indexView, 'name="sort"')
    && ! /name="state"/.test(indexView.slice(indexView.indexOf('<x-drawer')))
    && ! /name="colour"/.test(indexView.slice(indexView.indexOf('<x-drawer'))),
    'the drawer repeats a chip the reader can already see');

check('a search keeps the chips the form does not own',
    /@if \(\$state !== 'desk'\)\s*<input type="hidden" name="state"/.test(indexView)
    && /@if \(\$colour !== 'all'\)\s*<input type="hidden" name="colour"/.test(indexView),
    'without them, typing a search silently drops the state and the colour');

check('every write on the page is a form with a token, and a delete is never a link',
    times(indexView + card, '@csrf') >= 3
    && ! /href="\{\{ route\('notes\.(pin|archive|destroy)'/.test(indexView + card + editView));

check('the board card is a link to the note, with no control inside it',
    /<a class="nt-note nt-note--/.test(card)
    && ! /<form|<button/.test(card),
    'a button inside a link is two controls for one click');

check('the card keeps the words\' line breaks, escaped first',
    has(card, '{!! nl2br(e($note->body)) !!}'));

check('the empty state offers a way out',
    has(indexView, 'master-list-empty-actions')
    && has(indexView, 'href="#noteComposer"')
    && has(indexView, 'Clear the filters'));

check('the table becomes labelled cards on a phone',
    has(indexView, 'ui-mobile-cards')
    && times(indexView, 'data-label=') >= 4);

check('the module sheet is loaded by both notes screens, and owns no shared class',
    times(indexView, "assets/css/notes.css") === 1
    && times(editView, "assets/css/notes.css") === 1
    && ! /(^|\})\s*\.master-[a-z0-9-]+/m.test(sheet)
    && ! /\.master-list-(?:bar|chip|applied|toolbar|hint|empty)/.test(sheet),
    'a module-local copy of a shared class drifts the day the shared one is fixed');

check('the composer, the board and the table each sit on the shell\'s own rhythm',
    has(sheet, '.nt .nt-board') && has(sheet, '.nt .nt-compose-grid') && has(sheet, '.nt-edit .nt-edit-grid'));

/* --------------------------------------------------------------- 6. the shell */

const employeeMenu = shell.slice(shell.indexOf('$employeeItems = ['), shell.indexOf('$sidebarItems = Auth::user()'));
check('an employee keeps notes too, from their own menu',
    /'label' => 'Notes', 'route' => 'notes\.index'/.test(employeeMenu));

const officeMenu = shell.slice(shell.indexOf('$sidebarItems = Auth::user()'));
check('and the office has its own, in a section of its own',
    /'section' => 'Personal'/.test(officeMenu)
    && /'label' => 'Notes', 'route' => 'notes\.index'/.test(officeMenu));

check('the shell tells the stylesheets which module the page is',
    /request\(\)->routeIs\('notes\.\*'\) => 'notes'/.test(shell));

/* The topbar door, beside the search control. Both halves of the application
   see it — an employee's topbar has no search button at all — so it may not sit
   inside the office-only block, it must be a link (a control that navigates is
   a link, or the keyboard and the middle-click both lose), it must name itself
   in words because it is icon-only, and it must say when it is the current
   page. */
const topbar = shell.slice(shell.indexOf('<div class="topbar-actions">'), shell.indexOf('</header>'));
const topbarLink = topbar.indexOf("route('notes.index')");
check('the topbar carries a notes door, beside the search control',
    topbarLink > -1
    && topbarLink > topbar.indexOf('@endif')
    && topbarLink < topbar.indexOf("route('theme.toggle')")
    && /<a class="topbar-btn" href="\{\{ route\('notes\.index'\) \}\}"/.test(topbar),
    'it must be a link, outside the office-only block, before the theme switch');

check('the icon-only door names itself and marks the current page',
    /aria-label="Notes"/.test(topbar)
    && /title="Notes — private to this login"/.test(topbar)
    && /@if \(request\(\)->routeIs\('notes\.\*'\)\) aria-current="page" @endif/.test(topbar)
    && /fa-regular fa-note-sticky/.test(topbar));

/* An icon button that is an `<a>` keeps the browser's link blue unless the
   class it wears sets a colour — the same control would then be two colours
   depending on its tag. */
const layoutSheet = plain(read('public/assets/css/app-layout.css'));
const topbarBtn = layoutSheet.match(/\.topbar-btn \{[\s\S]*?\}/);
check('the shell\'s topbar button gives an anchor the theme\'s colour, not the browser link blue',
    !!topbarBtn && /color: var\(--text-primary\)/.test(topbarBtn[0]),
    topbarBtn ? topbarBtn[0].replace(/\s+/g, ' ').slice(0, 90) : '.topbar-btn not found');

check('and the current page is marked with the shell\'s own accent chip',
    /\.topbar-btn\[aria-current="page"\] \{[\s\S]*?background: var\(--accent-light\);[\s\S]*?color: var\(--accent\);/.test(layoutSheet));


check('all seven doors are registered, with the verbs they claim',
    /Route::get\('\/', \[NoteController::class, 'index'\]\)->name\('index'\)/.test(notesGroup)
    && /Route::post\('\/', \[NoteController::class, 'store'\]\)->name\('store'\)/.test(notesGroup)
    && /Route::get\('\/\{note\}\/edit', \[NoteController::class, 'edit'\]\)->name\('edit'\)/.test(notesGroup)
    && /Route::put\('\/\{note\}', \[NoteController::class, 'update'\]\)->name\('update'\)/.test(notesGroup)
    && /Route::patch\('\/\{note\}\/pin', \[NoteController::class, 'pin'\]\)->name\('pin'\)/.test(notesGroup)
    && /Route::patch\('\/\{note\}\/archive', \[NoteController::class, 'archive'\]\)->name\('archive'\)/.test(notesGroup)
    && /Route::delete\('\/\{note\}', \[NoteController::class, 'destroy'\]\)->name\('destroy'\)/.test(notesGroup),
    notesGroup.slice(0, 120));

/* ------------------------------------------------------------- 7. the paperwork */

check('the filter drawer is on the shared-drawer contract list',
    has(read('tools/checks/ui-components-check.cjs'), "'resources/views/notes/index.blade.php'"));

check('the module is written down',
    exists('docs/notes-module.md')
    && /tools\/checks\/notes-check\.cjs/.test(read('docs/notes-module.md'))
    && /NoteIntake/.test(read('docs/notes-module.md'))
    && /NoteVocabulary/.test(read('docs/notes-module.md')));

check('the check itself is listed with its siblings',
    /notes-check\.cjs/.test(read('tools/checks/README.md')));

/* A source check reads the SQL, not what the SQL says: that a missing owner
   matches no rows, and that a hand-typed filter falls back, are questions only
   a running test can answer. */
check('the rules a source check cannot prove are held by a test',
    exists('tests/Unit/NotesRulesTest.php')
    && /NotesRulesTest/.test(read('docs/notes-module.md'))
    && /1 = 0/.test(read('tests/Unit/NotesRulesTest.php')));

/* The employee workspace doc lists their menu item by item; a menu that grew
   without the map being redrawn is a map nobody can use. */
check('the employee workspace doc names the notes screen',
    /Notes/.test(read('docs/employee-workspace.md')));

/* ---------------------------------------------------------------- report */

console.log(`\nnotes: ${passed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);
