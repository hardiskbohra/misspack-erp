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

Screens: `resources/views/purchase_invoices/` — list, form, record, print.
A PO converts to a bill; the bill posts into the vendor ledger via `PurchaseBillLedger`.
