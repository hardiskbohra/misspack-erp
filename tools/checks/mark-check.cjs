/* ==========================================================================
   MARK CHECK — shipping-mark geometry and QR payloads
   --------------------------------------------------------------------------
   Run:  node tools/checks/mark-check.cjs
   Decoding needs `jsqr` (npm i jsqr); without it the geometry checks still
   run and the decode checks report as skipped.

   1. Geometry — parses shipping-mark.css and adds up the sticker's declared
      millimetre sizes. The office prints on 14 × 20 cm paper, so a sticker
      that cannot fit 128 × 62.6 mm, or that would push its branding off the
      label, fails here rather than on paper.
   2. Markup — the mark carries the brand, the website and both codes, and
      does not carry logistics/tracking fields.
   3. QR — encodes every payload the mark prints with the app's own encoder
      (public/assets/js/qr.js, no dependency) and decodes it again.
   ========================================================================== */
'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..', '..');
const CSS = path.join(ROOT, 'public/assets/css/shipping-mark.css');
const STICKER = path.join(ROOT, 'resources/views/shipments/partials/sticker.blade.php');

const out = [];
const check = (name, ok, detail = '') => out.push([name, !!ok, detail]);

const cssText = fs.readFileSync(CSS, 'utf8');
const css = cssText.replace(/\/\*[\s\S]*?\*\//g, '');
const sticker = fs.readFileSync(STICKER, 'utf8');

/* ------------------------------------------------------------- parsing */

function parseRules(text) {
    const rules = [];
    let i = 0, buf = '', insideRule = false, atDepth = 0;
    while (i < text.length) {
        const ch = text[i];
        if (ch === '{') {
            const selector = buf.trim(); buf = '';
            if (selector.startsWith('@')) { atDepth++; insideRule = false; }
            else { rules.push({ selector, body: '', inAtRule: atDepth > 0 }); insideRule = true; }
            i++; continue;
        }
        if (ch === '}') {
            if (insideRule) insideRule = false;
            else if (atDepth > 0) atDepth--;
            i++; continue;
        }
        if (insideRule && rules.length) rules[rules.length - 1].body += ch;
        else buf += ch;
        i++;
    }
    return rules;
}

const RULES = parseRules(css);

const block = (selector) => RULES
    .filter(r => r.selector.split(',').map(s => s.trim()).includes(selector))
    .map(r => r.body)
    .join('\n');

const prop = (selector, name) => {
    const m = new RegExp('(?:^|;|\\s)' + name + '\\s*:\\s*([^;]+)').exec(block(selector));
    return m ? m[1].trim() : null;
};

const mm = v => v === null ? null : parseFloat(String(v).replace(/[^\d.]/g, ''));
const pt2mm = pt => pt * 0.3528;
const line = (pt, factor) => pt2mm(pt) * factor;

/* ------------------------------------------------------------- 1. geometry */

const page = /@page\s*\{([^}]*)\}/.exec(css)[1];
const pageSize = /size:\s*([\d.]+)mm\s+([\d.]+)mm/i.exec(page);
check('page size is the office paper, 140 × 200 mm',
    !!pageSize && Math.abs(parseFloat(pageSize[1]) - 140) < 0.01
    && Math.abs(parseFloat(pageSize[2]) - 200) < 0.01,
    page.trim().replace(/\s+/g, ' '));

const paperW = parseFloat(pageSize[1]);
const paperH = parseFloat(pageSize[2]);
const margin = parseFloat(/margin:\s*([\d.]+)mm/.exec(page)[1]);
const sheetW = mm(prop('.mark-sheet', 'width'));
const stickerH = mm(prop('.mark', 'height'));
const padTop = mm((prop('.mark', 'padding') || '').split(' ')[0]);

check('the sheet is the printable width (140mm minus both margins)',
    Math.abs(sheetW - (paperW - 2 * margin)) < 0.01, `${sheetW} vs ${paperW - 2 * margin}`);

