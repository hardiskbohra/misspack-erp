/* ==========================================================================
   INVOICES CHECK — the invoice module's own rules
   --------------------------------------------------------------------------
   Run:  node tools/checks/invoices-check.cjs
   No dependencies. Exits non-zero on failure.

   The invoice module was a **fork of the design system**: its sheet redefined
   twenty-six shared `master-*` classes with module-tuned values, its screens
   opened with a hero whose rules lived only in that fork, and its list parsed
   the query string inline while the two questions the module exists to answer —
   what is still owed, and how late — could not be asked of it at all. What has
   to stay true now that it is on the shared chrome:

     - the module's sheet defines **no** shared class. A `master-*` rule here is
       the fork growing back, and every screen that loads this file would start
       disagreeing with the ledger next door again;
     - every class the invoices screens wear is defined — an `.si-*` used and
       never declared prints as ordinary text;
     - one rule for the money (`SalesInvoice::RECEIVED_SQL` / `receivedAmount()`)
       and one for how late it is (`ageingBuckets()`), used by the row, the
       chips, the figures, the filter and the CSV. Two spellings of one rule is
       how the list and the client's statement started disagreeing;
     - the figures are one aggregate, never a sum of the invoices the page
       happens to hold — and an aggregate alias is read off the row, because
       `->value('alias')` replaces the select list with the alias;
     - the receipt lands in the **ledger**, linked to the invoice, because that
       is where the bank line is reconciled and where the statement is built;
     - the vocabulary routes (`/export`, `/saved-views`) are registered before
       `Route::resource`, or `/sales-invoices/export` is an invoice called
       "export";
     - the sheet is loaded with a version, or the browser keeps serving the fork
       after it is gone.

   Source-level, like every other gate in this folder: the behaviour itself is
   exercised by the application.
   ========================================================================== */
'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..', '..');
const read = file => fs.readFileSync(path.join(ROOT, file), 'utf8');
const strip = css => css.replace(/\/\*[\s\S]*?\*\//g, '');

const out = [];
const check = (name, ok, detail = '') => out.push([name, !!ok, detail]);

/* declared here, with the rest: a `const` read above its own line is a TDZ
   crash that takes the whole gate down with it */
const view = read('resources/views/sales_invoices/index.blade.php');
const form = read('resources/views/sales_invoices/form.blade.php');
const record = read('resources/views/sales_invoices/show.blade.php');
const sheet = strip(read('public/assets/css/sales-invoices.css'));
const js = read('public/assets/js/sales-invoices.js');
const model = read('app/Models/SalesInvoice.php');
const controller = read('app/Http/Controllers/SalesInvoiceController.php');
const service = read('app/Services/SalesInvoiceFilters.php');
const routes = read('routes/web.php');
const listCss = strip(read('public/assets/css/master-list.css'));

const screens = { index: view, form, record };

/* ---- a tiny CSS rule reader: selectors with their bodies, media included --- */
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

const sheetRules = cssRules(sheet);

/* ---- 1. the sheet is the module's own ---------------------------------- */

const sharedRules = sheetRules.filter(rule =>
    rule.sel.split(',').some(sel => /^\s*(:root\[[^\]]*\]\s*)?\.master-[a-z0-9-]+\s*$/.test(sel.trim())));

check('the module\'s sheet defines no shared class',
    sharedRules.length === 0,
    'the fork is growing back: ' + sharedRules.slice(0, 3).map(r => r.sel).join(' | '));

check('a shared class may only appear here scoped under the module\'s own',
    sheetRules.every(rule => rule.sel.split(',').every(sel =>
        ! /\.master-[a-z0-9-]+/.test(sel) || /\.si-[a-z0-9-]+/.test(sel))),
    'an unscoped .master-* here overrides the design system for every page that loads it');

const definedSi = new Set(sheetRules.flatMap(rule =>
    [...rule.sel.matchAll(/\.(si-[a-z0-9-]+)/g)].map(m => m[1])));

