/* Cashflow list checks against a disposable loopback server with a fixed test clock.
 * Set CASHFLOW_GROUPS_BASE_URL, CASHFLOW_GROUPS_TEST_EMAIL,
 * CASHFLOW_GROUPS_TEST_PASSWORD and CHROMIUM_EXECUTABLE_PATH. */
'use strict';
const assert = require('node:assert/strict');
const { chromium } = require('playwright-core');
const base = new URL(process.env.CASHFLOW_GROUPS_BASE_URL || 'http://127.0.0.1:8138');
if (!['127.0.0.1', 'localhost', '[::1]'].includes(base.hostname)) throw new Error('Use a disposable loopback server only.');
for (const key of ['CASHFLOW_GROUPS_TEST_EMAIL', 'CASHFLOW_GROUPS_TEST_PASSWORD', 'CHROMIUM_EXECUTABLE_PATH']) {
    if (!process.env[key]) throw new Error(`Missing ${key}`);
}
const expected = [
    ['Upcoming', 2, ['Future far', 'Future near']],
    ['Today', 1, ['Today entry']], ['Yesterday', 1, ['Yesterday entry']],
    ['2 days ago', 1, ['Two days entry']], ['This week', 1, ['This week entry']],
    ['Last week', 2, ['Last week recent', 'Last week start']],
    ['This month', 2, ['This month recent', 'This month start']],
    ['Last month', 2, ['Last month recent', 'Last month start']],
    ['Older entries', 2, ['Older recent', 'Older past']],
];
async function groups(page) {
    return page.locator('.cashflow-index table.master-table tbody tr').evaluateAll(rows => {
        const result = [];
        for (const row of rows) {
            if (row.classList.contains('master-list-group')) {
                const spans = row.querySelectorAll('.master-list-group-inner span');
                result.push([spans[0].textContent.trim(), Number(spans[1].textContent.trim()), []]);
            } else if (row.classList.contains('cf-row')) {
                result.at(-1)[2].push(row.querySelector('.cf-particular a strong').textContent.trim());
            }
        }
        return result;
    });
}
async function main() {
    const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_EXECUTABLE_PATH, headless: true,
        args: ['--no-sandbox', '--disable-dev-shm-usage'] });
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(new URL('/login', base).href, { waitUntil: 'domcontentloaded' });
    await page.locator('[name="email"]').fill(process.env.CASHFLOW_GROUPS_TEST_EMAIL);
    await page.locator('[name="password"]').fill(process.env.CASHFLOW_GROUPS_TEST_PASSWORD);
    await Promise.all([page.waitForURL(url => !url.pathname.startsWith('/login')), page.locator('[type="submit"]').click()]);
    for (const width of [1440, 390, 320]) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto(new URL('/cashflows', base).href, { waitUntil: 'domcontentloaded' });
        assert.deepEqual(await groups(page), expected);
        assert.match(await page.locator('.master-list-hint').innerText(), /Newest first.*grouped by date/i);
        for (const divider of await page.locator('.cashflow-index tr.master-list-group').all()) {
            assert.equal(await divider.isVisible(), true, `${width}px divider is visible`);
            const count = divider.locator('.master-list-group-count');
            assert.equal(await count.evaluate(el => el.dataset.uiTooltip || el.title), 'Entries in this group on this page');
            const box = await count.boundingBox();
            assert.ok(box && box.x >= 0 && box.x + box.width <= width, `${width}px group count fits without horizontal scrolling`);
        }
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1), `${width}px page does not overflow`);
        if (width <= 767) {
            for (const cell of await page.locator('.master-list-foot td[data-label]').all()) {
                const box = await cell.boundingBox();
                assert.ok(box && box.x >= 0 && box.x + box.width <= width, `${width}px totals fit without horizontal scrolling`);
            }
        }
        await page.locator('.cashflow-index tr.master-list-group').first().scrollIntoViewIfNeeded();
        await page.screenshot({ path: `.work/cashflow-date-groups-${width}.png` });

        await page.locator('tr.cf-row [data-bulk-pick]').first().check();
        assert.equal(await page.locator('[data-bulk-bar]').isVisible(), true);
        if (width <= 767) {
            for (const button of await page.locator('[data-bulk-bar] button').all()) {
                const box = await button.boundingBox();
                assert.ok(box && box.height >= 40, `${width}px bulk button has a 40px touch target`);
            }
        }
        await page.locator('[data-bulk-clear]').click();
        assert.equal(await page.locator('[data-bulk-bar]').isVisible(), false);

        const debit = page.locator('.master-list-chip').filter({ hasText: /^\s*Debit\b/ });
        await Promise.all([page.waitForURL(url => url.searchParams.get('transaction_type') === 'debit'), debit.click()]);
        assert.deepEqual(await groups(page), [
            ['Yesterday', 1, ['Yesterday entry']], ['2 days ago', 1, ['Two days entry']],
            ['Last week', 1, ['Last week recent']], ['This month', 1, ['This month recent']],
            ['Older entries', 1, ['Older recent']],
        ]);
        await page.goto(new URL('/cashflows?sort=oldest', base).href, { waitUntil: 'domcontentloaded' });
        assert.deepEqual(await groups(page), expected.slice().reverse().map(([label, count, rows]) => [label, count, rows.slice().reverse()]));
        assert.match(await page.locator('.master-list-hint').innerText(), /Oldest first.*grouped by date/i);
        console.log(`PASS ${width}px: ordered groups and page counts, debit filter, bulk selection and oldest-first`);
    }
    assert.deepEqual(errors, [], 'No uncaught browser JavaScript errors');
    await browser.close();
}
main().catch(error => { console.error(error); process.exit(1); });
