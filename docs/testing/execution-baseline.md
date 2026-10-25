# Execution baseline and open gaps

**Date:** 25 October 2026. **Application baseline:** custom-date-range commit `8186393`, plus this testing-only change set. Production application/service/controller behaviour was not changed to make these tests pass.

## Actual evidence

| Check | Result / limit |
|---|---|
| Complete portable PHPUnit configuration (`phpunit.xml`) | Final run recorded below; includes Unit, Feature and Integration, not MySQL-only tests |
| New executable foundation | 134 expanded tests: permission/employee/asset/file/portal unit cases plus HTTP/notes/workflow/route integration cases; included in the full result |
| Catalogue validation | 31 domains,208 designs:93 unit +115 integration;53 designs have exact method-level links, some partial |
| Source inventory | 41 test files, including the separately invoked MySQL class; counts are source declarations, not pass/coverage numbers |
| Route export | 454 declared routes with action/middleware metadata; no access or ownership guarantee implied |
| Portable Node checks | Catalogue consistency, period-control behaviour, module dashboard contracts and Blade structure; actual run recorded with final hand-off |
| Native MySQL 8 migrations + day/month/year SQL | Implemented as separate CI job/config; **not executed in this sandbox**, which has no native MySQL service |
| Browser/system journeys | Designed here, not executed end-to-end in this task; earlier task screenshots/checks are not presented as fresh executions |
| Line/branch/mutation coverage | Not measured locally; CI is configured to upload Clover/JUnit evidence, without claiming a guessed percentage |

The sandbox has no native PHP/Composer installation. Executions used PHP8.4.25 through `@php-wasm/cli`3.1.60 and the locked repository dependency sources in ignored `vendor/`, with a test-only autoloader bootstrap. This is useful actual runtime evidence but not a substitute for a native Composer/PHP/MySQL run. CI installs the real locked dependencies via Composer on PHP8.4 and supplies Xdebug and MySQL separately.

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

1. Native MySQL migration and aggregation CI must actually pass; no local pass claimed.
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
- No local line-coverage percentage, native-MySQL pass or full-browser/system certification is asserted.
