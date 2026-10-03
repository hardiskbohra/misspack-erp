/* ==========================================================================
   REPORT CHECK — the analysis builder: a total you can open
   --------------------------------------------------------------------------
   Run:  node tools/checks/report-check.cjs
   No dependencies. Exits non-zero on failure.

   Wave 2A answers one question — "how much, of which kind, with whom, over
   which months" — for every wording of it. That makes the failure modes quiet
   and expensive, so they are checked rather than trusted:

     - **The figure and the drill are one definition of "these rows".** A cell
       is summed in the report window and opened in the ledger window; if the
       two ever disagree, the accountant re-adds the report by hand, which is
       the manual job this feature exists to delete. Both go through one filter
       vocabulary (App\Services\CashflowFilters) and the same whereDate bounds.
     - **A bucket boundary is a date, not a database dialect.** The dashboard's
       GROUP BY DATE_FORMAT(...) is MySQL-only; the report computes its buckets
       in PHP because the drill link needs each bucket's exact first and last
       day anyway.
     - **A comparison is the axis shifted, never the row above.** Comparing
       April with March inside an April–June report counts March twice: once in
       its own row and once as April's comparison.
     - **"Not set" is a row you can open.** The entries nobody was named against
       are the ones people ask about, and a row that cannot be drilled is a row
       nobody trusts.
     - **A bucket is named the way the module names periods.** The ledger's "This
       year" chip is January–December, so the columns the report cuts cannot come
       back labelled in financial years, and the reverse is a change to the
       chips, the presets and the buckets together.
   ========================================================================== */
'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..', '..');
const read = file => fs.readFileSync(path.join(ROOT, file), 'utf8');

const out = [];
const check = (name, ok, detail) => {
    out.push([name, ok, detail]);
};

