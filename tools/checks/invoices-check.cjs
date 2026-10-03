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

/* The figures are read in `index()` and nowhere else: a sum over hydrated rows
   there is the regression this names (the bulk sweep's own `get()` of the ticked
   ids is a different question and not this guard's business). */
const indexBody = controller.slice(
    controller.indexOf('public function index('),
    controller.indexOf('public function export(')
);

check('the figures are one aggregate over the filtered rows, not a loop',
    /selectRaw\(/.test(indexBody)
    && /coalesce\(sum\(/.test(indexBody)
    && ! /->get\(\)\s*->sum\(/.test(indexBody)
    && ! /->get\(\)/.test(indexBody.replace(/->first\(\)/g, ''))
    && /SalesInvoice::RECEIVED_SQL/.test(indexBody),
    'the old figures hydrated every invoice three times');

check('an aggregate alias is read off the row, never through value()',
    ! /selectRaw\([\s\S]{0,240}?->value\(/.test(controller)
    && ! /selectRaw\([\s\S]{0,240}?->value\(/.test(model)
    /* the alias is read off the aggregate row — `$totals->received` in the list,
       `first()?->received` in the model. `value('received')` is the one spelling
       that lies, because it replaces the select list with the alias. */
    && /\$totals->counted/.test(controller)
    && /\$totals->received/.test(controller)
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

const stateKeys = ['draft', 'sent', 'accepted', 'partial', 'paid', 'overdue', 'cancelled', 'converted'];

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

/* A constant block, from its own line to the `];` that closes it — never "from
   here to the next constant", which silently grows when a constant is added
   between the two. */
const constBlock = (name) => {
    const at = service.indexOf('public const ' + name);
    if (at === -1) return '';

    return service.slice(at, service.indexOf('];', at) + 2);
};

const keysBlock = constBlock('DEFAULTS');
const labelBlock = constBlock('LABELS');
const viewKeysBlock = constBlock('VIEW_KEYS');
const queryKeys = [...keysBlock.matchAll(/^\s*'([a-z_]+)' =>/gm)].map(m => m[1]);
const labelKeys = [...labelBlock.matchAll(/^\s*'([a-zA-Z]+)' =>/gm)].map(m => m[1]);
const viewKeyNames = [...viewKeysBlock.matchAll(/^\s*'([a-zA-Z]+)' => '([a-z_]+)'/gm)].map(m => [m[1], m[2]]);

const viewKeySet = new Set(viewKeyNames.map(([camel]) => camel));
const rangeKeyNames = [...constBlock('RANGE_LABELS').matchAll(/^\s*'([a-zA-Z]+)' =>/gm)].map(m => m[1]);

/* the chase worklist's own vocabulary, held to the chips the view draws */
const chaseKeyNames = [...constBlock('CHASE_KEYS').matchAll(/'([a-z0-9_]+)'/g)].map(m => m[1]);
const chaseLabelNames = [...constBlock('CHASE_LABELS').matchAll(/^\s*'([a-z0-9_]+)' =>/gm)].map(m => m[1]);

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

check('the chase worklist is one vocabulary too',
    chaseKeyNames.length === 3
    && chaseKeyNames.every(key => chaseLabelNames.includes(key))
    /* one loop draws the chase filter, from the labels the controller passes —
       it is a select in the filter row now; the chips that used to carry it
       were removed from the strip at the office's request, and with them the
       counts they asked for */
    && /@foreach\s*\(\$chaseLabels as \$chaseKey => \$chaseLabel\)/.test(view)
    && /<option value="\{\{ \$chaseKey \}\}" @selected\(\$chase === \$chaseKey\)>/.test(view)
    && /'chaseLabels' => SalesInvoiceFilters::CHASE_LABELS/.test(controller)
    && ! /chipCounts\['chase_/.test(view)
    && ! /\['payment' => 'unpaid'\]/.test(view)
    && ! /\['ageing' => 'overdue'\]/.test(view)
    /* and the question is asked of the log, in SQL, both ways */
    && /whereDoesntHave\('reminders'/.test(service)
    && /whereHas\('reminders'/.test(service)
    && /withCount\('reminders'\)/.test(model)
    && /withMax\('reminders as last_reminded_at', 'reminded_at'\)/.test(model)
    && /->withReminders\(\)/.test(controller),
    'a chip that says "not nudged in a week" has to ask the reminder log — and the '
    + 'list has to read that log without a query per row');

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
    && /\.master-list > \.master-grid \+ \.master-grid/.test(listCss)
    && /class="si-index master-list"/.test(view)
    && /<div class="master-list si-show">/.test(record)
    && ! /style="margin-top/.test(view)
    && ! /style="margin-top/.test(record),
    'a negative margin on a record page is a page spacing itself — and two grids '
    + 'stacked on that page are two cards sharing an edge');

/* ---- 4b. the record screen ------------------------------------------- */

const attachment = read('app/Models/SalesInvoiceAttachment.php');

/* The record page is not a hand-drawn screen: it is the same header, the same
   figures and the same grids every other record page in the office wears. The
   module's own hero classes (`si-head`, `si-count`) are what it looked like
   when it drew its own, and they are gone. */
const recordWears = [
    'class="master-card master-header"',
    'record-head-main',
    'record-head-chips',
    'record-head-actions',
    'master-stat-title',
    'master-stat-value',
    'master-section-head',
    'class="master-facts"',
    'class="master-grid is-even"',
];

check('the record page is the shared composition, not a screen of its own',
    recordWears.every(needle => record.includes(needle))
    && (record.match(/class="master-stat master-stat--flat/g) || []).length === 5
    && ! /class="si-head"/.test(record)
    && ! /class="si-count"/.test(record),
    'a record page that draws its own header, its own figures and its own '
    + 'spacing is the fork growing back one screen at a time');

/* `.master-empty` is the listing-level empty — 70px of vertical padding in the
   middle of a table. A card on a record page gets `.master-empty-state`, and a
   fact with nothing in it says so in words: this page printed `-/-` for a
   missing GSTIN and joined four address fields with commas over the empty
   ones. */
check('a card with nothing in it offers a sentence and a way out',
    ! /class="master-empty"/.test(record)
    && ! /-\/-/.test(record)
    && ! /<span[^>]*>\s*-\s*<\/span>/.test(record)
    && (record.match(/master-empty-state/g) || []).length >= 5
    && (record.match(/master-empty-value/g) || []).length >= 6,
    'an empty record card is not an empty list: it is a sentence, an icon, the '
    + 'button that fills it — and a dash is not a value');

check('the figures on the record page are the model\'s, not the page\'s',
    /\$invoice->receivedAmount\(\)/.test(record)
    && /\$invoice->balanceDue\(\)/.test(record)
    && ! /->sum\(/.test(record),
    'the page may not add the money up itself: the receipt rows read the ledger '
    + 'line and the balance reads the model, so the list, the record and the '
    + 'statement cannot disagree');

check('a file row reads its icon and its size from the model',
    /public function icon\(\): string/.test(attachment)
    && /public function sizeLabel\(\): string/.test(attachment)
    && /\$attachment->icon\(\)/.test(record)
    && /\$attachment->sizeLabel\(\)/.test(record)
    && ! /\$attachment->file_size\s*\//.test(record),
    'a view that picks an icon off a file name and divides bytes itself is '
    + 'arithmetic in a template');

check('the product cells name themselves for the stacked view',
    /class="master-table-wrap"/.test(record)
    && (record.match(/data-label="/g) || []).length >= 7,
    'below 768px the shell stacks the row and prints each cell\'s own column '
    + 'name: a cell without one stacks as loose text');

check('the actions run in the order the page is read',
    ['sales-invoices.index', 'sales-invoices.edit', 'sales-invoices.print']
        .map(name => record.indexOf("route('" + name + "'"))
        .every((at, i, all) => at > -1 && (i === 0 || at > all[i - 1]))
    && record.indexOf('data-open-payment') > record.indexOf("route('sales-invoices.edit'"),
    'back, then edit, then the two things this page is opened to do, then the '
    + 'document: the old header was Back · Edit · Log reminder · Print · Mark '
    + 'Sent / Portal in no order anyone could learn');

check('a receipt row reads its direction from the ledger line',
    /\$netReceipt < 0 \? 'Refunded'/.test(record)
    && /\$payment->credit_amount/.test(record)
    && /\$payment->debit_amount/.test(record),
    'a debit against an invoice is money going back out, and the row has to say '
    + 'so — the same rule the salary entries follow');

check('the sheet and the script are loaded with a version',
    ['index', 'form', 'record'].every(name =>
        new RegExp("\\$assetVer\\('assets/(css|js)/sales-invoices\\.(css|js)'\\)").test(
            { index: view, form, record }[name]))
    && ! /asset\('assets\/(css|js)\/sales-invoices/.test(view + form + record),
    'without a version the browser keeps serving the fork after it is gone');

/* ---- 4c. one document, one claim on the money -------------------------- */

/* The office's rule: a proforma becomes a tax invoice once, and after that the
   proforma is history — it is not sales, it is not a second receivable, and it
   is not late. Each half of that rule is enforced in one place: the link on the
   proforma (with a row lock, because a conversion is two clicks apart), the
   scopes, and the statement's own reads. */
const statementService = read('app/Services/PartyStatement.php');

check('a proforma becomes exactly one tax invoice',
    /\$already = \$salesInvoice->convertedInvoice/.test(controller)
    && /lockForUpdate\(\)/.test(controller)
    && /\$proforma->converted_invoice_id = \$tax->id;/.test(controller)
    && /->moveAdvanceTo\(/.test(controller),
    'the second conversion is refused by the proforma\'s own link, not by a '
    + 'count: without locking the row, two tabs convert the same proforma twice');

check('a converted proforma is out of sales, out of the ageing and out of the statement',
    /if \(\$this->isSuperseded\(\)\) \{\s*\n\s*return 0\.0;/.test(model)
    && /public function scopeNotSuperseded/.test(model)
    && (statementService.match(/->notSuperseded\(\)/g) || []).length === 4
    && /Moved to/.test(record)
    && /Moved to/.test(view),
    'the tax invoice stands for that money: a proforma that has become one owes '
    + 'nothing, and every read that would count it twice says so');

check('sales and potential revenue are two figures, and one document is never in both',
    /'sales' => \(float\) \(\$figures->sales \?\? 0\)/.test(controller)
    && /'potential' => \(float\) \(\$figures->potential \?\? 0\)/.test(controller)
    && /invoice_type = \\'tax\\'/.test(controller)
    && (controller.match(/converted_invoice_id is null/g) || []).length >= 2
    && /Sales \(filtered\)/.test(view)
    && /Potential revenue \(filtered\)/.test(view),
    'a proforma is potential revenue until a tax invoice is raised from it and '
    + 'nothing after that; adding the two into one "invoiced" figure is how the '
    + 'same 50,000 was counted twice');

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

/* Every field the server sums with has to be a field the form posts. The line
   discount was the exception: the row had no `discount_percent` input, the
   controller recomputes each line from the posted value, so the discount went
   in and was written back as zero by the next save. */
const itemMethod = controller.slice(
    controller.indexOf('private function syncItemsAndTotals'),
    controller.indexOf('private function storeAttachments')
);
const itemKeys = [...new Set([...itemMethod.matchAll(/\$item\['([a-z_]+)'\]/g)].map(m => m[1]))];

check('the form posts every field the server sums with',
    itemKeys.length >= 10
    && itemKeys.every(key => js.includes('][' + key + ']'))
    && /Disc %/.test(form)
    && /discount_percent/.test(form),
    'a field the server reads and the form does not post is data the office '
    + 'typed and lost: the line discount column is the one this guard exists for');

/* A money preview is only honest while it fills the ledger the office reads.
   One row nobody fills prints ₹ 0.00 for ever — and a preview that does not know
   about the receipts already filed against the invoice shows a balance the save
   will not print. */
const previewIds = [...form.matchAll(/id="(preview[A-Za-z]+)"/g)].map(m => m[1]);

check('the live preview fills every line of the ledger it draws',
    previewIds.length >= 10
    && previewIds.every(id => new RegExp("'" + id + "'").test(js))
    && /data-ledger-received="\{\{ \$ledgerReceived \}\}"/.test(form)
    && /\bledgerReceived\b/.test(js)
    && /syncItemsAndTotals/.test(js),
    'the ledger and the preview are one rule: the view draws the lines, the '
    + 'script fills them, and the balance reads the same receipts the model reads');

check('the form says which figure its own field is',
    /Opening received/.test(form)
    && /Receipts filed\s*\n?\s*against this invoice are added on top/.test(form.replace(/\s+/g, ' '))
    && ! /Amount Paid/.test(form),
    '"Amount Paid" beside a balance changed every screen that prints it');

/* The one class of SQL the host does not forgive: a select appended to a builder
   that already carries subselects. `withCount`/`withSum`/`withMax` write the table's
   columns plus correlated subqueries into the column list, and `selectRaw`/
   `select` append — an aggregate beside non-aggregated columns is MySQL 1140
   (only_full_group_by) and a 500 on the listing. Statements are what a `;` splits:
   the eager loads and the aggregate have to be in the same chain to be this bug. */
const selectAndAggregate = [controller, model]
    .flatMap(text => text.split(';'))
    .filter(statement => /selectRaw\(|->select\(/.test(statement)
        && /withCount\(|withSum\(|withMax\(/.test(statement));

check('no aggregate is appended to a builder that carries subselects',
    selectAndAggregate.length === 0,
    'selecting onto withCount/withSum/withMax is MySQL 1140 on a host running '
    + 'only_full_group_by — ask a query of its own instead');

check('the figures and the page totals are asked of a query of their own',
    /* one aggregate for the tiles, one for the footer — neither off the listing's
       own builder */
    (controller.match(/filteredQuery\(\$filters\)->reorder\(\)->selectRaw\(/g) || []).length === 2
    && ! /clone \$query\)->reorder\(\)->selectRaw\(/.test(controller)
    && /'counted' => \(float\) \(\$totals->counted \?\? 0\)/.test(controller)
    && /'received' => \(float\) \(\$totals->received \?\? 0\)/.test(controller),
    'the footer money is a query of its own, not the page the rows were loaded with');

/* A date hint is `\DateTimeInterface`. This model once hinted
   `?Illuminate\Support\Carbon\CarbonInterface` — a name no Laravel ships — and a hint
   nothing implements rejects every date a caller passes while accepting `null` without a
   murmur, so the 500 waits for the first caller that hands over a real date
   (`reminderMessage()` did, and the listing went down). Carbon, CarbonImmutable, DateTime
   and Date all satisfy `\DateTimeInterface`; it needs no import and no framework upgrade
   can move it out from under the hint. */
const dateParams = ['isOverdue', 'daysOverdue', 'ageingBucket', 'stateKey', 'stateLabel', 'reminderMessage'];

check('every date the model is handed is a \\DateTimeInterface',
    dateParams.every(name => model.includes('function ' + name + '(?\\DateTimeInterface $today = null)'))
    && model.includes('public function lastRemindedAt(): ?\\DateTimeInterface')
    && ! model.includes('CarbonInterface'),
    'a hint nothing implements is a 500 on the first caller that passes a date — '
    + '?Foo admits null happily and then rejects the Carbon the app hands it');

/* ---- 6b. the chase, the sweep and the CA's file ------------------------ */

const reminderModel = read('app/Models/SalesInvoiceReminder.php');
const reminderPartial = 'resources/views/sales_invoices/partials/reminder-modal.blade.php';
const reminderDialog = read(reminderPartial);
const migrations = fs.readdirSync(path.join(ROOT, 'database/migrations'));
const reminderMigration = migrations.find(file => /create_sales_invoice_reminders_table/.test(file)) || '';
const reminderMigrationText = reminderMigration ? read('database/migrations/' + reminderMigration) : '';

check('a reminder is a log, never a counter beside one',
    reminderMigrationText !== ''
    && /foreignId\('sales_invoice_id'\)->constrained\('sales_invoices'\)->cascadeOnDelete\(\)/.test(reminderMigrationText)
    && /index\(\['sales_invoice_id', 'reminded_at'\]\)/.test(reminderMigrationText)
    /* the invoice carries no "nudged" column at all: the log is the definition */
    && ! migrations
        .filter(file => /_(create|add_.*to)_sales_invoices_table/.test(file))
        .some(file => /reminded_at|reminders_count/.test(read('database/migrations/' + file))),
    'a counter kept beside a log drifts the first time a log row is deleted');

check('one channel vocabulary, shared by the dialog and the validation',
    /public static function channelOptions\(\): array/.test(reminderModel)
    && /'whatsapp' =>/.test(reminderModel)
    && reminderDialog.includes('$reminderChannels')
    && /'reminderChannels' => SalesInvoiceReminder::channelOptions\(\)/.test(controller)
    && (controller.match(/SalesInvoiceReminder::channelOptions\(\)/g) || []).length >= 3,
    'a channel the dialog offers that the controller refuses is a form that cannot save');

check('a chase is linked, stamped and says what was sent',
    /\$reminder->sales_invoice_id = \$salesInvoice->id;/.test(controller)
    && /\$reminder->reminded_at = \$data\['reminded_at'\] \?\? now\(\)->toDateString\(\);/.test(controller)
    && /\$reminder->message = \$data\['message'\] \?\? \$salesInvoice->reminderMessage\(\);/.test(controller)
    && /\$reminder->created_by = Auth::id\(\);/.test(controller)
    && /public function reminders\(\)/.test(model)
    && /orderByDesc\('reminded_at'\)/.test(model),
    'the log exists so that "when did we last ask" has an answer — an unlinked or '
    + 'unstamped row answers nothing');

check('the reminder words are the model\'s, and the link is only there when it opens',
    /public function reminderMessage\(/.test(model)
    && /\$balance = \$this->balanceDue\(\);/.test(model)
    && /CommonHelper::amount\(\$amount, \$this->currency\)/.test(model)
    && /if \(\$this->show_client_portal && \$this->public_token\)/.test(model)
    && /data-invoice-message="\{\{ \$invoice->reminderMessage\(\) \}\}"/.test(view)
    && /data-invoice-message="\{\{ \$invoice->reminderMessage\(\) \}\}"/.test(record),
    'a reminder that links to a page the client cannot open is worse than one with no link');

check('the dialog is written once and both screens open it',
    reminderDialog !== ''
    && /@include\('sales_invoices\.partials\.reminder-modal'\)/.test(view)
    && /@include\('sales_invoices\.partials\.reminder-modal'\)/.test(record)
    && reminderDialog.split('data-reminder-form').length === 2
    && (view + record).split('data-reminder-form').length === 1
    && /data-open-reminder/.test(view)
    && /data-open-reminder/.test(record)
    /* the dialog's copy button copies the field the office is editing, not the
       model's original words — what they wrote is what goes out, and the script
       alone cannot say that */
    && /data-copy-target="reminderMessage"/.test(reminderDialog)
    && /\[data-copy-target\]/.test(js),
    'one dialog: the row says which invoice, and the copy button copies what the office wrote');

check('the sweep keeps every promise the bar makes',
    /private const BULK_ACTIONS = \[/.test(controller)
    && ['remind', 'mark_sent', 'portal_on', 'portal_off', 'delete_drafts']
        .every(action => new RegExp("'" + action + "' =>").test(controller))
    && /case 'delete_drafts':[\s\S]{0,220}?if \(\$invoice->status === 'draft'\)/.test(controller)
    /* a cancelled document is not dragged back to sent by a sweep of the page */
    && /case 'mark_sent':[\s\S]{0,240}?if \(\$invoice->status === 'cancelled'\)/.test(controller)
    && /\$skipped\+\+;/.test(controller)
    && /'bulkActions' => self::BULK_ACTIONS/.test(controller)
    && /@foreach \(\$bulkActions as \$actionKey => \$actionLabel\)/.test(view)
    /* the boxes hang off the form by id: a form round the table would nest the row
       menus' own forms inside it, and a nested form never submits */
    && /form="bulkForm"/.test(view)
    && /<form id="bulkForm"/.test(view)
    && /action="\{\{ route\('sales-invoices\.bulk'\) \}\}"/.test(view),
    'a bulk delete that takes a sent invoice is a document the client holds, deleted');

check('the sweep is capped and the export takes a selection',
    /array_slice\(array_values\(array_unique\(array_filter\(\$ids\)\)\), 0, 500\)/.test(controller)
    && /\$selected !== \[\]/.test(controller)
    && /whereIn\('sales_invoices\.id', \$selected\)/.test(controller)
    && /'Selected on screen \('/.test(controller),
    'a GET URL is not a place for ten thousand ids, and "export selected" must be '
    + "the screen's own exporter");

check('the CA\'s file is the screen\'s rows, grouped by HSN and rate',
    /public function gstExport\(Request \$request\): StreamedResponse/.test(controller)
    && /whereNotIn\('sales_invoices\.status', \['draft', 'cancelled'\]\)/.test(controller)
    && /groupBy\('hsn_sac', 'gst_percent'\)/.test(controller)
    && /sum\(taxable_amount\) as taxable/.test(controller)
    && /SalesInvoiceItem::query\(\)/.test(controller)
    && /Drafts and cancelled invoices are excluded/.test(controller)
    && /\$this->filteredByLine\(\$selected, \$applied, \$labels\)/.test(controller)
    && (controller.match(/filteredByLine\(/g) || []).length >= 3
    && /sales-invoices\.gstExport/.test(view),
    'a summary that counted drafts would state a GST liability the office never incurred');

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
