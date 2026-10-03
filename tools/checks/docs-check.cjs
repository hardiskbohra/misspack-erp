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

/* The filter itself moved into the shared vocabulary (App\Services\CashflowFilters)
   when the analysis builder started using the same dimensions: the ledger, the
   archive and a report's drill-down now mean the same rows by "missing
   documents". The guard follows the query rather than the file it used to live
   in — it is still the same rule: a real query, not a label on a chip. */
const cashflowFilters = read('app/Services/CashflowFilters.php');
check('the to-do filter is a real query, not a label',
    /whereDoesntHave\('attachments'\)/.test(cashflowFilters)
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
    && /:root\[data-theme="dark"\] \.cf-doc-state\.is-missing\s*\{\s*--tone-fg:/.test(cashflowsCss)
    && /:root\[data-theme="dark"\] \.cf-doc-state\.is-unlinked\s*\{\s*--tone-fg:/.test(cashflowsCss));

/* ------------------------------- a document with no entry behind it ----- */

/* The bill that arrives before its payment, the purchase bill booked to a
   project, the invoice settled outside this ledger: a document has to be
   fileable, findable and usable without an entry — and matchable later when
   one appears. The link is optional, never a precondition. */

const standaloneMigration = walk(path.join(ROOT, 'database/migrations'))
    .map(rel).find(f => /let_cashflow_documents_stand_alone/.test(f));
check('the entry link became optional in a migration of its own',
    !!standaloneMigration, standaloneMigration || 'not found');

const standaloneText = standaloneMigration ? read(standaloneMigration) : '';
check('the column is rebuilt nullable only after the foreign key comes off',
    /dropForeign\(\['cashflow_entry_id'\]\)/.test(standaloneText)
    && /foreignId\('cashflow_entry_id'\)->nullable\(\)->change\(\)/.test(standaloneText)
    && standaloneText.indexOf('dropForeign') < standaloneText.indexOf('->nullable()->change()'));
const cascadeKey = /->foreign\('cashflow_entry_id'\)->references\('id'\)->on\('cashflow_entries'\)->cascadeOnDelete\(\)/g;
check('deleting an entry still deletes the documents filed against it',
    (standaloneText.match(cascadeKey) || []).length >= 2
    && /->foreign\('cashflow_entry_id'\)->references\('id'\)->on\('cashflow_entries'\)->cascadeOnDelete\(\)/
        .test(standaloneText.slice(standaloneText.indexOf('public function down'))),
    'the key goes back on after the rebuild, and under the rollback too');
check('a stand-alone document carries its own party, date, amount and currency',
    /->string\('party_name'\)->nullable\(\)/.test(standaloneText)
    && /->date\('document_date'\)->nullable\(\)/.test(standaloneText)
    && /->decimal\('amount', 16, 2\)->nullable\(\)/.test(standaloneText)
    && /->string\('currency', 3\)->default\('INR'\)/.test(standaloneText));
check('the rollback clears the rows it cannot keep, before it needs the column',
    /DB::table\('cashflow_attachments'\)->whereNull\('cashflow_entry_id'\)->delete\(\)/.test(standaloneText)
    && standaloneText.indexOf('whereNull') < standaloneText.indexOf('nullable(false)'));

check('the model can ask for the documents no entry has claimed',
    /public function scopeUnlinked\(\$query\)[\s\S]{0,80}whereNull\('cashflow_entry_id'\)/.test(model)
    && /public function scopeLinked\(\$query\)[\s\S]{0,80}whereNotNull\('cashflow_entry_id'\)/.test(model)
    && /public function isLinked\(\): bool/.test(model));
check('a matched document keeps the party written on the bill',
    /if \(filled\(\$this->party_name\)\) \{\s*return \(string\) \$this->party_name;/.test(model));
check('the date a month owns falls back the same way the query does',
    /return \$this->document_date \?\? \$this->cashflowEntry\?->entry_date \?\? \$this->created_at;/.test(model));

check('filing without an entry is a first-class upload path, not a second one',
    /public function storeStandalone\(Request \$request\): RedirectResponse[\s\S]{0,80}return \$this->file\(\$request, null\);/
        .test(controller)
    && /private function file\(Request \$request, \?CashflowEntry \$entry\)/.test(controller));
check('one upload path means one allowlist, one ceiling, one metadata set',
    /\$this->validateAllowedFile\(\$file\)/.test(controller)
    && /'currency' => \$data\['currency'\] \?\? 'INR'/.test(controller)
    && /'standalone'/.test(controller));

check('a match claims a document that is still unclaimed, and only that',
    /CashflowAttachment::unlinked\(\)->findOrFail\(\$data\['attachment_id'\]\)/.test(controller));
check('the archive can be asked which documents have no entry',
    /\$state = \(string\) \$request->query\('state', 'all'\)/.test(controller)
    && /in_array\(\$state, \['all', 'linked', 'unlinked'\]/.test(controller)
    && /=== 'unlinked', fn \(\$q\) => \$q->unlinked\(\)/.test(controller));
check('the two attachment routes sit with the archive, above the resource route',
    /Route::post\('\/cashflows\/\{cashflow\}\/attachments\/link'/.test(routes)
    && /Route::post\('\/cashflow-attachments'/.test(routes)
    && /->name\('cashflows\.attachments\.link'\)/.test(routes)
    && /->name\('cashflows\.attachments\.storeStandalone'\)/.test(routes)
    && routes.indexOf("Route::post('/cashflow-attachments'") < resourceAt);
/* the to-do list asks about *entries* with nothing filed against them; a
   document standing on its own is not attached to any entry, so it must never
   make an entry look answered */
check('the month-end to-do list counts entries, not documents',
    /private function missingCount\(array \$filters\): int[\s\S]{0,240}CashflowEntry::query\(\)[\s\S]{0,120}whereDoesntHave\('attachments'\)/
        .test(controller));

check('the archive row says whose bill it is, when, and how much — entry or not',
    /displayDate\(\)/.test(archiveView) && /partyLabel\(\)/.test(archiveView)
    && /amountLabel\(\)/.test(archiveView)
    && /class="cf-doc-state is-unlinked"[^>]*>\s*Not matched/.test(archiveView));
check('the archive counts what is still unmatched, on the chip and the card',
    /\$chipCounts\['unlinked'\]/.test(archiveView) && /\$unlinkedCount/.test(archiveView)
    && /'state' => 'unlinked'/.test(archiveView));

/* One control per dimension. The type is printed on the row and sorted by the
   accountant's eye; a filter for it meant a second set of chips saying what the
   row already said, and a "All Types" select under them saying it a third time.
   The claim state and the month are the two questions this list is asked. */
/* the chip row itself, so the guard reads what the row offers rather than
   which spelling a stray route() call happens to use */
const chipRow = archiveView.slice(
    archiveView.indexOf('master-list-chips'),
    archiveView.indexOf('master-filter-row'));

check('the archive filters by claim state and month, not by document type',
    chipRow.length > 0 && !/document_type/.test(chipRow)
    && !/name="document_type"[^>]*aria-label="Filter/.test(archiveView)
    && !/All Types<\/option>/.test(archiveView)
    && !/master-list-applied-key">Type</.test(archiveView),
    chipRow.replace(/\s+/g, ' ').slice(0, 120));

check('the type filter is gone from the query, not only from the markup',
    !/'documentType' =>/.test(controller) && !/where\('document_type'/.test(controller)
    && /private function filters\(Request \$request\): array/.test(controller)
    && /\$state = \(string\) \$request->query\('state', 'all'\)/.test(controller));

check('the paper trail still says what each document is',
    /\$document->documentTypeLabel\(\)/.test(archiveView)
    && /'document_type' => \['required', Rule::in\(array_keys\(CashflowAttachment::documentTypeOptions\(\)\)\)\]/.test(controller));
check('the archive can file a document of its own',
    /id="fileDocumentModal"/.test(archiveView)
    && /route\('cashflows\.attachments\.storeStandalone'\)/.test(archiveView)
    && /name="party_name"/.test(archiveView) && /name="currency"/.test(archiveView));

const cardView = read('resources/views/cashflows/partials/documents-card.blade.php');
check('an entry can claim a document that was filed before it existed',
    /route\('cashflows\.attachments\.link', \$entry\)/.test(cardView)
    && /name="attachment_id"/.test(cardView)
    && /unlinkedDocuments/.test(cardView));

const cashflowJsFile = read('public/assets/js/cashflows.js');
check('the file dialog is bound, not only drawn',
    /id="fileDocumentModal"/.test(archiveView) && /'fileDocumentModal'/.test(cashflowJsFile)
    && /openFileDocumentModal/.test(cashflowJsFile));

/* ------------------------------------------- the record page's card ----- */

/* The card is included as a row of the record page's own grid: inside it,
   spanning both columns. Left outside, it hangs under the columns with no
   gutter at all — the card glued to the one above it — and its edges line up
   with nothing on the page. The eight-space indent is the grid child's. */
const showView = read('resources/views/cashflows/show.blade.php');
const cardIncludeAt = showView.indexOf("@include('cashflows.partials.documents-card'");
check('the documents card is a row of the record page grid, not its neighbour',
    showView.indexOf('<div class="master-grid">') < cardIncludeAt
    && /^ {8}@include\('cashflows\.partials\.documents-card'/m.test(showView)
    && /\.cf-docs\s*\{\s*grid-column: 1 \/ -1;/.test(cashflowsCss));

check('the card takes the grid gutter and keeps no margin of its own',
    /\.cf-docs\s*\{[\s\S]{0,140}margin-bottom: 0;/.test(cashflowsCss));

/* Three fields, one row. The card is as wide as the page, so type, title and
   the file picker read as one line; the picker takes the whole row only when
   the width can no longer hold three columns. */
const uploadFields = cardView.slice(
    cardView.indexOf('cf-doc-upload-fields'),
    cardView.indexOf('cf-doc-upload-actions'));

check('the upload row carries exactly three fields',
    (uploadFields.match(/class="master-field"/g) || []).length === 3,
    (uploadFields.match(/class="master-field"/g) || []).length + ' fields');

check('the three fields share one row, and step down as the width drops',
    /\.cf-doc-upload-fields\s*\{\s*display: grid;\s*grid-template-columns: repeat\(3, minmax\(0, 1fr\)\);/
        .test(cashflowsCss)
    && /@media \(max-width: 1100px\)[\s\S]{0,240}\.cf-doc-upload-fields\s*\{\s*grid-template-columns: repeat\(2, minmax\(0, 1fr\)\);/
        .test(cashflowsCss)
    && /@media \(max-width: 768px\)[\s\S]{0,600}\.cf-doc-upload-fields\s*\{\s*grid-template-columns: 1fr;/
        .test(cashflowsCss));

check('the card badge is the shared badge in the document tone',
    /class="master-badge cf-doc-state .*is-missing/.test(cardView));

/* The record page's own badges use light-only colours in the detail sheet;
   without a dark half they stayed cream on a dark card. */
check('the record page badges carry a dark half',
    /:root\[data-theme="dark"\] \.cf \.master-badge\.status-pending/.test(cashflowsCss)
    && /:root\[data-theme="dark"\] \.cf \.master-badge\.status-reconciled/.test(cashflowsCss)
    && /:root\[data-theme="dark"\] \.cf \.master-badge\.type-debit/.test(cashflowsCss));

/* ------------------------------------ the trade's own paper, and the pack - */

/* A Bill of Entry and an E-way Bill are as ordinary in this business as a
   purchase bill. Filing them as "Other" is what made them impossible to find
   again, so the list carries them — and stays the one list: the two upload
   surfaces iterate it, the controller validates against it, the row prints it. */
const typeKeys = [...model.matchAll(/^\s{12}'([a-z_]+)' => '/gm)].map(m => m[1]);
check('the document types cover the trade, not just the bank',
    typeKeys.length >= 12
    && ['bill', 'boe', 'eway_bill', 'shipping_bill', 'packing_list', 'delivery_challan',
        'debit_note', 'credit_note', 'purchase_order', 'bank_slip', 'receipt', 'gst', 'other']
        .every(key => typeKeys.includes(key)),
    typeKeys.join(', '));
check('both upload surfaces offer that one list',
    /@foreach \(\$documentTypeOptions as \$key => \$label\)/.test(cardView)
    && /@foreach \(\$documentTypeOptions as \$key => \$label\)/.test(archiveView)
    && /Rule::in\(array_keys\(CashflowAttachment::documentTypeOptions\(\)\)\)/.test(controller));

/* The month, as one file somebody can mail. */
check('a filtered month can leave as one file for the accountant',
    /Route::get\('\/cashflows\/documents\/pack'/.test(routes)
    && /->name\('cashflows\.documents\.pack'\)/.test(routes)
    && routes.indexOf("Route::get('/cashflows/documents/pack'") < resourceAt
    && /public function pack\(Request \$request\)/.test(controller)
    && /class_exists\(\\ZipArchive::class\)/.test(controller));
check('the pack is the files, the folders and the index',
    /new \\ZipArchive\(\)/.test(controller)
    && /addFile\(\$disk->path\(\$document->file_path\), \$names\[\$document->id\]\)/.test(controller)
    && /addFromString\('index\.csv', \$index\)/.test(controller)
    && /deleteFileAfterSend\(true\)/.test(controller));
check('without the zip extension it is still the index, not an error',
    /if \(! class_exists\(\\ZipArchive::class\)\) \{[\s\S]{0,400}return response\(\$index, 200, \[/.test(controller)
    && /text\/csv; charset=UTF-8/.test(controller));
check('the index carries the columns an accountant asks for',
    /fputcsv\(\$stream, \[\s*'Date', 'Type', 'Party', 'Amount', 'Currency', 'Entry', 'Bill \/ reference',/.test(controller)
    && /'Filed on', 'Note',/.test(controller)
    && /"\\xEF\\xBB\\xBF"/.test(controller));
check('a file the server no longer holds is marked, not silently dropped',
    /isset\(\$missing\[\$document->id\]\) \? 'File missing on the server'/.test(controller)
    && /count\(\$missing\).' file\(s\) in this pack are no longer on the server/.test(controller));
check('the pack is named for its period and party',
    /private function packFilename\(array \$filters\): string/.test(controller)
    && /return Str::slug\(implode\(' ', \$parts\)\);/.test(controller));
check('a packed name survives every desktop it is opened on',
    /private static function safeSegment\(string \$segment\): string/.test(model)
    && /public function packName\(\): string/.test(model)
    /* the Windows-forbidden punctuation set is stripped, and the name capped */
    && /preg_replace\('\/\[[^\]]*:\*\?"<>\|[^\]]*\]\+\/', '-', \$segment\)/.test(model)
    && /mb_substr\(\$clean, 0, 120\)/.test(model));
check('two documents with one name both survive the pack',
    /private function uniqueName\(string \$name, array &\$used\): string/.test(controller));

/* "Send the accountant this party's papers for this month" is two filters and
   one button, so the party is a filter of its own — reading the same two names
   the row prints, and named in the applied strip like every other filter. */
check('the archive filters by party, on its own and in the URL',
    /<select class="master-select" name="party" aria-label="Filter by party">/.test(archiveView)
    && /'party' => trim\(\(string\) \$request->query\('party'\)\)/.test(controller)
    && /private function partyOptions\(\): array/.test(controller)
    && /'partyOptions' => \$this->partyOptions\(\),/.test(controller));
check('the party filter matches the two names the row prints',
    /party->where\('party_name', 'like', \$term\)/.test(controller)
    && /related_party_name', 'like', \$term/.test(controller)
    && /company_name', 'like', \$term/.test(controller)
    && /vendor_name', 'like', \$term/.test(controller));
check('the party is named in the applied strip, and clears on its own',
    /master-list-applied-key">Party</.test(archiveView)
    && /\$chipUrl\('party'\)/.test(archiveView));
check('the pack button submits the filters above it',
    /formaction="\{\{ route\('cashflows\.documents\.pack'\) \}\}"/.test(archiveView));

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
    standaloneMigration,
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