/** Strip CSS docblocks so a comment cannot satisfy a rule. */
const strip = text => text.replace(/\/\*[\s\S]*?\*\//g, '');

/** Collapse a docblock into one line, so prose can be asserted flat. */
const prose = text => text
    .split('\n')
    .map(line => line.replace(/^\s*(\/\*\*?|\*\/|\*)\s?/, '').trim())
    .join(' ')
    .replace(/\s+/g, ' ');

/* ------------------------------------------------------------------ files */

const service = read('app/Services/CashflowAnalysis.php');
const filters = read('app/Services/CashflowFilters.php');
const controller = read('app/Http/Controllers/CashflowController.php');
const model = read('app/Models/CashflowEntry.php');
const reportView = read('resources/views/cashflows/reports.blade.php');
const matrix = read('resources/views/cashflows/partials/report-matrix.blade.php');
const pdfView = read('resources/views/cashflows/pdf.blade.php');
const cashCss = read('public/assets/css/cashflows.css');
const entryForm = read('resources/views/cashflows/form.blade.php');
const ledgerView = read('resources/views/cashflows/index.blade.php');
const routes = read('routes/web.php');
const migration = read('database/migrations/2026_10_03_020000_promote_employee_on_cashflow_entries_table.php');

/* ------------------------------------------------------ the dimension list */

const dimensions = [...service.matchAll(/'column' => '([a-z_]+)'/g)].map(m => m[1]);
const dimensionFilters = [...service.matchAll(/'filter' => '([a-z_]+)'/g)].map(m => m[1]);
const vocabulary = [...filters.matchAll(/=> '([a-z_]+)',$/gm)].map(m => m[1]);

check('the report can group by the ledger\'s own dimensions',
    dimensions.length >= 10
    && ['client_id', 'vendor_id', 'employee_id', 'account_id', 'category_id', 'project_id', 'currency']
        .every(column => dimensions.includes(column)),
    dimensions.join(', '));

check('every dimension the report groups by can also be filtered by',
    dimensionFilters.length >= 10 && dimensionFilters.every(name => vocabulary.includes(name)),
    dimensionFilters.filter(name => !vocabulary.includes(name)).join(', '));

/* a dimension's column has to exist, or the report silently groups by nothing */
const schema = [
    '2026_06_10_010200_create_cashflow_entries_table.php',
    '2026_07_18_010700_add_project_id_to_cashflow_entries_table.php',
    '2026_08_29_000000_add_sales_invoice_id_to_cashflow_entries_table.php',
    '2026_10_03_020000_promote_employee_on_cashflow_entries_table.php',
].map(file => read('database/migrations/' + file)).join('\n');
const missingColumns = dimensions.filter(column => !new RegExp('\\b' + column + '\\b').test(schema));
check('every grouped column is a column on the table', missingColumns.length === 0, missingColumns.join(', '));

check('the period axis is month, quarter or year',
    /'month' => 'Month'/.test(service) && /'quarter' => 'Quarter'/.test(service) && /'year' => 'Year'/.test(service));

check('the figure can be money in, money out or the net',
    /'net' => /.test(service) && /'credit' => /.test(service) && /'debit' => /.test(service)
    && /return match \(\$this->measure\)/.test(service));

/* ------------------------------------------------------------- portability */

check('the buckets are computed in PHP, not in a database dialect',
    !/DATE_FORMAT|strftime|to_char|QUARTER\(|YEAR\(/.test(strip(service)),
    'the dashboard\'s bucketSql() is MySQL-only and must not be copied');

check('a bucket runs from the start of its unit to the end of it',
    [/return match \(\$unit\) \{[\s\S]{0,200}startOfYear\(\)/.test(service),
        /startOfQuarter\(\)/.test(service),
        /startOfMonth\(\)/.test(service),
        /endOfYear\(\)/.test(service),
        /endOfQuarter\(\)/.test(service),
        /endOfMonth\(\)/.test(service)].every(Boolean));

check('a range that starts mid-bucket is clamped to the range, not trimmed',
    /\$coveredStart = \$cursor->greaterThan\(\$start\) \? \$cursor : \$start;/.test(service)
    && /\$coveredEnd = \$bucketEnd->greaterThan\(\$end\) \? \$end : \$bucketEnd;/.test(service));

/* ------------------------------------------------- the figure and the drill */

check('the report reads the ledger in one query',
    /private function entries\(array \$filters, string \$from, string \$to\): Collection/.test(service)
    && (service.match(/->get\(\);/) || []).length === 1
    && (service.match(/\$this->entries\(/g) || []).length === 1);

check('the report applies the ledger\'s own filters, not a copy of them',
    /app\(CashflowFilters::class\)->apply\(\$query, \$filters\)->get\(\)/.test(service));

check('the report window and the ledger window use the same bounds',
    /whereDate\('entry_date', '>=', \$from\)/.test(service)
    && /whereDate\('entry_date', '<=', \$to\)/.test(service)
    && /whereDate\('entry_date', '>=', \$value\('dateFrom'\)\)/.test(filters)
    && /whereDate\('entry_date', '<=', \$value\('dateTo'\)\)/.test(filters));

check('a cell opens the rows it counted',
    /\$query\['date_from'\] = \$period\['start'\];/.test(service)
    && /\$query\['date_to'\] = \$period\['end'\];/.test(service)
    && /\$query\[\$definition\['filter'\]\] = /.test(service));

check('a row total opens every period that row covers',
    /\$analysis->ledgerQuery\(\$filters, \$dimension, null, \$row\['value'\]\)/.test(controller)
    && /\$analysis->ledgerQuery\(\$filters, \$dimension, \$cell\['period'\], \$row\['value'\]\)/.test(controller));

check('every cell on the page carries the link that opens it',
    (matrix.match(/href="\{\{ \$cell\['url'\] \}\}"/g) || []).length >= 2
    && /href="\{\{ \$row\['url'\] \}\}"/.test(matrix)
    && /href="\{\{ \$report\['url'\] \}\}"/.test(matrix)
    && /\$report\['totals'\]\['cells'\]\[\$loop->index\]\['url'\]/.test(matrix));

/* ---- the dimension registry is the contract the picker reads ---- */

const DIMENSION_KEYS = ['none', 'client', 'vendor', 'employee', 'account', 'category',
    'expense_head', 'payment_mode', 'transaction_type', 'accounting_status', 'project', 'currency'];

const registry = service.slice(service.indexOf('public const DIMENSIONS'), service.indexOf('public function build('));

check('the dimensions the roadmap names are the ones the registry carries',
    DIMENSION_KEYS.every(key => new RegExp("'" + key + "' => \\[").test(registry))
    && /'employee' => \[\n\s*'label' => 'Employee',\n\s*'hint' => '[^']+',\n\s*'column' => 'employee_id',\n\s*'filter' => 'employee_id',/.test(registry));

/* Read the registry as pairs, so "column and filter agree" is actually compared
   rather than counted: a dimension that groups by one column and filters another
   silently drops the cell's own rows out of the drill. */
const registryPairs = {};
for (const [, key, body] of registry.matchAll(/\n        '([a-z_]+)' => \[\n([\s\S]*?)\n        \],/g)) {
    const column = body.match(/'column' => (?:(?:'([^']*)')|(null))/);
    const filter = body.match(/'filter' => (?:(?:'([^']*)')|(null))/);
    registryPairs[key] = [column ? (column[1] ?? null) : undefined, filter ? (filter[1] ?? null) : undefined];
}

check('a dimension is grouped by the same column it can be filtered by',
    DIMENSION_KEYS.every(key => key in registryPairs)
    && DIMENSION_KEYS.filter(key => key !== 'none')
        .every(key => registryPairs[key][0] && registryPairs[key][0] === registryPairs[key][1])
    && registryPairs.none[0] === null);

check('the employee the entry is against is recorded when the entry is saved',
    /* both validators: the full form and the quick modal, which offers the
       same three links */
    (controller.match(/'employee_id' => \['nullable', 'integer', 'exists:users,id'\]/g) || []).length === 2
    && /name="employee_id"/.test(entryForm)
    && /'client_id', 'vendor_id', 'employee_id'/.test(model)
    && /public function employee\(\)/.test(model));

check('the ledger prints the party it was paid to, linked or typed by hand',
    /\$this->client\?->company_name\s*\n\s*\?\? \$this->vendor\?->vendor_name\s*\n\s*\?\? \$this->employee\?->name/.test(model)
    && /related_party_name/.test(model));

check('a cell whose dimension is empty opens the rows where it is empty',
    /self::NOT_SET\b/.test(filters)
    && /whereNull\(\$column\)->orWhere\(\$column, ''\)/.test(filters)
    && /CashflowFilters::NOT_SET_KEY\s*\n\s*\?\s*CashflowFilters::NOT_SET/.test(service)
    && /'filter' => 'employee_id'/.test(registry));

check('an empty window is widened to a range, never echoed back as empty',
    /private function reportWindow\(Request \$request, string \$unit\): array/.test(controller)
    && /subYears\(4\)->startOfYear\(\)->toDateString\(\)/.test(controller)
    && /startOfYear\(\)->toDateString\(\)/.test(controller));

/* index() is sliced, not searched: reportData() passes the same two keys, so a
   whole-file match would keep passing after the ledger stopped showing them. */
const indexBody = controller.slice(controller.indexOf('public function index('), controller.indexOf('public function create('));

check('the ledger shows what filtered it, including the dimension a cell handed over',
    /'appliedFilters' => app\(CashflowFilters::class\)->applied\(\$filters\),/.test(indexBody)
    && /'appliedFilterLabels' => app\(CashflowFilters::class\)->labels\(\$filters\),/.test(indexBody)
    && /\$appliedChips = collect\(\$appliedFilters\)/.test(ledgerView)
    && /\$appliedFilterLabels\[\$chip\['key'\]\] \?\? ''/.test(ledgerView)
    && /master-list-applied-chip/.test(ledgerView));

/* the "Not set" row is the one people ask about */
check('a report row with nothing in the dimension can still be opened',
    /public const NOT_SET = 'none';/.test(filters)
    && /public const NOT_SET_KEY = '__none';/.test(filters)
    && /\$query->where\(fn \(Builder \$q\) => \$q->whereNull\(\$column\)->orWhere\(\$column, ''\)\)/.test(filters)
    && /CashflowFilters::NOT_SET_KEY/.test(service));

/* ------------------------------------------------------------- comparisons */

/* The module's period vocabulary is calendar (the ledger's "This year" chip is
   January–December), so the buckets the report cuts must be calendar too: a
   window opened from that chip cannot come back labelled in financial years. */
/* A filter value is never handed to the date parser raw. "all" is a range name,
   not a day, and it arrives by link and by saved view: Carbon::parse('all')
   throws and the whole ledger becomes a 500 — over a filter. Everything that
   reads a date out of the query string reads it through DateRanges::normalise,
   which answers "a day, or nothing". */
/* ---- the filter vocabulary's own contract ---- */

/* Read the two tables as data. A filter's "off" is a sentinel ("all") or
   nothing at all (null), and the difference matters: `?? 'all'` reads a
   *declared* null as a missing key, so the three filters whose "off" is nothing
   were handed the sentinel — an empty search box searched the ledger for the
   word "all", and an empty date range became a value Carbon refused to parse. */
/* Both tables live in the filter vocabulary (CashflowFilters). */
const constantBlock = name => {
    const start = filters.indexOf("const " + name + " = [");

    return start === -1 ? '' : filters.slice(start, filters.indexOf('];', start));
};

const defaultsBlock = constantBlock('DEFAULTS');
const viewKeysBlock = constantBlock('VIEW_KEYS');

const table = block => {
    const entries = {};
    for (const [, key, value] of block.matchAll(/'([A-Za-z_]+)' => (null|'[^']*')/g)) {
        entries[key] = value === 'null' ? null : value.replace(/'/g, '');
    }
    return entries;
};

const defaults = table(defaultsBlock);
const viewKeys = table(viewKeysBlock);
const emptyDefaults = Object.entries(defaults).filter(([, value]) => value === null).map(([key]) => key);

check('every filter the views use is declared, and its "off" is a sentinel or nothing',
    Object.keys(viewKeys).length === Object.keys(defaults).length
    && Object.keys(viewKeys).length === 17
    && Object.values(viewKeys).every(key => key in defaults)
    && Object.values(defaults).every(value => value === null || value === 'all'));

check('only the search box and the two ends of a range are "nothing" when empty',
    emptyDefaults.sort().join(',') === 'date_from,date_to,search');

/* The producer and the consumer have to answer "is this filter set?" the same
   way. `fromRequest()` learned to emit null for the three filters whose "off" is
   nothing, but `apply()` still read `$filters[$key] ?? $default` — which turns a
   declared null straight back into the sentinel, so the ledger searched for the
   word "all" and bounded itself by the date 'all' and listed nothing. Reading a
   filter is a *presence* question: `array_key_exists`, never `??`. */
const methodBody = name => {
    const start = filters.indexOf('function ' + name + '(');

    return start === -1 ? '' : filters.slice(start, filters.indexOf('\n    }', start));
};

check('four readers of one vocabulary agree on what "unset" means',
    ['fromRequest', 'toQuery', 'applied', 'apply']
        .every(name => /self::default\(|array_key_exists\(/.test(methodBody(name))));

check('a filter that is present is never re-defaulted on the way into the query',
    /array_key_exists\(\$key, \$filters\)/.test(methodBody('apply'))
    && ! /\$filters\[\$key\] \?\?/.test(strip(methodBody('apply')))
    && /self::VIEW_KEYS\[\$key\] \?\? \$key/.test(filters));

check('a declared null default is a default, not a missing key',
    /public static function default\(string \$key\)/.test(filters)
    && /array_key_exists\(\$queryKey, self::DEFAULTS\) \? self::DEFAULTS\[\$queryKey\] : 'all'/.test(filters)
    && /self::VIEW_KEYS\[\$key\] \?\? \$key/.test(filters)
    && /\$request->query\(\$queryKey, self::default\(\$queryKey\)\)/.test(filters)
    && ! /\?\? 'all'/.test(strip(filters))
    && /'search' => trim\(\(string\) \$value\) \?: null,/.test(filters));

check('a date out of the query string is read as a day or as nothing',
    /DateRanges::normalise\(\$request->query\('date_from'\)\)/.test(controller)
    && /DateRanges::normalise\(\$request->query\('date_to'\)\)/.test(controller)
    && /DateRanges::normalise\(\$value\)/.test(filters)
    && /Carbon::createFromFormat\('!'.\$format, \$value\)/.test(read('app/Helpers/DateRanges.php'))
    && /\$date->format\(\$format\) === \$value/.test(read('app/Helpers/DateRanges.php'))
    && ! /Carbon::parse\(\s*\$request/.test(controller)
    && ! /Carbon::parse\(\s*request\(/.test(controller)
    && ! /Carbon::parse\(\s*\$request/.test(filters)
    && ! /Carbon::parse\(\$dateFrom\)|Carbon::parse\(\$dateTo\)/.test(ledgerView));

/* A source-reading check cannot answer "what does a bare request do to the
   query?" — two bugs proved that. The answer lives in a test that runs the
   service, and this guard is the tripwire that keeps it there. */
check('the filter vocabulary is run by a test, not only read by this file',
    fs.existsSync(path.join(ROOT, 'tests/Unit/CashflowFiltersTest.php'))
    && /test_a_request_with_no_filters_narrows_nothing/.test(read('tests/Unit/CashflowFiltersTest.php'))
    && /->apply\(CashflowEntry::query\(\), \$this->filters\(\$query\)\)/.test(read('tests/Unit/CashflowFiltersTest.php'))
    && /assertStringNotContainsString\('where', \$query\['sql'\], 'a bare ledger must not filter'\)/.test(read('tests/Unit/CashflowFiltersTest.php')));

check('the buckets are named the way the rest of the module names periods',
    /'year' => \$date->format\('Y'\),\n\s*'quarter' => 'Q'\.\$date->quarter\.' '\.\$date->format\('Y'\),\n\s*default => \$date->format\('M Y'\),/.test(service)
    && /'quarter' => 'Q'\.\$date->quarter,\n\s*default => \$date->format\('M'\),/.test(service)
    && ! /financialYearLabel/.test(service)
    && /'this_year' => \[\n\s*'from' => \$thisYear->toDateString\(\),/.test(read('app/Helpers/DateRanges.php')));

check('a comparison shifts the whole axis, it does not read the row above',
    /private function compare\(array \$periods, string \$unit, string \$comparison\): array/.test(service)
    && /subMonthsNoOverflow\(\$shift\)/.test(service)
    && /subQuartersNoOverflow\(\$shift\)/.test(service)
    && /subYearsNoOverflow\(\$shift\)/.test(service)
    && /the whole axis \*\*shifted\*\*, not the bucket in front of it/.test(prose(service)));

check('the shift is the window length for "previous" and a year for "last year"',
    /\$shift = \$comparison === 'previous'[\s\S]{0,80}count\(\$periods\)/.test(service)
    && /'month' => 12, 'quarter' => 4, 'year' => 1/.test(service));

/* the footer must not fold the comparison into the window's own totals */
const totalsBody = service.slice(service.indexOf('private function totals('), service.indexOf('private function compare('));
check('the footer adds up the window, and keeps the comparison beside it',
    /\$rows = \$bucketed\['byIndex'\]\[\$index\] \?\? \[\];/.test(totalsBody)
    && /'credit' => round\(array_sum\(array_map\(fn \(\$entry\) => \(float\) \$entry->credit_amount, \$rows\)\), 2\)/.test(totalsBody)
    && /\$totals\['compare_credit'\] = round\(\$totals\['compare_credit'\] \+ \(float\) \$entry->credit_amount/.test(totalsBody)
    && !/\$totals\['credit'\] \+ \$cells\[\$index\]\['compare_credit'\]/.test(totalsBody));

const rowsBody = service.slice(service.indexOf('private function rows('), service.indexOf('private function cells('));
check('a row counts the entries in the range, never the comparison\'s',
    /\$groups\[\$key\]\['count'\]\+\+;/.test(rowsBody)
    && (rowsBody.match(/\['count'\]\+\+/g) || []).length === 1);

check('a change is only claimed where there is something to compare against',
    /if \(abs\(\$compare\) < 0\.005\) \{\n            return null;/.test(service));

/* ----------------------------------------------------------- the page itself */

check('the picker is built from the registry, not from a list in the view',
    /@foreach \(\$dimensions as \$key => \$definition\)/.test(reportView)
    && /@foreach \(\$units as \$key => \$label\)/.test(reportView)
    && /@foreach \(\$measures as \$key => \$label\)/.test(reportView)
    && !/name="report_type"/.test(reportView));

check('a link from the page this replaced still opens the same report',
    /'client' => 'client'/.test(controller) && /'vendor' => 'vendor'/.test(controller)
    && /'cash_expense' => 'expense_head'/.test(controller)
    && /private function reportWindow\(Request \$request, string \$unit\)/.test(controller));

check('the report opens on a year, because a month answers almost nothing',
    /\$today->copy\(\)->startOfYear\(\)->toDateString\(\)/.test(controller));

check('an empty window is widened rather than reported as empty',
    /private function reportWindow[\s\S]{0,900}\$from \|\| \$to/.test(controller));

check('the report can be exported as data',
    /Route::get\('\/cashflows\/reports\/export'/.test(routes)
    && /public function exportReport\(Request \$request\)/.test(controller)
    && /public function exportReport/.test(controller)
    && /fwrite\(\$out, "\\xEF\\xBB\\xBF"\)/.test(controller)
    && /\$header\[\] = \$period\['label'\];/.test(controller));

check('the export is the report, period for period',
    /\$report = \$data\['report'\];/.test(controller)
    && /foreach \(\$report\['rows'\] as \$reportRow\)/.test(controller)
    && /foreach \(\$report\['periods'\] as \$index => \$period\)/.test(controller));

check('the report prints the same matrix it shows',
    /\$money\(\$cell\['measure_value'\]\)/.test(pdfView)
    && /foreach \(\$report\['periods'\] as \$period\)/.test(pdfView)
    && /\$dimensions\[\$dimension\]\['label'\]/.test(pdfView));

check('a report view is saved against the screen that saved it',
    /public const REPORT_VIEW_MODULE = 'cashflow-reports';/.test(controller)
    && /Rule::in\(\['cashflows', self::REPORT_VIEW_MODULE\]\)/.test(controller)
    && /forUser\(Auth::id\(\), self::REPORT_VIEW_MODULE\)/.test(controller)
    && /name="module" value="cashflow-reports"/.test(reportView));

check('the ledger opens a report\'s rows in the report\'s own order',
    /\$oldestFirst = \$request->query\('sort'\) === 'oldest';/.test(controller)
    && /\$query\['sort'\] = 'oldest';/.test(service));

/* --------------------------------------------------------------- honesty */

check('a range too long to draw says so instead of stopping quietly',
    /public const MAX_PERIODS = 36;/.test(service)
    && /count\(\$periods\) < self::MAX_PERIODS/.test(service)
    && /\$report\['truncated'\] = \$last !== false && \$last\['end'\] < \$to;/.test(service)
    && /Long range\./.test(reportView));

/* The mixed-currency decision is made once, on the controller, and the two
   views read it: a headline card signing itself "USD" over a range that holds
   dollars and rupees states the wrong amount, and so does a bar chart. */
check('a report that mixes currencies says so instead of signing the total',
    /\$report\['currencies'\] = \$this->currencies\(\$entries\);/.test(service)
    && /\$report\['money_currency'\] = count\(\$report\['currencies'\]\) === 1 \? \$report\['currencies'\]\[0\] : '';/.test(controller)
    && /\$multiCurrency = count\(\$report\['currencies'\]\) > 1;/.test(reportView)
    && /Mixed currencies\./.test(reportView)
    && (reportView.match(/\$moneyCurrency = \(string\) \(\$report\['money_currency'\] \?\? ''\);/g) || []).length === 1
    && (matrix.match(/\$moneyCurrency = \(string\) \(\$report\['money_currency'\] \?\? ''\);/g) || []).length === 1
    && !/currencies'\]\[0\]/.test(reportView)
    && !/currencies'\]\[0\]/.test(matrix));

/* The module's brand blue is a light-panel blue (about 3.4:1 on the dark
   card), so the two accents the report draws with it need their dark halves. */
check('the report accents are legible on the dark card too',
    /:root\[data-theme="dark"\] \.cashflow-reports \.cf-report-note \{\n\s*border-left-color: #93b4ff;/.test(strip(cashCss))
    && /:root\[data-theme="dark"\] \.cashflow-reports \.cf-report-more-count \{\n\s*background: rgba\(147, 180, 255, \.16\);\n\s*color: #93b4ff;/.test(strip(cashCss)));

check('every figure on the page goes through the money formatter',
    /\$moneyCurrency === ''\n\s*\? \\App\\Helpers\\CommonHelper::indianCurrency\(\$value, ''\)\n\s*: \\App\\Helpers\\CommonHelper::amount\(\$value, \$moneyCurrency\)/.test(matrix)
    && /\$money\(\$report\['totals'\]\['credit'\]\)/.test(reportView)
    && /\$money\(\$report\['totals'\]\['net'\]\)/.test(reportView)
    && /\$money\(\$cell\['credit'\]\)/.test(reportView)
    && !/\{\{ \$cell\['measure_value'\] \}\}/.test(matrix));

/* ------------------------------------------------------- the employee link */

check('an employee is a link on the entry, not only a name in a text box',
    /foreignId\('employee_id'\)->nullable\(\)/.test(migration)
    && /->constrained\('users'\)/.test(migration)
    && /public function employee\(\)/.test(model)
    && /'employee_id',/.test(model));

check('an entry with no party still has a name to print',
    /public function partyLabel\(\): string/.test(model)
    && /public function partyLabel\(\)/.test(read('app/Models/CashflowAttachment.php')));

check('the employee is a filter and a column, from the one vocabulary',
    /'employeeId' => 'employee_id'/.test(filters) && /'employeeId' => 'Employee'/.test(filters)
    && /'employee_id' => \['nullable', 'integer', 'exists:users,id'\]/.test(controller)
    && /name="employee_id"/.test(read('resources/views/cashflows/form.blade.php'))
    && /name="employee_id"/.test(read('resources/views/cashflows/index.blade.php')));

/* --------------------------------------------------------- the list chrome */

check('the applied strip is drawn from the filters that ran, not from a list in the view',
    /\$appliedChips = collect\(\$appliedFilters\)/.test(read('resources/views/cashflows/index.blade.php'))
    && /app\(CashflowFilters::class\)->applied\(\$filters\)/.test(controller)
    && /\$filtersActive = \$appliedChips->isNotEmpty\(\) \|\| filled\(\$dateFrom\) \|\| filled\(\$dateTo\);/.test(read('resources/views/cashflows/index.blade.php')));

check('the module sheet styles its own cells and not the surface',
    !/\.master-list/.test(strip(cashCss)));

check('the matrix is not stacked into cards on a phone',
    /@media \(max-width: 768px\) \{[\s\S]{0,600}\.cf\.cashflow-reports \.cf-report-table/.test(strip(cashCss))
    && /\.cf\.cashflow-reports \.cf-report-table thead \{\s*\n\s*display: table-header-group;/.test(strip(cashCss)));

check('the party column stays with its row while the periods scroll',
    /\.cf-report-name-col \{\n    position: sticky;\n    left: 0;/.test(strip(cashCss))
    && /background: var\(--mc-card\);/.test(strip(cashCss)));

check('the matrix has a dark theme, like every other cell in the module',
    /:root\[data-theme="dark"\] \.cashflow-reports \.cf-report-value\.is-in/.test(strip(cashCss))
    && /:root\[data-theme="dark"\] \.cashflow-reports \.cf-report-value\.is-out/.test(strip(cashCss)));

/* -------------------------------------------------------------- the shell */

/* Count braces and parentheses over the *code*: an apostrophe in a comment
   ("the ledger's rows") otherwise opens a string that never closes, which is
   how a naive strip reports a balanced file as broken. */
const brackets = text => {
    let code = '';
    let state = null;

    for (let i = 0; i < text.length; i++) {
        const two = text.slice(i, i + 2);
        const char = text[i];

        if (state === 'block') {
            if (two === '*/') { state = null; i++; }
            continue;
        }
        if (state === 'line') {
            if (char === '\n') { state = null; code += char; }
            continue;
        }
        if (state === 'single' || state === 'double') {
            if (char === '\\') { i++; continue; }
            if ((state === 'single' && char === "'") || (state === 'double' && char === '"')) state = null;
            continue;
        }

        if (two === '/*') { state = 'block'; i++; continue; }
        if (two === '//') { state = 'line'; i++; continue; }
        if (char === "'") { state = 'single'; continue; }
        if (char === '"') { state = 'double'; continue; }

        code += char;
    }

    return ['{', '('].every(open => {
        const close = open === '{' ? '}' : ')';
        return code.split(open).length === code.split(close).length;
    });
};

const phpFiles = ['app/Services/CashflowAnalysis.php', 'app/Services/CashflowFilters.php',
    'app/Http/Controllers/CashflowController.php',
    'database/migrations/2026_10_03_020000_promote_employee_on_cashflow_entries_table.php'];

check('the new PHP is balanced',
    phpFiles.every(file => brackets(read(file))),
    phpFiles.filter(file => !brackets(read(file))).join(', '));

check('the new PHP declares its namespace and imports what it uses',
    /^<\?php\n\s*namespace App\\Services;/m.test(service)
    && /^<\?php\n\s*namespace App\\Services;/m.test(filters)
    && /use Carbon\\Carbon;/.test(service)
    && /use Illuminate\\Support\\Facades\\DB;/.test(service));

/* ------------------------------------------------------------------ report */

let failed = 0;
for (const [name, ok, detail] of out) {
    if (!ok) failed++;
    console.log(`${ok ? '  ok  ' : ' FAIL '} ${name}${ok || !detail ? '' : '  -> ' + detail}`);
}
console.log(`\nreport: ${out.length - failed} passed, ${failed} failed`);
process.exit(failed === 0 ? 0 : 1);
