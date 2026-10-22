#!/usr/bin/env node
/* Optional real PDF regression. Requires playwright-core, a Chromium executable
   (CHROMIUM_EXECUTABLE_PATH), and Python with pymupdf. NODE_PATH/PYTHONPATH may
   point to temporary installs; no browser binaries or generated PDFs belong in Git.
   This is a stylesheet fixture, not a booted Laravel integration test. */
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');
const { chromium } = require('playwright-core');
const root = path.resolve(__dirname, '../..');
const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'packaging-borders-'));
const css = ['document-print.css', 'packaging-list-print.css', 'packaging-list-print-settings.css']
  .map(file => fs.readFileSync(path.join(root, 'public/assets/css', file), 'utf8')).join('\n');
const labels = ['Sr. no.', 'Item description', 'Carton', 'Qty / carton', 'Total qty', 'G.W. / carton', 'Gross weight', 'Total CBM'];
function fixture(orientation, rows, broken) {
  return `<!doctype html><html><head><style>${css}
    ${broken ? '@media print { html body.pdf-preview .pdf-sheet.pl-sheet {width:auto !important;} }' : ''}
    @page {size:A4 ${orientation};margin:16mm 12mm;}
    </style></head><body class="pdf-preview pl-standalone" data-orientation="${orientation}">
    <main class="pdf-sheet pl-sheet">
      <header class="pl-top"><div class="pl-seller"><p class="pl-company">MissPack</p></div><div class="pl-identity"><h1>Packaging List</h1><span class="pl-status">DRAFT</span></div></header>
      <section class="pl-facts">${['Packing date','Transport mode','Invoice no.','Shipment','PROJECT'].map(label => `<div class="pl-fact"><span>${label}</span><strong>Example</strong></div>`).join('')}</section>
      <section class="pl-parties">${['From','Bill to','Ship to'].map(label => `<article class="pl-party"><h2>${label}</h2><div class="pl-party-body"><p class="pl-party-name">Example company</p><p class="pl-party-detail">${label === 'Ship to' ? 'Ship address' : 'Example address'}</p></div></article>`).join('')}</section>
      <div class="pl-items-wrap"><table class="pl-items"><colgroup>${[5,27,7,12,13,12,12,12].map(weight => `<col style="width:${weight}%">`).join('')}</colgroup>
        <thead><tr>${labels.map(label => `<th>${label}</th>`).join('')}</tr></thead>
        <tbody><tr class="pl-section-band"><th colspan="8">Carton group</th></tr>${Array.from({length:rows}, (_,i) => `<tr><td>${i+1}</td><td>Spray bottle</td><td>1</td><td>336 pcs</td><td>336 pcs</td><td>5.3</td><td>5.3</td><td class="right">0.6630</td></tr>`).join('')}</tbody>
        <tfoot><tr><th colspan="8">Total</th></tr></tfoot></table></div>
      <section class="pl-bottom"><div class="pl-bottom-left"><div class="pl-declaration"><p>Declaration</p></div></div><div class="pl-bottom-right"><section class="pl-summary"><div class="pl-summary-heading"><h2>Packing summary</h2></div><table><tr><td>Weight</td><td>12 kg</td></tr></table></section><div class="pl-signature"><strong>MissPack</strong><span class="pl-signature-label">SIGNATORY</span></div></div></section>
    </main></body></html>`;
}
const rasterCheck = String.raw`
import math, sys, pymupdf
pdf = pymupdf.open(sys.argv[1])
checks = 0
for page in pdf:
    pix = page.get_pixmap(matrix=pymupdf.Matrix(3, 3), alpha=False)
    right = page.rect.width - 12 / 25.4 * 72
    left_x = max(0, math.floor((right - 2) * 3))
    right_x = min(pix.width, math.ceil((right + 1) * 3))
    for token in ['DRAFT', 'PROJECT', 'Ship address', '0.6630', 'Packing summary', 'SIGNATORY']:
        for rect in page.search_for(token):
            y = min(pix.height - 1, int((rect.y0 + rect.y1) / 2 * 3))
            # Only inspect the expected outer edge, away from text and horizontal rules.
            assert any(max(pix.pixel(x, y)[:3]) < 190 for x in range(left_x, right_x)), (sys.argv[1], page.number + 1, token, 'missing right border')
            checks += 1
assert checks >= 8, 'fixture must exercise the status, facts, parties, table, summary and signature'
print(str(len(pdf)) + ' page(s), ' + str(checks) + ' right-edge samples passed')
`;
(async () => {
  let browser;
  try {
    browser = await chromium.launch({ executablePath: process.env.CHROMIUM_EXECUTABLE_PATH || undefined,
      headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu'] });
    const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
    await page.emulateMedia({ media: 'print' });
    for (const orientation of ['portrait', 'landscape']) {
      for (const rows of [3, 100]) {
        const file = path.join(directory, `${orientation}-${rows}.pdf`);
        await page.setContent(fixture(orientation, rows, false));
        await page.pdf({ path: file, preferCSSPageSize: true, printBackground: true });
        const result = spawnSync('python3', ['-c', rasterCheck, file], { encoding: 'utf8' });
        if (result.status !== 0) throw new Error(result.stderr || result.stdout);
        console.log(`PASS ${orientation}, ${rows} rows: ${result.stdout.trim()}`);
      }
    }
    // Prove this test detects the old regression, rather than just finding any line.
    const broken = path.join(directory, 'old-auto-width.pdf');
    await page.setContent(fixture('portrait', 3, true));
    await page.pdf({ path: broken, preferCSSPageSize: true, printBackground: true });
    const result = spawnSync('python3', ['-c', rasterCheck, broken], { encoding: 'utf8' });
    if (result.status === 0 || !result.stderr.includes('missing right border')) throw new Error('The old auto-width regression was not detected');
    console.log('PASS negative control: the original auto-width clips the PDF right edge');
  } finally {
    if (browser) await browser.close();
    fs.rmSync(directory, { recursive: true, force: true });
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
