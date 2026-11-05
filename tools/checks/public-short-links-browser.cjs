/* Live browser regression against a DISPOSABLE loopback instance only.
 * Needs playwright-core, jsqr, pngjs, Chromium, the seeded .work fixture, and
 * SHORT_LINK_TEST_BASE_URL / SHORT_LINK_TEST_PASSWORD / CHROMIUM_EXECUTABLE_PATH.
 * No external service is contacted. See docs/testing/public-short-links.md.
 */
'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const { chromium } = require('playwright-core');
const jsQR = require('jsqr');
const { PNG } = require('pngjs');

const base = new URL(process.env.SHORT_LINK_TEST_BASE_URL || 'http://127.0.0.1:8136');
if (!['127.0.0.1', 'localhost', '[::1]'].includes(base.hostname)) throw new Error('Use a disposable loopback server only.');
const password = process.env.SHORT_LINK_TEST_PASSWORD;
if (!password || !process.env.CHROMIUM_EXECUTABLE_PATH) throw new Error('Supply a test-only password and Chromium path.');
const fixture = JSON.parse(fs.readFileSync(process.env.SHORT_LINK_FIXTURE || '.work/public-short-links-fixture.json', 'utf8'));
const url = path => new URL(path, base).href;

async function sameOriginOnly(context) {
    await context.route('**/*', route => {
        const requested = new URL(route.request().url());
        return requested.origin === base.origin ? route.continue() : route.abort();
    });
}

async function copyInput(page, id, type) {
    const input = page.locator('#' + id);
    const value = await input.inputValue();
    assert.match(value, new RegExp('^' + base.origin.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '/s/' + type + '[1-9][0-9]*\\.[A-Za-z0-9_-]{22}$'));
    await page.waitForFunction(() => typeof window.maCopy === 'function');
    await input.locator('..').getByRole('button', { name: 'Copy', exact: true }).click();
    await page.waitForFunction(expected => navigator.clipboard.readText().then(text => text === expected), value);
    assert.equal(await page.evaluate(() => navigator.clipboard.readText()), value);
    return value;
}

async function submitDraft(browser, page, link, field, value) {
    await page.goto(link, { waitUntil: 'domcontentloaded' });
    const action = await page.locator('form[data-kyc-form]').getAttribute('action');
    assert.equal(new URL(action).origin, base.origin);
    assert(await page.locator('form[data-kyc-form] [name="_token"]').inputValue());
    // Laravel accepts same-origin Fetch Metadata, so use a cross-site request
    // without a token to exercise the actual forgery protection boundary.
    const noCsrf = await page.request.post(action, {
        form: { intent: 'draft' }, headers: { 'Sec-Fetch-Site': 'cross-site' }, maxRedirects: 0,
    });
    assert.equal(noCsrf.status(), 419, 'Cross-site canonical KYC submissions must still enforce CSRF');
    await page.locator('#' + field).fill(value);
    const [post] = await Promise.all([
        page.waitForResponse(response => response.url() === action && response.request().method() === 'POST'),
        page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
        page.locator('[data-kyc-draft]').click(),
    ]);
    assert.equal(post.status(), 302, 'Draft should POST to the canonical handler');

    // A fresh browser context proves the value was saved on the server, not
    // restored from the client KYC form's localStorage draft.
    const verification = await browser.newContext();
    await sameOriginOnly(verification);
    try {
        const fresh = await verification.newPage();
        await fresh.goto(link, { waitUntil: 'domcontentloaded' });
        assert.equal(await fresh.locator('#' + field).inputValue(), value);
    } finally {
        await verification.close();
    }
}

