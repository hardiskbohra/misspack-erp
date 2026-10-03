# Cashflow module — UX & industry-standard feature roadmap

Written after auditing what the module already does, so every item below is a **gap
or an upgrade**, not a rebuild. File references are to this repo.

---

## 1. What the module already gives you (don't rebuild these)

| Area | Today |
| --- | --- |
| Ledger row | date, particular, invoice/bill no, bank reference, credit/debit, running balance, currency, account, category, payment mode, related party (client/vendor/expense/owner/employee/other) + name, project, linked sales invoice, notes, created-by |
| Accounts & masters | current/saving/cash accounts, categories, and master options (payment mode, expense head, related party…) — `cashflows/settings.blade.php` |
| Working surface | quick-view chips, saved views (private/shared), applied-filters strip, density switch, day-grouped rows, totals row, mobile cards, pinned header — the shared `master-list.css` layer |
| Period chips | This month · Last month · This year · Last year, with counts (`app/Helpers/DateRanges.php`) |
| Reports | period + report type (overall / client / vendor / cash expense), account summary, category summary, statement entries with bill + reference, **PDF export** — `CashflowController::reportData()` |
| Status | pending → booked → reconciled → disputed / ignored (manual only) |
| Automation | vendor payment → one action writes the vendor-currency bill/payment **and** the INR cashflow row; shipment cost heads mirror in too |
| Vendor side | `vendors/show` already has a **Vendor Currency Statement** tab (foreign amount + rate + INR), plus a `vendor_payment_attachments` table |

## 2. Your four manual jobs → what removes them

| Your job today | Feature that removes it |
| --- | --- |
| 1. Monthly GST filing: sales + purchase bills + bank statement with narration & party | Month-close pack (Wave 1B) + CSV/XLSX export (1C) + GST split on purchase rows (1D) + attachments so the bills travel with the entries (1A) |
| 2. Bills/invoices kept month-wise in Google Drive | Per-entry document attachments + a Documents archive with "missing documents" view (1A) |
| 3. Client / project / vendor / employee / category-wise reports | Project + employee as real dimensions, a group-by report builder with period comparison and drill-down (Wave 2A) |
| 4. Statement to client/vendor, vendor in foreign currency | Shareable party statement: PDF + expiring link, INR or vendor currency, opening balance and running balance (Wave 2B) |

---

## Wave 1 — the accountant loop (highest value, smallest surface)

**1A. Documents on an entry.** New `cashflow_attachments` table and upload UI on the
entry form + show page, following the existing `vendor_payment_attachments` /
`shipment_attachments` pattern (allowed types, 10 MB, drag-drop, camera on mobile).
Then: reuse one document across several entries (a single bank advice covering
20 lines), a **Documents** view in the module (filter by month / party / type /
missing), and a "no bill attached" flag on the ledger row so month-end has a
visible to-do list instead of a Drive folder.

**1B. Month-close pack — one button.** For a chosen month: a ZIP (and a printable
PDF summary) containing

- sales register (from `sales_invoices`: intra/inter/export, GSTIN, place of supply, tax split)
- purchase/expense register with GST split and ITC flag (needs 1D)
- bank statement export with **narration + party name per entry** (the ledger already has both)
- the attached documents, and
- a reconciliation summary (booked vs bank, unreconciled list)

Optional: email it to the accountant in one action. This single feature replaces
the monthly assemble-and-send ritual.

**1C. Exports accountants actually use.** CSV/XLSX for the ledger and every report
(the module has PDF only today). Columns labelled the way Tally/Excel expect
(voucher date, voucher type, ledger, party, narration, debit, credit, ref).

**1D. GST fields on spend.** CGST / SGST / IGST / cess split, HSN-SAC, supply type
(intra/inter/import), ITC eligibility, TDS section — and a proper **purchase bill**
concept: `vendor_payments` already has `entry_category = bill`, so a bill can carry
its tax split and be paid later, which is what makes a purchase register trustworthy.

**1E. Recurring entries + reminders.** Rent, salaries, GST/TDS challans,
subscriptions: a rule (amount, category, party, day, account) that drafts the entry
on its date and waits for confirmation. Also "expected but not recorded" reminders
for bills falling due.

## Wave 2 — reporting and sharing

