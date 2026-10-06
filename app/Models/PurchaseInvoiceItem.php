<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One line of a purchase order or purchase bill.
 *
 * The mirror of `SalesInvoiceItem`, including the money shape: `gross_amount`
 * is quantity × rate before discount, `taxable_amount` is after it, and one of
 * `cgst_amount` / `sgst_amount` / `igst_amount` carries the tax according to the
 * document's `gst_type`.
 */
class PurchaseInvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_invoice_id', 'product_id', 'project_product_id', 'product_name', 'description',
        'hsn_sac', 'quantity', 'unit', 'unit_price', 'gross_amount', 'discount_percent',
        'discount_amount', 'taxable_amount', 'gst_percent', 'cgst_amount', 'sgst_amount',
        'igst_amount', 'line_total', 'sort_order', 'remarks',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'taxable_amount' => 'decimal:2',
        'gst_percent' => 'decimal:2',
        'cgst_amount' => 'decimal:2',
        'sgst_amount' => 'decimal:2',
        'igst_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function invoice()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function projectProduct()
    {
        return $this->belongsTo(ProjectProduct::class, 'project_product_id');
    }
}
