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

## One question, one query

The listing asks three questions — the tiles, the footer, and the rows — and each gets
a builder of its own:

```php
$figures  = $this->filteredQuery($filters)->reorder()->selectRaw(...)->first();
$totals   = $this->filteredQuery($filters)->reorder()->selectRaw(...)->first();
$invoices = $this->filteredQuery($filters)->withReceived()->withCount('payments')
    ->withReminders()->latest('invoice_date')->paginate(25);
```

That is not tidiness. `withCount` / `withSum` / `withMax` do not only add eager loads:
they write `sales_invoices.*` **and one correlated subquery per aggregate into the
column list**, and `selectRaw` / `select` *append* to a column list (only a plain
`sum()` replaces it). Appending an aggregate to those columns is an aggregate select
sitting beside non-aggregated columns with no `GROUP BY`, and MySQL answers that with
`1140 … incompatible with sql_mode=only_full_group_by` — the mode the office's host
runs with. A builder with no columns of its own takes one aggregate select cleanly.

`tools/checks/invoices-check.cjs` holds both halves of the rule: no statement may carry
`withCount`/`withSum`/`withMax` **and** a `select`/`selectRaw`, and the tiles and the
footer must be `filteredQuery($filters)->reorder()->selectRaw(...)` — a query of their
own, never the builder the page was loaded from.

## How late it is

One vocabulary, on the model: `ageingBuckets()` — `current`, `1_30`, `31_60`,
`61_90`, `90_plus`, with the filter's labels next to them. A row's bucket comes from
`ageingBucket()`; `isOverdue()` is `balanceDue() > 0.01` and the due date in the past,
and it is **false** for a draft or a cancelled invoice (the office has already decided
about those). The chip, the filter, the figures and the CSV all use these — a second
spelling is how the list and the statement started disagreeing.

Every date these methods are handed — and the one `lastRemindedAt()` hands back — is a
`\DateTimeInterface`, never a Carbon interface. `?CarbonInterface` reads stricter and is a
trap: `Illuminate\Support\Carbon\CarbonInterface` names nothing Laravel ships, a hint
nothing implements accepts `null` without a murmur and rejects every real date, so the 500
waits quietly until the first caller passes one. Carbon, CarbonImmutable, DateTime and Date
all satisfy `\DateTimeInterface`, it needs no import, and no framework upgrade can move it.

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

- **chips with counts** — All invoices · Proforma · Tax · Drafts, plus one chip per
  `DateRanges::presets()` period. A chip's count is what that chip would show, asked
  with the rest of the view kept. The money questions (nothing received · partly paid ·
  paid), how late it is (the five ageing buckets) and who to chase (due within 7 days ·
  not nudged in a week · nudged this week) are one select each in the filter row, not
  chips: the strip was seven chips of noise over filters the row already offers;
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

## The record screen

`resources/views/sales_invoices/show.blade.php` is the shared record composition,
the same one the shipment, employee and user record pages wear:

    .master-card.master-header        the number, the state chips, the actions
    .master-stats > .master-stat--flat five figures: total, received, GST,
                                      balance, due date
    .master-card.master-section       the line items, at the page's full width
    .master-grid.is-even              billing | summary, payments | chase,
                                      terms & notes | files

Four rules hold it together:

- **no fork.** The page wears the shared classes and the module sheet defines
  no `master-*`; the only classes it adds are its own (`.si-show`, the money
  column, a receipt row, a file row, a chase row, a note). Guarded by
  `tools/checks/invoices-check.cjs`.
- **the money is the model's.** `receivedAmount()` and `balanceDue()` are read
  once, at the top; the receipt rows print the ledger lines they came from, so
  the page cannot add up a second balance. A debit against the invoice prints as
  a refund, because the direction comes from the entry.
- **an empty card explains itself.** `.master-empty-state` — an icon, a
  sentence, the button that fills it. Never `.master-empty`, which is the
  listing-level empty, and never a bare dash: a fact the office has not filled
  in says *Not on file*, and an address is built from the parts that exist
  rather than joined with commas over the empty ones.
- **the spacing is the shell's.** `master-list.css` spaces every pair of blocks
  on a page, including two grids stacked (`grid + grid`) — the record page's
  grid rows were flush against each other until it did. A module does not space
  its own page, and no page carries an inline margin.

The file row's icon and size come from `SalesInvoiceAttachment::icon()` and
`sizeLabel()` — the same reading the ledger's attachments use, so no view has to
guess an icon from a file name.

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

## Chasing the money

A bill that has been sent is not money in the bank, and the office's real work is
the second half of the month: ringing, WhatsApp-ing and emailing. That is recorded
here rather than remembered.

