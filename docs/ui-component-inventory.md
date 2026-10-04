# UI component inventory

This is an inventory of reusable UI already present in the project, not a claim that every component in the roadmap is finished. **Shared** means there is a reusable implementation; **Partial** means it exists in one or more screens but is not yet a common component; **Gap** means it is a candidate for the future UX guideline and has no dependable shared implementation. Update this inventory whenever a component is promoted, added, or retired.

New views should prefer `core-*` classes. Existing `master-*`, `cf-*`, `cp-*`, `cpa-*`, `ship-*`, `pd-*`, and other page namespaces are compatibility adapters.

## Layout

| Component | Status | Current implementation |
| --- | --- | --- |
| App shell | Shared | `layouts.app`, `.sidebar`, `.main-wrap`, `.page-content`; `public/assets/css/app-layout.css` |
| Sidebar | Shared | `.sidebar`, `.sidebar-item`, `.sidebar-nav`; `app-layout.css` |
| Mini sidebar | Partial | Admin `.sidebar-collapsed` and portal icon rail at 992–1199px, with a tablet/mobile drawer below; `app-layout.css`, `app-layout.js`, `client-portal-workspace.css`, `resources/css/layout/responsive.css` |
| Topbar | Shared | `.topbar`, `.topbar-title`, `.topbar-actions`; `app-layout.css` |
| Breadcrumb | Partial | `.master-breadcrumb`, module-specific breadcrumb headers |
| Page header | Shared | `.master-header`, `.cf-header`, `.cp-page-head`; normalize through `components/cards.css` and `layout/page.css` |
| Page footer | Gap | No general application page-footer component |
| Section header | Shared | `.master-section-title`, `.cf-section-title`, `.cp-section-heading` |

## Navigation

| Component | Status | Current implementation |
| --- | --- | --- |
| Sidebar menu | Shared | `.sidebar-nav`, `.sidebar-item`; portal uses `.cp-nav` / `.cp-nav-link` |
| Nested menu | Partial | Menu groups and active items exist; no consistent nested disclosure contract |
| Tabs | Shared | `.master-tabs` / `.master-tab`; Cashflows `.cf-tabs` / `.cf-tab`; core aliases in `components/tabs.css` |
| Stepper | Partial | Shipment tracking and project milestone steppers are page-specific (`shipments.css`, `projects.css`) |
| Pagination | Shared | Laravel paginator partial and `.master-pagination` / `.cp-pagination`; `resources/views/components/pagination.blade.php` |
| Secondary navigation | Partial | Record tabs and portal sub-navigation, no standalone shared secondary-nav component |

## Data views

| Component | Status | Current implementation |
| --- | --- | --- |
| Standard table | Shared | `.core-table`, `.master-table`, `.cp-table`; wrappers keep horizontal scrolling outside the table |
| Table density | Shared | Standard, compact, and comfortable modes; `master-list.js` persists each opted-in view in localStorage and `tables.css` applies responsive paddings |
| Compact table | Shared | Compact preset is one of the three shared density modes; `master-list` keeps its mobile card treatment |
| Grouped table | Partial | Sectioned lists/subtotals exist in Cashflows and reports; no general grouped-table API |
| Sortable table | Partial | Some lists sort via query controls; no shared accessible sort-header component |
| Filterable table | Shared | Visible search and quick chips stay in the list toolbar; secondary criteria open in the shared, theme-aware right drawer across administrative and client-portal modules |
| Selectable table | Partial | `.master-list-pick` and bulk controls on some lists; not available on every data table |
| Column chooser | Shared | `master-list.js` creates an accessible per-table chooser for `data-table-settings` tables; visibility preferences persist by `data-table-key` in localStorage and action columns remain visible |
| Saved views | Shared | Named filter sets are stored per user and module in `saved_views` (`app/Services/SavedViews.php`) and rendered by the `.master-list-saved*` chrome in the list toolbar; clients, shipments, cashflows, sales invoices and vendors use the same store |
| Server-side pagination | Shared | Laravel paginator/query pagination in module controllers and shared view partial |
| Grid view | Partial | Product/catalogue and portal cards; not a general table/grid switch |
| List view | Shared | `.master-list` list chrome for the administrative index pages |
| Kanban | Partial | Tasks board `.master-board`, `.master-column`, `.master-task-card`; behavior and card content are task-specific |
| Timeline | Partial | `.timeline`, `.pd-timeline`, `.ship-track`; shared visual baseline, distinct business markup |
| Master-detail | Shared | Shipment, client, user, project and other show/detail screens use the master-detail card/tab patterns |

## Cards

| Component | Status | Current implementation |
| --- | --- | --- |
| KPI card | Shared | `.master-stat`, `.cf-stat`, `.cp-stat`; semantic icon accents and neutral surfaces |
| KPI trend card | Gap | No common trend/delta KPI card |
| Summary card | Partial | `.master-info`, statement summaries, report totals; semantics vary by module |
| Product card | Partial | Product grid and client-portal catalogue card styles |
| Project card | Partial | Project list/show and client portal project cards |
| Vendor card | Partial | Vendor pages use master card and vendor-specific summary patterns |
| Activity card | Gap | Timeline entries exist; no shared activity card/feed component |
| Alert card | Partial | Semantic alerts exist; informational callout and alert-card hierarchy are not fully standardized |

