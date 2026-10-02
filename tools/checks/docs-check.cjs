/* ==========================================================================
   DOCS CHECK — the bills behind the ledger
   --------------------------------------------------------------------------
   Run:  node tools/checks/docs-check.cjs
   No dependencies. Exits non-zero on failure.

   Wave 1 of the cashflow roadmap moved the paperwork out of a Drive folder and
   onto the entry it belongs to. What has to stay true for that to work:

     - an entry owns its documents (one table, one relation, one place a file
       is filed) and the ledger can count them without a query per row;
     - the archive route is registered *before* the resource route, or
       /cashflows/documents is read as an entry id and 404s;
     - the server and the file picker agree on what is allowed: the accept
       attribute offers exactly what the controller stores, and every upload
       form is multipart or the browser posts the name and not the file;
     - the month-end number is one number: the ledger's "Missing documents"
       chip counts the same rows the archive reports as outstanding;
     - this repository has no PHP runtime in CI, so the new PHP is at least
       scanned for the imbalance a missing brace leaves behind.
   ========================================================================== */
'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..', '..');
const read = file => fs.readFileSync(path.join(ROOT, file), 'utf8');
const exists = file => fs.existsSync(path.join(ROOT, file));

const out = [];
const check = (name, ok, detail = '') => out.push([name, !!ok, detail]);

const walk = (dir, acc = []) => {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);
        entry.isDirectory() ? walk(full, acc) : acc.push(full);
    }
    return acc;
};

const rel = full => path.relative(ROOT, full).split(path.sep).join('/');

/* ------------------------------------------------------------- the record */

const migration = walk(path.join(ROOT, 'database/migrations'))
    .map(rel).find(f => /create_cashflow_attachments_table/.test(f));
check('the documents table has its own migration', !!migration, migration || 'not found');

const migrationText = migration ? read(migration) : '';
check('a document belongs to an entry and goes when the entry goes',
    /foreignId\('cashflow_entry_id'\)->constrained\('cashflow_entries'\)->cascadeOnDelete\(\)/.test(migrationText));

/* the month reads the type and the archive sorts by when it was filed */
check('the archive has the indexes it filters on',
    /index\(\['cashflow_entry_id', 'document_type'\]\)/.test(migrationText)
    && /index\('created_at'\)/.test(migrationText));

const model = read('app/Models/CashflowAttachment.php');
check('one place decides what a document type is',
    /public static function documentTypeOptions\(\): array/.test(model)
    && /'bank_slip'/.test(model) && /'gst'/.test(model));

const entryModel = read('app/Models/CashflowEntry.php');
check('the entry owns its documents',
    /public function attachments\(\)/.test(entryModel)
    && /hasMany\(CashflowAttachment::class, 'cashflow_entry_id'\)/.test(entryModel));

/* -------------------------------------------------------------- the routes */

const routes = read('routes/web.php');
const archiveAt = routes.indexOf("'/cashflows/documents'");
const resourceAt = routes.indexOf("Route::resource('cashflows'");
check('the archive route exists and is not read as an entry id',
    archiveAt > -1 && resourceAt > -1 && archiveAt < resourceAt,
    'archive @' + archiveAt + ' vs resource @' + resourceAt);