**A reminder is a log row, not a flag.** `sales_invoice_reminders` holds one row
per chase — the invoice, the channel, the day, the words that were sent and what
came back, and who logged it. There is deliberately **no `reminded_at` or
`reminders_count` column on the invoice**: a counter kept beside a log drifts the
first time a log row is deleted, so the listing reads both through
`SalesInvoice::scopeWithReminders()` (`withCount` + `withMax`) — the same two
subqueries-per-page trick the money rule uses.

**The words come from the model.** `SalesInvoice::reminderMessage()` writes the
message: what is owed (`balanceDue()`), how late it is, and the client's link — but
only when `show_client_portal` is on, because the public page exists only then and
a reminder that links to a 404 is worse than one with no link at all. The office
may edit the text in the dialog before sending; the model's version is what is
logged when they do not.

**The chase worklist** is a filter like any other (`SalesInvoiceFilters::CHASE_KEYS`),
with a chip and a count for each:

| Chip | The question |
| --- | --- |
| Due within 7 days | still owed, and falling due this week — the diary |
| Not nudged in a week | still owed, and nobody has asked in seven days — the conscience |
| Nudged this week | still owed, and already asked — so nobody is rung twice |

## Sweeping a selection

The toolbar's bulk bar acts on ticked rows. Each action is the **same** change the
row menu makes, in a loop — there is no second implementation of "mark sent" here.

- **Log a reminder** for a whole selection (the model's words, today, WhatsApp);
- **mark sent** (which also puts the invoice on the portal);
- **show / hide in the client portal**;
- **delete drafts only** — and it says so: a sent invoice is a document the client
  already holds, so anything past draft is skipped and counted in the reply;
- **export the selection**, and its **GST summary**, through the same two exporters
  the screen uses (`?ids[]=` — capped at 500, because a GET URL is not a place for
  ten thousand ids).

The checkboxes hang off the bulk form by id (`form="bulkForm"`) instead of being
wrapped in it: a form around the table would nest the row menus' own forms inside
it, and a nested form never submits.

## The CA's file

`GET sales-invoices/gst-export` is the month-end summary: the invoice **items**
grouped by HSN/SAC and rate, with taxable, CGST, SGST, IGST and total — for the
filters the screen is showing, or for the ticked selection. It reads the items
because that is where HSN and rate actually live (the header only carries totals),
and it leaves **drafts and cancellations out**: they are not tax documents, and a
summary that counted them would state a liability the office never incurred. Both
this file and the invoice CSV describe their filters in the same words, from
`SalesInvoiceFilters::applied()` / `labels()`.

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

### What a conversion does to the document's life

The tax invoice a conversion creates inherits the proforma's `status`, `sent_at`
and `accepted_at`: the conversion re-papers the same money rather than starting a
document nobody has sent. A draft proforma becomes a draft tax invoice; a
cancelled proforma cannot be converted at all.

### What is owed

*Outstanding* is the Balance column, summed: each standing document's total minus
the money received — drafts included, cancellations aside. The tile and the
footer's own total ask that one question of the same rows, because a tile that
excludes drafts while the column above it prints their balance is a figure the
office stops believing. *Overdue* stays the part of that balance that is late
(`isOverdue()`), and nothing is late before it is sent.

### The cells on the listing

The invoice cell reads down three lines — the number, the type chip (with the PO
when there is one), then the date on its own line, because a date sharing a line
with the chip wrapped mid-year on every row. The money cell's second line is the
item count. A fact nobody filled in says so in words — `No GSTIN on file`,
`No project mapped`, `No due date` — never a dash. And a receipt on the record
page names the cashflow entry it came from and links to it.

### What a client brings onto an invoice

The invoice keeps its own copy of the client — company, brand, contact, email,
mobile, GSTIN, PAN, both addresses in their parts (address line, city, state,
country, pincode) and the place of supply — and the printed invoice lays those
parts out. One list decides what that copy holds: `clientSnapshot()` in
`SalesInvoiceController`. The server fills it when the form opens with
`?client_id=`; the form carries the same list on the client select as JSON
(`data-snapshot` per option, `data-snapshot-fields` for the shape) and the script
applies it by field name, so a pick always lands exactly what a link lands.

Two rules keep it honest:

* **loading shows, picking chooses.** On load the script fills only empty fields,
  because the stored copy is the invoice's and a screen must not rewrite it by
  being opened; picking a client overwrites every field in the list, and clears
  the ones the new client does not hold, because that is the office saying whose
  details they want now.
* **every field in the list has an input on the form.** A value the client holds
  and the invoice prints, with nowhere to see it, is a value the office cannot
  check before it prints — and it would fill only on the deep-link path. Adding a
  field to `clientSnapshot()` without a field on the form fails the module's gate.
