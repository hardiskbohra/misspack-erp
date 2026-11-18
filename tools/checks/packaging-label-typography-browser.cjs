#!/usr/bin/env node
/* Optional real-Blade print regression. Requires Composer dependencies, PHP,
   playwright-core, Chromium, and Python with PyMuPDF. NODE_PATH/PYTHONPATH
   may point to temporary installs. LABEL_TEST_INTER_DIR may point to the
   @fontsource/inter/files directory to exercise Inter offline; without it the
   app's system fallback is tested. The PHP renderer uses fake in-memory models;
   no database or external service is contacted. PDFs stay in a temp directory. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const { chromium } = require('playwright-core');

const root = path.resolve(__dirname, '../..');
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'thermal-label-'));
const origin = 'http://label.fixture.test';
const interDirectory = process.env.LABEL_TEST_INTER_DIR || '';
const fixture = path.join(root, 'tools/testing/packaging-label-fixture.php');
const assets = {
  '/assets/css/packaging-list-label.css': ['public/assets/css/packaging-list-label.css', 'text/css'],
  '/assets/js/qr.js': ['public/assets/js/qr.js', 'application/javascript'],
  '/assets/js/packaging-list-label-print.js': ['public/assets/js/packaging-list-label-print.js', 'application/javascript'],
  '/images/logo-dark.png': ['public/images/logo-dark.png', 'image/png'],
};

// Before this change, thermal body text was 5.6 pt. These are the previous
// multipliers/+2 pt party increment, not values derived from the modified CSS.
function previousSizes(format) {
  const thermal = format.startsWith('thermal');
  const base = thermal ? 5.6 : (format === 'a5' ? 11 : 12);
  return {
    base, header: .72 * base, title: (thermal ? 1.35 : 1.6) * base,
    barcode: .68 * base, identityKey: .72 * base,
    identityValue: (thermal ? 1.15 : 1.42) * base,
    partyKey: .72 * base + 2, partyName: 1.05 * base + 2,
    partyAddress: (thermal ? .8 : format === 'a5' ? .84 : .88) * base + 2,
    table: base, tableHeading: .82 * base, itemValue: base,
    // Chromium's UA `small` uses 1/1.2 of the parent size.
    itemDetail: base / 1.2, physicalKey: .72 * base,
    physicalValue: (thermal ? 1.15 : 1.25) * base,
    handling: .74 * base, note: base, noteKey: .72 * base,
  };
}
const selectors = {
  base: '.carton-label', header: '.carton-label header p', title: '.carton-label h1',
  barcode: '.label-qr small', identityKey: '.label-identity .label-key',
  identityValue: '.label-identity strong', partyKey: '.label-parties .label-key',
  partyName: '.label-parties strong', partyAddress: '.label-parties p',
  table: '.label-items table', tableHeading: '.label-items th',
  itemValue: '.label-items td strong', itemDetail: '.label-items td small',
  physicalKey: '.label-physical .label-key', physicalValue: '.label-physical strong',
  handling: '.label-handling-symbol strong', note: '.label-handling-note',
  noteKey: '.label-handling-note .label-key',
};

function render(format, scenario) {
  const command = process.env.LABEL_TEST_PHP || 'php';
  const result = spawnSync(command, [fixture, format, scenario], {
    cwd: root, encoding: 'utf8',
    env: { ...process.env, APP_ENV: 'testing', APP_RUNNING_IN_CONSOLE: 'true',
      APP_KEY: 'base64:dHR0dHR0dHR0dHR0dHR0dHR0dHR0dHR0dHR0dHR0dHQ=',
      APP_URL: origin, DB_CONNECTION: 'sqlite', DB_DATABASE: ':memory:' },
  });
  if (result.status !== 0) throw new Error(`Fixture renderer failed: ${result.stderr || result.error || result.stdout}`);
  assert(result.stdout.includes('<main class="carton-label">'), 'Blade must render the carton label');
  return result.stdout;
}

async function openFixture(browser, format, scenario) {
  const html = render(format, scenario);
  const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
  const unexpected = [];
  await page.addInitScript(() => { window.__prints = 0; window.print = () => { window.__prints++; }; });
  await page.route('**/*', async route => {
    const url = new URL(route.request().url());
    if (url.origin === 'https://fonts.googleapis.com' && url.pathname === '/css2' && interDirectory) {
      const css = [400, 500, 600, 700].map(weight =>
        `@font-face{font-family:Inter;font-style:normal;font-weight:${weight};src:url(${origin}/test-font/inter-${weight}.woff2) format('woff2')}`
      ).join('\n');
      return route.fulfill({ status: 200, contentType: 'text/css', body: css });
    }
    if (url.origin !== origin) return route.abort(); // No network: either offline Inter or the actual fallback stack.
    if (url.pathname === '/') return route.fulfill({ status: 200, contentType: 'text/html', body: html });
    const font = url.pathname.match(/^\/test-font\/inter-(400|500|600|700)\.woff2$/);
    if (font && interDirectory) {
      const file = path.join(interDirectory, `inter-latin-${font[1]}-normal.woff2`);
      return route.fulfill({ status: 200, contentType: 'font/woff2', body: fs.readFileSync(file) });
    }
    const asset = assets[url.pathname];
    if (!asset) { unexpected.push(url.pathname); return route.abort(); }
    return route.fulfill({ status: 200, contentType: asset[1], body: fs.readFileSync(path.join(root, asset[0])) });
  });
  await page.goto(origin+'/', { waitUntil: 'load' });
  await page.evaluate(() => document.fonts.ready);
  if (interDirectory) {
    const loaded = await page.evaluate(() => [...document.fonts].filter(face => face.family === 'Inter' && face.status === 'loaded').map(face => face.weight));
    assert(loaded.includes('400') && loaded.includes('700'), `Inter regular and bold did not load: ${loaded}`);
  }
  await page.waitForSelector('.label-qr-code svg');
  if (scenario === 'batch') {
    await page.waitForFunction(() => window.__prints === 1);
  } else {
    assert.equal(await page.evaluate(() => window.__prints), 0, `${format}/${scenario}: single labels must remain manual`);
    await page.click('[data-print-labels]');
    await page.waitForFunction(() => window.__prints === 1);
  }
  assert.deepEqual(unexpected, [], 'fixture requested unexpected local assets');
  assert.equal(await page.locator('[data-label-print-error]').isHidden(), true, 'the real print preparation must succeed');
  await page.emulateMedia({ media: 'print' });
  return page;
}

