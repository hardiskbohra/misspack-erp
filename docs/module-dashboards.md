# Overview and module dashboards

## Navigation

The existing `/dashboard` page remains the ERP-wide command view. It combines cross-module statistics, immediate attention, comparisons, operational trends and system health.

Dedicated dashboards are available from **Management → Module dashboards**:

- `/dashboards/sales`
- `/dashboards/purchase`
- `/dashboards/projects`
- `/dashboards/operations`
- `/dashboards/finance`
- `/dashboards/work`

`/dashboards` is the module dashboard directory. The overview's **All modules** panel also links to it.

## Reporting periods

`App\Services\DashboardPeriod` is the shared period parser. Dedicated dashboards accept monthly, quarterly, half-yearly and yearly periods. Controls are rendered in the application topbar. Every selected period has a preceding, equal-length like-for-like range used by comparable statistic cards.

Changing modules preserves the current reporting-period query string. Point-in-time cards such as total open receivables remain clearly labelled as the current open position and do not claim a period comparison.

## Projection rules

`App\Services\ModuleDashboard` supplies the six dashboard projections. Every projection returns:

- module-specific headline statistics;
- current-versus-prior values where a period comparison is meaningful;
- a six-segment selected-period trend;
- compact live status/workload distributions;
- important red alerts with links to the source work queue or record.

The projections are schema-aware so an optional or not-yet-migrated module produces an empty state rather than breaking the dashboard.

### Financial authority

Open receivables and payables use `SalesInvoice::balanceDue()` and `PurchaseInvoice::balanceDue()` with their payment and allocation relations loaded. They do not trust duplicated balance summary columns.

Finance labels cashflow values as **inward movement** and **outward movement**, not income and expense. Transfer-category entries are excluded from both movement totals and their trend so bank-to-cash contra movements are not treated as operating inflow or outflow.

Unallocated cash is reported as aggregate entry-currency values; currencies are not silently converted or added as an INR equivalent.

## Interface contract

- Statistic cards retain a white surface, including in dark mode.
- There is no redundant dashboard title/description card below the application topbar.
- Alerts use a high-contrast red treatment and an actionable attention list.
- Cards have borders and no box shadows.
- Module navigation and trend panels remain horizontally usable on smaller screens.
- Native `progress` elements represent trend and distribution values, preserving accessible values without inline width styles.

## Regression coverage

Run:

```bash
node tools/checks/module-dashboard-check.cjs
```

The check covers stable routes, module coverage, period controls, comparisons, alerts, authoritative balances, contra exclusions, navigation, white statistic cards and responsive/no-shadow styling. The repository-wide PHP, Blade, design, SQL and module checks should also remain green.
