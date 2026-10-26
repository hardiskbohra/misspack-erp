# Invoice adjustments — the state machine, credit notes, debit notes, returns

_Audit §3.5 (P1): once a tax invoice is issued, its money stops being editable;
corrections must exist as documents of their own._

## The rule

A tax invoice in `draft` is a working document — edit it freely, delete it if
it was a mistake. The moment it is **issued** (marked sent, emailed with "mark
sent", converted from a proforma), it becomes a legal document:

- **Money and identity lock.** Number, client, billing address, GSTIN, place of
  supply, GST split, dates of the document, currency, discount/freight/packing/
  other-charges/round-off, the opening `amount_paid`, and every line's name,
  quantity, rate, discount and tax percent. An update that moves any of these
  is refused — saying *which* fields moved and what to do instead.
- **It cannot be deleted.** Deleting deletes the trail. What survives is the
  document plus its corrections.
- **The working fields stay open.** Terms, notes, transport, payment schedule,
  portal visibility, attachments — these explain the document, they don't
  rewrite it. Due date stays editable too: rescheduling collection is not
  forgery.

Enforcement lives in `App\Services\SalesInvoiceAdjustment`
(`assertMoneyFieldsUnchanged` inside the update transaction, after the row is
locked; `assertDeletable` on delete) — not in a Blade `@if`, so import paths,
upcoming APIs, and later UIs inherit the same law.

## Corrections are documents: credit and debit notes

A correction _to an issued invoice_ is a new document that chains onto it:

| | Credit note | Debit note |
|---|---|---|
| Trade term | what the client owes ↓ | what the client owes ↑ |
| Number | `MP/CN/{FY}/NNN` | `MP/DN/{FY}/NNN` |
| Tax | invoice's split, pro-rated | invoice's split, pro-rated |
| Cap | cumulative credits ≤ what the invoice is still worth | — |
| Required | date, amount, **reason** | date, amount, **reason** |

Financial years run April–March, numbered per kind, exactly like the invoices
themselves (`SalesInvoiceAdjustment::makeNoteNumber`).

Notes are **immutable once issued**. A wrong note is never edited or deleted —
it is **cancelled**, with its own reason, and stays on the record marked
cancelled with `cancelled_at`/`cancel_reason`. A correction of a correction is
also a document.

`SalesInvoice::payableTotal()` is what the client actually owes:
`receivableTotal() − live credits + live debits` (floored at zero). Everything
that asks "what is still owed" reads it:

- `balanceDue()`, `advanceAmount()`, `paymentState()` — so a fully credited
  invoice stops chasing money, and receipts already in hand become advance.
- The listing's outstanding/overdue tiles, the payment filter's
  unpaid/partial/paid buckets and CSV exports: same rule in SQL
  (`SalesInvoice::PAYABLE_TOTAL_SQL` = receivable − `CREDIT_NOTES_SQL` +
  `DEBIT_NOTES_SQL`), so the row, the tile, and the export cannot disagree.
  Receipts (`RECEIVED_SQL`) are *never* touched — what was paid was paid.
- The stored `balance_amount` copy other ledgers read (party statement etc.)
  is re-synced after every note issue/cancel
  (`SalesInvoiceAdjustment::syncStoredBalance`).

## Returns

A sales return is **one flow**: the return document and its credit note are the
same record (`is_return = 1`), created in one transaction:

- line-by-line quantities capped at _invoiced − already returned_ (returns
  accumulate so double-returning is impossible);
- per-line valuation at the invoice's own rates, tax pro-rated;
- `restock` flag marks that the goods went back into stock — the hook the
  inventory ledger (audit P0 next-up) will consume when it exists;
- the resulting credit nets into the receivable like any credit note.

Every note prints as a document on the invoice letterhead
(`sales-invoice-notes.print`, `resources/views/sales_invoices/notes/print.blade.php`)
and cancelled notes print with a CANCELLED stamp.

## Where to look in code

| Piece | File |
|---|---|
| Lock rules + issue/return/cancel + numbering | `app/Services/SalesInvoiceAdjustment.php` |
| Note record | `app/Models/SalesInvoiceNote.php`, table `sales_invoice_notes` |
| Money integration | `app/Models/SalesInvoice.php` (`notes()`, `payableTotal()`, `PAYABLE_TOTAL_SQL`) |
| Endpoints | `app/Http/Controllers/SalesInvoiceNoteController.php`, routes `sales-invoices.notes.store`, `sales-invoices.returns.store`, `sales-invoice-notes.cancel`, `sales-invoice-notes.print` |
| UI | record page card _Adjustments & returns_ + three dialogs (`partials/adjustments-card.blade.php`, `partials/adjustments-modals.blade.php`) |
| Tests | `tests/Feature/InvoiceAdjustmentTest.php` (IT-SALES-022…030) |

## What this defers (deliberately)

- **Inventory movement rows for restock.** The flag is stored; the stock-ledger
  consumption belongs to the inventory MVP so there is exactly one stock
  writer.
- **E-invoice IRN cancellation mirroring.** E-way-bill/IRN cancellation stays
  its own surface (`SalesInvoiceEInvoiceController`); GST cancellation _with
  reason on the invoice itself_ arrives with the "cancel tax invoice" admin
  flow, which is the next tranche of §3.5.
- **GST export rows for notes** (GSTR-1 CDNUR etc.). The listing exports keep
  netting via `PAYABLE_TOTAL_SQL`; statutory note tables are a reporting
  tranche.
