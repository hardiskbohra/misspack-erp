/* Live purchase conversion checks: disposable loopback test server only.
 * Set PURCHASE_TEST_BASE_URL, PURCHASE_TEST_EMAIL, PURCHASE_TEST_PASSWORD,
 * PURCHASE_TEST_ORDERS (JSON object by width) and CHROMIUM_EXECUTABLE_PATH. */
'use strict';
const assert = require('node:assert/strict');
const { chromium } = require('playwright-core');
const base = new URL(process.env.PURCHASE_TEST_BASE_URL || 'http://127.0.0.1:8137');
if (!['127.0.0.1', 'localhost', '[::1]'].includes(base.hostname)) throw new Error('Use a disposable loopback server only.');
for (const key of ['PURCHASE_TEST_EMAIL', 'PURCHASE_TEST_PASSWORD', 'PURCHASE_TEST_ORDERS', 'CHROMIUM_EXECUTABLE_PATH']) {
    if (!process.env[key]) throw new Error(`Missing ${key}`);
}
const orders = JSON.parse(process.env.PURCHASE_TEST_ORDERS);
async function main() {
    const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_EXECUTABLE_PATH, headless: true,
        args: ['--no-sandbox', '--disable-dev-shm-usage'] });
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(new URL('/login', base).href, { waitUntil: 'domcontentloaded' });
    await page.locator('[name="email"]').fill(process.env.PURCHASE_TEST_EMAIL);
    await page.locator('[name="password"]').fill(process.env.PURCHASE_TEST_PASSWORD);
    await Promise.all([page.waitForURL(url => !url.pathname.startsWith('/login')), page.locator('[type="submit"]').click()]);
    for (const width of [1440, 390]) {
        await page.setViewportSize({ width, height: 900 });
        const orderPath = `/purchase-invoices/${orders[width]}`;
        await page.goto(new URL(orderPath, base).href, { waitUntil: 'domcontentloaded' });
        const form = page.locator('#purchasePartialForm');
        assert.equal(await form.count(), 1);
        const cash = form.locator('tr').filter({ hasText: 'Cash advance' }).locator('[data-purchase-transfer]');
        const bank = form.locator('tr').filter({ hasText: 'Bank advance' }).locator('[data-purchase-transfer]');
        assert.equal(Number(await cash.inputValue()), 0);
        assert.equal(Number(await bank.inputValue()), 0);
        await page.locator('#purchaseConversionValue').fill('2500');
        await cash.fill('2000');
        await bank.fill('500');
        assert.match(await page.locator('#purchaseConversionSummary').innerText(), /Bill INR 2,500\.00.*Selected advances INR 2,500\.00.*Remaining PO INR 7,500\.00/);
        await page.locator('#purchaseConversionMode').selectOption('percent');
        assert.equal(Number(await page.locator('#purchaseConversionValue').inputValue()), 25);
        assert.equal(await page.locator('#purchaseConversionValue').getAttribute('max'), '100');
        await page.locator('#purchaseConversionMode').selectOption('amount');
        assert.equal(Number(await page.locator('#purchaseConversionValue').inputValue()), 2500);
        await bank.fill('600');
        assert.match(await page.locator('#purchaseConversionValue').evaluate(el => el.validationMessage), /cannot exceed/i);
        await bank.fill('500');
        assert.equal(await page.locator('#purchaseConversionValue').evaluate(el => el.validationMessage), '');
        const button = form.getByRole('button', { name: 'Create linked bill' });
        await button.scrollIntoViewIfNeeded();
        const box = await button.boundingBox();
        assert.ok(box && box.x >= 0 && box.x + box.width <= width, `${width}px submit button fits`);
        await Promise.all([page.waitForURL(url => url.pathname !== orderPath), button.click()]);
        const billPath = new URL(page.url()).pathname;
        assert.match(await page.locator('body').innerText(), /Cash Purchase Bill/);
        assert.match(await page.locator('body').innerText(), /Paid\s+₹2,500(?:\.00)?/);
        await page.goto(new URL(orderPath, base).href, { waitUntil: 'domcontentloaded' });
        const detail = await page.locator('body').innerText();
        assert.match(detail, /Remaining PO total\s+₹7,500(?:\.00)?/);
        assert.match(detail, /Advance paid\s+₹500(?:\.00)?/);
        assert.match(detail, /Still open\s+₹7,000(?:\.00)?/);
        assert.match(detail, /Vendor payment #/);
        await page.goto(new URL(orderPath + '/print', base).href, { waitUntil: 'domcontentloaded' });
        const print = await page.locator('body').innerText();
        assert.match(print, /Original PO total\s+₹10,000(?:\.00)?/);
        assert.match(print, /Less linked bills\s+-₹2,500(?:\.00)?/);
        assert.match(print, /Remaining PO total\s+₹7,500(?:\.00)?/);
        assert.match(print, /Balance due\s+₹7,000(?:\.00)?/);
        await page.goto(new URL(billPath, base).href, { waitUntil: 'domcontentloaded' });
        await page.locator('#supplierRef').fill('SUP-' + width);
        await Promise.all([page.waitForLoadState('domcontentloaded'), page.getByRole('button', { name: 'Save supplier reference' }).click()]);
        await page.waitForURL(url => url.pathname === billPath);
        assert.equal(await page.locator('#supplierRef').inputValue(), 'SUP-' + width);
        // The fictional fixture's vendor is #1. Both the wholly transferred
        // and the split source show their destination without edit/delete.
        await page.goto(new URL('/vendors/1?tab=money', base).href, { waitUntil: 'domcontentloaded' });
        for (const label of ['Cash advance', 'Bank advance']) {
            const row = page.locator('tr').filter({ hasText: label }).filter({ has: page.locator(`a[href$="${billPath}"]`) });
            assert.equal(await row.count(), 1, `${width}px ${label} has bill provenance`);
            assert.match(await row.innerText(), /Linked conversion/);
            await row.locator('.master-dropdown-toggle').click();
            assert.equal(await row.getByRole('button', { name: /^Delete$/ }).count(), 0);
            assert.equal(await row.getByRole('link', { name: /^Edit$/ }).count(), 0);
        }
        const rmbOrderPath = `/purchase-invoices/${orders['rmb' + width]}`;
        await page.goto(new URL(rmbOrderPath, base).href, { waitUntil: 'domcontentloaded' });
        assert.match(await page.locator('#purchasePartialConversion').innerText(), /full PO quantities.*round off/s);
        await page.locator('#purchaseConversionValue').fill('9000');
        await Promise.all([page.waitForURL(url => url.pathname !== rmbOrderPath), page.getByRole('button', { name: 'Create linked bill' }).click()]);
        let row = page.locator('table.si-lines tbody tr').first();
        assert.match(await row.locator('[data-label="Qty"]').innerText(), /10000/);
        assert.match(await row.locator('[data-label="Rate"]').innerText(), /¥0\.90/);
        await page.goto(new URL(rmbOrderPath, base).href, { waitUntil: 'domcontentloaded' });
        await page.locator('#purchaseConversionKind').selectOption('bill');
        await page.locator('#purchaseConversionValue').fill('12000');
        await Promise.all([page.waitForURL(url => url.pathname !== rmbOrderPath), page.getByRole('button', { name: 'Create linked bill' }).click()]);
        const rmbBillPath = new URL(page.url()).pathname;
        const billText = await page.locator('body').innerText();
        row = page.locator('table.si-lines tbody tr').first();
        assert.match(await row.locator('[data-label="Qty"]').innerText(), /10000/);
        assert.match(await row.locator('[data-label="Rate"]').innerText(), /¥1\.20/);
        assert.match(billText, /Grand total\s+¥12,000(?:\.00)?/);
        await page.goto(new URL(rmbBillPath + '/print', base).href, { waitUntil: 'domcontentloaded' });
        const rmbPrint = await page.locator('body').innerText();
        assert.match(rmbPrint, /10,000/);
        assert.match(rmbPrint, /¥1\.20/);
        assert.match(rmbPrint, /Grand total\s+¥12,000(?:\.00)?/);

        const pricesPath = `/purchase-invoices/${orders['priced' + width]}`;
        const checkProductLines = async () => {
            const rows = page.locator('table.si-lines tbody tr');
            assert.equal(await rows.count(), 3, `${width}px all three PO lines appear`);
            for (const [i, rate] of ['₹0.50', '₹1', '₹1.50'].entries()) {
                const line = rows.nth(i);
                assert.match(await line.locator('[data-label="Product"]').innerText(), new RegExp(`Product ${i + 1}`));
                assert.match(await line.locator('[data-label="Qty"]').innerText(), /1000/);
                assert.equal((await line.locator('[data-label="Rate"]').innerText()).trim(), rate);
            }
            assert.match(await page.locator('body').innerText(), /financial references/);
        };
        await page.goto(new URL(pricesPath, base).href, { waitUntil: 'domcontentloaded' });
        await page.locator('#purchaseConversionValue').fill('3000');
        await Promise.all([page.waitForURL(url => url.pathname !== pricesPath), page.getByRole('button', { name: 'Create linked bill' }).click()]);
        const pricedCashPath = new URL(page.url()).pathname;
        await checkProductLines();
        await page.goto(new URL(pricesPath, base).href, { waitUntil: 'domcontentloaded' });
        await page.locator('#purchaseConversionKind').selectOption('bill');
        await page.locator('#purchaseConversionValue').fill('3000');
        await Promise.all([page.waitForURL(url => url.pathname !== pricesPath), page.getByRole('button', { name: 'Create linked bill' }).click()]);
        const pricedBillPath = new URL(page.url()).pathname;
        await checkProductLines();
        await page.goto(new URL(pricedBillPath + '/print', base).href, { waitUntil: 'domcontentloaded' });
        const pricePrint = await page.locator('body').innerText();
        assert.match(pricePrint, /Product 1[\s\S]*1,000[\s\S]*₹0\.50/);
        assert.match(pricePrint, /Product 2[\s\S]*1,000[\s\S]*₹1/);
        assert.match(pricePrint, /Product 3[\s\S]*1,000[\s\S]*₹1\.50/);
        assert.match(pricePrint, /financial references/);
        for (const path of [pricedCashPath, pricedBillPath]) {
            const id = path.split('/').at(-1);
            await page.goto(new URL(`/packaging-lists/create?purchase_invoice=${id}`, base).href, { waitUntil: 'domcontentloaded' });
            assert.match(await page.locator('body').innerText(), /Enter actual physical quantities/);
            assert.match(await page.locator('body').innerText(), /No physical source balance/);
            const inputs = page.locator('.pl-carton-panels [data-item] [data-qty]');
            assert.equal(await inputs.count(), 3);
            for (const input of await inputs.all()) assert.equal(await input.inputValue(), '');
        }

        const roundPath = `/purchase-invoices/${orders['round' + width]}`;
        await page.goto(new URL(roundPath, base).href, { waitUntil: 'domcontentloaded' });
        await page.locator('#purchaseConversionValue').fill('333.333');
        await Promise.all([page.waitForURL(url => url.pathname !== roundPath), page.getByRole('button', { name: 'Create linked bill' }).click()]);
        const roundCashPath = new URL(page.url()).pathname;
        assert.match(await page.locator('body').innerText(), /Round off\s+₹0\.333/);
        assert.match(await page.locator('body').innerText(), /Grand total\s+₹333\.333/);
        await page.goto(new URL(roundCashPath + '/print', base).href, { waitUntil: 'domcontentloaded' });
        assert.match(await page.locator('body').innerText(), /Round off\s+₹0\.333/);
        await page.goto(new URL(roundPath, base).href, { waitUntil: 'domcontentloaded' });
        await page.locator('#purchaseConversionKind').selectOption('bill');
        await page.locator('#purchaseConversionValue').fill('666.667');
        await Promise.all([page.waitForURL(url => url.pathname !== roundPath), page.getByRole('button', { name: 'Create linked bill' }).click()]);
        const roundBillPath = new URL(page.url()).pathname;
        assert.match(await page.locator('body').innerText(), /Round off\s+-₹0\.333/);
        assert.match(await page.locator('body').innerText(), /Grand total\s+₹666\.667/);
        await page.goto(new URL(roundBillPath + '/print', base).href, { waitUntil: 'domcontentloaded' });
        assert.match(await page.locator('body').innerText(), /Round off\s+-₹0\.333/);
        console.log(`PASS ${width}px: manual payments, repeated PO lines and scaled cash/tax rates, ±0.333 visible round off, RMB and packing warnings`);
    }
    assert.deepEqual(errors, [], 'No uncaught browser JavaScript errors');
    await browser.close();
}
main().catch(error => { console.error(error); process.exit(1); });
