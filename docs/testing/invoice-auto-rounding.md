# Automatic rounding for new INR invoices

## Accepted behaviour (IT-SALES-032 to IT-SALES-035)

- Newly created **INR proforma and tax invoices** default to rounding the final total to the nearest whole rupee, after discounts, GST and charges. Half rupees round upward: 100.49 → 100, 100.50 → 101. Save the signed difference separately as `round_off`, so the subtotal, GST, total, balance, printed invoice and e-invoice payload reconcile.
- Staff may turn off automatic rounding and enter a manual adjustment. When auto is enabled, the server computes it independently of the submitted preview; an eligible draft recomputes it after changes. The live preview and saved amount must agree.
- Existing invoices remain unchanged and existing manual round-off is not automatically changed on edit. A schema flag defaults off for existing records. Cash, USD and RMB totals are not automatically rounded.
- Converting a proforma into a tax invoice preserves the exact agreed outstanding claim and transferred receipts, including a fractional remainder after cash conversions; it is not re-rounded. Since the three-decimal precision change, the conversion is refused if the claim cannot be expressed in paise or if a partially converted line cannot be expressed using its original quantity and a three-decimal rate (see [three-decimal precision](three-decimal-pricing-and-cashflow.md)). Duplicating an invoice is a new draft and defaults to automatic rounding if eligible. Issued tax-invoice monetary fields and round-off policy stay locked under the existing adjustment rules.
- Verify half-rupee boundaries, exact totals, GST/charges, manual override, mode changes, currencies/types, legacy edits, duplicates, full/partial conversions and the issued-invoice lock through real HTTP and persistence, with focused native MySQL and responsive browser checks. Existing records and receipts must not be modified by the migration.

## Implementation and release safety

`auto_round_total` is false by default for existing records; the invoice writer
sets it true for new eligible documents unless an explicit manual mode, or a
legacy nonzero manual `round_off` without a mode, was supplied. The server
recalculates auto mode after all other monetary fields and saves the signed
adjustment, balance and words. Switching an auto draft to an ineligible currency
or type without a new manual adjustment does not carry the old computed value.
Conversion explicitly turns off auto mode to preserve the old claim. Copies are
new eligible drafts and calculate their own adjustment. The mode is a locked
monetary field on issued tax invoices.

Deploy the migration with the application release, before accepting invoice
writes, using `php artisan migrate --force` after the usual backup. This task
changes no production database by itself. The migration's `down()` refuses to
drop an active `auto_round_total` flag because that would lose invoice policy.
For rollback, stop invoice writers first: MySQL DDL commits independently, and
the check cannot exclude a concurrent write between the check and ALTER. Keep a
backup and determine how each auto invoice's policy will be preserved or
explicitly converted to manual before removing the column; a code-only rollback
may leave the extra column in place. Do not force the column drop against active
invoices.

## Reproducing the browser check

`tools/checks/invoice-rounding-browser.cjs` uses Playwright and Chromium against
an authenticated **disposable loopback** instance with a freshly migrated test
database; it creates and edits invoices and rejects non-loopback URLs. Install
`playwright-core` and a Chromium binary separately, set `NODE_PATH` as needed,
then supply `INVOICE_TEST_BASE_URL`, `INVOICE_TEST_EMAIL`, `INVOICE_TEST_PASSWORD`
and `CHROMIUM_EXECUTABLE_PATH`. It exercises the real login, Blade, scripts,
form submissions and persisted edit at 1440/768/390px, including Select2 GST and
discount changes, half-paise line values, currency/type switches and manual mode.

## Verification — 27 October 2026

- Test-first full-schema HTTP scenarios: after correcting a missing fixture
  invoice number, seven intended failures demonstrated the missing behaviour;
  the completed scenarios passed. The migration rollback guard, currency/type
  transition and Select2/half-paise preview cases were also observed failing for
  the intended reason before their respective fixes. Additional conversion,
  non-zero GST and e-invoice assertions are regression/characterization coverage.
- PHP 8.4.26, `APP_ENV=testing APP_RUNNING_IN_CONSOLE=true php vendor/bin/phpunit
  -c phpunit.xml --no-progress --display-phpunit-notices`: **642 tests / 16,828
  assertions passed**, with the one pre-existing PHPUnit notice about a transport
  mock without expectations. Runtime 46.9 seconds.
- Disposable strict MySQL **8.0.14**: the full fresh migration chain, then
  `DB_CONNECTION=mysql ... php vendor/bin/phpunit -c phpunit.mysql.xml
  --no-progress --display-phpunit-notices`: **25 tests / 258 assertions passed**
  in 5.0 seconds, including real HTTP invoice saves, exact partial conversion
  and a separate temporary schema for existing-data/rollback migration checks.
  Portable and native suites ran serially to avoid fake-storage collisions.
- `npm run test:contracts` passed (291 designed cases, 134 method-linked; 77
  PHP test files inventoried). `node tools/checks/invoices-check.cjs` passed
  **82 checks** after correcting its pre-existing false positive for the loaded
  shared client-account stylesheet. Route inventory: **483**; `git diff --check`
  passed. Links and inventories do not claim measured runtime coverage.
- Real headless Chromium 153 against a freshly migrated disposable SQLite DB
  and the live PHP 8.4.21 server: `node tools/checks/invoice-rounding-browser.cjs`
  passed at **1440, 768 and 390 px**. It exercised login, automatic/manual
  toggles, half-up and half-paise boundaries, Select2 changes, currency/type
  switches, a real create and subsequent manual edit, and responsive control
  placement. No production data or real delivery services were used.
