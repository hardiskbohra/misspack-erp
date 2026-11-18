/* Real vendor statement, guest share and A4 print using disposable loopback fixtures. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { chromium } = require('playwright-core');
const base = new URL(process.env.VENDOR_STATEMENT_PROJECTS_BASE_URL || 'http://127.0.0.1:8142');
if (!['127.0.0.1', 'localhost', '[::1]'].includes(base.hostname)) throw new Error('Use a disposable loopback server only.');
for (const key of ['VENDOR_STATEMENT_PROJECTS_TEST_EMAIL', 'VENDOR_STATEMENT_PROJECTS_TEST_PASSWORD', 'CHROMIUM_EXECUTABLE_PATH']) {
    if (!process.env[key]) throw new Error(`Missing ${key}`);
}
const fixture = JSON.parse(fs.readFileSync('.work/vendor-statement-projects-fixture.json', 'utf8'));
const query = '?period=all&currency=INR&ageing=0';
const statementPath = `/cashflows/statements/vendor/${fixture.vendor_id}`;

async function checkDocument(page, width, label) {
    const table = page.locator('table.stmt-table').first();
    const bill = table.locator('tbody tr').filter({ hasText: 'BROWSER-PROJECT-BILL' });
    const split = table.locator('tbody tr').filter({ hasText: 'BROWSER-SPLIT' });
    const ledgerSplit = table.locator('tbody tr').filter({ hasText: 'BROWSER-LEDGER-SLICE' });
    const noProject = table.locator('tbody tr').filter({ hasText: 'BROWSER-NO-PROJECT' });
    assert.equal(await bill.count(), 1, `${label}: one bill row`);
    assert.equal(await split.count(), 1, `${label}: one cashflow payment row`);
    assert.equal(await ledgerSplit.count(), 1, `${label}: one split ledger payment row`);
    assert.equal(await noProject.count(), 1, `${label}: one unallocated row`);
    assert.match(await bill.locator('td').nth(1).textContent(), /Purchase bill BROWSER-PROJECT-BILL/);
    assert.match(await bill.locator('.stmt-project').textContent(), /Project: Refillable Dispenser Kits for North Clinic/);
    const splitText = await split.locator('.stmt-project').textContent();
    assert.ok(splitText.includes('Projects:') && splitText.includes(fixture.alpha_name) && splitText.includes(fixture.beta_name),
        `${label}: all allocated project names in the particulars cell`);
    assert.match(await split.locator('td').nth(1).textContent(), /Payment for vendor projects/);
    const ledgerSplitText = await ledgerSplit.locator('.stmt-project').textContent();
    assert.ok(ledgerSplitText.includes('Projects:') && ledgerSplitText.includes(fixture.alpha_name) && ledgerSplitText.includes(fixture.beta_name),
        `${label}: purchase-payment splits retain both project names`);
    assert.match(await ledgerSplit.locator('td').nth(1).textContent(), /Advance shared across documents/);
    assert.equal(await noProject.locator('.stmt-project').count(), 0, `${label}: no invented project`);
    const headers = (await table.locator('thead').innerText()).replace(/\s+/g, ' ');
    assert.match(headers, /Debit \(paid\)/i);
    assert.match(headers, /Credit \(billed\)/i);
    assert.match((await table.locator('tfoot').innerText()).replace(/\s+/g, ' '), /185\.375.*300\.50.*115\.125/);
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1), `${label}: no page-wide overflow at ${width}px`);
    const projectOverflow = await table.locator('.stmt-project').evaluateAll(nodes => nodes.map(node => ({
        text: node.textContent.trim(), scrollWidth: node.scrollWidth, clientWidth: node.clientWidth,
        wrapping: getComputedStyle(node).overflowWrap,
    })).filter(item => item.scrollWidth > item.clientWidth + 1 || item.wrapping !== 'anywhere'));
    assert.deepEqual(projectOverflow, [], `${label}: project names must wrap without clipping`);
}

async function main() {
    const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_EXECUTABLE_PATH, headless: true,
        args: ['--no-sandbox', '--disable-dev-shm-usage'] });
    try {
        const context = await browser.newContext();
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.goto(new URL('/login', base).href, { waitUntil: 'domcontentloaded' });
        await page.locator('[name="email"]').fill(process.env.VENDOR_STATEMENT_PROJECTS_TEST_EMAIL);
        await page.locator('[name="password"]').fill(process.env.VENDOR_STATEMENT_PROJECTS_TEST_PASSWORD);
        await Promise.all([page.waitForURL(url => !url.pathname.startsWith('/login')), page.locator('[type="submit"]').click()]);
        for (const width of [1440, 390, 320]) {
            await page.setViewportSize({ width, height: 900 });
            await page.goto(new URL(statementPath + query, base).href, { waitUntil: 'domcontentloaded' });
            await checkDocument(page, width, `office ${width}px`);
            await page.screenshot({ path: `.work/vendor-statement-projects-office-${width}.png`, fullPage: true });
            await page.goto(new URL(statementPath + '/print' + query, base).href, { waitUntil: 'domcontentloaded' });
            await checkDocument(page, width, `print preview ${width}px`);
            await page.screenshot({ path: `.work/vendor-statement-projects-print-${width}.png`, fullPage: true });
            console.log(`PASS ${width}px: office + print preview, project names, wrapping, amounts`);
        }
        await page.setViewportSize({ width: 1200, height: 900 });
        await page.emulateMedia({ media: 'print' });
        await checkDocument(page, 1200, 'print media');
        await page.pdf({ path: '.work/vendor-statement-projects-a4.pdf', format: 'A4', preferCSSPageSize: true, printBackground: true });
        assert.deepEqual(errors, [], 'No uncaught office JavaScript errors');
        const guest = await browser.newContext({ viewport: { width: 390, height: 900 } });
        const guestPage = await guest.newPage();
        await guestPage.goto(new URL(`/statement/${fixture.share_token}`, base).href, { waitUntil: 'domcontentloaded' });
        await checkDocument(guestPage, 390, 'guest public share');
        await guestPage.screenshot({ path: '.work/vendor-statement-projects-guest-390.png', fullPage: true });
        await guest.close();
        console.log('PASS guest share and A4 PDF generation');
    } finally {
        await browser.close();
    }
}
main().catch(error => { console.error(error); process.exit(1); });
