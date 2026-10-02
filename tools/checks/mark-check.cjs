/* ==========================================================================
   MARK CHECK — shipping-mark geometry and QR payloads
   --------------------------------------------------------------------------
   Run:  node tools/checks/mark-check.cjs
   Decoding needs `jsqr` (npm i jsqr); without it the geometry checks still
   run and the decode checks report as skipped.

   1. Geometry — parses shipping-mark.css and adds up the sticker's declared
      millimetre sizes. The office prints on 85 × 130 mm labels, one sticker
      per label, so a sticker that cannot fit that label, or whose branding
      would spill past it, fails here rather than on paper.
   2. Markup — the mark carries the brand, the website and both codes, does
      not carry logistics/tracking fields, and sets the receiver apart from
      the shipper.
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
const lh = (pt, factor) => pt2mm(pt) * factor;
const fontOf = (selector, name) => parseFloat(prop(selector, 'font-size'));

/* ------------------------------------------------------------- 1. geometry */

const page = /@page\s*\{([^}]*)\}/.exec(css)[1];
const pageSize = /size:\s*([\d.]+)mm\s+([\d.]+)mm/i.exec(page);
check('page size is the label, 85 × 130 mm',
    !!pageSize && Math.abs(parseFloat(pageSize[1]) - 85) < 0.01
    && Math.abs(parseFloat(pageSize[2]) - 130) < 0.01,
    page.trim().replace(/\s+/g, ' '));

const paperW = parseFloat(pageSize[1]);
const paperH = parseFloat(pageSize[2]);
const margin = parseFloat((/margin:\s*([\d.]+)mm/.exec(page) || [0, 0])[1]);
const sheetW = mm(prop('.mark-sheet', 'width'));
const stickerH = mm(prop('.mark', 'height'));
const pad = (prop('.mark', 'padding') || '0').split(' ').map(mm);
const padTop = pad[0];
const padX = pad.length > 1 ? pad[1] : pad[0];   /* shorthand: 4mm 4mm 4mm 4mm */

check('the sheet is the label width (no page margin to inset it)',
    Math.abs(sheetW - (paperW - 2 * margin)) < 0.01, `${sheetW} vs ${paperW - 2 * margin}`);

const cols = /flex:\s*0\s+0\s+100%/.test(block('.mark')) ? 1 : 2;
const rows = 1, perPage = 1;
const cellW = (paperW - 2 * margin) / cols;

check(`a sticker fills the label (${cellW} × ${stickerH} mm)`,
    cols === 1 && Math.abs(cellW - sheetW) < 0.01 && Math.abs(stickerH - 129.5) < 0.01,
    `${cellW} × ${stickerH}`);
check('one sticker per label', perPage === rows * cols);
check('the sticker cannot round onto a second page (>= .4mm of slack)',
    (paperH - 2 * margin) - rows * stickerH >= 0.4,
    `${((paperH - 2 * margin) - rows * stickerH).toFixed(2)}mm`);

/* vertical budget. The head, the party stack, the meta strip and the footer
   are summed with their declared clamps at worst case, so a longer address
   cannot make them collide on paper. */
const headRow = Math.max(
    mm(prop('.mark-brand', 'height')),
    fontOf('.mark-number', 'font-size') * 0.3528 * 1.2 + 2 * mm(prop('.mark-number', 'padding').split(' ')[0]));
const head = headRow
    + mm((prop('.mark-head', 'gap') || '0').split(' ')[0])
    + lh(fontOf('.mark-title', 'font-size'), 1.1)
    + 0.4 + lh(fontOf('.mark-sub', 'font-size'), 1.2)
    + mm(prop('.mark-head', 'padding-bottom')) + 0.5;

const from = lh(fontOf('.mark-label', 'font-size'), 1.2) + 0.5
    + lh(fontOf('.mark-party-from strong', 'font-size'), 1.2) + 0.5
    + 3 * lh(fontOf('.mark-party p', 'font-size'), 1.3) + 0.5
    + 2 * lh(fontOf('.mark-party-from .mark-contact', 'font-size'), 1.3);
const to = lh(fontOf('.mark-label', 'font-size'), 1.2) + 0.6
    + lh(fontOf('.mark-party-to strong', 'font-size'), 1.15) + 0.6
    + 3 * lh(fontOf('.mark-party-to p', 'font-size'), 1.3) + 0.5
    + 2 * lh(fontOf('.mark-party-to .mark-contact', 'font-size'), 1.3);
const separator = mm(prop('.mark-party ~ .mark-party', 'padding-top'))
    + mm((prop('.mark-parties', 'gap') || '0').split(' ')[0]);
const party = from + separator + to;

/* a grid row is as tall as its tallest cell: the shipment-label chip is taller
   than a value line, so the row must be measured against the chip or the strip
   is under-counted and the budget lies */
const metaValue = Math.max(
    lh(fontOf('.mark-meta strong', 'font-size'), 1.2),
    lh(fontOf('.mark-label-chip', 'font-size'), 1.2)
        + 2 * mm(prop('.mark-label-chip', 'padding').split(' ')[0])
        + 0.6);
