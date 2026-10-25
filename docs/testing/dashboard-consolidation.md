# One analytics dashboard per existing module

## Agreed scope

25 October 2026: consolidate existing analytics, not a new dashboard for every ERP table. Keep a compact executive overview. Six canonical destinations remain `/dashboards/{sales,purchase,projects,operations,finance,work}`; `/dashboards` is only a directory. Operational record lists/queues remain execution tools, not additional analytical dashboards.

## Acceptance / TDD designs

- **UT-DASH-004**: ageing bands distinguish undated/not-yet-due, 1–30,31–60,61–90 and90+; due today is not overdue. Module visibility follows existing permissions.
- **IT-DASH-006**: overview has module summaries and canonical links, not duplicate sales/finance/operations dashboard tabs. Selected dates survive overview → module and module → module navigation, including rolling/multi-year overview windows normalized to exact custom dates.
- **IT-DASH-007**: module analytical panels retain all existing chart domains and add client/product/vendor concentration, document ageing, workflow/ownership, freight/mode/carrier, receipt reconciliation, cash category/account/project movement and task workload/completion analysis. Period flows and live positions must be explicitly distinguished; money must be currency-filtered, never summed across currencies or presented as profit.
- **IT-DASH-008**: authorization applies before building data, including overview summaries; restricted users see only available module links. Unknown modules remain404; empty/missing data renders an honest empty/unavailable state. Existing custom-date validation stays enforced.

Coverage limits: this is operational/document analytics, not audited inventory valuation, statutory profit, predictive forecasting or salary analytics. No inferred FX conversions. No changes to accounting postings or operational workflow.

## Consolidated analytical coverage

Every module includes comparable KPIs, period trend, a secondary activity trend, live distributions, detailed analytical tables, and a capped priority action queue (up to8, explicitly labelled). Full source lists remain operational tools.

| Module | Analytics in its one workspace |
|---|---|
| Sales | Issued tax/cash sales and counts; lead intake/conversion activity; client/product concentration; lead sources; live client/invoice/lead statuses; payment/allocation-aware receivable ageing and overdue documents |
| Purchase | Orders and bills, comparative counts/values; order and bill trends; vendor/product concentration; order-vs-bill activity; vendor/document statuses; canonical payable ageing and overdue bills |
| Projects | Starts/completions; active/risky/unassigned projects and exceptions; workflow stages; owner/priority/category distributions; client workload and live client project-document value/progress (including active open PIs, excluding converted/superseded values) |
| Operations | Dispatch/delivery/freight/packing; shipment and packing trends; current shipment/packing status; transport modes/types; carrier freight; cartons; receipt reconciliation separated by unit, including damage, shortage, returns and replacement quantities |
| Finance | Currency-specific inward/outward/net movement; receivables/payables and allocation controls; project-tagged movement trend; cash category/account/project movement; payment modes; live accounting controls. Cash movement and invoice value are not labelled profit or closing bank balances |
| Work & People | Active headcount (not payroll); completed/open/overdue tasks; coordinator actions and ownership gaps; task intake/completion trends, live status/priority; assignee workload; on-time/late/undated completion analysis; exception ownership and feedback activity |

Currency options follow existing ERP currencies (INR/USD/RMB). Quantity units are never combined. All table groups are available in scrollable tables rather than silently truncating to top8; alert queues alone are intentionally capped. Missing source tables/required fields produce an explicit unavailable state; an empty installed source is labelled “No data for this scope.” Live metrics/statuses are current snapshots, not historical as-of-period positions. Lead conversion activity uses the existing last-updated timestamp, not an invented conversion event history.

## Test-first evidence and verification

- Before production edits:18 focused tests produced13 missing-feature errors (new analytics rules/service API) and5 intended old-UI/validation failures. The old overview/directory did not meet consolidation, visibility and currency assertions.
- A second red run reproduced missing secondary trends and sales-only directory access denial before those features were implemented. A separate red assertion reproduced missing live client project-value analysis.
- Financial integration fixtures exposed two real date-boundary discrepancies: model-written SQLite DATE values contain midnight timestamps, so same-day trend segments and final-day cash entries were being excluded. Original expected business totals remained unchanged; inclusive end-of-day boundaries made KPI/trend/table totals agree. This was not hidden by rewriting fixture dates or expected money.
- Complete portable suite: **471 tests,15,209 assertions PASS** on PHP8.4.25 WASM (native PHP/Composer unavailable locally); JUnit independently checked.28 new expanded dashboard cases are included, not added to that total. New native MySQL module reconciliation test is separate from the portable count.
- `npm run test:contracts`: catalogue freshness and negative self-checks,1764 period-control assertions,19 module-dashboard contracts and14 Blade checks (245 templates).
- Additional source checks: design79, projects60, permissions/audit21 passed.
- Actual Chromium153:21 page/viewport combinations passed (overview + six modules ×1440/900/390px), plus real drawer/Select2 date switching and legacy overview-tab → canonical module navigation. Fixtures include actual fictional invoices, direct receipts and allocations; no live application data or remote delivery services.
- Historical duplicated overview projection/record-feed code and unused canvas-tab JavaScript were removed. Existing custom-date/SQL compatibility helpers remain tested. The previous link-count assertion was updated from6 to7 because the overview link now also retains dates, rather than weakening date preservation.
- Local native MySQL is unavailable. CI freshly migrates an isolated MySQL8 database and runs the new native aggregation/reconciliation regression as well as the retained day/month/year cases; its result is verified after push. These checks do not establish multi-writer concurrency.

### Reproduce browser checks

```sh
APP_ENV=testing php tools/testing/render-dashboard-analytics.php .work/dashboards
CHROMIUM_EXECUTABLE_PATH=/path/to/chromium \
  node tools/checks/dashboard-consolidation-browser.cjs .work/dashboards
```

Requires locked Composer development dependencies, `playwright-core` and Chromium (`NODE_PATH` can point to a temporary install). All fixture databases are forced to disposable in-memory SQLite under `APP_ENV=testing`. Generated HTML and screenshots stay ignored under `.work/`; none belong in Git. The local PHP command used the PHP8.4 WASM runtime equivalent.
