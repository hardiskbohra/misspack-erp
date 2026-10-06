# Project CSS architecture

## Purpose

`resources/css/master.css` is the single source entry point for the shared ERP design system. The shared Blade partial loads it through Vite when a Vite manifest or dev server is available, and otherwise serves the generated `public/assets/css/design-system.css` fallback. Admin, client workspace, and standalone public/form screens that use shared UI components all use that partial. Print/PDF documents intentionally keep their print-specific stylesheets; the project-wide typography cap still applies to them.

The repo has a large, working set of static stylesheets under `public/assets/css/`. They remain as compatibility/page styles while screens are migrated, not as a second place for new shared components. Avoid a risky one-shot move of those existing assets: they are consumed directly by Blade, contain route-specific behavior, and some use relative asset URLs. The migration rule is to move/author new shared UI in `resources/css/` and move a page stylesheet into `resources/css/pages/` when that page is next substantially changed.

## Source tree

```text
resources/css/
├── master.css                    # Vite entry; owns import order
├── tokens/
│   ├── colors.css
│   ├── typography.css
│   ├── spacing.css
│   └── elevation.css
├── layout/
│   ├── app-shell.css
│   ├── sidebar.css
│   ├── topbar.css
│   ├── page.css
│   └── responsive.css               # global breakpoint and mobile-priority layer
├── components/
│   ├── buttons.css
│   ├── cards.css
│   ├── forms.css
│   ├── tables.css
│   ├── badges.css
│   ├── tabs.css
│   ├── modals.css
│   ├── drawers.css
│   ├── alerts.css
│   ├── dropdowns.css
│   ├── pagination.css
│   ├── timeline.css
│   ├── kanban.css
│   ├── attachments.css
│   └── activity.css
└── pages/
    ├── module-adapters.css       # bridge for existing module markup
    └── vendors.css               # vendor list, record and form composition
```

## Cascade and ownership

The entry-point order is fixed:

1. **Tokens** — color/surface, type scale, spacing/radii, elevation and z-index.
2. **Layout** — app shell, sidebar, topbar and page boundaries; no business-specific rules.
3. **Components** — reusable geometry, states, focus treatment, and theme surfaces.
4. **Page adapters** — narrow exceptions needed by existing page markup; never a second generic component definition.
5. **Responsive layer** — the final project-wide breakpoint rules, reduced-column layouts, mobile card treatment, and explicit secondary-content hiding.

New markup should use the existing `core-*` component contract first. `master-*`, `cf-*`, `cp-*`, `cpa-*`, and other legacy namespaces are adapters until their screens are migrated. The component CSS may support those aliases, but a module stylesheet must not redefine a generic button, card, field, table, tab, badge, or modal.

Page CSS may own domain composition (grid columns, ordering, widths) and states (for example shipment status, invoice balance, or approval state), using the shared breakpoint bands rather than redefining shell behavior. It must use shared tokens for color, surface, spacing, radius, and type. A page should not create an unscoped element rule or a new color token to fix one screen.

## Runtime and build

- `vite.config.js` registers both `resources/css/app.css` and `resources/css/master.css` as inputs.
- Admin, client-workspace, and standalone public/form views include `layouts.partials.design-system-styles` after their current compatibility assets so shared component rules are authoritative. `public/assets/css/app-guidelines.css` remains a legacy bridge for the four normalized modules; the admin layout loads it before the canonical responsive/design-system pass.
- The partial uses Vite when `public/hot` or `public/build/manifest.json` exists; otherwise it loads the checked-in fallback at `public/assets/css/design-system.css`. This keeps the UI styled in deployments and test environments that do not run the Vite build.
- `public/assets/js/master-list.js` owns the list behaviour that is the same everywhere: row navigation, the pinned-header shadow and the saved-view form toggle. Table geometry is not behaviour — one comfortable rhythm is declared in `resources/css/components/tables.css` and nothing reads a density or hides a column.
- `public/assets/js/master-drawer.js` is loaded by both admin and client-portal layouts and pairs with `<x-drawer>` for shared, keyboard-accessible quick details.
- Run `npm run build:design-system` after changing the source CSS to refresh the fallback. `npm run build` refreshes that fallback and produces the Vite manifest/bundle. Do not commit `public/build`; it is generated and ignored.
- `public/assets/css/master.css` is an unreferenced historical bundle, not an entry point. Do not link or add new rules there; `resources/css/master.css` is canonical.

## Component API and migration

