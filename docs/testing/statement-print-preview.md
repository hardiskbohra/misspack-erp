# Statement print preview (25 October 2026)

Acceptance (REQ-STATEMENT / REQ-UI):
- Statement page has one Print link, opening an authenticated preview in a new
  tab, and no separate Download PDF action. Share remains unchanged.
- Preview has Download and Filters. Its editable period, exact dates, currency
  and ageing selection also drive the download. Explicit All time must survive
  even if stale month dates were in the original URL. Ageing off stays off.
- Keep one ledger calculator/statement partial. No arithmetic or posting changes.
- Printed header has a top logo, issuer details left, statement heading and
  client/vendor identity/contact/date criteria right. Issuer address in three
  compact lines, website on its own line, no issuer PAN.
- Account summary uses sufficient full-width space and never clips amounts.
  Test a real A4 PDF, long party names, large currency totals and pagination.
- Controls are print-hidden. Existing public/portal links remain private to their
  existing identity/token boundaries. New route is office/finance protected.

## Executed TDD and gates

- Initial Red:5 intended failures/6 assertions (old Print button; missing print
  route). Follow-up address/date Red:2 missing-helper errors and1 missing exact
  date assertion. Green after implementation:8 focused tests/71 assertions.
- The initial monthly-default characterization wrongly expected the closing
  balance to exclude pre-period activity. Corrected the test to assert debit100,
  opening200696 and a1Oct start. Production balance arithmetic was not changed.
- Full portable PHP8.4.25/WASM: **491 tests/15,376 assertions**, including Unit,
  Integration and Feature. This is real execution, not a native PHP/MySQL claim.
- Real Chromium153,1440px app plus1200/390px preview: Print popup leaves the
  original page intact; Download requests browser printing without replacing the
  preview; editable dates select Custom and apply; currency/ageing retained;
  Filters keyboard Escape/focus handling, no page-wide overflow or JS exceptions.
- Actual Chromium A4 PDFs, inspected with PyMuPDF: one page each for all-time INR,
  exact2025 dates and RMB vendor. Default company address is exactly three visual
  lines; embedded logo; aligned issuer/party headers; PAN and controls absent;
  complete amounts including **₹2,90,280 / ₹89,584 / ₹2,00,696**. A73-row, long-name,
  twelve-crore case fits3pages with repeating table headings, all70 extra invoice
  references once, final closing total once, and every word within12mm margins.
- The old UI source guard required the removed standalone PDF action. Updated
  it to require Print/new-tab on the statement page and filtered Download plus
  the shared drawer in the preview, retaining embedded-client and document-pack
  checks. Statements75, shared PDF20, design79 and UI24 source checks; default catalogue,
  period/dashboard/Blade contracts PASS. Catalogue221 designs (96unit/125integration),
  66method-linked designs,50test files and455declared routes. Not coverage figures.
- No SQL/schema/posting changes. New GET route retains office and finance access;
  public shares and portal routes remain untouched. Native PHP/MySQL CI is an
  independent post-push gate; evidence will be reported with the commit.

## Download behaviour

The locked project has no optional Dompdf package. As with existing print
endpoints, Download therefore opens the browser print dialog; choose **Save as
PDF** (or a printer). The preview stays open with its Filters. Its notice states
this explicitly and is not printed. If Dompdf is installed, the same canonical
URL uses the existing server PDF response instead. Server Dompdf rendering was
not executed locally; real browser-generated PDFs were verified as above.

## Reproduce (disposable testing data only)

```sh
APP_ENV=testing php tools/testing/render-statement-print.php .work/statement-print
CHROMIUM_EXECUTABLE_PATH=/path/to/chromium node tools/checks/statement-print-browser.cjs .work/statement-print
python3 tools/checks/statement-print-pdf.py .work/statement-print  # requires PyMuPDF
vendor/bin/phpunit -c phpunit.xml
npm run test:contracts
APP_ENV=testing php tools/testing/export-routes.php --check
```

Sandbox equivalent uses `/tmp/erp-test-tools/node_modules/.bin/php-wasm-cli`
with`PHP=8.4`, Playwright via`NODE_PATH=/tmp/erp-test-tools/node_modules`, Chromium
`/tmp/chromium` with`LD_LIBRARY_PATH=/tmp/al2023/lib`, and PyMuPDF via
`PYTHONPATH=/tmp/erp-pdf-tools`. Raw HTML/PDF/PNG/JUnit/logs stay ignored in`.work/`.
