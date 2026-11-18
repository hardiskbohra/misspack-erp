# Statement print field selection

## Acceptance — REQ-STATEMENT / IT-STATEMENT-010

- The office print preview for client and vendor statements lets the operator select transaction date, description, reference, debit, credit and running balance. Vendor project names can be selected independently of the description; if only project names are selected, the transaction table has a Project heading. Issuer and party names, document identity and reporting period remain visible.
- Optional printed sections are account summary, ageing, bank details, other-currency notice, statement notes, logo, issuer details and party details. The existing ageing switch is retained. Date and reference choices apply in the ageing table as well as the transaction table. The controls explain that summary and ageing are independent and that visibility is not a redaction mechanism.
- A fresh preview defaults to the existing complete document. Changes take effect on Apply and are reproducible in the preview URL and its Download/PDF URL, including dates, currency, and ageing. Reloading or changing reporting filters retains the selection. This is a per-document URL, not a global/browser-local table preference; it neither changes saved data nor existing office, client-portal or guest-share views. Existing authorization remains in force.
- The selected fields are rendered by the server in both the print preview and the PDF response, not merely hidden with browser CSS. All unchecked is valid and omits the transaction table, not a malformed zero-column table. Every other column combination has correct table header, body, opening, closing and empty-state geometry. If no running-balance column is selected, don't print a valueless opening row; debit/credit-only totals are labelled Period totals. If no monetary columns are selected, omit opening/closing financial rows. Print a disclosure when transaction fields are omitted; all balances and totals are still computed from the full statement.
- Project names, descriptions and references stay escaped. A project-only view is supported even if description is disabled; a client does not acquire vendor project data. Unknown or malformed query choices must not cause an exception or inject text. Omission of a field is presentation only, not a promise to redact the same value from free-text fields, independent sections or underlying data.
- No SQL/schema/ledger mutation, rounding, ordering, currency conversion, date-filter, share tracking, delivery or financial-calculation change. The selection defaults and independent ageing semantics are covered for vendor and client statements, no-project/empty statements, negative and foreign-currency totals, and the browser-print and optional server-PDF paths.
- Use the shared drawer/forms, accessible labels, keyboard navigation, responsive touch targets and A4 document styles. Check the actual Apply/reload, date filters, Download, wide and mobile layouts, multiple-page output and printable margins in real Chromium with fictional, disposable fixtures.

## Verification (2026-11-01)

- Test-first: after applying the project-token removal migration to the reduced fixture, the new HTTP tests failed for the intended missing field controls, unchanged table, missing ageing selection and missing server-PDF adapter state: **7 failures / 25 assertions** before production changes. The project-only visual test first found the text column had just 47% of the page; a later mobile test found ordinary amounts wrapped at 390px. Both were corrected and rechecked. A further HTTP Red established that the print stylesheet needed a cache-busted URL.
- Focused statement, project and converted-PO regression suite: **41 tests / 1,600 assertions PASS** (00:21.682). It includes all 64 client transaction-column combinations, project-only vendor rows, zero selected columns, ageing geometry, foreign/negative balances, escaping, unchanged totals and ledger rows, preview/PDF agreement, permission boundaries, app/share non-regression and a separate-process fake of the optional PDF facade.
- Full portable Unit/Integration/Feature suite: `APP_ENV=testing APP_RUNNING_IN_CONSOLE=true TEST_PHP_BINARY=… php vendor/bin/phpunit -c phpunit.xml --no-progress --display-phpunit-notices` — **751 tests / 20,910 assertions PASS** (02:38.173, PHP 8.4.26), with the pre-existing `SalesInvoiceEmailTest` mock-without-expectations notice.
- Strict disposable MySQL 8.0.14: after a fresh test-only schema, `php vendor/bin/phpunit -c phpunit.mysql.xml --no-progress` — **41 tests / 510 assertions PASS** (00:09.219). These are existing native financial/statement regressions, not a new native print-field test; no SQL/schema/locking code changed in this task.
- `npm run test:contracts` — PASS (32 domains, 317 designed cases, 164 method-linked cases, 91 executable files inventoried; not runtime coverage). Route inventory — **489 routes**. Statement source checks **75/75**, shared UI **24/24**, PDF **20/20**, PHP/JS syntax and `git diff --check` — PASS. The statement source guard was adjusted for the existing batched cashflow collection and for the app-only status flag, without removing either check. The first contract run found a block-form Blade formatting issue; it passed after the directive was formatted on separate lines.
- Real Chromium 153 on a guarded disposable SQLite app: **1440px, 390px and 320px PASS** for default preview, the shared accessible drawer, touch-sized labels, Escape/cancel, selected fields, retained URL state, period edits, Show all and browser-print fallback. A project-only table kept names and normal-size balances visible at 390/320px. Existing vendor office/print and guest-share regressions passed at their original widths; default client/vendor print regressions passed.
- PyMuPDF independently inspected a **one-page vendor project-only PDF** and a **three-page 65-invoice client PDF**: selected and repeated headings, complete names/rows, one closing total, full amounts and A4 margins passed. Existing default all-time, custom-date, vendor and 73-row/three-page client PDFs also passed. Artifacts and fictional databases remain ignored under `.work/`.
- The actual DomPDF package is not installed locally: its controller branch was tested with an isolated facade double, not a server-side PDF renderer. No physical phone or printer was tested. This change does not deploy or alter live data.

## Reproduce (disposable data only)

```sh
APP_ENV=testing APP_RUNNING_IN_CONSOLE=true TEST_PHP_BINARY=/path/to/php php vendor/bin/phpunit -c phpunit.xml
npm run test:contracts
APP_ENV=testing php tools/testing/export-routes.php --check
# Against a freshly migrated SQLite database under .work, with a fictional test password:
APP_ENV=testing php tools/testing/seed-vendor-statement-projects-browser.php
APP_ENV=testing php tools/testing/seed-statement-print-fields-browser.php
# Start the guarded tools/testing/cashflow-date-groups-router.php server on loopback.
CHROMIUM_EXECUTABLE_PATH=/path/to/chromium node tools/checks/statement-print-fields-browser.cjs
python3 tools/checks/statement-print-fields-pdf.py  # requires PyMuPDF
```
