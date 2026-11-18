# Responsive design: one contract, every screen, 360px and up

How MissPack ERP behaves from a 4-inch phone to a 4K monitor, what each layer
does, and — most importantly — **what a new screen must do to keep it true**.
The last section is enforced in CI by `tools/checks/responsive-check.cjs`
(part of `npm run test:contracts`), so skim it even if you read nothing else.

---

## 1. Breakpoints — the project grid

| Width | Behaviour |
|---|---|
| ≥ 1440px | Full desktop, full sidebar, centred 1920px content column |
| 1200–1439px | Full sidebar, standard desktop spacing |
| 992–1199px | Compact 82px icon sidebar (admin) / icon rail (client portal) |
| 768–991px | **Tablet**: off-canvas drawer navigation, 4+ column layouts drop to 2, `ui-mobile-secondary` content is hidden |
| < 768px | **Phone**: drawer navigation, every standard grid stacks to 1 column, toolbars wrap, master-list rows become cards, touch targets ≥ 40px |
| ≤ 480px | Tiny phones: full-bleed modal sheets, remaining in-card grids stack |

These are the only breakpoints. A module must not invent its own (vendor
portal's 900px/650px is the one documented exception — it ships its own
self-contained shell).

## 2. The four layers, in load order

1. **Shell & components** — `core.css`, `app-layout.css`, `master-*.css` and
   the module sheets. Layout logic (sidebar ↔ icon rail ↔ drawer, hamburger,
   topbar) lives in `app-layout.css` + `assets/js/app-layout.js`.
2. **Module sheets** (`public/assets/css/<module>.css`) — page-local designs;
   each owns its own `@media` rules beside its own classes.
3. **Legacy guard** — `public/assets/css/responsive.css`: page-level "no
   horizontal scroll", table wrappers scroll, safe-area padding, touch
   targets, bottom-sheet modals.
4. **The contract** — `resources/css/layout/responsive.css`, built **last**
   into `design-system.css` via `npm run build:design-system` (or Vite).
   Scoped to `body[data-ui-shell]` ("app" and "portal") so public print pages
   are untouched. It maps every standard grid class to its per-breakpoint
   columns — this is the file to extend when a genuinely new grid class is
   born.

## 3. The five patterns (use these, nothing else)

1. **Wrap wide tables.** Every interactive `<table>` that can outgrow a phone
   sits in `<div class="master-table-wrap">` (or a module `*-table-wrap` whose
   stylesheet carries `overflow-x:auto`). The page itself never scrolls
   sideways; the table region does. Totals/footer rows of a `master-list`
   table (`.master-list-foot`) are exempt — at ≤767px the master-list sheet
   stacks them into labelled cards like every other row.
2. **Use the standard grid classes** (`master-grid-*`, `master-form-grid`,
   `cf-grid`, `cp-grid-*`, …). The contract already maps them: 4→3 at 1199,
   →2 at 991, →1 at 767. A new kind of grid belongs in the contract's maps,
   not in ad-hoc page CSS.
3. **Mark secondary content.** Add `ui-mobile-secondary` (or
   `data-mobile-priority="secondary"`) to columns/cells/rows that may vanish
   on small screens. Primary data and actions are never auto-hidden.
4. **Card-opt-in tables.** A table that should read as stacked record cards
   on phones (instead of sideways scrolling) gets `ui-mobile-cards` and
   `data-label` on each `<td>` — the contract restyles it at ≤767px. The
   standard `master-list` table does this automatically.
5. **Dialogs are sheets.** `master-modal` is a bottom sheet on phones and
   full-bleed at ≤480px automatically — never fight it with fixed widths.

## 4. Shells covered

- **Admin/office** (`layouts/app.blade.php`, `data-ui-shell="app"`) — sidebar:
  full → icon rail (992–1199) → off-canvas drawer (<992, hamburger persisted
  in `localStorage`).
- **Client portal** (`client_portal/layouts/app.blade.php`,
  `data-ui-shell="portal"`) — same rail/drawer idea with `cp-*` classes.
- **Vendor portal** (`vendor_portal/layout.blade.php`) — self-contained
  `vp-*` shell: fixed sidebar → off-canvas at ≤900px with its own
  `vp-menu` toggle; two breakpoints; excluded from the contract by design.
- **Auth & public pages** (login, two-factor challenge, lead/product/KYC/
  statement/tracking pages) — standalone, own stylesheets with their own
  media rules; paper documents (invoice/statement/voucher/payslip/label
  prints) are fixed A4/label width with `@media print`, intentionally not
  reflowed.

## 5. Adding a screen? This is the checklist CI enforces

`npm run test:contracts` → `responsive-check.cjs` fails the build when:

1. A standalone page/layout lacks `<meta name="viewport">`.
2. An interactive `<table>` has no scroll wrapper, or its wrapper class
   carries no `overflow-x` in any stylesheet. (Print/PDF/e-mail templates
   and `master-list-foot` replicas are exempt — matching the patterns above.)
3. An inline `style` hard-codes `width: ≥321px` (use a class or `max-width`).
4. An interactive module stylesheet has zero `@media` rules. Structural
   sheets are allow-listed *with a reason* inside the check
   (`STRUCTURAL_SHEETS`) — extend that list only with an equally concrete
   reason.
5. The contract is broken: `layout/responsive.css` must stay the **last**
   `@import` in `resources/css/master.css`, and the built
   `design-system.css` must contain it (run `npm run build:design-system`
   after touching `resources/css/**`).
6. No `@media (max-width: …≤900px)` block pins a layout floor past the phone:
   `min-width`/`width` ≥ 321px inside a mobile block fails the build. Wide
   tables are the standing exception (their wrapper scrolls, rule 2);
   anything else needs a named entry in `MOBILE_FLOOR_EXCEPTIONS` with its
   reason, like the six-column module trend chart (`640px` inside a
   `.module-trend-card` that the same block gives `overflow-x: auto`).

Beyond the check: keep tap targets ≥ 40px, never rely on hover for essential
actions, and try the page once in a 375px-wide window before calling it done.

## 5.1 The October pass — dispatch, packaging, receipts

Walking the three warehouse flows on the smallest phone found and fixed:

- **Dispatch form:** the card-ified line-items table kept a phantom
  `min-width: 346px` inside its ≤767px block — every 320px screen scrolled
  sideways. The floor is gone (cards are `width: 100%` + padding).
- **Shipment detail attachments:** a fixed 4-column grid squeezed photos to
  ~70px and remove-buttons under the 40px tap floor; ≤767px it is two
  columns with the image reading 4:3.
- **Delivery receipt entry:** the 9-column reconciliation table now opens as
  a full-screen sheet (`100dvh`, safe-area-aware footer) whose lines become
  labelled stacked cards — the same `data-label` pattern the shipment form
  already used. Seven thumb entries per line, no hunting inside a scroll well.

`test:contracts` rule 6 (above) pins all three shapes for every future
screen.

## 6. Test devices / quick sanity

Chrome DevTools presets cover the grid: iPhone SE (375×667) for ≤767,
iPad Mini (768×1024) for the tablet band, a 1024×768 window for the compact
icon rail, and 1440+ for full desktop. The highest-risk flows on phones —
dispatch & shipment forms, packaging list verification, delivery receipts,
invoice forms — are exactly the ones to open first after any shell change.
