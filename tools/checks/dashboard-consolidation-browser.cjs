#!/usr/bin/env node
/* Optional real-Blade browser regression.
 * APP_ENV=testing php tools/testing/render-dashboard-analytics.php .work/dashboards
 * CHROMIUM_EXECUTABLE_PATH=... NODE_PATH=... node tools/checks/dashboard-consolidation-browser.cjs .work/dashboards
 * Fixtures are fictional/in-memory; all generated output stays ignored.
 */
'use strict';
const {chromium} = require('playwright-core');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const directory = path.resolve(process.argv[2] || path.join(root, '.work/dashboards'));
(async () => {
    const browser = await chromium.launch({executablePath: process.env.CHROMIUM_EXECUTABLE_PATH, headless: true,
        args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu']});
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.route('**/*', route => {
            const url = new URL(route.request().url());
            if (url.hostname === 'analytics.test' && !url.pathname.includes('.')) {
                const name = url.pathname === '/dashboards' ? 'directory' : url.pathname.split('/').pop();
                const file = path.join(directory, name+'.html');
                if (fs.existsSync(file)) return route.fulfill({path: file, contentType: 'text/html'});
            }
            const file = path.resolve(root, 'public', '.'+decodeURIComponent(url.pathname));
            if (file.startsWith(path.join(root, 'public')+path.sep) && fs.existsSync(file) && fs.statSync(file).isFile()) return route.fulfill({path: file});
            return route.abort();
        });
        for (const width of [1440, 900, 390]) {
            await page.setViewportSize({width, height: 1000});
            for (const module of ['directory', 'sales', 'purchase', 'projects', 'operations', 'finance', 'work']) {
                errors.length = 0;
                const pathname = module === 'directory' ? '/dashboards' : '/dashboards/'+module;
                await page.goto('http://analytics.test'+pathname+'?period_type=custom&start_date=2026-10-10&end_date=2026-10-12&currency=INR', {waitUntil:'networkidle'});
                assert.deepEqual(errors, [], module+' JavaScript errors');
                const quick = page.locator('[data-quick-menu] > button');
                assert.equal((await quick.textContent()).trim(), '');
                assert.equal(await quick.getAttribute('aria-label'), 'Quick menu');
                assert.equal(await page.locator('.topbar-actions > .theme-form,.topbar-actions > a[aria-label="Notes"]').count(), 0);
                if (module !== 'directory') {
                    const filter = page.locator('[data-drawer-open="moduleDashboardPeriodDrawer"]');
                    assert.equal((await filter.textContent()).trim(), '');
                    assert(await filter.isVisible(), 'Reporting filter is reachable on phones too');
                    assert((await filter.boundingBox()).width >= 40);
                    assert((await quick.boundingBox()).width >= 40);
                    assert((await filter.boundingBox()).x >= 0 && (await filter.boundingBox()).x + (await filter.boundingBox()).width <= width);
                    assert((await page.locator('#moduleReportingFilters').textContent()).includes('10 Oct 2026'));
                    assert(!(await page.locator('header.topbar').textContent()).includes('10 Oct 2026'));
                }

                assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth+1), module+' document overflow at '+width);
                await page.evaluate(() => document.documentElement.dataset.theme = 'light');
                assert.equal(await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--mc-card').trim()), '#ffffff');
                for (const stat of await page.locator('.module-stat-card').evaluateAll(nodes => nodes.map(node => ({top:getComputedStyle(node).borderTopColor, side:getComputedStyle(node).borderLeftColor})))) assert.notEqual(stat.top, stat.side, 'Statistics retain restrained semantic border accents');
                const surfaces = await page.locator('.module-dashboard-card,.module-stat-card,.module-directory-card,.module-directory-note,.module-dashboard-nav,.module-reporting-summary').evaluateAll(nodes => nodes.map(node => ({kind:node.className, color:getComputedStyle(node).backgroundColor})));
                assert(surfaces.length > 0);
                for (const card of surfaces) assert.equal(card.color, 'rgb(255, 255, 255)', module+' white card: '+card.kind);
                for (const card of await page.locator('.module-dashboard-card,.module-directory-card').evaluateAll(nodes => nodes.map(node => getComputedStyle(node).borderRadius))) assert.equal(card, '18px');
                assert.equal(await page.locator('a').filter({hasText:/^(ERP overview|Overview)$/}).count(), 0);
                if (module === 'directory') {
                    assert.equal(await page.locator('.module-directory-card').count(), 6);
                } else {
                    assert(await page.locator('#moduleAnalysis').isVisible());
                    assert(await page.locator('.module-analysis-panel').count() >= 5);
                    assert.equal(await page.locator('.module-trend-card').count(), 2);
                    await page.locator('a[href="#moduleAnalysis"]').click();
                    assert.equal(new URL(page.url()).hash, '#moduleAnalysis');
                    const link = new URL(await page.locator('.module-dashboard-nav a').filter({hasText:'Finance'}).getAttribute('href'));
                    assert.equal(link.searchParams.get('start_date'),'2026-10-10');
                    assert.equal(link.searchParams.get('currency'),'INR');
                }
                if (module === 'work') {
                    await quick.click();
                    await page.keyboard.press('End');
                    assert((await page.evaluate(() => document.activeElement.textContent)).includes('Notes'));
                    await page.keyboard.press('Escape');
                    assert(await quick.evaluate(node => node === document.activeElement));
                    await page.locator('.topbar-user-btn').click();
                    assert(await page.locator('.user-menu-panel:visible').getByRole('menuitem', {name:'Switch to dark'}).isVisible());
                    await page.keyboard.press('Escape');
                    await page.locator('[data-drawer-open="moduleDashboardPeriodDrawer"]').click();
                    assert.equal(await page.locator('#moduleDashboardPeriodDrawer-layer').getAttribute('aria-hidden'), 'false');
                    await page.keyboard.press('Escape');
                    assert.equal(await page.locator('#moduleDashboardPeriodDrawer-layer').getAttribute('aria-hidden'), 'true');
                }
                await page.evaluate(() => window.scrollTo(0, 0));
                if (module === 'work' && width === 1440) await page.screenshot({path:path.join(directory,'compact-work-topbar.png'),clip:{x:280,y:0,width:1160,height:330}});
                if (width === 1440 || width === 390) await page.screenshot({path:path.join(directory, module+'-'+width+'.png'),fullPage:true});
                await page.evaluate(() => document.documentElement.dataset.theme = 'dark');
                assert.equal(await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--mc-card').trim()), '#1a2540');
                const dark = await page.locator('.module-dashboard-card,.module-stat-card,.module-directory-card,.module-directory-note,.module-dashboard-nav,.module-reporting-summary').evaluateAll(nodes => nodes.map(node => ({background:getComputedStyle(node).backgroundColor, color:getComputedStyle(node).color})));
                for (const card of dark) {
                    assert.equal(card.background, 'rgb(26, 37, 64)', module+' themed dark card');
                    assert.notEqual(card.color, card.background, 'Readable dark-mode card text');
                }
                if (module === 'sales' && width === 390) await page.screenshot({path:path.join(directory,'sales-dark-390.png'),fullPage:true});
                console.log('PASS '+module+' at '+width+'px: white light cards, themed dark cards, no overflow or JS errors');
            }
        }
        await page.goto('http://analytics.test/dashboards/sales?period_type=custom&start_date=2026-10-10&end_date=2026-10-12&currency=INR',{waitUntil:'networkidle'});
        // Exercise the actual drawer and enhanced Select2 control, not an invisible select.
        await page.setViewportSize({width:1440,height:1000});
        await page.locator('[data-drawer-open="moduleDashboardPeriodDrawer"]').click();
        await page.locator('#select2-modulePeriodType-container').click();
        await page.getByRole('option', {name:'Month',exact:true}).click();
        assert.equal(await page.locator('#modulePeriodStartDate').isDisabled(), true);
        await page.locator('#select2-modulePeriodType-container').click();
        await page.getByRole('option', {name:'Custom date range',exact:true}).click();
        assert.equal(await page.locator('#modulePeriodStartDate').isDisabled(), false);
        assert.equal(await page.locator('#modulePeriodEndDate').getAttribute('min'), '2026-10-10');
        console.log('PASS reporting-period controls preserve exact dates');
        await page.keyboard.press('Escape');
        assert.equal(await page.locator('#moduleDashboardPeriodDrawer-layer').getAttribute('aria-hidden'), 'true');
        assert(await page.locator('[data-drawer-open="moduleDashboardPeriodDrawer"]').evaluate(node => node === document.activeElement));
        console.log('PASS drawer Escape closes and restores focus to reporting-period trigger');
    } finally { await browser.close(); }
})().catch(error => {console.error(error); process.exitCode=1;});
