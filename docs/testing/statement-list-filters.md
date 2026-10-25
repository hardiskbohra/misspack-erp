# Statement list: All time and currency quick filters

25 October 2026 acceptance (REQ-STATEMENT; IT-STATEMENT-004/005):
- The rendered All time link must retain `period=all`, clear both date bounds,
  and preserve search, party type and selected currency. Following it must include
  historical client and vendor activity, not silently fall back to this month.
- With no currency filter, list all party/currency accounts. Specific currency
  quick filters and All currencies remain visible, work for client/vendor/all
  party modes, retain other criteria, and show the active selection.
- Keep one row and one statement link per party/currency. Totals must be separated
  by currency; never add INR/USD/RMB etc into one monetary number. Counts can sum.
- Search, drawer Apply, filter removal and individual statement links preserve
  All time. Single-party statements, PDF and shares remain single-currency.
- Include cashflow-only currency codes in the picker. Unsupported/array currency
  inputs receive validation errors, not a500 or silent fallback.
- Verify genuine rendered links and real HTTP/database results before production
  changes; then desktop/mobile browser interaction, full portable Unit/Integration/
  Feature, source contracts and native MySQL reporting CI. Fixtures are disposable.

Initial actual red run: four HTTP regressions failed (rendered All time lost its
preset, default currency remained INR, no visible currency quick filters, and
malformed currency was not validated). Fixture setup errors were corrected before
recording this behaviour red. All time used to look like an opening-only account
because the page was silently in This Month; the regression follows the exact
rendered link and asserts zero opening plus all historical movement in both types.


## Implementation and evidence

- Fixed the shared chip URL predicate: `all` is removable for party/currency
  defaults, **not** for `period=all`. The explicit preset survives navigation,
  search, currency removal and drawer Apply. Date edits change the hidden period
  to custom, avoiding an All time preset overriding an intentionally entered date.
- List default is all currencies; one-party documents/PDFs/shares remain
  single-currency. Each row/link keeps its own currency. The activity service
  explicitly accepts all, suppresses mixed-currency money aggregates and retains
  the legacy INR default for existing direct callers.
- Cards/footer show separate currency subtotals; counts refer to accounts rather
  than pretending multiple currency accounts are distinct people. All parties
  uses neutral debit/credit labels; vendor-specific headers correctly identify
  debit/Paid and credit/Billed. An actual red regression preceded the label fix.
- The list picker also discovers currencies present only in cashflow. Selected
  currency applies to the visible send-log rows as well. Invalid currency inputs
  use the ERP's existing web validation redirect/session error contract; no
  application-wide exception-format change was made.
- While checking filters, a separate actual red exposed date-cast midnight
  timestamps excluding a month's final day and displaying last activity as a dash.
  Date-based activity sources now compare/store Y-m-d calendar dates. October's
  expected125 remains unchanged;1Nov900 remains excluded. Native MySQL covers a
  same-day closed range independently.

## Actual verification (25 October 2026)

- Six focused HTTP/database integration tests: **61 assertions PASS**. Finance
  permission middleware remains enabled; fixtures use real migrations in forced
  in-memory SQLite. The first fixture-only errors were not counted as TDD red.
- Full portable Unit+Integration+Feature: **480 tests,15,277 assertions PASS** on
  PHP8.4.25 WASM; JUnit separately checked for zero errors/failures/skips. This
  includes the six new cases, not an additional six on top of480.
- `npm run test:contracts`: catalogue freshness/negative tests,632 active module
  period assertions,19 dashboard contracts and14 Blade checks (245 templates).
  `node tools/checks/statement-check.cjs`: **75 checks PASS**;
  `node tools/checks/design-check.cjs`: **79 checks PASS**.
- Chromium153 at1440/900/390px: actual clicks through All time, USD, Clients,
  search submission, restoration to All currencies, preserved query context,
  resulting fixture rows, drawer custom-date switching, Escape close and no
  document overflow or JS exceptions. Navigation waits for stylesheet/JS loading;
  HTTP regressions independently verify real controller/service/database results.
- Native MySQL regression added to `MySqlReportingTest`: historical client and
  vendor rows, separate USD/RMB subtotals, null mixed total and inclusive same-day
  dates. No native PHP/MySQL is available locally; both CI jobs are checked after
  push. No concurrency or FX conversion certification is claimed.

### Reproduce browser checks

```sh
APP_ENV=testing php tools/testing/render-statement-list.php .work/statement-list
CHROMIUM_EXECUTABLE_PATH=/path/to/chromium \
  node tools/checks/statement-list-browser.cjs .work/statement-list
```

Requires development Composer dependencies and `playwright-core`; `NODE_PATH` may
point to a temporary install. Fixtures refuse non-testing environments and force
SQLite :memory:. Generated HTML/screenshots/JUnit remain in ignored `.work/`.
