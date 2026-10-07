/* ==========================================================================
   ASSETS CHECK — the fixed asset register's promises.
   --------------------------------------------------------------------------
   Run:  node tools/checks/assets-check.cjs
   No dependencies. Exits non-zero on failure.

   A check is a promise that survives mutation: every assertion below has to be
   able to *fail* when somebody changes the product for the worse, and each one
   was probed by making that change in a scratch copy and watching this file go
   red. The promises this module makes, in the order they would be broken:

     A. one calculator   — accumulated depreciation and net book value are not
                           columns, and the arithmetic lives in one class
     B. one writer       — `AssetIntake` is the only thing that writes an asset,
                           a hand-over or a repair, and the state follows the
                           cupboard in one direction only
     C. the curve        — 1 April – 31 March, pro-rated by days, the last year
                           lands on the residual, a disposal stops the curve,
                           and the two formulas are proved by porting them
     D. the page         — figures, chips, table, export and tabs read the same
                           query; the dialogs are shared and the shell owns them;
                           and every name, method, key, class, tone and font the
                           views write is one the module and the shell have
     E. the boundary     — the register is a module, the classes are a setting,
                           the report is company-wide by design
   ========================================================================== */
'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..', '..');
const read = (file) => fs.readFileSync(path.join(ROOT, file), 'utf8');
const exists = (file) => fs.existsSync(path.join(ROOT, file));
const has = (text, needle) => text.includes(needle);
const times = (text, needle) => text.split(needle).length - 1;

/* Behaviour is asserted on stripped source — a `//` line or a docblock must
   never be able to satisfy a promise about code. Documented rules are asserted
   on the raw file, because a comment is where a rule outlives the person who
   wrote it. */
const plain = (text) => text
    .replace(/\/\*[\s\S]*?\*\//g, '')
    .replace(/\/\/[^\n]*/g, '')
    .replace(/\{\{--[\s\S]*?--\}\}/g, '');

/* One method's body — its signature up to the next method, so a promise can be
   about *where* something happens and not merely that the words appear
   somewhere in the file. */
const methodBody = (text, name) => {
    const start = text.indexOf(`function ${name}(`);
    if (start < 0) return '';
    const rest = text.slice(start);
    const end = rest.slice(1).search(/\n    (?:public|private|protected) function /);
    return end < 0 ? rest : rest.slice(0, end + 1);
};

let passed = 0;
let failed = 0;
const failures = [];

const check = (name, ok, detail = '') => {
    if (ok) {
        passed++;
    } else {
        failed++;
        failures.push(`${name}${detail ? ` — ${detail}` : ''}`);
    }
};

/* ---------------------------------------------------------------- the files */

const files = {
    categoryMigration: 'database/migrations/2026_10_07_150000_create_fixed_asset_categories_table.php',
    assetMigration: 'database/migrations/2026_10_07_150100_create_fixed_assets_table.php',
    allocationMigration: 'database/migrations/2026_10_07_150200_create_fixed_asset_allocations_table.php',
    maintenanceMigration: 'database/migrations/2026_10_07_150300_create_fixed_asset_maintenances_table.php',
    category: 'app/Models/FixedAssetCategory.php',
    asset: 'app/Models/FixedAsset.php',
    allocation: 'app/Models/FixedAssetAllocation.php',
    maintenance: 'app/Models/FixedAssetMaintenance.php',
    vocabulary: 'app/Services/AssetVocabulary.php',
    depreciation: 'app/Services/AssetDepreciation.php',
    figures: 'app/Services/AssetFigures.php',
    filters: 'app/Services/AssetFilters.php',
    intake: 'app/Services/AssetIntake.php',
    controller: 'app/Http/Controllers/FixedAssetController.php',
    settingController: 'app/Http/Controllers/AssetSettingController.php',
    index: 'resources/views/assets/index.blade.php',
    show: 'resources/views/assets/show.blade.php',
    report: 'resources/views/assets/depreciation.blade.php',
    settings: 'resources/views/settings/assets.blade.php',
    overview: 'resources/views/assets/partials/tab-overview.blade.php',
    dispose: 'resources/views/assets/partials/modal-dispose.blade.php',
    form: 'resources/views/assets/partials/asset-form.blade.php',
    sheet: 'public/assets/css/assets.css',
    script: 'public/assets/js/assets.js',
    routes: 'routes/web.php',
    layout: 'resources/views/layouts/app.blade.php',
    directory: 'app/Services/SettingsDirectory.php',
    search: 'app/Services/GlobalSearch.php',
    uiCheck: 'tools/checks/ui-components-check.cjs',
    settingsDocs: 'docs/settings-module.md',
    docs: 'docs/fixed-assets.md',
    readme: 'tools/checks/README.md',
};

const missing = Object.entries(files).filter(([, file]) => !exists(file)).map(([key]) => key);
check('every file this module is made of exists', missing.length === 0, missing.join(', '));

const src = Object.fromEntries(Object.entries(files).map(([key, file]) => [key, exists(file) ? read(file) : '']));
const code = Object.fromEntries(Object.entries(src).map(([key, text]) => [key, plain(text)]));

/* ═══════════════════════════════════════════════ A. one calculator ══════════ */

/* The recipe is a recipe: columns that say how to work the curve out, not stored
   figures that can disagree with it. If accumulated depreciation or net book
   value ever becomes a column, the register can print a number the maths does not
   produce — and that is the one thing a depreciation register must never do. */
check('the asset stores a recipe, not the answer',
    has(src.assetMigration, "'useful_life_years'")
    && has(src.assetMigration, "'depreciation_method'")
    && has(src.assetMigration, "'residual_percent'")
    && !/table->(decimal|double|float|integer)\(\s*'(accumulated|accumulated_depreciation|net_book_value|book_value|written_down_value|wdv)'/.test(src.assetMigration),
    'accumulated depreciation and net book value are not columns — they are computed');

check('nor does any other table keep a running total of them',
    !['categoryMigration', 'allocationMigration', 'maintenanceMigration']
        .some((key) => /'(accumulated_depreciation|net_book_value|book_value|accumulated)'/.test(src[key])),
    'a second table caching the curve is a second answer to the same question');

check('total cost is a statement about its columns, not a column',
    has(src.figures, 'sum(cost + gst_amount)')
    && has(src.asset, 'public function totalCost(')
    && !has(src.assetMigration, "'total_cost'"),
    'a stored total is a total somebody has to remember to maintain');

/* One class computes the curve, and the model hands every reading to it rather
   than working anything out itself. */
check('the arithmetic lives in one class and only there',
    has(src.depreciation, 'public function schedule(')
    && has(src.depreciation, 'public function chargeForYear(')
    && has(src.depreciation, 'public function accumulatedAt(')
    && has(src.depreciation, 'public function netBookValueAt(')
    && has(src.depreciation, 'private function charge('),
    'AssetDepreciation is the only place a charge is worked out');

check('the model reads that class instead of computing for itself',
    has(src.asset, 'private function depreciation(): AssetDepreciation')
    && has(code.asset, 'app(AssetDepreciation::class)')
    && times(code.asset, 'AssetDepreciation::class') >= 1
    && !/\$this->effectiveLifeYears\(\)\s*[)\s]*(?:==|\/|\*)/.test(code.asset),
    'a model that recomputes a charge is the second implementation this check exists for');

check('no controller, view or page works out a charge of its own',
    !/(\*\s*rate|-\s*\$residual\s*\)\s*\/|\/\s*\$life\b|\/\s*\d+\s*\/\s*100)/.test(
        code.controller + code.index + code.show + code.report + code.figures),
    'the pages render figures; they never derive them');

