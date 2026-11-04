/* Interactive stock workflow on a disposable, loopback-only test fixture. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { chromium } = require('playwright-core');

const base = new URL(process.env.STOCK_BROWSER_BASE_URL || 'http://127.0.0.1:8155');
if (!['127.0.0.1', 'localhost', '[::1]'].includes(base.hostname)) throw new Error('Use a disposable loopback server only.');
for (const key of ['STOCK_BROWSER_PASSWORD', 'CHROMIUM_EXECUTABLE_PATH']) if (!process.env[key]) throw new Error(`Missing ${key}`);
const fixture = JSON.parse(fs.readFileSync('.work/stock-browser-fixture.json', 'utf8'));
const url = path => new URL(path, base).href;

async function visit(page, path) {
    const response = await page.goto(url(path), { waitUntil: 'domcontentloaded' });
    assert(response && response.ok(), `${path}: HTTP ${response?.status()}`);
}
async function login(page, email) {
    await visit(page, '/login');
    await page.locator('[name="email"]').fill(email);
    await page.locator('[name="password"]').fill(process.env.STOCK_BROWSER_PASSWORD);
    await Promise.all([page.waitForURL(current => !current.pathname.startsWith('/login')), page.locator('[type="submit"]').click()]);
}
async function submit(page, selector) {
    await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.locator(selector).click()]);
}
async function dismissValidation(page) {
    await page.locator('.ma-overlay').getByRole('button', { name: 'OK' }).click();
    await page.locator('.ma-overlay').waitFor({ state: 'detached' });
}
async function select(page, id, text) {
    await page.locator(`${id} + .select2-container .select2-selection`).click();
    await page.locator('.select2-results__option').filter({ hasText: text }).first().click();
}
async function geometry(page, path, width) {
    await page.setViewportSize({ width, height: 900 });
    await visit(page, path);
    await page.waitForTimeout(400); // shared Select2 settles after DOMContentLoaded
    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${path} overflows at ${width}px`);
    assert(await page.locator('.stock').isVisible(), `${path} has a visible stock section at ${width}px`);
    if (width < 768) {
        const tables = await page.locator('.stock table.ui-mobile-cards:visible').evaluateAll(nodes =>
            nodes.map(table => ({ table: table.scrollWidth, wrapper: table.parentElement.clientWidth })));
        for (const table of tables) assert(table.table <= table.wrapper + 1,
            `${path} cards overflow their wrapper at ${width}px: ${JSON.stringify(table)}`);
    }
}
async function createCount(page, itemId, name) {
    await visit(page, '/stock/counts/create');
    await page.locator('#countTitle').fill(name);
    await select(page, '#countItems', 'Fictional stock bottle');
    assert.deepEqual(await page.locator('#countItems').evaluate(el => Array.from(el.selectedOptions, option => option.value)), [String(itemId)]);
    await submit(page, 'button:has-text("Create count and snapshot")');
    const path = new URL(page.url()).pathname;
    assert.match(path, /^\/stock\/counts\/\d+$/);
    return path;
}
async function enterCount(page, qty, reason) {
    await page.locator('.stock-count-input').fill(qty);
    await page.locator('.stock-count-reason').fill(reason);
}
async function confirmAndSubmit(page, button, confirmation) {
    await page.getByRole('button', { name: button, exact: true }).click();
    await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
        page.locator('.ma-overlay').getByRole('button', { name: confirmation, exact: true }).click()]);
}

async function main() {
    const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_EXECUTABLE_PATH, headless: true,
        args: ['--no-sandbox', '--disable-dev-shm-usage'] });
    const errors = [];
    try {
        const counter = await browser.newContext({ viewport: { width: 1440, height: 900 } });
        const page = await counter.newPage();
        page.on('pageerror', error => errors.push(error.message));
        await login(page, 'stock-counter-browser@example.test');
        for (const width of [1440, 768, 390, 320]) {
            for (const path of ['/stock', '/stock/items/create', '/stock/counts', '/stock/counts/create']) await geometry(page, path, width);
            console.log(`PASS ${width}px: register, opening and count creation layouts`);
        }
        await page.setViewportSize({ width: 1440, height: 900 });
        await visit(page, '/stock/items/create');
        await select(page, '#stockProduct', 'Fictional stock bottle');
        assert.equal(await page.locator('#stockProduct').inputValue(), String(fixture.product_id));
        await page.locator('#stockVariant').fill('Amber 100 ml');
        await page.locator('#stockBatch').fill('LOT-B-01');
        await page.locator('#stockExpiry').fill('2027-01-31');
        await page.locator('#stockOpening').fill('10.125');
        await page.locator('#stockReason').fill('Opening shelf verification');
        await submit(page, 'button:has-text("Record opening stock")');
        const itemPath = new URL(page.url()).pathname;
        assert.match(itemPath, /^\/stock\/items\/\d+$/);
        const itemId = Number(itemPath.split('/').pop());
        assert.match(await page.locator('.stock-facts').innerText(), /10\.125/);
        assert.match(await page.locator('.stock-table').innerText(), /Opening shelf verification/);

        await select(page, '#movementType', 'Issue');
        await page.locator('#movementQuantity').fill('99');
        await page.locator('#movementReason').fill('Too many');
        await submit(page, 'button:has-text("Record movement")');
        assert.match(await page.locator('[role="alert"]').first().innerText(), /exceeds the current stock balance/i);
        assert.equal(await page.locator('#movementQuantity').inputValue(), '99');
        await dismissValidation(page);
        await select(page, '#movementType', 'Receipt');
        await page.locator('#movementQuantity').fill('2.375');
        await page.locator('#movementReason').fill('Fictional receipt');
        await submit(page, 'button:has-text("Record movement")');
        assert.match(await page.locator('.stock-facts').innerText(), /12\.500/);
        await select(page, '#movementType', 'Issue');
        await page.locator('#movementQuantity').fill('1');
        await page.locator('#movementReason').fill('Fictional issue');
        await submit(page, 'button:has-text("Record movement")');
        assert.match(await page.locator('.stock-facts').innerText(), /11\.500/);
        console.log('PASS opening, exact receipt/issue arithmetic, and over-issue validation');

        const countPath = await createCount(page, itemId, 'Browser physical count');
        for (const width of [1440, 768, 390, 320]) {
            await geometry(page, itemPath, width);
            await geometry(page, countPath, width);
            await page.screenshot({ path: `.work/stock-count-${width}.png`, fullPage: true });
            console.log(`PASS ${width}px: ledger and editable count layouts`);
        }
        await page.setViewportSize({ width: 1440, height: 900 });
        await visit(page, countPath);
        const stale = await counter.newPage();
        stale.on('pageerror', error => errors.push(error.message));
        await visit(stale, countPath);
        await enterCount(stale, '9', 'Unsaved old form');
        await enterCount(page, '10.500', 'One damaged unit');
        await submit(page, 'button:has-text("Save draft")');
        await submit(stale, 'button:has-text("Save draft")');
        assert.match(await stale.locator('[role="alert"]').first().innerText(), /count changed since you opened/i);
        assert.equal(await stale.locator('.stock-count-input').inputValue(), '10.500');
        await dismissValidation(stale);
        await stale.locator('summary:has-text("View your unsaved entries")').click();
        assert.match(await stale.locator('details.stock-alert').innerText(), /9.*Unsaved old form/s);
        await stale.close();
        await submit(page, 'button:has-text("Submit for independent review")');
        assert.match(await page.locator('.stock').innerText(), /different operations manager/i);
        assert.equal(await page.getByRole('button', { name: 'Approve and post', exact: true }).count(), 0);
        const csrf = await page.locator('input[name="_token"]').first().inputValue();
        const revision = await page.locator('input[name="revision"]').first().inputValue();
        const selfReview = await counter.request.post(url(`${countPath}/approve`), { form: { _token: csrf, revision, reason: 'Self approval' } });
        assert.equal(selfReview.status(), 403);
        console.log('PASS stale form recovery and self-approval prohibition');

        const reviewer = await browser.newContext({ viewport: { width: 1440, height: 900 } });
        const reviewPage = await reviewer.newPage();
        reviewPage.on('pageerror', error => errors.push(error.message));
        await login(reviewPage, 'stock-reviewer-browser@example.test');
        for (const width of [1440, 768, 390, 320]) await geometry(reviewPage, countPath, width);
        await reviewPage.setViewportSize({ width: 1440, height: 900 });
        await visit(reviewPage, countPath);
        assert.match(await reviewPage.locator('.stock').innerText(), /10\.500/);
        await reviewPage.locator('#approveReason').fill('Verified physical count evidence');
        await confirmAndSubmit(reviewPage, 'Approve and post', 'Approve and post');
        assert.match(await reviewPage.locator('.stock').innerText(), /Posted/);
        assert.match(await reviewPage.locator('.stock-events').innerText(), /Stock Reviewer/);
        assert.match(await reviewPage.locator('.stock-events').innerText(), /Verified physical count evidence/);
        await visit(page, itemPath);
        assert.match(await page.locator('.stock-facts').innerText(), /10\.500/);
        assert.match(await page.locator('.stock-table').innerText(), /Count adjustment/);
        console.log('PASS independent review, audited adjustment and reviewer layouts');

        const second = await createCount(page, itemId, 'Browser recount');
        await enterCount(page, '10.500', '');
        await submit(page, 'button:has-text("Submit for independent review")');
        await visit(page, itemPath);
        await page.locator('#movementQuantity').fill('1');
        await page.locator('#movementReason').fill('Receipt after snapshot');
        await submit(page, 'button:has-text("Record movement")');
        await visit(reviewPage, second);
        assert.match(await reviewPage.locator('.stock').innerText(), /Recount required/);
        await reviewPage.locator('#approveReason').fill('Cannot approve stale snapshot');
        await confirmAndSubmit(reviewPage, 'Approve and post', 'Approve and post');
        assert.match(await reviewPage.locator('[role="alert"]').first().innerText(), /Stock changed after the snapshot/i);
        await dismissValidation(reviewPage);
        await reviewPage.locator('#rejectReason').fill('Stock moved; recount required');
        await submit(reviewPage, 'button:has-text("Return to counter")');
        await visit(page, second);
        await confirmAndSubmit(page, 'Refresh book balances', 'Refresh for recount');
        assert.equal(await page.locator('.stock-count-input').inputValue(), '');
        assert.match(await page.locator('.stock-count-table').innerText(), /11\.500/);
        await enterCount(page, '11.500', '');
        await submit(page, 'button:has-text("Submit for independent review")');
        await visit(reviewPage, second);
        await reviewPage.locator('#approveReason').fill('Recount verified');
        await confirmAndSubmit(reviewPage, 'Approve and post', 'Approve and post');
        assert.match(await reviewPage.locator('.stock').innerText(), /Posted/);
        assert.match(await reviewPage.locator('.stock-events').innerText(), /Stock moved; recount required/);
        console.log('PASS stale ledger blocks approval, return, refresh and independently approved recount');

        // Edit, movement and archive checks for the later register-controls release.
        // Exercise stock correction, independent review, guarded archive and restore.
        const editPath = `${itemPath}/edit`;
        for (const width of [1440, 768, 390, 320]) {
            await geometry(page, editPath, width);
            await geometry(page, `${itemPath}#stockArchive`, width);
        }
        await page.setViewportSize({ width: 1440, height: 900 });
        const staleMovement = await counter.newPage();
        staleMovement.on('pageerror', error => errors.push(error.message));
        await visit(staleMovement, itemPath);
        await select(staleMovement, '#movementType', 'Issue');
        await staleMovement.locator('#movementQuantity').fill('2');
        await staleMovement.locator('#movementReason').fill('Unsubmitted old identity movement');
        const staleEdit = await counter.newPage();
        staleEdit.on('pageerror', error => errors.push(error.message));
        await visit(staleEdit, editPath);
        await staleEdit.locator('#stockVariant').fill('Unsaved old variant');
        await staleEdit.locator('#stockEditReason').fill('Old edit form');
        await visit(page, editPath);
        await page.locator('#stockVariant').fill('Amber corrected');
        await page.locator('#stockEditReason').fill('Corrected the descriptive variant');
        await submit(page, 'button:has-text("Save changes")');
        assert.match(await page.locator('.stock-table').innerText(), /Amber 100 ml/);
        assert.match(await page.locator('.stock-events').innerText(), /Corrected the descriptive variant/);
        await submit(staleMovement, 'button:has-text("Record movement")');
        assert.match(await staleMovement.locator('[role="alert"]').first().innerText(), /item changed since you opened/i);
        await dismissValidation(staleMovement);
        await staleMovement.locator('summary:has-text("View your unsaved movement")').click();
        assert.match(await staleMovement.locator('details.stock-alert').innerText(), /Unsubmitted old identity movement/);
        assert.equal(await staleMovement.locator('#movementQuantity').inputValue(), '');
        await submit(staleEdit, 'button:has-text("Save changes")');
        assert.match(await staleEdit.locator('[role="alert"]').first().innerText(), /item or its balance changed/i);
        await dismissValidation(staleEdit);
        await staleEdit.locator('summary:has-text("View your unsaved entries")').click();
        assert.match(await staleEdit.locator('details.stock-alert').innerText(), /Unsaved old variant/);
        assert.equal(await staleEdit.locator('#stockVariant').inputValue(), 'Amber corrected');
        await staleMovement.close(); await staleEdit.close();

        await visit(page, editPath);
        await page.locator('#stockRequestedQuantity').fill('9.500');
        await page.locator('#stockEditReason').fill('Physical correction request');
        await submit(page, 'button:has-text("Save changes")');
        const correctionPath = new URL(page.url()).pathname;
        assert.match(correctionPath, /^\/stock\/counts\/\d+$/);
        assert.match(await page.locator('.stock-count-table').innerText(), /11\.500[\s\S]*9\.500/);
        assert.equal(await page.getByRole('button', { name: 'Approve and post', exact: true }).count(), 0);
        await visit(reviewPage, correctionPath);
        await reviewPage.locator('#approveReason').fill('Verified correction evidence');
        await confirmAndSubmit(reviewPage, 'Approve and post', 'Approve and post');
        await visit(page, itemPath);
        assert.match(await page.locator('.stock-facts').innerText(), /9\.500/);
        assert.match(await page.locator('.stock-events').innerText(), /Physical correction request/);

        const archiveCount = await createCount(page, itemId, 'Count blocks archive');
        await visit(page, `${itemPath}#stockArchive`);
        assert.match(await page.locator('#stockArchive').innerText(), /Finish or cancel the open counts/);
        assert.equal(await page.getByRole('button', { name: 'Archive item', exact: true }).count(), 0);
        await visit(page, archiveCount);
        await page.locator('summary:has-text("Cancel this count")').click();
        await page.locator('#cancelReason').fill('Count no longer required');
        await confirmAndSubmit(page, 'Cancel count', 'Cancel count');
        await visit(page, `${itemPath}#stockArchive`);
        assert.equal(await page.locator('#stockArchive').evaluate(el => el.open), true);
        await page.locator('#archiveReason').fill('Retire the duplicate register record');
        await page.locator('[name="acknowledge_nonzero"]').check();
        await confirmAndSubmit(page, 'Archive item', 'Archive item');
        assert.match(await page.locator('.stock').innerText(), /This item is archived/);
        assert.equal(await page.getByRole('button', { name: 'Record movement' }).count(), 0);
        await visit(page, '/stock');
        assert.match(await page.locator('.stock').innerText(), /archived with stock/);
        await visit(page, '/stock?status=archived');
        assert.match(await page.locator('.stock-table').innerText(), /Fictional stock bottle[\s\S]*9\.500/);
        await visit(page, `${itemPath}#stockRestore`);
        assert.equal(await page.locator('#stockRestore').evaluate(el => el.open), true);
        await page.locator('#restoreReason').fill('Restore after review');
        await confirmAndSubmit(page, 'Restore item', 'Restore item');
        assert.match(await page.locator('.stock-facts').innerText(), /9\.500/);
        assert.match(await page.locator('.stock-events').innerText(), /Restore after review/);
        console.log('PASS correction review, stale recovery, guarded archive and restoration');

        const reader = await browser.newContext({ viewport: { width: 390, height: 900 } });
        const readerPage = await reader.newPage();
        readerPage.on('pageerror', error => errors.push(error.message));
        await login(readerPage, 'stock-reader-browser@example.test');
        await visit(readerPage, itemPath);
        assert.equal(await readerPage.getByRole('button', { name: 'Record movement' }).count(), 0);
        assert.equal(await readerPage.getByRole('link', { name: 'Edit item' }).count(), 0);
        assert.equal(await readerPage.locator('#stockArchive').count(), 0);
        assert.equal((await readerPage.goto(url('/stock/items/create'))).status(), 403);
        assert.equal((await readerPage.goto(url(editPath))).status(), 403);
        await visit(readerPage, second);
        assert.equal(await readerPage.getByRole('button', { name: 'Approve and post' }).count(), 0);
        console.log('PASS read-only office access');
        assert.deepEqual(errors, [], `Browser JavaScript errors: ${errors.join('; ')}`);
        await reader.close(); await reviewer.close(); await counter.close();
    } finally {
        await browser.close();
    }
}
main().catch(error => { console.error(error); process.exitCode = 1; });