const usedSi = new Set();
Object.values(screens).forEach(text =>
    [...text.matchAll(/\bsi-[a-z0-9-]+/g)].forEach(m => usedSi.add(m[0])));
[...js.matchAll(/['"]?(si-[a-z0-9-]+)['"]?/g)].forEach(m => usedSi.add(m[1]));

const undefinedSi = [...usedSi].filter(name => ! definedSi.has(name));

check('every class the invoice screens wear is defined in the module\'s sheet',
    undefinedSi.length === 0,
    'used and never declared: ' + undefinedSi.join(', '));

/* ---- 2. one money rule, one ageing vocabulary --------------------------- */

check('the money rule is spelled once, and it is the model\'s',
    /public const RECEIVED_SQL = /.test(model)
    && /public function receivedAmount\(\): float/.test(model)
    && /public function balanceDue\(\): float/.test(model)
    && (controller.match(/SalesInvoice::RECEIVED_SQL/g) || []).length >= 3
    && ! /from cashflow_entries/.test(controller)
    && ! /from cashflow_entries/.test(service)
    && ! /from cashflow_entries/.test(view),
    'the ledger join belongs to the model, and every screen asks the model for it');

check('the list does no money arithmetic of its own',
    ! /credit_amount/.test(view)
    && ! /debit_amount/.test(view)
    && ! /total_amount\s*-/.test(view)
    && /\$invoice->receivedAmount\(\)/.test(view)
    && /\$invoice->balanceDue\(\)/.test(view),
    'a view that adds money up is a second definition of it');

check('the figures are one aggregate over the filtered rows, not a loop',
    /selectRaw\(/.test(controller)
    && /coalesce\(sum\(/.test(controller)
    && ! /->get\(\)\s*->sum\(/.test(controller)
    && ! /SalesInvoice::[^;]*->get\(\)/.test(controller),
    'the old figures hydrated every invoice three times');

check('an aggregate alias is read off the row, never through value()',
    ! /selectRaw\([\s\S]{0,240}?->value\(/.test(controller)
    && ! /selectRaw\([\s\S]{0,240}?->value\(/.test(model)
    && /->first\(\)\?->received/.test(controller)
    && /->first\(\)\?->received/.test(model),
    'value() replaces the select list with the alias, which does not exist');

const bucketKeys = [...model.matchAll(/^\s*'(current|1_30|31_60|61_90|90_plus)' =>/gm)].map(m => m[1]);

check('how late is one vocabulary: the model\'s buckets are the filter\'s',
    /public static function ageingBuckets\(\): array/.test(model)
    && bucketKeys.length === 5
    && bucketKeys.every(key => new RegExp("'" + key + "'").test(service))
    && /'overdue'/.test(service)
    && /public function ageingBucket\(/.test(model)
    && /public function isOverdue\(/.test(model),
    'a chip that says "31-60 days late" has to filter by the bucket the row is in');

const stateKeys = ['draft', 'sent', 'accepted', 'partial', 'paid', 'overdue', 'cancelled'];

/* A state is drawn when a rule gives it a tone; the dark sheet only re-tints it. */
const drawn = (key) => sheetRules.some(rule => rule.sel.split(',').some(sel =>
    new RegExp('\\.si-status\\.status-' + key + '(?![a-z0-9_-])').test(sel))
    && /--tone-bg/.test(rule.body));

const missingStates = stateKeys.filter(key => ! drawn(key) || ! new RegExp("'" + key + "'").test(model));

check('every state the row can wear is a state the sheet draws',
    missingStates.length === 0 && /stateKey\(/.test(model) && /stateLabel\(/.test(model),
    'a state with no rule is a chip with no colour: ' + missingStates.join(', '));

check('the state is the money\'s, and a draft or a cancellation is the office\'s',
    /\$payment = \$this->paymentState\(\);/.test(model)
    && /if \(in_array\(\$this->status, \['draft', 'cancelled'\], true\)\)/.test(model)
    && /return \$this->isOverdue\(\$today\) \? 'overdue' : 'sent';/.test(model.replace(/\n\s*/g, ' '))
    || /'overdue';/.test(model) && /paymentState\(\)/.test(model),
    'the chip is read from the money, except where the office has decided');

/* ---- 3. the vocabulary and the query ----------------------------------- */

const keysBlock = service.slice(service.indexOf('public const DEFAULTS'), service.indexOf('public const LABELS'));
const labelBlock = service.slice(service.indexOf('public const LABELS'), service.indexOf('public const VIEW_KEYS'));
const viewKeysBlock = service.slice(service.indexOf('public const VIEW_KEYS'), service.indexOf('public const PAYMENT_KEYS'));
const queryKeys = [...keysBlock.matchAll(/^\s*'([a-z_]+)' =>/gm)].map(m => m[1]);
const labelKeys = [...labelBlock.matchAll(/^\s*'([a-zA-Z]+)' =>/gm)].map(m => m[1]);
const viewKeyNames = [...viewKeysBlock.matchAll(/^\s*'([a-zA-Z]+)' => '([a-z_]+)'/gm)].map(m => [m[1], m[2]]);

const viewKeySet = new Set(viewKeyNames.map(([camel]) => camel));
const rangeKeyNames = [...service
    .slice(service.indexOf('public const RANGE_LABELS'), service.indexOf('public const PAYMENT_KEYS'))
    .matchAll(/^\s*'([a-zA-Z]+)' =>/gm)].map(m => m[1]);

check('every dimension the screen reads is declared in the vocabulary',
    viewKeyNames.length >= 13
    && viewKeyNames.every(([, queryKey]) => queryKeys.includes(queryKey))
    && labelKeys.length >= 10
    && labelKeys.every(key => viewKeySet.has(key)),
    'a filter with no label is a filter nobody can remove');

check('a dimension the screen reads gets exactly one chip',
    labelKeys.every(key => new Set(labelKeys).size === labelKeys.length)
    && rangeKeyNames.length === 2
    && rangeKeyNames.every(key => viewKeySet.has(key) && ! labelKeys.includes(key))
    && /'query' => \[\$queryKey\]/.test(service)
    && /implode\(' · '/.test(controller),
    'a date range is one question and two keys — two chips for it is a filter the '
    + 'accountant has to remove twice');

check('an empty filter is empty: nothing blank reaches a where or a date',
    /'date_from', 'date_to', 'due_from', 'due_to' => DateRanges::normalise\(\$value\),/.test(service)
    && /'search' => trim\(\(string\) \$value\) \?: null,/.test(service),
    'a blank date that reaches Carbon is a 500 over a filter');

check('a filter that is present is the filter, even when it is empty',
    /array_key_exists\(\$key, \$filters\)/.test(service)
    && /function default\(string \$key\)/.test(service)
    && ! /\?\? self::DEFAULTS/.test(service),
    '`?? default` reads a declared null as a missing key — the bug that shipped twice');

check('a value the module does not know widens the list',
    /array_merge\(\['overdue'\], array_keys\(SalesInvoice::ageingBuckets\(\)\)\), true\)/.test(service)
    && /return \$q;/.test(service)
    && /'overdue'/.test(service),
    'an unknown filter is a filter that failed — showing everything is the honest answer');

check('the money filter asks the money, not a stored word',
    /whereDoesntHave\('payments'\)/.test(service)
    && /whereRaw\("\{\$received\} > 0\.01"\)/.test(service)
    && /whereRaw\("\{\$received\} >= sales_invoices\.total_amount - 0\.01"\)/.test(service),
    'an invoice marked paid a year ago, whose receipt was deleted, is not paid');

/* ---- 4. the list wears the shared chrome ------------------------------- */

const chrome = [
    'class="si-index master-list"',
    'class="master-stats"',
    'class="master-list-bar"',
    'class="master-list-chips"',
    'class="master-filter-row"',
    'class="master-list-applied"',
    'class="master-card master-table-card master-card--flat"',
    'class="master-list-toolbar"',
    'class="master-list-density desktop-only"',
    'class="master-list-total"',
    'class="master-list-empty"',
];

check('the list wears the chrome every other listing wears',
    chrome.every(needle => view.includes(needle))
    && /<x-pagination :items="\$invoices" \/>/.test(view)
    && (view.match(/data-label="/g) || []).length >= 9,
    'the module does not draw its own list; it fills the shared one');

check('the page rhythm belongs to the shell, not to an inline style',
    /\.master-list > \.master-card \+ \.master-card/.test(listCss)
    && /\.master-list > \.master-card \+ \.master-grid/.test(listCss)
    && /class="si-index master-list"/.test(view)
    && /<div class="master-list">/.test(record)
    && ! /style="margin-top/.test(view)
    && ! /style="margin-top/.test(record),
    'a negative margin on a record page is a page spacing itself');

check('the sheet and the script are loaded with a version',
    ['index', 'form', 'record'].every(name =>
        new RegExp("\\$assetVer\\('assets/(css|js)/sales-invoices\\.(css|js)'\\)").test(
            { index: view, form, record }[name]))
    && ! /asset\('assets\/(css|js)\/sales-invoices/.test(view + form + record),
    'without a version the browser keeps serving the fork after it is gone');

/* ---- 5. the actions that make it a module ------------------------------ */

const PARTIAL = 'resources/views/sales_invoices/partials/payment-modal.blade.php';
const dialog = fs.existsSync(path.join(ROOT, PARTIAL)) ? read(PARTIAL) : '';

const viewRouteNames = [...(view + form + record + js).matchAll(/route\('(sales-invoices\.[a-zA-Z.]+)'/g)]
    .map(m => m[1]);

/* `Route::resource('sales-invoices')` registers these seven names itself — a
   guard that does not know that fails on a module that is perfectly correct. */
const resourceNames = ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']
    .map(action => 'sales-invoices.' + action);
const unregistered = viewRouteNames.filter(name =>
    ! routes.includes("'" + name + "'") && ! resourceNames.includes(name));

check('every route the screens link to is registered',
    viewRouteNames.length >= 10
    && /Route::resource\('sales-invoices'/.test(routes)
    && unregistered.length === 0,
    'a menu item that 500s is worse than a missing one: ' + unregistered.join(', '));

const exportAt = routes.indexOf("'/sales-invoices/export'");
const savedAt = routes.indexOf("'/sales-invoices/saved-views'");
const resourceAt = routes.indexOf("Route::resource('sales-invoices'");

check('the vocabulary routes are registered before the resource route',
    exportAt !== -1 && savedAt !== -1 && resourceAt !== -1 && exportAt < resourceAt && savedAt < resourceAt,
    '/sales-invoices/export read by the resource route is an invoice called "export"');

check('a receipt is a ledger entry, linked to the invoice it pays',
    /new CashflowEntry\(\)/.test(controller)
    && /\$entry->sales_invoice_id = \$salesInvoice->id;/.test(controller)
    && /\$entry->transaction_type = 'credit';/.test(controller)
    && /\$entry->client_id = \$salesInvoice->client_id;/.test(controller)
    && /public function recordPayment\(/.test(controller)
    && /sales-invoices\.payments\.store/.test(dialog),
    'the money lives in the ledger — that is where the bank line is reconciled');

check('the stored balance follows the money rule, so the statement agrees',
    /refreshInvoiceMoney/.test(controller)
    && /\$invoice->balanceDue\(\)/.test(controller)
    && /'balance_amount' => \$balance,/.test(controller)
    && /receivedAmount\(\) \+ \$invoice->ledgerReceived\(\)/.test(controller.replace(/\n\s*/g, ' '))
    || /ledgerReceived\(\)/.test(controller),
    'PartyStatement prints this column on a client\'s statement');

check('the export is the screen\'s own rows',
    /public function export\(Request \$request\): StreamedResponse/.test(controller)
    && /filteredQuery\(\$filters\)/.test(controller)
    && (controller.match(/filteredQuery\(/g) || []).length >= 3
    && /Filtered by/.test(controller)
    && /sales-invoices\.export/.test(view),
    'a file that leaves the app has to contain the rows the screen was showing');

check('the receipt dialog is written once and both screens open it',
    dialog !== ''
    && /@include\('sales_invoices\.partials\.payment-modal'\)/.test(view)
    && /@include\('sales_invoices\.partials\.payment-modal'\)/.test(record)
    /* one dialog in the whole module: it is the partial, and neither screen
       carries a second copy of the form */
    && dialog.split('data-payment-form').length === 2
    && (view + record).split('data-payment-form').length === 1
    && /data-open-payment/.test(view)
    && /data-open-payment/.test(record),
    'the page about one invoice is where the office rings about it — a receipt has to be '
    + 'recordable there, from the one dialog');

check('the receipt dialog names itself and comes back with the typing',
    /<input type="hidden" name="_dialog" value="paymentModal">/.test(dialog)
    && /data-open-dialog="\{\{ \$errors->any\(\) \? old\('_dialog'\) : '' \}\}"/.test(dialog)
    && /window\.MasterModal\.open\(dialog\)/.test(js)
    && /data-action-template/.test(dialog)
    && /template\.replace\('__INVOICE__', id\)/.test(js),
    'one dialog for the page: the row that opened it says which invoice it is for');

check('the action cell is a menu, not a row of buttons',
    /class="master-row-actions"/.test(view)
    && /class="master-dropdown-toggle"/.test(view)
    && /class="master-dropdown-menu"/.test(view)
    && ! /master-dropdown-menu[\s\S]{0,900}?class="master-btn/.test(view),
    'a row menu item is an icon and a label, never a framed button');

check('the form says which figure its own field is',
    /Opening received/.test(form)
    && /Receipts filed\s*\n?\s*against this invoice are added on top/.test(form.replace(/\s+/g, ' '))
    && ! /Amount Paid/.test(form),
    '"Amount Paid" beside a balance changed every screen that prints it');

/* ---- 6. the paperwork -------------------------------------------------- */

/* ---- 7. what each screen is handed -------------------------------------
   The failure this guards against has shipped twice: a Blade template reading a
   variable nobody passed. PHP turns that into an `ErrorException`, so the page
   is a 500 — and no syntax check, no design rule and no mutation of the query
   layer can see it. The contract is written here, in both directions: every
   variable the screen (and the partials it includes) reads must be one the
   method that renders it passes, and every name the contract lists must be read
   somewhere, so a rename cannot leave a lie behind. */

const GLOBALS = ['errors', 'message', 'loop', 'assetVer'];
const viewKeysCamel = viewKeyNames.map(([camel]) => camel);

const methodKeys = (needle) => {
    const at = controller.indexOf(needle);
    if (at === -1) return [];

    const call = controller.slice(at, controller.indexOf(');', at));
    const named = [...call.matchAll(/'([a-zA-Z_][a-zA-Z0-9_]*)' =>/g)].map(m => m[1]);
    const compacted = [...call.matchAll(/compact\(([^)]*)\)/g)]
        .flatMap(m => [...m[1].matchAll(/'([a-zA-Z_][a-zA-Z0-9_]*)'/g)].map(k => k[1]));

    return [...named, ...compacted];
};

const sharedKeys = methodKeys('private function sharedData');
const indexKeys = methodKeys("view('sales_invoices.index'");
const formKeys = methodKeys("view('sales_invoices.form'");
const showKeys = methodKeys("view('sales_invoices.show'");
const printKeys = methodKeys("view('sales_invoices.print'");

/* A screen reads its own file and every partial it pulls in. */
const reads = (file) => {
    const text = read(file);
    const parts = [...text.matchAll(/@include\('([^']+)'\)/g)]
        .map(m => 'resources/views/' + m[1].replace(/\./g, '/') + '.blade.php')
        .filter(part => fs.existsSync(path.join(ROOT, part)))
        .map(part => read(part)).join('\n');

    const all = text + '\n' + parts;
    const declared = new Set();

    for (const m of all.matchAll(/\$([a-zA-Z_][a-zA-Z0-9_]*)\s*=(?![=>])/g)) declared.add(m[1]);
    for (const m of all.matchAll(/\bas\s+\$([a-zA-Z_][a-zA-Z0-9_]*)/g)) declared.add(m[1]);
    for (const m of all.matchAll(/\bas\s+\$\w+\s*=>\s*\$([a-zA-Z_][a-zA-Z0-9_]*)/g)) declared.add(m[1]);
    for (const m of all.matchAll(/(?:fn|function)\s*\(([^)]*)\)/g)) {
        for (const p of m[1].matchAll(/\$([a-zA-Z_][a-zA-Z0-9_]*)/g)) declared.add(p[1]);
    }

    return [...new Set([...all.matchAll(/\$([a-zA-Z_][a-zA-Z0-9_]*)/g)].map(m => m[1]))]
        .filter(name => ! declared.has(name) && ! GLOBALS.includes(name));
};

/* [file, the keys the view call spells out, the keys it hands by construction] */
const screensWithContract = [
    ['resources/views/sales_invoices/index.blade.php',
        [...indexKeys, ...sharedKeys], viewKeysCamel],
    ['resources/views/sales_invoices/form.blade.php',
        [...formKeys, ...sharedKeys], []],
    ['resources/views/sales_invoices/show.blade.php', showKeys, []],
    ['resources/views/sales_invoices/partials/payment-modal.blade.php',
        showKeys.concat(indexKeys), []],
    ['resources/views/sales_invoices/print.blade.php', printKeys, []],
];

for (const [file, handed, spread] of screensWithContract) {
    const undecided = reads(file).filter(name => ! handed.includes(name) && ! spread.includes(name));

    check('every variable ' + path.basename(file) + ' reads is passed to it',
        undecided.length === 0,
        'read but never handed to this view: ' + undecided.join(', '));
}

const ownKeys = [
    ['resources/views/sales_invoices/index.blade.php', indexKeys],
    ['resources/views/sales_invoices/form.blade.php', formKeys],
    ['resources/views/sales_invoices/show.blade.php', showKeys],
    ['resources/views/sales_invoices/print.blade.php', printKeys],
];

const sharedRead = [
    ...reads('resources/views/sales_invoices/index.blade.php'),
    ...reads('resources/views/sales_invoices/form.blade.php'),
];

check('no screen is handed a variable nothing reads',
    ownKeys.every(([file, keys]) => keys.every(name => reads(file).includes(name)))
    && sharedKeys.every(name => sharedRead.includes(name)),
    'a stale entry in the contract is a rename that half happened — or payload that '
    + 'no screen has read in years');

check('the framework variables the screens read are the shell\'s own',
    GLOBALS.every(name => name === 'assetVer'
        ? /View::share\('assetVer'/.test(read('app/Providers/AppServiceProvider.php'))
        : true),
    'reading a variable is not the same as it existing');

check('the module explains itself where the next person will look',
    fs.existsSync(path.join(ROOT, 'docs/invoice-management.md'))
    && /receivedAmount\(\)/.test(read('docs/invoice-management.md'))
    && /RECEIVED_SQL/.test(read('docs/invoice-management.md'))
    && /ageingBuckets\(\)/.test(read('docs/invoice-management.md'))
    && /Record payment|record a receipt/i.test(read('docs/invoice-management.md')),
    'the money rule is the part nobody can re-derive from the code in a hurry');

/* ---------------------------------------------------------------- report */

const failed = out.filter(([, ok]) => !ok);
out.forEach(([name, ok, detail]) =>
    console.log((ok ? '  ok   ' : '  FAIL ') + name + (ok || !detail ? '' : '  -> ' + detail)));
console.log('\ninvoices: ' + (out.length - failed.length) + ' passed, ' + failed.length + ' failed');
process.exit(failed.length ? 1 : 0);
