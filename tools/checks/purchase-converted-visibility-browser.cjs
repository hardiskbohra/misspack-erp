/* Actual drawer/Select2 and purchase-list navigation on disposable loopback fixtures.
 * Set PURCHASE_VISIBILITY_BASE_URL, PURCHASE_VISIBILITY_TEST_EMAIL,
 * PURCHASE_VISIBILITY_TEST_PASSWORD and CHROMIUM_EXECUTABLE_PATH. */
'use strict';
const assert = require('node:assert/strict');
const { chromium } = require('playwright-core');
const base = new URL(process.env.PURCHASE_VISIBILITY_BASE_URL || 'http://127.0.0.1:8141');
if (!['127.0.0.1', 'localhost', '[::1]'].includes(base.hostname)) throw new Error('Use a disposable loopback server only.');
for (const key of ['PURCHASE_VISIBILITY_TEST_EMAIL', 'PURCHASE_VISIBILITY_TEST_PASSWORD', 'CHROMIUM_EXECUTABLE_PATH']) {
    if (!process.env[key]) throw new Error(`Missing ${key}`);
}

async function rowNames(page) {
    return page.locator('.pi-table tbody tr a.si-number').allTextContents();
}
async function chooseConversion(page, value, label, width) {
    const trigger = page.locator('[data-drawer-open="purchaseFiltersDrawer"]');
    await trigger.click();
    const drawer = page.locator('#purchaseFiltersDrawer');
    assert.equal(await drawer.isVisible(), true);
    assert.equal(await trigger.getAttribute('aria-expanded'), 'true');
    const field = page.locator('#piFilterConverted');
    const fieldLabel = page.locator('label[for="piFilterConverted"]');
    assert.equal(await fieldLabel.isVisible(), true);
    assert.equal(await fieldLabel.innerText(), 'Converted orders');
    const selection = field.locator('xpath=following-sibling::span[contains(@class,"select2-container")]').locator('.select2-selection');
    await selection.scrollIntoViewIfNeeded();
    const box = await selection.boundingBox();
    assert.ok(box && box.x >= 0 && box.x + box.width <= width, `${width}px conversion control fits`);
    if (width === 320 && value === 'only') {
        // Exercise the real Select2 keyboard path as well as pointer input.
        await selection.focus();
        await page.keyboard.press('Enter');
        const search = page.locator('.select2-container--open .select2-search__field');
        await search.fill(label);
        const option = page.getByRole('option', { name: label, exact: true });
        assert.equal(await option.isVisible(), true);
        await page.keyboard.press('Enter');
    } else {
        await selection.click();
        const option = page.getByRole('option', { name: label, exact: true });
        assert.equal(await option.isVisible(), true);
        await option.click();
    }
    assert.equal(await field.inputValue(), value);
    const apply = drawer.getByRole('button', { name: 'Apply filters' });
    const buttonBox = await apply.boundingBox();
    assert.ok(buttonBox && buttonBox.x >= 0 && buttonBox.x + buttonBox.width <= width, `${width}px Apply button fits`);
    await Promise.all([
        page.waitForURL(url => url.searchParams.get('converted') === value),
        apply.click(),
    ]);
}

