/* Live browser regression on a DISPOSABLE local test instance only.
 * Requires playwright-core and Chromium, a freshly migrated SQLite test DB,
 * and a test admin. Set INVOICE_TEST_BASE_URL, INVOICE_TEST_EMAIL,
 * INVOICE_TEST_PASSWORD, and CHROMIUM_EXECUTABLE_PATH. This script creates and
 * edits test invoices; it must never run against production.
 */
'use strict';

const assert = require('node:assert/strict');
const { chromium } = require('playwright-core');

const base = new URL(process.env.INVOICE_TEST_BASE_URL || 'http://127.0.0.1:8135');
if (!['127.0.0.1', 'localhost', '[::1]'].includes(base.hostname)) throw new Error('Use a disposable loopback test server only.');
const email = process.env.INVOICE_TEST_EMAIL;
const password = process.env.INVOICE_TEST_PASSWORD;
if (!email || !password || !process.env.CHROMIUM_EXECUTABLE_PATH) throw new Error('Supply test credentials and Chromium path.');

async function main() {
    const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_EXECUTABLE_PATH, headless: true,
        args: ['--no-sandbox', '--disable-dev-shm-usage'] });
    const context = await browser.newContext();
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(new URL('/login', base).href, { waitUntil: 'domcontentloaded' });
    await page.locator('[name="email"]').fill(email);
    await page.locator('[name="password"]').fill(password);
    await Promise.all([page.waitForURL(url => !url.pathname.startsWith('/login')), page.locator('[type="submit"]').click()]);

    for (const width of [1440, 768, 390]) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto(new URL('/sales-invoices/create?type=proforma', base).href, { waitUntil: 'domcontentloaded' });
        const auto = page.getByRole('checkbox', { name: 'Automatically round the INR total' });
        const adjustment = page.locator('#roundOff');
        await auto.waitFor();
        assert.equal(await auto.isChecked(), true, `${width}: auto is the new INR default`);
        assert.equal(await adjustment.getAttribute('aria-describedby'), 'roundOffHelp');
        assert.match(await page.locator('#roundOffHelp').innerText(), /nearest rupee/i);
        await page.locator('#gstType').selectOption('export');
        await page.locator('[name="items[0][product_name]"]').fill('Browser cartons');
        const price = page.locator('[name="items[0][unit_price]"]');
        await price.fill('100.49');
        assert.equal(await adjustment.inputValue(), '-0.49');
        assert.equal(await adjustment.evaluate(el => el.readOnly), true);
        assert.match(await page.locator('#previewTotal').innerText(), /100\.00/);
        await price.fill('100.50');
        assert.equal(await adjustment.inputValue(), '0.50');
        assert.match(await page.locator('#previewTotal').innerText(), /101\.00/);
        // Select2 announces picks through jQuery, not a native input event.
        await page.evaluate(() => window.jQuery('#gstType').val('intra_state').trigger('change'));
        assert.equal(await adjustment.inputValue(), '0.41', `${width}: three-decimal GST selection recalculates round-off`);
        assert.match(await page.locator('#previewTotal').innerText(), /119\.00/);
        await page.evaluate(() => window.jQuery('#gstType').val('export').trigger('change'));
        await page.locator('#discountValue').fill('10');
        assert.match(await page.locator('#previewTotal').innerText(), /91\.00/);
        await page.evaluate(() => window.jQuery('#discountType').val('percent').trigger('change'));
        assert.equal(await adjustment.inputValue(), '-0.45', `${width}: discount type selection recalculates round-off`);
        assert.match(await page.locator('#previewTotal').innerText(), /90\.00/);
        await page.locator('#discountValue').fill('0');
        // A PI keeps 9.495, then rounds the whole total down to 9.
        const quantity = page.locator('[name="items[0][quantity]"]');
        await quantity.fill('0.015');
        await price.fill('633.00');
        assert.equal(await adjustment.inputValue(), '-0.495', `${width}: three-decimal PI matches server`);
        assert.match(await page.locator('#previewTotal').innerText(), /9\.00/);
        await quantity.fill('1');
        await price.fill('100.50');

        await auto.uncheck();
        assert.equal(await adjustment.evaluate(el => el.readOnly), false);
        await adjustment.fill('0.25');
        assert.match(await page.locator('#previewTotal').innerText(), /100\.75/);
        await auto.check();
        assert.equal(await adjustment.inputValue(), '0.50');
        await auto.uncheck();
        assert.equal(await adjustment.inputValue(), '0.25', `${width}: manual override is restored`);
        await auto.check();
        await page.locator('#invoiceCurrency').selectOption('USD');
        assert.equal(await auto.isDisabled(), true);
        assert.equal(await adjustment.inputValue(), '0.25', `${width}: INR automatic adjustment must not leak into USD`);
        assert.match(await page.locator('#previewTotal').innerText(), /100\.75/);
        await page.locator('#invoiceCurrency').selectOption('INR');
        assert.equal(await adjustment.inputValue(), '0.50');
        await page.locator('#invoiceType').selectOption('cash');
        assert.equal(await auto.isDisabled(), true);
        assert.equal(await adjustment.inputValue(), '0.25', `${width}: INR automatic adjustment must not leak into cash`);
        await page.locator('#invoiceType').selectOption('proforma');
        assert.equal(await adjustment.inputValue(), '0.50');
        await auto.scrollIntoViewIfNeeded();
        const box = await auto.boundingBox();
        assert.ok(box && box.x >= 0 && box.x + box.width <= width, `${width}: rounding control fits on screen`);
        console.log(`PASS ${width}px: half-up preview, manual restore, currency/type switches and accessible control`);
    }

    // Submit the actual form, choose a private portal setting, then override
    // the saved invoice and edit it again.
    const invalid = await page.locator('form.master-form').evaluate(form => Array.from(form.querySelectorAll(':invalid')).map(el => `${el.name}: ${el.validationMessage}`));
    assert.deepEqual(invalid, [], 'Browser form validation must permit the fixture to save');
    await page.locator('form.master-form [type="submit"]').first().click();
    await Promise.all([
        page.waitForURL(url => /^\/sales-invoices\/\d+$/.test(url.pathname), { waitUntil: 'domcontentloaded', timeout: 10000 }),
        page.getByRole('dialog').getByRole('button', { name: 'No', exact: true }).click(),
    ]);
    assert.match(await page.locator('body').innerText(), /Grand total\s+₹101\b/);
    assert.match(await page.locator('body').innerText(), /Round off\s+₹0\.50/);
    const savedPath = new URL(page.url()).pathname;
    await page.goto(new URL(savedPath + '/edit', base).href, { waitUntil: 'domcontentloaded' });
    const auto = page.getByRole('checkbox', { name: 'Automatically round the INR total' });
    const adjustment = page.locator('#roundOff');
    assert.equal(await auto.isChecked(), true);
    assert.equal(await adjustment.inputValue(), '0.50');
    await auto.uncheck();
    await adjustment.fill('0.23');
    assert.match(await page.locator('#previewTotal').innerText(), /100\.73/);
    await Promise.all([
        page.waitForURL(url => url.pathname === savedPath),
        page.locator('form.master-form [type="submit"]').first().click(),
    ]);
    await page.goto(new URL(savedPath + '/edit', base).href, { waitUntil: 'domcontentloaded' });
    assert.equal(await auto.isChecked(), false);
    assert.equal(await adjustment.inputValue(), '0.23');
    assert.match(await page.locator('#previewTotal').innerText(), /100\.73/);
    assert.deepEqual(errors, [], 'No uncaught browser JavaScript errors');
    console.log('PASS live form save/edit: server total, manual override and policy persist');
    await browser.close();
}

main().catch(error => { console.error(error); process.exit(1); });