async function checkTypography(page, format) {
  const measured = await page.evaluate(selectors => Object.fromEntries(Object.entries(selectors).map(([key, selector]) => {
    const element = document.querySelector(selector);
    if (!element) throw new Error('Missing typography fixture: '+selector);
    return [key, parseFloat(getComputedStyle(element).fontSize) * 3 / 4]; // CSS pixels -> typographic points
  })), selectors);
  const increment = format.startsWith('thermal') ? 1 : 0;
  for (const [key, before] of Object.entries(previousSizes(format))) {
    const expected = before + increment;
    assert(Math.abs(measured[key] - expected) < .02,
      `${format} ${key}: expected ${expected.toFixed(3)} pt (previous ${before.toFixed(3)} + ${increment}), got ${measured[key].toFixed(3)} pt`);
  }
}

async function checkLayout(page, format, scenario) {
  const result = await page.evaluate(({ format, scenario }) => {
    const labels = [...document.querySelectorAll('.carton-label')];
    const failures = [];
    const mm = x => x * 96 / 25.4;
    const check = (condition, message) => { if (!condition) failures.push(message); };
    const within = (inner, outer, tolerance = 1.5) => inner.left >= outer.left - tolerance && inner.right <= outer.right + tolerance &&
      inner.top >= outer.top - tolerance && inner.bottom <= outer.bottom + tolerance;
    const height = format === 'thermal150' ? 150 : format === 'thermal' ? 140 : format === 'a5' ? 148 : 210;
    const width = format.startsWith('thermal') ? 100 : format === 'a5' ? 210 : 297;
    check(labels.length === (scenario === 'batch' ? 3 : 1), 'wrong label count');
    check(getComputedStyle(document.querySelector('.label-toolbar')).display === 'none', 'toolbar printed');
    let clippedItems = false;
    labels.forEach((label, index) => {
      const box = label.getBoundingClientRect();
      check(Math.abs(box.width - mm(width)) < 1, `label ${index+1} width`);
      check(Math.abs(box.height - mm(height)) < 1, `label ${index+1} height`);
      check(label.scrollHeight <= label.clientHeight + 2, `label ${index+1} overflows its frame`);
      const qr = label.querySelector('.label-qr-code svg');
      check(!!qr && within(qr.getBoundingClientRect(), box), `label ${index+1} QR missing or outside frame`);
      const titleBox = label.querySelector('.label-title').getBoundingClientRect();
      const qrBox = label.querySelector('.label-qr').getBoundingClientRect();
      check(titleBox.right <= qrBox.left + 1.5, `label ${index+1} sender heading overlaps QR`);
      if (format.startsWith('thermal') && qr) {
        check(Math.abs(qr.getBoundingClientRect().width - mm(19)) < 1, `label ${index+1} QR width changed`);
      }
      const logo = label.querySelector('.label-brand-logo');
      check(scenario === 'vendor' ? !logo : !!logo && logo.naturalWidth > 0 && within(logo.getBoundingClientRect(), box),
        `label ${index+1} sender branding`);
      if (logo && format.startsWith('thermal')) check(Math.abs(logo.getBoundingClientRect().height - mm(8)) < 1, `label ${index+1} logo height changed`);
      const items = label.querySelector('.label-items');
      const itemBox = items.getBoundingClientRect();
      const table = items.querySelector('table').getBoundingClientRect();
      clippedItems ||= table.bottom > itemBox.bottom + 1;
      for (const section of label.children) check(within(section.getBoundingClientRect(), box), `label ${index+1} ${section.className || section.tagName} outside frame`);
      // Check actual line boxes, including nested small text, against every
      // clipping ancestor. The overflow fixture deliberately clips item lines.
      // The unchanged A5 design also clips this unusually dense fixture; its
      // geometry is compared with thermal rules removed below instead.
      const allowItemClipping = scenario === 'overflow' || format === 'a5';
      const walker = document.createTreeWalker(label, NodeFilter.SHOW_TEXT);
      let text;
      while ((text = walker.nextNode())) {
        if (!text.nodeValue.trim()) continue;
        const parent = text.parentElement;
        if (parent.closest('svg')) continue;
        const range = document.createRange(); range.selectNodeContents(text);
        const cell = parent.closest('.label-identity > div, .label-parties > div, .label-physical > div, .label-items td, .label-items th, .label-handling-symbol, .label-handling-note, .label-title, .label-qr');
        for (const rect of range.getClientRects()) {
          if (cell && !(allowItemClipping && items.contains(parent))) {
            check(within(rect, cell.getBoundingClientRect()), `label ${index+1} text outside its cell: ${text.nodeValue.trim().slice(0, 45)}`);
          }
          for (let ancestor = parent; ancestor && ancestor !== document.body; ancestor = ancestor.parentElement) {
            const style = getComputedStyle(ancestor);
            if (style.overflowX !== 'hidden' && style.overflowY !== 'hidden') continue;
            if (allowItemClipping && ancestor === items) continue;
            // Overflowing item content is clipped before the parent frame;
            // it need not also fit the frame in these two cases.
            if (allowItemClipping && ancestor === label && items.contains(parent)) continue;
            check(within(rect, ancestor.getBoundingClientRect()), `label ${index+1} clipped text: ${text.nodeValue.trim().slice(0, 45)}`);
          }
        }
      }
      for (const selector of ['.label-identity', '.label-parties', '.label-physical']) {
        const style = getComputedStyle(label.querySelector(selector));
        check(parseFloat(style.borderLeftWidth) > 0 && parseFloat(style.borderRightWidth) > 0, `${selector} side rules`);
      }
      const first = label.querySelector('.label-items th');
      const last = label.querySelector('.label-items th:last-child');
      check(parseFloat(getComputedStyle(first).borderLeftWidth) > 0 && parseFloat(getComputedStyle(last).borderRightWidth) > 0, 'item table side rules');
    });
    if (scenario === 'overflow') check(clippedItems, 'overflow fixture did not exercise the item-band clipping policy');
    else if (format !== 'a5') check(!clippedItems, 'a normal item table was clipped');
    return { failures, clippedItems };
  }, { format, scenario });
  assert.deepEqual(result.failures, [], `${format}/${scenario}: print layout violations`);
  if (format === 'a5' && result.clippedItems) {
    console.log('NOTE A5 dense fixture has pre-existing item-band clipping; thermal rules are checked for no effect below');
  }
}