check('upload and removal have routes, with the verbs they need',
    /Route::post\('\/cashflows\/\{cashflow\}\/attachments'/.test(routes)
    && /Route::delete\('\/cashflow-attachments\/\{attachment\}'/.test(routes)
    && /->name\('cashflows\.attachments\.store'\)/.test(routes)
    && /->name\('cashflows\.attachments\.destroy'\)/.test(routes));

/* ------------------------------------------------------------ the uploads */

const controller = read('app/Http/Controllers/CashflowAttachmentController.php');
const allowlist = (controller.match(/private const ALLOWED_EXTENSIONS = \[([\s\S]*?)\];/) || [, ''])[1]
    .match(/'([a-z0-9]+)'/g);
const allowed = new Set((allowlist || []).map(e => e.replace(/'/g, '')));

check('the controller keeps an extension allowlist',
    allowed.size >= 10 && allowed.has('pdf') && allowed.has('jpg'), [...allowed].join(','));

check('an upload is a real file with a size ceiling',
    /'attachments\.\*' => \['required', 'file', 'max:20480'\]/.test(controller));

check('the type is validated against the one list',
    /Rule::in\(array_keys\(CashflowAttachment::documentTypeOptions\(\)\)\)/.test(controller));

/* every upload form: multipart, an accept list, and a label for the picker */
const blades = walk(path.join(ROOT, 'resources/views/cashflows'))
    .filter(f => f.endsWith('.blade.php'));
const multipartHazards = [];
const acceptHazards = [];

blades.forEach(file => {
    const text = read(rel(file));
    [...text.matchAll(/<form\b([^>]*)>([\s\S]*?)<\/form>/g)].forEach(m => {
        const [, attrs, body] = m;
        if (!/type="file"/.test(body)) return;
        const where = rel(file) + ' @' + text.slice(0, m.index).split('\n').length;

        if (!/enctype="multipart\/form-data"/.test(attrs)) {
            multipartHazards.push(where);
        }

        [...body.matchAll(/<input\b[^>]*type="file"[^>]*>/g)].forEach(input => {
            const accept = (input[0].match(/accept="([^"]*)"/) || [, ''])[1];
            const offered = new Set(accept.split(',').map(s => s.trim().replace(/^\./, '')).filter(Boolean));
            const missing = [...allowed].filter(ext => !offered.has(ext));
            if (offered.size === 0 || missing.length) {
                acceptHazards.push(where + ' missing ' + (missing.join(',') || 'accept'));
            }
        });
    });
});

check('every upload form posts the file itself', multipartHazards.length === 0, multipartHazards.join(' | '));
check('the picker offers exactly what the server stores', acceptHazards.length === 0, acceptHazards.join(' | '));

check('a document type is never spelled out in a view',
    !blades.some(f => /'bank_slip'|"bank_slip"/.test(read(rel(f)))));

/* -------------------------------------------------------- the ledger's flag */

const cashflowController = read('app/Http/Controllers/CashflowController.php');
check('the ledger counts documents with the page, not per row',
    /withCount\('attachments'\)/.test(cashflowController));

check('the to-do filter is a real query, not a label',
    /whereDoesntHave\('attachments'\)/.test(cashflowController)
    && /'missing_documents' => \$count\(\['documents' => 'missing'\]\)/.test(cashflowController));

const indexView = read('resources/views/cashflows/index.blade.php');
check('the ledger shows the count on the row and the chip on the bar',
    /attachments_count/.test(indexView)
    && /\$chipCounts\['missing_documents'\]/.test(indexView)
    && /route\('cashflows\.documents'\)/.test(indexView));

/* ---------------------------------------------------------- the archive page */

const archiveView = read('resources/views/cashflows/documents.blade.php');
check('the archive is the same list surface as every other list',
    /class="cf cashflow-documents master-list"/.test(archiveView)
    && /master-list-chips/.test(archiveView)
    && /x-pagination :items="\$documents"/.test(archiveView));

check('the archive wears the shared strip, not a look-alike',
    /master-list-applied-title/.test(archiveView) && !/master-list-applied-label/.test(archiveView));

check('its rows stack on a phone like every other list',
    /data-label="File"/.test(archiveView) && /data-label="Action"/.test(archiveView));

/* --------------------------------------------------------------- the theme */

const cashflowsCss = read('public/assets/css/cashflows.css');
check('the document tones have a dark counterpart',
    /:root\[data-theme="dark"\] \.cf-doc-state\.is-filed\s*\{\s*--tone-fg:/.test(cashflowsCss)
    && /:root\[data-theme="dark"\] \.cf-doc-state\.is-missing\s*\{\s*--tone-fg:/.test(cashflowsCss));

/* ------------------------------------------------------- the PHP, scanned */

/* Comments and strings first, then brackets: an apostrophe inside a comment
   ("the entry's own scope") otherwise opens a string that never closes, and a
   brace count over the raw text reports the wrong answer either way. */
function bracketBalance(text) {
    let i = 0, depth = 0, min = 0, inLine = false, inBlock = false, quote = null;
    while (i < text.length) {
        const ch = text[i], next = text[i + 1];
        if (inLine) { if (ch === '\n') inLine = false; i++; continue; }
        if (inBlock) { if (ch === '*' && next === '/') { inBlock = false; i += 2; continue; } i++; continue; }
        if (quote) {
            if (ch === '\\') { i += 2; continue; }
            if (ch === quote) quote = null;
            i++; continue;
        }
        if (ch === '/' && next === '/') { inLine = true; i += 2; continue; }
        if (ch === '#') { inLine = true; i++; continue; }
        if (ch === '/' && next === '*') { inBlock = true; i += 2; continue; }
        if (ch === "'" || ch === '"') { quote = ch; i++; continue; }
        if (ch === '{') depth++;
        if (ch === '}') { depth--; min = Math.min(min, depth); }
        i++;
    }
    return { depth, min };
}

const phpFiles = [
    migration,
    'app/Models/CashflowAttachment.php',
    'app/Http/Controllers/CashflowAttachmentController.php',
    'routes/web.php',
].filter(Boolean);

const phpHazards = phpFiles.filter(file => {
    const { depth, min } = bracketBalance(read(file));
    return depth !== 0 || min < 0;
});
check('the new PHP is bracket-balanced (no interpreter here to ask)',
    phpFiles.length >= 4 && phpHazards.length === 0, phpHazards.join(', '));

check('every new PHP file opens with a php tag and closes with none',
    phpFiles.every(file => /^<\?php/.test(read(file)) && !/\?>\s*$/.test(read(file))));

/* ---------------------------------------------------------------- report */

const failed = out.filter(([, ok]) => !ok);
out.forEach(([name, ok, detail]) =>
    console.log((ok ? '  ok   ' : '  FAIL ') + name + (ok || !detail ? '' : '  -> ' + detail)));
console.log('\ndocs: ' + (out.length - failed.length) + ' passed, ' + failed.length + ' failed');
process.exit(failed.length ? 1 : 0);
