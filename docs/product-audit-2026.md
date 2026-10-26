# MissPack ERP — Product Audit & Improvement Roadmap

**Date:** 2026-10-25
**Auditor:** Senior PM / ERP Business Analyst / Laravel Architect / UX / QA (combined review)
**Basis:** Full static review of the codebase at `development` HEAD (~50k LOC PHP, 96 models, 77 controllers, 80 services, 250 Blade views, 399 routes, 66 test files, `docs/`, `tools/checks/`).

---

## 1. Executive summary

MissPack ERP is already a **genuinely broad operational system** for an Indian
packaging business: leads → products → projects (with execution workflows,
approvals, quality inspections and financial closure) → sales & purchase
invoicing → cashflow ledger → shipments, packaging lists and delivery receipts →
client portal, vendor portal, employee workspace, feedback, fixed assets, notes,
tasks, audit log and a fine-grained permission matrix. The documentation
(`docs/`), contract checks (`tools/checks/`), and drafted test strategy are
well above the norm for an SMB in-house ERP.

The gaps that matter most are not code quality — they are **whole business
capabilities that are missing or stop halfway**:

| # | Finding | Why it hurts | Priority |
|---|---------|--------------|----------|
| 1 | **No inventory/stock module.** Products have a boolean `ready_stock_available`; nothing tracks quantities, warehouses, movements or reorder points. | The single most-asked ERP question — *"how many do we have?"* — is unanswerable. Packaging lists and shipments never decrement stock. | P0 |
| 2 | **Collections are manual.** The only `Mail` class in the app is `OfficeBriefingMail`. Invoice reminders (`SalesInvoiceReminder`) are logs of phone/WhatsApp/email done *outside* the system; no invoice is ever emailed, no dunning sequence runs. | Slower cash collection, revenue leakage, no audit of what the customer was sent. | P0 |
| 3 | ~~**Office login has no rate limit and a 4-char minimum password** (`AuthController`, routes `/login` vs portal logins which *are* throttled). No 2FA anywhere.~~ **Resolved 2026-10-25:** email+IP lockout with audited throttling, single `Password::defaults()` strength rule, TOTP two-factor with challenge flow and single-use recovery codes — see [login-security.md](login-security.md). | The admin surface is the weakest-protected door into the whole business. | P0 |
| 4 | **Credit control is dead data.** `clients.credit_limit` / `credit_days` exist but are referenced by *no* invoice or summary logic. | Sales can keep invoicing a client who is over limit and overdue — the control the column promises does not exist. | P1 |
| 5 | **Purchase cycle has no PO → GRN → 3-way-match, and no credit/debit notes or returns exist anywhere.** | Price/quantity disagreements with vendors and customer returns are handled by editing history, which is audit-unfriendly and GST-risky. | P1 |
| 6 | **Quotation module was dropped** (`drop_quote_modules`) but `sales_invoices.customer_quote_id` remains; the sales journey jumps lead → proforma. | Dead schema + a Process gap between enquiry and proforma. | P2 |
| 7 | **PDF generation is a silent optional extra** — `class_exists(Dompdf…)` checks everywhere, but `barryvdh/laravel-dompdf` is **not in `composer.json`**. | On a fresh deploy, every "Download PDF" quietly degrades to print views. | P1 |
| 8 | **Multi-write endpoints mostly run without DB transactions** (only 16 of 77 controllers mention `DB::transaction`). | A mid-request failure leaves invoice items, cashflow syncs and allocations half-written. | P1 |
| 9 | **No e-invoice (IRN/QR) support, no TDS on vendor payments, no bank reconciliation, no FX revaluation.** | Compliance ceiling as turnover grows past the ₹5 cr e-invoice mandate; manual Tally rework at year end. | P2 |
| 10 | **`audit_logs` grows unbounded** — middleware audits every mutation, with no retention/prune/archive job. | Table bloat slows backups and the audit UI itself. | P2 |
| 11 | **No `.env.example`** though `composer setup` copies it. | New environment setup breaks at step one. | P1 (trivial fix) |
| 12 | ~~**Single organisation/GSTIN only; no API layer (web routes only); mobile responsiveness partial.**~~ **Mobile/tablet closed 2026-10-25:** the responsive contract was largely built (shell drawer, per-breakpoint grid maps, card-tables, sheet modals); residual gaps fixed (price-calculator + products mobile grids, unwrapped statement table) and the whole contract is now CI-enforced — see [responsive-design.md](responsive-design.md), `tools/checks/responsive-check.cjs`. Multi-org and API layer remain open. | Scaling ceilings: second entity, mobile app/integrations (WhatsApp, transporter APIs), shop-floor phones at dispatch. | P2 |

