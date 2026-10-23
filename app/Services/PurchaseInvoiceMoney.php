<?php

namespace App\Services;

use App\Models\PurchaseInvoice;
use Illuminate\Support\Facades\Schema;

/** Refresh stored bill state from active payments, including reversals to unpaid. */
class PurchaseInvoiceMoney
{
    public function refresh(array $invoiceIds): void
    {
        $ids = array_values(array_unique(array_filter($invoiceIds)));
        if (! $ids || ! Schema::hasTable('purchase_invoices')) return;

        // Read fresh models, not cached payments or withPaid() aggregates.
        foreach (PurchaseInvoice::query()->whereIn('id', $ids)->get() as $invoice) {
            $changes = ['balance_amount' => $invoice->balanceDue()];
            if ($invoice->isBill() && ! in_array($invoice->status, ['draft', 'cancelled'], true)) {
                $state = $invoice->paymentState();
                $changes['status'] = $state === 'unpaid' ? 'received' : $state;
            }
            $invoice->forceFill($changes)->saveQuietly();
        }
    }
}
