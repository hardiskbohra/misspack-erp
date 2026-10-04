# The invoice-and-office narrative — the older parts

The pull request that carries this work (`#2`) holds the narrative task by task:
what was reported, what the cause was, what changed, and the gates it had to
pass. GitHub stops accepting a pull-request body past 256 KiB, and the narrative
passed it, so the older parts live here, in the repository, and the pull request
keeps the recent ones.

Parts 4 – 12ad, in order: the projects 500, the select2 filters, the audit batch,
mobile responsiveness, the shared modal theme, the collapsed sidebar, the vendor
payment's INR cashflow, the shipments waves, the flat card surfaces, the shipping
mark's three iterations, the shipments list at a glance, the row menu, the money
formatting, the one-list surface, the cashflow module's waves and every filter
fix up to the roadmap.

---

## Part 4 — Projects module 500 fix (shipments.project_id)

**Bug:** `SQLSTATE[42S22] Unknown column 'shipments.project_id'` on the projects module.

**Root cause:** migration `2026_07_17_140210_add_label_to_shipments_table` was broken — it re-added the already-existing `shipment_type` column and positioned `project_id`/`vendor_id` with `AFTER client_id` (a column that doesn't exist until the 07_20 migration). It failed on install, so `shipments.project_id` was never created — while `Project::shipments()` (FK `project_id`) is lazy-loaded from `projects/show` and `client_portal/projects/show`, causing the 500.

**Fix (`950f073`):**
- Rewrote the migration: idempotent `Schema::hasColumn` guards, no duplicate column, safe `AFTER shipment_type` position; `down()` no longer drops `shipment_type`.
- `ProjectController@show`: eager-loads `shipments` only when the column exists (same guarded pattern as `milestones`/`cashflowEntries`), falls back to an empty collection so the page renders on older DBs.
- `ClientPortalProjectController@show`: same guard + fallback.

**On your server:** after deploying, run `php artisan migrate`. If `2026_07_17_140210_add_label_to_shipments_table` is already listed in the `migrations` table (previously force-skipped), delete that row first and migrate again — the repaired migration is safe to re-run.
---

## Part 5 — Select2 filter layout fix (all modules)

**Bug:** every filter bar rendered each Select2 control as a full-width stacked row, so the filter card took the entire page. Root cause: Select2 4.1 wraps each select in a generated `.select2-container` span and does **not** copy the select's classes onto it — the container (the real flex item in `.master-filter-row`) lost the `.master-select { flex: 1 1 150px }` sizing and fell back to `width: 100% !important`.

**Fix (`32269a4`):**
- `master-selects.js`: `selectionCssClass: ':all:'` (classes copied to the rendered selection) + app-only classes mirrored onto the container span (Select2 internals excluded — `select2-hidden-accessible` carries `!important` clip rules that would hide the control) + `[hidden]` selects are left native/invisible (legacy hidden filters no longer leak into the UI; modal selects still initialise).
- `select2-theme.css`: neutraliser for `span.select2-container.master-select` strips the native-control geometry the class mirror would inherit (height/padding/border/radius/background) — the inner `.select2-selection` is again the only visible 44px control.

Verified with a jsdom harness against the real assets: container `flexBasis: 150px`, neutralised geometry, no internal-class leakage, hidden-select skip, modal decoration — all correct.

**Note:** assets have no cache-busting query — hard-refresh (Ctrl+Shift+R) after deploying.

## Part 6 — Full-project audit fixes (screenshot batch, 2026-10-01)

| # | Issue | Fix |
|---|-------|-----|
| a | Invoice items not inheriting input/select2 CSS (incl. new rows) | `sales-invoices.js` row template now emits `master-*` field classes; compact `.master-items-table` rules added in `sales-invoices.css` (matches `.master-product-select` sizing, 44px controls) |
| b | Invoice list — top space above dates | date cell markup rebuilt (no stray `<br>`/block spans); cell is now a simple flex column |
| c | Milestone update modal not scrollable | `.pmile-modal-card > form` made a flex column, `.pmile-modal-body` gets `flex:1 1 auto; min-height:0`, head/footer `flex-shrink:0` |
| d | Confirm button said "Delete" on Generate-Timeline | `MasterAlert` honours `data-confirm-text` + `data-confirm-danger` (`confirmDanger()`); Generate form now sets `data-confirm-text="Generate"` |
| e | Milestone view redesign | Rewritten to the stepper/card design (node + connector + card: title, description, badges, progress, planned/actual dates, owner, notes); **one shared partial** (`projects/partials/milestone-product-block.blade.php`) used by admin and client portal — portal is read-only (no edit button, owner name only when eager-loaded → no N+1, no admin-name leak); superseded gen-2 timeline CSS/markup deleted |
| f | Light/dark theme consistency | New `--mc-*` surface/text/border token set (light + dark) in `app-layout.css`; `--master-*` tokens re-mapped for dark in `master.css`; ~510 hard-coded light colours tokenized across **all** admin master-\* + module CSS (projects, sales-invoices, cashflows, lead-quotes, leads, clients, products, shipments, dashboard, tasks, users, vendors, price-calculator, core). Badge pastels intentionally stay in both themes. Login/public/portal/print pages untouched by design |
| g | Bell badge jumps on hover | `position:relative` added to `.topbar-btn` so the absolutely-positioned badge has a stable containing block |

Plus: removed stray double-quotes after `data-confirm` attributes in 17 views (confirm text was being truncated by MasterAlert).

> **Part 6 correction (606eae8):** the tokenization pass initially rewrote the `--mc-*` token *definitions* themselves (e.g. `--mc-card: var(--mc-card)`), creating var() self-reference cycles that voided every card background/border using the tokens. Fixed by restoring literal token values, plus: dark-mode text tokens (`--master-dark` and module `*-dark` tokens) now resolve to light text in dark mode, and six previously-undefined tokens (`--ship-*`, `--client-border`, price-calculator yellow) that were silently voiding their rules are now defined. Verified with a cascade resolver: 30/30 tokens resolve correctly in both themes, no cycles, no dangling `var()` refs.


## Part 7 — Full mobile & tablet responsiveness (6d0d472)

New central layer `public/assets/css/responsive.css`, loaded **last** (after `@stack('styles')`) in both the admin and client-portal layouts so it fills gaps across every module without restyling desktop:

| Width | Behaviour |
|-------|-----------|
| > 1199px | Desktop — persistent sidebar (unchanged) |
| 768–1199px (tablet) | Off-canvas sidebar drawer (existing app-layout CSS/JS: hamburger, overlay, Escape, link-click close, body scroll lock) + toolbar wrapping + **every `.master-table` scrolls horizontally inside its card** — this also fixes the 3 unwrapped tables (products/form, vendor_quotes form+show) that would overflow the page |
| ≤ 767px (mobile) | Compact table cells; kanban board column-snap scroll; tab strips (projects `pd-tabs-nav`, vendors `vendor-tabs`, dashboard `master-tabs`) scroll without wrapping; sticky topbar + page get safe-area (notch / home-indicator) padding; **touch targets raised to ≥ 40px** (topbar buttons, task-card ⋮ menu, modal close, icon buttons, toggles, logout, pagination, tabs); Select2 full-width with 44px touch height and viewport-clamped dropdowns |
| ≤ 480px (tiny phones) | Modals become full-bleed bottom sheets (body self-scrolls, footer clears the home indicator) |

Global rules: no text auto-scaling, images capped to their container, no tap-highlight flash, pagination wraps.

Audit basis: full `@media` inventory across all 37 stylesheets + view markup checks (table wraps, tab containers, pagination, kanban, modals, drawers). Module stylesheets (master-*, dashboard, tasks, projects, sales-invoices, cashflows, leads, clients, vendors, users, public/login pages) already carried their own grids/MQs; this layer covers only the confirmed gaps. Preview: `/preview/index.html` + `/preview/form.html` (resize the window or use devtools device mode).


## Part 8 — Milestone editor aligned to the shared modal theme (4ae83de)

The **Edit Milestone** dialog was the last modal in the app still built on a one-off class set (`.pmile-modal*`) — its own backdrop, radius (24px), close button, padding scale and surface token — so it did not match the dialogs used everywhere else (and its surface came from a bare `var()` that renders transparent whenever the token is unavailable).

It is now built on the **same shared component as every other module**:

- **Markup**: `.master-modal` / `.master-modal-card` / `-header` / `-body` / `-footer`, `.master-modal-close`, `data-close-modal` — identical vocabulary to tasks, clients, vendors, shipments, products, leads and the project page's own Add/Edit Product modals.
- **Behaviour**: open/close goes through `window.MasterModal` (app-layout.js), so backdrop click, Escape, the × button and the body scroll-lock behave exactly like every other dialog.
- **Delete**: now the standard themed confirmation modal (440px card, 🗑 header, `master-btn-danger` submit) instead of an inline link under the footer — the same pattern as tasks/clients/vendors delete.
- **Sizing**: editor card widened to 880px via CSS scoped to `#editMilestoneModal`; phones keep the shared bottom-sheet (`≤767px`) and full-bleed (`≤480px`) rules from `responsive.css`.
- **Cleanup**: removed the dead `.pmile-modal*` CSS from `projects.css` and the unused copy in `client-portal-project-show.css` (the portal milestones tab is read-only — it never renders the editor). Net −170 lines of duplicated modal CSS.

Verified in the review deck (`/preview/project-milestones.html`): modal opens from ✎ with prefilled values, closes via ×/Cancel/Escape/backdrop, locks body scroll, and hands off to the delete confirmation; 47/47 DOM checks pass.


## Part 9 — Collapsed sidebar keeps its menu names (bb86f63)

Collapsing the desktop sidebar to its 82px mini width used to hide every label (`opacity: 0; visibility: hidden; width: 0`), leaving bare icons with no way to tell what they are.

Collapsed mode now renders each nav item as a compact stacked tile — **icon on top, name underneath**:

- label at 10px / 600 weight, centred, clamped to two lines with an ellipsis so longer names (e.g. "Vendor Quotes") stay tidy
- items grow to a 58px minimum height; the nav keeps scrolling normally
- no `!important` needed — the label is re-shown by a more specific selector than the rule that hides it

**Scope**: wrapped in `@media (min-width: 1200px)`, so the ≤1199px off-canvas drawer (which shows full side-by-side rows) and the expanded desktop sidebar are both untouched. The client portal sidebar has no collapse mode, so it is unaffected.


## Part 10 — One vendor payment entry keeps the INR cashflow in sync (5b28f08)

**Problem.** A vendor payment had to be typed twice: once in *Vendor → Payments* (vendor currency, e.g. RMB/USD) and again in *Cashflow* (INR). The two copies drifted the moment anything changed — edit the vendor amount and the cashflow copy was stale, delete one and the other survived — and the link was opt-in (an unchecked box by default), so most payments never reached the cashflow ledger at all.

**Rule now:** the vendor payment entry is the **source of truth**; its INR cashflow entry is a **mirror** maintained automatically.

| Action | Before | Now |
|---|---|---|
| Add a payment (debit) | Cashflow entry only if a checkbox was ticked (off by default) | INR cashflow entry created automatically (box on by default) |
| Add a bill (credit) | Checkbox could create a *credit* cashflow entry — a payable is not cash movement | No cashflow entry; the control unticks itself and explains why |
| Edit the payment | Cashflow copy left stale — had to be edited by hand | Amount, date, reference, account, mode, notes re-synced (manual `reconciled` / category work preserved) |
| Delete the payment | Orphan INR entry left in the cashflow module | Mirror deleted with the payment |
| Delete the cashflow entry | Vendor row kept a dangling "Cashflow #id" | Vendor payment is unlinked, with a message saying so |
| Missing account / INR value | Mirror silently skipped | Clear validation message instead of a silent no-op |

**Visibility, both directions**
- Vendor ledger rows show **↔ Cashflow #id** as a link to the entry.
- Cashflow list rows carry a **↔ Vendor payment** chip linking back to the vendor's Payments tab (one batch query per page, no N+1).
- Cashflow detail and edit screens explain the link — vendor name, original currency amount and rate — and where to edit it.

**Scalable pieces**
- `App\Services\VendorPaymentCashflowSync` — all mirror logic in one place (create / update / remove / detach / batch lookup), so future modules (project payments, expenses) reuse it instead of re-implementing.
- `App\Services\CashflowLedger` — the running-balance recalculation extracted out of `CashflowController`; every writer of cashflow entries now updates the ledger identically.
- `vendors.js` drives the control (follows credit/debit, preserves a deliberate opt-out, marks the account required for payments, derives the edit state from the real link); vendor tabs honour `#payments` so cross-module links land on the right panel.

**No schema change** — `vendor_payment_entries.cashflow_entry_id` and `cashflow_entries.vendor_id` already existed, so there is nothing to migrate on deploy.

**Verified:** 4/4 PHP files parse, 4/4 Blade templates compile, 19/19 new DOM behaviour checks, 14/14 new preview-page checks, 47/47 existing deck checks. Existing payments created before this change stay untouched and can be mirrored by opening and saving them. Preview: `/preview/vendor-payments.html` (linked from the hub).

## Part 11 — Shipments: open-first order, printable shipping marks, From/To memory, spend totals (d533db9)

**Ordering** — the shipment list now leads with what is still moving. Open statuses (booked, picked up, in transit, out for delivery, custom hold, delayed) come first; delivered and cancelled drop into a labelled *Closed — delivered / cancelled* block. Inside each group the newest pickup date wins, then the newest record, so the second-level sort is date as asked. The rule lives in `Shipment::scopePriorityOrder()` / `closedStatuses()` / `isClosed()`, so any future shipment list (project tab, vendor tab) can reuse it instead of re-writing the ORDER BY.

**Total spent, as per the shown entries** — the index gains a *Spent (filtered)* card that follows every active filter (search, status, type, currency, pickup-date range) and a footer row totalling the rows on screen. Both are **per currency** — shipments bill in INR / USD / RMB, so the numbers are listed side by side and never added into one meaningless figure. `Shipment::formatAmount()` / `formatTotals()` keep list, totals and stickers on one formatting rule.

**Shipping mark stickers** — new printable sheet (`/shipments/{id}/shipping-mark`, plus a *Shipping Mark / Stickers* entry in every row menu and a button on the shipment page). Each sticker carries From and To blocks (name, address, city/state/country/pincode, phone, email), package *n of N*, gross weight, tracking number, courier, destination, label chip and the shipment reference. The sheet prints **8 stickers per A4 page**; the sticker count defaults to the recorded package count and can be set 1–48 from the toolbar before printing — so a 30-carton consignment is cut-and-paste ready.

**From / To memory** — party names already shipped are offered as suggestions, and picking one fills only the fields the operator has not touched yet (never overwriting a typed value). A note says where the values came from — "Prefilled 6 fields from SHP-000012 (1 already filled) — undo" — and undo restores exactly those fields. Both sides work independently; new parties are simply left alone. Matching lives in `App\Services\ShipmentPartyDirectory` (case-insensitive, trimmed name match, and every field is taken from the newest shipment that actually has a value, so a half-filled newer record can never blank a known address). One endpoint (`GET /shipments/party-lookup`) serves both the datalist and the fill.

**Also** — collapsed the duplicated `shipments.history.store` route definition found in the audit.

**Verified:** PHP parse clean (model, service, controller, routes), Blade compiles (index, form, show, shipping-mark), 50/50 new preview-page DOM checks, 19/19 From/To prefill checks, 47/47 existing deck checks, 19/19 vendor-sync checks, 14/14 page checks. Preview: `/preview/shipments-index.html` · `/preview/shipping-mark.html` · `/preview/shipments-form.html` (all linked from the hub).

**Interpretation note:** "total amount spent as per the shown entries" is implemented as the running total of the listed shipment entries (filtered + current page). If a per-shipment roll-up of its own cost entries was meant instead, that is a small addition on the shipment page — say the word.

## Part 12 — Shipments Wave 1 & 2: control tower, document vault, print pack, QR stickers, freight money (e04cf1a)

Wave 1 is **seeing and proving**; Wave 2 is **paying for it** — both built on the patterns already in the project (master-* UI, the cashflow mirror service from Part 10, the print-page fallback from the cashflow reports).

**Control tower (list)** — `eta_date` + `delay_reason` on shipments, a **due in X days / X days overdue / due today** chip on every row, and a chip bar with live counts: All · Needs attention · Overdue ETA · Arriving ≤ 7 days · Hold / delayed · Docs pending · **E-way expiring**. Counts respect the active search/filters, and any filter combination can be saved as a personal or shared view ("☆ Save this view"). The existing dashboard delay/custom-hold alert hook keeps working off the same columns.

**Document vault** — `shipment_attachments` gains a real `document_type` (BL/AWB, commercial invoice, packing list, shipping bill, bill of entry, certificate of origin, insurance, e-way bill, CHA checklist, POD …). `App\Services\ShipmentDocuments` decides what a shipment actually needs (export: packing list + invoice + shipping bill; import: + BOE; domestic: challan + POD) and returns a ✔/✗ checklist plus `missing` / `complete`. Uploads are type-tagged from one select, and marking a shipment **Delivered with missing mandatory paper shows a warning toast that names the documents**.

**Status rules** — a shipment cannot be marked **Delivered** without a drop date, and cannot move to **Custom hold / Delayed** without a remark explaining it. Enforced in the controller so both the form and the tracking-update path obey the same rule.

**E-way bill** — number + validity date, with a **3-day expiry warning** on the detail page and the list, feeding the same attention filters (`Shipment::ewayState()` / `ewayLabel()`).

**Print pack** — packing list, delivery challan and shipment summary built from the real item lines (SKU, HS code, qty, net/gross weight, declared value per item with per-currency totals), From/To blocks, totals row, signature lines, notes. Same pattern as the cashflow reports: if `barryvdh/laravel-dompdf` is installed the buttons download a PDF, otherwise they open the clean print view (browser → Save as PDF) with a one-line note.

**QR on the sticker — with no dependency** — `public/assets/js/qr.js` is a self-contained QR encoder (byte mode, versions 1–10, ECC M with an L fallback, Reed-Solomon over GF(256), all eight masks scored by the spec penalties). It draws an inline SVG, so the code is generated on the device: no image service, no CDN, no composer package. Each sticker's code opens the shipment's **public tracking page** (or the record itself for staff when a shipment has no share token). A new **whole-open-board sticker sheet** (`/shipments/stickers`) prints every open shipment at 1–8 copies each — the "print the board" button warehouses actually use. Verified end-to-end by decoding the rendered SVG with a reference QR decoder (7 payload lengths from 10 to 237 bytes, all versions 1–10).

**Client communication** — a *Notify client* tick-box on the tracking-update form (and on status changes) sends the existing portal notification, and a **six-step tracking stepper** (Booked → Picked up → In transit → Customs clearance → Out for delivery → Delivered) appears on the admin detail page, the client portal and the public tracking page. "Delayed" is not a stage of its own — it maps onto whichever stage the tracking history proves. The public page also gains expected delivery, the current hold-up, a **WhatsApp share** link and copy-link button.

**Wave 2 — freight money.** `shipment_costs` holds the breakdown: freight, insurance, customs duty, CHA / clearing, last-mile, demurrage and other, each with its own currency, exchange rate and an INR snapshot (`ShipmentCostLedger` recalculates it, `Shipment::costSummary()` / `costPerKg()` roll it up). Paid heads (a paid account + an INR amount) post into `cashflow_entries` **through the same `CashflowLedger`** the vendor payments use — the shipment row shows **↔ IN-000482**, and the cashflow row shows **↔ Shipment cost** back to the shipment. The shipment can be linked to a sales invoice to show **landed cost, invoice value, margin and margin %** per consignment.

**Verified:** PHP parse clean (model, services, controller, routes, 5 migrations), Blade compiles (index, form, show, public, stickers, shipping-mark, tracker partial, sticker partial, cost/documents partials, print pack, portal), 99/99 new Wave 1 & 2 DOM checks (including QR decode), 17/17 QR encoder checks, 50/50 Part-11 preview checks, 19/19 prefill checks, 19/19 vendor-sync checks, 14/14 page checks, 47/47 deck checks. Previews (hub → shipments): `/preview/shipment-detail.html` · `shipments-index.html` · `shipment-print.html` · `shipment-challan.html` · `shipment-summary.html` · `shipping-mark.html` · `shipment-stickers.html` · `shipment-track.html` · `shipments-form.html`.

**Not built (Wave 3, deliberately):** multi-leg / container fields (vessel, AWB/BL, transshipment port), the logistics KPI report, carrier tracking APIs (DHL/FedEx/BlueDart/Maersk), WhatsApp Business API, Tally export, carrier rate cards.

## Part 12a — Flat card surfaces, correct in light and dark (91b57f9)

The shipment screens painted their panels instead of letting the theme surface show through — pastel KPI cards, a tinted grey band separating the open/closed groups, chips on a soft grey fill, and a few detail rows whose pale opaque wash sat under text that follows the theme (unreadable in dark mode).

- **New `public/assets/css/master-flat.css`**, loaded straight after the master sheets in both the admin and the client-portal layouts. `.master-card--flat` makes a panel use `--mc-card` — solid white in the light theme, the deep panel colour in dark — and `.master-stat--flat` moves the colour onto the icon badge. One rule set, both themes, no per-theme override; any module can opt in.
- **Shipment list:** both cards and all five KPI cards are flat; the closed-group divider and the totals row follow the card; chips sit on the card surface with the count pill on the soft token; the active chip and the tracker use the accent tokens instead of hard-coded blues; the cancelled badge uses `--mc-soft`.
- **Shipment detail:** every section card (overview, tracker, costs, documents, route, products, photos, history) plus the costs/documents partials, info tiles, document rows and cost totals.
- **Dark-theme readability:** document rows, the prefilled-field highlight and the tracker's done step moved from pale opaque washes to translucent tints, so the text keeps the theme's colour; four references to the non-existent `--mc-text-1` token now use `--mc-text`.
- **Nothing semantic was flattened** — ETA chips, e-way warnings, status pills and label chips keep their colours.

**Verified:** 39/39 CSS-token & markup checks (both themes, cascade order asserted, no hard-coded white left in the module sheets), 53/53 deck behaviour checks run in **both** themes, 66/66 Blade structural checks. Preview: `/preview/shipments-index.html` (and `?theme=dark`), `/preview/shipment-detail.html` (and `?theme=dark`).


### Part 12b — white filter card & table in the light theme (b2fe608)

The cards themselves were on the card token, but the surfaces inside them were not: the list-table **header band** was painted with the soft surface (`#f8fbff` in the light theme), which is what made the table read as tinted next to a white card.

- New **`--mc-table-head`** token — white in the light theme, the soft panel in dark, so the header band still separates from the rows there. Applied to `.master-table th` app-wide, not just the shipment screen.
- `.master-card--flat` now also puts the **filter row, table wrapper, inputs/selects and the chip count pill** on the card surface, so a flat card is white right through in the light theme. Table rows are deliberately left transparent so module row colours (closed shipments, selected rows) still win.
- **Cache-busting:** `/public` assets have no build step, so a reload kept using the cached CSS and a change could look like it never applied. `AppServiceProvider` now shares an `$assetVer` helper (`?v=<file mtime>`), used by the admin layout, the portal layout and all shipment views — a plain refresh is enough from now on, and other modules can adopt it with one line.


### Part 12c — the card and its fields are white (4a806d5)

Root cause of "the card and the field background are the same, it looks odd": the theme tokens were declared **only** under `:root[data-theme="light"]` / `:root[data-theme="dark"]`. When that attribute is empty — Laravel renders `data-theme=""` while the session has no theme yet — or missing entirely (the **client-portal layout never sets it**), every `var(--mc-card)` resolved to *nothing*: the card **and** the inputs became transparent and showed the page behind them, while the Select2 fields stayed white because `select2-theme.css` hard-codes `#fff`. That is exactly the mismatched look.

- **`app-layout.css`** — the light token block is now declared on a bare `:root` too, so the tokens always resolve and light is the default; `[data-theme="dark"]` still wins on specificity. The portal (no `data-theme` attribute at all) benefits too.
- **`master-flat.css`** — `.master-card` / `.master-section` and `.master-input` / `.master-select` / `.master-textarea` fall back to a literal `#ffffff`, so a page without a resolvable theme attribute can never render a see-through card or field.
- **`shipments/form.blade.php`** — the header, form and tracking-history cards carry `master-card--flat`, so create/edit reads white right through, with fields outlined like the Select2 boxes.

**Verified:** 66/66 CSS-token & markup checks (form page + "no theme attribute" fallbacks), 53/53 deck behaviour checks, 55/55 Blade structural checks, PHP parse clean.


### Part 12d — missing `shipments.shipment_label` column (041b0ee)

Saving a shipment threw **SQLSTATE 42S22 / 1054: Unknown column 'shipment_label' in 'field list'**. No migration ever created that column: migration `2026_07_17_140210` is named *add_label_to_shipments_table*, but its `up()` re-added the already-existing `shipment_type` column instead of the label — so the migration failed on install and nothing in it was applied. The later repairs (guarded `project_id` / `vendor_id`) fixed the Projects 500 but never restored the label.

- **New `2026_10_02_000000_add_shipment_label_to_shipments_table.php`** — idempotent `hasColumn` guard, nullable string after `shipment_type`, reversible `down()`. A separate file on purpose: databases that already recorded the 2026_07_17 migration never re-read it, so editing that file could not repair them.
- **`shipments/form.blade.php`** — the label input repopulated from `old('identity_name')` instead of `old('shipment_label')`, so a failed validation copied the identity name into the label box. Fixed.

**Deploy step:** `php artisan migrate` (`--force` in production) — also applies the Wave 1 & 2 migrations if they have not been run yet.

**Standing audit:** every model's `$fillable` was cross-checked against every migration; `shipment_label` was the only column written by code that no migration creates.


### Part 12e — buttons restored to the theme style (25e7f89)

The flat-card sheet carried a guard that set `background-color: initial` on `.master-btn` / `.master-badge` / `.master-label-chip` / `.tooltip-text` inside a flat card, meant to stop the new panel rules from touching them. It was unnecessary (nothing in that sheet paints those elements) and destructive: `initial` means **transparent**, so every button inside a flat card lost its fill while keeping its `box-shadow` — a primary button (white text on the theme's `#ef4770` fill with its pink glow) rendered as an **empty white pill with only the glow left**. That is exactly what the three screenshots showed: the list chip bar (Filter, + Quick Shipment, Save this view), the detail page (+ Add Cost Head, print pack), and the form (Save, upload rows).

- **Guard removed** — buttons, badges, label chips and tooltips keep the exact theme styling they have everywhere else in the app (primary = the theme's filled button, secondary = soft).
- **File inputs** now follow the theme too: `::file-selector-button` / `::-webkit-file-upload-button` in `master-form.css` give the browser-drawn picker the soft fill, primary-coloured label and the same radius/height as the fields beside it (the "Choose files" control no longer looks foreign).
- The shipment **upload row can shrink** (`min-width: 0`), so its submit button is no longer pushed out of the card.

**Verified:** 74/74 CSS-token & markup checks (now including "no flat rule blanks a button fill" and "the winning `.master-btn-primary` declaration is still the theme fill"), 53/53 deck behaviour checks, 8/8 schema-drift checks, 44/44 Blade checks.


### Part 12f — "+ Add cost" now works (8322bb4)

The cost breakdown's **+ Add cost** button, the edit/remove buttons on each cost row and the delete-confirm flow are all wired by `shipments.js` — but only the **list** and the **form** pushed that script. On the **detail** page the script was never loaded, so `initCostModal()` never ran and the button did nothing: no error, the click simply had no listener.

- `shipments/show.blade.php` now pushes `assets/js/shipments.js` via `@push('scripts')` (versioned through `$assetVer`), like the list and form.

**Verified by clicking the button** in a DOM carrying the real modal markup and the real script — 24/24 checks: modal opens, scroll lock applies, close button and Escape close it, edit prefill works (`_method` → PUT, action → that row's cost id, amount/head/currency loaded, exchange-rate field revealed for a USD row), and re-opening resets to add mode.

**New standing guard:** a static contract check now fails whenever a view owns a JS hook but doesn't load its script, when a `getElementById` hook is missing from the module's views, or when a bound class hook exists nowhere in the app (dead code).


### Part 12g — modals fit on screen, and `hidden` means hidden (631631e)

Reported as *"modals are shown like the screenshot, make it appropriate"*. Four separate causes:

1. **The `hidden` attribute did nothing.** The user-agent rule for `[hidden]` loses to *any* author rule that sets `display` — and `.master-field { display: flex }` is exactly that, so the cost modal's two conditional fields (**Describe it** for an unknown head, **Exchange rate** for a non-INR bill) stayed on screen even though the script had hidden them. `core.css` now carries the canonical `[hidden] { display: none !important }` guard; the one-off workaround on `.ship-save-view` folds into it. Eight places in the app toggled `hidden` on a class that sets `display`.
2. **The card was not a flex column**, so nothing could scroll but the card: the body had a frozen `max-height: calc(100vh - 190px)` — a guess at the header + footer height — while the card clipped at `100vh - 40px`, which is why the last field sat under the action row. The card is now fixed header / scrolling body (`min-height: 0`) / fixed footer, capped in `dvh` so a short viewport scrolls the overlay instead of losing the top.
3. **25 of the 29 modals wrap those three in a `<form>`**, and a form is a block box — the flex properties never reached them. The wrapper form is a column too, and grows only when it holds the body, so a footer-only wrapper (delete dialogs) stays content-sized.
4. **The rhythm was doubled.** `.master-label` carries its own 10px top/bottom margins on top of the field's 7px flex gap — about 120px of dead height across the cost modal's rows, which is what turned it into a scroll box. Modals now use the field gap alone (scoped, so ordinary forms keep theirs).

Also in the same shared vocabulary:

- `.master-modal-card.small`, `.master-modal-head` and `.master-modal-backdrop` are first-class in `master-index.css`.
- `tasks.css` no longer restates the entire chrome (it shipped its own overlay/card/head/body/footer and `display: block` on `.open`, which defeated the flex layout). Phone behaviour (stacked grid, full-width actions) moved to `responsive.css` for every modal.
- Labels and controls use the theme text token instead of a hard `#111111`, and `--master-muted` is declared in the dark theme block: labels were near-black on dark panels. The legacy `--dark` name the admin layout never defines is gone.

**Verification:** new modal harness (41 checks) reads the real stylesheets in load order, resolves the winning declarations and does the box arithmetic for five viewport heights — the form fits without an inner scrollbar from 900px up and never exceeds the viewport. A markup contract walks all 29 modal cards and fails if one buries its body below the card or restates the chrome in a module sheet. Click-through stays green at 30/30, including the conditional fields appearing/disappearing with head and currency.


### Part 12h — Shipment record page redesigned (0942d26)

The module worked; the page didn't read like a *record*. Five causes:

1. **Every field was a box.** Thirteen bordered, tinted boxes meant nothing on the card was a value — everything was a container. Values are now a **facts grid**: label over value, hairline separated, no fill. The form sheet's decorative rule (`SHIPMENT OVERVIEW ————————`) was leaking into every card because `master-form.css` loads after `master-show.css`; card titles are plain 15px titles now, and the rule survives only inside long form cards as a quiet hairline.
2. **Unknown values were dashes.** `<x-fact>` renders a muted "Not set" instead, and header chips render only when they have something to say — including the empty `LABEL` badge (a colour dot with no text, because `labelColorClass()` returns `label-0` for an empty label).
3. **Too many primaries, too much red.** Seven equal-weight header buttons became one primary (Edit), a grouped print cluster, and a soft Public Tracking. Cost row actions are Edit/Remove **outlines**, and money columns are right-aligned with tabular figures.
4. **Six boxes for six steps.** The stepper is now a connector line with small markers — done steps tinted, current step filled, rest quiet — and a vertical list on phones.
5. **Colour that only worked in one theme.** ETA/eway pastels were solid light-theme values, product cards hard-coded `#1f2937`, and the timeline assumed a white page. Tints are translucent now and every semantic hue has a dark counterpart.

**Structure**

- New shared sheet `master-detail.css` (after `master-form.css`, before `master-flat.css`) owns the record-view primitives: card headers, facts grid, chips, empty states, numeric cells, ghost row actions, timeline, record header. Module sheets add context, never restate it.
- New `<x-fact>` component: a label/value pair is one line of Blade, so any record page can adopt the same pattern.
- Side rail regrouped: Share Tracking, Add Tracking Update (**visible labels for every field** — placeholders are not labels), Paperwork Checklist as a hairline list, Photos and Tracking History with real empty states.
- 12 cost-modal labels are now linked with `for=`; they were labelled only visually before.

**Verification**

- `ui-check.js` — 44 checks: stylesheet wiring/order, then the cascade **resolved against the real load order** (a rule that lands before the one it replaces fails), plus the design rules (one primary per card, no bare dashes, numeric alignment, labelled controls, empty states, dark pairs). Includes a guard for a bug caught mid-work: an unscoped `.master-facts strong` rule would have flattened every status badge, so the value rule is scoped with `:not([class])`.
- `hooks-diff.js` — **0 removals** across ids, names, `data-*`, `onclick` and `route()` calls in the three rewritten views.
- `sanity.js` — Blade directives balance, PHP parses clean (39 files), JS hook contract holds, script-loading per view intact.

Scope note: `master-detail.css` + `<x-fact>` are shared, so the same treatment can be adopted on the other record pages (clients, vendors, projects, invoices, cashflows) module by module without touching this one.


### Part 12i — Overlap + upload box fixed, shipping mark rebuilt for A5 (e155f43)

**Two layout bugs, each with a specific cause**

1. **Overlapping text on Paperwork Checklist.** The hint under a card title carried a `-10px` top margin — written for a title that keeps its own bottom margin, but inside `.master-section-head` the title's bottom margin is `0`, so the negative margin pulled the hint *up over* the heading. It's a small positive margin now, and a new guard fails any negative margin on a title/hint pair.
2. **The document upload box was mostly empty space.** The old upload row was a flex *row* whose controls carried `flex: 1 1 240px`. Once the fields moved into a column flex, a length flex-basis stops being a width and becomes the **main size** — a 240px-tall file input. Controls are sized by height now, the native picker is vertically centred in the 44px field, and a new guard fails any length flex-basis on a control inside a column flex.

**Shipping mark, rebuilt for A5 landscape**

- **Geometry:** A5 landscape, 6 mm margins, 2 × 2 stickers of 99 × 68 mm. Every size is in millimetres and the add-up is asserted, so the mark can't silently overflow the cell. The party block is the one flexible row (rows centred, overflow clipped); header, meta strip and footer are fixed; long addresses clamp at three lines.
- **Branding:** wordmark (`images/logo-dark.png`) in the header, website + “MissPack · Packed Perfect” in the footer, all read from a new `config/brand.php` — an install changes its identity in one place.
- **Both codes on the sticker:** the tracking QR (12 mm, white plate, real 3-module quiet zone) **and** a new Instagram QR to `https://www.instagram.com/themisspack/` with the `@themisspack` caption. Generated locally by `assets/js/qr.js` — no external service.
- **Removed** Logistic Partner and Tracking from the sticker, plus the dead `.mark-destination` / `.mark-ref` / `.mark-cut` rules. Destination moved into the meta strip, now a single-ruled band (the meta and footer rules sat 1 mm apart and read as a double line).
- Toolbar aligns to the printable sheet width; the copies hint says 4 per A5 page.

**Verification**

- `mark-check.js` (28 checks): A5 geometry summed from the stylesheet, head budget from its tallest child, footer/party budgets, and a **real decode** of every payload the mark prints (tracking, public token, https, Instagram) using the app's own encoder.
- `decode-proof.js` (4 checks): renders the sheet at 300 dpi and decodes **both QR codes out of the raster**, then samples the plate for white — the codes are proven *printable*, not merely encodable.
- Proof image: `public/_preview/sticker-140x200-proof.png` (untracked), real wordmark, real geometry, print-ready.
- `ui-check` 47/0, `sanity` 8/0, `hooks-diff` 0 removals, all 44 stylesheets balanced.

**One judgement call to review:** at 12 mm the tracking code prints at 0.31 mm per module — within spec but with little tolerance for a worn printer. 14 mm still fits the cell comfortably if you'd prefer the margin.

---

## Part 12j — the mark is now laid out for 14 × 20 cm paper

You said the office prints on **14 × 20 cm**, so the mark is no longer A5 landscape.

### The sheet

| | before | now |
| --- | --- | --- |
| page | A5 landscape, named size | **140 × 200 mm portrait, declared in absolute millimetres** |
| printable area (6 mm margins) | 198 × 136 mm | **128 × 188 mm** |
| stickers per page | 4 (2 × 2 of 99 × 68 mm) | **3 (one column of 128 × 62.3 mm)** |
| toolbar says | "4 per A5 page (2 × 2)" | **"3 per page (140 × 200 mm)"** |

- **`@page { size: 140mm 200mm; margin: 6mm }`** — declared in millimetres rather than as a named size, because a printer driver with no 140 × 200 preset would otherwise fall back to A4 and rescale the whole sheet.
- **One full-width column.** Two columns on 140 mm would leave each party about 28 mm of address width — enough for a name and nothing else. Full width gives the From/To columns ~57 mm each.
- **62.3 mm per row, not 62.6.** 3 × 62.6 = 187.8 mm of the 188 mm printable height leaves 0.76 px of slack; one rounding difference in the page box and the third sticker starts a second page. 62.3 mm leaves ~1 mm, and the check asserts that floor.
- **The tracking QR grows to 13 mm** now that the footer owns the whole sheet width — 0.33 mm per module, and a 13 mm code is comfortable for a phone at arm's length. The Instagram code stays 11 mm.
- **0.8 mm of clearance above the meta rule.** The parties block is a centred grid, so on a long address the last contact line could end up against the hairline — on paper that reads as a print collision. Bottom-only padding, since the head rule already separates the top.

The header keeps the wordmark, title and shipment chip; the meta strip keeps package count, gross weight and destination; the footer keeps the website, tagline and both codes. `perPage` is 3 in the single-shipment action *and* the bulk sheet action.

### Verification

Re-runnable gates now live in the repo as **`tools/checks/`** — see its README for what each one guards and why:

```bash
node tools/checks/design-check.cjs    # 35 checks — cascade + record-page design rules
node tools/checks/blade-check.cjs     #  4 checks — 88 templates: directives, includes, components
node tools/checks/mark-check.cjs      # 24 checks — mark geometry + QR payload decodes
```

| gate | result |
| --- | --- |
| `mark-check` | **24 / 0** — page 140 × 200, sheet 128, cell 128 × 62.3, 3 rows, ≥ 1 mm slack, row budget 55.2 mm of 56.3 mm, both QR payloads encode **and decode** (jsqr), tracking code 0.33 mm/module |
| `design-check` | **35 / 0** — both layouts load `master-detail.css` in the right slot, the record-page rules actually win the cascade, one primary action per card, labelled controls, dark-theme pairs, plus the two layout-hazard guards (a length `flex-basis` on a control in a column flex; a negative margin on a title/hint pair) |
| `blade-check` | **4 / 0** — all 88 templates have balanced directives, every `@include` and `<x-component>` resolves, every `@push` targets a real stack |
| brace balance | all 44 stylesheets balanced |
| proof render | 300 dpi sheet, **10 / 0** — both codes decode *out of the raster* on **all three** stickers, plate sampled white, third sticker lands inside 200 mm |

The proof image is regenerated for the new paper: three stickers on a portrait 140 × 200 mm sheet at `public/_preview/sticker-140x200-proof.png` (untracked — it is a dev artifact, not a shipped asset).

**One judgement call to review:** three stickers of 62.3 mm is what fits the 188 mm printable height exactly; if the office trims the sheets or the printer's unprintable margin is wider than 6 mm, tell me the real margins and I will re-cut the geometry from them.

---

## Part 12k — the mark is now the label itself: 85 × 130 mm

You gave the exact numbers, so the sticker *is* the label: **85 mm wide, 130 mm tall, one sticker per label**. That supersedes the three-to-a-sheet layout from 12j.

| | 12j | now |
| --- | --- | --- |
| page | 140 × 200 mm with 6 mm margins | **85 × 130 mm, no page margin** |
| sticker | 128 × 62.3 mm, three per sheet | **85 × 129.5 mm, one per label** |
| parties | two columns, side by side | **stacked — shipper above receiver** |
| toolbar says | "3 per page (140 × 200 mm)" | **"1 per label (85 × 130 mm)"** |
| codes | 13 mm / 11 mm | **17 mm / 14 mm** |

**Why it is laid out this way**

- **129.5 mm of the 130 mm height, not 130.** A box that matches the page exactly can round a fraction over and make the print engine emit a blank label after every copy. The half millimetre is invisible on paper.
- **The mark carries its own 4 mm inset** (`@page` margin is 0, so there is nowhere else for it to come from). That inset also absorbs a printer's unprintable edge — the wordmark, the addresses and both codes all sit inside it.
- **The parties stack.** Four millimetres of side padding leaves 77 mm, about 35 mm per party; half an address does not fit in 35 mm. Each party centres in its own half with a hairline between, so the label is not hollow in the middle.
- **The receiver is set a step larger than the shipper** — 12.5pt name and 10pt address against 9pt and 7.5pt. The receiver is what a courier reads; the shipper is a return address. Both blocks now carry their own classes (`mark-party-from` / `mark-party-to`) so this does not depend on document order.
- **The codes grow with the label:** 17 mm tracking (0.44 mm per module) and 14 mm Instagram.
- **The footer branding drops to 8pt and wraps.** At 9.5pt `www.themisspack.com` ran *under* the tracking plate — the proof render caught it, and `mark-check` now guards that a long website cannot paint over a code.

**Verification**

| gate | result |
| --- | --- |
| `mark-check` | **31 / 0** — page 85 × 130, mark 85 × 129.5, ≥ 0.4 mm page slack, worst-case row budget **119.4 mm of 121.5 mm**, receiver larger than shipper, no hollow middle, footer can't collide with the codes, both QR payloads encode **and decode** (0.44 / 0.40 mm per module) |
| `design-check` | **35 / 0** |
| `blade-check` | **4 / 0** (88 templates) |
| proof render | 300 dpi label, **6 / 0** — both codes decode *out of the raster*, both plates sample white, captions inside the inset |

Proof: `public/_preview/sticker-85x130-proof.png` (untracked dev artifact).

**If the stock is not exactly 85 × 130** — a roll with a wider gap, or a label whose printable area is inset more than 4 mm — give me the real figure and I will re-cut the geometry; the check asserts the page size, so it will keep you honest either way.

---

## Part 12l — the address keeps its pin code (85 × 130 mm label)

Reported on a real print: the receiver's address came out as "…Shahibaag, Ahmedaba…" — the pin code and country were gone. Merged your `1a587d4` first, then fixed the truncation.

**Why it was cut**

| | |
| --- | --- |
| the address needs | 3.6 lines at 10pt for a 145-character address (measured against the 77 mm line) |
| the clamp allowed | **3 lines** |
| the leading cost | `line-height: 1.5` on the sheet and `2` on the party/meta blocks — about 2 mm a line, which is most of a fourth line |

**What changed**

- **Four lines, and the address steps down a size instead of losing its tail.** The partial measures the address and picks a size: 3 lines at the base size (120 characters for the receiver, 132 for the shipper), 4 lines at 8.5 pt (192), 4 lines at 7.5 pt (216). The reported 145-character address now prints in full at 8.5 pt.
- **The pin code is repeated in the destination strip** — `AHMEDABAD, GUJARAT, INDIA 380004` — and if the pin code field is empty it falls back to the last six-digit number in the address. Whatever happens to the address block, the code the courier sorts by is printed where it is read.
- **The address no longer repeats what the free text already says.** His address already ends with city, state, pin and country; the record also had them as fields, and printing both is what pushed the line over.
- **One base leading (1.35)** with per-element exceptions, instead of 1.5/2.
- **The FRAGILE chip moves to the footer**, where the codes already set the height. In the meta strip it either started a second row or wrapped the destination, and either way it cost the address a line.
- **The destination value is 8 pt** — at 8.5 pt it is 0.6 mm too wide for its column, and wrapping it would cost an address line too.
- **Contact lines are escaped** before the template joins them with `<br>` — a record value should not be able to act as markup on a printed label.
- Hardened while in here: the size-step rules must come *after* the per-party rules they override (same specificity, so source order decides), the logo size moved out of the inline style, and the now-dead rules for the title block and shipment-number chip are gone.

**Verification**

```
mark-check   47/0   address budget re-derived from the font (40/44 chars per 77mm line),
                    thresholds read out of the partial, every boundary address asserted —
                    including the exact 145-character address from the report — at 8.5pt and 7.5pt,
                    with and without the label chip; both QRs decode; rule-order guard
design-check 35/0   blade-check 4/0
proof render  6/0   both codes decode out of the 300 dpi raster, address complete
```

**Two things to decide:**

1. **The shipment number is no longer printed anywhere on the label** — the header redesign removed the `SHP-0002` chip, and the tracking QR carries the record. Say the word and I'll put the number back (the footer has room beside the branding).
2. `.DS_Store` files and `storage/logs/laravel.log` were committed along the way; I untracked them and added them to `.gitignore`. The working copies are untouched.

---

## Part 12m — the shipments list reads at a glance (d660cb7)

> *"Make listing page more attractive and follow UI industry standards to make it functional reach."*

The screenshot showed a page that had been edited a cell at a time. Under it: inline `style=` on most cells, a status chip that printed white on yellow (`#dbcb3b`), a hard-coded blue label chip, a row hover that used `--bg-card` — which in dark theme *is* the card, so the hover did nothing exactly where the list is hardest to scan — a Charges column that did not line up with its own total, a kebab menu no keyboard could close, `colspan="10"` on an 8-column table, and a delete modal that no longer existed while its markup and JS bindings still did.

The page is now built from the shared vocabulary rather than restyled locally, and the parts that other lists will want moved into `master-index.css` instead of being copied.

**The list itself**

| | |
| --- | --- |
| **One primary action** | Quick Shipment moved out of the toolbar into the topbar (new `.topbar-page-actions` slot in the layout), so the page has a single loudest button. |
| **One toolbar** | Search, status, currency and pickup-date in one row; **Reset appears only when a filter is actually set**, and the submit button says *Apply filters* instead of *Filter*. Every control carries a label (`aria-label` or `<label for>`). |
| **A scannable row** | Date first with P/C project and client tags, then the shipment number as the link with the product as its second line, route, logistic, ETA, charges, status, and one kebab. Whole rows are keyboard-reachable and open the record. |
| **Money that lines up** | Charges is an `.is-num` cell, and the totals row puts its figure in the *same column* rather than in a merged cell — the one thing the old table could not do. |
| **Honest controls** | The closed block says how many rows it holds (`1 on this page`), the order rule is a one-line caption with the full sentence in its tooltip, and the empty state has a real way out (clear the filters, or start a shipment). |
| **Both themes** | Every status, type and label tone has a dark counterpart — 8 statuses, 3 types and 6 label colours — so no chip is white-on-light or dark-on-dark in either theme. |

**Row linking that behaves.** A row carries `data-href` and opens the record, but the handler steps aside for anything interactive inside it (`a, button, input, select, textarea, label, form`) and for a click that ends a text selection. Nothing about the row overrides a control that sits in it.

**What is now shared rather than repeated**

- `master-index.css`: the hover is `--mc-hover` (visible in dark), the last body row drops its hairline so it does not double up with the totals row, and the modal bits a confirmation dialog always needs (`.master-modal-icon.is-danger`, `.master-modal-text`, `.master-modal-card.is-narrow`).
- `app-layout.js`: menu toggles carry `aria-expanded`, opening one closes the others, and **Escape closes the open menu and returns focus to its button**.

**Zero inline styles** remain on the page (checked mechanically — the old page had them on most cells).

**Verification**

```
list-check   32/0   headers are scope="col" and count 8, no cell overruns its columns,
                    Charges and the totals sit in .is-num, the number is the link,
                    the row-link JS guards links/buttons/inputs/selections,
                    one primary action in the header and at most one in the toolbar,
                    Reset only when filtered, every toolbar control labelled,
                    every status/type/label tone has a light *and* dark rule,
                    empty state present, menu state + Escape, density, right-aligned
                    actions, caption tooltip, closed-block count, no fixed hex colours
blade-check   4/0 (88 templates)   design-check 35/0   mark-check 47/0
```

`tools/checks/README.md` documents the new gate, and `blade-check.cjs` was repaired while adding it: it counted `@hasSection` as its own block (Blade closes it with `@endif`), which was mis-flagging 80 templates as imbalanced.

**One thing I chose deliberately:** the card keeps `overflow: visible`, because the table's own scroll wrapper already clips the kebab menus and a second clip on `.master-card` would cut them harder. Say the word if you would rather have rounded corners guaranteed at the card edge and the menus flipped upward instead.

---

## Part 12n — the same list, now a working surface (69340bc)

Part 12m made the page consistent with the shared vocabulary; this pass makes it do more. Nothing above the table changed shape — the toolbar, chips and rows look the same at rest.

**The list scrolls inside its own box (≥1200px).** Fifty rows is a page you scroll, so the header now pins to the top and the totals row pins to the bottom of the table's own scroll area. Scroll anywhere in the list and the column names and the money total stay with you. Two details that make it work rather than nearly work:

- the table switches to `border-collapse: separate` inside that media query — with collapsed borders the hairline belongs to the table, not to the sticky cell, so the header would lose its bottom border exactly when it sticks;
- the header lifts (`box-shadow`) as soon as the first row goes under it, via one passive scroll listener, so the boundary is unmistakable.

**Row density.** `Comfortable | Compact` sits beside the ordering rule, not in the filter toolbar — how much list fits on screen is a preference, not a filter. Compact is about a third more rows; the choice is applied before the first paint (the script ships at the end of the body, so there is no flash of the other density) and remembered per device. `aria-pressed` carries the state for anyone not reading the highlight.

**Applied filters, each removable on its own.** The filter card now ends with a strip naming what is actually filtering: `SEARCH green ✕  STATUS In transit ✕  PICKUP FROM 01 Oct 2026 ✕`. Two things this fixes beyond looks:

- filters that arrive from a **saved view or a URL** — status, currency, pickup date, attention — had no visible control at all, so rows could be missing with no way to tell why. They now show up here and can be removed;
- removing one chip keeps every other filter (`except()` the one key, restart `page`, drop `saved_view` since the result is no longer that view). `Clear all filters` is the escape hatch.

**One row rhythm.** Every first line in a row now shares a 20px line box and every second line a 16px one, with the status/ETA pills sitting *on* the first line rather than being 22px tall next to 13px text. Before this, the row's top line wandered: 13px in the date cell, 13.5px in the shipment cell, a 22px pill in the status cell.

**The stacked mobile card gets its labels back.** The ≤768px layout turned each row into a card but the label rules rendered `content: ""` — six empty blocks. The column name now travels with the cell (`<td data-label="Charges">` → `attr(data-label)`), so the card reads as labelled values instead of a pile of strings. The card's shadow also stopped being a fixed black and uses the theme token.

**A real dark-theme bug, fixed in the shared stylesheet.** The kebab menu on *every* list page was painted with fixed light-theme values: the toggle icon `#55627a`, its hover `#e9efff`, the menu items `#2b3445`. On the dark card that is a dark label on a dark panel and a light chip flashing on a dark toolbar. All three now follow the theme, with a dark counterpart for the danger item.

**Verification**

```
list-check   48/0   (was 32)  applied chips cover every filter and remove only their own key,
                              density control states its state, is remembered and is applied
                              pre-paint, both densities change the row geometry, the grid pins
                              header + totals with separated borders, header lift on scroll,
                              mobile data-labels, no empty ::before placeholders, one row rhythm
design-check 36/0   (was 35)  the shared row-action menu is themed, not fixed to a light palette
blade-check   4/0 (88 templates)   mark-check 47/0
```

Every new rule was tested by reverting the thing it describes: reintroducing `color:#2b3445` in the menu fails design-check, dropping the density key from the script and setting the header to `static` both fail list-check.

**Still on the table if you want it:** bulk selection with a bulk action bar (mark delivered, export), and sortable columns — the latter needs a controller decision, since the list deliberately orders open-first/closed-block and an arbitrary sort would break that rule.

---

## Part 12o — the closed divider spans again (faaf9c2)

You were right, and the cause is one line of CSS: the divider's `<td>` was set to `display: flex` so its label and count would sit at the two ends. **A table cell that is a flex box is no longer a table cell** — the browser wraps it in an anonymous cell in the first column, `colspan="8"` stops meaning anything, and the strip collapses into a narrow column with its label wrapping over three lines (exactly what your screenshot shows).

**The fix**

```html
<td colspan="8">
    <div class="ship-group-inner">   {{-- the flex lives here, not on the cell --}}
        <span>Closed — delivered / cancelled</span>
        <span class="ship-group-count">1</span>
    </div>
</td>
```

The cell keeps `padding`, the two hairlines, the uppercase label and the letter-spacing it already had; the wrapper does the `space-between`. The count still sits at the far edge and the strip spans the full row.

**The same bug was one page over.** The users list grids every `.master-table td` into a 112px label column + value column at phone widths — which squeezed its `colspan="7"` empty state into the label column and printed an empty label above it. That grid now excludes spanning cells (`:not([colspan])`), and the label rule only fires where a cell actually carries `data-label`.

**Guarded so it cannot come back**

- `design-check` (37/0) now refuses any `<td>`/`<th>` turned into a flex or grid box **anywhere in the page layout**, across every stylesheet rather than only the shared ones — and inside a card breakpoint only when the selector excludes `[colspan]`.
- `list-check` (51/0) pins the divider itself: spanning cell, inner flex, count at the far edge, and still a hairline band rather than a tinted block.

Both were verified by putting the bug back: reintroducing `display:flex` on the divider fails list-check *and* design-check; reintroducing the users grid fails design-check. The tree is clean again afterwards.

```
design-check 37/0   blade-check 4/0 (88 templates)   mark-check 47/0   list-check 51/0
```

---

## Part 12p — the row menu renders above the list (1de1a0a)

The kebab panel was coming out interleaved with the rows behind it. Four separate things were doing that, and each one on its own is enough to make a menu look broken:

**1. A later stylesheet was overriding it.** `master-show.css` loads *after* `master-index.css` and carried a full copy of the dropdown cluster with the old fixed colours. So the themed values I put in `master-index.css` in Part 12n (`--mc-text-2` icon, `--mc-hover` hover, `--mc-text` items) **never applied** — the live values were `#55627a`, `#e9efff` and `#2b3445`. On the dark card that is a dark label on a dark panel. The duplicate is deleted; `master-index.css` is the single owner of the component.

**2. A panel in a table cell paints in its row's pass.** So every row after the open one painted *on top of* the menu — the menu items were visible underneath the following rows' text, which is exactly the jumble in your screenshot. The row now gets `tr.is-menu-open { position: relative; z-index: 4 }` while its menu is open (set by `app-layout.js`, released on close, Escape or an outside click), so the whole row — panel included — paints above its siblings.

**3. The list scrolls inside its own box since Part 12n, and that box clips.** A menu opened near the bottom edge would have been cut. The panel is now measured against the box it lives in: it **flips above the button** when there is more room up there, and its **height is capped** to the space that is actually left so it scrolls internally rather than disappearing past the edge.

**4. The closed rows were muted with `opacity: .68` on the cells.** Opacity creates a stacking context, so any menu opened in the closed block was *see-through* and painted below the rows after it. Finished rows are now muted in colour (`--mc-text-2`) — same "done" look, no stacking context.

**Verification**

```
design-check 40/0   (was 37)   no row/cell may set opacity; only master-index.css may
                               declare the menu; the raised row and the flip must exist
list-check   56/0   (was 51)   the script raises and releases the row, flips and caps the
                               panel, and closed rows are muted in colour
blade-check   4/0 (88)         mark-check 47/0
```

Every guard was verified by putting the specific bug back — re-declaring the menu in `master-show.css`, restoring `opacity: .68`, or dropping the row-raise from the layout script each fail the check that describes it, and the tree is clean again afterwards.

**Worth knowing:** the same duplicate-stylesheet problem is why the dark-theme menu fix in Part 12n appeared to do nothing. If any other shared component looks "unstyled" the same way, it is worth checking whether a later sheet re-declares it — the new check now covers the whole load order for this one, and the same pattern would show up the same way.


## Part 12q — a delivered shipment records today's date, it does not argue (164a849)

The **Validation Error** you hit — *"Add the delivery (drop) date before marking this shipment delivered"* — is gone, and the rule underneath it is inverted. The app no longer asks the operator for a date it already knows; it records the day the delivery was marked and moves on.

- **The default lives in one place.** `Shipment::withDeliveryDefaults()` fills the delivery date whenever a status is about to become `delivered` and the record has none. It only ever **fills** — a date already on the shipment, or one submitted with the form, is never overwritten, so a delivery recorded late keeps the date it was recorded with and correcting one stays an explicit edit.
- **"Today" is the office's today, not UTC.** The app stores UTC; a delivery marked at 2 AM in India belongs to that Indian day. The day comes from `app.business_timezone` (`Asia/Kolkata` by default, `APP_BUSINESS_TIMEZONE` to change it) rather than from the server clock.
- **Every path that writes a status applies it** — not just the one in your screenshot: the edit form, the **Add Tracking Update** form on the record page, a shipment *created* already delivered, and a status that changes because a history entry was edited (that last one derives its status from the latest history row, so it needed the same default).
- **The date is visible before it is saved.** The tracking form now offers a **Delivery (drop) date** field next to the status, prefilled with today and shown only when the status can actually use it; leaving it empty still records today. The edit form's Drop Date says what an empty field will do. The script fills the field for a delivered status and clears it again when the status moves away — but only if it was the script that filled it, never a value the record already had.
- **The flash message names what was recorded** — *"…The delivery date was recorded as 02 Oct 2026 because none was given — edit the shipment to correct it."* — so a date the operator did not type never appears silently.

**What is still refused, deliberately:** a hold or a delay with **no reason** (an unexplained hold is noise in every report downstream), and a **delivery date that precedes its pickup**. Both are data errors rather than empty fields, so they keep their validation error — and the second one is new: the edit form already refused it, the tracking form did not, and it would now be the only thing standing between a future pickup date and an impossible record.

**One thing found on the way:** `validatedHistoryData()` is shared with `updateHistory()`, whose rows have no `drop_date` column at all. Adding the field to that shared validator would have looked right and silently dropped it on update. The date is therefore validated where it belongs — in `storeHistory()` — and never touches the history table.

**Verification**

```
status-check 20/0   (new)      the same rule from four sides: the default writes today's
                               date and never overwrites, every status-writing path applies
                               it, nothing refuses a delivery, the still-refused cases stay
design-check 40/0              blade-check 4/0 (88)      mark-check 47/0     list-check 56/0
```

Each status-check guard was proved by putting the specific bug back — restoring the old validation error, removing the default from the history path, deleting the fill line from the model, and writing a timestamp instead of a date each fail exactly the check that describes them. One of those proofs caught a check of mine that was passing on structure alone; it now asserts the write.

## Part 12r — the action menu leaves the table, and a foreign cost shows its rate (8b5dbed, 9997c00)

Two things from the same screen.

**1. The action menu no longer competes with the table.** Part 12p raised the open row and flipped the panel — that fixed the *symptom* inside the table. This removes the fight: the panel is now `position: fixed` and placed against the viewport, so it is not part of the row's paint pass and not clipped by the box the list scrolls in.

- `master-index.css`: the panel is fixed and z-indexed above the sticky header (3) and the topbar (1040) and below a modal (9999). The row-raise (`is-menu-open`) and the flip class (`drop-up`) are gone — with a fixed panel there is nothing to raise and nothing to flip with CSS.
- `app-layout.js`: the button is measured, the panel is placed under it and right-aligned with it, kept inside the viewport, flipped above when there is more room there, and capped to the room the viewport actually has so it scrolls internally rather than disappearing past an edge. An open panel follows the page while it scrolls (capture phase, so a scroll inside the table's own box counts) and on resize.
- `shipments.js`: a click anywhere on the panel belongs to the panel. Before this, a click on the panel's empty padding fell through to the row and opened the shipment.

The same menu is used by **cashflows, leads and vendor quotes**, which had the same stacking and clipping exposure — all four are fixed by this.

**2. The exchange rate is now always on screen when a cost is not in INR.** The modal *had* the field, but it appeared and disappeared with the currency select — so it was missing exactly when a foreign bill was being entered, and the server then refused the row with nowhere to type the number.

- The field is always present. On the base currency it is locked at 1 and reads as settled (required marker hidden, input muted); on anything else it is required. The server renders the first state from the shipment's currency; the script only ever matches it, on load and on a delegated `change`/`input`, so a missed event cannot leave it hidden.
- The rate starts from **the rate that currency was last billed at** — the shipment's own history first, then the rest of the book — so the operator confirms a number instead of inventing one. A rate entered for one currency never carries over to another, and the rate on the row being edited is never overwritten by the default.
- The INR value is shown while it is typed: *"≈ ₹ 2,085.50 posted to the ledger"*, plus which earlier rate it came from. What gets frozen into the ledger is never a surprise on the report.
- The ledger still refuses a foreign cost without a rate, and never invents a 1:1 conversion — the INR value is `amount × rate` or nothing.

**Verification**

```
design-check 41/0              list-check 58/0 (was 56)     cost-check 16/0 (new)
blade-check  4/0 (88)          mark-check 47/0              status-check 20/0
```

Every new guard was proved by putting the bug back: absolute positioning with a low z-index, dropping the scroll follow, dropping `.master-dropdown` from the row click guard, hiding the rate field again, dropping the load-time sync, letting the ledger invent a rate, and removing the server's refusal each fail the check that describes them.


## Part 12s — the rate is editable, not locked (b21b282)

Part 12r put the rate box on screen but locked it at 1 on the base currency and only let it be typed on a foreign one. That is the wrong default: on a foreign bill the rate is the one number the app cannot know for certain, so it has to be typeable whenever it matters. Nothing is locked now.

- The input is a normal input in every case — no `readonly` in the markup, no `readOnly` in the script, and the muted "settled" styling is gone. It opens with the rate that currency was last billed at, and the operator types the rate this bill was actually raised at; the INR value it produces updates as they type.
- On the base currency it starts at 1 and the required marker is hidden, with the hint saying why: *"INR bill — the amount is already in rupees, so the ledger keeps the rate at 1."* A rupee bill is now **stored** at 1 on save, which also closes a real hole — before this, a row whose currency had been edited to INR could keep a foreign rate and multiply a rupee amount by it.
- The live INR value follows the field in every case, so the effect of the number being typed is visible before saving.
- The ledger keeps one rule — INR value = amount × rate, with the base currency defaulting to 1 — and a foreign row without a rate is still refused rather than quietly converted at 1:1.

**Verification**

```
cost-check 19/0 (was 16)   design 41/0   blade 4/0 (88)   mark 47/0   list 58/0   status 20/0
```

Verified by putting the bugs back: re-adding `readonly`, dropping the rupee default, dropping the save-time normalisation, and dropping the base case from the live preview each fail the check that describes them.


## Part 12t — the open row menu moves into the body, where nothing can draw over it (3e8f372)

The panel was still coming out see-through on the shipments list: the row content behind it stayed legible and the kebabs of the rows below sat **on top of** the menu items. Every earlier pass tried to win *inside* the table — raise the row, flip the panel, cap it, place it fixed. A fixed box still belongs to the paint order of its ancestors, so the first ancestor that creates a stacking context (a sticky cell, an opacity group, a transformed card, a filter) traps it again, whatever `z-index` it asks for.

- **While a menu is open its panel is moved into `<body>`.** There is no table above it any more: no row pass to be painted under, no scroll box to clip it, no ancestor stacking context to trap it. It is placed against the viewport by measuring the button, and it is put back exactly where it came from when it closes — so the markup stays as authored and its links, kebabs and delete form keep working.
- **The panel's background is stated as a background *colour* with a fallback** (`var(--mc-card, #ffffff)`) instead of the shorthand, so nothing can leave it see-through; the shadow is a touch stronger to carry the lift.
- **The panel carries its own focus ring.** The `focus-visible` rule scoped to `.master-table` cannot reach a panel that is no longer inside the table, so the panel styles its own links and buttons.
- `z-index: 1300` — above the sticky header (3), the topbar (1040) and the sidebar, below a modal (9999).

**Verification**

```
design-check 42/0 (was 41)   list-check 62/0 (was 58)   blade 4/0 (88)   mark 47/0   status 20/0   cost 19/0
```

The panel must be fixed, opaque and above the topbar; the script must portal it, remember it per dropdown, show it explicitly (the `.open` selector cannot reach a portaled panel), and restore it on close. Each guard was verified by putting the bug back — dropping the portal, dropping the explicit display, and returning to the `background` shorthand each fail the check that describes them.

**Note on `.master-raw-action`:** that class does not exist anywhere in this codebase (`resources/views`, `public/assets/css`, `public/assets/js` — all searched). The row-actions cell is `.master-row-actions` and the menu is `.master-dropdown` / `.master-dropdown-menu`. If that name comes from a local edit or another stylesheet, send it over and it will be wired in.


## Part 12u — one shape for every row-menu item (21e3a22)

"Public Link" was centre-aligned and had no icon while the six items around it were left-aligned with icons. It was not a menu item at all: it was a `<button class="master-btn master-btn-soft master-btn-sm">`. Inside the panel a button wears its own background, centres its own content (`justify-content: center`) and lines up with nothing — and it had no glyph, so its label started in a different place again. The same pattern was in the delete items on **cashflows** and **vendor quotes**.

- **The panel owns the item shape.** Those three views now use plain menu items — the `.master-btn*` classes are gone, and each item keeps only its behaviour (the `master-delete-btn` hook, the `data-delete-url`/`data-name` attributes, the copy-link handler).
- **"Public Link" gains the icon it was missing**, and the three delete items carry the vocabulary's `danger` class — the same red destructive item the **leads** menu already had.
- **The item rule now states the shape:** `justify-content: flex-start`, `border-radius: 0`, `box-shadow: none` — so nothing a button (or any future markup) brings with it can break the alignment — and the icon slot is a fixed **18 px**, so every label starts at the same left edge whatever the glyph is.

**Verification**

```
blade-check 6/0 (was 4)   design-check 43/0 (was 42)   list 62/0   mark 47/0   status 20/0   cost 19/0
```

`blade-check` now walks every menu block in all 88 templates: no item may carry button styling and every item must have an icon (4 menus, 19 items). `design-check` holds the left edge, the zero radius and the 18 px icon slot in the stylesheet. Each guard was verified by putting the bug back — re-adding `.master-btn` to an item, deleting its icon, and dropping the left-alignment each fail the check that describes them.


## Part 12v — the spend total is one ₹ figure (ab19b78)

The Spent card listed every currency the entries happened to be billed in — `INR 1,20,000.00 · USD 2,400.00` — which takes two codes, a conversion in the reader's head, and leaves two figures sitting side by side as if they could be added. The ledger already knows better: **every cost head carries the rupee value it was booked at, at the rate of the day.**

- **One rupee total.** The Spent card, the list footer and the cost card now show a single `₹` figure summed from those recorded values — no rate is re-applied, nothing is averaged, and two currencies are never added as bare figures.
- **A shipment still on the old single “Shipment Cost” figure** counts here while it is billed in rupees. A *foreign* legacy figure has no rate to convert with, so it waits for its cost heads rather than being converted at a rate nobody recorded.
- **The rupee sign replaces the code `INR` wherever it was used as a symbol:** amounts, the rate hint on a cost head, the rate placeholders on the vendor payment modals, the ledger's column header, the summary's total label. `formatInr()` is the one formatter behind it, and `formatAmount()` only puts `₹` in front of a rupee figure — a dollar amount keeps its own code, because a `₹` in front of it would state the wrong amount. Per-head amounts and declared values still show the currency they are actually in.
- `INR` may still *name* the currency in prose, in a select option or in a comparison; it may no longer be the symbol.

**Verification**

```
cost-check 25/0 (was 19)   design 43/0   blade 6/0   list 62/0   mark 47/0   status 20/0
```

New guards: no rupee amount, rate or field is written with the code; the symbol lives in one place and every rupee figure goes through it; no PHP literal builds money as `"INR …"`; the list totals are rupees; the filtered total is the ledger's own INR snapshot; the cost card headlines the rupee total. Four mutations each failed their own check — the symbol changed to `INR`, the spend card put back to a currency list, raw amounts summed instead of the snapshot, and an amount prefixed with `"INR "`.


## Part 12w — Indian grouping, written in ₹, from one formatter (f266d98)

`150000` was coming out as `₹ 150,000.00` — western grouping, a space after the sign, and `.00` on an amount that has no paise. It now reads **`₹1,50,000`**.

- **`CommonHelper::indianCurrency()` — the formatter the sales-invoice module already used — is now the app's one money formatter.** The grouping rule is written once (last three digits, then twos) and the rupee sign lives in one constant; `Shipment::formatInr()` / `formatAmount()` delegate to it, so the ledger, the list, the shipping marks and the prints can never disagree again.
- **`inr($amount)` and `money($amount, $currency)`** are the short names views use. The helper file was already autoloaded, so nothing has to be dumped on the server. `money()` keeps a non-rupee amount in its own currency (`USD 2,400.00`) — a ₹ in front of a dollar figure would state the wrong amount.
- **Paise only when the amount has them:** a whole amount reads `₹1,50,000`, a fractional one `₹1,23,456.50`.
- **Every rupee figure on every page** now goes through it: cashflows (index, show, reports, PDF), vendors, projects (admin and client portal), leads, products, quotes, invoices, payments, the dashboard, the client portal and the shipments module.
- **`public/assets/js/money.js`** mirrors the same rule in the browser (the cost modal's “≈ ₹ … posted to the ledger”), included by both layouts.
- The last hand-built money in templates is gone — a currency echoed and then a rupee figure (`USD ₹1,200`) is folded into `money()`.

**Verification**

```
cost-check 28/0 (was 25)   blade 7/0 (was 6)   design 43/0   list 62/0   mark 47/0   status 20/0
```

`cost-check` holds: money has one formatter and it groups the Indian way; the browser half is **run against fixtures** (`150000 → ₹1,50,000`, `6163140 → ₹61,63,140`, `123456.5 → ₹1,23,456.50`, `-1234567.5 → -₹12,34,567.50`, `0 → ₹0`, `1000 → ₹1,000`); a template may not format money by hand — only an exchange rate keeps its own decimals; and a money figure is never hand-built next to its currency. `blade-check` adds “every `{{ … }}` expression has balanced brackets”, the thing a sweep of this size can break. Every guard failed on a real occurrence — the hand-built check went red on five live two-line cases — and on six deliberate mutations.


## Part 12x — the money helper is called by class name (735ef6d)

The cashflow page died with **“Call to undefined function inr()”**. The short `inr()` / `money()` names existed only once something had already loaded the helper class: the sales-invoice pages worked because they *name* the class, and a page that never mentions it — cashflows — reached the view with no function defined. A global function that depends on another file being loaded first is not a helper, it is a trap.

- **Every money call in every template (125 of them, across 31 templates) now names the class:** `\App\Helpers\CommonHelper::indianCurrency(…)` and `::amount(…)`. The autoloader resolves a class name on first use, so the figure formats whether or not composer has been dumped, and the helper file declares no global functions at all any more.
- The same sweep had written the class name with **doubled separators** (`\\App\\Helpers\\…`), a PHP parse error — caught and fixed before it left the branch.
- Two guards, each proved by putting the bug back: a template may not call a bare `inr()` / `money()` — *“money is called by class name, never by a global that may not be loaded”* — and no `{{ … }}` expression may contain a doubled namespace separator.

**Verification**

```
cost-check 29/0 (was 28)   blade 7/0   design 43/0   list 62/0   mark 47/0   status 20/0
```

## Part 12y — every listing is one surface, and cashflow is on it (8fd2d83)

The cashflow listing was its own island: a plain table under four stat cards, while the shipment list had grown quick-view chips, saved views, an applied-filters strip, a density switch, a pinned grid with a totals row and an empty state that offers a way out. Copying that markup into cashflow would have meant two lists to fix every time one of them changed — so the chrome was lifted out of the shipment sheet instead, and both lists now opt into it.

- **`public/assets/css/master-list.css`** — the list chrome, one file: chip bar and chips (with counts), saved views and the save-view form, the applied-filters strip, the table bar with its ordering hint, the density switch, the grouped divider, the totals row, the empty state, the mobile card and the pinned desktop grid. A list opts in with `class="… master-list"`. The shipment sheet lost **505 lines** (2270 → 1765) and every rule it kept is still its own cell; the cashflow sheet gained only the ledger's own cells.
- **`public/assets/js/master-list.js`** — the behaviour behind that chrome: row navigation (a click on a link, button, form or a text selection stays with the row's own control), the density switch remembered per list and applied before the table paints, the pinned-header shadow, and the saved-view form. `shipments.js` and `cashflows.js` now only name their root and their storage key — neither re-implements any of it.
- **The cashflow list reads like the shipment list now:** five stat tiles (credit, debit, net, bank & cash, pending settlement, each with a tooltip), chips with live counts (All · Credit · Debit · Pending · This month), saved views with the share option, an applied strip where every filter — including ones that arrived from a URL — is removable in one click, the ordering hint beside Comfortable/Compact, and rows grouped by day (Today / Yesterday / Earlier this month / Older entries / Undated) with a count on each divider. The totals row shows the page's figures next to the same figures for the whole filtered set, and the empty state offers both “Clear filters” and the quick-entry dialog.
- **Its own concepts stayed its own:** credit and debit as separate money columns (green and red, both correct in the dark theme), the account and its type, the settlement pill for pending / booked / reconciled / disputed / ignored — each with a light *and* a dark tone — the mirrored vendor-payment and shipment-cost chips that jump to their source, and the quick-entry and account dialogs. Those dialogs' triggers are back in the page header, beside the detailed form and the links to Reports and Settings.
- **Suggested views** are now shared with the shipments module: `POST /cashflows/saved-views` and `DELETE /cashflows/saved-views/{view}`, the same `SavedViews` service, the same `?saved_view=ID` round-trip. Nothing new to migrate.

**Verification**

```
design 43/0   blade 7/0   mark 46/0   list 75/0 (was 62)   status 20/0   cost 29/0
```

Seven checks were added to `list-check.cjs`, each proved by putting the bug back — the two lists must carry the same chrome classes and every class they use must be defined in the shared sheet; no module sheet may re-declare the chrome; both must drive the shared toolkit; the mobile card labels and the compact density may not leak outside their breakpoint; every settlement status needs a light and a dark tone; the list must keep its module's destinations; every dialog it renders must have a way to open it.

Two defects were caught by this work and fixed before it shipped: rules lifted out of a `@media` block had lost their breakpoint (the mobile card label would have shown on the desktop table), and the rewritten page had dropped the “Detailed Form”, “+ Account”, “Reports” and “Settings” actions — the account dialog was rendered but nothing could open it.


## Part 12z — the two lists are literally the same screen (b7cec22)

Part 12y gave the cashflow listing the shipment listing's **chrome**. This part makes it the same screen **down to the numbers**: the ledger's rows were a different height, its columns had no width hints (so they reflowed from page to page), its headers sat on a different padding, and its tiles were taller than the shipment ones.

- **The ledger table is measured exactly like the shipment table:** 12px/14px cells, the same filter-row inset (`14px 16px 16px`), the same row rhythm (a 20px first line and a 16px second line, 18px/15px in compact), the same 4px sub-line spacing, and a width hint on each of its eight columns so nothing shifts between pages.
- **Both lists open with the same strip of five flat tiles** — title and value, and only the last one carries a second line. The explanations moved into each tile's tooltip instead of stretching the tile.
- **The ledger row gained the fact it never showed:** how the money moved (NEFT / RTGS / UPI / Cash / …), as a small tag on the row's second line.
- **The add-account button left the page header** for the account filter it belongs to, and **the quick-entry dialog points at the detailed form** — every way to record an entry stays one click from the list.
- **A real defect found while auditing:** the clients list had a stray `</td>` (an invalid row that browsers recover from silently). Fixed, and `blade-check` now proves every structural tag in every template balances.

**Verification**

```
design 43/0   blade 8/0 (was 7)   mark 46/0   list 81/0 (was 62 two parts ago)   status 20/0   cost 29/0
```

`list-check` now compares the two screens directly instead of trusting a comment: the same components in the same order, the same table measurements, one shared row rhythm, column hints on both, the same strip of tiles, and the ledger's three doors (quick entry, detailed form, add account) pinned open. Every one of those five guards was proved by putting the bug back — five mutations, five red.



## Part 12aa — the tablet band stopped shattering the table (571ea7d)

The report was a screenshot of the cashflow listing: the desktop header (DATE / PARTICULAR / ACCOUNT / CREDIT / DEBIT / BALANCE / STATUS / ACTION) and the day dividers were drawn, but every row underneath had come apart — a date box on the left, the particular crammed beside it, a lone “—” under Credit, a red ₹ figure floating mid-row, and the row's ⋮ action sitting under the next row.

The cause was in the central responsive layer, not in either list: below 1200px `responsive.css` set `display: block` on `.master-table`. A block `<table>` is not a scrolling table — the browser then wraps `thead` and `tbody` in two **anonymous tables**, so the header and the rows stop sharing one column grid and each cell is laid out on its own. That is why the header looked right while the rows did not: the header was one anonymous table, the body another. The band is **769–1199px** — a laptop at 125% zoom, or a tablet — so the list broke on exactly the screens between phone and desktop.

- **The table keeps its box; the wrapper does the scrolling.** A `.master-table-wrap` already scrolls (the same shell every other page uses), so the rule that restyled the table was as unnecessary as it was destructive. The three tables that had no wrapper — the products price ladder and both vendor-quote tables — now have one, so no table is left without somewhere to scroll.
- **The list surface owns its own shell.** `master-list.css` pins `table`, `thead`, `tbody`, `tr` and `td` back to their table display from 769px up, and the labelled-card restack lives below 768px — **scoped to `.master-list`**. Until now that card layout was leaking in from `shipments.css` **unscoped**, so at phone width it restacked every table in the app, including forms and detail pages that have no labels to show. The shared sheet owns it now, the module sheet keeps only its own cells, and the shared sheet's stale comment pointing at a card layout it did not contain is gone.
- **Both themes are right.** The card keeps a hairline and uses `--mc-shadow`, the elevation token that is paired for light and dark; it had been using a light-only shadow token, so on the dark panel the card separated from the background by nothing but its own fill.
- **Four new checks in `list-check.cjs`, each proved by putting the bug back:** no sheet may restack the shell above the phone band; the module sheet must leave the list shell alone; the shared sheet has to pin table, thead, tbody, tr and td **by name** (a substring needle let `display: table-header-group` answer for `display: table`, so every pin is a full declaration); and every table in a view must sit in a `.master-table-wrap`.

**Verification**

```
design 43/0   blade 8/0   mark 46/0   list 85/0 (was 81)   status 20/0   cost 29/0
```

Nine mutations, nine red: `display:block` on `.master-table` back at `max-width: 1199px` (the reported defect), an unscoped `.master-table tbody` restack back in the module sheet, a bare `.master-table` restack at top level, the shared sheet dropping its phone-band stack, the shared sheet losing any one of its five shell pins, and a view losing its `.master-table-wrap`. The sandbox has no browser, so the render itself was verified headlessly: a cascade simulation over the real stylesheet load order shows the winning `display` for `.master-table` is the table default at 1440px and 1024px and the card layout at 768px and below.


## Part 12ab — every dialog scrolls, and the ledger has its period chips (e6fa289)

**The quick-entry dialog would not scroll.** The shared modal already had the right shape — a card bounded to the screen, with the body carrying the overflow — but the rule that made the form fill the card hung on `:has()`. Where `:has()` is unsupported the tall form stayed content-sized, overflowed the card's own `overflow: hidden`, and the last fields could not be reached at all: no scrollbar, nothing to drag. The form is now a column flex that fills the card in the **plain** rule, so the scroll no longer depends on the browser, and the trailing action row pins to the bottom of the card — which is also what a full-bleed phone dialog wants. Both shapes in the app are covered (`card > form > header/body/footer`, and the delete dialogs' `card > header/body + form(footer)`): 20 dialogs across 14 templates.

**Period chips on the ledger: This month · Last month · This year · Last year.** The four ranges are one definition (`App\Helpers\DateRanges`) — the key, the label, the closed from/to pair behind the link, and which one is currently applied. The chip row, the count printed on each chip and the applied-filters strip all read those keys, so a chip can never name a month its link does not filter by; the strip now says **“Period: Last month”** instead of spelling out two dates, and each chip's count is the number of rows that chip would show, respecting the other filters. The ranges resolve in the **business timezone**, not the server's, and `entry_date` was already indexed, so the four counts are cheap.

- **Five new guards in `design-check.cjs`** (43 → 48): the card is a screen-bounded flex column; the form fills it and may shrink; the body owns the overflow; the action row stays put; the scroll never depends on `:has()`; and every dialog in every template keeps its body inside the card or the card's form.
- **One new guard in `blade-check.cjs`** (8 → 9): every `@php` block has balanced brackets. It needed a small scanner rather than a regex — an apostrophe inside a PHP comment (“the paginator's page”) otherwise swallows the rest of the block, and the guard reads green while the page is broken. That is exactly how a sweep once shipped a parse error.
- **Five new guards in `list-check.cjs`** (85 → 90): the ranges are declared once in render order; the list takes them from the shared helper instead of doing its own month math; a count is computed for every chip; each chip carries its own label, range, count and lit state; and the applied chip names the period.

**Verification**

```
design 48/0 (was 43)   blade 9/0 (was 8)   mark 46/0   list 90/0 (was 85)   status 20/0   cost 29/0
```

Nine mutations, nine red: the form stops filling the card, the scroll goes back behind `:has()`, the body stops shrinking, the action row unpins, a dialog body moves one level deeper, a range disappears from the shared list, a chip loses its own label, the controller stops sending the labels, and a bracket breaks inside an `@php` block. An apostrophe placed inside a PHP comment on purpose stays green — no false positive.

The period chips are on the cashflow list, where the date-range filter already exists; they come from the shared helper, so any list that gains a date-range filter (the shipments list's `from_date` is a single open end today) gets the same four chips from the same keys.


## Part 12ac — what the cashflow module should do next (f71f4a2)

No code: an audit plus a sequenced plan, kept in the repo at `docs/cashflow-ux-roadmap.md` so it sits with the module it describes.

The audit first records what the module **already** does — ledger with running balance and bank reference, accounts/categories/masters, the shared list surface with saved views and period chips, reports with a PDF export, the vendor-payment and shipment-cost mirrors, and the vendor currency statement tab on `vendors/show` — so nothing gets rebuilt.

Then it maps the four monthly manual jobs onto concrete gaps:

| Today | Feature that removes it |
| --- | --- |
| GST filing pack: sales + purchase bills + bank statement with narration and party | Month-close pack (one button → ZIP/PDF with sales register, purchase register, bank statement, documents, reconciliation summary) + CSV/XLSX exports + GST split on spend |
| Bills kept month-wise in Google Drive | Per-entry documents (new `cashflow_attachments`, following `vendor_payment_attachments`) + a Documents view with a "missing bill" flag |
| Client / project / vendor / employee / category-wise reports | Project and employee as real dimensions + a group-by report builder with period comparison and drill-down |
| Statement to client/vendor, vendor in foreign currency | Shareable party statement — PDF, expiring link, INR or vendor currency, opening + running balance, ageing |

Waves: **1** the accountant loop (documents, month-close pack, exports, GST split, recurring) · **2** reporting (dimensions, report builder, shareable statements, ageing) · **3** control (bank reconciliation, approvals + audit log, forecast and budgets) · **4** later (multi-currency revaluation, cheque lifecycle, OCR, bank feeds, Tally bridge).

Six decisions are listed as open, because they change the build: purchase bills vs cashflow rows, share link vs emailed ZIP, which bank file type, employee master vs login users, approval thresholds, and where the exchange rate comes from.

Gates unchanged and green: design 48 · blade 9 · mark 46 · list 90 · status 20 · cost 29.


## Part 12ad — the choice chips lose their box, and the option list stops hiding behind the dialog (f8414b5)

**Credit/Debit carried a border box the control never drew.** The segmented control's label was `class="master-chip"` — a name `master-detail.css` already owns for the record page's small neutral meta chip (`padding: 4px 9px; border: 1px solid; border-radius: 8px`), and `master-detail.css` loads *after* `master-form.css`. So the meta chip's box was painted around every choice, with the pill sitting inside it. A shared class name is not a style bug you can override away — the next sheet to load moves it again — so the control is now `.master-choice-*` and belongs to nobody else. It also draws no box of its own: an unselected chip is a soft fill (`--mc-soft`), hover is one fill step (`--mc-soft` → `--mc-border`, which reads darker in light and lighter in dark), the selected chip keeps its module colour, and keyboard focus brings a ring because there is no box left to show focus. The two copies of the component (`master-form.css`, `master-index.css`) are identical for the first time.

Side effects worth knowing: the meta chip keeps its name and loses a `cursor: pointer` it never earned, and its icons go back to 10px while the choice chip's stay 13px — both were collateral damage of the shared name. Six views use the control (cashflows quick entry + detailed form, products form + index bulk editor, vendor quotes form, shipments form).

**The Category list was cut off by the modal.** Select2 was told to parent its option list *inside* the modal card, so the card's `overflow: hidden` clipped it at the card's edge and the sticky action row painted over it — and now that the body is the scroll region, the list had nowhere to go at all. The panel is portaled to `<body>` instead, which is the doctrine this codebase already follows for row menus: on the body no scrolling or `overflow: hidden` ancestor can clip it and no stacking context can trap it. Select2 keeps its own place — it binds `scroll` to every scrolling ancestor of the control and flips the list above the field when the window leaves no room below — and the dropdown's `z-index` now sits above the dialog (`9999` → `10050`), because a body-parented panel is outside the dialog's stacking context. A dropdown whose modal closes while it is open closes with it, so nothing hangs over the page afterwards.

**Ten new guards in `design-check.cjs` (48 → 58):** the choice chip has exactly one owner · no view or sheet still calls it by the meta chip's name · no choice label wears the meta chip class · the chip draws no box · its fill and its hover come from theme tokens · it keeps a keyboard focus ring · the two copies are identical · a select's option list is parented to the page and not to the dialog · its z-index is above the dialog's (that one compares the two values rather than pinning them). **Nine mutations, nine red**, each caught by its own guard, with the tree restored green between each.

```
design 58/0 (was 48)   blade 9/0   mark 46/0   list 90/0   status 20/0   cost 29/0
```

---
