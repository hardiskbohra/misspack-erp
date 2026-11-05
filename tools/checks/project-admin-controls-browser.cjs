/* Real interactions against the guarded disposable project fixture. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { chromium } = require('playwright-core');
const base = new URL(process.env.PROJECT_ADMIN_BROWSER_BASE_URL || 'http://127.0.0.1:8144');
if (!['127.0.0.1', 'localhost', '[::1]'].includes(base.hostname)) throw new Error('Use a disposable loopback server only.');
for (const key of ['PROJECT_ADMIN_BROWSER_EMAIL', 'PROJECT_ADMIN_BROWSER_PASSWORD', 'CHROMIUM_EXECUTABLE_PATH']) {
    if (!process.env[key]) throw new Error(`Missing ${key}`);
}
const { project_id: id } = JSON.parse(fs.readFileSync('.work/project-admin-controls-fixture.json', 'utf8'));
const url = path => new URL(path, base).href;
const overview = `/projects/${id}?tab=overview`;
const workflow = `/projects/${id}?tab=workflow#workflow-history`;
async function navigate(page, path) {
    const response = await page.goto(url(path), { waitUntil: 'domcontentloaded' });
    assert(response && response.ok(), `Expected ${path} to load successfully, got ${response?.status()}`);
}
async function submit(page, button) {
    await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), button.click()]);
    assert.equal(await page.getByText('Validation Error', { exact: true }).count(), 0, 'No validation modal should appear');
}
async function login(page, email) {
    await navigate(page, '/login');
    await page.locator('[name="email"]').fill(email);
    await page.locator('[name="password"]').fill(process.env.PROJECT_ADMIN_BROWSER_PASSWORD);
    await Promise.all([page.waitForURL(current => !current.pathname.startsWith('/login')), page.locator('[type="submit"]').click()]);
}
async function assertFits(page, selectors, width) {
    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `No page-wide overflow at ${width}px`);
    for (const selector of selectors) {
        const rect = await page.locator(selector).first().evaluate(node => {
            const r = node.getBoundingClientRect(); return { left: r.left, right: r.right, height: r.height, width: r.width };
        });
        assert(rect.left >= -1 && rect.right <= width + 1 && rect.width > 30 && rect.height >= 40,
            `${selector} must be an unclipped touch target at ${width}px: ${JSON.stringify(rect)}`);
    }
}
async function ensureOpen(details) {
    if (!await details.evaluate(node => node.open)) await details.locator(':scope > summary').click();
}
async function main() {
    const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_EXECUTABLE_PATH, headless: true,
        args: ['--no-sandbox', '--disable-dev-shm-usage'] });
    const errors = [];
    try {
        const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
        const page = await context.newPage();
        page.on('pageerror', error => errors.push(error.message));
        await login(page, process.env.PROJECT_ADMIN_BROWSER_EMAIL);
        await navigate(page, overview);
        const admin = page.locator('#project-admin-controls');
        assert(await admin.isVisible());
        assert.match(await admin.innerText(), /Calculated workflow/);
        // Use the visible Select2 UI, not a programmatic select change.
        await admin.locator('#project-admin-status + .select2-container .select2-selection').click();
        await page.locator('.select2-results__option').getByText('On Hold', { exact: true }).click();
        await page.locator('#project-admin-progress').fill('73');
        await page.locator('#project-admin-reason').fill('Administrator reviewed the execution status.');
        await submit(page, admin.getByRole('button', { name: 'Save administrator state' }));
        assert.match(await page.locator('.project-record-progress-value').innerText(), /73%.*Administrator-set/);
        assert.match(await page.locator('#project-progress-heading').locator('..').innerText(), /Administrator-set/);
        assert.match(await admin.innerText(), /Calculated workflow/);
        await navigate(page, '/projects');
        assert.match(await page.locator('tr').filter({ hasText: 'BROWSER-PROJECT-CONTROL' }).innerText(), /73%/);

        for (const width of [1440, 390, 320]) {
            await page.setViewportSize({ width, height: 900 });
            await navigate(page, overview);
            assert.match(await admin.innerText(), /73%/);
            await assertFits(page, ['#project-admin-progress', '#project-admin-reason', '.project-admin-state-form button', '.project-admin-closure > summary'], width);
            const close = admin.locator('.project-admin-closure');
            await ensureOpen(close);
            assert.match(await close.innerText(), /obligations remain active/i);
            await assertFits(page, ['#project-manual-close-reason', '.project-admin-closure form button'], width);
            await page.screenshot({ path: `.work/project-admin-overview-${width}.png`, fullPage: true });
            console.log(`PASS ${width}px: admin state, visible closure risks and touch geometry`);
        }
        await page.setViewportSize({ width: 1440, height: 900 });
        await navigate(page, workflow);
        const setup = page.locator('.project-workflow-stage').filter({ has: page.getByRole('heading', { name: 'Project setup', exact: true }) });
        await ensureOpen(setup);
        let action = setup.locator('.project-admin-bypass details').first();
        await ensureOpen(action);
        await action.locator('[name="reason"]').fill('Administrator accepts this isolated setup risk.');
        await submit(page, action.getByRole('button', { name: 'Record bypass' }));
        await ensureOpen(setup);
        assert.match(await setup.innerText(), /Administrator bypass by Browser Administrator/);
        assert.match(await setup.innerText(), /accepts this isolated setup risk/);
        action = setup.locator('.project-admin-bypass details').first();
        await ensureOpen(action);
        await action.locator('[name="revocation_reason"]').fill('The setup risk is withdrawn.');
        await submit(page, action.getByRole('button', { name: 'Revoke bypass' }));
        await ensureOpen(setup);
        assert.match(await setup.innerText(), /Administrator bypass/);
        console.log('PASS workflow: individual reason, visible actor and auditable revocation');

        await navigate(page, overview);
        const close = admin.locator('.project-admin-closure');
        await ensureOpen(close);
        await page.locator('#project-manual-close-reason').fill('Outstanding work and the invoice remain with the accounts team.');
        await close.locator('[name="risk_acknowledgement"]').check();
        await submit(page, close.getByRole('button', { name: 'Record decision and close' }));
        assert.match(await page.locator('.project-record-header').innerText(), /Manually closed/i);
        assert.match(await page.locator('body').innerText(), /125\.57/);
        await page.screenshot({ path: '.work/project-admin-manually-closed.png', fullPage: true });

        // The published value, not only the calculated workflow, is visible to the client.
        const portal = await browser.newContext({ viewport: { width: 390, height: 900 } });
        try {
            const clientPage = await portal.newPage();
            clientPage.on('pageerror', error => errors.push(error.message));
            await navigate(clientPage, '/client-portal/login');
            await clientPage.locator('[name="login"]').fill('project-browser-client');
            await clientPage.locator('[name="password"]').fill(process.env.PROJECT_ADMIN_BROWSER_PASSWORD);
            await Promise.all([clientPage.waitForURL(current => !current.pathname.endsWith('/login')), clientPage.locator('[type="submit"]').click()]);
            await navigate(clientPage, '/client-portal/projects');
            assert.match(await clientPage.locator('.cp-project-card').innerText(), /73%/);
            assert.match(await clientPage.locator('.cp-project-card').innerText(), /Manually closed/i);
            await navigate(clientPage, `/client-portal/projects/${id}`);
            assert.match(await clientPage.locator('body').innerText(), /73%/);
            assert.doesNotMatch(await clientPage.locator('body').innerText(), /Outstanding work and the invoice remain with the accounts team/);
            console.log('PASS portal: published progress and status, private reason stays private');
        } finally { await portal.close(); }

        await navigate(page, overview);
        const reopen = admin.locator('.project-admin-closure');
        await ensureOpen(reopen);
        await page.locator('#project-admin-reopen-reason').fill('The project needs follow-up work.');
        await submit(page, reopen.getByRole('button', { name: 'Reopen with audit record' }));
        assert.doesNotMatch(await page.locator('.project-record-header').innerText(), /Manually closed/i);
        assert.match(await page.locator('.project-record-progress-value').innerText(), /73%/);
        await navigate(page, `/projects/${id}?tab=logs`);
        assert.match(await page.locator('body').innerText(), /Administrator changed project state/);
        assert.match(await page.locator('body').innerText(), /Administrator revoked workflow bypass/);
        assert.match(await page.locator('body').innerText(), /Project manually closed/);
        console.log('PASS closure: preserved balance, reopen and internal audit history');

        const limited = await browser.newContext({ viewport: { width: 390, height: 900 } });
        try {
            const limitedPage = await limited.newPage();
            await login(limitedPage, 'limited-project-browser@example.test');
            await navigate(limitedPage, overview);
            assert.equal(await limitedPage.locator('#project-admin-controls').count(), 0);
            await navigate(limitedPage, workflow);
            assert.equal(await limitedPage.getByText('Administrator bypass', { exact: true }).count(), 0);
            console.log('PASS limited operator: no administrator controls');
        } finally { await limited.close(); }
        assert.deepEqual(errors, [], 'No uncaught browser JavaScript errors');
    } finally { await browser.close(); }
}
main().catch(error => { console.error(error); process.exit(1); });
