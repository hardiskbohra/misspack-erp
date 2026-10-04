# Purchase management

What the purchase module does, and the handful of rules that decide what its numbers
mean. Read this before changing anything under
`app/Http/Controllers/PurchaseInvoiceController.php`,
`app/Services/PurchaseInvoiceFilters.php`, `app/Services/PurchaseBillLedger.php`,
`app/Models/PurchaseInvoice.php` or the `resources/views/purchase_invoices/` screens —
`tools/checks/purchases-check.cjs` holds every rule below.

It is the mirror of [invoice management](invoice-management.md): one table, two
documents, the same decomposition (model — service — controller — screen — script —
check). Where the two differ, this file says so and why.

## Two documents, one table

`purchase_invoices` holds both documents, split by `invoice_type`:

| `invoice_type` | What it is | Numbered | Owed? |
| --- | --- | --- | --- |
| `order` | **Purchase Order** — what we asked a vendor to supply | `MP/PO/{FY}/###` | No. It is a commitment, and the list calls its value *open value* until a bill exists |
| `bill` | **Purchase Bill** — what the vendor supplied and charged for | `MP/PB/{FY}/###` | Yes. It is a payable, and it posts to the vendor ledger |

The sales side splits the same way a `proforma` and a `tax` invoice; the purchase
vocabulary is **order** and **bill** everywhere — in the database, the routes
(`purchase-orders.*` / `purchase-bills.*`), the statuses and the screens. There is no
"proforma" in this module.

## The order becomes the bill, once

A purchase bill is raised from its order by the order's own **Convert to purchase
bill** action — the row menu's action, the record page's primary button. There is no
second create screen that could produce a bill which is not linked to an order.

- **One-to-one, and locked.** `converted_invoice_id` on the order points at the bill.
  A second attempt is refused with a message naming the bill that already exists, and
  the order stops offering the action (`PurchaseInvoice::canConvert()`).
- **A copy, with overrides.** The lines, rates, taxes, terms and vendor snapshot are
  copied; our number, the vendor's own bill number and the dates are the office's to
  fill in. The copy runs inside a transaction and takes the row lock
  (`lockForUpdate()`) before it reads the order.
- **Money already paid moves across.** Nothing is owed on an order, but a vendor can
  have been paid in advance against one: those ledger rows are re-pointed at the bill,
  so the payable starts from the truth.
- **Deleting the bill re-opens the order.** The link is what made the order history;
  with the bill gone the order goes back to `approved`. The order itself cannot be
  deleted while a bill points at it.

## The money is one rule

A bill's paid figure is **not** a column the office maintains by hand:

```
opening paid  +  every vendor ledger row filed against the bill
```

- `purchase_invoices.amount_paid` is the **opening** figure — money paid before (or
  outside) the ledger, typed on the form. The form labels it *Opening paid*.
- `vendor_payment_entries` rows carrying `purchase_invoice_id` and
  `transaction_type = debit` are the **payments**: the bank line, the mode, the
  reference. That is where a payment is reconciled.
- `PurchaseInvoice::PAID_SQL` is the SQL expression of the same sum, for the queries
  that must not hydrate a row per document (the figures, the chips, the CSVs, the
  payment filter). `paidAmount()` computes it on a loaded model, `scopeWithPaid()`
  adds it to a list, and `balanceDue()` is `total_amount − paidAmount()`, floored at
  zero.
- `balance_amount` is **written from** `paidAmount()`, never the other way round —
  the same rule the sales side keeps, for the same reason.

Two consequences the listing leans on:

- the payment filter asks the money, not a stored word: *Nothing paid* is the paid
  figure at zero, and a bill marked `paid` a year ago whose payment was reversed is
  not paid;
- `amount_paid` is never double counted. A payment recorded from *Record payment*
  lives in the vendor ledger only.

## A bill posts to the vendor ledger

This is the one thing the purchase side has that the sales side does not.
`PurchaseBillLedger` keeps **one** row per bill in `vendor_payment_entries`:

- `transaction_type = credit` (we owe more), `entry_category = bill`,
  `status = booked`;
- our document number in `invoice_number`, the order it came from **and the vendor's
  own bill number** in `particular`, and the bill's `due_date` — the two things the
  office reconciles against when the vendor's paper arrives;
