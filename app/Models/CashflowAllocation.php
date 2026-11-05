<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One monetary slice of a cashflow entry.
 *
 * The amount is always in the cash entry's currency. Document amount is the
 * value applied to an invoice/order/bill in that document's own currency. A
 * line may carry a project and one document because those are dimensions of the
 * same slice, not two separate uses of the money.
 */
class CashflowAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'cashflow_entry_id', 'project_id', 'sales_invoice_id', 'purchase_invoice_id',
        'amount', 'document_amount', 'currency', 'document_currency', 'note',
    ];

    protected $casts = [
        'amount' => \App\Casts\DecimalUpToThree::class,
        'document_amount' => \App\Casts\DecimalUpToThree::class,
    ];

    public function cashflowEntry()
    {
        return $this->belongsTo(CashflowEntry::class, 'cashflow_entry_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function salesInvoice()
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }

    public function purchaseInvoice()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    public function documentAmount(): float
    {
        return (float) ($this->document_amount ?? $this->amount);
    }
}