Sections 3–7 detail every finding with evidence; §8 sequences them into a
roadmap; §9 proposes the KPI layer to instrument once the above lands.

---

## 2. What is genuinely strong (protect these)

1. **Domain depth** — GST splits with seller/client *snapshots* on invoices
   (historical integrity), e-way bill fields, HSN/SAC on lines, packaging lists
   with carton-level labels + QR + verification, delivery receipts, shipment
   costs synced into cashflow. This is packaging-industry fit, not generic CRUD.
2. **Project execution rigour** — workflow templates, gates, approval requests
   with versioned overrides, quality inspections, exception registry, financial
   closure. Few SMB ERPs have this.
3. **Money stored as decimals** (133 decimal columns) with document-currency +
   INR denomination on vendor payments and allocation records
   (`cashflow_allocations`) instead of naïve "paid" flags.
4. **Security & governance bones** — permission matrix enforced in middleware
   with audit-on-deny, audit log with actor/route/IP, soft deletes on projects,
   expiring vendor share links, token-based public shares (`Str::random(48)`),
   throttled *portal* logins and support endpoints, upload policy service.
5. **Operational cadence** — scheduled recurring-cashflow sweep, feedback
   closure job, daily 09:15 office briefing email + hourly chase. An ERP that
   nags is an ERP that gets used.
6. **Engineering culture** — docs per module, a TDD playbook, integration test
   catalogue with traceability, contract checks wired into `npm run
   test:contracts`, CI workflow `erp-tests.yml`.

---

## 3. Business & product gaps (PM lens)

### 3.1 P0 — Inventory & warehouse management (missing module)
**Evidence:** `products` table has `ready_stock_available` (boolean),
`ready_stock_moq`, but no quantity, location, movement or valuation fields; no
stock tables exist in 120+ migrations. Packaging list cartons and shipment items
reference products but never decrement anything.

**What to build (increments):**
1. **Stock ledger MVP:** `warehouses`, `stock_movements` (type: receipt /
   issue / adjustment / return; qty; unit cost; reference to purchase invoice,
   shipment, packaging list or manual), `stock_balances` (product × warehouse,
   derived + cached from the ledger). Never store a mutable "current qty" as the
   source of truth.
2. **Automatic postings:** purchase invoice receipt (optional, per line) →
   receipt; shipment dispatch → issue; packaging list verification → reserve →
   issue on dispatch.
3. **Controls:** reorder point + low-stock office alert (you already have
   `office_alerts` — perfect sink), negative-stock prevention, stock-take /
   cycle-count adjustment flow.
4. **Later:** batch/lot & expiry (food-adjacent packaging customers ask for
   this), raw-material vs finished-goods split, BOM for kitting.