- `amount_in_inr` at the document's own rate, so a bill raised in USD or RMB still
  reads in rupees where the bank is reconciled.

Payments are the debits against that row, and they are the reason:

- the vendor's **payables ageing** and **statement** need no second number — they read
  the rows this bill posted;
- the record page shows those rows (§ *The screens*);
- deleting a bill removes its posting, but refuses while payments exist: a payment is
  a fact about the bank, not about the document.

## Statuses

| Document | Flow |
| --- | --- |
| Purchase order | `draft → sent → approved → billed` (or `cancelled`) |
| Purchase bill | `draft → received → partial → paid` (or `cancelled`) |

`overdue` is **derived**, never stored: past the due date, not a draft, not
cancelled, and not fully paid. `PurchaseInvoice::stateKey()` / `stateLabel()` are the
one place that decides the word the chip wears, and `ageingBucket()` the one place
that decides the ageing band.

`PurchaseInvoice::STATUS_FLOW` is the single list of allowed transitions; the row
menu, the record page and the bulk sweep all ask it, so no screen can offer a move
the model would refuse.

## The screens

- **Listings** — `purchase-orders.index`, `purchase-bills.index`. Money tiles asked
  of the whole filtered set in one aggregate, status chips with counts, quick periods,
  the drawer, the applied strip, saved views, density, bulk bar, and a CSV / GST
  summary of exactly what is on screen.
- **Form** — `…create` / `…edit`. The vendor block is filled from the vendor record by
  **one** list: the controller writes the snapshot onto the option as JSON and hands
  the field names to the script, so a column added to the snapshot arrives in the form
  with no edit to either file. The money preview mirrors `syncItemsAndTotals()` step
  for step — including the ledger payments it cannot see itself, handed over in
  `data-ledger-paid`.
- **Record** — `…show`. The state, the money, the lines, the summary, who it was
  billed by and to, and — for a bill — the **vendor ledger** section listing the
  posting and every payment against it, each linking to its cashflow entry.
- **Print** — `…print` and the public link (`/public-purchase-invoices/{token}`, the
  token and nothing else). An A4 sheet with its own `.pi-print-*` classes; the line
  columns are allocated back from the header by largest remainder so what is printed
  foots to what is printed. There is **no vendor portal**: the public link is a print
  link, so it shows the document and no office chrome.

## Sweeps and files

The bulk bar offers only what a person could do one row at a time — mark sent
(orders) / received (bills), approve (orders), cancel, delete drafts — and it reports
what it left alone and why. A cancelled document is never dragged back; a bill with
payments is never deleted or cancelled by a sweep.

Both exports ask the screen's own query through `PurchaseInvoiceFilters`, so the file
and the screen can never disagree about the rows, and both carry a `Filtered by` line
built from the same chip vocabulary. The GST summary is the **items**, grouped by
HSN/SAC and rate, with drafts and cancellations left out — a bill nobody has received
is not input credit.

## Files

| File | What it owns |
| --- | --- |
| `app/Models/PurchaseInvoice.php` | The money rule, the statuses, the state words, the ageing, the conversion guards |
| `app/Models/PurchaseInvoiceItem.php` | One line, and what it is worth |
| `app/Services/PurchaseBillLedger.php` | The one ledger row a bill posts, and its removal |
| `app/Services/PurchaseInvoiceFilters.php` | The filter vocabulary: parsing, chips, labels, selected ids |
| `app/Http/Controllers/PurchaseInvoiceController.php` | The document's life: list, form, record, print, convert, pay, sweep, export |
| `resources/views/purchase_invoices/*` | The four screens and the two partials (payment dialog, ledger) |
| `public/assets/css/purchase-invoices{,-print}.css` | The module's own classes, `pi-` prefixed; no shared class is defined in either |
| `public/assets/js/purchase-invoices.js` | The line builder, the money preview, the vendor snapshot, the dialogs, the sweep |
| `tools/checks/purchases-check.cjs` | Every rule above, as a check |

Not here yet, and deliberately: attachments on a purchase document, reminders to a
vendor, and a bulk status change beyond the four above. The vendor module's own
documents archive and reminder surfaces are the models to follow when they arrive.
