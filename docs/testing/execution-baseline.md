# Execution baseline and open gaps

**Date:** 25 October 2026. **Application baseline:** custom-date-range commit `8186393`, plus this testing-only change set. Production application/service/controller behaviour was not changed to make these tests pass.

## Original testing-pack evidence (commit96bdcf5)

| Check | Result / limit |
|---|---|
| Complete portable PHPUnit configuration (`phpunit.xml`) | Local427 tests/14,986 assertions PASS; native PHP CI job also PASS. Includes Unit, Feature and Integration, not MySQL-only tests |
| New executable foundation | 134 expanded tests: permission/employee/asset/file/portal unit cases plus HTTP/notes/workflow/route integration cases; included in the full result |
| Catalogue validation | 31 domains,208 designs:93 unit +115 integration;53 designs have exact method-level links, some partial |
| Source inventory | 41 test files, including the separately invoked MySQL class; counts are source declarations, not pass/coverage numbers |
| Route export | 454 declared routes with action/middleware metadata; no access or ownership guarantee implied |
| Portable Node checks | Catalogue consistency, period-control behaviour, module dashboard contracts and Blade structure; actual run recorded with final hand-off |
| Native MySQL 8 migrations + day/month/year SQL | **PASS in native GitHub CI run40176652907**; not executed in this sandbox, which has no native MySQL service |
| Browser/system journeys | Designed here, not executed end-to-end in this task; earlier task screenshots/checks are not presented as fresh executions |
| Line/branch/mutation coverage | Not measured locally; native CI completed the coverage command and uploaded Clover/JUnit artifacts. Artifact contents were not inspected here; no percentage claimed |

