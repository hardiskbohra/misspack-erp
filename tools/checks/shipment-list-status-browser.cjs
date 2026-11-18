/* Disposable Chromium coverage for the shipment-list status dialog. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { chromium } = require('playwright-core');
const base = new URL(process.env.SHIPMENT_LIST_BROWSER_BASE_URL || 'http://127.0.0.1:8155');
if (!['127.0.0.1', 'localhost', '[::1]'].includes(base.hostname)) throw new Error('Use a disposable loopback server only.');
for (const key of ['SHIPMENT_LIST_BROWSER_PASSWORD', 'CHROMIUM_EXECUTABLE_PATH']) if (!process.env[key]) throw new Error(`Missing ${key}`);
const fixture = JSON.parse(fs.readFileSync('.work/shipment-list-status-fixture.json', 'utf8'));
const url = path => new URL(path, base).href;
async function visit(page, path) {
    const response = await page.goto(url(path), { waitUntil: 'domcontentloaded' });
    assert(response && response.ok(), `${path}: HTTP ${response?.status()}`);
}
async function login(page, email) {
    await visit(page, '/login');
    await page.locator('[name="email"]').fill(email);
    await page.locator('[name="password"]').fill(process.env.SHIPMENT_LIST_BROWSER_PASSWORD);
    await Promise.all([page.waitForURL(current => !current.pathname.startsWith('/login')), page.locator('[type="submit"]').click()]);
}
const opener = (page, id) => page.locator(`[data-shipment-status-open][data-shipment-id="${id}"]`);
const modal = page => page.locator('#shipmentStatusModal');
async function submit(page) {
    await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), modal(page).getByRole('button', { name: 'Save status' }).click()]);
}
async function main() {
    const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_EXECUTABLE_PATH, headless: true,
        args: ['--no-sandbox', '--disable-dev-shm-usage'] });
    const errors = [];
    try {
        const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
        const page = await context.newPage();
        page.on('pageerror', error => errors.push(error.message));
        await login(page, 'shipment-list-manager@example.test');
        for (const width of [1440, 768, 390, 320]) {
            await page.setViewportSize({ width, height: 900 });
            await visit(page, '/shipments?search=BROWSER-LIST');
            const link = opener(page, fixture.first_id);
            assert.match(await link.getAttribute('href'), new RegExp(`/shipments/${fixture.first_id}\\?tab=tracking`));
            await link.click();
            const dialog = modal(page);
            assert(await dialog.isVisible());
            assert.match(await dialog.innerText(), /BROWSER-LIST-01/);
            assert.equal(await dialog.locator('[name="status"]').inputValue(), 'in_transit');
            assert.equal(await dialog.locator('[name="notify_client"][type="checkbox"]').isChecked(), true);
            assert.equal(await dialog.locator('[name="status"]').getAttribute('data-no-select2'), '');
            const save = dialog.getByRole('button', { name: 'Save status' });
            const close = dialog.getByRole('button', { name: 'Close status update' });
            await save.focus();
            await page.keyboard.press('Tab');
            assert.equal(await close.evaluate(el => document.activeElement === el), true, 'Tab remains in the dialog');
            await page.keyboard.press('Shift+Tab');
            assert.equal(await save.evaluate(el => document.activeElement === el), true, 'Shift+Tab remains in the dialog');
            assert(await dialog.locator('[role="dialog"]').evaluate(el => {
                const r = el.getBoundingClientRect(); return r.left >= -1 && r.right <= window.innerWidth + 1;
            }), `Modal fits the ${width}px viewport`);
            assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `No page overflow at ${width}px`);
            await page.keyboard.press('Escape');
            assert.equal(await dialog.isVisible(), false);
            assert.equal(await link.evaluate(el => document.activeElement === el), true, 'Focus returns to the row action');
            console.log(`PASS ${width}px status dialog, accessible context and geometry`);
        }
        await page.setViewportSize({ width: 1440, height: 900 });
        await visit(page, '/shipments?search=BROWSER-LIST');
        await opener(page, fixture.first_id).click();
        await modal(page).click({ position: { x: 5, y: 5 } });
        assert.equal(await modal(page).isVisible(), false, 'Backdrop closes the dialog');
        assert.equal(await opener(page, fixture.first_id).evaluate(el => document.activeElement === el), true);
        await opener(page, fixture.first_id).click();
        await modal(page).locator('[name="status"]').selectOption('delivered');
        await modal(page).locator('[name="drop_date"]').fill('2025-12-31');
        await modal(page).locator('[name="remarks"]').fill('Private browser delivery note');
        await modal(page).locator('[name="is_public"][type="checkbox"]').uncheck();
        await modal(page).locator('[name="notify_client"][type="checkbox"]').uncheck();
        await submit(page);
        assert.equal(await page.locator('.ma-overlay').count(), 0, 'Inline validation does not create a second overlay');
        assert(await modal(page).isVisible());
        assert.match(await modal(page).innerText(), /cannot be before the pickup/i);
        assert.equal(await modal(page).locator('[name="remarks"]').inputValue(), 'Private browser delivery note');
        assert.equal(await modal(page).locator('[name="is_public"][type="checkbox"]').isChecked(), false);
        assert.equal(new URL(page.url()).searchParams.get('search'), 'BROWSER-LIST');
        await modal(page).locator('[name="drop_date"]').fill('');
        await submit(page);
        assert.equal(new URL(page.url()).searchParams.get('search'), 'BROWSER-LIST');
        assert.match(await opener(page, fixture.first_id).locator('..').innerText(), /delivered/i);
        await visit(page, `/shipments/${fixture.first_id}?tab=tracking`);
        assert((await page.content()).includes('Private browser delivery note'));
        await visit(page, `/track-shipment/${fixture.public_token}`);
        assert(!(await page.content()).includes('Private browser delivery note'));
        console.log('PASS validation recovery, delivery default, filtered return and private tracking choice');

        await visit(page, '/shipments?search=BROWSER-LIST-02');
        await opener(page, fixture.second_id).click();
        await modal(page).locator('[name="status"]').selectOption('delayed');
        await modal(page).locator('[name="remarks"]').fill('Unsaved stale note');
        const concurrent = await context.newPage();
        concurrent.on('pageerror', error => errors.push(error.message));
        await visit(concurrent, '/shipments?search=BROWSER-LIST-02');
        await opener(concurrent, fixture.second_id).click();
        await modal(concurrent).locator('[name="status"]').selectOption('out_for_delivery');
        await submit(concurrent);
        await concurrent.close();
        await submit(page);
        assert.equal(await page.locator('.ma-overlay').count(), 0, 'Stale validation does not create a second overlay');
        assert(await modal(page).isVisible());
        assert.match(await modal(page).innerText(), /changed since/i);
        assert.match(await modal(page).innerText(), /Out for delivery/);
        assert.equal(await modal(page).locator('[name="remarks"]').inputValue(), 'Unsaved stale note');
        console.log('PASS stale form refusal and unsaved input recovery');

        const readerContext = await browser.newContext({ viewport: { width: 390, height: 900 } });
        const reader = await readerContext.newPage();
        reader.on('pageerror', error => errors.push(error.message));
        await login(reader, 'shipment-list-reader@example.test');
        await visit(reader, '/shipments?search=BROWSER-LIST');
        assert.equal(await reader.locator('[data-shipment-status-open]').count(), 0);
        assert.equal(await reader.locator('#shipmentStatusModal').count(), 0);
        await readerContext.close();
        const noJsContext = await browser.newContext({ javaScriptEnabled: false });
        const noJsPage = await noJsContext.newPage();
        await login(noJsPage, 'shipment-list-manager@example.test');
        await visit(noJsPage, '/shipments?search=BROWSER-LIST-02');
        assert.equal(await noJsPage.evaluate(() => typeof window.MasterModal), 'undefined', 'Page scripts are disabled for the fallback check');
        await Promise.all([noJsPage.waitForURL(current => current.pathname === `/shipments/${fixture.second_id}`, { waitUntil: 'domcontentloaded' }), opener(noJsPage, fixture.second_id).click()]);
        assert.equal(new URL(noJsPage.url()).searchParams.get('tab'), 'tracking');
        await noJsContext.close();
        assert.deepEqual(errors, []);
        console.log('PASS read-only access, no-JavaScript fallback and no JavaScript errors');
        await context.close();
    } finally { await browser.close(); }
}
main().catch(error => { console.error(error); process.exitCode = 1; });