const cols = /flex:\s*0\s+0\s+100%/.test(block('.mark')) ? 1 : 2;
const rows = 3, perPage = 3;
const cellW = (paperW - 2 * margin) / cols;

check(`a sticker cell is ${cellW} × ${stickerH} mm (one full-width column)`,
    cols === 1 && Math.abs(cellW - sheetW) < 0.01 && Math.abs(stickerH - 62.3) < 0.01,
    `${cellW} × ${stickerH}`);
check('three rows fit the printable height', rows * stickerH <= (paperH - 2 * margin) + 0.01,
    `${rows * stickerH} vs ${paperH - 2 * margin}`);
check('the third row cannot round onto a second page (>= 1mm of slack)',
    (paperH - 2 * margin) - rows * stickerH >= 1,
    `${((paperH - 2 * margin) - rows * stickerH).toFixed(2)}mm`);
check('three stickers per page (one column)', perPage === rows * cols);

/* vertical budget: the head is its tallest child */
const titleBlock = line(parseFloat(prop('.mark-title', 'font-size')), 1.1)
    + 0.4 + line(parseFloat(prop('.mark-sub', 'font-size')), 1.2);
const numberBox = line(parseFloat(prop('.mark-number', 'font-size')), 1.2)
    + 2 * mm(prop('.mark-number', 'padding').split(' ')[0]);
const head = Math.max(mm(prop('.mark-brand', 'height')), titleBlock, numberBox)
    + (mm(prop('.mark-head', 'padding-bottom')) || 0) + 0.5;

const addressLines = 3, contactLines = 2;
const party = line(parseFloat(prop('.mark-label', 'font-size')), 1.2)
    + 0.5 + line(parseFloat(prop('.mark-party strong', 'font-size')), 1.2)
    + 0.5 + addressLines * line(parseFloat(prop('.mark-address', 'font-size') ||
        (block('.mark-party p').match(/font-size:\s*([\d.]+)pt/) || [])[1]), 1.25)
    + 0.5 + contactLines * line(parseFloat(block('.mark-contact').match(/font-size:\s*([\d.]+)pt/)[1]), 1.25);
const meta = 0.8 + line(parseFloat(prop('.mark-label', 'font-size')), 1.2)
    + 0.3 + line(parseFloat(prop('.mark-meta strong', 'font-size')), 1.2);
const captionSize = parseFloat(prop('.mark-qr-hint', 'font-size'));
const foot = mm(prop('.mark-qr-code', 'width'))
    + (mm(prop('.mark-qr-hint', 'margin-top')) || 0)
    + line(captionSize, 1.05) + (mm(prop('.mark-foot', 'padding-top')) || 0) + 0.5;

const gap = mm(prop('.mark', 'gap'));
const partyPadTop = mm(prop('.mark-parties', 'padding-top') || '0');
const partyPadBottom = mm(prop('.mark-parties', 'padding-bottom') || '0');
const content = head + party + meta + foot + 3 * gap + partyPadTop + partyPadBottom;
const box = stickerH - 2 * padTop;

check(`the sticker's rows fit its height (${content.toFixed(1)}mm of ${box}mm)`, content <= box,
    `${content.toFixed(1)} vs ${box}`);
check('there is >= 1mm of slack for a slightly longer address', box - content >= 1,
    `${(box - content).toFixed(1)}mm`);
check('the party block absorbs overflow instead of overlapping the footer',
    /\.mark-parties\s*\{[^}]*flex:\s*1\s+1\s+auto[^}]*min-height:\s*0[^}]*overflow:\s*hidden/.test(css)
    || /\.mark-parties\s*\{[^}]*min-height:\s*0[^}]*overflow:\s*hidden/.test(css));
check('header, meta and footer cannot be squeezed',
    /\.mark-head,\s*\.mark-meta,\s*\.mark-foot\s*\{[^}]*flex:\s*0\s+0\s+auto/.test(css));
