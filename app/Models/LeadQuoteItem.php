<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadQuoteItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_quote_id', 'product_name', 'description', 'quantity', 'unit', 'capacity',
        'finish_type', 'printing_type', 'unit_price', 'amount', 'remarks',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    public function quote()
    {
        return $this->belongsTo(LeadQuote::class, 'lead_quote_id');
    }
}
