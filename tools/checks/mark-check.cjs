/* ==========================================================================
   MARK CHECK — shipping-mark geometry, address fitting and QR payloads
   --------------------------------------------------------------------------
   Run:  node tools/checks/mark-check.cjs
   Decoding needs `jsqr` (npm i jsqr); without it the geometry checks still
   run and the decode checks report as skipped.

   1. Geometry — parses shipping-mark.css and adds up the sticker's declared
      millimetre sizes against the 85 × 130 mm label.
   2. Address fitting — the one block that varies by record. The character
      budget per line comes from measuring the real font against the 77 mm
      line; the thresholds are read out of the Blade partial, so changing them
      without re-measuring fails here instead of on paper. A long address must
      step down a size rather than lose its tail: the last line carries the
      pin code and the country, which is what a courier sorts by.
   3. Markup — brand, website, both codes, no logistics/tracking fields, the
      contacts escaped, and the pin code repeated in the destination strip.
   4. QR — encodes every payload the mark prints with the app's own encoder
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
const lines = (pt, factor) => pt2mm(pt) * factor;
const fontOf = (selector, name = 'font-size') => parseFloat(prop(selector, name));
const firstGap = (selector) => mm((prop(selector, 'gap') || '0').split(' ')[0]);

/* the wordmark's own aspect ratio decides the header height */
function pngSize(file) {
    const buf = fs.readFileSync(file);
    return { width: buf.readUInt32BE(16), height: buf.readUInt32BE(20) };
}

/* ------------------------------------------------------------- 1. geometry */

const page = /@page\s*\{([^}]*)\}/.exec(css)[1];
const pageSize = /size:\s*([\d.]+)mm\s+([\d.]+)mm/i.exec(page);
check('page size is the label, 85 × 130 mm',
    !!pageSize && Math.abs(parseFloat(pageSize[1]) - 85) < 0.01
    && Math.abs(parseFloat(pageSize[2]) - 130) < 0.01,
    page.trim().replace(/\s+/g, ' '));
check('screen controls span the viewport while the label stays print-sized',
    /\.mark-toolbar\s*\{[^}]*width:\s*100%[^}]*max-width:\s*none/.test(css)
    && /\.mark-sheet\s*\{[^}]*width:\s*85mm/.test(css)
    && /\.no-print\s*\{[^}]*display:\s*none !important/.test(css));

const paperW = parseFloat(pageSize[1]);
const paperH = parseFloat(pageSize[2]);
const margin = parseFloat((/margin:\s*([\d.]+)mm/.exec(page) || [0, 0])[1]);
const sheetW = mm(prop('.mark-sheet', 'width'));
const stickerH = mm(prop('.mark', 'height'));
const pad = (prop('.mark', 'padding') || '0').split(' ').map(mm);
const padTop = pad[0];
const padX = pad.length > 1 ? pad[1] : pad[0];
const gap = mm(prop('.mark', 'gap'));

check('the sheet is the label width (no page margin to inset it)',
    Math.abs(sheetW - (paperW - 2 * margin)) < 0.01, `${sheetW} vs ${paperW - 2 * margin}`);
check(`a sticker fills the label (${sheetW} × ${stickerH} mm)`,
    Math.abs(stickerH - 129.5) < 0.01, `${sheetW} × ${stickerH}`);
check('the sticker cannot round onto a second page (>= .4mm of slack)',
    (paperH - 2 * margin) - stickerH >= 0.4,
    `${((paperH - 2 * margin) - stickerH).toFixed(2)}mm`);

/* the fixed blocks, in declaration order */
/* the printed wordmark comes from config/brand.php, not a hard-coded path */
const brandLogo = /'logo_print'\s*=>\s*'([^']+)'/.exec(fs.readFileSync(path.join(ROOT, 'config/brand.php'), 'utf8'))[1];
const logo = pngSize(path.join(ROOT, 'public', brandLogo));
const logoH = mm(prop('.mark-brand', 'width')) * (logo.height / logo.width);
const trackQr = mm(prop('.mark-qr-code', 'width'));
const socialQr = mm(prop('.mark-qr-social .mark-qr-code', 'width'));
const caption = mm(prop('.mark-qr-hint', 'margin-top')) + lines(fontOf('.mark-qr-hint'), 1.05);

const head = Math.max(logoH, trackQr + caption) + mm(prop('.mark-head', 'padding-bottom')) + 0.5;

const metaRow = lines(fontOf('.mark-label'), 1.2) + 0.3 + lines(fontOf('.mark-meta strong'), 1.35);
const metaPad = mm((prop('.mark-meta', 'padding') || '0').split(' ')[0]);
const metaGap = mm((prop('.mark-meta', 'gap') || '0').split(' ')[0]);
const foot = mm(prop('.mark-foot', 'padding-top')) + socialQr + caption;
const partyPad = mm(prop('.mark-parties', 'padding-bottom') || '0');
const partyGap = mm(prop('.mark-parties', 'gap'));

