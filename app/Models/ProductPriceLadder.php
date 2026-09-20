<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductPriceLadder extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'quantity', 'unit', 'capacity', 'finish_type', 'printing_type',
        'landing_cost_inr', 'selling_cost_inr', 'remarks',
    ];

    protected $casts = [
        'landing_cost_inr' => 'decimal:2',
        'selling_cost_inr' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