async function main() {
    const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_EXECUTABLE_PATH, headless: true,
        args: ['--no-sandbox', '--disable-dev-shm-usage'] });
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(new URL('/login', base).href, { waitUntil: 'domcontentloaded' });
    await page.locator('[name="email"]').fill(process.env.PURCHASE_VISIBILITY_TEST_EMAIL);
    await page.locator('[name="password"]').fill(process.env.PURCHASE_VISIBILITY_TEST_PASSWORD);
    await Promise.all([page.waitForURL(url => !url.pathname.startsWith('/login')), page.locator('[type="submit"]').click()]);

    for (const width of [1440, 390, 320]) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto(new URL('/purchase-invoices?search=VIS', base).href, { waitUntil: 'domcontentloaded' });
        let names = await rowNames(page);
        for (const number of ['VIS-PO-PARTIAL', 'VIS-PO-OPEN', 'VIS-BILL-DONE', 'VIS-BILL-LEGACY', 'VIS-BILL-PARTIAL']) {
            assert.ok(names.includes(number), `${width}px default retains ${number}`);
        }
        assert.ok(!names.includes('VIS-PO-DONE') && !names.includes('VIS-PO-LEGACY'), `${width}px default hides full POs`);
        const initialFigures = await page.locator('.master-stats .master-stat').first().innerText();
        assert.match(initialFigures, /31,000/);
        assert.match(initialFigures, /17,500/);
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1), `${width}px page fits`);

        await chooseConversion(page, 'include', 'Include fully converted orders', width);
        names = await rowNames(page);
        assert.ok(names.includes('VIS-PO-DONE') && names.includes('VIS-PO-LEGACY') && names.includes('VIS-PO-PARTIAL'));
        assert.equal(new URL(page.url()).searchParams.get('search'), 'VIS');
        assert.equal(await page.locator('.master-stats .master-stat').first().innerText(), initialFigures, 'Including history must not duplicate figures');
        assert.match(await page.locator('tr').filter({ has: page.locator('a.si-number', { hasText: 'VIS-PO-DONE' }) }).innerText(), /Fully converted/);
        assert.equal(await page.locator('.master-list-applied-chip').filter({ hasText: 'Include fully converted orders' }).count(), 1);
        await page.screenshot({ path: `.work/purchase-converted-${width}.png` });

        const orders = page.locator('.master-list-chip').filter({ hasText: /^\s*Orders\s/ }).first();
        await Promise.all([page.waitForURL(url => url.searchParams.get('invoice_type') === 'order'), orders.click()]);
        assert.equal(new URL(page.url()).searchParams.get('converted'), 'include');
        assert.equal(new URL(page.url()).searchParams.get('search'), 'VIS');
        assert.deepEqual((await rowNames(page)).sort(), ['VIS-PO-DONE', 'VIS-PO-LEGACY', 'VIS-PO-OPEN', 'VIS-PO-PARTIAL']);

        await chooseConversion(page, 'only', 'Only fully converted orders', width);
        assert.equal(new URL(page.url()).searchParams.get('invoice_type'), 'order');
        assert.equal(new URL(page.url()).searchParams.get('search'), 'VIS');
        assert.deepEqual((await rowNames(page)).sort(), ['VIS-PO-DONE', 'VIS-PO-LEGACY']);
        const chip = page.locator('.master-list-applied-chip').filter({ hasText: 'Only fully converted orders' });
        await Promise.all([page.waitForURL(url => !url.searchParams.has('converted')),
            chip.getByRole('link', { name: 'Remove Converted orders filter' }).click()]);
        assert.equal(new URL(page.url()).searchParams.get('search'), 'VIS');
        assert.equal(new URL(page.url()).searchParams.get('invoice_type'), 'order');
        assert.deepEqual((await rowNames(page)).sort(), ['VIS-PO-OPEN', 'VIS-PO-PARTIAL']);

        await page.goto(new URL('/purchase-invoices?search=PAGE-PO-DONE&converted=only', base).href, { waitUntil: 'domcontentloaded' });
        assert.equal((await rowNames(page)).length, 25);
        const pageTwo = page.locator('a[href*="page=2"]').first();
        await Promise.all([page.waitForURL(url => url.searchParams.get('page') === '2'), pageTwo.click()]);
        assert.equal(new URL(page.url()).searchParams.get('converted'), 'only');
        assert.equal(new URL(page.url()).searchParams.get('search'), 'PAGE-PO-DONE');
        assert.equal((await rowNames(page)).length, 2);
        console.log(`PASS ${width}px: default/include/only, Select2 drawer, chips, figures and pagination`);
    }
    assert.deepEqual(errors, [], 'No uncaught browser JavaScript errors');
    await browser.close();
}
main().catch(error => { console.error(error); process.exit(1); });