## Forms

| Component | Status | Current implementation |
| --- | --- | --- |
| Text | Shared | `.core-text-input`, `.master-input` |
| Number | Shared | Native number input with shared field styles |
| Currency | Partial | Money helpers and currency-prefixed/amount controls in finance modules |
| Select | Shared | `.core-select`, `.master-select` |
| Multi-select | Partial | Select2 multi-select on selected screens; not an app-wide default |
| Search select | Shared | Select2 theme and shared select initialization |
| Date | Shared | Native date/Flatpickr integrations with shared form styles |
| Date range | Partial | Date-from/to filters, statement and report-specific components |
| Time | Partial | Native time/date-time fields, no shared time-range component |
| File | Shared | Native file fields and upload controls in module forms |
| Image upload | Partial | Product, shipment, vendor, and portal upload flows are page-specific |
| Rich text | Gap | No reusable rich-text editor component found |
| Checkbox | Shared | `.master-check` and core checkbox styles |
| Radio | Partial | Native radios in module forms, no shared radio-card group |
| Toggle | Partial | Theme toggle and shipment visibility switch; no general setting-toggle API |
| Tags | Partial | Badges and Select2 tags on some screens; no common editable tag input |
| Quantity + unit | Partial | Product/shipment line-item controls; no shared quantity-unit component |

## Feedback

| Component | Status | Current implementation |
| --- | --- | --- |
| Toast | Shared | `MasterAlert.toast` and client-portal flash/toast styles |
| Alert | Shared | `.master-alert`, `.cf-alert`, `.cp-alert`, validation/error styling |
| Confirmation | Shared | `MasterAlert` confirmation dialogs and `data-confirm` handling |
| Empty state | Shared | `.master-list-empty`, `.master-empty-state`, `.cp-empty` variants |
| Error state | Partial | Validation and page-level error states; no universal error-page component |
| Loading | Gap | No app-wide loading-state contract |
| Skeleton | Gap | No skeleton components found |
| Progress | Partial | Upload/step/progress displays exist in domain flows; no general progress component |

## Overlay

| Component | Status | Current implementation |
| --- | --- | --- |
| Modal | Shared | `.master-modal`, `.master-modal-card`; Projects and client-document variants are compatibility adapters |
| Drawer | Shared | `<x-drawer>` plus `master-drawer.js` provides theme-aware, responsive panels, focus trapping, Escape/backdrop/close handling, and focus restoration; vendor/project quick details and module filter panels demonstrate the pattern |
| Dropdown | Shared | `.master-dropdown`, user-menu and Select2 dropdowns |
| Popover | Partial | Tooltips and anchored helper patterns exist; no shared popover primitive |
| Tooltip | Partial | Tooltip containers in lists and finance summaries; not consistently accessible/initialized |
| Context menu | Gap | No common context-menu component found |

## Business components

| Component | Status | Current implementation |
| --- | --- | --- |
| Price ladder | Shared, domain-specific | Product price ladders and price-calculator grid (`products.css`, `price-calculator.css`) |
| Quote comparison | Gap | Lead quotes are listed per lead; the vendor-quote module was removed, so no comparison matrix exists |
| Payment summary | Partial | Invoice, portal, and Cashflow payment summaries use separate markup |
| Invoice summary | Partial | Sales invoice show/list and portal invoice detail cards |
| Shipment tracker | Shared, domain-specific | Shipment detail tracker plus public tracking/stepper (`shipments.css`, `shipment-public.css`) |
| Project timeline | Partial | Project activity/milestone chronology and client-portal timeline (`projects.css`, `client-portal-project-show.css`) |
| Milestone tracker | Shared, domain-specific | Project milestone stepper and editor (`projects.css`) |
| Activity feed | Gap | Chronological entries exist, but no normalized event/feed API or component |
| Document attachment | Shared, domain-specific | Master, vendor, project, shipment, and portal attachment cards/grids |
| Approval flow | Partial | Client KYC review/approval states; no reusable multi-step approval flow |
| Audit log | Gap | No shared audit-log view component found |

## Architecture follow-up

The inventory records what exists today. The shared responsive contract lives in `resources/css/layout/responsive.css`: use `.ui-mobile-secondary` for genuinely optional tablet/phone details and `.ui-mobile-cards` plus `data-label` for labelled mobile rows. Keep core actions and required data visible. Tables opt into saved columns and three density modes with `data-table-settings` plus a unique `data-table-key`. Use `<x-drawer>`, `<x-filter-trigger>`, and the shared `data-drawer-*` API for keyboard-accessible quick details and module filter panels; leave primary search and quick chips visible. See `docs/ui-design-guidelines.md` for usage rules. The detailed UX guideline should specify when to choose each pattern, interaction and keyboard behavior, empty/loading/error states, responsive transformations, and content rules. It should not create a second visual token or component API.