check('long addresses are clamped', /-webkit-line-clamp:\s*3/.test(block('.mark-address')));
check('the QR keeps a white plate', /background:\s*#fff/.test(block('.mark-qr-code')));
check('QR colour survives printing', /print-color-adjust:\s*exact/.test(css));

/* --------------------------------------------------------------- 2. markup */

check('the sticker shows the brand logo', /mark-brand/.test(sticker) && /logo_print/.test(sticker));
check('the website is printed', /mark-website/.test(sticker) && /\[.website.\]/.test(sticker));
check('an Instagram QR is rendered',
    /mark-qr-social/.test(sticker) && /\[.instagram.\]/.test(sticker));
check('logistics is gone from the mark', !/Logistic/i.test(sticker));
check('the tracking number is gone from the mark', !/tracking_number/.test(sticker));
check('the copies counter is still shown', /markCopy/.test(sticker));

/* ------------------------------------------------------------------- 3. QR */

let jsqr = null;
try { jsqr = require('jsqr'); } catch (e) { /* optional */ }

const qrSource = fs.readFileSync(path.join(ROOT, 'public/assets/js/qr.js'), 'utf8');
const ShipQR = new Function('window', 'document', 'self',
    qrSource + '\nreturn window.ShipQR;')({ window: {} }, undefined, { window: {} });

const payloads = {
    'tracking URL (internal route)': '/shipments/17',
    'tracking URL (public token)': 'http://localhost:8000/track-shipment/JPDfDz7yBMEi2PV',
    'public tracking (https)': 'https://www.themisspack.com/track-shipment/JPDfDz7yBMEi2PV',
    'instagram': 'https://www.instagram.com/themisspack/',
};

function rasterise(matrix, quiet, scale) {
    const dim = matrix.size + quiet * 2;
    const size = dim * scale;
    const data = new Uint8ClampedArray(size * size * 4).fill(255);
    for (let y = 0; y < matrix.size; y++) {
        for (let x = 0; x < matrix.size; x++) {
            if (!matrix.modules[y][x]) continue;
            for (let dy = 0; dy < scale; dy++) {
                for (let dx = 0; dx < scale; dx++) {
                    const px = ((y + quiet) * scale + dy) * size + ((x + quiet) * scale + dx);
                    data[px * 4] = 0; data[px * 4 + 1] = 0; data[px * 4 + 2] = 0;
                }
            }
        }
    }
    return { data, size };
}

Object.entries(payloads).forEach(([label, value]) => {
    let matrix = null, decoded = null;
    try {
        matrix = ShipQR.matrix(value);
        if (jsqr) {
            const { data, size } = rasterise(matrix, 3, 4);
            const result = jsqr(data, size, size);
            decoded = result && result.data;
        }
    } catch (e) {
        decoded = 'error: ' + e.message;
    }

    if (jsqr) {
        check(`QR decodes: ${label}`, decoded === value,
            `${decoded} (version ${matrix && matrix.version}, ecl ${matrix && matrix.ecl})`);
    } else {
        check(`QR payload encodes: ${label}`, !!matrix, 'jsqr not installed — decode skipped');
    }
});

if (jsqr) {
    const trackMatrix = ShipQR.matrix(payloads['tracking URL (public token)']);
    const moduleMm = mm(prop('.mark-qr-code', 'width')) / (trackMatrix.size + 6);
    check(`the tracking code prints at a scannable pitch (${moduleMm.toFixed(2)} mm/module)`,
        moduleMm >= 0.3, `${moduleMm.toFixed(3)} mm`);
} else {
    check('tracking code pitch check', true, 'skipped (no jsqr)');
}

/* ---------------------------------------------------------------- report */

const failed = out.filter(([, ok]) => !ok);
out.forEach(([name, ok, detail]) =>
    console.log((ok ? '  ok   ' : '  FAIL ') + name + (ok || !detail ? '' : '  -> ' + detail)));
console.log('\nmark: ' + (out.length - failed.length) + ' passed, ' + failed.length + ' failed'
    + (jsqr ? '' : ' (jsqr not installed — decode skipped)'));
process.exit(failed.length ? 1 : 0);