async function main() {
    const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_EXECUTABLE_PATH,
        headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage'] });
    const office = await browser.newContext({ deviceScaleFactor: 3, permissions: ['clipboard-read', 'clipboard-write'] });
    const guest = await browser.newContext({ deviceScaleFactor: 3, permissions: ['clipboard-read', 'clipboard-write'] });
    await sameOriginOnly(office); await sameOriginOnly(guest);
    const page = await office.newPage();
    const publicPage = await guest.newPage();
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    publicPage.on('pageerror', e => errors.push(e.message));
    try {
        await page.goto(url('/login'), { waitUntil: 'domcontentloaded' });
        await page.locator('[name="email"]').fill('short-browser@example.test');
        await page.locator('[name="password"]').fill(password);
        await Promise.all([page.waitForURL(value => value.pathname !== '/login'), page.locator('[type="submit"]').click()]);

        let clientLink, vendorLink, trackingLink;
        for (const width of [1440, 390]) {
            await page.setViewportSize({ width, height: 950 });
            await publicPage.setViewportSize({ width, height: 950 });
            await page.goto(url('/clients/' + fixture.client + '?tab=kyc'), { waitUntil: 'domcontentloaded' });
            clientLink = await copyInput(page, 'clientKycLink', 'c');
            await publicPage.goto(clientLink, { waitUntil: 'domcontentloaded' });
            assert.match(new URL(publicPage.url()).pathname, /^\/client-kyc\/[A-Za-z0-9]+$/);
            assert.match(await publicPage.locator('form[data-kyc-form]').getAttribute('action'), /\/client-kyc\//);
            assert.equal(await publicPage.locator('#company_name').inputValue(), 'Browser Short Client');

            await page.goto(url('/vendors/' + fixture.vendor + '?tab=kyc'), { waitUntil: 'domcontentloaded' });
            vendorLink = await copyInput(page, 'vendorKycLink', 'v');
            await publicPage.goto(vendorLink, { waitUntil: 'domcontentloaded' });
            assert.match(new URL(publicPage.url()).pathname, /^\/vendor-kyc\/[A-Za-z0-9]+$/);
            assert.match(await publicPage.locator('form[data-kyc-form]').getAttribute('action'), /\/vendor-kyc\//);
            assert.equal(await publicPage.locator('#vendor_name').inputValue(), 'Browser Short Vendor');

            await page.goto(url('/shipments/' + fixture.shipment), { waitUntil: 'domcontentloaded' });
            trackingLink = await copyInput(page, 'publicTrackingLink', 'h');
            await publicPage.goto(trackingLink, { waitUntil: 'domcontentloaded' });
            assert.match(new URL(publicPage.url()).pathname, /^\/track-shipment\/[A-Za-z0-9]+$/);
            assert.equal(await publicPage.locator('.track-share-copy').getAttribute('data-copy'), trackingLink);
            const whatsapp = await publicPage.locator('.track-share-wa').getAttribute('href');
            assert(new URL(whatsapp).searchParams.get('text').includes(trackingLink));
            await publicPage.locator('.track-share-copy').click();
            await publicPage.waitForFunction(expected => navigator.clipboard.readText().then(text => text === expected), trackingLink);
            assert.equal(await publicPage.evaluate(() => navigator.clipboard.readText()), trackingLink);
        }

        await submitDraft(browser, publicPage, clientLink, 'company_name', 'Browser Short Client Draft');
        await submitDraft(browser, publicPage, vendorLink, 'vendor_name', 'Browser Short Vendor Draft');

        await page.setViewportSize({ width: 1440, height: 1100 });
        await page.goto(url('/shipments/' + fixture.shipment + '/shipping-mark'), { waitUntil: 'domcontentloaded' });
        const qr = page.locator('[data-ship-qr]').first();
        assert.equal(await qr.getAttribute('data-ship-qr'), trackingLink);
        await qr.locator('svg').waitFor({ state: 'visible' });
        const image = PNG.sync.read(await qr.screenshot());
        const decoded = jsQR(new Uint8ClampedArray(image.data), image.width, image.height);
        assert(decoded, 'The rendered shipping-mark QR must actually decode');
        assert.equal(decoded.data, trackingLink);
        await publicPage.goto(decoded.data, { waitUntil: 'domcontentloaded' });
        assert.match(new URL(publicPage.url()).pathname, /^\/track-shipment\//);

        for (const [short, action] of [['/s/e', '/lead-enquiry'], ['/s/c', '/client-portal/login'], ['/s/v', '/vendor-portal/login']]) {
            await publicPage.goto(url(short), { waitUntil: 'domcontentloaded' });
            assert.equal(new URL(publicPage.url()).pathname, short);
            assert.equal(new URL(await publicPage.locator('form').first().getAttribute('action'), base).pathname, action);
        }
        await publicPage.goto(url('/client-portal/kyc'), { waitUntil: 'domcontentloaded' });
        assert.equal(new URL(publicPage.url()).pathname, '/client-portal/login');
        await publicPage.locator('[name="login"]').fill('short-client');
        await publicPage.locator('[name="password"]').fill(password);
        await Promise.all([publicPage.waitForURL(value => value.pathname === '/client-portal'), publicPage.locator('[type="submit"]').click()]);
        await publicPage.goto(url('/client-portal/kyc'), { waitUntil: 'domcontentloaded' });
        assert.equal(await publicPage.locator('#kycLink').inputValue(), clientLink);
        await publicPage.getByRole('button', { name: 'Copy link', exact: true }).click();
        await publicPage.waitForFunction(expected => navigator.clipboard.readText().then(text => text === expected), clientLink);
        assert.deepEqual(errors, [], 'Unexpected browser JavaScript errors');
        console.log('PASS short links: desktop/mobile office copy, guest redirects, canonical KYC draft/CSRF, tracking/WhatsApp copy, decoded shipping-mark QR, static forms and authenticated portal KYC copy.');
    } finally {
        await browser.close();
    }
}
main().catch(error => { console.error(error); process.exitCode = 1; });
