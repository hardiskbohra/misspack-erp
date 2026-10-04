# MissPack ERP UI design guidelines

This is the shared visual contract for the ERP application shell and its modules. The current implementation is a mix of the newer `core-*` components and legacy `master-*`/module components; both should resolve to the same tokens and visual rules. New screens should use `core-*` where available. Existing screens may retain their markup while their module CSS is limited to layout and domain-specific states.

## Foundations

- **Typeface:** Inter, with the application font stack as fallback.
- **Page canvas:** `--mc-soft`.
- **Card surface:** `--mc-card` (white in light mode; the theme's dark surface in dark mode).
- **Nested/quiet surface:** `--mc-card-soft`.
- **Borders, text and shadows:** use `--mc-border`, `--mc-text`, `--mc-text-2`, `--mc-text-3`, and `--mc-shadow`; do not hard-code a light-theme color into a shared component.
- **Semantic palette:** blue, teal, green, orange, purple and red are reserved for state or meaning. Put semantic color on an icon, badge, indicator or hairline—not as a full-card pastel wash.

## Typography

| Role | Size | Weight |
| --- | ---: | ---: |
| Page title | 20–22 px | 600–700 |
| Card/section title | 15–16 px | 600 |
| Body and table values | 13–14 px | 400–500 |
| Field labels and table headings | 11–12 px | 500–600 |
| Key metric value | 18 px | 600 |
| Status/badge | 11–12 px | 600 |

**Project-wide cap: no text may exceed `font-weight: 700`.** This applies to application, public, portal, print, preview, inline, embedded, and checked-in vendor styles, as well as font files requested from external providers. Use weight and size sparingly; avoid extra-bold/black text for headings, labels, table headers, badges, and buttons.

## Print and PDF documents

- Use the shared Inter stack and tabular figures; keep document body text at roughly 10 pt (13 px), captions/field labels at 8–9 pt, and line-height around 1.4–1.5. Do not shrink dense financial tables below a legible size to force a one-page fit.
- Use A4 portrait with 12 mm margins for invoices, payslips and account statements unless a document has a clear landscape data need. Use a white paper surface and dark text regardless of the app or device theme; never carry a dark-mode canvas into a PDF.
- Print output removes browser/app chrome, shadows and rounded-card framing. Keep a simple hierarchy of issuer, document identity/date, counterparties, itemized figures, tax/totals, payment terms and sign-off.
- Right-align currency values with tabular numerals and use the shared currency formatter. Label taxable values, discounts, GST heads and balances distinctly; repeat table headers and avoid splitting a table row across pages.

## Surfaces and spacing

- Cards use a theme surface, a 1 px theme border, an 18 px outer radius, and the shared shadow. Nested cards use the same surface with a quieter border; do not use another pastel card fill.
- Stat cards use the same surface as ordinary cards. Their meaning is shown with a semantic icon badge and a restrained border accent.
- Tab panels may use `--mc-card-soft` to remain visibly distinct from the page canvas; cards inside the panel use `--mc-card`.
- Use an 8 px spacing rhythm: 12–16 px between related controls, 16 px between cards, and 20–24 px for outer card padding where the content permits.
- Controls and buttons should remain 40–44 px tall and retain visible keyboard focus states.

## Tables and tabs

- Tables sit inside a white/theme card with a single `--mc-border` row divider. Headers use the shared table-head token, 11 px uppercase text at weight 600; values use 13 px text at weight 400–500. Keep numeric columns right-aligned and tabular.
- Keep horizontal scrolling on the wrapper, not on the table element. On small screens, preserve the module's labelled row/card layout and touch targets.
- Use the shared `.master-tabs` / `.master-tab` treatment for record tabs. Module-specific tabs (such as Cashflow settings) should match its spacing, surface, active state, and focus treatment.
- Opt tables into preferences with `data-table-settings` and a stable, unique `data-table-key`. The shared chooser persists visible columns in localStorage; keep the actions column and essential mobile fields available. Density presets are **standard**, **compact**, and **comfortable**, with a per-view preference.
- Use `<x-drawer>` for quick details and lightweight side tasks that do not need a full-page transition. Open with `data-drawer-open`; provide a visible close control, Escape/backdrop behavior, focus trapping, and return focus to the opener. Keep drawer surfaces and borders theme-aware.
- On module list pages, keep primary search and quick-filter chips visible; put secondary criteria in a right-side `<x-drawer>` opened by `<x-filter-trigger>`. Keep the drawer controls inside the same GET form, preserve their names and selected values, and place Apply/Reset actions in its footer. Do not hide essential criteria with `desktop-only`; the shared drawer is the mobile access path too.

## Responsive behavior

Use one project-wide breakpoint contract; module styles may refine composition inside these bands, but must not redefine the shell behavior:

| Viewport | Layout rule |
| --- | --- |
| ≥1440px | Full layout, all desktop columns, expanded navigation by default, generous page gutters. |
| 1200–1439px | Full sidebar and standard desktop composition. |
| 992–1199px | Compact icon sidebar/rail; retain the full workspace and reduce dense summary/filter grids to three columns. The rail may be expanded with its toggle. |
| 768–991px | Reduced-column tablet layout: keep the full workflow available while trimming secondary table columns and simplifying dense summaries/forms; stack detail panes when needed. |
| <768px | Mobile essentials and primary workflows: one-column forms/content, compact two-up metrics, labelled record cards, wrapped controls, and touch-sized primary actions. |

On phones, keep the module's primary identifier, current state, key amount/date, and main action visible. Mark redundant metadata or lower-priority table columns with `.ui-mobile-secondary` (or `data-mobile-priority="secondary"`); the shared responsive layer hides only those explicit items. Do not mark required fields, validation, status/approval signals, or the main action as secondary. Use `.ui-mobile-cards` with `data-label` on cells when a non-master-list table should become a labelled card list; otherwise keep the table inside its horizontal scroll wrapper. This keeps the mobile screen focused without removing functionality or record data.

## Implementation rules

- Shared component styles belong in `resources/css/components/` and tokens belong in `resources/css/tokens/`; new screens should use the `core-*` API. Do not add another module-local copy of a generic card, table, tab, field, or typography rule. `public/assets/css/app-guidelines.css` remains a compatibility bridge for the four legacy modules and loads before the canonical design-system pass.
- Module styles may define grid columns, widths, responsive composition, and semantic states. They must use the shared tokens for surfaces and text.
- Preserve domain colors for statuses and credits/debits, but keep those colors local to the status element, icon, or border.
- `resources/css/master.css` is the canonical entry point: shared components, `pages/module-adapters.css`, then `layout/responsive.css` as the final pass. Admin/client-workspace shells and standalone shared-UI views load it through `layouts.partials.design-system-styles`; the partial uses Vite when available and a generated static fallback otherwise. Existing static page CSS remains a compatibility layer while screens are migrated; see `docs/css-architecture.md` and `docs/ui-component-inventory.md`.
