<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesInvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_invoice_id', 'product_id', 'project_product_id', 'product_name', 'description',
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
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }

    public function product()
    {
        return $this->belongsTo(\App\Models\Product::class, 'product_id');
    }

    public function projectProduct()
    {
        return $this->belongsTo(\App\Models\ProjectProduct::class, 'project_product_id');
    }
}
