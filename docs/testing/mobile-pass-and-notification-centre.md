# Manual cases — mobile pass & notification centre (October 2026)

Manual verification for the two legs merged onto PR #10 head `ef4886a`:

1. **Mobile pass** — dispatch form, shipment detail, attachments, delivery-receipt entry/history, packaging lists at ≤767px (pin: `responsive-check.cjs` rule 6).
2. **Notification centre** — `/office-alerts/centre` trays, filters, desk scoping, plain-form actions, and the unchanged bell dropdown/popup flow.

CI already proves the static contract and the server logic (8 contract rules; `OfficeAlertCentreTest`, route/catalogue gates). These cases cover what a browser and a thumb prove that CI cannot.

## Devices & sign-in

- Chrome/Edge DevTools presets: **Galaxy S8 (360×740)**, **iPhone SE (375×667)**, **iPhone 12 Pro (390×844, has the notch/safe-area)**, **iPad Mini (768×1024)**, and a **custom 320×568** portrait (the smallest the contract promises).
- Also do one pass on a real phone if available — DevTools does not reproduce every tap-target issue.
- Sign in as an **office admin** (non-employee role). Keep a second admin (different department, e.g. "sales & marketing") and one **employee** account ready.
- Per case, first confirm a desktop 1440px render is unchanged (the mobile rules must not leak up).

## Test data for the centre

The centre renders whatever alerts exist. To raise a predictable set, run in `php artisan tinker`:

```php
\App\Models\OfficeAlert::create(['event_key'=>'manual.qa','fingerprint'=>'mc:critical','title'=>'Manual QA — custom hold','body'=>'Critical, requires ack, operations.','severity'=>'critical','requires_ack'=>true,'team'=>'operations','action_url'=>'/dashboard','action_label'=>'Open']);
\App\Models\OfficeAlert::create(['event_key'=>'manual.qa','fingerprint'=>'mc:attention','title'=>'Manual QA — KYC waiting','body'=>'Attention, requires ack, sales.','severity'=>'attention','requires_ack'=>true,'team'=>'sales']);
\App\Models\OfficeAlert::create(['event_key'=>'manual.qa','fingerprint'=>'mc:info','title'=>'Manual QA — FYI note','body'=>'Info, no ack, office-wide.','severity'=>'info','requires_ack'=>false]);
```

Refresh any office page once so `ensureToday`/payload runs. Delete later with
`\App\Models\OfficeAlert::where('event_key','manual.qa')->delete();` in tinker.

---

## Part 1 — Mobile pass (MC-MOB)

| # | Screen (start URL) | Steps | Expected |
|---|---|---|---|
| MC-MOB-01 | Dispatch form `/shipments/create` at 320×568 | Open; scroll through the line-items area | No horizontal page scroll; each item is a stacked card with labelled fields; every input reachable by thumb; page scrollbar only vertical |
| MC-MOB-02 | Same, iPhone SE 375 | Add a second item row | New card appends in place; delete/duplicate row buttons ≥40px tall; no layout jump sideways |
| MC-MOB-03 | Same, submit with a deliberate error | Submit with empty party | Validation errors render above/below the correct card fields; no nav element overlaps Submit |
| MC-MOB-04 | Shipment detail `/shipments/{id}` at 375 | Scroll the tracker, costs card, attachments card | Everything reads top-to-bottom; no card overflows the viewport; horizontal scrollbar appears **only** inside deliberately scrollable tables |
| MC-MOB-05 | Shipment detail attachments at 375 | Upload/observe ≥3 photos | Grid is **2 columns** (was 4 tight columns); each image reads 4:3; remove (×) buttons are comfortably tappable (≥40px) without zooming |
| MC-MOB-06 | Shipment detail at 320 | Open attachments grid with 1 photo | Single item does not stretch oddly; card stays within 320px |
| MC-MOB-07 | **Record delivery receipt** modal at 375 (iPhone 12 Pro for notch) | From shipment detail, tap *Record delivery receipt* | Modal is a **full-screen sheet**: covers the viewport (100dvh), square corners, no page content peeking behind; address-bar collapse does not crop it |
| MC-MOB-08 | Receipt modal at 320 | Enter quantities for all lines | Each line is a labelled card (labels visible above inputs, not a table); every qty input spans full width; no horizontal scrolling **inside** the sheet or page |
| MC-MOB-09 | Receipt modal, notch phone, iPhone 12 Pro | Scroll to the bottom of the sheet | Footer action buttons sit **above** the home-indicator safe area; last input not hidden behind the keyboard when focused (browser resizes the sheet) |
| MC-MOB-10 | Receipt modal, iPad Mini 768 | Open the same modal at 768px | Desktop layout returns: modalcentred dialog with table columns — the mobile sheet must not leak up |
| MC-MOB-11 | Delivery-receipt history at 375 | Scroll the past-receipts cards on shipment detail | Card header and footer **wrap** onto two lines gracefully; status chips stay one-per-line; no text clipped |
| MC-MOB-12 | Packaging lists `/packaging-lists` at 375 | Index filters → open a list → form edit | Filter chips scroll/snap horizontally as designed; the items table scrolls *inside* its wrapper (allowed by contract); form inputs full-width; submit row reachable |
| MC-MOB-13 | Packaging label preview at 375 | Open the label/print view | Fixed-width label area scrolls horizontally by design (print artefact, exempt); the surrounding page does not; back link tappable |
| MC-MOB-14 | All five surfaces, 375→768 landscape flip | Rotate to landscape and back | Layout re-composes without a refresh; no orphaned horizontal bars after rotation |

