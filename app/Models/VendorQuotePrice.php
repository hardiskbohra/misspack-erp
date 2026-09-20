<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorQuotePrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_quote_id',
        'quantity',
        'unit',
        'finish_type',
        'printing_type',
        'vendor_unit_price',
        'landing_cost_inr',
        'selling_price_inr',
        'moq',
        'remarks',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'vendor_unit_price' => 'decimal:4',
        'landing_cost_inr' => 'decimal:2',
        'selling_price_inr' => 'decimal:2',
    ];

    public function vendorQuote()
    {
        return $this->belongsTo(VendorQuote::class);
    }
}
