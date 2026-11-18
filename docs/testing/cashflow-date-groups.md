# Cashflow list date groups

## Acceptance — IT-CASH-009

The cashflow listing groups each visible entry exactly once, using its `entry_date` and the office's configured business timezone for today. In newest-first order the groups are **Today**, **Yesterday**, **2 days ago**, **This week**, **Last week**, **This month**, **Last month**, and **Older entries**. Only groups with rows on the current page are shown. The more specific label wins when calendar periods overlap: the first three days win over weeks, and weeks win over months. Weeks run Monday through Sunday; months are calendar months, including year boundaries.

Entries dated after the business day, if present, appear in a separate **Upcoming** group, rather than being mislabeled as past activity. Undated entries, if encountered, retain **Undated**. The existing query, financial figures, filters, pagination and default newest-first order are unchanged; a report drill-down requesting oldest-first still uses its existing ordering. Divider counts describe rows on the current page, not the entire filtered ledger.

Concrete boundaries:

- On Thursday 29 October 2026: 29/28/27 October are Today/Yesterday/2 days ago, 26 October is This week, 19–25 October is Last week, 1–18 October is This month, September is Last month and August or earlier is Older entries.
- On Monday 4 January 2027: 1–3 January and 28–31 December overlap last week; Yesterday and 2 days ago take precedence, and the remainder is Last week. Earlier December is Last month.
- On Sunday 1 March 2026: dates 23–26 February are This week despite falling in the previous month; 16–22 February is Last week, earlier February is Last month and January is older.
- At 20:00 UTC on 28 October, the office in Asia/Kolkata is already on 29 October; 29 October is Today.

## Verification (2026-11-01)

- **Red first:** `APP_ENV=testing APP_RUNNING_IN_CONSOLE=true php vendor/bin/phpunit -c phpunit.xml tests/Integration/CashflowDateGroupingTest.php --filter test_listing_groups_rows_in_specificity_order_and_preserves_oldest_sort` failed on repeated `Earlier this month` dividers for future dates. The boundary test independently failed because the requested groups were absent; the office-midnight case also exposed the server-timezone discrepancy. Tests used full migrations, a disposable in-memory SQLite database and a frozen clock.
- `APP_ENV=testing APP_RUNNING_IN_CONSOLE=true php vendor/bin/phpunit -c phpunit.xml tests/Unit/CashflowDateGroupsTest.php tests/Integration/CashflowDateGroupingTest.php --no-progress` — **6 tests / 1,030 assertions PASS** (00:04.206, PHP 8.4.26). Includes leap day, contiguous groups across several calendar boundaries, all requested dividers, future/undated, business timezone, overlapping weeks/months, both sort orders, filters and page-only counts.
- Full portable Unit/Integration/Feature suite: `APP_ENV=testing APP_RUNNING_IN_CONSOLE=true php vendor/bin/phpunit -c phpunit.xml --no-progress --display-phpunit-notices` — **723 tests / 19,459 assertions PASS** (02:19.730, PHP 8.4.26). The existing `SalesInvoiceEmailTest` mock-without-expectations notice remains.
- `npm run test:contracts` — PASS (32 domains, 312 designed cases, 159 method-linked cases, 85 executable files inventoried; not a runtime coverage claim); route inventory — 489 declared routes; PHP/Node syntax and `git diff --check` — PASS.
- **Browser red/green:** at 390px the group count was off-screen because the card table retained an 800px minimum width; the bulk action's Select2 text also covered the Clear button, and its 34px buttons failed the 40px touch-target check. Scoped cashflow-only mobile rules fixed these without changing shared components. `node tools/checks/cashflow-date-groups-browser.cjs` against a disposable SQLite server with `tools/testing/cashflow-date-groups-router.php` and `tools/testing/seed-cashflow-date-groups-browser.php` — **1440px, 390px and 320px PASS** for group names/counts and totals fitting, debit filter, bulk select/clear and oldest-first sorting. The test router fixes the office clock at 29 October 2026; the server uses a test-only extended session lifetime to prevent frozen-clock cookie expiry. No physical phone was tested.
- No new SQL, schema or locking behaviour was introduced; no local native-MySQL run was needed for this presentation-only change. No production data or credentials were used.
