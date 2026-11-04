/* Disposable Chromium checks for the link tabs, receipt entry and private comments. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { chromium } = require('playwright-core');
const base = new URL(process.env.SHIPMENT_TABS_BROWSER_BASE_URL || 'http://127.0.0.1:8155');
if (!['127.0.0.1', 'localhost', '[::1]'].includes(base.hostname)) throw new Error('Use a disposable loopback server only.');
for (const key of ['SHIPMENT_TABS_BROWSER_PASSWORD', 'CHROMIUM_EXECUTABLE_PATH']) if (!process.env[key]) throw new Error(`Missing ${key}`);
const fixture = JSON.parse(fs.readFileSync('.work/shipment-tabs-fixture.json', 'utf8'));
const shipmentPath = `/shipments/${fixture.shipment_id}`;
const url = path => new URL(path, base).href;
async function visit(page, path) {
    const target = url(path);
    const current = new URL(page.url());
    const destination = new URL(target);
    const same = current.origin === destination.origin && current.pathname === destination.pathname && current.search === destination.search;
    let response;
    if (same) {
        if (current.hash !== destination.hash) await page.goto(target);
        response = await page.reload({ waitUntil: 'domcontentloaded' });
    } else response = await page.goto(target, { waitUntil: 'domcontentloaded' });
    assert(response && response.ok(), `${path}: HTTP ${response?.status()}`);
}
async function login(page, email) {
    await visit(page, '/login');
    await page.locator('[name="email"]').fill(email);
    await page.locator('[name="password"]').fill(process.env.SHIPMENT_TABS_BROWSER_PASSWORD);
    await Promise.all([page.waitForURL(current => !current.pathname.startsWith('/login')), page.locator('[type="submit"]').click()]);
}
async function main() {
    const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_EXECUTABLE_PATH, headless: true,
        args: ['--no-sandbox', '--disable-dev-shm-usage'] });
    const errors = [];
    try {
        const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, acceptDownloads: true });
        const page = await context.newPage();
        page.on('pageerror', error => errors.push(error.message));
        await login(page, 'shipment-tabs-browser@example.test');
        const sections = {
            overview: 'Shipment Overview', tracking: 'Tracking History', products: 'Delivery receipt reconciliation',
            costs: 'Freight Cost Breakdown', documents: 'Shipment Attachments', comments: 'Internal comments',
        };
        for (const width of [1440, 768, 390, 320]) {
            await page.setViewportSize({ width, height: 900 });
            for (const [tab, heading] of Object.entries(sections)) {
                await visit(page, `${shipmentPath}?tab=${tab}`);
                // Shared Select2 initializes after DOMContentLoaded and can
                // briefly report its pre-layout intrinsic width.
                await page.waitForTimeout(500);
                const panel = page.locator(`#shipment-panel-${tab}`);
                assert(await panel.isVisible(), `${tab} panel is visible at ${width}px`);
                assert((await panel.innerText()).includes(heading), `${tab} contains ${heading}`);
                assert.equal(await page.locator('[role="tabpanel"]:visible').count(), 1);
                const active = page.locator(`#shipment-tab-${tab}`);
                assert.equal(await active.getAttribute('aria-selected'), 'true');
                assert(await active.evaluate(el => {
                    const a = el.getBoundingClientRect(), b = el.closest('[role="tablist"]').getBoundingClientRect();
                    return a.left >= b.left - 1 && a.right <= b.right + 1;
                }), `${tab} active tab is visible in its scroll bar at ${width}px`);
                assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${tab} has no page overflow at ${width}px`);
            }
            const textbox = page.locator('#shipmentCommentBody');
            assert(await textbox.isVisible());
            const rect = await textbox.evaluate(el => { const r = el.getBoundingClientRect(); return { left:r.left, right:r.right, width:r.width }; });
            assert(rect.left >= 0 && rect.right <= width + 1 && rect.width > 100, `Comment form fits at ${width}px: ${JSON.stringify(rect)}`);
            await page.screenshot({ path: `.work/shipment-comments-${width}.png`, fullPage: true });
            console.log(`PASS ${width}px: all six panels, active state, comment form and page geometry`);
        }
        await page.setViewportSize({ width: 1440, height: 900 });
        await visit(page, `${shipmentPath}?tab=overview`);
        const tab = page.locator('#shipment-tab-overview');
        await tab.focus();
        await page.keyboard.press('ArrowRight');
        assert.equal(await page.evaluate(() => document.activeElement.id), 'shipment-tab-tracking');
        await Promise.all([page.waitForURL(current => current.searchParams.get('tab') === 'tracking'), page.keyboard.press('Enter')]);
        await page.goBack({ waitUntil: 'domcontentloaded' });
        assert.equal(new URL(page.url()).searchParams.get('tab'), 'overview');
        console.log('PASS keyboard tab navigation and browser back');
        await page.goto(url(`${shipmentPath}#shipmentAttachments`));
        await page.waitForURL(current => current.searchParams.get('tab') === 'documents' && current.hash === '#shipmentAttachments');
        assert(await page.locator('#shipmentAttachments').isVisible());
        await page.goto(url(`${shipmentPath}#shipmentCosts`));
        await page.waitForURL(current => current.searchParams.get('tab') === 'costs' && current.hash === '#shipmentCosts');
        assert(await page.locator('#shipmentCosts').isVisible());
        await page.goto(url(`${shipmentPath}?record_delivery_receipt=0#shipmentAttachments`));
        await page.waitForURL(current => current.searchParams.get('tab') === 'documents' && current.hash === '#shipmentAttachments');
        await page.goto(url(`${shipmentPath}?record_delivery_receipt=1#shipmentAttachments`));
        assert(await page.locator('#shipment-panel-products').isVisible());
        assert(await page.locator('#deliveryReceiptModal').isVisible());
        await page.locator('[data-delivery-receipt-close]').first().click();
        await visit(page, `${shipmentPath}?record_delivery_receipt=1`);
        assert(await page.locator('#deliveryReceiptModal').isVisible());
        assert(await page.locator('#shipment-panel-products').isVisible());
        await page.locator('[data-delivery-receipt-close]').first().click();
        console.log('PASS legacy anchors and direct delivery receipt entry');

        await visit(page, `${shipmentPath}?tab=comments`);
        await page.locator('#shipmentCommentBody').fill('Please preserve this text on validation.');
        await page.locator('#shipmentCommentFiles').setInputFiles({ name: 'unsafe.exe', mimeType: 'application/octet-stream', buffer: Buffer.from('bad') });
        await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.getByRole('button', { name: 'Add comment' }).click()]);
        assert(await page.getByText('Comment could not be saved').isVisible(), 'Validation errors are displayed next to the form');
        assert(await page.locator('.ship-comments [role="alert"]').evaluate(el => parseFloat(getComputedStyle(el).borderLeftWidth) >= 3), 'Inline validation has a visible warning edge');
        assert.equal(await page.locator('#shipmentCommentBody').inputValue(), 'Please preserve this text on validation.');
        assert(await page.getByText('Please choose your files again after correcting the form.').isVisible());
        assert.equal(await page.locator('[data-shipment-comment]').count(), 0);
        await page.locator('.ma-overlay').getByRole('button', { name: 'OK' }).click();
        await page.locator('#shipmentCommentBody').fill('Fictional team note with a private PDF. <script>window.shipmentXss = true</script>');
        const content = Buffer.from('%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF\n');
        const filename = `private-evidence-${'x'.repeat(180)}.pdf`;
        await page.locator('#shipmentCommentFiles').setInputFiles({ name: filename, mimeType: 'application/pdf', buffer: content });
        await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.getByRole('button', { name: 'Add comment' }).click()]);
        assert.equal(new URL(page.url()).searchParams.get('tab'), 'comments');
        const comment = page.locator('[data-shipment-comment]').filter({ hasText: 'Fictional team note with a private PDF.' });
        assert.match(await comment.innerText(), /Fictional team note with a private PDF/);
        assert.match(await comment.innerText(), /Shipping Browser/);
        assert.equal(await page.evaluate(() => window.shipmentXss), undefined, 'Comment text must not execute');
        const fileUrl = await comment.getByRole('link', { name: filename }).getAttribute('href');
        const download = await context.request.get(fileUrl);
        assert.equal(download.status(), 200);
        assert.match(download.headers()['content-disposition'], /^attachment/);
        assert.equal(download.headers()['x-content-type-options'], 'nosniff');
        assert.deepEqual(await download.body(), content);
        await page.locator('#shipmentCommentBody').fill(`Second fictional note without a file. ${'y'.repeat(600)}`);
        await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.getByRole('button', { name: 'Add comment' }).click()]);
        assert.match(await page.locator('[data-shipment-comment]').first().innerText(), /Second fictional note/);
        assert.equal(await page.locator('[data-shipment-comment]').count(), 2);
        await page.setViewportSize({ width: 320, height: 900 });
        await page.waitForTimeout(500);
        assert(await page.locator('#shipment-tab-comments').evaluate(el => {
            const a = el.getBoundingClientRect(), b = el.closest('[role="tablist"]').getBoundingClientRect();
            return a.left >= b.left - 1 && a.right <= b.right + 1;
        }), 'Active tab stays visible after the viewport narrows');
        assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'Long comment and filename wrap on a 320px page');
        assert(await comment.getByRole('link', { name: filename }).evaluate(el => {
            const rect = el.getBoundingClientRect(); return rect.left >= 0 && rect.right <= innerWidth + 1;
        }), 'Long filename fits its mobile card');
        await page.screenshot({ path: '.work/shipment-comments-with-files-320.png', fullPage: true });
        await page.setViewportSize({ width: 1440, height: 900 });
        const publicLink = await page.locator('a:has-text("Public Tracking")').first().getAttribute('href');
        const publicRedirect = await context.request.get(publicLink, { maxRedirects: 0 });
        assert.equal(publicRedirect.status(), 302);
        await visit(page, publicRedirect.headers().location);
        assert(!(await page.content()).includes('Fictional team note with a private PDF'));
        assert(!(await page.content()).includes(filename));
        console.log('PASS validation recovery, comment upload, safe private download, ordering and public privacy');
        const readerContext = await browser.newContext({ viewport: { width: 390, height: 900 } });
        const reader = await readerContext.newPage();
        reader.on('pageerror', error => errors.push(error.message));
        await login(reader, 'shipment-reader-browser@example.test');
        await visit(reader, `${shipmentPath}?tab=comments`);
        assert(await reader.getByText('Fictional team note with a private PDF.').isVisible());
        assert.equal(await reader.locator('#shipmentCommentBody').count(), 0);
        assert.equal((await readerContext.request.get(fileUrl)).status(), 200);
        assert.equal(await reader.getByRole('button', { name: /^Delete comment/ }).count(), 0);
        await readerContext.close();
        await visit(page, `${shipmentPath}?tab=comments`);
        const evidenceComment = page.locator('[data-shipment-comment]').filter({ hasText: 'Fictional team note with a private PDF.' });
        await evidenceComment.getByRole('button', { name: /^Delete comment/ }).click();
        await page.locator('.ma-overlay').getByRole('button', { name: 'Cancel' }).click();
        assert.equal(await evidenceComment.count(), 1);
        await evidenceComment.getByRole('button', { name: /^Delete comment/ }).click();
        await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.locator('.ma-overlay').getByRole('button', { name: 'Delete comment' }).click()]);
        assert.equal(await evidenceComment.count(), 0);
        assert.equal((await context.request.get(fileUrl)).status(), 404);
        await page.locator('[data-shipment-comment]').first().getByRole('button', { name: /^Delete comment/ }).click();
        await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.locator('.ma-overlay').getByRole('button', { name: 'Delete comment' }).click()]);
        assert.equal(await page.locator('[data-shipment-comment]').count(), 0);
        console.log('PASS delete confirmation, cancel and private-file removal');
        const anonymous = await browser.newContext();
        const denied = await anonymous.request.get(fileUrl, { maxRedirects: 0 });
        assert.equal(denied.status(), 302);
        assert.match(denied.headers().location, /\/login$/);
        await anonymous.close();
        assert.deepEqual(errors, []);
        console.log('PASS read-only and guest access boundaries; no JavaScript errors');
        await context.close();
    } finally {
        await browser.close();
    }
}
main().catch(error => { console.error(error); process.exitCode = 1; });
