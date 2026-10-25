/* ==========================================================================
   RESPONSIVE CHECK — every screen keeps working from 360px up
   --------------------------------------------------------------------------
   Run:  node tools/checks/responsive-check.cjs

   The project has one responsive contract (resources/css/layout/responsive.css,
   documented in docs/responsive-design.md) and it only stays true if new
   screens follow it. This tool walks every Blade template and every
   public stylesheet and checks the five ways a screen historically broke:

     1. every standalone page / layout declares the viewport meta
     2. every interactive wide <table> sits in a scroll wrapper whose
        wrapper class carries overflow-x in some stylesheet
     3. no inline style hard-codes a width wider than the narrowest phone
     4. every interactive module stylesheet owns at least one @media rule
        (structural sheets are allow-listed with reasons, below)
     5. the responsive contract is intact: imported LAST in the design-system
        entry and present with its two flagship classes in the built sheet
   ========================================================================== */
'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..', '..');
const VIEWS = path.join(ROOT, 'resources/views');
const PUBLIC_CSS = path.join(ROOT, 'public/assets/css');

const out = [];
const check = (name, ok, detail = '') => out.push([name, !!ok, detail]);

const walk = (dir, acc = []) => {
    for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, e.name);
        e.isDirectory() ? walk(full, acc) : acc.push(full);
    }
    return acc;
};

const bladeFiles = walk(VIEWS).filter(f => f.endsWith('.blade.php'));
const rel = f => path.relative(ROOT, f).replace(/\\/g, '/');
const relV = f => path.relative(VIEWS, f).replace(/\\/g, '/');

/* Print/PDF/e-mail documents are fixed-width paper on purpose: A4 does not
   reflow, and their stylesheets already carry @media print rules. */
const FIXED_MEDIUM = /(^|\/)(print|prints|pdf|voucher|payslip|carton-label|expense-statement)(\/|\.|$)|\/mail\//;

/* ------------------------------------------------------ 1. viewport meta */

const missingViewport = [];
bladeFiles.forEach(file => {
    const text = fs.readFileSync(file, 'utf8');
    if (!/<\s*html[\s>]/i.test(text) && !/<\s*head[\s>]/i.test(text)) return;
    if (!/<meta\s+name="viewport"/i.test(text)) missingViewport.push(relV(file));
});
check('every standalone page declares the viewport meta', missingViewport.length === 0,
    missingViewport.join(' | '));

/* ------------------------------------------------- 2. tables scroll inside */

const cssIndex = fs.readdirSync(PUBLIC_CSS)
    .filter(f => f.endsWith('.css'))
    .map(f => fs.readFileSync(path.join(PUBLIC_CSS, f), 'utf8'))
    .join('\n');

const classHasOverflow = cls => {
    const escaped = cls.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return new RegExp('\\.' + escaped + '(?![\\w-])[^{]*\\{[^}]*overflow(?:-x)?:\\s*(?:auto|scroll|overlay)').test(cssIndex);
};

const unwrappedTables = [];
const missingOverflow = new Set();

bladeFiles.forEach(file => {
    const relFile = relV(file);
    if (FIXED_MEDIUM.test(relFile)) return;

    const text = fs.readFileSync(file, 'utf8');
    for (const m of text.matchAll(/<table[\s>]/gi)) {
        /* The wrapper must precede the table: look back for class="…wrap…" in
           the open elements ahead of it. */
        const before = text.slice(Math.max(0, m.index - 600), m.index);

        /* A totals/footer replica of a master-list table needs no wrapper: at
           <=767px the master-list sheet stacks every row of EVERY table in the
           list, footer included, into labelled cards (master-list.css). */
        if (before.includes('master-list-foot')) continue;

        const wraps = [...before.matchAll(/class="([^"]*)"/g)]
            .flatMap(c => c[1].trim().split(/\s+/))
            .filter(token => /wrap/i.test(token));

        if (!wraps.length) {
            unwrappedTables.push(`${relFile}:${text.slice(0, m.index).split('\n').length}`);
            continue;
        }
        /* At least one wrapper class must carry the scroll; sibling modifier
           classes (e.g. cf-allocation-table-wrap) decorate, not scroll. */
        if (!wraps.some(classHasOverflow)) {
            missingOverflow.add(`${relFile} → ${wraps.map(w => '.' + w).join(' .')}`);
        }
    }
});