const meta = metaRow + 2 * metaPad + 0.3;
const half = ((stickerH - 2 * padTop) - head - 3 * gap - meta - foot - partyPad - partyGap) / 2;

check('the header, meta strip and footer leave the parties a workable half',
    half >= 26, `${half.toFixed(1)}mm each`);

/* the optional label chip rides in the footer, where the codes set the height:
   if the branding column ever grew past the code column the chip would start
   costing the addresses space */
const brandfoot = lines(fontOf('.mark-website'), 1.2) + 0.4 + lines(fontOf('.mark-tagline'), 1.35)
        + 0.7 + lines(fontOf('.mark-foot .mark-label-chip'), 1.2)
    + 2 * mm((prop('.mark-foot .mark-label-chip', 'padding') || '0').split(' ')[0]);
const codeColumn = socialQr + caption + 0.3;
check('the label chip rides along in the footer without growing it',
    brandfoot <= codeColumn + 0.01,
    `branding column ${brandfoot.toFixed(1)}mm vs codes ${codeColumn.toFixed(1)}mm`);
check('the parties split the block in half',
    /grid-template-rows:\s*1fr\s+1fr/.test(block('.mark-parties')));
check('each party centres in its own half',
    /justify-content:\s*center/.test(block('.mark-party')));

/* ------------------------------------------------------ 2. address fitting */

/* Characters per line measured with the real font against the 77mm content
   width (ImageMagick, DejaVu Sans: 10pt → 40, 9pt → 44, 8.5pt → 48, 7.5pt → 54).
   The per-party figures are read from the partial so the two cannot drift. */
const CAPACITY = { 10: 40, 9: 44, 8.5: 48, 7.5: 54 };
const clampLines = parseInt(/-webkit-line-clamp:\s*(\d+)/.exec(block('.mark-address'))[1], 10);

const budget = /\$markAddressCapacity\s*=\s*\[([^\]]+)\]/.exec(sticker);
const lineBudget = /\$markAddressLines\s*=\s*\[([^\]]+)\]/.exec(sticker);
const capacity = budget
    ? Object.fromEntries([...budget[1].matchAll(/'(\w+)'\s*=>\s*([\d.]+)/g)].map(m => [m[1], parseFloat(m[2])]))
    : {};

check('the partial sizes a long address from its length',
    !!budget && !!lineBudget && capacity.to > 0 && capacity.from > 0 && capacity.compact > 0,
    `capacity ${JSON.stringify(capacity)}`);
check('the clamp leaves room for four lines', clampLines >= 4, `${clampLines} lines`);
/* The step rules have to come *after* the per-party rules they override: the
   receiver's `.mark-party-to p` and the shipper's `.mark-party-from
   .mark-contact` have the same specificity, so source order decides which one
   wins and a rule in the wrong place silently does nothing. */
const stepAt = css.indexOf('.mark-party.is-compact p');
const overridden = ['.mark-party-to p', '.mark-party-from .mark-contact', '.mark-party-from p']
    .map(sel => css.indexOf(sel));
check('a long address steps down a size instead of being cut off',
    /\.mark-party\.is-compact p[\s\S]{0,80}font-size:\s*8\.5pt/.test(css)
    && /\.mark-party\.is-longer p[\s\S]{0,80}font-size:\s*7\.5pt/.test(css)
    && /\.mark-party\.is-compact \.mark-contact[\s\S]{0,80}font-size:/.test(css));
check('the step rules come after the rules they override',
    stepAt > Math.max(...overridden),
    `step rules at ${stepAt}, party rules at ${overridden.join(', ')}`);

const linesFor = () => Object.fromEntries(
    [...lineBudget[1].matchAll(/'(\w+)'\s*=>\s*([\d.]+)/g)].map(m => [m[1], parseFloat(m[2])]));

/* A party block at a given address: the size it lands on, the lines it needs
   and the height it takes. The name keeps its size when the address steps
   down — only the address and contact paragraphs change. */
function partyBlock(party) {
    const sizes = party === 'to'
        ? { base: fontOf('.mark-party-to p'), name: fontOf('.mark-party-to strong', 'font-size'), nameGap: 0.6 }
        : { base: fontOf('.mark-party p'), name: fontOf('.mark-party-from strong'), nameGap: 0.5 };

    return (characters) => {
        const keep = capacity[party] * linesFor().keep;
        const compact = capacity.compact * linesFor().step;
        const size = characters > compact ? 7.5 : characters > keep ? 8.5 : sizes.base;
        const needed = Math.ceil(characters / CAPACITY[size]);

        const height = lines(fontOf('.mark-label'), 1.2)
            + sizes.nameGap + lines(sizes.name, 1.2)
            + 0.5 + Math.min(clampLines, needed) * lines(size, 1.3)
            + 0.5 + 2 * lines(size, 1.3);

        return { size, needed, height, keep, compact };
    };
}