// A5 has a known, pre-existing overflow with the dense all-handling fixture.
// Verify the entire rendered A5 layout is identical with every thermal rule
// removed; this deliberately does not claim the old A5 stress case fits.
async function checkA5Unaffected(page) {
  const snapshot = () => page.evaluate(() => [...document.querySelectorAll('.carton-label, .carton-label *')].map(element => {
    const r = element.getBoundingClientRect(), s = getComputedStyle(element);
    return [element.tagName, r.x, r.y, r.width, r.height, s.fontSize, s.lineHeight, s.display,
      s.overflowX, s.overflowY, s.borderTopWidth, s.borderRightWidth, s.borderBottomWidth, s.borderLeftWidth];
  }));
  const actual = await snapshot();
  await page.evaluate(() => {
    const sheet = [...document.styleSheets].find(s => s.href && s.href.includes('packaging-list-label.css'));
    if (!sheet) throw new Error('Label stylesheet not loaded');
    for (let i = sheet.cssRules.length - 1; i >= 0; i--) {
      const rule = sheet.cssRules[i];
      if (rule.type === CSSRule.STYLE_RULE && rule.selectorText.includes('.format-thermal')) sheet.deleteRule(i);
    }
  });
  assert.deepEqual(await snapshot(), actual, 'thermal-specific rules must not alter the A5 layout');
  console.log('PASS A5 layout and computed styles are identical with thermal rules removed');
}