The sandbox has no native PHP/Composer installation. Executions used PHP8.4.25 through `@php-wasm/cli`3.1.60 and the locked repository dependency sources in ignored `vendor/`, with a test-only autoloader bootstrap. This is useful actual runtime evidence but not a substitute for a native Composer/PHP/MySQL run. CI installs the real locked dependencies via Composer on PHP8.4 and supplies Xdebug and MySQL separately. Both native jobs passed in [run40176652907](https://github.com/hardiskbohra/misspack-erp/actions/runs/40176652907), testing code commit96bdcf5. The run/job status was verified through the GitHub API; raw log download is blocked by this sandbox network policy.

Normal native command: `vendor/bin/phpunit -c phpunit.xml`. Sandbox equivalent used:

```sh
APP_ENV=testing PHP=8.4 /tmp/erp-test-tools/node_modules/.bin/php-wasm-cli \
  vendor/phpunit/phpunit/phpunit -c phpunit.xml --no-progress \
  --log-junit .work/full-suite.xml
```

Runtime logs and raw XML stay under ignored `.work/`; CI artifacts use their own storage. No customer dataset or generated vendor tree is committed.

## Legacy test-only corrections

The first full attempt exposed environment gaps and old assertions. These corrections retain and strengthen the underlying checks rather than changing financial production code:

| Old test problem | Correction and reason |
|---|---|
| Root example expected HTTP200 | Assert the actual documented redirect to `login`; root route deliberately redirects. |
| Cashflow party-column contract omitted office services | Include the existing `office_service_id` link; production already supports it. |
| Calendar helper treated1-based occurrence IDs as list indices | Compare normalized date values while adding an explicit `[1,2]` sequence-key assertion. Production documents sequence1 as first. |
| End-date test expected5December before a1December cutoff | Expect only5October/5November; the date ceiling is real, not an approximate month. |
| NPS expected50 for scores10 and5 | Expect0 (one promoter, one detractor); separately assert50 for10 and8 (one promoter, one passive). |
| Empty dimension array assumed passive despite high headline scores | Characterize current declared implementation: no weak dimension plus overall5/recommendation10 yields promoter. This does not bypass request-level minimum-answer validation. |
| Payslip fixture did not preload issuer addresses/banks | Set explicit in-memory relations; do not depend on absent tables or another test's organisation state. |
| Payslip equality required the same Carbon object identity | Assert equal paid-date values and keep strict equality for canonical document arrays. |
| Two in-memory unheaded cartons both had null IDs | Assign distinct fixture IDs, matching the stored-row domain; unheaded blocks now really are distinct. |
| Pure PHPUnit packaging test used framework-aware model collection | Boot the Laravel test case, while retaining explicitly loaded items and no business-row persistence. |

The XML now specifies an isolated DB, a fixed non-secret32-byte test key and512MB test memory budget. The complete PHP-WASM run required about313MB; smaller focused runs use much less. CI coverage collection may need a separate measured budget if its runner differs.

## Confirmed open accounting discrepancy

**IT-ASSET-002, not fixed or hidden:** using an in-memory asset with cost100000, residual5%, life5, purchase1Apr2026 and disposal30Sep2026, `AssetDepreciation::chargeForYear(...,'2026-27')` returns **95000**. The service's own disposal-prorating description implies **9526.03** (`19000 × 183 / 365`). The final-row residual adjustment also runs for early disposal. The probe executed the real service and did not persist any asset.

Accounting-policy signoff and a red/green regression are the next priority. See [tdd-playbook.md](tdd-playbook.md). A green suite does **not** mean the existing suite covers this defect, or that all208 planned cases passed.

## Remaining release gates

1. Keep the now-passing native MySQL migration/aggregation CI mandatory; no local native-MySQL execution is claimed.
2. Verify upgrade/rollback paths against representative prior schemas; SQLite scoped fixtures do not prove the full migration chain or MySQL semantics.
3. Real concurrent allocation/posting/conversion/numbering tests with overlapping native connections remain planned.
4. Full portal record/download/print/export isolation matrix remains planned beyond unit enablement and office/notes boundaries.
5. Full QC/packing/dispatch/closure and stale-evidence browser journeys remain planned beyond tested service execution and existing financial regressions.
6. Load/accessibility/responsiveness/print and backup/restore release checks require actual measured runs, not a test-design count.

## Final portable run

- **427 tests, 14,986 assertions: PASS** with `phpunit.xml` on PHP8.4.25 (PHP-WASM).
- Includes **134 new expanded portable tests**; the three MySQL data-provider cases are outside this count and unexecuted locally.
- Runtime25.026s; peak312.50MB in this sandbox. This is an observed test-run measurement, not an application performance SLA.
- `npm run test:contracts`: PASS — catalogue/method traceability, tooling self-checks,1764 period-control assertions,17 module-dashboard checks and14 Blade checks across244 templates.
- Route-inventory regeneration/check: PASS,454 declarations.
- Native PHP/Composer suite and disposable MySQL fresh-migration/aggregation jobs: PASS in the linked CI run.
- No local line-coverage percentage, inspected native assertion count or full-browser/system certification is asserted.


## Delivery receipt follow-up (25 October 2026)

The receiver-facing delivery receipt is a subsequent production view/presentation change, not part of the original testing-only baseline above. The current catalogue contains **212 designs** (94 unit,118 integration), **57 method-linked cases** and **44 PHPUnit files**. Existing planned cases and release gaps remain open.

- Latest complete portable run: **443 tests,15,093 assertions PASS** (Unit, Integration and Feature), PHP8.4.25 WASM; independently checked JUnit with zero failures/errors/skips.
- `npm run test:contracts`, shared PDF documents and project-workflow checks passed.
- Real Chromium/PyMuPDF print checks passed for own-company, vendor, discrepancy and60-line receipts; browser Print / Save as PDF only, not optional DomPDF or full end-to-end system journeys.
- Test-first failures, focused results, commands and limitations are recorded in [delivery-receipt-print.md](delivery-receipt-print.md). The original native CI links above do not certify this later change; its new branch CI is checked separately.


## Dashboard consolidation follow-up (25 October 2026)

Current catalogue: **216 designs** (95 unit,121 integration),61 linked designs and47 PHPUnit files. This follow-up consolidates existing module dashboards and removes duplicated overview projections; it does not certify every business question in the ERP.

- Complete portable suite: **471 tests,15,209 assertions PASS** (Unit, Integration and Feature), PHP8.4.25 WASM. New dashboard cases are included in that total.
- Browser verification: real Blade/fictional in-memory fixtures for executive overview and six modules at1440/900/390px, no document overflow or JavaScript exceptions; actual drawer/Select2 period controls and legacy-tab canonical navigation checked separately.
- Read-only monetary, date, permission, product/header cardinality, ageing and unit-separation tests documented in [dashboard-consolidation.md](dashboard-consolidation.md).
- A new native MySQL module concentration/trend reconciliation test is included in the separate MySQL configuration. Its CI result is checked after pushing; no local native MySQL execution is claimed, and the earlier CI links above do not certify this change.


## Module-only dashboards and visual contract follow-up (25 October 2026)

The previous overview retention is superseded: executive reporting and its links are removed; `/dashboard` redirects to the permission-filtered module directory. The empty-permission state is explicit. Shared `.core-card` styling and restored light/dark `--mc-card` tokens fix transparent analytical cards; no hardcoded white dark-mode override remains.

- Full portable Unit+Integration+Feature: **474 tests,15,216 assertions PASS**, PHP8.4.25 WASM.
- Browser:42 light/dark page-state checks across directory/six modules at three widths; actual white card backgrounds in light mode and canonical dark backgrounds, consistent card radius, no overflow/JS exceptions, real period controls and drawer Escape/focus restoration.
- Contracts:632 active module period assertions,19 module contracts and14 Blade checks (244 templates), plus catalogue/negative self-checks. Deleted overview scenarios are retired explicitly, not counted as retained coverage.
- Native CI is independently verified after push; no SQL or concurrency change/certification is claimed. See [dashboard-consolidation.md](dashboard-consolidation.md).


## Statement All time and currency quick-filter follow-up (25 October 2026)

Current catalogue:218 designs (95 unit,123 integration),63 method-linked designs
and48 PHPUnit files. These design counts are not measured coverage.

- Test-first failures reproduced the dropped `period=all`, INR-only default,
  missing currency quick filters, invalid currency handling, incorrect vendor
  debit/credit descriptions and final-day date-cast exclusion.
- Complete portable suite: **480 tests,15,277 assertions PASS**, PHP8.4.25 WASM,
  Unit+Integration+Feature; independently inspected JUnit.
- Chromium: actual statement filters and drawer at desktop/tablet/mobile widths;
  source statement75/design79 and all default contracts passed. Each monetary
  subtotal remains single-currency; all-currency grand totals are null.
- A native MySQL controller/reporting regression covers historical client/vendor
  data, separate currency totals and a closed one-day window. Native CI results
  are checked after pushing; local native execution is not claimed.
- See [statement-list-filters.md](statement-list-filters.md) for commands, scope
  and evidence. No live installation data was accessed or modified.


## Compact topbar follow-up (25 October 2026)

- Catalogue219 designs (95 unit,124 integration),64 linked designs,48 PHPUnit files.
- Actual red3 targeted failures, then full portable **483 tests/15,304 assertions PASS**
  on PHP8.4.25 WASM. Unit, Integration and Feature all included.
- Browser: light/dark module workspaces and directory at desktop/tablet/mobile;
  icon-only launchers, private Notes keyboard path, profile theme option, selected
  dates/currency in content, mobile reporting drawer and40px targets verified.
- Shared filter icon variant is opt-in and generated static CSS rebuilt. No SQL or
  arithmetic changes; native CI checked after push independently.
- See [compact-topbar.md](compact-topbar.md) for TDD, changed menu expectations and
  scope. Design counts are not measured runtime coverage.


## Statement print preview —25October2026

Test-first new-tab preview/download selection plus address/exact-date regressions:
Red5failures/6assertions, then follow-up2missing-helper errors/1missing-date failure;
Green8focused/71assertions. Full portable **491tests/15,376assertions PASS**.
The historical opening balance remains part of the monthly closing balance; no
accounting calculation or SQL changes. Default contracts and statements75/shared
PDF20/design79/UI24source guards pass. Actual Chromium/PyMuPDF proves popup and
filter/download interactions, unclipped complete amounts, three-line issuer
address, exact custom dates, embedded logo,12mmA4 bounds and73rows across3pages
with all rows and only one final closing total. See[statement-print-preview.md](statement-print-preview.md)
for commands, optional browser Save as PDF behaviour and local engine limits.
Updated inventory:221designs (96unit/125integration),66method-linked designs,
50test files,455declared routes; no runtime coverage percentage is implied.