**2A. Dimensions and the report builder.** Promote `employee` from free text to a
real link (users already carry department/designation), keep project/client/vendor/
category, then: group-by any dimension × period (month/quarter/year), compare
(this vs last vs same period last year), drill from a total straight to the rows,
save the result as a saved view, export it. Replaces the "sometimes needed" ad-hoc
requests, because you can build them yourself in ten seconds.

*Shipped, with the trade's own reading of "compare":* a comparison in a report is
the **whole axis shifted**, never the row above it — comparing April with March
inside an April–June report counts March twice, once in its own column and once as
April's comparison. Delivered as:

- **Cashflow → Reports** — one screen with the period range, the saved views, and
  the builder: **Group by** any of twelve dimensions (overall, client, vendor,
  employee, account, category, expense head, payment mode, credit/debit, accounting
  status, project, currency) × **Period** (month / quarter / year) × **Figure** (net,
  credit, debit) × **Compare** (nothing, the previous period of the same length, or
  the same period last year). It opens on the year to date, and a year bucket opens
  on five years, because "this month" is not a report.
- **A total you can open.** Every row name, every cell and the footer are links: the
  cell carries its dimension, its bucket's exact first and last day, and the sort, so
  the ledger it opens sums to the figure on the page. The ledger shows what arrived
  with it as removable chips, like any other filter.
- **Employee is a link, not a name.** `cashflow_entries.employee_id` points at
  `users`, the entry form and Quick Entry both offer the person (with designation),
  and the migration matches what was already typed by exact name — anything that did
  not match keeps its text and reads as "not set" in the report, where it can be
  fixed rather than quietly renumbered.
- **A mixed range says so.** Rupees, dollars and yuan are never dressed as one
  currency: when a window holds more than one, the figures carry no sign at all and
  the page names the currencies in it — and the currency is a dimension, so
  filtering to one is a click away.
- **Export** — CSV (Excel-ready, with the figure, the comparison and every period
  column) and the print/PDF sheet, both cut to the same window as the screen.
- **Up to 36 buckets**, and a longer range says it was cut instead of silently
  dropping a period.

*Left for 1C / 2C, not 2A:* the accountant pack and XLSX are 1C; the age-ordered
worklist across parties is 2C.

**2B. Shareable party statements.** A statement per client/vendor: opening balance,
running balance, ageing (0–30 / 31–60 / 61–90 / 90+), and for vendors a
**foreign-currency column set** (foreign amount, rate, INR). Deliver as PDF, as an
**expiring public link** (the `public_token` pattern already used for clients,
leads and invoices), and as a page in the client portal so clients self-serve. Sent
statements get logged, so "I sent that on the 5th" is answerable.

*Shipped (`70c3e1c` + the statement commit), with the trade's own reading of the
last sentence:* a statement is built per **party and currency** — a vendor billed
in RMB and paid in rupees holds two balances, and a line that added them would be
wrong by the exchange rate. Delivered as:

- **Cashflow → Statements** — one line per party and currency with opening / debit
  / credit / closing, filterable by type, period, currency and name. A party with
  a balance carried in but no movement still appears: that is the statement somebody
  forgot to send.
- **The statement itself** — letterhead, party block, summary, ruled rows with a
  running balance, ageing (clients aged from the due date against the invoice
  balance after part payments; vendors from the bill date, which the block says),
  and a foot that reports any other currency on the account rather than adding it in.
- **PDF** (dompdf when installed, print-ready page when not), **an expiring public
  link** (long token, 7/30/90 days or never, revocable in one click, every open
  counted), and **a page in the client portal** so a client with a login never needs
  a link at all.
- **A log**: what was sent, to whom, by which channel, when it expires, and how many
  times it was opened — with the party name copied onto the row so the record
  survives a rename.

*Still to come in 2B:* issuing a month's statements in one run (every party with a
balance, one action), and attaching the month-close pack from 1B to the same link.

**2C. Receivables / payables ageing + reminders.** Buckets per party, what is
overdue, and a one-click reminder email/WhatsApp template.

*Part-started by 2B:* the buckets and the per-document ageing exist on every
statement, and the WhatsApp/email links are generated from the party's own number
and address. What is missing is the age-ordered **worklist across parties** — one
screen of "who is late and by how much" — and a reminder recorded against the party
rather than against one statement.

## Wave 3 — control and foresight

- **3A. Bank reconciliation.** Import the bank CSV/OFX, auto-match on amount + date
  ± window + reference, a queue for unmatched lines, reconcile/un-reconcile with
  who and when. `accounting_status = reconciled` becomes a workflow instead of a
  label you set by hand.
