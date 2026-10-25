/* Run: node tools/checks/cash-conversion-check.cjs
 * Optional Chromium: PLAYWRIGHT_PATH=<playwright-core package>,
 * CHROMIUM_PATH=<binary>, or @sparticuz/chromium beside playwright-core.
 */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const source = fs.readFileSync(path.join(root, 'public/assets/js/sales-invoices.js'), 'utf8');
const view = fs.readFileSync(path.join(root, 'resources/views/sales_invoices/show.blade.php'), 'utf8');
let passed = 0;
function check(name, condition) {
    assert.ok(condition, name); passed++; console.log('  ok  ' + name);
}
check('PI card supplies remaining value, available receipts and currency', ['data-convertible=', 'data-received=', 'data-currency='].every(s => view.includes(s)));
check('server-rendered transfer defaults to the lesser of receipts and invoice value', view.includes("old('transfer_received', min(max($receivedAmount, 0), $invoice->cashConvertibleRemaining()))"));
check('failed requests preserve the explicitly chosen transfer', view.includes('data-preserve-transfer='));
check('record page explains receipt priority and retained PI advance', view.includes('Cash-mode / cash-account receipts are used first') && view.includes('Untransferred receipts stay as a PI advance'));

async function run() {
    let playwright;
    try { playwright = require(process.env.PLAYWRIGHT_PATH || 'playwright-core'); }
    catch (_) {
        console.log(`\ncash conversion UI: ${passed} passed; browser checks SKIPPED (install playwright-core and set PLAYWRIGHT_PATH)`); return;
    }
    let executablePath = process.env.CHROMIUM_PATH;
    if (!executablePath) {
        const chromium = await import(require('node:url').pathToFileURL(require.resolve('@sparticuz/chromium', {
            paths: [path.dirname(require.resolve(process.env.PLAYWRIGHT_PATH || 'playwright-core'))],
        })).href);
        executablePath = await chromium.default.executablePath();
    }
    const browser = await playwright.chromium.launch({ executablePath, headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage'] });
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        async function fixture({ remainder = 300000, received = 150000, amount = remainder, transfer = Math.min(received, remainder), preserve = false, jquery = false } = {}) {
            await page.goto('about:blank');
            await page.setContent(`<section id="cashInvoiceConversion" data-convertible="${remainder}" data-received="${received}" data-currency="INR">
                <select id="cashConversionMode"><option value="amount">Amount</option><option value="percent">Percent</option></select>
                <input id="cashConversionValue" type="number" min="0.01" step="0.01" required value="${amount}">
                <input id="cashTransferReceived" type="number" min="0" step="0.01" required data-preserve-transfer="${preserve}" value="${transfer}">
                <p id="cashTransferSummary"></p></section>`);
            if (jquery) {
                // Exercise the same event-binding branch used by the shared select.
                await page.evaluate(() => {
                    window.jQuery = node => ({ on: (event, handler) => node.addEventListener(event, handler) });
                    window.jQuery.fn = {};
                });
            }
            await page.addScriptTag({ content: source });
        }
        async function value(id) { return page.locator('#' + id).inputValue(); }
        async function maxTransfer() { return page.locator('#cashTransferReceived').getAttribute('max'); }
        for (const jquery of [false, true]) {
            await fixture({ jquery });
            check(`initial transfer is 150000, not the full 300000 (${jquery ? 'jQuery branch' : 'native'})`, await value('cashTransferReceived') === '150000.00');
            await page.locator('#cashConversionValue').fill('100000');
            check('100000 Cash Invoice automatically gets only 100000 paid', await value('cashTransferReceived') === '100000.00');
            check('100000 transfer is also the browser maximum', await maxTransfer() === '100000.00');
            check('live summary leaves 50000 with the PI', (await page.locator('#cashTransferSummary').textContent()).includes('50,000.00 INR stays with PI'));
            await page.locator('#cashConversionMode').selectOption('percent');
            check('percentage mode resets to 100 percent', await value('cashConversionValue') === '100');
            await page.locator('#cashConversionValue').fill('25');
            check('25 percent applies 75000 of the remaining 300000', await value('cashTransferReceived') === '75000.00' && await maxTransfer() === '75000.00');
            await page.locator('#cashTransferReceived').fill('0');
            await page.locator('#cashConversionValue').fill('50');
            check('an explicit zero transfer is never overwritten', await value('cashTransferReceived') === '0');
            await page.locator('#cashTransferReceived').fill('50000');
            await page.locator('#cashConversionValue').fill('75');
            check('an explicit partial transfer stays chosen on amount changes', await value('cashTransferReceived') === '50000');
            await page.locator('#cashConversionValue').fill('10');
            check('an oversized manual choice is invalid, not silently transferred', await maxTransfer() === '30000.00' && !await page.locator('#cashTransferReceived').evaluate(el => el.checkValidity()));
            await page.locator('#cashTransferReceived').fill('20000');
            check('correcting the manual transfer clears its validity error', await page.locator('#cashTransferReceived').evaluate(el => el.checkValidity()));
        }
        await fixture({ remainder: 200000, received: 50000 });
        await page.locator('#cashConversionMode').selectOption('percent');
        await page.locator('#cashConversionValue').fill('10');
        check('later conversions use the remaining PI base, not its original total', await value('cashTransferReceived') === '20000.00');
        await fixture({ received: 0 });
        check('no receipts means zero transferable money', await value('cashTransferReceived') === '0.00' && await maxTransfer() === '0.00');
        await fixture({ amount: 100000, transfer: 50000, preserve: true });
        check('a failed request preserves the chosen 50000', await value('cashTransferReceived') === '50000');
        await fixture({ amount: 100000, transfer: 150000, preserve: true });
        check('a failed oversized request remains visibly invalid', !await page.locator('#cashTransferReceived').evaluate(el => el.checkValidity()));
        await fixture({ remainder: 0.03, received: 0.03 });
        await page.locator('#cashConversionMode').selectOption('percent');
        await page.locator('#cashConversionValue').fill('33.33');
        check('percentage caps round to invoice cents', await value('cashTransferReceived') === '0.01');
        check('module initializes without browser errors', errors.length === 0);
    } finally { await browser.close(); }
    console.log(`\ncash conversion UI: ${passed} passed, 0 failed (source audit + Chromium)`);
}
run().catch(error => { console.error(error); process.exitCode = 1; });