check('every interactive wide table sits inside a scroll wrapper', unwrappedTables.length === 0,
    unwrappedTables.join(' | '));
check('every table scroll wrapper carries overflow-x in a stylesheet', missingOverflow.size === 0,
    [...missingOverflow].join(' | '));

/* ------------------------------------------------- 3. no hard-coded width */

const hardWidths = [];
bladeFiles.forEach(file => {
    const relFile = relV(file);
    if (FIXED_MEDIUM.test(relFile)) return;

    const text = fs.readFileSync(file, 'utf8');
    for (const m of text.matchAll(/style="[^"]*?[^-]width:\s*(\d{3,})px/gi)) {
        if (parseInt(m[1], 10) >= 321 && !/max-width/.test(m[0].slice(0, 20))) {
            hardWidths.push(`${relFile}:${text.slice(0, m.index).split('\n').length} width:${m[1]}px`);
        }
    }
});
check('no inline style hard-codes a width wider than a phone', hardWidths.length === 0,
    hardWidths.slice(0, 8).join(' | '));

/* ------------------------------------------- 4. module sheets own a media */

/* Sheets with no @media ON PURPOSE — each must state why it cannot break: */
const STRUCTURAL_SHEETS = {
    'core.css': 'base reset/tokens glue; every rule is width-agnostic',
    'master-flat.css': 'flat layout helpers whose display:block stacks by default',
    'user-avatar.css': 'fixed-size avatar circles; no layout to reflow',
    'office-services.css': 'person-cell inside the scroll-table system; its data columns are hidden on mobile by ui-mobile-secondary in the contract',
};

const medialess = [];
fs.readdirSync(PUBLIC_CSS).filter(f => f.endsWith('.css')).forEach(f => {
    if (STRUCTURAL_SHEETS[f]) return;
    const text = fs.readFileSync(path.join(PUBLIC_CSS, f), 'utf8');
    if (!/@media[^{]*\{/g.test(text)) medialess.push(f);
});
check('every interactive module stylesheet owns at least one @media rule', medialess.length === 0,
    medialess.join(' | '));

/* ------------------------------------------------ 5. the contract is intact */

const entry = fs.readFileSync(path.join(ROOT, 'resources/css/master.css'), 'utf8');
const imports = [...entry.matchAll(/@import\s+'([^']+)'/g)].map(m => m[1]);
check('the responsive contract is imported last in the design-system entry',
    imports[imports.length - 1] === './layout/responsive.css',
    'last import is ' + imports[imports.length - 1]);

const built = path.join(PUBLIC_CSS, 'design-system.css');
const builtText = fs.existsSync(built) ? fs.readFileSync(built, 'utf8') : '';
check('the built design-system sheet carries the responsive contract',
    builtText.includes('data-ui-shell') && builtText.includes('ui-mobile-cards') && builtText.includes('ui-mobile-secondary'),
    'run npm run build:design-system');

/* ---------------------------------------------------------------- report */

const failed = out.filter(([, ok]) => !ok);
out.forEach(([name, ok, detail]) =>
    console.log((ok ? '  ok   ' : '  FAIL ') + name + (ok || !detail ? '' : '  -> ' + detail)));
console.log('\nresponsive: ' + (out.length - failed.length) + ' passed, ' + failed.length + ' failed'
    + ` (${bladeFiles.length} templates, ${fs.readdirSync(PUBLIC_CSS).filter(f => f.endsWith('.css')).length} stylesheets)`);
process.exit(failed.length ? 1 : 0);
