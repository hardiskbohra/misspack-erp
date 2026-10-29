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
        console.log(`PASS ${width}px: manual cash/bank split, validation, totals, print, supplier reference and protected vendor payments`);
    }
    assert.deepEqual(errors, [], 'No uncaught browser JavaScript errors');
    await browser.close();
}
main().catch(error => { console.error(error); process.exit(1); });
