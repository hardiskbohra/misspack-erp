<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipmentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id', 'product_name', 'sku', 'hs_code', 'quantity', 'unit', 'declared_value', 'currency',
        'net_weight', 'gross_weight', 'description',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'declared_value' => 'decimal:2',
        'net_weight' => 'decimal:3',
        'gross_weight' => 'decimal:3',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }
}
