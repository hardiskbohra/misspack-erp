<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackagingListItem extends Model
{
    use HasFactory;

    protected $fillable = ['packaging_list_carton_id', 'product_id', 'item_description', 'quantity_per_carton', 'total_quantity', 'unit', 'sort_order'];
    protected $casts = ['quantity_per_carton' => 'decimal:3', 'total_quantity' => 'decimal:3'];

    public function carton() { return $this->belongsTo(PackagingListCarton::class, 'packaging_list_carton_id'); }
    public function product() { return $this->belongsTo(Product::class); }
}
