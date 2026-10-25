#!/usr/bin/env node
/* Real Blade output + Chromium PDFs. First run tools/testing/render-delivery-receipts.php.
 * Requires playwright-core, Chromium (CHROMIUM_EXECUTABLE_PATH), Python + pymupdf.
 * NODE_PATH/PYTHONPATH can reference temporary installs. All artifacts stay outside Git.
 * Usage: node tools/checks/delivery-receipt-print-browser.cjs .work/receipts
 */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {spawnSync} = require('node:child_process');
const {chromium} = require('playwright-core');
const root = path.resolve(__dirname, '../..');
const directory = path.resolve(process.argv[2] || path.join(root, '.work/receipts'));
const pdfCheck = String.raw`
import sys, pymupdf
case = sys.argv[2]
doc = pymupdf.open(sys.argv[1])
text = '\n'.join(page.get_text() for page in doc)
for hidden in ['Record integrity', 'SHA-256', 'Internal audit operator', 'internal-evidence.pdf', 'LARAVEL']:
    assert hidden not in text, hidden
for required in ['Receiver acknowledgement', 'Receiver signature', 'Name / designation:', 'Date / time:', 'Company stamp']:
    assert required in text, required
assert len(doc) == 1 if case != 'long' else len(doc) > 1
for page in doc:
    assert abs(page.rect.width - 595.3) < 1 and abs(page.rect.height - 841.9) < 1, page.rect
    # Check actual PDF geometry, not the print-media viewport (which is not paper-sized).
    for word in page.get_text('words'):
        assert word[0] >= 32 and word[2] <= page.rect.width - 32, (case, 'horizontal clipping', word)
if case == 'vendor':
    assert 'Solo Beauty Packaging Co., Ltd' in text
    assert len(doc[0].get_images()) == 0
else:
    assert len(doc[0].get_images()) > 0, 'Missing printed logo'
if case in ['discrepancy', 'long']:
    assert 'Received with discrepancies' in text
    assert 'Received in accepted condition with no discrepancy.' not in text
if case == 'long':
    assert sum(page.get_text().count('Shampoo') for page in doc) == 60, 'Lost delivery lines'
    assert 'Shampoo' in doc[0].get_text(), 'Table pushed off first page by whole-table break avoidance'
    assert len(doc[-1].search_for('Receiver acknowledgement')) == 1
    assert len(doc[-1].search_for('Company stamp')) == 1, 'Acknowledgement/signature/stamp split across pages'
    for page in doc:
        if 'Shampoo' in page.get_text():
            assert 'RECEIVED' in page.get_text(), 'Table headers must repeat'
for page in doc:
    page.get_pixmap(matrix=pymupdf.Matrix(1.25, 1.25)).save(sys.argv[1].replace('.pdf', '-page'+str(page.number+1)+'.png'))
print(str(len(doc)) + ' A4 page(s), no clipping, signing fields and branding verified')
`;
(async () => {
    let browser;
    try {
        browser = await chromium.launch({executablePath: process.env.CHROMIUM_EXECUTABLE_PATH || undefined,
            headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu']});
        const page = await browser.newPage({viewport: {width: 1280, height: 1000}});
        await page.route('**/*', async route => {
            const pathname = new URL(route.request().url()).pathname;
            // No remote services or live application/database: use only this checkout's assets.
            if (pathname.startsWith('/assets/') || pathname.startsWith('/images/')) {
                const file = path.resolve(root, 'public', '.'+pathname);
                if (file.startsWith(path.join(root, 'public')+path.sep) && fs.existsSync(file)) return route.fulfill({path: file});
            }
            return route.abort();
        });
        for (const name of ['own', 'vendor', 'discrepancy', 'long']) {
            await page.setContent(fs.readFileSync(path.join(directory, `receipt-${name}.html`), 'utf8'), {waitUntil: 'networkidle'});
            await page.emulateMedia({media: 'screen'});
            await page.screenshot({path: path.join(directory, `receipt-${name}-screen.png`), fullPage: true});
            assert.equal(await page.locator('.receipt-logo').count(), name === 'vendor' ? 0 : 1);
            if (name !== 'vendor') assert(await page.locator('.receipt-logo').evaluate(img => img.complete && img.naturalWidth > 0));
            await page.emulateMedia({media: 'print'});
            assert.equal(await page.locator('.pdf-toolbar').evaluate(el => getComputedStyle(el).display), 'none');
            assert(await page.locator('.receipt-signature-space').evaluate(el => el.getBoundingClientRect().height >= 22*96/25.4));
            assert(await page.locator('.receipt-stamp-space').evaluate(el => el.getBoundingClientRect().height >= 40*96/25.4));
            const file = path.join(directory, `receipt-${name}.pdf`);
            await page.pdf({path: file, format: 'A4', preferCSSPageSize: true, printBackground: true});
            const result = spawnSync('python3', ['-c', pdfCheck, file, name], {encoding: 'utf8'});
            if (result.status !== 0) throw new Error(result.stderr || result.stdout);
            console.log(`PASS ${name}: ${result.stdout.trim()}`);
        }
    } finally {
        if (browser) await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
