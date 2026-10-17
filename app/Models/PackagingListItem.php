<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackagingListItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'packaging_list_carton_id', 'product_id', 'source_type', 'source_item_id', 'source_quantity',
        'previously_packed_quantity', 'item_description', 'sku', 'customer_item_code', 'hs_code',
        'batch_number', 'serial_numbers', 'manufactured_at', 'expires_at', 'country_of_origin',
        'unit_net_weight', 'unit_gross_weight', 'packing_instructions', 'remarks',
        'quantity_per_carton', 'total_quantity', 'unit', 'sort_order',
    ];

    protected $casts = [
        'quantity_per_carton' => 'decimal:3', 'total_quantity' => 'decimal:3',
        'source_quantity' => 'decimal:3', 'previously_packed_quantity' => 'decimal:3',
        'unit_net_weight' => 'decimal:3', 'unit_gross_weight' => 'decimal:3',
        'serial_numbers' => 'array', 'manufactured_at' => 'date', 'expires_at' => 'date',
    ];

    public function carton() { return $this->belongsTo(PackagingListCarton::class, 'packaging_list_carton_id'); }
    public function product() { return $this->belongsTo(Product::class); }

    public function remainingAfterPacking(): ?float
    {
        if ($this->source_quantity === null) return null;
        return round((float) $this->source_quantity - (float) $this->previously_packed_quantity - (float) $this->total_quantity, 3);
    }
}