**Fail signals for Part 1:** any body-level horizontal scrollbar ≤767px; any element wider than the viewport; a tap target you have to pinch-zoom to hit; the receipt modal showing the old centred-desktop shell with a cramped 9-column table.

## Part 2 — Notification centre (MC-NC)

Seed the three manual alerts first (tinker block above). Also submit one real KYC for an end-to-end critical ack alert if convenient.

| # | Start | Steps | Expected |
|---|---|---|---|
| MC-NC-01 | Bell (topbar) | Click the bell | Dropdown lists the seeded alerts; footer shows **"Open the notification centre"**; badge count matches open unsnoozed items |
| MC-NC-02 | Bell footer | Click the centre link | `/office-alerts/centre` opens; heading "Notification centre"; stat strip shows Open / Needs action / Snoozed / Resolved counts |
| MC-NC-03 | Centre default view | Read the default tray | **Open (all desks)** items listed, newest critical first (severity CASE order); the seeded cards show title, body, desk chip, severity tone colour (red/amber/blue) |
| MC-NC-04 | Tray chips | Click **Snoozed**, then **Resolved** | URL updates (`?state=snoozed`, `?state=resolved`); lists switch; empty trays show the "Nothing waiting"-style empty state, not a blank page |
| MC-NC-05 | Desk + severity chips | Pick *Sales* desk, then *Needs action* severity | Both active chips highlighted; URL carries both (`team=sales&severity=critical`); then click *Resolved* tray — **desk and severity survive the tray change** (shareable URL), and vice versa |
| MC-NC-06 | Shareable URL | Copy room URL with filters set, open in a new tab | Identical filtered view loads — no session-only state |
| MC-NC-07 | Ack from the centre (JS on) | On the *Manual QA — custom hold* card click **Mark read** (ack-required alerts use this label) | Card vanishes from Open; flash/status confirms; **resolved tray** shows it with *"acknowledged by {your name}"* and timestamp |
| MC-NC-08 | Ack with JS disabled (or DevTools network offline→ordinary form) | Disable JavaScript, repeat ack on the *KYC waiting* card | Plain form POST → **302 back to the centre** with status flash (no JSON rendered in the browser); resolved row present |
| MC-NC-09 | Mark read (non-ack alert) | On the *FYI note* card click **Got it** (non-ack alerts use this label) | Leaves Open → appears in Resolved as *"you marked it seen"*; other admins' Open trays still show their own copy until they read it |
| MC-NC-10 | Snooze presets | Snooze the FYI/attention card with the **1h** preset (1h / 4h / Tomorrow 9:00 buttons) on the centre | Card moves to the Snoozed tray with its wake time; bell dropdown shows it dimmed at the bottom; after the hour (or via tinker `office_alert_states` update setting `snoozed_until` to the past) it returns to Open |
| MC-NC-11 | Shared acknowledgement | Have the **second admin** open the centre after you acked the critical alert | They do **not** see it in Open; their Resolved tray shows it and names **you** as the resolver (shared ack resolves it office-wide) |
| MC-NC-12 | Desk scoping | Sign in as the sales-department admin | Centre shows sales + office-wide briefings only; opening `?team=operations` shows the empty state, never the operations cards |
| MC-NC-13 | Employee wall | Sign in as the **employee**, visit the URL directly | Redirected to their own dashboard (office middleware), never the centre; bell never renders for them anywhere |
| MC-NC-14 | Settings off | As settings manager, disable briefings in **Settings → Briefings**, reload centre | Centre shows the *disabled* banner with a link back to settings instead of the trays; bell unread = 0. Re-enable afterwards |
| MC-NC-15 | Bell regression | Back at any page, click a bell item's link; wait for/raise a critical ack-required alert | Bell item navigation works; the critical modal popup still appears once and acks/snoozes through the same endpoints (JSON flow unchanged) |
| MC-NC-16 | Centre at 375 | Filter rows + card actions on a phone | Chips wrap/tap cleanly; card action buttons (Got it / snooze presets) full-width tappable; nothing overflows |

**Fail signals for Part 2:** resolved items that lose the resolver's name; filters that reset each other; JSON dumped to the browser on form posts; employees reaching the page; the bell badge disagreeing with the Open tray; the snoozed card reappearing in Open before its wake time.

## After the pass

- Delete the seeded alerts (tinker snippet above).
- Note any visual-only findings (spacing, contrast) separately from functional failures — the latter block the merge, the former can follow as polish.
- If a case fails on a device geometry, capture: URL, viewport, screenshot, and whether it passes at 375px (narrows down media-query vs content bugs).