- `core-*` is the default API for new markup. Keep behavior/accessibility attributes (`aria-expanded`, `aria-controls`, `aria-selected`, `aria-invalid`) in the view/JS layer and state visuals in the component stylesheet.
- Drawer triggers use `data-drawer-open`, `aria-controls`, and `aria-expanded`; the shared script traps focus, closes on Escape/backdrop/close controls, and restores focus to the opener. Bind record-specific text/links through `data-drawer-*` attributes rather than injecting HTML.
- Every table keeps the one **comfortable** row rhythm declared in `resources/css/components/tables.css` (`padding: 14px 16px` on `.core-table`, `.master-table`, `.master-items-table`, `.cf-table`, `.cpa-table`, `.cp-table`, `.cq-table`). There is no per-table height or column preference to opt into: a density switch and a column chooser used to live in the list toolbar and were removed ERP-wide, because a reader-local preference made two people look at two different tables. Tables preserve their mobile card/scroll treatment.
- Existing component aliases are listed in `docs/ui-component-inventory.md`; update the inventory when a shared component is added, promoted, or retired.
- `resources/css/pages/module-adapters.css` is a temporary bridge for the recent Shipments, Clients, Cashflows, and Users normalization. It is imported after shared components but before the global responsive pass, and should shrink as those views adopt core components.
- `resources/css/pages/vendors.css` is the vendor module's page sheet (list cells, status/type tones, record composition, payables/ageing, spend bars, activity trail, contacts, form sections). It is imported beside `module-adapters.css`, so it ships in the design-system bundle and the legacy `public/assets/css/vendors.css` that the vendor views used to link has been removed. Its rules stay scoped to `.vendor-index`, `.vendor-show` and `.vendor-form`, and it declares no generic button, card, field, table, tab, badge or modal: the record's cards are still `.master-card` with the module's own inner padding, and its lists are still `.master-table`.
- Money tables are the module's one exception to the shared row height: `.vendor-money-table` is a `.master-table` that takes the card's own width (`min-width: 0`, not the list's 1280 px floor) at 11×14 px rows, and its wrapper bleeds to the card's edges (`.vendor-table-bleed`) so the first and last cells carry the head's 20 px inset. A money row states each fact once — one `Amount` cell and one `Account` cell, with the second line in `.master-sub` — rather than two half-empty columns; `.vendor-late-chip` badges the days late and the status cell carries the cashflow link and the proof links. Below 768 px the tables become `.ui-mobile-cards` and the bleed, with its insets, is reset.
- A vendor record tab renders one `.master-tab-panel`; a tab that answers several questions (procurement, money) stacks its sections in `.vendor-blocks` (a 16 px column of cards) and each section is a `.vendor-block-card` — a `master-card` on the soft panel, per the guideline that cards inside a tab panel use `--mc-card`. Inside a block, content groups are flat and only tiles (ageing, product, currency, attachment) take the nested treatment — the same surface with a quieter border.
- The vendor form follows the client form's field rules: `.vendor-form-nav` is the same control bar, each `.vendor-form-section` is its own card inside the form card, and `.master-detail-grid` lays three fields across on a desk, two at 992–1199 px, and one on a phone.
- The vendor list's bulk bar wears the shared `.master-list-bulk` chrome and the record's bulk checkboxes reach their form through the `form` attribute, so the bar can sit above the table without a second styling rule. The record's tabs stay URL-addressable: `.vendor-jump` is an in-panel anchor strip, not a tab set, and each composite tab (procurement, money) is one `role="tabpanel"` holding several `.vendor-block` sections.
- Existing page styles still linked from `public/assets/css/` are the legacy page layer. When migrating one, update all Blade links to the resource entry/appropriate page source, check its relative image/font URLs, and remove the old duplicate only after the Vite build and page are verified.

## Quality gates

Before merging CSS changes:

1. Run `npm run build` (or at minimum `npm run build:design-system` when Vite dependencies are unavailable).
2. Check light and dark themes at 1440px+, 1200–1439px, 992–1199px, 768–991px, and below 768px.
3. Check keyboard focus and overflow for forms, tabs, tables, dialogs, and navigation.
4. Keep all declared/requested font weights at or below 700 (including shorthand, SVG, embedded and print styles).
5. Run `git diff --check` and update the component inventory if coverage changed.

This architecture is the structural baseline. The detailed UX guideline will define the user flows, content hierarchy, density decisions, and interaction rules for these components separately.