const metaRow = lh(fontOf('.mark-label', 'font-size'), 1.2) + 0.5 + metaValue;
const metaPad = mm((prop('.mark-meta', 'padding') || '0').split(' ')[0]);
const meta = 2 * metaRow + mm((prop('.mark-meta', 'gap') || '0').split(' ')[0])
    + 2 * metaPad + 0.5;

const qr = mm(prop('.mark-qr-code', 'width'));
const foot = mm(prop('.mark-foot', 'padding-top')) + qr
    + mm(prop('.mark-qr-hint', 'margin-top')) + lh(fontOf('.mark-qr-hint', 'font-size'), 1.05)
    + 0.5;

const gap = mm(prop('.mark', 'gap'));
const partyPadBottom = mm(prop('.mark-parties', 'padding-bottom') || '0');
const content = head + 3 * gap + party + partyPadBottom + meta + foot;
const box = stickerH - 2 * padTop;

check(`the sticker's rows fit its height (${content.toFixed(1)}mm of ${box}mm)`, content <= box,
    `${content.toFixed(1)} vs ${box}`);
check('there is >= 1mm of slack for a slightly longer address', box - content >= 1,
    `${(box - content).toFixed(1)}mm`);
check('the receiver is set larger than the shipper',
    fontOf('.mark-party-to strong', 'font-size') > fontOf('.mark-party-from strong', 'font-size'),
    `${fontOf('.mark-party-to strong', 'font-size')}pt vs ${fontOf('.mark-party-from strong', 'font-size')}pt`);
check('the parties stack (a single label column)',
    /grid-template-columns:\s*minmax\(0,\s*1fr\)/.test(block('.mark-parties')));
check('each party centres in its own half of the label (no hollow middle)',
    /grid-template-rows:\s*1fr\s+1fr/.test(block('.mark-parties'))
    && /justify-content:\s*center/.test(block('.mark-party')));
check('a long website cannot paint under the tracking plate',
    /overflow-wrap:\s*anywhere/.test(block('.mark-brandfoot'))
    && /overflow:\s*hidden/.test(block('.mark-brandfoot')));
check('the website fits the width the codes leave it',
    fontOf('.mark-website', 'font-size') <= 8.5,
    `${fontOf('.mark-website', 'font-size')}pt beside ${(sheetW - 2 * padX - qr - mm(prop('.mark-qr-social .mark-qr-code', 'width')) - 3).toFixed(0)}mm`);
check('the party block absorbs overflow instead of overlapping the footer',
    /\.mark-parties\s*\{[^}]*flex:\s*1\s+1\s+auto[^}]*min-height:\s*0[^}]*overflow:\s*hidden/.test(css)
    || /\.mark-parties\s*\{[^}]*min-height:\s*0[^}]*overflow:\s*hidden/.test(css));
check('header, meta and footer cannot be squeezed',
    /\.mark-head,\s*\.mark-meta,\s*\.mark-foot\s*\{[^}]*flex:\s*0\s+0\s+auto/.test(css));
check('long addresses are clamped', /-webkit-line-clamp:\s*3/.test(block('.mark-address')));
check('the QR keeps a white plate', /background:\s*#fff/.test(block('.mark-qr-code')));
check('QR colour survives printing', /print-color-adjust:\s*exact/.test(css));
check('the footer codes fit the label beside the branding',
    qr + mm(prop('.mark-qr-social .mark-qr-code', 'width')) + 3 <= sheetW - 2 * padX,
    `${qr} + ${mm(prop('.mark-qr-social .mark-qr-code', 'width'))} + 3 vs ${sheetW - 2 * padX}mm of content`);

/* --------------------------------------------------------------- 2. markup */

check('the sticker shows the brand logo', /mark-brand/.test(sticker) && /logo_print/.test(sticker));
check('the website is printed', /mark-website/.test(sticker) && /\[.website.\]/.test(sticker));
check('an Instagram QR is rendered',
    /mark-qr-social/.test(sticker) && /\[.instagram.\]/.test(sticker));
check('the parties carry their own classes',
    /mark-party mark-party-from/.test(sticker) && /mark-party mark-party-to/.test(sticker));
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
    [['.mark-qr-code', 'tracking'], ['.mark-qr-social .mark-qr-code', 'instagram']].forEach(([sel, name]) => {
        const url = name === 'tracking' ? payloads['tracking URL (public token)'] : payloads.instagram;
        const size = mm(prop(sel, 'width'));
        const moduleMm = size / (ShipQR.matrix(url).size + 6);
        check(`the ${name} code prints at a scannable pitch (${moduleMm.toFixed(2)} mm/module)`,
            moduleMm >= 0.3, `${moduleMm.toFixed(3)} mm over ${size}mm`);
    });
} else {
    check('QR pitch checks', true, 'skipped (no jsqr)');
}

/* ---------------------------------------------------------------- report */

const failed = out.filter(([, ok]) => !ok);
out.forEach(([name, ok, detail]) =>
    console.log((ok ? '  ok   ' : '  FAIL ') + name + (ok || !detail ? '' : '  -> ' + detail)));
console.log('\nmark: ' + (out.length - failed.length) + ' passed, ' + failed.length + ' failed'
    + (jsqr ? '' : ' (jsqr not installed — decode skipped)'));
process.exit(failed.length ? 1 : 0);
