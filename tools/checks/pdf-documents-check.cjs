'use strict';

const fs = require('fs');
const path = require('path');
const ROOT = path.resolve(__dirname, '..', '..');
const read = relative => fs.readFileSync(path.join(ROOT, relative), 'utf8');
let passed = 0;
let failed = 0;
const check = (name, ok, detail = '') => {
    const mark = ok ? 'ok  ' : 'FAIL';
    console.log(`  ${mark} ${name}${ok || !detail ? '' : ` -> ${detail}`}`);
    if (ok) passed++;
    else failed++;
};
const plain = text => text.replace(/\/\*[\s\S]*?\*\//g, '');

const payslipView = read('resources/views/employees/payslip-pdf.blade.php');
const payslipPartial = read('resources/views/employees/partials/payslip.blade.php');
const payslipCss = plain(read('public/assets/css/payslip.css'));
const statementView = read('resources/views/cashflows/statement-pdf.blade.php');
const statementPublicView = read('resources/views/statements/public.blade.php');
const statementPartial = read('resources/views/cashflows/partials/statement.blade.php');
const statementCss = plain(read('public/assets/css/statement.css'));
const portalStatementView = read('resources/views/client_portal/statements/index.blade.php');
const invoiceView = read('resources/views/sales_invoices/print.blade.php');
const invoiceCss = plain(read('public/assets/css/sales-invoices-print.css'));
const cashflowView = read('resources/views/cashflows/pdf.blade.php');
const cashflowCss = plain(read('public/assets/css/cashflows-pdf.css'));
const shipmentViews = [
    read('resources/views/shipments/print/packing-list.blade.php'),
    read('resources/views/shipments/print/delivery-challan.blade.php'),
    read('resources/views/shipments/print/summary.blade.php'),
];
const shipmentToolbar = read('resources/views/shipments/print/partials/toolbar.blade.php');
const shipmentCss = plain(read('public/assets/css/shipment-print.css'));
const documentCss = plain(read('public/assets/css/document-print.css'));
const payslipDocument = read('app/Services/PayslipDocument.php');
const statementController = read('app/Http/Controllers/PartyStatementController.php');
const cashflowController = read('app/Http/Controllers/CashflowController.php');
const shipmentController = read('app/Http/Controllers/ShipmentController.php');
const guideline = read('docs/ui-design-guidelines.md');
const shippingMarkView = read('resources/views/shipments/shipping-mark.blade.php');
const stickerSheetView = read('resources/views/shipments/stickers.blade.php');
const shippingMarkCss = plain(read('public/assets/css/shipping-mark.css'));

const standaloneA4Views = [
    payslipView,
    statementView,
    statementPublicView,
    portalStatementView,
    invoiceView,
    cashflowView,
    ...shipmentViews,
];
const loadsSharedPrint = view => /assets\/css\/document-print\.css/.test(view);
const hasInterLink = view => /fonts\.googleapis\.com\/css2\?family=Inter:wght@400;500;600;700/.test(view);
check('all A4 PDF/print views load the shared document stylesheet and Inter',
    standaloneA4Views.every(loadsSharedPrint)
    && [payslipView, statementView, statementPublicView, invoiceView, cashflowView, ...shipmentViews].every(hasInterLink));

check('the shared stylesheet defines one paper, type, and action system',
    /--pdf-font-family:\s*"Inter"/.test(documentCss)
    && /--pdf-ink:/.test(documentCss)
    && /--pdf-accent:/.test(documentCss)
    && /--pdf-sheet-width:\s*210mm/.test(documentCss)
    && /font-size:\s*8\.25pt/.test(documentCss)
    && /font-size:\s*9\.5pt/.test(documentCss)
    && /\.pdf-sheet/.test(documentCss)
    && /\.pdf-action--primary/.test(documentCss)
    && /min-height:\s*40px/.test(documentCss)
    && /:focus-visible/.test(documentCss)
    && /@page\s*\{\s*size:\s*A4 portrait;\s*margin:\s*12mm/.test(documentCss));

check('invoice, payslip and statement use the same A4 paper shell',
    /class="page pdf-sheet"/.test(invoiceView)
    && /\$ctx === 'pdf' \? 'pdf-sheet'/.test(payslipPartial)
    && /'pdf-sheet' : \(\$ctx === 'portal' \? 'pdf-print-sheet'/.test(statementPartial)
    && /class="pdf-preview invoice-standalone"/.test(invoiceView)
    && /class="pdf-preview ps-standalone"/.test(payslipView)
    && /class="pdf-preview stmt-standalone stmt-print"/.test(statementView));

check('the invoice viewer uses shared, accessible theme actions',
    /class="pdf-toolbar pdf-toolbar--spread"/.test(invoiceView)
    && /pdf-action pdf-action--secondary/.test(invoiceView)
    && /pdf-action pdf-action--primary/.test(invoiceView)
    && !/class="master-btn/.test(invoiceView));

check('PDF fallback guidance is consistent and user-facing',
    /Use the print dialog and choose Save as PDF to download this payslip/.test(payslipDocument)
    && /Use the print dialog and choose Save as PDF to download this statement/.test(statementController)
    && /Use the print dialog and choose Save as PDF to download this report/.test(cashflowController)
    && /Use the print dialog and choose Save as PDF to download this shipment document/.test(shipmentController)
    && !/Install barryvdh\/laravel-dompdf/.test(payslipDocument + statementController + cashflowController + shipmentController));

check('the guideline makes the shared PDF foundation mandatory for future documents',
    /document-print\.css/.test(guideline)
    && /body\.pdf-preview/.test(guideline)
    && /\.pdf-sheet/.test(guideline)
    && /Specialized labels/.test(guideline));

const cssFiles = [documentCss, payslipCss, statementCss, invoiceCss, cashflowCss, shipmentCss];
const weightCeiling = css => {
    const weights = [...css.matchAll(/font-weight\s*:\s*(\d+)\b/g)].map(match => Number(match[1]));
    return weights.length > 0 && Math.max(...weights) <= 700;
};
check('every shared and document-specific PDF stylesheet stays within weight 700',
    cssFiles.every(weightCeiling));

check('payslip, statement and invoice retain readable aligned print typography',
    /\.payslip\s*\{[^}]*font-family:\s*var\(--ps-font\)[^}]*font-size:\s*13px[^}]*line-height:\s*1\.5/.test(payslipCss)
    && /body\.stmt-standalone \.stmt\s*\{[^}]*--stmt-text:\s*var\(--pdf-ink, #1b2a41\)[^}]*background:\s*#fff/.test(statementCss)
    && /\.pdf-print-sheet\s*\{[^}]*--stmt-text: var\(--pdf-ink\) !important/.test(documentCss)
    && /\.items td\.right,[\s\S]*?text-align:\s*right/.test(invoiceCss)
    && /font-size:\s*9\.5pt/.test(documentCss)
    && /\.ps-table thead\s*\{\s*display:\s*table-header-group/.test(payslipCss)
    && /\.stmt-table thead\s*\{\s*display:\s*table-header-group/.test(statementCss));

check('invoice totals remain currency-correct and tax labels do not double-count',
    /CommonHelper::amount\(\(float\) \$amount, \$currency\)/.test(invoiceView)
    && !/CommonHelper::indianCurrency/.test(invoiceView)
    && /Taxable value/.test(invoiceView)
    && /Item discounts/.test(invoiceView)
    && /Invoice discount/.test(invoiceView)
    && /<td>CGST<\/td>/.test(invoiceView)
    && /<td>SGST<\/td>/.test(invoiceView)
    && /<td>IGST<\/td>/.test(invoiceView)
    && /Received to date/.test(invoiceView)
    && /Balance due/.test(invoiceView));

const invoiceSectionOrder = [
    'class="invoice-top"',
    'class="invoice-facts"',
    'class="parties"',
    'class="items"',
    'class="invoice-bottom"',
    'class="terms-panel"',
].map(marker => invoiceView.indexOf(marker));
check('invoice follows the reference hierarchy without inventing a subject',
    invoiceSectionOrder.every((position, index) => position > -1 && (index === 0 || position > invoiceSectionOrder[index - 1]))
    && /Bill to/.test(invoiceView)
    && /Ship to/.test(invoiceView)
    && /Bank details/.test(invoiceView)
    && /Authorised Signatory/.test(invoiceView)
    && !/Subject/.test(invoiceView));

check('discounted invoice line amounts reconcile to the stored tax and taxable totals',
    /largest-remainder allocation/.test(invoiceView)
    && /\$remainingCents = \$targetCents - array_sum\(\$allocatedCents\)/.test(invoiceView)
    && /\$allocatedCents\[\$order\[\$cent % \$count\]\]\+\+/.test(invoiceView)
    && /\$lineTaxableAmounts = \$allocateCents\(\(float\) \$invoice->taxable_amount, \$taxableWeights\)/.test(invoiceView)
    && /\$lineCgstAmounts = \$allocateCents\(\(float\) \$invoice->cgst_amount, \$cgstWeights\)/.test(invoiceView)
    && /\$lineSgstAmounts = \$allocateCents\(\(float\) \$invoice->sgst_amount, \$sgstWeights\)/.test(invoiceView)
    && /\$lineIgstAmounts = \$allocateCents\(\(float\) \$invoice->igst_amount, \$igstWeights\)/.test(invoiceView)
    && /\$lineDiscountAmount = \$lineTaxableAmountsAvailable/.test(invoiceView)
    && /\$item->taxable_amount - \$lineTaxableAmount/.test(invoiceView)
    && /'discount_amount' => \$lineDiscountAmount/.test(invoiceView)
    && /'taxable_amount' => \$lineTaxableAmountsAvailable/.test(invoiceView)
    && /'tax_amount' => \$lineTaxesAvailable/.test(invoiceView)
    && /\$money\(\$line\['tax_amount'\]\)/.test(invoiceView)
    && /Total discount/.test(invoiceView)
    && /lineFiguresUnavailable/.test(invoiceView)
    && !/\$money\(\$item->(?:cgst|sgst|igst)_amount\)/.test(invoiceView));

check('cashflow reports use the shared paper shell but keep their landscape data need',
    /class="pdf-preview"/.test(cashflowView)
    && /class="pdf-sheet pdf-sheet--landscape"/.test(cashflowView)
    && /@page\s*\{\s*size:\s*A4 landscape;\s*margin:\s*12mm/.test(cashflowCss));

check('shipment paperwork shares the A4 shell and themed toolbar',
    shipmentViews.every(view => /class="pdf-preview"/.test(view) && /class="print-sheet pdf-sheet"/.test(view))
    && /pdf-action pdf-action--primary/.test(shipmentToolbar)
    && /pdf-action pdf-action--secondary/.test(shipmentToolbar));

check('special shipping labels keep their exact custom page geometry',
    /size:\s*85mm 130mm/.test(shippingMarkCss)
    && !loadsSharedPrint(shippingMarkView)
    && !loadsSharedPrint(stickerSheetView));

console.log('');
console.log(`pdf documents: ${passed} passed, ${failed} failed`);
if (failed) process.exitCode = 1;
