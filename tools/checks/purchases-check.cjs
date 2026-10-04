'use strict';

/**
 * The purchase module's guard rails.
 *
 * `invoices-check.cjs` guards the sales side of the same idea; this file guards
 * the purchase side, and it exists because the two sides are close enough to be
 * copy-pasted into disagreement. The rules here are the ones a copy would break
 * silently: a filter that starts reading the stored `status = 'paid'` word
 * instead of the money, a second place that writes `converted_invoice_id`, a
 * purchase sheet that quietly inherits a sales class, a sweep that can do what
 * the row menu cannot, a preview whose arithmetic has drifted from the server's.
 *
 * Dependency-free, like its siblings.
 */

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

const walk = (dir, out = []) => {
    for (const entry of fs.readdirSync(path.join(ROOT, dir), { withFileTypes: true })) {
        const rel = path.join(dir, entry.name);
        entry.isDirectory() ? walk(rel, out) : out.push(rel.replaceAll(path.sep, '/'));
    }
    return out;
};

/* ----------------------------------------------------------------- sources */

const controller = read('app/Http/Controllers/PurchaseInvoiceController.php');
const model = read('app/Models/PurchaseInvoice.php');
const itemModel = read('app/Models/PurchaseInvoiceItem.php');
const ledger = read('app/Services/PurchaseBillLedger.php');
const filtersService = read('app/Services/PurchaseInvoiceFilters.php');
const routes = read('routes/web.php');
const views = walk('resources/views/purchase_invoices');
const viewText = Object.fromEntries(views.map(file => [file, read(file)]));
const allViews = Object.values(viewText).join('\n');
const js = read('public/assets/js/purchase-invoices.js');
const sheet = plain(read('public/assets/css/purchase-invoices.css'));
const printSheet = plain(read('public/assets/css/purchase-invoices-print.css'));
const salesPrintSheet = plain(read('public/assets/css/sales-invoices-print.css'));

/* ------------------------------------------------------- the module's sheets
   The sales rule, kept: a module's sheet defines no shared class. The prefix is
   what makes that checkable, and it is also what stops a purchase screen from
   being re-timed by a sales rule three files away.

   A shared class may still be *scoped* — `.pi-form .master-section` times a
   card inside the module's own page and nothing else — so what is refused is a
   rule that *defines* a `master-*` class, and a shared class in a selector that
   does not lead with the module's own prefix. */

