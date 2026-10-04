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
const payslipCss = plain(read('public/assets/css/payslip.css'));
const statementView = read('resources/views/cashflows/statement-pdf.blade.php');
const statementCss = plain(read('public/assets/css/statement.css'));
const invoiceView = read('resources/views/sales_invoices/print.blade.php');
const invoiceCss = plain(read('public/assets/css/sales-invoices-print.css'));
const payslipDocument = read('app/Services/PayslipDocument.php');
const statementController = read('app/Http/Controllers/PartyStatementController.php');
const guideline = read('docs/ui-design-guidelines.md');

const hasInterLink = view => /fonts\.googleapis\.com\/css2\?family=Inter:wght@400;500;600;700/.test(view);
check('all three standalone documents load the shared Inter family',
    [payslipView, statementView, invoiceView].every(hasInterLink));

check('PDF fallback instructions explain Save as PDF without leaking package internals',
    /Use the print dialog and choose Save as PDF to download this payslip/.test(payslipDocument)
    && /Use the print dialog and choose Save as PDF to download this statement/.test(statementController)
    && !/Install barryvdh\/laravel-dompdf/.test(payslipDocument + statementController));

check('the PDF guideline defines readable A4 typography and paper surfaces',
    /## Print and PDF documents/.test(guideline)
    && /A4 portrait with 12 mm margins/.test(guideline)
    && /white paper surface/.test(guideline)
    && /tabular numerals/.test(guideline));

const weightCeiling = (name, css) => {
    const weights = [...css.matchAll(/font-weight\s*:\s*(\d+)\b/g)].map(match => Number(match[1]));
    return weights.length > 0 && Math.max(...weights) <= 700;
};
check('payslip, statement and invoice styles stay within the 700 weight cap',
    weightCeiling('payslip', payslipCss)
    && weightCeiling('statement', statementCss)
    && weightCeiling('invoice', invoiceCss));

check('payslip uses Inter, a readable text scale and A4 print margins',
    /\.payslip\s*\{[^}]*font-family:\s*var\(--ps-font\)[^}]*font-size:\s*13px[^}]*line-height:\s*1\.5/.test(payslipCss)
    && /@page\s*\{\s*size:\s*A4 portrait;\s*margin:\s*12mm/.test(payslipCss)
    && /\.ps-notice,[\s\S]*?display:\s*none !important/.test(payslipCss)
    && /\.ps-table thead\s*\{\s*display:\s*table-header-group/.test(payslipCss));

check('statement PDFs always print as light A4 paper, even under dark mode',
    /class="stmt-standalone stmt-print"/.test(statementView)
    && /body\.stmt-standalone:not\(\.stmt-print\)/.test(statementCss)
    && /body\.stmt-standalone \.stmt\s*\{[^}]*--stmt-text:\s*#1a2540[^}]*background:\s*#fff/.test(statementCss)
    && /@page\s*\{\s*size:\s*A4 portrait;\s*margin:\s*12mm/.test(statementCss));

check('invoice prints currency-correct, itemized taxable values and GST heads',
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

check('invoice print is A4 with right-aligned amounts and no screen chrome',
    /@page\s*\{\s*size:\s*A4 portrait;\s*margin:\s*12mm/.test(invoiceCss)
    && /\.items td\.right,[\s\S]*?text-align:\s*right/.test(invoiceCss)
    && /\.toolbar,[\s\S]*?\.no-print\s*\{\s*display:\s*none !important/.test(invoiceCss)
    && /\.page\s*\{[^}]*width:\s*auto !important[^}]*min-height:\s*0 !important/.test(invoiceCss));

console.log('');
console.log(`pdf documents: ${passed} passed, ${failed} failed`);
if (failed) process.exitCode = 1;