- **3B. Approval + audit trail.** Maker–checker for entries above a threshold
  (accountant books, partner approves), and an immutable change log per entry
  (the `project_logs` pattern already exists in this codebase).
- **3C. Cash-flow forecast.** 13-week projection from open invoice due dates,
  vendor bills, recurring rules and the current balance — the standard "will we be
  comfortable next month" view; plus budget vs actual per category/project.

## Wave 4 — later / optional

Multi-currency rate history and revaluation · cheque lifecycle (issued / cleared /
bounced) · petty-cash drawer · loan/EMI split (principal vs interest) · bill OCR to
prefill entries · bank feed via account aggregator · Tally/Zoho import-export
bridge · branch/multi-entity ledgers.

---

## 2b. Quick Entry: one question about the party, answered twice

Quick Entry is the ledger's front door — one bank line, recorded in a few
seconds — and it asked about the party twice: **Related To** (which kind) and
**Party / Expense Name** (a free text box). The kinds that have a record to link
to now have a list to pick from, so an entry is filed *against* the client or the
vendor rather than beside their name.

- **One picker at a time.** "Related To" chooses which list you are picking from:
  a **client**, a **vendor**, the **employee** being paid, or the **head** a cash
  expense was spent under. The field that does not belong to the chosen kind is
  hidden — and **cleared**, because a hidden `<select>` still submits, and a
  client left over from a moment ago is how an entry lands on the wrong party's
  statement.
- **The link decides the label.** `CashflowEntry::alignPartyType()`: the chosen
  type stands when it names one of the links that is set; otherwise the link
  wins, in `partyLinkColumns()` order (client, vendor, employee). A party
  statement is built from the *column* (`PartyStatement` reads `client_id` /
  `vendor_id`, never the label), while the ledger's filter and the report read
  the *label* — when the two disagree, one row is on a client's statement and
  invisible to the filter for "Client". Quick Entry made that easy: the selector
  opens on its first option, so a vendor payment filed without touching it was
  labelled "Client" while linked to the vendor. Proved by
  `php artisan test --filter=CashflowPartyTest`.
- **A failed save comes back.** The dialog names itself (`_dialog`), the page
  reopens the one the errors belong to, and every field is server-rendered with
  `old()` — the office should not retype a bank line because the amount was
  missing. The archive's filing dialog and the account dialog work the same way.

## 3. UX-effectiveness, applying to every wave

- **Keyboard-first entry**: Enter saves, `N` new, `/` search, arrow-key row
  navigation, and a command palette for "jump to client…".
- **Smart defaults**: last used account/category/party per transaction type; recent
  parties pinned to the top of the select; party auto-fills its category.
- **Undo beats confirmation**: save optimistically and offer a 5-second Undo
  toast instead of a dialog; keep the dialog for destructive actions only.
- **Bulk actions**: multi-select rows → set category/status/project, attach one
  document, export the selection. Month-end work is batch work.
- **Status becomes a workflow**: "needs booking", "needs documents", "needs
  reconciliation" queues with counts, so a month is closed by emptying queues.
- **Mobile capture**: photograph a bill → entry with the image attached; the
  operator does the filing at the desk, not from memory.
- **One vocabulary**: any new chrome goes into the shared list layer
  (`master-list.css` / `DateRanges` / `MasterList` toolkit), never copied per
  module — with a checker guard proving it, as every recent change has.

## 4. Decisions I need from you before building

1. **Purchases**: do we introduce a real **purchase bill** (vendor bill with GST
   split, then payment), or keep purchases as cashflow rows with a tax split?
2. **Handover to the accountant**: expiring **share link**, or an emailed **ZIP/PDF**
   each month (or both)?
3. **Bank import**: which bank and file type do you get — HDFC CSV/XLSX, PDF, or
   the net-banking export? One real file decides the importer.
4. **Employee dimension**: link to login users (they have department/designation),
   or do you want a separate employee master (staff who never log in)?
5. **Approval**: who approves what, and above which amount?
6. **Currency**: is the rate entered per entry, or fetched per day (and which source)?

## 5. Suggested first cut

If we take **Wave 1A + 1B + 1C** first, your monthly ritual collapses into: open
the module → attach bills as they arrive → press *Month-close pack* → send it. That is
the shortest path from four manual jobs to one button, and every later wave builds
on the same attachments and dimensions.
