/* Disposable Chromium coverage for non-blocking shipment payment advice. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { chromium } = require('playwright-core');
const base = new URL(process.env.SHIPMENT_PAYMENT_BROWSER_BASE_URL || 'http://127.0.0.1:8155');
if (!['127.0.0.1', 'localhost', '[::1]'].includes(base.hostname)) throw new Error('Use a disposable loopback server only.');
for (const key of ['SHIPMENT_PAYMENT_BROWSER_PASSWORD', 'CHROMIUM_EXECUTABLE_PATH']) if (!process.env[key]) throw new Error(`Missing ${key}`);
const fixture = JSON.parse(fs.readFileSync('.work/shipment-payment-warning-fixture.json', 'utf8'));
const url = path => new URL(path, base).href;
async function visit(page, path) {
    const response = await page.goto(url(path), { waitUntil: 'domcontentloaded' });
    assert(response && response.ok(), `${path}: HTTP ${response?.status()}`);
}
async function login(page) {
    await visit(page, '/login');
    await page.locator('[name="email"]').fill('shipment-payment-browser@example.test');
    await page.locator('[name="password"]').fill(process.env.SHIPMENT_PAYMENT_BROWSER_PASSWORD);
    await Promise.all([page.waitForURL(current => !current.pathname.startsWith('/login')), page.locator('[type="submit"]').click()]);
}
async function checkWarning(page, number, width) {
    const warning = page.locator('.shipment-balance-warning[role="status"]');
    assert(await warning.isVisible(), `Payment warning is visible at ${width}px`);
    assert.match(await warning.innerText(), new RegExp(number));
    assert.match(await warning.innerText(), /outstanding Proforma balance/i);
    assert.match(await warning.innerText(), /Accounts/i);
    assert.doesNotMatch(await warning.innerText(), /75\.00/, 'Operations-only users do not receive invoice amounts');
    assert(await warning.evaluate(el => {
        const rect = el.getBoundingClientRect();
        return rect.left >= -1 && rect.right <= window.innerWidth + 1 && el.scrollWidth <= el.clientWidth + 1;
    }), `Warning fits the ${width}px viewport`);
    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `No overflow at ${width}px`);
    assert.equal(await page.locator('.ma-overlay').count(), 0, 'Payment advice must not open a blocking dialog');
}
async function main() {
    const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_EXECUTABLE_PATH, headless: true,
        args: ['--no-sandbox', '--disable-dev-shm-usage'] });
    const errors = [];
    try {
        for (const width of [1440, 768, 390, 320]) {
            const context = await browser.newContext({ viewport: { width, height: 900 } });
            const page = await context.newPage();
            page.on('pageerror', error => errors.push(error.message));
            await login(page);
            const row = fixture[String(width)];
            await visit(page, `/shipments?search=${encodeURIComponent(row.number)}`);
            await page.locator(`[data-shipment-status-open][data-shipment-id="${row.id}"]`).click();
            const modal = page.locator('#shipmentStatusModal');
            await modal.locator('[name="status"]').selectOption('picked_up');
            await modal.locator('[name="remarks"]').fill('Fictional pickup');
            await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), modal.getByRole('button', { name: 'Save status' }).click()]);
            await checkWarning(page, row.number, width);
            assert.match(await page.locator(`[data-shipment-status-open][data-shipment-id="${row.id}"]`).locator('..').innerText(), /picked up/i);
            await context.close();
        }
        const context = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 900 } });
        const page = await context.newPage();
        await login(page);
        const row = fixture.nojs;
        await visit(page, `/shipments/${row.id}?tab=tracking`);
        assert.equal(await page.evaluate(() => typeof window.MasterModal), 'undefined');
        const form = page.locator('form.history-form');
        await form.locator('[name="status"]').selectOption('picked_up');
        await form.locator('[name="remarks"]').fill('Fictional pickup');
        await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), form.getByRole('button', { name: 'Add History' }).click()]);
        await checkWarning(page, row.number, 390);
        await context.close();
        assert.deepEqual(errors, []);
        console.log('PASS payment warning, authorized tracking and responsive/no-JavaScript access');
    } finally { await browser.close(); }
}
main().catch(error => { console.error(error); process.exitCode = 1; });