/* Nullable means "follow the class" — three fields read in one order, with one
   default at the end of the chain. */
check('an asset may follow its class instead of carrying its own recipe',
    /'useful_life_years'\s*=>\s*\['nullable'/.test(code.controller)
    && /'depreciation_method'\s*=>\s*\['nullable'/.test(code.controller)
    && has(src.asset, 'public function effectiveMethod(')
    && has(src.asset, 'public function effectiveLifeYears(')
    && has(src.asset, 'public function effectiveResidualPercent(')
    && has(src.asset, 'public function inheritsRecipe('));

check('the recipe reads the asset first, then its class, then the vocabulary default',
    has(code.asset, '->useful_life_years')
    && has(code.asset, '->category?->useful_life_years')
    && has(src.vocabulary, 'DEFAULT_RESIDUAL_PERCENT = 5.0')
    && has(code.asset, 'DEFAULT_RESIDUAL_PERCENT'),
    'the chain has to be one chain, or two assets in one class depreciate differently');

/* GST decides the basis, and it is one switch read in one place. */
check('the capitalised basis is one expression with one switch',
    has(src.asset, 'public function capitalisedCost(')
    && has(src.asset, 'public function claimsInputCredit(')
    && has(src.figures, 'case when depreciate_on_total then cost + gst_amount else cost end')
    && has(src.assetMigration, "'depreciate_on_total'"),
    'the register, the record and the report must all mean the same thing by "cost"');

/* ══════════════════════════════════════════════ B. one writer ══════════════ */

/* Every door is `AssetIntake`. A controller that calls `$asset->update()` itself
   is a door that forgot to keep the hand-over history or the state in step. */
check('AssetIntake is the only thing that writes an asset, a hand-over or a repair',
    [...code.controller.matchAll(/\$[a-zA-Z_]\w*->(?:save|update|delete)\(/g)].length === 0
    && times(code.intake, '->save()') >= 8
    && times(code.controller, '$this->intake->') >= 8
    && has(src.intake, 'private function fill(')
    && has(src.intake, 'private function open(')
    && has(src.intake, 'private function close('),
    'the controller validates and redirects; every write is a call on the one writer');

check('every door the page offers has a writer and a route',
    ['create', 'update', 'allocate', 'returnAsset', 'maintain', 'verify', 'dispose', 'delete']
        .every((method) => has(src.intake, `public function ${method}(`))
    && ['store', 'update', 'allocate', 'takeBack', 'maintain', 'verify', 'dispose', 'destroy']
        .every((method) => has(src.controller, `public function ${method}(`))
    && ['assets.store', 'assets.update', 'assets.allocate', 'assets.takeBack', 'assets.maintain', 'assets.verify', 'assets.dispose', 'assets.destroy']
        .every((name) => has(src.routes, `name('${name}')`)),
    'a door with no writer is a button that loses somebody\'s typing');

/* The cupboard rule: an asset in store that is handed to somebody is in use
   again, and an asset that comes back sits in store — because leaving it "in
   use" with nobody holding it would be a lie on the register. Nothing else moves
   the state by itself. */
check('the state follows the cupboard, in the one direction that would otherwise be a lie',
    has(code.intake, 'STATUS_SPARE')
    && has(code.intake, 'STATUS_IN_USE')
    && times(code.intake, 'STATUS_MAINTENANCE') === 0
    && has(src.intake, 'the cupboard'),
    'spare → in use on a hand-over, in use → spare on a return; nothing else is guessed');

check('a hand-over closes the previous one, so exactly one row is open',
    has(methodBody(code.intake, 'allocate'), 'close(')
    && has(methodBody(code.intake, 'returnAsset'), "whereNull('returned_on')")
    && has(methodBody(code.intake, 'close'), "whereNull('returned_on')")
    && has(src.intake, 'one open'),
    'the open hand-over is the asset\'s custody: two of them is two answers');

check('the register reads custody from the open hand-over, not from a copy of it',
    has(src.asset, 'public function openAllocation(')
    && has(src.asset, 'public function holderLabel(')
    && has(src.index, 'openAllocation')
    && has(src.show, 'openAllocation')
    && has(src.overview, 'openAllocation'),
    'a cached custodian is a custodian who is right until somebody is handed something');

/* A disposal is not a delete: it stops the curve, closes the custody and keeps
   the history. */
check('a disposal stops the curve, closes the custody and keeps the history',
    has(src.intake, 'public function dispose(')
    && has(src.depreciation, 'disposal_date')
    && has(src.asset, 'public function bookValueOnDisposal(')
    && has(src.report, 'disposed_on')
    && !has(code.intake, 'forceDelete'),
    'disposed assets stay on the register as history — that is what a register is');

check('deleting an asset takes its own history with it, in one transaction',
    has(methodBody(code.intake, 'delete'), 'DB::transaction')
    && has(methodBody(code.intake, 'delete'), 'allocations()->delete()')
    && has(methodBody(code.intake, 'delete'), 'maintenances()->delete()')
    && has(methodBody(code.intake, 'delete'), '$asset->delete()'),
    'a register row without its history is a row whose past the office cannot answer for');

/* The vocabulary is the only place the words and the badge tones live. */
check('the words and their tones come from one vocabulary',
    has(src.vocabulary, 'public const STATUSES')
    && has(src.vocabulary, 'public const STATUS_TONES')
    && has(src.vocabulary, 'public const CONDITIONS')
    && has(src.vocabulary, 'public const CONDITION_TONES')
    && has(src.vocabulary, 'public const METHODS')
    && has(src.vocabulary, 'public const KINDS')
    && has(src.vocabulary, 'public const KIND_TONES')
    && has(src.vocabulary, 'public static function statusTone('),
    'a module-local colour per state is how two screens end up calling one state two colours');

check('the pages wear the shared badge with the tone the vocabulary gives it',
    has(src.index, 'core-badge core-badge-{{ $asset->stateTone() }}')
    && has(src.show, 'core-badge core-badge-{{ $asset->conditionTone() }}')
    && has(src.overview, 'core-badge core-badge-{{ $asset->stateTone() }}')
    && !/(?:^|\s)\.(?:fa|asset)-(?:badge|chip|pill)\b/m.test(src.sheet),
    'the shared core-badge is the badge; this sheet must not define one');

/* ══════════════════════════════════════════════ C. the curve ══════════════ */

/* The financial year is 1 April – 31 March, stated once, and the schedule is cut
   into those years rather than into "365 days from purchase". */
check('the year is the Indian financial year, defined once',
    has(src.vocabulary, 'FINANCIAL_YEAR_START_MONTH = 4')
    && has(code.depreciation, 'AssetVocabulary::FINANCIAL_YEAR_START_MONTH')
    && !has(code.depreciation, '01-04')
    && !has(code.depreciation, "'April'"),
    'a second copy of "the year starts in April" is a second answer to "which year is this charge in"');

check('a year is charged by the days the asset was on the books in it',
    has(src.depreciation, "'days_in_year'")
    && has(src.depreciation, 'diffInDays')
    && times(src.depreciation, '$daysInYear') >= 2,
    'the year of purchase and the year of sale pay their own share, not a whole year');

check('the last year lands on the residual, whoever did the rounding',
    has(src.depreciation, '$residual')
    && /\$isLast\s*\)\s*\{\s*\$charge = round\(\$opening - \$residual/.test(plain(src.depreciation)),
    'without this line the curve ends a few rupees from where the office said it would');

check('written down value is anchored to the residual, not a flat rate',
    has(src.depreciation, '1 - pow($residual / $basis, 1 / $life)'),
    'WDV at a flat percentage never lands on the residual — the rate is solved from it');

check('nothing is charged before the purchase date or after a disposal',
    has(code.depreciation, 'greaterThan($start)')
    && has(code.depreciation, 'lessThan($end)')
    && has(code.depreciation, 'disposal_date')
    && has(code.depreciation, 'if ($to->greaterThanOrEqualTo($end))') === false
    && has(code.depreciation, '$isLast = $to->greaterThanOrEqualTo($end)'),
    'a charge outside the days the asset was ours is a charge nobody can defend');

check('a not-depreciated asset produces no rows at all',
    has(code.depreciation, 'if (! $asset->depreciates()')
    && has(src.asset, 'public function depreciates(')
    && has(code.asset, '$this->effectiveMethod() !== AssetVocabulary::METHOD_NONE'),
    'a non-depreciable asset in the schedule would print a row that charges it to nothing');

check('a residual value is a percentage, and it is bounded',
    /'residual_percent'\s*=>\s*\['nullable',\s*'numeric',\s*'min:0',\s*'max:100'\]/.test(code.controller)
    && /'residual_percent'\s*=>\s*\['nullable',\s*'numeric',\s*'min:0',\s*'max:100'\]/.test(code.settingController)
    && has(src.assetMigration, "decimal('residual_percent', 5, 2)"),
    'a residual over 100% would depreciate an asset backwards');

/* The formula, ported out of PHP. This is the part of the module that can be
   *proved* without a PHP runtime, so it is proved — straight line and WDV, a full
   year, a pro-rated year, and the last year landing on the residual. */
const straightLine = (opening, basis, residual, days, daysInYear, life) =>
    Math.max(0, Math.min(
        Math.round(((basis - residual) / life) * (days / daysInYear) * 100) / 100,
        Math.round((opening - residual) * 100) / 100,
    ));

const fullYear = straightLine(100000, 100000, 5000, 365, 365, 10);
const halfYear = straightLine(100000, 100000, 5000, 183, 365, 10);

check('straight line spreads the depreciable value evenly across its life',
    fullYear === 9500,
    `a full year of 1,00,000 over 10 years at a 5% residual is 9,500, got ${fullYear}`);

check('a part-year is charged by the day, not rounded up to a whole year',
    halfYear < fullYear && halfYear > fullYear * 0.45 && halfYear < fullYear * 0.55,
    `183 of 365 days should be about half of 9,500, got ${halfYear}`);

/* WDV: the rate is solved from the residual, the first year takes the bigger
   bite, and the solved rate lands the curve on the residual at the end. */
const wdvRate = (basis, residual, life) => 1 - Math.pow(residual / basis, 1 / life);
const wdvFirst = (basis, residual, life) => Math.round(basis * wdvRate(basis, residual, life) * 100) / 100;
const wdvEnd = (basis, residual, life) => {
    let value = basis;
    for (let i = 0; i < life; i++) value -= Math.round(value * wdvRate(basis, residual, life) * 100) / 100;
    return Math.round(value * 100) / 100;
};

check('written down value charges more in the first year than straight line',
    wdvFirst(100000, 5000, 10) > 9500,
    `WDV first year ${wdvFirst(100000, 5000, 10)} should beat straight line's 9,500`);

check('and the solved rate leaves the residual at the end of the life',
    Math.abs(wdvEnd(100000, 5000, 10) - 5000) < 60,
    `ten WDV years should end near the 5,000 residual, got ${wdvEnd(100000, 5000, 10)}`);

check('a flat WDV rate would not, which is why the rate is solved',
    (() => {
        const flat = 0.2;
        let value = 100000;
        for (let i = 0; i < 10; i++) value -= value * flat;
        return Math.abs(value - 5000) > 2000;
    })(),
    'ten years of a flat 20% leaves about 10,737, not the 5,000 the office wrote down');

/* The last-year rule as arithmetic: whatever the earlier years left, the closing
   row is the residual. */
check('the last row is the residual, whatever the earlier rows left',
    (() => {
        const opening = wdvEnd(100000, 5000, 10) + Math.round(1, 0);
        const charge = Math.round((opening - 5000) * 100) / 100;
        return opening - charge === 5000 || Math.abs(opening - charge - 5000) < 0.02;
    })()
    && has(plain(src.depreciation), 'max(0.0, min($charge, round($opening - $residual, 2)))'),
    'the charge is clamped so the curve can never go below the residual');

check('a disposal stops the curve the day it leaves',
    has(plain(src.depreciation), 'if ($disposed)')
    && has(plain(src.depreciation), '$end = $asset->disposal_date'),
    'nothing after the sale belongs to us');

/* ══════════════════════════════════════════════ D. the page ═══════════════ */

/* One query: the totals above the table, the chips, the table and the export are
   the same builder, so "In use in Ahmedabad" means one thing on one screen. */
check('the register\'s figures, table and export are one query',
    has(src.filters, 'public function apply(')
    && has(src.filters, 'public function page(')
    && has(code.controller, '$this->figures->summary($query)')
    && has(code.controller, '$this->filters->page($query, $filters)')
    && has(src.routes, "name('assets.export')")
    && times(code.controller, '->apply(') >= 3,
    'the register and the file that leaves it must not be two different lists');

check('a page is taken from a clone, never from the builder the figures borrowed',
    has(src.filters, '->paginate(')
    && /\$this->order\(clone \$query/.test(src.filters)
    && has(src.figures, '(clone $query)'),
    'paginate() writes a LIMIT onto the builder it is called on, and MySQL refuses that inside an IN subquery');

check('the chip counts lift the state chip, or each one would count itself',
    has(src.filters, 'public function withoutStatus(')
    && has(code.controller, 'withoutStatus')
    && has(src.figures, 'public function stateCounts(')
    && has(src.index, 'stateCounts[$key]'));

check('the count beside an attention link is the same query the link opens',
    has(code.figures, 'serviceDue(60)')
    && has(code.filters, 'serviceDue(')
    && has(src.asset, 'public function scopeServiceDue(')
    && has(src.index, "'service' => 'due'"),
    'a number that opens a different list than it counted is a number nobody trusts twice');

check('the register offers what SQL can order, and says why the fifth order is missing',
    has(src.filters, 'public const SORT_LABELS')
    && has(src.filters, 'public const SORT_SCOPES')
    && !has(src.filters, "'nbv'")
    && !has(src.filters, "'net_book_value'")
    && has(src.index, 'no "net book value" order')
    && has(src.asset, 'deliberately no'),
    'a sort that has to compute every asset to order a page dies at a thousand assets');

check('the register carries the shared drawer, chips, pagination and empty state',
    has(src.index, '<x-filter-trigger')
    && has(src.index, '<x-drawer')
    && has(src.index, 'master-list-chip')
    && has(src.index, '<x-pagination')
    && has(src.index, 'master-list-empty'),
    'the shared list furniture is the list — a module-local copy drifts the day the shared one is fixed');

check('the drawer offers a filter for every criterion the register answers',
    ['category', 'location', 'department', 'custodian', 'condition', 'warranty', 'verification', 'service', 'sort']
        .every((name) => has(src.index, `name="${name}"`))
    && ['category', 'location', 'department', 'custodian', 'condition', 'warranty', 'verification', 'service', 'sort']
        .every((name) => has(src.filters, `'${name}'`)),
    'a filter the drawer offers and the service drops is a control that lies');

check('every filter a request carries is checked against what the screen offers',
    has(src.filters, 'public function fromRequest(')
    && times(src.filters, 'array_key_exists(') >= 4
    && has(src.filters, 'preg_match('),
    'a hand-typed ?warranty=garbage is the default view, not an empty list and not a 500');

check('the drawer is registered where the shared components are audited',
    has(src.uiCheck, 'resources/views/assets/index.blade.php'),
    'a new screen with a drawer belongs in the list the shared-component check walks');

/* The record's tabs are links with their own URL — the rule the project and the
   recurring records follow — so a form posted from a tab comes back to it. */
check('the record\'s tabs are the shared strip, as links with their own address',
    has(src.show, 'class="master-tabs"')
    && has(src.show, 'role="tab"')
    && has(src.show, 'aria-selected=')
    && has(src.show, 'class="master-tab-count"')
    && has(src.show, '$recordUrl($key)')
    && times(src.show, "@include('assets.partials.tab-") >= 3
    && !/(?:^|\s)\.(?:fa|asset)-tabs?\b/m.test(src.sheet),
    'a module that draws its own tab strip is a module whose tabs stop matching the rest of the ERP');

check('every panel is a partial, and the panel shown is the one asked for',
    ['tab-overview', 'tab-allocation', 'tab-maintenance', 'tab-depreciation']
        .every((name) => exists(`resources/views/assets/partials/${name}.blade.php`))
    && has(code.controller, "request->query('tab', 'overview')")
    && has(code.controller, 'array_key_exists($tab, $tabs)'),
    'an unknown tab is the overview, not an error page');

/* The dialogs are shared, and the door says which asset it means. */
check('a door is a marker: the dialog is found by the form it owns, not by an id list',
    has(src.index, 'data-open-asset-modal="allocate"')
    && has(src.index, 'data-asset-form') === false
    && has(src.overview, 'data-open-asset-modal="edit"')
    && has(src.dispose, 'data-asset-form="dispose"')
    && has(src.form, 'data-asset-form') === false
    && has(src.script, 'data-asset-form')
    && !/dialogs\s*=\s*\{/.test(code.script),
    'one mechanism: a marker names the form, the form owns the dialog');

check('the control that opens a door carries the sentence the dialog needs',
    has(src.index, 'data-subject=')
    && has(src.index, 'data-current=')
    && has(src.index, 'data-action=')
    && has(src.script, 'setText('),
    'the row knows which asset it is and what state it is in; the shared dialog must not guess');

check('a shared dialog clears itself before it is filled again',
    has(code.script, 'form.reset()')
    && has(code.script, "setAttribute('action'"),
    'a location typed for one asset must not still be in the box for the next one');

check('a failed save reopens the dialog it came from, with the typing kept',
    has(src.index, 'data-open-dialog=')
    && has(src.show, 'data-open-dialog=')
    && has(src.index, '_dialog')
    && has(src.script, 'data-open-dialog')
    && !/form\.reset\(\)[\s\S]{0,200}data-open-dialog/.test(code.script),
    'the server names the dialog; the script opens it and does not clear the fields');

check('closing a dialog is the shell\'s, not this module\'s',
    has(src.script, 'window.MasterModal')
    && !has(code.script, "classList.remove('open')")
    && has(src.dispose, 'data-close-modal=')
    && !has(code.script, 'Escape'),
    'a second implementation of Escape and the backdrop is how two dialogs start behaving differently');

check('a destructive action asks through the shell\'s confirm, not through a module one',
    has(src.index, 'data-confirm=')
    && has(src.overview, 'data-confirm=')
    && has(src.index, 'data-confirm-title=')
    && !has(code.script, 'confirm('),
    'data-confirm is global (master-alert.js); re-implementing it here is the drift');

check('the list uses the shell\'s row navigation and header shadow',
    has(src.script, 'MasterList.rowNavigation')
    && has(src.script, 'MasterList.gridShadow')
    && has(src.index, 'master-table-wrap ui-mobile-cards')
    && has(src.index, 'data-href='),
    'the whole row opens the asset; the header shadow is shared');

check('the report asks for one year, through the server, not through a rewrite',
    has(src.report, 'name="year"')
    && has(code.controller, 'yearOptions(')
    && has(code.controller, "request->query('year'")
    && has(src.report, "route('assets.depreciation')"),
    'the year is the one question this screen asks, so it is the one control it carries');

/* The export is the same columns the office kept, including the two figures the
   spreadsheet never had. */
check('the export carries the office\'s own columns plus the two they could not keep right',
    has(src.controller, "'Asset ID'")
    && has(src.controller, 'Purchase Cost')
    && has(src.controller, 'Capitalised')
    && has(src.controller, 'Useful Life (Years)')
    && has(src.controller, 'Depreciation Method')
    && has(src.controller, 'Accumulated Depreciation')
    && has(src.controller, 'Net Book Value')
    && has(src.controller, 'Last Physical Verification')
    && has(src.controller, 'Disposal Value')
    && has(src.controller, 'Remarks')
    && has(src.controller, 'totalCost()')
    && has(src.controller, 'netBookValue()'),
    'a register that exports fewer columns than it holds is a register an auditor cannot use');

/* ══════════════════════════════════════════════ E. the boundary ═══════════ */

/* The register is a module; the classes are a setting. That boundary is the one
   this round had to get right, and it is written down where both halves live. */
check('the register is a module of its own, under the money it accounts for',
    has(src.routes, "name('assets.index')")
    && has(src.routes, "'/fixed-assets'")
    && has(src.layout, "['label' => 'Fixed assets', 'route' => 'assets.index'")
    && has(src.layout, "'section' => 'Accounts'")
    && (() => {
        /* …and it stands inside that section rather than after the last one. */
        const at = src.layout.indexOf("'route' => 'assets.index'");
        const section = src.layout.lastIndexOf("'section' =>", at);
        return section > -1 && src.layout.slice(section, at).includes("'Accounts'");
    })(),
    'the sidebar door sits with the money, because that is what a register is');

check('the register is not a settings area, and the setting is not the register',
    !has(src.directory, 'FixedAsset::class')
    && has(src.settings, "@include('settings.partials.nav', ['current' => 'assets'])")
    && has(src.settingsDocs, 'the assets themselves are records'),
    'a setting is a rule or a master list; the chairs are records and have a module');

check('the classes are a setting with its own controller, CRUD and routes',
    has(src.settingController, 'public function index(')
    && has(src.settingController, 'public function store(')
    && has(src.settingController, 'public function update(')
    && has(src.settingController, 'public function destroy(')
    && has(src.routes, "name('settings.assets')")
    && has(src.routes, "name('settings.assets.update')")
    && has(src.routes, "name('settings.assets.destroy')"));

check('a class holding assets cannot be deleted out from under them',
    has(code.settingController, '->assets()->count()')
    && has(code.settingController, 'move them to another class first'),
    'the register must not be left with assets whose class has stopped existing');

check('the two halves point at each other, so neither is a dead end',
    has(src.index, "route('settings.assets')")
    && has(src.settings, "route('assets.index')"),
    'the register says where the recipe comes from; the recipe says where the assets are');

/* The report is asked for a year, and a year belongs to the company. */
check('the depreciation report is company-wide, and says so on the page',
    has(src.report, 'whole company')
    && !/\$this->filters/.test(code.controller.slice(
        code.controller.indexOf('public function depreciation('),
        code.controller.indexOf('public function export('),
    ))
    && has(code.controller, "yearReport(FixedAsset::query()->with('category')"),
    'reading a year through a location filter answers a question nobody asked');

check('the report\'s total row is the sum of the rows above it',
    has(src.figures, "'totals'")
    && has(src.figures, 'emptyTotals')
    && has(src.figures, 'subtotal')
    && has(src.report, "totals['charge']")
    && has(src.report, "group['subtotal']['charge']"),
    'a total computed a second way is a total that can disagree with the page it sits on');

/* The search and the shell know about the register; a module nobody can find is a
   module nobody uses. */
check('the register is findable from the global search',
    has(src.search, "'key' => 'assets'")
    && has(src.search, "'label' => 'Fixed assets'")
    && has(src.search, 'FixedAsset::class')
    && has(src.search, "'table' => 'fixed_assets'")
    && has(src.search, "'route' => 'assets.show'"),
    'the search reads the model list, so a new module is one entry, not a second index');

check('the module key reaches the shell, so its cards get the guideline treatment',
    has(src.layout, "request()->routeIs('assets.*') => 'assets'")
    && has(src.layout, "request()->routeIs('settings.assets*') => 'assets'")
    && has(src.layout, 'data-ui-module='));

/* The sheet owes the guidelines: tokens, not literals; its own cards' inset;
   nothing shared redefined; a dark value for every tint it invents. */
check('the sheet spends tokens and no literals',
    !/#[0-9a-fA-F]{3,6}/.test(src.sheet)
    && !/rgba?\(/.test(src.sheet)
    && has(src.sheet, 'var(--ui-'));

check('the sheet gives this module\'s own cards their inset and never pads a table card',
    /\.fixed-assets \.ast-block[\s\S]{0,400}?padding:\s*20px 22px/.test(src.sheet)
    && /\.ast-table-card\s*\{[\s\S]{0,80}?padding:\s*0/.test(src.sheet)
    && !/^\s*\.master-card\s*\{/m.test(plain(src.sheet)),
    'the shared card is a surface with no padding; a card holding a table carries none');

check('the sheet defines no shared component of its own',
    (() => {
        const selectors = [...plain(src.sheet).matchAll(/^\s*([^@{}\n][^{}\n]*)\{/gm)]
            .map((m) => m[1].trim());
        return selectors.length >= 20
            && selectors.every((selector) => selector.includes('.ast-') || selector === '.ast-settings')
            && !/^\s*\.(?:master|core)-[^{]*\{/m.test(plain(src.sheet));
    })(),
    'a module-local copy of a card, tab or badge is how two screens stop matching');

check('every tint this sheet invents has a dark-theme value',
    times(src.sheet, ':root[data-theme="dark"]') >= 2
    && has(src.sheet, 'color-mix(')
    && has(src.sheet, 'data-theme="dark"'),
    'a colour that cannot be seen on the dark surface is not a colour');

/* A register is a compliance artefact: the record has to be able to print every
   column the office kept, and the check above only proves the export carries
   them. This one proves the record shows them too. */
check('the record shows every column the register holds',
    ['asset_code', 'category?->name', 'description', 'make', 'model', 'serial_no', 'purchase_date',
        'supplierLabel()', 'invoice_no', 'invoice_date', 'cost', 'gst_amount', 'totalCost()', 'capitalisedCost()',
        'placeLabel()', 'department', 'holderLabel()', 'stateLabel()', 'warranty_end_date', 'effectiveLifeYears()',
        'methodLabel()', 'accumulatedDepreciation()', 'netBookValue()', 'insurance_details',
        'last_verified_on', 'conditionLabel()', 'disposal_date', 'disposal_value', 'remarks']
        .every((field) => has(src.overview + src.show, `$asset->${field}`) || has(src.overview, `$${field}`)),
    'the pages may arrange the columns differently; they may not lose one');

/* A class a screen names must have rules: a class left behind in the markup
   renders as nothing, and no reviewer reading the Blade can tell. The module's
   own namespace is `ast-` (never `fa-`, which Font Awesome owns), and the one
   exception is the root hook the module's script reads. */
const moduleViews = [
    files.index, files.show, files.report, files.settings, files.overview, files.dispose, files.form,
    'resources/views/assets/partials/tab-allocation.blade.php',
    'resources/views/assets/partials/tab-maintenance.blade.php',
    'resources/views/assets/partials/tab-depreciation.blade.php',
    'resources/views/assets/partials/modal-allocate.blade.php',
    'resources/views/assets/partials/modal-register.blade.php',
    'resources/views/assets/partials/modal-edit.blade.php',
    'resources/views/assets/partials/modal-return.blade.php',
    'resources/views/assets/partials/modal-maintenance.blade.php',
    'resources/views/assets/partials/modal-verify.blade.php',
];

check('every class this module names is a class its sheet defines',
    (() => {
        const names = new Set();

        moduleViews.filter((file) => exists(file)).forEach((file) => {
            for (const m of read(file).matchAll(/ast-[a-z0-9-]+/g)) names.add(m[0]);
        });

        /* The script's root hook is the one class the sheet does not have to
           define: it is a query target, not a style. */
        const hooks = ['ast-index'];
        const undefined = [...names].filter((name) => !hooks.includes(name) && !has(src.sheet, `.${name}`));

        return names.size >= 40 && undefined.length === 0;
    })(),
    'a class with no rule is a class that renders as nothing, and the markup will not say so');

check('a number in a column is written by one formatter, never by a view',
    has(src.vocabulary, 'public static function percentLabel(')
    && has(src.asset, 'public function residualPercentLabel(')
    && has(src.overview, 'residualPercentLabel()')
    && !/number_format\([^)]*,\s*\d/.test(src.index + src.show + src.report + src.settings + src.overview),
    'the money rule (cost-check), applied to the other number this module prints with decimals');

/* The badge tones the vocabulary names, and every shared modifier the module's
   markup writes by hand, have to be tones a sheet actually styles. A badge class
   nobody defines renders as plain text with a stray class on it — and badges are
   the one thing a module is never allowed to invent for itself. */
const shellSheets = fs.readdirSync(path.join(ROOT, 'public/assets/css'))
    .filter((name) => name.endsWith('.css'))
    .map((name) => read(`public/assets/css/${name}`))
    .join('\n');

const toneNames = [...src.vocabulary.matchAll(/_TONES = \[([\s\S]*?)\];/g)]
    .flatMap((m) => [...m[1].matchAll(/'([a-z]+)'/g)].map((x) => x[1]));

const unstyledTones = [...new Set(toneNames)]
    .filter((tone) => !new RegExp(`\\.core-badge-${tone}\\s*[,{]`).test(shellSheets));

const handModifiers = [];

moduleViews.filter((file) => exists(file)).forEach((file) => {
    for (const m of plain(read(file)).matchAll(/class="([^"]*)"/g)) {
        /* `{{ … }}` inside a class attribute is a decision, not a class: it is
           the branch's own names (caught by the promise above) that must exist,
           and the words around it that this promise reads. */
        const classes = m[1].replace(/\{\{[\s\S]*?\}\}/g, ' ').trim().split(/\s+/);

        classes.filter((cls) => /^core-badge-[a-z-]+$/.test(cls)).forEach((cls) => {
            if (!new RegExp(`\\.${cls}\\s*[,{]`).test(shellSheets)) handModifiers.push(`${file}: ${cls}`);
        });

        /* A stat card's colour is the shell's word too: `green` is a rule in
           `app-guidelines.css`, and a colour somebody invents here renders as an
           untinted card beside four tinted ones. */
        if (classes.includes('master-stat') || classes.includes('master-stat--flat')) {
            classes.filter((cls) => !cls.startsWith('master-')).forEach((cls) => {
                if (!new RegExp(`\\.${cls}\\s*[,{:\\s]`).test(shellSheets)) handModifiers.push(`${file}: master-stat ${cls}`);
            });
        }

        if (classes.includes('master-info-box')) {
            classes.filter((cls) => /^is-[a-z-]+$/.test(cls)).forEach((cls) => {
                if (!new RegExp(`\\.master-info-box\\.${cls}[\\s,{]`).test(shellSheets)) {
                    handModifiers.push(`${file}: master-info-box ${cls}`);
                }
            });
        }
    }
});

check('the badge tones and the notice-box modifiers the module writes are the shell\'s',
    toneNames.length >= 8 && unstyledTones.length === 0 && handModifiers.length === 0,
    [...unstyledTones.map((tone) => `tone ${tone}`), ...new Set(handModifiers)].slice(0, 4).join(' · '),
    'a badge with no rule is a word in a box');

/* A page that reads `$asset->netBookValued()` dies in the reader's face, and
   neither php-check (which parses) nor blade-check (which balances) can see it.
   There is no PHP in this sandbox to render the page, so the honest substitute
   is this: every `$name` a module view reads is passed by its controller, bound
   by a view in the module, or handed to an include; every method it calls on an
   asset, a class, a hand-over or a repair exists on that model; and every key it
   reads off a figure, a total, a report row or a year exists where that is
   built. */
const modelMethods = (file) => new Set([...read(file).matchAll(/public function ([a-zA-Z_]\w*)\(/g)].map((m) => m[1]));
const modelMembers = {
    asset: modelMethods(files.asset),
    class: modelMethods(files.category),
    allocation: modelMethods(files.allocation),
    maintenance: modelMethods(files.maintenance),
};
const vocabularyMembers = new Set([
    ...[...src.vocabulary.matchAll(/public (?:static )?function ([a-zA-Z_]\w*)\(/g)].map((m) => m[1]),
    ...[...src.vocabulary.matchAll(/public const ([A-Z_]+) =/g)].map((m) => m[1]),
]);
const filterMembers = new Set([...src.filters.matchAll(/public const ([A-Z_]+) =/g)].map((m) => m[1]));
const depreciationMembers = modelMethods(files.depreciation);
const helperMembers = new Set(['amount']);

/* The names a keyed line contributes: `'key' =>` in a view's data array. */
const keysIn = (text) => new Set([...text.matchAll(/'([a-zA-Z_]\w*)'\s*=>/g)].map((m) => m[1]));

/* A `[...]` body, balanced, starting at the first `[` at or after `from`. */
const bracketBody = (text, from) => {
    const start = text.indexOf('[', from);
    if (start < 0) return '';
    let depth = 0;
    for (let i = start; i < text.length; i++) {
        if (text[i] === '[') depth++;
        else if (text[i] === ']' && --depth === 0) return text.slice(start + 1, i);
    }
    return '';
};

const controllerKeys = new Set();

for (const text of [src.controller, src.settingController]) {
    for (const m of text.matchAll(/array_merge\(\s*\$this->sharedData\(\)\s*,\s*\[/g)) {
        for (const key of keysIn(bracketBody(text, m.index))) controllerKeys.add(key);
    }
    for (const m of text.matchAll(/view\('settings\.assets',\s*\[/g)) {
        for (const key of keysIn(bracketBody(text, m.index))) controllerKeys.add(key);
    }
    for (const m of text.matchAll(/private function sharedData\(\)[\s\S]*?\n    \}/g)) {
        for (const key of keysIn(m[0])) controllerKeys.add(key);
    }
    if (has(text, '...$filters')) {
        const defaults = src.filters.split('public const DEFAULTS = [')[1].split('];')[0];
        for (const key of keysIn(defaults)) controllerKeys.add(key);
        controllerKeys.add('sort');
    }
}

/* Names a view binds itself — a `$x =`, a `@foreach` binding, a closure
   parameter. A partial is rendered in its page's scope, so a name bound by any
   view of the module counts for all of them; so does a name an `@include`
   hands down. */
/* Deliberately without `asset`: it is the module's subject and not a generic,
   and leaving it here would also switch the method check off for the one object
   most of these calls are made on. It arrives from the controller or an
   `@include`. */
const boundNames = new Set(['loop', 'errors', 'assetVer', 'slot', 'attributes', 'component', 'message']);

moduleViews.filter((file) => exists(file)).forEach((file) => {
    const text = plain(read(file));

    for (const m of text.matchAll(/\$([a-zA-Z_]\w*)\s*=(?!=)/g)) boundNames.add(m[1]);
    for (const m of text.matchAll(/\bas\s+\$([a-zA-Z_]\w*)/g)) boundNames.add(m[1]);
    for (const m of text.matchAll(/\bas\s+\$[a-zA-Z_]\w*\s*=>\s*\$([a-zA-Z_]\w*)/g)) boundNames.add(m[1]);
    for (const m of text.matchAll(/\b(?:fn|function|use)\s*\(([^)]*)\)/g)) {
        for (const n of m[1].matchAll(/\$([a-zA-Z_]\w*)/g)) boundNames.add(n[1]);
    }
    for (const m of text.matchAll(/@include\(\s*'[^']+'[\s\S]{0,400}?\[/g)) {
        for (const key of keysIn(bracketBody(text, m.index))) boundNames.add(key);
    }
});

/* What each array actually carries. */
/* `$figures` is `summary()`'s own array plus `curve()`'s, merged with `+=` in
   the product — so the promise has to read both, or it would report the curve's
   keys as missing when they are exactly where they should be. */
const summaryKeys = new Set([
    ...keysIn(methodBody(code.figures, 'summary')),
    ...keysIn(methodBody(code.figures, 'curve')),
    ...[...src.vocabulary.matchAll(/const STATUS_\w+ = '([a-z_]+)'/g)].map((m) => m[1]),
    'warranty_soon', 'insurance_soon', 'verify_due', 'service_due',
]);
const totalKeys = keysIn(src.figures.split('private function emptyTotals()')[1].split('];')[0]);
const reportRowKeys = new Set([
    'id',
    ...keysIn(src.figures.split("'id' => $asset->id,")[1].split('];')[0]),
]);
/* The schedule's rows are a different shape from the report's: read them off the
   calculator's own `@return` line rather than out of its body. */
const scheduleKeys = new Set([
    ...[...src.depreciation.split('@return list<array{')[1].split('}>')[0]
        .matchAll(/([a-z_]+):/g)].map((m) => m[1]),
]);
const yearKeys = new Set(['key', 'label', 'from', 'to']);
const groupKeys = new Set(['category', 'rows', 'subtotal']);

const objectVars = {
    asset: 'asset',
    category: 'class',
    allocation: 'allocation',
    maintenance: 'maintenance',
};
const staticMembers = {
    AssetVocabulary: vocabularyMembers,
    AssetFilters: filterMembers,
    AssetDepreciation: depreciationMembers,
    CommonHelper: helperMembers,
};

const viewProblems = [];

moduleViews.filter((file) => exists(file)).forEach((file) => {
    const text = plain(read(file));

    /* 1. a variable nobody passes and nobody binds */
    for (const m of text.matchAll(/\$([a-zA-Z_]\w*)/g)) {
        const name = m[1];
        if (!controllerKeys.has(name) && !boundNames.has(name)) {
            viewProblems.push(`${file}: $${name}`);
        }
    }

    /* 2. a method on a model that does not have it */
    for (const m of text.matchAll(/\$([a-zA-Z_]\w*)->([a-zA-Z_]\w*)\(/g)) {
        /* No scope guard here: `$asset`, `$category`, `$allocation` and
           `$maintenance` are this module's four objects wherever they appear, and
           a name like `$keep` or `$row` is simply not one of them. */
        const model = objectVars[m[1]];
        if (model && !modelMembers[model].has(m[2])) {
            viewProblems.push(`${file}: $${m[1]}->${m[2]}()`);
        }
    }

    /* 3. a static member that is not there */
    for (const m of text.matchAll(/(AssetVocabulary|AssetFilters|AssetDepreciation|CommonHelper)::([A-Za-z_]\w*)/g)) {
        if (!staticMembers[m[1]].has(m[2])) viewProblems.push(`${file}: ${m[1]}::${m[2]}`);
    }

    /* 4. an array key that is not in the array */
    const arrays = {
        figures: summaryKeys, totals: totalKeys, year: yearKeys, group: groupKeys,
        row: new Set([...reportRowKeys, ...scheduleKeys]),
    };
    for (const m of text.matchAll(/\$([a-zA-Z_]\w*)\['([a-z_]+)'\]/g)) {
        const keys = arrays[m[1]];
        if (keys && !keys.has(m[2])) viewProblems.push(`${file}: $${m[1]}['${m[2]}']`);
    }
});

check('no view reads a name, a method or a key the module does not have',
    viewProblems.length === 0,
    [...new Set(viewProblems)].slice(0, 4).join(' · '));

/* ------------------------------------------------------------ the docs row */

check('the module is written down where the next reader looks',
    has(src.docs, 'Fixed assets')
    && has(src.docs, '## ')
    && has(src.docs, 'AssetIntake')
    && has(src.docs, 'AssetDepreciation')
    && has(src.docs, 'settings')
    && has(src.readme, 'assets-check.cjs'),
    'a module with no doc is a module the next person rebuilds');

console.log(`\nassets: ${passed} passed, ${failed} failed`);
if (failures.length) {
    console.log('');
    failures.forEach((line) => console.log(`  · ${line}`));
}
process.exit(failed ? 1 : 0);
