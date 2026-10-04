/* ==========================================================================
   STATEMENT CHECK — the account as a document, and the link it travels on
   --------------------------------------------------------------------------
   Run:  node tools/checks/statement-check.cjs
   No dependencies. Exits non-zero on failure.

   A statement is money leaving the building with a customer's name on it, so
   the things that must stay true are the things that make it *right*:

     - a statement is per party **and currency**: a vendor billed in RMB and
       paid in rupees holds two balances, and adding them is wrong by the
       exchange rate;
     - the opening balance is everything before the period, the running balance
       is that plus the period's movement, and the sign is decided once, by
       party type — a client's balance rises with an invoice, a vendor's with a
       bill;
     - the party id column is the one its type owns. A client with id 5 and a
       vendor with id 5 are two parties; matching either column puts one's
       money on the other's statement;
     - the document is written once. Screen, PDF, public link and portal render
       the same partial, so a figure cannot look different depending on who is
       reading it — and the office's own vocabulary (a row's booking status)
       stays on the office side;
     - the link is the only public thing: long token, expires, revocable, every
       open counted, and the statement itself never stored — it is rebuilt from
       the ledger, so it cannot go stale;
     - this repository has no PHP runtime here, so the new PHP is at least
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

/* A comment or a docblock is wrapped to the width of the file, so a guard that
   looks for a sentence must read it flat — a needle that only matches one
   line-break position passes or fails by accident. */
const flat = text => text.replace(/\s+/g, ' ').trim();

/* The same, for a docblock: each line starts with the comment's own star, so
   the stars are dropped before the wrapping is flattened away. */
const prose = text => flat(text.replace(/^\s*\*\s?/gm, ' '));

/* ------------------------------------------------------------- the record */

const migration = walk(path.join(ROOT, 'database/migrations'))
    .map(rel).find(f => /create_party_statement_shares_table/.test(f));
check('the share log has its own migration', !!migration, migration || 'not found');

const migrationText = migration ? read(migration) : '';
check('a link is a long unique token', /string\('token', 80\)->unique\(\)/.test(migrationText));
check('a link can expire and be revoked',
    /dateTime\('expires_at'\)->nullable\(\)/.test(migrationText)
    && /dateTime\('revoked_at'\)->nullable\(\)/.test(migrationText));
check('every open is recorded',
    /unsignedInteger\('views'\)/.test(migrationText)
    && /dateTime\('first_viewed_at'\)/.test(migrationText)
    && /dateTime\('last_viewed_at'\)/.test(migrationText));
check('the log keeps the party name it was sent to',
    /string\('party_name'\)/.test(migrationText)
    && /copied string is the more truthful record/.test(migrationText));