### 3.2 P0 — Collections & communication automation
**Evidence:** `app/Mail/` contains only `OfficeBriefingMail`;
`SalesInvoiceReminder` stores `channel` = whatsapp/email/phone as a *log* of
manual activity (comment in controller: "The office rings, WhatsApps and emails
all month; this is where that gets written down").

**What to build:**
1. **Send the invoice:** "Email invoice to client" mailable (PDF attached),
   tracked as an event on the invoice; "Send" transitions draft → sent.
2. **Dunning engine:** configurable schedule per client or per invoice
   (e.g. −3 days, due date, +3, +7, +15), generating reminder emails and/or
   office-alert tasks for calls; stop automatically on payment allocation.
3. **Statement auto-share:** monthly party-statement email with the existing
   secure share link.
4. **WhatsApp-first:** the team already works on WhatsApp — generate deep links
   (`wa.me` with prefilled text + PDF link) as a zero-integration first step
   before committing to the WhatsApp Business API.

### 3.3 P1 — Credit control activation
**Evidence:** `credit_limit` (decimal) + `credit_days` on `clients` are dead —
no reference in `SalesInvoiceController` or `ClientAccountSummary`.

**What to build:** compute exposure = open invoice balance + unallocated
advance; block or warn (with override + audit, using the existing
approval-override pattern) when a new invoice would breach the limit; surface
exposure and overdue days on the client profile header; auto-hold new proforma
confirmation when any tax invoice is overdue > X days.

### 3.4 P1 — Purchase cycle completeness (PO → GRN → match)
**Evidence:** `purchase_invoices` + vendor payments + vendor portal updates
exist; no purchase orders, no goods-receipt step, no quantity/price variance
check. Vendor ratings absent. (The "3-way match" is what turns a vendor KYC +
portal into actual control.)

**What to build:** PO with statuses (draft → approved → sent → partly received
→ closed); GRN against PO (qty accepted/rejected, links into inventory of
§3.1); match report PO vs GRN vs invoice with variance flags; vendor scorecard
(on-time %, rejection %, price variance) fed from shipment/GRN data you already
capture.

### 3.5 P1 — Credit/debit notes, returns & invoice discipline
**Evidence:** statuses include `cancelled`; no credit-note/debit-note/return
document types; no evidence of locking issued tax invoices from edit.

- Add **invoice state machine**: once `sent` (tax invoice), monetary fields
  become immutable; corrections only via credit note (against invoice, restocks
  inventory optionally, reduces receivable) / debit note. Cancellation of a tax
  invoice should require reason + mirror e-way bill cancellation.
- Sales returns: return document → restock movement + credit note in one flow.

### 3.6 P2 — Quotation resurrection or cleanup
**Evidence:** migration `2026_10_06_010000_drop_quote_modules.php`; dead column
`sales_invoices.customer_quote_id`.

Either re-introduce a light quotation (lead → quote → one-click convert to
proforma) — the natural pre-proforma step for custom packaging deals — or drop
the leftover column and references. Decide; don't leave the scar tissue.

### 3.7 P2 — Structural ceilings
- **Single organisation**: `organisations` is effectively a singleton (one
  GSTIN/PAN/IEC). If a second GST registration or branch is ever added, every
  document's seller snapshot must become selectable per entity. Multi-entity is
  much cheaper to design now (add `organisation_id` to documents) than to
  retrofit.
- **No API layer**: `shouldRenderJsonWhen` anticipates `api/*` but no API
  routes exist; no Sanctum/passport package. Any integration (Tally/Zoho handoff,
  transporter tracking, WhatsApp API, IndiaMART/website lead capture beyond the
  one public form, a mobile app) needs token-authenticated APIs.
- **HR**: payslips + documents are fine for a small team; leave/attendance and
  advance-salary tracking are the next asks (employment statuses already exist).

---

## 4. Financial accuracy & compliance gaps (BA lens)

| Finding | Evidence | Recommendation |
|---|---|---|
| **No e-invoicing readiness** | No IRN/QR fields or generation for tax invoices (hits ~₹5 cr turnover). E-way bill numbers are hand-entered. | Roadmap: IRP integration (or via GSP), store IRN + signed QR on invoice, print QR on the tax invoice PDF. Even before mandate, prep the schema. |
| **GST correctness is manual** | `gst_type` defaults `intra_state`; CGST/SGST vs IGST split per line appears user-driven. `place_of_supply` stored but not evidently deriving the split. ~~No HSN/GST-rate master.~~ **Progress 2026-10-26**: GSTIN checksum validation + HSN/SAC rate master shipped ([e-invoice.md](e-invoice.md)); split derivation and rounding policy remain open. | Derive split from seller state vs place of supply; ~~validate GSTIN format~~ (done: 15-char checksum); ~~HSN + rate master table~~ (done); round per-line vs per-document consistently (document a single rounding rule). |
| **No TDS/TCS tracking** | Vendor payments deduct full amounts; Indian SMBs deduct TDS (194C/194J) and sometimes collect TCS (206C(1H)). | Add TDS section, rate, deducted amount on vendor payment entries; TDS payable report for the accountant. |
| **No bank reconciliation** | Cashflow accounts exist; no statement import/matching. | CSV import per account, auto-match by amount+date+narration, unmatched queue. This is the feature that makes the ledger *trustworthy*. |
| **FX handling stops at entry** | `exchange_rate` per entry (decimal 16,6) + INR denomination on vendor payments — good at posting time. Open foreign-currency balances are not revalued at period end. | Period-end revaluation report (unrealised gain/loss) for open invoices. |
| **Fixed assets are informational** | Depreciation engine exists (`AssetDepreciation`, tests) but posts nowhere — there is no GL, and cashflow is single-entry cash movement. | Acceptable scope choice. Document the boundary + a clean year-end export (Tally-friendly CSV) so the accountant reconciles once, cleanly. |
| **PDF dependency is phantom** | `class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)` guards in Cashflow/PartyStatement/Shipment/DeliveryReceipt controllers, but the package is absent from `composer.json`. | Either `composer require barryvdh/laravel-dompdf` (they clearly want PDFs) or delete the branches. Silent optional features rot. |
| **Audit log unbounded** | `AuditMutations` middleware on the whole `web` group writes a row per mutation; no prune/archive (no retention logic found). | Scheduled prune with archive-to-file (or quarterly partition), plus index review on `occurred_at`. Also make audit writes queued/async long-term. |

---

## 5. Architecture & security (Laravel architect lens)

**P0 – Harden the front door.**
- `/login` POST has **no throttle** (portal logins have `throttle:10,1`) and
  validation allows `password: min:4`. Add `throttle:5,1` + lockout messaging,
  raise minimum length (12+), consider `Password::defaults()` rules, and
  TOTP 2FA for admins (the audit log already captures auth events to alert on).

**P1 – Make writes atomic.** Only 16/77 controllers use `DB::transaction`.
Invoice + items + cashflow sync, shipment + costs + cashflow sync, conversion
flows (proforma → tax, cash conversion) are multi-model writes. Rule: *every
endpoint that writes >1 model wraps a transaction* — enforce via the service
layer (services like `CashInvoiceConversion` are the right home) and add a
contract check (you already have a checks harness that can grep for this).

**P1 – Tame the fat controllers.** `VendorController` 1,779 LOC,
`SalesInvoiceController` 1,619, `PurchaseInvoiceController` 1,359 — with
validation inline (no `app/Http/Requests`). Extract Form Requests + single-action
invokables/actions per use case; keep controllers as traffic cops. This
directly raises test coverage quality because request validation becomes
unit-testable.

**P1 – Fix deployment basics.**
- Add `.env.example` (composer `setup` references it; it is missing).
- Document queue worker + scheduler + cache driver expectations (database
  defaults are fine at this scale, but `MAIL_MAILER=log` as shipped default
  means no mail ever leaves a fresh box — with §3.2 that changes anyway).
- Add backup/restore runbook (DB + `storage/`), and a staging environment note.

**P2 – Structural evolution.**
- Versioned API with Sanctum when the first integration lands; do not build it
  speculatively before §3.2/§3.7 pick the consumer.
- Domain events exist for KYC and shipment status — extend the pattern
  (`InvoiceIssued`, `PaymentReceived`, `ProjectGateFailed`) so notifications,
  briefings and future webhooks hang off events, not controller code.
- Global search does per-model `LIKE` scans across 13 sources and calls
  `Schema::hasTable()` per source per query — cache the schema checks; move to
  full-text indexes (MySQL `FULLTEXT`) when any table crosses ~100k rows.
- `audit_logs` (§4) and `shipment_tracking_histories`/`client_portal_*` event
  tables need retention jobs as data accrues.
- Keep the monolith. The modular boundaries (services per module) are good;
  the win is discipline inside it, not micro-services.

---

## 6. UX / UI gaps (design lens)

Strengths to keep: design-token CSS architecture + documented guidelines
(`docs/css-architecture.md`, `ui-design-guidelines.md`), saved views, global
search, module dashboards, print-specific styles for labels/statements.

1. **Mobile-first the shop-floor flows.** Only ~3 page stylesheets contain media
   queries. Prioritise: packaging verification, delivery-receipt capture,
   shipment dispatch, and office alerts — these happen on phones in the
   warehouse/dispatch, not at desks. Dashboards can stay desktop-first.
2. **Table operations.** Lists are the ERP's home screen. Add: bulk actions
   (status change, assign, export), column show/hide persisted to the existing
   saved views, sticky headers on long lists, and keyboard navigation hints.
3. **A notification centre for office users.** `office_alerts` + briefings are
   close; add an in-app bell aggregating alerts, pending approvals (project
   gate requests), overdue invoices, and failed jobs — badge in the topbar.
   Task assignments today rely on the 09:15 digest; too slow for same-day work.
4. **First-run & empty states.** Provide a setup wizard (organisation profile →
   taxes/HSN → users → cashflow accounts → branding) and intentional empty
   states with a primary action on every list. Demo seeders exist; make a
   `demo:seed` for sales/staging.
5. **Form ergonomics on big documents.** Invoice/PO-style forms with dynamic
   lines: guarantee add/remove/reorder keyboard support, autosave drafts
   (status `draft` exists — let people leave and resume), and inline totals
   (subtotal/tax/round-off) computed client-side to match server-side exactly
   (one shared rounding rule — see §4).
6. **Accessibility pass.** Contrast tokens, focus-visible styles, aria labels on
   icon buttons, printable views remain clean. Cheap now, expensive later.
7. **Cross-portal consistency.** Office, client portal and vendor portal should
   visibly be one product (shared tokens already exist — extend to components).

---

## 7. QA & process gaps

1. **Keep CI, add gates.** `erp-tests.yml` exists; add: coverage reporting
   (threshold on critical services — cashflow, invoice money math), `pint --test`
   in CI, Composer/npm audit, and a migration-freshness check (migrate:fresh +
   seed on a scratch DB).
2. **Real E2E on golden paths.** The `tools/checks/*browser.cjs` scripts are
   hand-run; promote the top journeys into an automated suite (login → create
   client → proforma → convert to tax invoice → record payment → allocate →
   statement PDF; and PO/GRN flows once built).
3. **Data safety in non-prod.** Policy + scrubber so staging/demo never holds
   real GSTIN/bank/payslip data.
4. **Definition-of-done hooks.** PR template referencing the existing
   traceability catalogue (`docs/testing/traceability.md`) + contract checks —
   the machinery exists; make it mandatory ceremony.
5. **Performance smoke.** A `php artisan` diag or check script that times the
   dashboard + heaviest list with seeded volume (say 50k invoices/lines) so
   index regressions show up before users do.

---

## 8. Recommended roadmap

### Now (0–1 month) — protect & un-break
- [x] Throttle `/login`, password policy upgrade, 2FA for admins (P0, sec) — **shipped 2026-10-25** ([login-security.md](login-security.md)); remaining follow-up: make 2FA mandatory for admins via an office setting
- [ ] `.env.example`; add `barryvdh/laravel-dompdf` to composer or remove the branches (P1)
- [ ] Transaction-wrapping pass on multi-write endpoints + a `tools/checks` rule (P1)
- [ ] Audit-log retention job (P2 but trivial)
- [ ] Decide quotations: restore-lite or remove `customer_quote_id` (P2)
- [x] "Email invoice to client" + send-event tracking (first slice of P0 collections) — **shipped 2026-10-26**: compose dialog on the invoice record page and list row menu (defaults from `SalesInvoice::emailDefaults()` — snapshot e-mail, then the client card's accounts desk); every attempt lands in `sales_invoice_sends` with recipients/subject/message/sender/status, so "we never received it" is answerable; failures keep the transport's reason *and* brief the accounts desk through the notification centre; mark-sent/portal flip rides the send by default, skipped for re-sends. See [collections-outreach.md](collections-outreach.md). The dunning schedule and open-tracking remain open.

### Next (1–3 months) — cash & control
- [ ] Dunning engine + statement auto-share + WhatsApp deep links (P0)
- [ ] Inventory MVP: warehouses, stock ledger, receipts from purchases, issues on dispatch, low-stock alerts (P0)
- [ ] Credit-limit enforcement with audited overrides (P1)
- [ ] Invoice state machine lock + credit/debit notes + returns (P1)
- [ ] PO → GRN → 3-way match (P1)
- [ ] Bank reconciliation import (P1)
- [x] Mobile pass on dispatch/packaging/receipt flows; notification centre (UX) — **mobile half shipped 2026-10-25**: responsive contract audited, remaining module gaps fixed, CI enforcement added (responsive-check.cjs); notification centre still open
- [ ] Coverage gate + automated E2E golden paths (QA)

### Later (3–6+ months) — scale & compliance ceiling
- [x] E-invoice (IRN/QR) schema + integration; GSTIN checksum + HSN/rate master (P2) — **shipped 2026-10-26**: mod-36 GSTIN checksum enforced at every collection door; Taxes & HSN master in Settings (Invoice/purchase forms autofill code→rate); per-invoice IRN/QR lifecycle (generate → ack → cancel-in-24h) with manual and HTTP gateway providers; invoice print carries the signed QR via the offline QR generator (extended to v20, pinned by golden tests). See [e-invoice.md](e-invoice.md). The GST split derivation from place-of-supply remains open.
- [ ] TDS on vendor payments + reports (P2)
- [ ] Multi-entity/branch groundwork (`organisation_id` on documents) (P2)
- [ ] Sanctum API for the first real integration; FX revaluation report (P2)
- [ ] Vendor scorecards; leave/attendance; inventory batches/BOM (P2+)

---

## 9. Instrument these KPIs once the above lands

- **Cash:** DSO, overdue %, dunning response rate, unallocated cash age.
- **Order-to-delivery:** quote/proforma → dispatch lead time, on-time dispatch %,
  packaging verification first-pass rate, delivery-receipt cycle time.
- **Purchase:** PO-vs-invoice variance %, vendor on-time %, GRN rejection %.
- **Inventory:** stock accuracy (cycle-count variance), stockout incidents,
  dead-stock value.
- **Adoption:** daily active office users, alerts acted-upon within 24h, portal
  logins per client/month (portal engagement is your service-quality proxy).

---

*Prepared as a living document: revisit quarterly; close items in the roadmap
checklist as they ship.*