['to', 'from'].forEach(party => {
    const at = partyBlock(party);
    const base = { to: 10, from: 9 }[party];
    const keep = capacity[party] * linesFor().keep;
    const compact = capacity.compact * linesFor().step;

    [
        [`a ${keep}-character address stays at the base size`, keep, base],
        [`one character more steps the address down to 8.5pt`, keep + 1, 8.5],
        [`a ${compact}-character address still prints complete at 8.5pt`, compact, 8.5],
        [`one character more steps the address down to 7.5pt`, compact + 1, 7.5],
        [`a ${clampLines * CAPACITY[7.5]}-character address still prints complete at 7.5pt`,
            clampLines * CAPACITY[7.5], 7.5],
    ].forEach(([label, characters, size]) => {
        const result = at(characters);
        check(`${party}: ${label}`,
            result.size === size && result.needed <= clampLines && result.height <= half,
            `${result.size}pt, ${result.needed} lines, ${result.height.toFixed(1)}mm of ${half.toFixed(1)}mm`);
    });
});
check('the party block absorbs overflow instead of overlapping the footer',
    /\.mark-parties\s*\{[^}]*min-height:\s*0[^}]*overflow:\s*hidden/.test(css));
check('header, meta and footer cannot be squeezed',
    /\.mark-head,\s*\.mark-meta,\s*\.mark-foot\s*\{[^}]*flex:\s*0\s+0\s+auto/.test(css));
check('no inline styles in the partial (the stylesheet is authoritative)',
    !/style="/.test(sticker));
check('QR colour survives printing', /print-color-adjust:\s*exact/.test(css));
check('the QR keeps a white plate', /background:\s*#fff/.test(block('.mark-qr-code')));
check('the footer branding cannot paint under a code',
    /\.mark-brandfoot\s*\{[^}]*overflow-wrap:\s*anywhere/.test(css));

/* --------------------------------------------------------------- 3. markup */

check('the sticker shows the brand logo', /mark-brand/.test(sticker) && /logo_print/.test(sticker));
check('the website is printed', /mark-website/.test(sticker) && /\[.website.\]/.test(sticker));
check('a tracking QR is rendered', /data-ship-qr/.test(sticker));
check('an Instagram QR is rendered',
    /mark-qr-social/.test(sticker) && /\[.instagram.\]/.test(sticker));
check('the parties carry their own classes',
    /mark-party mark-party-to/.test(sticker) && /mark-party mark-party-from/.test(sticker));
check('the contacts are escaped before the <br> join',
    /array_map\('e'/.test(sticker) && /implode\('<br>'/.test(sticker));
/* The destination carries city, state, country and pin code. If it wraps, the
   meta row grows and takes an address line with it, so it has to fit on one
   line: measured at about 45 characters per 77mm at 8pt. */
const destinationCapacity = 45;
const destinationColumn = (sheetW - 2 * padX) - 15 - 4;   /* package column + gap */
check('the destination value fits its column on one line',
    fontOf('.mark-meta-destination strong') <= 8
    && destinationCapacity * (destinationColumn / 77) >= 33,
    `${fontOf('.mark-meta-destination strong')}pt in ${destinationColumn.toFixed(0)}mm holds about ${Math.floor(destinationCapacity * (destinationColumn / 77))} characters`);

check('the address does not repeat what the free text already says',
    /mb_strpos\(\$fold\(\$address\), \$fold\(\$part\)\)/.test(sticker));

/* the label that reported the truncation: 145 characters, which used to be cut
   at three lines with the pin code and country lost */
const reported = 'D-4/4, July Apartment, Girdharnagar Road, Behind Indane Gas Company, '
    + 'Opposite Chandramani Hospital, Shahibaag, Ahmedabad, Gujarat, India - 380004';
const reportedBlock = partyBlock('to')(reported.length);
check(`the reported ${reported.length}-character address prints complete`,
    reportedBlock.size === 8.5 && reportedBlock.needed <= clampLines && reportedBlock.height <= half,
    `${reportedBlock.size}pt, ${reportedBlock.needed} lines, ${reportedBlock.height.toFixed(1)}mm of ${half.toFixed(1)}mm`);

check('the pin code is repeated in the destination strip',
    sticker.includes('$markDestination') && sticker.includes('preg_match') && sticker.includes('\\d{6}'));
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
    [['.mark-qr-code', 'tracking', payloads['tracking URL (public token)']],
     ['.mark-qr-social .mark-qr-code', 'instagram', payloads.instagram]].forEach(([sel, name, url]) => {
        const size = mm(prop(sel, 'width'));
        const pitch = size / (ShipQR.matrix(url).size + 6);
        check(`the ${name} code prints at a scannable pitch (${pitch.toFixed(2)} mm/module)`,
            pitch >= 0.3, `${pitch.toFixed(3)} mm over ${size}mm`);
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