check('the statement it points at is a period and a currency, not a copy of the numbers',
    /date\('date_from'\)->nullable\(\)/.test(migrationText)
    && /string\('party_currency', 10\)/.test(migrationText)
    && ! /decimal\(/.test(migrationText));

/* ---------------------------------------------------------------- the model */

const model = read('app/Models/PartyStatementShare.php');
check('one place decides what a party type is',
    /const PARTY_TYPES = \[/.test(model) && /'vendor' => 'Vendor'/.test(model));
check('a link is live only while it is neither revoked nor expired',
    /revoked_at === null/.test(model) && /expires_at->isFuture\(\)/.test(model));
check('the state has three words, not two',
    /'revoked'/.test(model) && /'expired'/.test(model) && /return 'live'/.test(model));
/* the label map contains the word 'expired' even when the branch that decides
   it is gone, so the branch is what gets read */
check('an expired link is decided by the clock, not by the label',
    /if \(\$this->expires_at !== null && \$this->expires_at->isPast\(\)\) \{\s*return 'expired';/.test(model));
check('a token collision retries instead of erroring',
    /do \{[\s\S]{0,80}Str::random\(48\)[\s\S]{0,80}\} while \(static::where\('token', \$token\)->exists\(\)\)/.test(model));
check('a ten-digit number is an Indian mobile and gets its country code',
    /strlen\(\$digits\) === 10/.test(model) && /'91'\.\$digits/.test(model));
check('an email link only exists for a valid address',
    /FILTER_VALIDATE_EMAIL/.test(model));
check('the link is the public route, spelled once',
    /route\('statements\.public', \$this->token\)/.test(model));

/* -------------------------------------------------------------- the engine */

const service = read('app/Services/PartyStatement.php');
check('the engine has its own home', exists('app/Services/PartyStatement.php'));
check('the five ageing buckets are the ones an accountant reads',
    /'current' => 'Not due'/.test(service) && /'d1_30' => '1–30 days'/.test(service)
    && /'d31_60' => '31–60 days'/.test(service) && /'d61_90' => '61–90 days'/.test(service)
    && /'d90_plus' => '90\+ days'/.test(service));
check('the bucket function actually returns the last bucket',
    /return 'd90_plus';/.test(service) && /return 'd1_30';/.test(service));

check('a statement is built for one currency, not for a sum of them',
    /rows in it carry the running balance, rows in any other currency are reported separately/.test(prose(service))
    && /strtoupper\(\(string\) \(\$options\['currency'\]/.test(service));
check('the opening balance is everything before the period',
    /private function splitOpening/.test(service) && /if \(\$date < \$from\)/.test(service));
check('the running balance starts from the opening balance',
    /\$running = \$opening;/.test(service) && /\$running \+= \$sign \* \(\$row\['debit'\] - \$row\['credit'\]\)/.test(service));
check('the sign is decided once, by party type',
    /\$sign = \$type === 'vendor' \? -1 : 1;/.test(service));
check('a client statement pairs invoices with receipts',
    /if \(Schema::hasTable\('sales_invoices'\)\) \{\s*\$invoices = SalesInvoice::query\(\)/.test(service)
    && /'particular' => \$credit > 0 \? 'Receipt' : 'Refund/.test(service));
check('a vendor statement is the vendor currency ledger',
    /VendorPaymentEntry::query\(\)[\s\S]{0,200}where\('vendor_id'/.test(service)
    && /vendor currency ledger: bills raised and payments made/.test(service));
check('a vendor with only cashflow rows still gets a statement, and it says so',
    /The fallback for a vendor whose money was only ever filed/.test(prose(service))
    && /no vendor-currency rows filed yet/.test(service)
    /* and the fallback is a live branch, not a comment about one */
    && /if \(\$rows === \[\] && Schema::hasTable\('cashflow_entries'\)\) \{/.test(service));
check('a party is matched on its own id column — never both',
    /where\(\$type === 'vendor' \? 'vendor_id' : 'client_id', \$partyId\)/.test(service)
    && /one's money on the other's statement/.test(prose(service)));
check('a client is aged on the invoice balance, from its due date',
    /\(float\) \$invoice->balance_amount/.test(service) && /due_date \?: \$invoice->invoice_date/.test(service));
check('a vendor is aged from the bill date, and the statement says so',
    /Aged from the bill date/.test(prose(service)) && /in_array\(\$entry->status, \['pending', 'booked'\]/.test(service));
check('a bill that was paid is not outstanding',
    /\$entry->transaction_type === 'debit' \|\| ! in_array/.test(service));
check('other currencies are reported, never added in',
    /'other_currencies' => \$this->otherCurrencies\(\$type, \$party, \$currency, \$from, \$to\)/.test(service)
    && /each currency is its own statement/.test(read('resources/views/cashflows/partials/statement.blade.php')));

/* ------------------------------------------------------------- the routes */

const routes = read('routes/web.php');
check('the public statement link is outside auth, like every other public link',
    /Route::get\('\/statement\/\{token\}', \[PartyStatementController::class, 'publicShow'\]\)[\s\S]{0,120}->name\('statements\.public'\)/.test(routes));
check('the public token is constrained to what a token is',
    /->where\('token', '\[A-Za-z0-9\]\{20,80\}'\)/.test(routes));

const statementsRouteAt = routes.indexOf("name('cashflows.statements')");
const resourceRouteAt = routes.indexOf("Route::resource('cashflows'");
check('the surface is registered before the resource route',
    statementsRouteAt > 0 && resourceRouteAt > statementsRouteAt,
    statementsRouteAt === -1 ? 'surface route missing' : 'order');
check('the PDF route cannot be read as a party page',
    /Route::get\('\/cashflows\/statements\/pdf'/.test(routes));
check('the party page is constrained to the two ledgers there are',
    /whereIn\('partyType', \['client', 'vendor'\]\)/.test(routes));
check('a link is revoked with PATCH and forgotten with DELETE',
    /->name\('cashflows\.statements\.shares\.revoke'\)/.test(routes)
    && /@method\('PATCH'\)/.test(read('resources/views/cashflows/statements.blade.php')));
check('the client portal shows their own statement without a link',
    /Route::get\('\/statement', \[ClientPortalStatementController::class, 'index'\]\)->name\('statement\.index'\)/.test(routes));

/* --------------------------------------------------------------- the views */

const surface = read('resources/views/cashflows/statements.blade.php');
const preview = read('resources/views/cashflows/statement-show.blade.php');
const publicPage = read('resources/views/statements/public.blade.php');
const expired = read('resources/views/statements/expired.blade.php');
const portalPage = read('resources/views/client_portal/statements/index.blade.php');
const partialPath = 'resources/views/cashflows/partials/statement.blade.php';
const partial = read(partialPath);
const pdfView = read('resources/views/cashflows/statement-pdf.blade.php');

check('the document is written once and rendered everywhere',
    ["'cashflows.partials.statement'", '"cashflows.partials.statement"'].some(needle =>
        preview.includes(needle) && publicPage.includes(needle) && portalPage.includes(needle) && pdfView.includes(needle)),
    'preview / public / portal / pdf must all include the partial');
check('no second copy of the statement markup exists',
    ! /stmt-table/.test(surface) && ! /stmt-table/.test(preview) && ! /stmt-table/.test(publicPage) && ! /stmt-table/.test(portalPage),
    'the table belongs to the partial alone');
/* The word 'app' survives in the condition even when the condition stops
   coming from the caller, so what is read is the assignment and the number of
   places the condition actually gates something. */
check('the office vocabulary stays on the office side',
    /\$ctx = \$context \?\? 'app';/.test(partial)
    && /internal status of a row/.test(prose(partial))
    && (partial.match(/\@if \(\$ctx === 'app'\)/g) || []).length >= 2,
    'row statuses are printed only in the app, and the condition comes from the caller');
check('the expiry is explained, not blanked',
    /This statement link has expired/.test(expired) && /was withdrawn/.test(expired) && /no longer available/.test(expired));
check('an expired or revoked link is not a mystery 404',
    /response\(\)->view\('statements\.expired'[\s\S]{0,80}410\)/.test(read('app/Http/Controllers/PartyStatementController.php')));

/* ---------------------------------------------------------- link hygiene */

const controller = read('app/Http/Controllers/PartyStatementController.php');
check('"all parties" is both lists, merged',
    /\$types = \$filters\['partyType'\] === 'all' \? \['client', 'vendor'\] : \[\$filters\['partyType'\]\];/.test(controller));

check('the party reading it is counted, the office checking it is not',
    /if \(! Auth::check\(\)\) \{/.test(controller));
check('the statement is rebuilt on every open, never served from the row',
    /statement is rebuilt from the ledger on every open/.test(prose(model))
    && /build\(\s*\$share->party_type/.test(controller));
/* the PDF action carries the same line, so the page's own guard is read from
   the page's own method — a guard satisfied by a sibling is not a guard */
const showMethod = controller.slice(controller.indexOf('public function show('), controller.indexOf('public function pdf('));
check('a statement is never built for a party that no longer exists',
    /abort_if\(\$statement === null, 404\)/.test(showMethod) && /abort_if\(\$statement === null, 404\)/.test(controller));
check('the PDF degrades to a printable page when dompdf is absent',
    /class_exists\(\\Barryvdh\\DomPDF\\Facade\\Pdf::class\)/.test(controller)
    && /Use the print dialog and choose Save as PDF to download this statement\./.test(controller)
    && /\$pdfFallbackMessage/.test(pdfView));
check('the period survives the jump from the list to the statement',
    /'period' => \$periodKey/.test(surface) && /array_filter\(\['partyType'/.test(surface));
check('the period survives issuing a link',
    /'period' => \$data\['period'\] \?\? null,/.test(controller)
    && /name="period" value="\{\{ \$periodKey \}\}"/.test(preview));
check('the note belongs to the link, printed on the statement',
    /'note'/.test(controller) && /Note printed on the statement/.test(preview));

/* ------------------------------------------------------- money and design */

const helper = read('app/Helpers/CommonHelper.php');
check('one place turns a currency code into what a person reads',
    /public static function currencyLabel\(/.test(helper)
    && /return \$code === 'INR' \? self::SYMBOL : \$code;/.test(helper));
check('the statement never prints the letters INR where a rupee sign belongs',
    /\$currencyLabel = \\App\\Helpers\\CommonHelper::currencyLabel\(\$currency\);/.test(partial)
    && ! />INR</.test(surface + preview + partial + publicPage + portalPage + expired)
    && ! /\{\{ \$currency \}\}/.test(partial + surface),
    'rupees are the sign; other currencies keep their code, from one helper');
/* The send log printed a lone ₹ under every period on a single-currency party:
   a symbol that tells nothing apart is a line of noise in a table. The currency
   earns its line only when the party's links can differ by one. */
check('the send log names the currency only when it tells a link apart',
    /@if \(count\(\$currencyOptions\) > 1\)[\s\S]{0,400}?currencyLabel\(\$share->party_currency\)/.test(preview));

/* The toolbar used to carry its own margin-bottom as well as the shell's rule —
   a page spacing itself, which is how a 16px gap and a 24px gap end up in the
   same column. The rhythm between two cards belongs to the shell. */
check('the statement page does not space its own blocks',
    ! /\.stmt-toolbar \{[^}]*margin-bottom/.test(read('public/assets/css/statement.css')));

check('every figure goes through the money formatter',
    /CommonHelper::amount\(/.test(partial)
    && /CommonHelper::indianCurrency\(\$row\['inr'\]\)/.test(partial),
    'no raw float reaches the page');
check('the surface is the module\'s own sheet plus the shared chrome',
    /assets\/css\/statement\.css/.test(preview + pdfView + publicPage + expired + portalPage)
    && /assets\/css\/cashflows\.css/.test(surface)
    /* the chrome belongs to the shell: loaded once in the layout, after the
       module sheets a page pushes, and never pushed by a page itself */
    && /@stack\('styles'\)[\s\S]{0,800}?assets\/css\/master-list\.css/.test(read('resources/views/layouts/app.blade.php'))
    && ! /assets\/css\/master-list\.css/.test(surface));
/* A statement range arrives as a query string and can name a range rather than
   a day ("all", "custom"); it is read through DateRanges::normalise — never
   handed to Carbon raw — on the office screen, in the portal and in the service
   that builds the document. */
check('a statement range is read as a date or as nothing',
    /DateRanges::normalise\(\$request->query\('date_from'\)\)/.test(read('app/Http/Controllers/PartyStatementController.php'))
    && /DateRanges::normalise\(\$request->query\('date_from'\)\)/.test(read('app/Http/Controllers/ClientPortalStatementController.php'))
    && /DateRanges::normalise\(\$from\)/.test(read('app/Services/PartyStatement.php'))
    && ! /Carbon::parse\(\s*\$request/.test(read('app/Http/Controllers/PartyStatementController.php'))
    && ! /Carbon::parse\(\s*\$request/.test(read('app/Http/Controllers/ClientPortalStatementController.php')));

/* The run and its send log are two cards of one page: both sit directly on the
   list root (so the shell's own rhythm separates them) and neither carries its
   own spacing — a module that spaces its own page is a second owner. */
check('the run and its send log are two blocks of the page, not one table',
    (surface.match(/^    <div class="master-card/gm) || []).length === 2
    && ! /\.master-card \+ \.master-card/.test(read('public/assets/css/statement.css')));

check('the module sheet leaves the list chrome to the surface',
    ! /\.master-list\b/.test(read('public/assets/css/statement.css').replace(/\/\*[\s\S]*?\*\//g, '')));

const statementCss = read('public/assets/css/statement.css');
/* The surface prints dates through the module's own reader rather than
   reaching for Carbon in the template — no view in this module parses a date. */
check('the surface prints a date, it never parses one',
    ! /Carbon::parse\(/.test(surface)
    && /DateRanges::display\(\$row\['last_date'\], '—'\)/.test(surface));

check('the document has a dark theme of its own',
    /:root\[data-theme="dark"\] \.stmt \{/.test(statementCss));
check('a public reader follows their own theme, and only there',
    /@media \(prefers-color-scheme: dark\) \{[\s\S]{0,200}body\.stmt-standalone/.test(statementCss)
    && ! /prefers-color-scheme[\s\S]{0,80}\.cashflow-statement/.test(statementCss));
check('the unavailable-link notice stays light with guideline surfaces',
    /body\.stmt-centered\s*\{[^}]*color-scheme:\s*light[^}]*background:\s*var\(--pdf-page-bg/.test(statementCss)
    && /\.stmt-expired\s*\{[^}]*background:\s*var\(--pdf-paper/.test(statementCss)
    && /\.stmt-expired-facts\s*\{[^}]*background:\s*var\(--pdf-surface-soft/.test(statementCss)
    && /body\.stmt-standalone:not\(\.stmt-print\):not\(\.stmt-centered\)/.test(statementCss)
    && ! /body\.stmt-standalone:not\(\.stmt-print\) \.stmt-expired/.test(statementCss));
check('the document prints without the app around it',
    /@media print \{/.test(statementCss) && /@page \{/.test(statementCss)
    && /\.no-print,[\s\S]{0,200}display: none !important;/.test(statementCss));
check('money columns line up down the page',
    /font-variant-numeric: tabular-nums/.test(statementCss) && /\.stmt-num \{[\s\S]{0,80}text-align: right/.test(statementCss));

check('the share dialog has a trigger and an owner',
    /id="openShareStatement"/.test(preview)
    && /class="master-modal" id="shareStatementModal"/.test(preview)
    && /\['openShareStatement', 'emptyShareStatement'\]/.test(read('public/assets/js/cashflows.js'))
    && /getElementById\('shareStatementModal'\)/.test(read('public/assets/js/cashflows.js')));

check('the nav reaches the new surface',
    /'label' => 'Statements'/.test(read('resources/views/layouts/app.blade.php'))
    && /cashflows\.statements/.test(read('resources/views/layouts/app.blade.php')));
check('the ledger item no longer lights up while a statement is open',
    /'except' => \['cashflows\.documents', 'cashflows\.statements', 'cashflows\.statements\.\*'\]/.test(read('resources/views/layouts/app.blade.php')));
check('a party page offers its own statement',
    /partyType' => 'client', 'party' => \$client->id/.test(read('resources/views/clients/show.blade.php'))
    && /partyType' => 'vendor', 'party' => \$vendor->id/.test(read('resources/views/vendors/show.blade.php')));

/* ------------------------------------------------------------------- PHP */

const balance = (text) => {
    let depth = 0, min = 0, inString = null, escape = false, inComment = false, inLine = false;
    for (let i = 0; i < text.length; i++) {
        const c = text[i], n = text[i + 1];
        if (inLine) { if (c === '\n') inLine = false; continue; }
        if (inComment) { if (c === '*' && n === '/') { inComment = false; i++; } continue; }
        if (inString) {
            if (escape) { escape = false; continue; }
            if (c === '\\') { escape = true; continue; }
            if (c === inString) inString = null;
            continue;
        }
        if (c === '/' && n === '/') { inLine = true; i++; continue; }
        if (c === '#') { inLine = true; continue; }
        if (c === '/' && n === '*') { inComment = true; i++; continue; }
        if (c === '"' || c === "'") { inString = c; continue; }
        if (c === '{') { depth++; } else if (c === '}') { depth--; min = Math.min(min, depth); }
    }
    return { depth, min };
};

const phpFiles = [
    migration,
    'app/Models/PartyStatementShare.php',
    'app/Services/PartyStatement.php',
    'app/Http/Controllers/PartyStatementController.php',
    'app/Http/Controllers/ClientPortalStatementController.php',
    'routes/web.php',
].filter(Boolean);

const phpHazards = phpFiles.filter(file => {
    const { depth, min } = balance(read(file));
    return depth !== 0 || min < 0;
});
check('the new PHP is bracket-balanced (no interpreter here to ask)',
    phpFiles.length >= 6 && phpHazards.length === 0, phpHazards.join(', '));
check('every new PHP file opens with a php tag and closes with none',
    phpFiles.every(file => /^<\?php/.test(read(file)) && ! /\?>\s*$/.test(read(file))));
check('the portal controller answers to the portal base',
    /class ClientPortalStatementController extends ClientPortalBaseController/.test(read('app/Http/Controllers/ClientPortalStatementController.php')));
check('a statement can never be built for another client in the portal',
    /build\('client', \$client->id/.test(read('app/Http/Controllers/ClientPortalStatementController.php')));

/* ---------------------------------------------------------------- report */

const failed = out.filter(([, ok]) => !ok);
out.forEach(([name, ok, detail]) =>
    console.log((ok ? '  ok   ' : '  FAIL ') + name + (ok || !detail ? '' : '  -> ' + detail)));
console.log('\nstatement: ' + (out.length - failed.length) + ' passed, ' + failed.length + ' failed');
process.exit(failed.length ? 1 : 0);
