# Purchase orders and purchase bills

The buying-side twin of the sales invoice module. One table (`purchase_invoices`),
two documents: `invoice_type` is `order` (a Purchase Order) or `bill` (the
Purchase Bill raised from it). Both live in one UI under `purchase-invoices.*`,
the way a proforma and a tax invoice share `/sales-invoices`.

The money rule is the same shape as sales: `amount_paid` is the opening figure;
ledger debits on `vendor_payment_entries.purchase_invoice_id` are the payments;
`PurchaseInvoice::PAID_SQL` is the sum; `balance_amount` is written from it.
An order that became a bill (`converted_invoice_id`) owes nothing — convert once,
under a row lock. Deleting the bill puts the order back.

`PurchaseInvoice::countsTowardsProject()` is what a **project's budget** reads: the
orders placed for a project plus the bills recorded against it, a cancelled document
and an order that a bill has carried aside. `Project::budgetAmount()` sums those rows
in the project's currency (at the document's rate when the two disagree), and the
project's Invoices tab lists them — an order keeps its row as history once it is
billed, it is simply not counted twice. A purchase document also writes the
**project's products**: `afterSave()` hands its lines to `App\Services\ProjectProducts`,
so what a project buys appears on it without a second entry (the vendor and the
supplier's own bill number are the purchase side's half of that row).

Screens: `resources/views/purchase_invoices/` — list, form, record, print.
A PO converts to a bill; the bill posts into the vendor ledger via `PurchaseBillLedger`.

## One payment, one owner

A rupee payment can be recorded in two places, and only one of them owns it:

| Written in | Ledger row | Owner |
| --- | --- | --- |
| Vendor ledger (`vendors.payments.*`) | `vendor_payment_entries`, `source` null | the ledger; `VendorPaymentCashflowSync` mirrors it to a cashflow entry |
| Cashflow (`cashflows.*`, `vendor_id` set, debit, INR) | `vendor_payment_entries`, `source = 'cashflow'` | the cashflow row; `CashflowVendorPaymentSync` keeps the ledger row in step and deletes it with the entry |

The link column is `vendor_payment_entries.cashflow_entry_id` in both directions,
so the pair can always be found from either side. A row with `source = 'cashflow'`
is read-only in the vendor module — the Money tab shows a *From cashflow* chip and
a *Change in cashflow* link instead of Edit and Delete, and the controller refuses
`updatePayment()`/`destroyPayment()` on it, because changing one half of a mirrored
pair silently leaves the other half wrong.

`CashflowAllocations` ignores `source = 'cashflow'` rows when it looks for a
conflicting mirror, so the originating cashflow entry can still be allocated to a
bill without the derived row looking like a second payment.

A cashflow row the ledger cannot mirror — a vendor matched only by
`related_party_name`, a foreign-currency row, money received back — is not
converted. It is shown instead: in *Other cashflow activity* on the vendor's Money
tab, and appended to the vendor statement of account in its own currency, which is
why `PartyStatement::currencies()` reads `cashflow_entries.currency` as well as
`vendor_payment_entries.foreign_currency`. Recurring rules post through the same
sync, so a scheduled vendor payment lands on both ledgers at once.