const pdfCheck = String.raw`
import math, re, sys, pymupdf
file, format, scenario = sys.argv[1:]
pdf = pymupdf.open(file)
expected_count = 3 if scenario == 'batch' else 1
assert len(pdf) == expected_count, (file, 'page count', len(pdf), expected_count)
width_mm, height_mm = {'thermal': (100, 140), 'thermal150': (100, 150), 'a4': (297, 210), 'a5': (210, 148)}[format]
for index, page in enumerate(pdf):
    assert abs(page.rect.width - width_mm * 72 / 25.4) < 1.5, (file, 'width', page.rect)
    assert abs(page.rect.height - height_mm * 72 / 25.4) < 1.5, (file, 'height', page.rect)
    text = re.sub(r'\s+', ' ', page.get_text()).strip()
    required = ['PACKAGING LIST', 'MP/PL/26-27/024', 'CTN-TEST-00', f'{index + 1}/3', 'FROM', 'SHIP TO',
                'DIMENSIONS', 'NET WEIGHT', 'GROSS WEIGHT', '6.6 kg', '7.2 kg', 'FRAGILE', 'KEEP DRY',
                'THIS SIDE UP', 'HAZARDOUS', 'HANDLING INSTRUCTIONS', 'Keep away from heat']
    # A5's unchanged dense fixture already clips its item band; thermal normal
    # labels and A4 must retain all item text, while the intentional overflow
    # fixture checks the protected weights/handling rather than excess rows.
    if scenario != 'overflow' and format != 'a5':
        required += ['ITEM / SKU', 'BATCH / SERIALS', 'SKU-300', 'BATCH-24', 'SER-001', '174 pcs', 'India', '392330']
        required += ['Fine Mist Pump' if scenario == 'batch' and index == 2 else '300ml Shampoo Bottle']
    for token in required:
        # A word may wrap inside a handling symbol; require every character to
        # print, but do not treat a legitimate line break as missing text.
        assert token in text or token.replace(' ', '') in text.replace(' ', ''), (file, index + 1, 'missing printed text', token, text)
    # The sample sits on both outside rules of the ruled form, alongside
    # identity, parties, item heading and physical data. This detects missing
    # or clipped side borders in the actual rasterized PDF, not just CSS.
    if format.startswith('thermal'):
        pix = page.get_pixmap(matrix=pymupdf.Matrix(3, 3), alpha=False)
        for token in ['TYPE', 'FROM', 'ITEM / SKU', 'NET WEIGHT']:
            rects = page.search_for(token)
            assert rects, (file, 'missing border anchor', token)
            y = min(pix.height - 1, int((rects[0].y0 + rects[0].y1) / 2 * 3))
            for x_pt in [3.5 * 72 / 25.4, (100 - 3.5) * 72 / 25.4]:
                left = max(0, math.floor((x_pt - 1) * 3))
                right = min(pix.width, math.ceil((x_pt + 1) * 3))
                assert any(max(pix.pixel(x, y)[:3]) < 190 for x in range(left, right)), (file, index + 1, token, 'missing outside border', x_pt)
details = 'geometry, required printed content' + (' and outside rules' if format.startswith('thermal') else '')
print(f'{len(pdf)} page(s), {details} passed')
`;

