/* Run: node tools/checks/confirmation-dialog-check.cjs
 * Source audit always runs. Browser checks require playwright-core + Chromium:
 * PLAYWRIGHT_PATH=/path/to/node_modules/playwright-core
 * CHROMIUM_PATH=/path/to/chrome (or @sparticuz/chromium beside playwright-core).
 */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const source = fs.readFileSync(path.join(root, 'public/assets/js/master-alert.js'), 'utf8');
let passed = 0;
function check(name, condition) {
    assert.ok(condition, name);
    passed++;
    console.log('  ok  ' + name);
}
function walk(dir) {
    return fs.readdirSync(dir, { withFileTypes: true }).flatMap(entry => {
        const file = path.join(dir, entry.name);
        return entry.isDirectory() ? walk(file) : [file];
    });
}
const confirmations = walk(path.join(root, 'resources/views')).filter(f => f.endsWith('.blade.php'))
    .flatMap(file => [...fs.readFileSync(file, 'utf8').matchAll(/<(?:form|button)\b(?:[^"'<>]|"[^"]*"|'[^']*')*>/g)]
        .filter(match => /\bdata-confirm=/.test(match[0])).map(match => ({ file, tag: match[0] })));
check('ERP confirmation declarations are audited', confirmations.length > 40);
check('shared form/button confirmations no longer assume Delete', !source.includes("getAttribute('data-confirm-text') || 'Delete'"));
const nonDestructive = confirmations.filter(({ tag }) => /data-confirm="(?:Create|Raise|Mark)\b/.test(tag));
check('conversion and completion confirmations exist in the audit', nonDestructive.length >= 7);
for (const { file, tag } of nonDestructive) {
    check(path.relative(root, file) + ': non-destructive confirmation has neutral styling', /data-confirm-danger="false"/.test(tag));
    check(path.relative(root, file) + ': non-destructive confirmation has an appropriate answer',
        /data-confirm-text="(?:Yes|Convert to bill|Mark received)"/.test(tag));
}

async function run() {
    let playwright;
    try { playwright = require(process.env.PLAYWRIGHT_PATH || 'playwright-core'); }
    catch (_) {
        console.log(`\nconfirmation dialogs: ${passed} passed; browser checks SKIPPED (install playwright-core and set PLAYWRIGHT_PATH)`);
        return;
    }
    let executablePath = process.env.CHROMIUM_PATH;
    if (!executablePath) {
        const chromiumModule = await import(require('node:url').pathToFileURL(require.resolve('@sparticuz/chromium', {
            paths: [path.dirname(require.resolve(process.env.PLAYWRIGHT_PATH || 'playwright-core'))],
        })).href);
        executablePath = await chromiumModule.default.executablePath();
    }
    const browser = await playwright.chromium.launch({ executablePath, headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage'] });
    try {
        const page = await browser.newPage();
        await page.setContent('<!doctype html><body><main id="fixture"></main></body>');
        await page.addStyleTag({ content: fs.readFileSync(path.join(root, 'public/assets/css/master-alert.css'), 'utf8') });
        await page.addScriptTag({ content: source });
        async function fixture(html) {
            await page.waitForFunction(() => !document.querySelector('.ma-overlay'));
            await page.locator('#fixture').evaluate((el, markup) => { el.innerHTML = markup; }, html);
            await page.evaluate(() => {
                window.submits = 0;
                document.querySelectorAll('form').forEach(form => {
                    form.submit = () => { window.submits++; };
                    form.addEventListener('submit', e => { e.preventDefault(); if (!form.hasAttribute('data-confirm')) window.submits++; });
                });
            });
        }
        async function answer(button) {
            await page.getByRole('dialog').getByRole('button', { name: button, exact: true }).click();
            await page.waitForFunction(() => !document.querySelector('.ma-overlay'));
        }
        for (const viewport of [{ width: 1280, height: 900 }, { width: 390, height: 844 }]) {
            await page.setViewportSize(viewport);
            await fixture('<form data-confirm="Create a tax invoice?" data-confirm-danger="false"><button>Create tax invoice</button></form>');
            await page.getByRole('button', { name: 'Create tax invoice', exact: true }).click();
            check(`${viewport.width}px: tax conversion answers Yes, not Delete`, await page.getByRole('dialog').getByRole('button', { name: 'Yes', exact: true }).count() === 1);
            check(`${viewport.width}px: dialog fits the viewport`, await page.getByRole('dialog').evaluate(el => {
                const box = el.getBoundingClientRect();
                return box.left >= 0 && box.right <= window.innerWidth;
            }));
            check(`${viewport.width}px: conversion is not styled as deletion`, await page.locator('.ma-dialog-actions .ma-btn-primary').count() === 1);
            await answer('Cancel');
            check(`${viewport.width}px: Cancel does not submit`, await page.evaluate(() => window.submits) === 0);
            await page.getByRole('button', { name: 'Create tax invoice', exact: true }).click();
            await answer('Yes');
            check(`${viewport.width}px: Yes submits once`, await page.evaluate(() => window.submits) === 1);
        }
        await fixture('<form data-confirm="Delete this invoice?"><button>Delete invoice</button></form>');
        await page.getByRole('button', { name: 'Delete invoice', exact: true }).click();
        check('destructive questions also get a safe Yes fallback', await page.getByRole('dialog').getByRole('button', { name: 'Yes', exact: true }).count() === 1);
        check('destructive confirmations retain danger styling', await page.locator('.ma-btn-danger').count() === 1);
        await answer('Cancel');
        check('cancelled deletion never submits', await page.evaluate(() => window.submits) === 0);

        await fixture('<form><button data-confirm="Remove this address?">Remove address</button></form>');
        await page.getByRole('button', { name: 'Remove address', exact: true }).click();
        check('button-based confirmations answer Yes too', await page.getByRole('dialog').getByRole('button', { name: 'Yes', exact: true }).count() === 1);
        await answer('Cancel');
        check('cancelled button does not submit', await page.evaluate(() => window.submits) === 0);
        await page.getByRole('button', { name: 'Remove address', exact: true }).click();
        await answer('Yes');
        check('confirmed button replays once without a second dialog', await page.evaluate(() => window.submits) === 1);

        await fixture('<form data-confirm="Raise a purchase bill?" data-confirm-text="Convert to bill" data-confirm-danger="false"><button>Convert</button></form>');
        await page.getByRole('button', { name: 'Convert', exact: true }).click();
        check('appropriate explicit form labels are preserved', await page.getByRole('dialog').getByRole('button', { name: 'Convert to bill', exact: true }).count() === 1);
        await answer('Convert to bill');
        check('custom form confirmation still submits once', await page.evaluate(() => window.submits) === 1);

        await fixture('<form><button data-confirm="Remove this contact?" data-confirm-text="Remove" data-confirm-danger="0">Remove contact</button></form>');
        await page.getByRole('button', { name: 'Remove contact', exact: true }).click();
        check('appropriate explicit button labels are preserved', await page.getByRole('dialog').getByRole('button', { name: 'Remove', exact: true }).count() === 1);
        check('explicit button danger override is preserved', await page.locator('.ma-btn-primary').count() === 1);
        await answer('Remove');

        await page.evaluate(() => { window.MasterAlert.confirm('Proceed?').then(ok => { window.result = ok; }); });
        check('programmatic confirmation defaults to Yes', await page.getByRole('dialog').getByRole('button', { name: 'Yes', exact: true }).count() === 1);
        await answer('Yes');
        check('programmatic Yes resolves true', await page.evaluate(() => window.result) === true);
        await page.evaluate(() => { window.MasterAlert.confirm('Proceed?').then(ok => { window.result = ok; }); });
        await page.keyboard.press('Escape');
        await page.waitForFunction(() => !document.querySelector('.ma-overlay'));
        check('Escape still resolves confirmation as false', await page.evaluate(() => window.result) === false);
        await page.evaluate(() => { window.MasterAlert.confirm('Show this invoice in the client portal?', {
            confirmText: 'Yes', cancelText: 'No', danger: false,
        }).then(ok => { window.result = ok; }); });
        check('explicit Yes/No choices retain their No button', await page.getByRole('dialog').getByRole('button', { name: 'No', exact: true }).count() === 1);
        await answer('No');
        check('explicit No still resolves false', await page.evaluate(() => window.result) === false);
        await page.evaluate(() => { window.MasterAlert.alert('Saved.'); });
        check('informational alerts still say OK, not Yes', await page.getByRole('dialog').getByRole('button', { name: 'OK', exact: true }).count() === 1);
        await answer('OK');
    } finally { await browser.close(); }
    console.log(`\nconfirmation dialogs: ${passed} passed, 0 failed (source audit + Chromium)`);
}
run().catch(error => { console.error(error); process.exitCode = 1; });
