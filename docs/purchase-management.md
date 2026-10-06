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
