<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A document's share of one unchanged vendor-ledger payment. */
class PurchasePaymentSplit extends Model
{
    protected $fillable = ['vendor_payment_entry_id', 'purchase_invoice_id', 'amount'];

    protected $casts = ['amount' => 'decimal:4'];

    public function payment()
    {
        return $this->belongsTo(VendorPaymentEntry::class, 'vendor_payment_entry_id');
    }

    public function purchaseInvoice()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }
}
