<?php

namespace App\Services;

use App\Models\PurchaseInvoice;
use App\Models\VendorPaymentEntry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Posts a purchase bill into the vendor ledger.
 *
 * Why this exists
 * ---------------
 * A purchase bill is not a document that lives beside the ledger — it *is* a
 * payable. The vendor's own Money tab, the Payables ageing and the statement
 * all read `vendor_payment_entries`, so a bill that never posts there is
 * invisible to every screen that answers "what do we owe this supplier".
 *
 * Rule: the purchase bill is the source of truth. Its ledger row is a mirror,
 * created/updated/removed by this service, and it is one row per bill:
 *   - a **credit** (the supplier bill) in the bill's currency, with the rupee
 *     value at the bill's own rate;
 *   - `entry_category = bill`, `status = booked`, so the ageing and the
 *     dashboard's purchase figures count it exactly like a hand-entered bill;
 *   - `invoice_number` is the supplier's own number when the office captured
 *     one, because that is the number the ledger is reconciled against.
 *
 * Payments against the bill are **debits** carrying the same
 * `purchase_invoice_id`; they are written by the module's payment action (and
 * mirrored into the INR cashflow by `VendorPaymentCashflowSync`), never by this
 * service.
 *
 * A purchase order posts once it has left the office (`sent` or `approved`),
 * so the vendor Money tab and the statement show the commitment. The moment
 * that order becomes a bill, the order row is taken out and only the bill
 * remains — one purchase, one credit.
 */
class PurchaseBillLedger
{
    public function available(): bool
    {
        return class_exists(VendorPaymentEntry::class)
            && Schema::hasTable('vendor_payment_entries')
            && Schema::hasColumn('vendor_payment_entries', 'purchase_invoice_id');
    }

    /**
     * Create or refresh the bill's ledger row.
     *
     * Returns the row when one exists afterwards, or null when the document
     * does not need one (a draft or cancelled order, a billed order whose bill
     * has taken over, a cancelled bill, a document without a vendor).
     */
    public function sync(PurchaseInvoice $invoice): ?VendorPaymentEntry
    {
        if (! $this->available()) {
            return null;
        }

        if (! $invoice->vendor_id || $invoice->status === 'cancelled') {
            $this->remove($invoice);

            return null;
        }

        if ($invoice->isOrder()) {
            if (! $this->orderIsOpen($invoice)) {
                $this->remove($invoice);

                return null;
            }

            return $this->write($invoice, 'order');
        }

        if (! $invoice->isBill()) {
            $this->remove($invoice);

            return null;
        }

        return $this->write($invoice, 'bill');
    }

    /** Sent or approved, and not yet raised as a bill. */
    private function orderIsOpen(PurchaseInvoice $invoice): bool
    {
        return in_array($invoice->status, ['sent', 'approved'], true)
            && ! $invoice->converted_invoice_id;
    }

    private function write(PurchaseInvoice $invoice, string $kind): VendorPaymentEntry
    {
        $entry = $this->posting($invoice) ?? new VendorPaymentEntry();
        $order = $kind === 'bill' && $invoice->purchase_order_id
            ? $invoice->purchaseOrder
            : null;

        $entry->fill([
            'vendor_id' => $invoice->vendor_id,
            'purchase_invoice_id' => $invoice->id,
            'project_id' => $invoice->project_id,
            'transaction_date' => $invoice->invoice_date ?: now()->toDateString(),
            'due_date' => $invoice->due_date,
            'invoice_number' => $kind === 'bill' ? $invoice->referenceNumber() : $invoice->invoice_number,
            'foreign_currency' => $invoice->currency ?: 'INR',
            'foreign_amount' => (float) $invoice->total_amount,
            'exchange_rate' => $this->rate($invoice),
            'transaction_type' => 'credit',
            'entry_category' => $kind === 'order' ? 'order' : 'bill',
            'particular' => $kind === 'order'
                ? 'Purchase order '.$invoice->invoice_number
                : trim('Purchase bill '.$invoice->invoice_number
                    .($order ? ' against '.$order->invoice_number : '')),
            'status' => 'booked',
            'amount_in_inr' => $this->rupees($invoice),
            'remarks' => $kind === 'order'
                ? 'Auto-posted from purchase order '.$invoice->invoice_number.'.'
                : 'Auto-posted from purchase bill '.$invoice->invoice_number.'.',
            'created_by' => $invoice->created_by ?: Auth::id(),
        ]);

        $entry->save();

        return $entry;
    }

    /**
     * Take the bill's row out of the ledger.
     *
     * Only the auto-posted credit goes: a payment that was actually made is a
     * fact about the bank, not about this document, and the controller refuses
     * to delete a bill that has payments for the same reason.
     */
    public function remove(PurchaseInvoice $invoice): void
    {
        if (! $this->available() || ! $invoice->exists) {
            return;
        }

        $this->posting($invoice)?->delete();
    }

    /**
     * The advance follows the document.
     *
     * An order is not owed, but money can be paid against one before the bill
     * arrives. When the order becomes a bill, those debits move with it — one
     * purchase, one payable, the way a proforma's receipts move to its tax
     * invoice.
     */
    public function movePayments(PurchaseInvoice $order, PurchaseInvoice $bill): void
    {
        if (! $this->available()) {
            return;
        }

        VendorPaymentEntry::query()
            ->where('purchase_invoice_id', $order->id)
            ->where('transaction_type', 'debit')
            ->update(['purchase_invoice_id' => $bill->id]);
    }

    /** The row this service owns for the bill: the auto-posted credit. */
    private function posting(PurchaseInvoice $invoice): ?VendorPaymentEntry
    {
        if (! $this->available() || ! $invoice->exists) {
            return null;
        }

        return VendorPaymentEntry::query()
            ->where('purchase_invoice_id', $invoice->id)
            ->where('transaction_type', 'credit')
            ->first();
    }

    /**
     * The rate the rupee value is computed at.
     *
     * A rupee bill is always 1 whatever is typed beside it — the base currency
     * is stored at 1 — and a foreign bill at the rate it was raised at.
     */
    private function rate(PurchaseInvoice $invoice): float
    {
        if (($invoice->currency ?: 'INR') === 'INR') {
            return 1.0;
        }

        return (float) ($invoice->exchange_rate ?: 1);
    }

    private function rupees(PurchaseInvoice $invoice): float
    {
        return round((float) $invoice->total_amount * $this->rate($invoice), 2);
    }
}
