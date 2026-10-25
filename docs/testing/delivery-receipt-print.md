# Delivery receipt: receiver-facing printout

## Acceptance criteria (25 October 2026)

- **UT-SHIP-004:** Letterhead follows the shipment's shipper using the existing own-organisation sender policy, not the consignee, recorder, app name or cost payer. Empty/third-party shippers do not receive our logo. Use the configured print logo and preserve the shipment's origin address; only an own-company shipment without an origin address may fall back to the organisation's shipping/billing address.
- **IT-SHIP-006:** The shared receipt contains shipment/receipt identity, delivery quantities, discrepancies, recipient and delivery date. It contains no record/evidence hashes, internal recorder identity or evidence filenames. Rendering must not change persisted integrity/evidence data.
- **IT-SHIP-007:** The A4 receipt has a receiver acknowledgement, handwriting space for receiver signature, name/designation, date/time and company stamp. A discrepant receipt must not assert unconditional receipt in good condition. Escape all supplied names, addresses, reasons and notes.
- **IT-SHIP-008:** Existing authentication, operations permission and receipt-to-shipment ownership checks remain enforced. Sharing means printing/saving the document as PDF, not introducing a public access link or online signature endpoint.

## TDD execution

Write unit, view and HTTP regression tests before production changes; observe the old printout fail its receiver-facing assertions. Record actual verification results here after implementation. No schema, receipt creation, quantity reconciliation, evidence-storage or integrity-hash changes are intended.

### Observed Red → Green

- Before production edits: `DeliveryReceiptPrintViewTest` ran **5 tests, 4 intended failures** (printed audit metadata, missing letterhead, vendor app-name heading, missing sign/stamp block); the existing escaping assertion passed. `DeliveryReceiptPrintTest` ran **4 tests, 2 intended failures** on missing stamp/signature fields; ownership and permission checks already passed. The five new issuer-unit tests initially errored because the new resolver class did not exist yet.
- After implementation: focused unit/view/HTTP cases passed. Added a further party-name/address escaping regression during refactoring.
- Actual Chromium PDFs: own-company, vendor and discrepancy fixtures each fit **one A4 portrait page**; the **60-line** fixture spans **seven A4 pages**, with repeated table headers, all 60 lines present, and the acknowledgement/signature/stamp block kept together. Browser checks inspect the rendered logo, printable writing-space dimensions and hidden toolbar; PyMuPDF checks real paper dimensions, text boundaries and printed content. PDF pages were rasterized for visual review.
- The long-document browser test first exposed the existing whole-table `break-inside: avoid` pushing all goods off page one. A regression assertion reproduced that failure; a receipt-scoped pagination override fixed it without changing challans or summaries.
- Shared PDF document contracts: **20 passed**. Existing project-workflow contracts: **115 passed**.

### Reproduce browser/PDF verification

With normal locked Composer dependencies, `playwright-core`, Chromium and Python `pymupdf` installed (temporary external installs are acceptable):

```bash
APP_ENV=testing php tools/testing/render-delivery-receipts.php .work/receipts
CHROMIUM_EXECUTABLE_PATH=/path/to/chromium \
  node tools/checks/delivery-receipt-print-browser.cjs .work/receipts
```

`NODE_PATH` and `PYTHONPATH` may point to temporary tool installations. Local execution used PHP 8.4 WASM for the PHP command, Chromium 153 supplied by a temporary npm package, and PyMuPDF; no live app, real shipment data, network fonts or external delivery services were used. Generated HTML, screenshots and PDFs stay in ignored `.work/`, not Git. This validates browser Print / Save as PDF, not the optional, uninstalled DomPDF package.

### Complete pre-submission gate

- Complete `phpunit.xml` (Unit + Integration + Feature): **443 tests, 15,093 assertions PASS**, PHP8.4.25 WASM; JUnit independently verified with no failures, errors or skips. The16 added expanded cases are included, not a separate total.
- `npm run test:contracts`: PASS (catalogue freshness and negative self-checks, dashboard controls/module contracts,244-template Blade audit).
- `node tools/checks/pdf-documents-check.cjs`:20 passed; `node tools/checks/project-workflow-check.cjs`:115 passed.
- `delivery-receipt-print-browser.cjs`: all four real-Blade/PDF fixtures passed. The full browser commands above are separate from the portable PHP suite.
- A Gujarati-address regression also reproduced and fixed ASCII-only address folding before submission; supplied non-Latin city/state/country details are retained.
- No schema, SQL or receipt-storage changes; a new local native MySQL run was not required/performed. The branch's native CI result is checked separately after pushing; earlier CI does not establish this change's result.