async function checkPdf(page, format, scenario) {
  const file = path.join(tmp, `${format}-${scenario}.pdf`);
  await page.pdf({ path: file, preferCSSPageSize: true, printBackground: true });
  const result = spawnSync('python3', ['-c', pdfCheck, file, format, scenario], { encoding: 'utf8' });
  if (result.status !== 0) throw new Error(result.stderr || result.stdout || String(result.error));
  console.log(`PASS ${format}/${scenario}: ${result.stdout.trim()}`);
}

(async () => {
  let browser;
  try {
    browser = await chromium.launch({ executablePath: process.env.CHROMIUM_EXECUTABLE_PATH || undefined,
      headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu'] });
    for (const format of ['thermal', 'thermal150', 'a4', 'a5']) {
      const page = await openFixture(browser, format, 'single');
      await checkTypography(page, format);
      await checkLayout(page, format, 'single');
      await checkPdf(page, format, 'single');
      if (format === 'a5') await checkA5Unaffected(page);
      await page.close();
    }
    for (const format of ['thermal', 'thermal150']) {
      for (const scenario of ['batch', 'vendor', 'overflow']) {
        const page = await openFixture(browser, format, scenario);
        await checkTypography(page, format);
        await checkLayout(page, format, scenario);
        await checkPdf(page, format, scenario);
        await page.close();
      }
    }
    // A base-only change would leave this child at .72em, adding only .72 pt.
    // Prove the browser assertion catches that tempting partial fix.
    const negative = await openFixture(browser, 'thermal', 'single');
    await negative.addStyleTag({ content: '.format-thermal .carton-label header p{font-size:.72em}' });
    await assert.rejects(() => checkTypography(negative, 'thermal'), /thermal header: expected/);
    await negative.close();
    console.log('PASS negative control: a base-only increase does not add a full point to relative text');
    console.log(`PASS exact one-point thermal increase; A4/A5 typography unchanged; single, batch, vendor and item-overflow printing (${interDirectory ? 'offline Inter' : 'fallback font'})`);
  } finally {
    if (browser) await browser.close();
    fs.rmSync(tmp, { recursive: true, force: true });
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
