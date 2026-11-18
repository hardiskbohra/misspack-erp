/* Interactive sales-only workflow grouping, using fictional disposable data. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { chromium } = require('playwright-core');
const base = new URL(process.env.PROJECT_ADMIN_BROWSER_BASE_URL || 'http://127.0.0.1:8144');
if (!['127.0.0.1', 'localhost', '[::1]'].includes(base.hostname)) throw new Error('Use a disposable loopback server only.');
for (const key of ['PROJECT_ADMIN_BROWSER_EMAIL', 'PROJECT_ADMIN_BROWSER_PASSWORD', 'CHROMIUM_EXECUTABLE_PATH']) {
    if (!process.env[key]) throw new Error(`Missing ${key}`);
}
const fixture = JSON.parse(fs.readFileSync('.work/project-admin-controls-fixture.json', 'utf8'));
const url = path => new URL(path, base).href;
async function navigate(page, path) {
    const target = url(path);
    const currentUrl = new URL(page.url());
    const targetUrl = new URL(target);
    const sameDocument = currentUrl.origin === targetUrl.origin && currentUrl.pathname === targetUrl.pathname && currentUrl.search === targetUrl.search;
    let response;
    if (sameDocument) {
        if (currentUrl.hash !== targetUrl.hash) await page.goto(target);
        response = await page.reload({ waitUntil: 'domcontentloaded' });
    } else {
        response = await page.goto(target, { waitUntil: 'domcontentloaded' });
    }
    assert(response && response.ok(), `${path}: ${response?.status()}`);
}
async function open(details) {
    if (!await details.evaluate(el => el.open)) await details.locator(':scope > summary').click();
}
async function main() {
    const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_EXECUTABLE_PATH, headless: true,
        args: ['--no-sandbox', '--disable-dev-shm-usage'] });
    const errors = [];
    try {
        const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
        const page = await context.newPage();
        page.on('pageerror', error => errors.push(error.message));
        await navigate(page, '/login');
        await page.locator('[name="email"]').fill(process.env.PROJECT_ADMIN_BROWSER_EMAIL);
        await page.locator('[name="password"]').fill(process.env.PROJECT_ADMIN_BROWSER_PASSWORD);
        await Promise.all([page.waitForURL(current => !current.pathname.startsWith('/login')), page.locator('[type="submit"]').click()]);
        for (const width of [1440, 390, 320]) {
            await page.setViewportSize({ width, height: 900 });
            await navigate(page, `/projects/${fixture.project_id}?tab=workflow#workflow-history`);
            const history = page.locator('#workflow-history');
            assert(await history.isVisible(), 'History is the active workflow view');
            assert.match(await history.innerText(), /16 open rules · 104 open checks/);
            assert.doesNotMatch(await history.textContent(), /Purchase-only fictional board/);
            const stage = page.locator('.project-workflow-stage').filter({ has: page.getByRole('heading', { name: 'Artwork approval', exact: true }) });
            await open(stage);
            const rule = stage.locator('[data-workflow-rule="artwork_approved"]');
            assert.equal(await rule.count(), 1);
            assert.equal(await rule.evaluate(el => el.open), false, 'Multi-product rule starts collapsed');
            assert.match(await rule.locator(':scope > summary').innerText(), /12 products · 0 of 12 checks complete/);
            await open(rule);
            const checks = rule.locator('[data-workflow-step-id]');
            assert.equal(await checks.count(), 12);
            for (const id of fixture.sales_product_ids) {
                const check = rule.locator(`[data-workflow-product-id="${id}"]`);
                assert.equal(await check.count(), 1);
                assert.match(await check.innerText(), /Current artwork approved/);
                assert((await check.innerText()).includes(`Product: ${fixture.product_names[id]} · Product #${id}`));
            }
            assert.equal(await rule.locator(`[data-workflow-product-id="${fixture.purchase_product_id}"]`).count(), 0);
            const rect = await rule.locator(':scope > summary').evaluate(el => { const r = el.getBoundingClientRect(); return { left:r.left, right:r.right, height:r.height }; });
            assert(rect.left >= -1 && rect.right <= width + 1 && rect.height >= 44, `Rule summary must fit and be touchable at ${width}px: ${JSON.stringify(rect)}`);
            const dispatchStage = page.locator('.project-workflow-stage').filter({ has: page.getByRole('heading', { name: 'Dispatch readiness', exact: true }) });
            await open(dispatchStage);
            const dispatchRule = dispatchStage.locator('[data-workflow-rule="shipment_dispatched"]');
            await open(dispatchRule);
            for (const id of [fixture.sales_product_ids[0], fixture.sales_product_ids[2]]) {
                const check = dispatchRule.locator(`[data-workflow-product-id="${id}"]`);
                assert.match(await check.innerText(), /Required quantity dispatched/);
                assert((await check.innerText()).includes(`Product: ${fixture.product_names[id]} · Product #${id}`));
            }
            const projectGate = dispatchStage.locator('[data-workflow-rule="dispatch_balance"] [data-workflow-step-id]');
            assert.match(await projectGate.innerText(), /Complete pre-dispatch balance received/);
            assert.match(await projectGate.innerText(), /Project-level control/);
            const longId = fixture.sales_product_ids[11];
            const longLabel = dispatchRule.locator(`[data-workflow-product-id="${longId}"] .project-workflow-product`);
            assert((await longLabel.innerText()).includes(fixture.product_names[longId]));
            const nameBox = await longLabel.evaluate(el => { const r = el.getBoundingClientRect(); return { left:r.left, right:r.right, scroll:el.scrollWidth, width:el.clientWidth }; });
            assert(nameBox.left >= -1 && nameBox.right <= width + 1 && nameBox.scroll <= nameBox.width + 1, `Long product name must wrap without clipping at ${width}px: ${JSON.stringify(nameBox)}`);
            assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `No page overflow at ${width}px`);
            await page.screenshot({ path: `.work/workflow-grouping-${width}.png`, fullPage: true });
            await page.locator('#workflow-tab-products').click();
            const productCards = page.locator('.workflow-product-disclosure').first().locator('.project-gate-list > .project-gate');
            assert.equal(await productCards.count(), 6);
            for (const card of await productCards.all()) {
                assert((await card.locator('.workflow-gate-product').innerText()).includes(`Product: ${fixture.product_names[fixture.sales_product_ids[0]]} · Product #${fixture.sales_product_ids[0]}`));
            }
            assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Readiness cards fit at ${width}px`);
            console.log(`PASS ${width}px: named individual gates, project-level label, long-name wrapping and no purchase-only lane`);
        }
        await page.setViewportSize({ width: 1440, height: 900 });
        await navigate(page, `/projects/${fixture.project_id}?tab=workflow#workflow-history`);
        const reopenedStage = page.locator('.project-workflow-stage').filter({ has: page.getByRole('heading', { name: 'Artwork approval', exact: true }) });
        await open(reopenedStage);
        const rule = page.locator('[data-workflow-rule="artwork_approved"]');
        await open(rule);
        const first = rule.locator(`[data-workflow-product-id="${fixture.sales_product_ids[0]}"]`);
        const second = rule.locator(`[data-workflow-product-id="${fixture.sales_product_ids[1]}"]`);
        const action = first.locator('.project-admin-bypass details').first();
        await open(action);
        await action.locator('[name="reason"]').fill('Administrator accepts this specific artwork risk.');
        await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), action.getByRole('button', { name: 'Record bypass' }).click()]);
        const stage = page.locator('.project-workflow-stage').filter({ has: page.getByRole('heading', { name: 'Artwork approval', exact: true }) });
        await open(stage); await open(rule);
        assert.match(await first.innerText(), /Administrator bypass/);
        assert.doesNotMatch(await second.innerText(), /Administrator bypass by/);
        assert.match(await page.locator('#workflow-history').innerText(), /16 open rules · 103 open checks/);
        console.log('PASS separate bypass: first product changed, second retained its own decision');
        assert.deepEqual(errors, [], 'No uncaught browser JavaScript errors');
        await context.close();
    } finally { await browser.close(); }
}
main().catch(error => { console.error(error); process.exitCode = 1; });
