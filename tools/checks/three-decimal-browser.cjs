/* Interactive precision regression. Run only on a freshly seeded disposable
 * loopback test instance; this script creates and edits fictional documents.
 * Set PRECISION_TEST_BASE_URL, PRECISION_TEST_EMAIL, PRECISION_TEST_PASSWORD,
 * PRECISION_TEST_ACCOUNT and CHROMIUM_EXECUTABLE_PATH. */
'use strict';
const assert = require('node:assert/strict');
const { chromium } = require('playwright-core');
const base = new URL(process.env.PRECISION_TEST_BASE_URL || 'http://127.0.0.1:8135');
if (!['127.0.0.1', 'localhost', '[::1]'].includes(base.hostname)) throw new Error('Use a disposable loopback server only.');
for (const name of ['PRECISION_TEST_EMAIL', 'PRECISION_TEST_PASSWORD', 'PRECISION_TEST_ACCOUNT', 'CHROMIUM_EXECUTABLE_PATH']) {
    if (!process.env[name]) throw new Error(`Missing ${name}`);
}

async function main() {
    const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_EXECUTABLE_PATH, headless: true,
        args: ['--no-sandbox', '--disable-dev-shm-usage'] });
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(new URL('/login', base).href, { waitUntil: 'domcontentloaded' });
    await page.locator('[name="email"]').fill(process.env.PRECISION_TEST_EMAIL);
    await page.locator('[name="password"]').fill(process.env.PRECISION_TEST_PASSWORD);
    await Promise.all([page.waitForURL(url => !url.pathname.startsWith('/login')), page.locator('[type="submit"]').click()]);

    const kinds = [
        { path: '/sales-invoices', type: 'proforma', amount: /0\.574/ },
        { path: '/sales-invoices', type: 'tax', amount: /0\.57(?!\d)/ },
        { path: '/purchase-invoices', type: 'order', amount: /0\.574/ },
        { path: '/purchase-invoices', type: 'bill', amount: /0\.574/ },
    ];
    for (const width of [1440, 390]) {
        await page.setViewportSize({ width, height: 900 });
        for (const kind of kinds) {
            await page.goto(new URL(`${kind.path}/create?type=${kind.type}`, base).href, { waitUntil: 'domcontentloaded' });
            await page.locator('#gstType').selectOption('export');
            const auto = page.locator('#autoRoundTotal');
            if (await auto.count() && await auto.isChecked()) await auto.uncheck();
            await page.locator('[name="items[0][product_name]"]').fill(`Precision ${kind.type} ${width}`);
            const rate = page.locator('[name="items[0][unit_price]"]');
            const quantity = page.locator('[name="items[0][quantity]"]');
            assert.equal(await rate.getAttribute('step'), '0.001');
            await rate.fill('0.574');
            await quantity.fill('5000');
            assert.match(await page.locator('#previewTotal').innerText(), /2,870\.00/, `${kind.type}: screenshot example`);
            await quantity.fill('1');
            assert.match(await page.locator('#previewTotal').innerText(), kind.amount, `${kind.type}: document precision`);
            assert.deepEqual(await page.locator('form.master-form').evaluate(form => Array.from(form.querySelectorAll(':invalid')).map(el => `${el.name}: ${el.validationMessage}`)), []);
            await page.locator('form.master-form [type="submit"]').first().click();
            const prompt = page.getByRole('dialog').getByRole('button', { name: 'No', exact: true });
            if (await prompt.isVisible()) await prompt.click();
            await page.waitForURL(url => new RegExp(`^${kind.path}/\\d+$`).test(url.pathname), { timeout: 12000 });
            const savedPath = new URL(page.url()).pathname;
            assert.match(await page.locator('body').innerText(), /0\.574/, `${kind.type}: detail retains the rate`);
            await page.goto(new URL(savedPath + '/print', base).href, { waitUntil: 'domcontentloaded' });
            const printed = await page.locator('body').innerText();
            assert.match(printed, /0\.574/, `${kind.type}: print retains the rate`);
            assert.match(printed, kind.amount, `${kind.type}: print retains the total`);
            await page.goto(new URL(savedPath + '/edit', base).href, { waitUntil: 'domcontentloaded' });
            assert.equal(Number(await page.locator('[name="items[0][unit_price]"]').inputValue()), 0.574);
            assert.match(await page.locator('#previewTotal').innerText(), kind.amount, `${kind.type}: edit reload`);
            console.log(`PASS ${width}px ${kind.type}: preview, save, print and edit`);
        }
    }
    await page.goto(new URL('/cashflows/create', base).href, { waitUntil: 'domcontentloaded' });
    await page.locator('[name="entry_date"]').fill('2026-10-28');
    await page.locator('label').filter({ has: page.locator('[name="transaction_type"][value="credit"]') }).click();
    await page.locator('[name="particular"]').fill('Browser fractional credit');
    const amount = page.locator('form [name="amount"]');
    assert.equal(await amount.getAttribute('step'), '0.001');
    await amount.fill('0.574');
    await page.locator('[name="account_id"]').selectOption(process.env.PRECISION_TEST_ACCOUNT);
    await page.locator('[name="accounting_status"]').selectOption('booked');
    await page.locator('[name="related_party_type"]').selectOption('other');
    await Promise.all([page.waitForURL(url => url.pathname !== '/cashflows/create'), page.getByRole('button', { name: 'Create Entry' }).click()]);
    await page.goto(new URL('/cashflows', base).href, { waitUntil: 'domcontentloaded' });
    const ledger = await page.locator('body').innerText();
    assert.match(ledger, /Browser fractional credit/);
    assert.match(ledger, /0\.574/);
    assert.match(ledger, /10\.574/);
    await page.getByRole('link', { name: 'Browser fractional credit' }).click();
    await page.locator('[data-detail-allocation-add]').first().click();
    assert.match(await page.locator('[data-detail-available-total]').innerText(), /0\.574/);
    await page.evaluate(() => {
        const select = document.querySelector('#detailAllocationSales');
        const option = Array.from(select.options).find(item => item.value);
        if (!option) throw new Error('Expected a sales document in the allocation picker');
        window.jQuery(select).val(option.value).trigger('change');
    });
    await page.locator('#detailAllocationAmount').fill('0.575');
    const dialogPromise = page.waitForEvent('dialog');
    const click = page.getByRole('button', { name: 'Save allocation' }).click();
    const dialog = await dialogPromise;
    assert.match(dialog.message(), /exceeds the available.*0\.574/i);
    await dialog.accept();
    await click;
    assert.equal(await page.locator('#detailAllocationModal').getAttribute('aria-hidden'), 'false');
    assert.deepEqual(errors, [], 'No uncaught browser JavaScript errors');
    console.log('PASS cashflow: entry, three-decimal running balance and over-allocation guard');
    await browser.close();
}
main().catch(error => { console.error(error); process.exit(1); });
