# Invoice management

What the module does, and the handful of rules that decide what the numbers mean.
Read this before changing anything under `app/Http/Controllers/SalesInvoiceController.php`,
`app/Services/SalesInvoiceFilters.php`, `app/Models/SalesInvoice.php` or the three
`resources/views/sales_invoices/` screens — `tools/checks/invoices-check.cjs` holds
every rule below.

## The money is one rule

An invoice's received figure is **not** a column the office maintains by hand. It is:

```
opening received  +  every ledger line linked to the invoice
```

- `sales_invoices.amount_paid` is the **opening** figure — money that arrived before
  (or outside) the ledger, typed on the invoice form. The form labels it *Opening
  received* and says what it is.
- `cashflow_entries` rows carrying `sales_invoice_id` are the **receipts**: the bank
  line, the payment mode, the date. This is where a receipt is reconciled.
- `SalesInvoice::RECEIVED_SQL` is the SQL expression of the same sum, for the queries
  that must not hydrate a row per invoice (the figures, the chips, the CSV, the
  payment filter).
- `SalesInvoice::receivedAmount()` computes it on a loaded model;
  `scopeWithReceived()` adds it as `received_credit` / `received_debit` for a list;
  `balanceDue()` is `total_amount − receivedAmount()`, floored at zero.
- `balance_amount` is **written from** `receivedAmount()` (`refreshInvoiceMoney()`),
  never the other way round, because `PartyStatement` prints that column on a client's
  statement and the statement has to agree with the invoice.

Two consequences the list leans on:

- the payment filter asks the money, not a stored word: *Nothing received* is
  `amount_paid ≤ 0.01` **and** no ledger lines — an invoice marked `paid` a year ago
  whose receipt was deleted is not paid;
- `amount_paid` is never double counted. A receipt recorded from *Record payment*
  lives in the ledger only.

`cashflow_entries.sales_invoice_id` arrives in its own migration with an index and no
foreign key, because the ledger predates the invoice module and the table may not
exist at all on an old install. Guard it (`Schema::hasTable`) wherever it is read.

## How late it is

One vocabulary, on the model: `ageingBuckets()` — `current`, `1_30`, `31_60`,
`61_90`, `90_plus`, with the filter's labels next to them. A row's bucket comes from
`ageingBucket()`; `isOverdue()` is `balanceDue() > 0.01` and the due date in the past,
and it is **false** for a draft or a cancelled invoice (the office has already decided
about those). The chip, the filter, the figures and the CSV all use these — a second
spelling is how the list and the statement started disagreeing.

## What the state chip says

`SalesInvoice::stateKey()` / `stateLabel()`. The chip is about the **money**:

| The invoice | The chip |
| --- | --- |
| `draft` / `cancelled` | Draft / Cancelled — the office's word stands |
| past its due date with money owed | Overdue |
| fully received | Paid |
| partly received | Partly paid |
| sent / accepted | Accepted, else Awaiting payment |

`statusOptions()` on the model still carries the stored vocabulary; the chip is
derived, so an invoice that has been paid does not also read "Overdue" because
nobody clicked a word a month ago.

## The listing

`resources/views/sales_invoices/index.blade.php` wears the shared chrome
(`.master-list`, `.master-stats`, `.master-list-bar`, `.master-list-chips`,
`.master-filter-row`, `.master-list-toolbar`, the pinned table) exactly as the
cashflow listing does. The module contributes no list styling of its own — see
**The sheet** below.

Capability, all from the listing:

- **chips with counts** — All invoices · Proforma · Tax · Drafts · Nothing received ·
  Partly paid · Paid · Overdue, plus one chip per `DateRanges::presets()` period;
- **saved views** (`SavedViews`, module `sales-invoices`) — the query is the view, and
  `?saved_view=ID` redirects into it;
- **filters** — search, type, status, client, project, payment, ageing, invoice date
  range, due date range, portal state (`SalesInvoiceFilters::DEFAULTS`);
- **figures** — invoiced / received / outstanding / overdue for the *filtered* set, one
  `selectRaw` aggregate, plus a module-wide draft count. Never a loop over the fetched
  page;
- **density switch**, clickable rows, one row menu of real routes (View · Edit ·
  Record payment · Print · Copy client link · Mark sent · Hide/Show in portal ·
  Convert to tax invoice · Duplicate as draft · Delete);
- **CSV export** of the same rows, honouring every filter, with a BOM so Excel reads
  the rupee signs.

A filter with an unknown value **widens** the list rather than narrowing it to
nothing: a filter that fails is not a question, and showing everything is the honest
answer to a question nobody asked.

## Recording a receipt

*Record payment* on a row (or on the record page) posts to
`sales-invoices.payments.store`. In one transaction it writes a **credit
`CashflowEntry`** linked to the invoice (client, date, amount, payment mode, reference,
narration) and refreshes `balance_amount` from the new total. The ledger is the
single home of received money: the invoice list, the client statement, the cashflow
listing and the payment filter read it from there.

One dialog serves the whole listing. The row that opens it says which invoice it is
for; the form action is filled in from the `data-action-template` the route rendered
(`__INVOICE__`), and the amount opens prefilled with what is still owed.

## The sheet

`public/assets/css/sales-invoices.css` defines **no `master-*` class**. It was once a
fork of the design system — twenty-six shared classes redefined with module-tuned
values — which is why the module never quite matched the ledger next door. Only the
module's own names live here (`.si-*`, the line-items table, `.si-total-box`,
`.si-status` tones). A shared class used here would override the design system for
every page that loads the sheet.

`public/assets/js/sales-invoices.js` boots the form's line-item builder **and** the
list's chrome (density, saved-view toggle, clickable rows, the receipt dialog, the
client link). Every piece is guarded: the same file loads on the form, which has none
of it.

Both are loaded through `$assetVer()` — the module's file lives at a fixed path, so
without a version the browser keeps serving the old sheet after a deploy.

## Routes the module owns

| Method | URI | Name |
| --- | --- | --- |
| GET | `sales-invoices/export` | `sales-invoices.export` |
| POST | `sales-invoices/saved-views` | `sales-invoices.saved-views.store` |
| DELETE | `sales-invoices/saved-views/{savedView}` | `sales-invoices.saved-views.destroy` |
| PATCH | `sales-invoices/{salesInvoice}/portal` | `sales-invoices.portal` |
| POST | `sales-invoices/{salesInvoice}/payments` | `sales-invoices.payments.store` |
| POST | `sales-invoices/{salesInvoice}/duplicate` | `sales-invoices.duplicate` |
| POST | `sales-invoices/{salesInvoice}/convert` | `sales-invoices.convert` |
| GET | `sales-invoices/{salesInvoice}/print` | `sales-invoices.print` |

All of them are declared **before** `Route::resource('sales-invoices', …)`, or
`/sales-invoices/export` is read as an invoice called "export". The client portal is
public and read-only: `GET /public-sales-invoices/{token}`, the token minted when the
invoice is created.