const cssRules = (text, query = '') => {
    const rules = [];
    let i = 0;

    while (i < text.length) {
        const at = text.indexOf('@media', i);
        const brace = text.indexOf('{', i);

        if (at !== -1 && (brace === -1 || at < brace)) {
            const open = text.indexOf('{', at);
            let depth = 0;
            let end = open;

            for (; end < text.length; end++) {
                if (text[end] === '{') depth++;
                else if (text[end] === '}' && --depth === 0) break;
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

const sharedRules = sheetRules.filter(rule =>
    rule.sel.split(',').some(sel => /^(\s*:root\[[^\]]*\]\s*)?\.master-[a-z0-9-]+\s*$/.test(sel.trim())));

check('the module sheet defines no shared master-* class',
    sharedRules.length === 0, sharedRules.slice(0, 3).map(r => r.sel).join(' | '));

check('a shared class may only appear in the module sheet scoped under pi-',
    sheetRules.every(rule => rule.sel.split(',').every(sel =>
        ! /\.master-[a-z0-9-]+/.test(sel) || /\.pi-[a-z0-9-]+/.test(sel))),
    sheetRules.filter(rule => rule.sel.split(',').some(sel =>
        /\.master-[a-z0-9-]+/.test(sel) && ! /\.pi-[a-z0-9-]+/.test(sel))).slice(0, 2).map(r => r.sel).join(' | '));

check('the print sheet defines no class of the shared vocabulary',
    ! /\.master-[a-z0-9-]+/.test(printSheet));

const salesPrintClasses = new Set([...salesPrintSheet.matchAll(/\.([a-z][a-z0-9-]*)/g)].map(m => m[1]));
const printClasses = [...printSheet.matchAll(/\.([a-z][a-z0-9-]*)/g)].map(m => m[1]);
const shared = printClasses.filter(name => salesPrintClasses.has(name));

check('the purchase sheet shares no class with the sales sheet', shared.length === 0, shared.join(', '));

/* Every `pi-` class a purchase view wears has to exist somewhere: an undeclared
   class is a cell with no geometry, and the class audit is the only way to see
   it without a browser. */
const declared = new Set([
    ...[...sheet.matchAll(/\.([a-zA-Z][a-zA-Z0-9_-]*)/g)].map(m => m[1]),
    ...[...printSheet.matchAll(/\.([a-zA-Z][a-zA-Z0-9_-]*)/g)].map(m => m[1]),
    ...[...js.matchAll(/(pi-[a-z0-9-]+)/g)].map(m => m[1]),
]);

const worn = new Set();
for (const text of Object.values(viewText)) {
    for (const m of text.matchAll(/(pi-[a-z0-9-]+)/g)) worn.add(m[1]);
}

const undressed = [...worn].filter(name => !declared.has(name)).sort();

check('every pi-* class a purchase view wears is declared by a module sheet',
    undressed.length === 0, undressed.join(', '));

check('the listing and the record load the module sheet',
    (viewText['resources/views/purchase_invoices/index.blade.php'] || '').includes('assets/css/purchase-invoices.css')
    && (viewText['resources/views/purchase_invoices/show.blade.php'] || '').includes('assets/css/purchase-invoices.css')
    && (viewText['resources/views/purchase_invoices/form.blade.php'] || '').includes('assets/css/purchase-invoices.css'));

check('the print sheet is loaded by the print view and by nothing else',
    (viewText['resources/views/purchase_invoices/print.blade.php'] || '').includes('assets/css/purchase-invoices-print.css')
    && views.filter(file => file !== 'resources/views/purchase_invoices/print.blade.php'
        && viewText[file].includes('purchase-invoices-print.css')).length === 0);

/* ------------------------------------------------------------ one money rule
   What a document is owed is `total − (opening paid + what the vendor ledger
   says)`. That expression lives in the model, and everything that needs the
   figure asks the model: a second sum of `amount_paid` is a second answer. */

check('the money rule is the model\'s, and it is written once',
    /PAID_SQL\s*=/.test(model)
    && /function paidAmount\(\)/.test(model)
    && /function balanceDue\(\)/.test(model)
    && /balance_amount/.test(model));

const paidSums = [...controller.matchAll(/(?:sum|selectRaw)\([^)]*amount_paid/g)].length;
check('the controller never sums the opening figure on its own', paidSums === 0, `${paidSums} occurrence(s)`);

check('the listing aggregate and the row rule are the same expression',
    /paidSql\(\)/.test(controller)
    && /withPaid\(\)/.test(controller)
    && /balance_amount/.test(model)
    && /total_amount - 0\.01/.test(controller));

check('the balance is written from the rule, never the other way round',
    /balance_amount['"\s]*=>/.test(controller)
    && /paidAmount\(\)/.test(controller)
    && !/amount_paid\s*=\s*[^;]*balance_amount/.test(controller));

/* "Paid" and "part paid" are questions about the money, not the stored word: a
   bill marked paid a year ago whose payment was reversed is not paid. */
check('the payment filter asks the money, not the status word',
    /'nothing'\s*=>/.test(controller) && /'partial'\s*=>/.test(controller) && /'paid'\s*=>/.test(controller)
    && !/status',\s*'paid'/.test(controller)
    && !/where\('status',\s*'partial'\)/.test(controller));

/* ------------------------------------------------- one conversion per order */

const linkAssignments = [...controller.matchAll(/converted_invoice_id\s*=\s*([^;]+);/g)].map(m => m[1].trim());

check('exactly one place links an order to its bill, and one place clears it',
    linkAssignments.length === 2
    && linkAssignments.filter(value => value === 'null').length === 1
    && linkAssignments.some(value => /\$bill|\$copy|\$invoice/.test(value)),
    linkAssignments.join(' | '));

check('the second conversion is refused with a message naming the bill',
    /->convert|convert\(/i.test(controller)
    && /has become|was already billed|already been billed|convertedInvoice\?->invoice_number/i.test(controller));

check('the conversion takes the row lock before it copies',
    /lockForUpdate\(\)/.test(controller) && controller.indexOf('lockForUpdate') < controller.indexOf('function convert') + 2000);

check('deleting the bill re-opens the order it came from',
    /converted_invoice_id\s*=\s*null/.test(controller) && /'approved'/.test(controller));

/* ------------------------------------------------------------- the ledger
   A bill is a payable because one row in the vendor ledger says so. One writer,
   one category, and the payment rows are keyed to the bill. */

check('the purchase side writes its ledger row in one service',
    /entry_category['"]\s*=>\s*['"]bill['"]/.test(ledger)
    && !/entry_category['"]\s*=>\s*['"]bill['"]/.test(controller));

check('the posted row carries the vendor\'s number and the due date',
    /vendor_bill_number/.test(ledger) && /'due_date'\s*=>/.test(ledger));

check('a payment against a bill is keyed to the bill',
    /'purchase_invoice_id'\s*=>/.test(controller)
    && /purchase_invoices\.id/.test(model));

check('the record page shows the ledger for a bill and not for an order',
    /@if\s*\(!\s*\$isOrder\)[\s\S]*?purchase_invoices\.partials\.ledger[\s\S]*?@endif/
        .test((viewText['resources/views/purchase_invoices/show.blade.php'] || '').replace(/\{\{--[\s\S]*?--\}\}/g, '')));

/* ------------------------------------------------- the sweep and the row menu
   A sweep may only do what a person could do one row at a time. */

const bulkConst = (controller.match(/BULK_ACTIONS\s*=\s*\[([\s\S]*?)\];/) || [])[1] || '';
const bulkKeys = [...bulkConst.matchAll(/'([a-z_]+)'\s*=>/g)].map(m => m[1]);

check('the sweep is a named set of actions, validated at the door',
    bulkKeys.length >= 3 && /Rule::in\(array_keys\(\$this->bulkActions/.test(controller));

check('the sweep offers each list its own verbs',
    /'advance'/.test(bulkConst) && /'approve'/.test(bulkConst) && /function bulkActions\(/.test(controller)
    && /unset\(\$actions\['approve'\]\)/.test(controller));

check('the sweep refuses a row it cannot honestly change',
    /status !== 'draft'/.test(controller)
    && /ledgerPaid\(\)\s*>\s*0\.01/.test(controller)
    && /isSuperseded\(\)/.test(controller));

check('the bulk bar and the row menu are wired to the same route',
    (viewText['resources/views/purchase_invoices/index.blade.php'] || '').includes('.bulk')
    && /purchaseBulkForm/.test(js)
    && /name="ids\[\]"/.test(viewText['resources/views/purchase_invoices/index.blade.php'] || ''));

/* ------------------------------------------------------- screens and routes */

const routeNames = new Set([...routes.matchAll(/->name\('([^'{$]+)'\)/g)].map(m => m[1]));

for (const m of routes.matchAll(/Route::resource\('([a-z-]+)'/g)) {
    for (const action of ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']) {
        routeNames.add(`${m[1]}.${action}`);
    }
}

for (const prefix of ['purchase-orders', 'purchase-bills']) {
    for (const suffix of ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy',
        'print', 'status', 'convert', 'bulk', 'export', 'gstExport', 'payments.store',
        'saved-views.store', 'saved-views.destroy']) {
        routeNames.add(`${prefix}.${suffix}`);
    }
}

const usedRoutes = new Set();
for (const text of Object.values(viewText)) {
    for (const m of text.matchAll(/route\('([^']+)'/g)) usedRoutes.add(m[1]);
    for (const m of text.matchAll(/route\(\$\w+\s*\.\s*'\.([a-zA-Z.\-]+)'/g)) {
        usedRoutes.add(`purchase-orders.${m[1]}`);
        usedRoutes.add(`purchase-bills.${m[1]}`);
    }
}

const missingRoutes = [...usedRoutes].filter(name => !routeNames.has(name)).sort();

check('every route a purchase view names exists',
    missingRoutes.length === 0, missingRoutes.join(', '));

const exportAt = routes.indexOf('/export');
const showAt = routes.indexOf("'/purchase-orders/{purchaseInvoice}'");
check('the vocabulary routes come before the routes that take an id',
    exportAt > 0 && showAt > exportAt && /saved-views/.test(routes));

/* ------------------------------------------------------------ the vocabulary */

check('the statuses are the model\'s, not a list in a view',
    /function statusOptions\(\)/.test(model)
    && !/draft'\s*=>\s*'Draft'[\s\S]{0,200}received/.test(allViews)
    && /statusOptions/.test(controller));

check('a purchase document is an order or a bill, never a proforma or a tax invoice',
    /TYPE_ORDER\s*=\s*'order'/.test(model) && /TYPE_BILL\s*=\s*'bill'/.test(model)
    && !/proforma|tax invoice/i.test(model.replace(/\/\*[\s\S]*?\*\//g, '')));

check('one ageing vocabulary, shared by the model and the filters',
    /function ageingBucket\(/.test(model)
    && (model.match(/ageingBucket\(/) || []).length >= 1
    && !/ageingBucket/.test(filtersService) || /ageingBucket/.test(model));

check('overdue is derived from the due date and the money',
    /function isOverdue\(/.test(model) && /function daysOverdue\(/.test(model)
    && /due_date/.test(model)
    && !/'status'\s*=\s*'overdue'/.test(model));

check('the filter vocabulary lives in one service, read by the screen and the files',
    /function fromRequest\(/.test(filtersService)
    && /function applied\(/.test(filtersService)
    && /function labels\(/.test(filtersService)
    && /PurchaseInvoiceFilters/.test(controller)
    && /fromRequest/.test(controller)
    && /filterLabels/.test(controller));

/* --------------------------------------------------------------- the form
   The preview is only useful if it computes what the server will store: the
   script's arithmetic is the server's, and every element it writes to exists. */

const form = viewText['resources/views/purchase_invoices/form.blade.php'] || '';
const jsIds = new Set([
    ...[...js.matchAll(/getElementById\('([A-Za-z0-9_]+)'\)/g)].map(m => m[1]),
    ...[...js.matchAll(/setText\('([A-Za-z0-9_]+)'/g)].map(m => m[1]),
    ...[...js.matchAll(/setRow\('([A-Za-z0-9_]+)',\s*'([A-Za-z0-9_]+)'/g)].flatMap(m => [m[1], m[2]]),
]);
const formIds = new Set([...form.matchAll(/id="([A-Za-z0-9_]+)"/g)].map(m => m[1]));
const orphans = [...jsIds].filter(id => !formIds.has(id) && !allViews.includes(`id="${id}"`)).sort();

check('every field the money preview writes to is on the form',
    orphans.length === 0, orphans.join(', '));

check('the preview is fed the ledger payments it cannot see itself',
    /data-ledger-paid/.test(form) && /data-ledger-paid/.test(js)
    && /ledgerPaid/.test(form));

check('the form hands the script the vendor snapshot fields, not a second list',
    /data-snapshot-fields/.test(form) && /vendorSnapshotFields/.test(controller)
    && /vendorSnapshotFields/.test(form)
    && /(snapshot-fields|dataset\.snapshotFields|['"]snapshotFields['"])/.test(js));

check('a bill raised from an order cannot be re-pointed at another order',
    !/name="purchase_order_id"/.test(form) || /readonly/.test(form));

/* ------------------------------------------------------------------ docs */

check('the module is written down',
    exists('docs/purchase-management.md')
    && /purchase order/i.test(read('docs/purchase-management.md'))
    && /vendor ledger/i.test(read('docs/purchase-management.md')));

console.log('');
console.log(`purchases: ${passed} passed, ${failed} failed`);
if (failed) process.exitCode = 1;
