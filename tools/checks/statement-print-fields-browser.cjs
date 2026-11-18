/* Real print-option interactions against the guarded disposable SQLite app. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { chromium } = require('playwright-core');
const base = new URL(process.env.STATEMENT_PRINT_FIELDS_BASE_URL || 'http://127.0.0.1:8143');
if (!['127.0.0.1', 'localhost', '[::1]'].includes(base.hostname)) throw new Error('Use a disposable loopback server only.');
for (const key of ['VENDOR_STATEMENT_PROJECTS_TEST_EMAIL', 'VENDOR_STATEMENT_PROJECTS_TEST_PASSWORD', 'CHROMIUM_EXECUTABLE_PATH']) {
    if (!process.env[key]) throw new Error(`Missing ${key}`);
}
const fixture = JSON.parse(fs.readFileSync('.work/statement-print-fields-fixture.json', 'utf8'));
const pathFor = (type, id) => `/cashflows/statements/${type}/${id}/print?period=all&currency=INR&ageing=0`;
const field = (page, name) => page.locator(`input[type="checkbox"][name="print_fields[]"][value="${name}"]`);
const trigger = page => page.locator('[data-drawer-open="statementPrintFilters"]');
const table = page => page.locator('table.stmt-table').first();
const normalized = text => text.replace(/\s+/g, ' ').trim();

async function apply(page) {
    await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.getByRole('button', { name: 'Apply filters' }).click()]);
}

function chosenFields(url) {
    return [...url.searchParams].filter(([key]) => /^print_fields\[\d*\]$/.test(key)).map(([, value]) => value).sort();
}

async function main() {
    const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_EXECUTABLE_PATH, headless: true,
        args: ['--no-sandbox', '--disable-dev-shm-usage'] });
    try {
        const context = await browser.newContext();
        await context.addInitScript(() => { window.print = () => { window.__printRequested = true; }; });
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.goto(new URL('/login', base).href, { waitUntil: 'domcontentloaded' });
        await page.locator('[name="email"]').fill(process.env.VENDOR_STATEMENT_PROJECTS_TEST_EMAIL);
        await page.locator('[name="password"]').fill(process.env.VENDOR_STATEMENT_PROJECTS_TEST_PASSWORD);
        await Promise.all([page.waitForURL(url => !url.pathname.startsWith('/login')), page.locator('[type="submit"]').click()]);

        for (const width of [1440, 390, 320]) {
            await page.setViewportSize({ width, height: 900 });
            await page.goto(new URL(pathFor('vendor', fixture.vendor_id), base).href, { waitUntil: 'domcontentloaded' });
            assert.equal(await table(page).locator('thead th').count(), 6);
            assert.equal(await page.locator('.stmt-project').count(), 3);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'Preview has no page-wide overflow');
            await trigger(page).click();
            assert.equal(await page.locator('#statementPrintFilters').getAttribute('role'), 'dialog');
            assert.equal(await page.locator('#statementPrintFilters-layer').getAttribute('aria-hidden'), 'false');
            await page.waitForTimeout(300); // Allow the shared drawer transition to finish before visual inspection.
            await page.screenshot({ path: `.work/statement-print-fields-drawer-${width}.png` });
            for (const key of ['date', 'particulars', 'project_names', 'reference', 'debit', 'credit', 'balance',
                'summary', 'bank_details', 'other_currencies', 'notes', 'logo', 'issuer_details', 'party_details']) {
                const box = field(page, key);
                assert(await box.isChecked(), `${key} is selected by default`);
                await box.scrollIntoViewIfNeeded();
                const size = await box.locator('..').evaluate(node => {
                    const r = node.getBoundingClientRect();
                    return { width: r.width, height: r.height, left: r.left, right: r.right };
                });
                assert(size.height >= 40 && size.width >= 100 && size.left >= -1 && size.right <= width + 1,
                    `${key} has an unclipped touch-sized label at ${width}px: ${JSON.stringify(size)}`);
            }
            // Cancel must not leave un-applied changes in the next opening.
            await field(page, 'reference').uncheck();
            await page.keyboard.press('Escape');
            assert.equal(await page.locator('#statementPrintFilters-layer').getAttribute('aria-hidden'), 'true');
            await trigger(page).click();
            assert(await field(page, 'reference').isChecked(), 'Escape discards un-applied edits');
            await page.keyboard.press('Escape');
            assert.equal(await trigger(page).getAttribute('aria-expanded'), 'false');
            console.log(`PASS ${width}px: default document, accessible drawer, touch targets and cancel`);
        }

        await page.setViewportSize({ width: 1440, height: 900 });
        await page.goto(new URL(pathFor('vendor', fixture.vendor_id), base).href, { waitUntil: 'domcontentloaded' });
        await trigger(page).click();
        for (const key of ['reference', 'project_names', 'credit', 'summary', 'bank_details', 'party_details']) await field(page, key).uncheck();
        await apply(page);
        let headings = normalized(await table(page).locator('thead').innerText());
        assert.match(headings, /DATE.*PARTICULARS.*DEBIT.*BALANCE/i);
        assert.doesNotMatch(headings, /REFERENCE|CREDIT/i);
        assert.equal(await page.locator('.stmt-project, .stmt-block--summary, .stmt-ageing').count(), 0);
        assert.doesNotMatch(await page.locator('article.stmt').innerText(), /Bank:/);
        assert.match(await page.locator('article.stmt').innerText(), /Browser Supplier/);
        assert.match(await page.locator('.stmt-field-disclosure').innerText(), /totals still include all transactions/i);
        assert.match(normalized(await table(page).locator('tfoot').innerText()), /185\.375.*115\.125/);
        const current = new URL(page.url());
        const pdf = new URL(await page.locator('#statementDownload').getAttribute('href'));
        assert.equal(pdf.searchParams.get('fields_configured'), '1');
        assert.deepEqual(chosenFields(pdf), chosenFields(current));
        for (const key of ['period', 'currency', 'ageing']) assert.equal(pdf.searchParams.get(key), current.searchParams.get(key));
        await page.locator('#statementDownload').click();
        assert(await page.evaluate(() => window.__printRequested), 'Browser-PDF fallback prints the selected preview');
        await page.reload({ waitUntil: 'domcontentloaded' });
        await trigger(page).click();
        assert.equal(await field(page, 'reference').isChecked(), false, 'Selection survives reload');
        await page.locator('#printFrom').fill('2026-10-01');
        await page.locator('#printTo').fill('2026-10-31');
        assert.equal(await page.locator('#printPeriod').inputValue(), 'custom');
        await apply(page);
        assert.equal(new URL(page.url()).searchParams.get('date_from'), '2026-10-01');
        assert.equal(new URL(await page.locator('#statementDownload').getAttribute('href')).searchParams.get('date_to'), '2026-10-31');
        assert.doesNotMatch(await table(page).locator('thead').innerText(), /REFERENCE/i, 'Date changes preserve the selected fields');
        await trigger(page).click();
        await page.locator('#statementPrintShowAll').click();
        assert(await field(page, 'reference').isChecked() && await field(page, 'credit').isChecked());
        assert(await page.locator('#printAgeing').isChecked());
        await apply(page);
        assert.equal(await table(page).locator('thead th').count(), 6, 'Show all restores columns');
        assert.equal(await page.locator('.stmt-block--summary').count(), 1);
        console.log('PASS vendor: selection, fallback download, reload, date edits and Show all');

        // Project-only particulars and a balance expose the narrow-table layout
        // and a very long, unbroken name in an actual A4 document.
        await page.goto(new URL(pathFor('vendor', fixture.vendor_id), base).href, { waitUntil: 'domcontentloaded' });
        await trigger(page).click();
        for (const key of ['date', 'particulars', 'reference', 'debit', 'credit', 'summary', 'bank_details',
            'other_currencies', 'notes', 'issuer_details', 'party_details']) await field(page, key).uncheck();
        await apply(page);
        assert.equal(await table(page).locator('thead th').count(), 2);
        const projectWidth = await table(page).evaluate(node => node.tHead.rows[0].cells[0].getBoundingClientRect().width / node.getBoundingClientRect().width);
        assert(projectWidth >= 0.65, `A project-only table should use the available width for names, got ${projectWidth}`);
        headings = normalized(await table(page).locator('thead').innerText());
        assert.match(headings, /PROJECT.*BALANCE/i);
        assert.doesNotMatch(await page.locator('article.stmt').innerText(), /BROWSER-PROJECT-BILL|Advance shared across documents/);
        assert.match(await page.locator('article.stmt').innerText(), new RegExp(fixture.alpha_name));
        const clipped = await page.locator('.stmt-project').evaluateAll(nodes => nodes.filter(node => node.scrollWidth > node.clientWidth + 1).map(node => node.textContent));
        assert.deepEqual(clipped, [], 'Project-only text wraps without clipping');
        await page.screenshot({ path: '.work/statement-print-fields-project-only.png', fullPage: true });
        for (const width of [390, 320]) {
            await page.setViewportSize({ width, height: 900 });
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `No selected-table overflow at ${width}px`);
            const overflow = await table(page).locator('td, th').evaluateAll(nodes => nodes.filter(node => node.scrollWidth > node.clientWidth + 1).map(node => node.textContent.trim()));
            assert.deepEqual(overflow, [], `No selected-table cell clips at ${width}px`);
            const wrappedAmounts = await table(page).locator('.stmt-num').evaluateAll(nodes => nodes.map(node => {
                const range = document.createRange(); range.selectNodeContents(node);
                const lines = new Set([...range.getClientRects()].filter(rect => rect.width > 0).map(rect => Math.round(rect.top))).size;
                return { text: node.textContent.trim(), lines };
            }).filter(item => item.lines > 1));
            assert.deepEqual(wrappedAmounts, [], `Normal-size balances and their heading must remain on one line at ${width}px`);
            await page.screenshot({ path: `.work/statement-print-fields-project-only-${width}.png`, fullPage: true });
        }
        await page.setViewportSize({ width: 1200, height: 900 });
        await page.emulateMedia({ media: 'print' });
        assert.equal(await page.locator('.pdf-toolbar').evaluate(node => getComputedStyle(node).display), 'none');
        await page.pdf({ path: '.work/statement-print-fields-vendor-a4.pdf', format: 'A4', preferCSSPageSize: true, printBackground: true });
        await page.emulateMedia({ media: 'screen' });

        // A client with many rows exercises repeated chosen headings and total.
        await page.goto(new URL(pathFor('client', fixture.client_id), base).href, { waitUntil: 'domcontentloaded' });
        await trigger(page).click();
        for (const key of ['particulars', 'credit', 'bank_details', 'other_currencies']) await field(page, key).uncheck();
        await apply(page);
        assert.equal(await table(page).locator('tbody tr').count(), 66, '65 invoices plus opening');
        headings = normalized(await table(page).locator('thead').innerText());
        assert.match(headings, /DATE.*REFERENCE.*DEBIT.*BALANCE/i);
        assert.doesNotMatch(headings, /PARTICULARS|CREDIT/i);
        await page.emulateMedia({ media: 'print' });
        await page.pdf({ path: '.work/statement-print-fields-client-a4.pdf', format: 'A4', preferCSSPageSize: true, printBackground: true });
        await page.emulateMedia({ media: 'screen' });
        assert.deepEqual(errors, [], 'No uncaught JavaScript exceptions');
        console.log('PASS project-only and 65-row client A4 generation');
    } finally {
        await browser.close();
    }
}
main().catch(error => { console.error(error); process.exit(1); });
