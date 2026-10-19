<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipmentPackingAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_item_id', 'packaging_list_item_id', 'project_product_id',
        'allocated_quantity', 'created_by',
    ];

    protected $casts = ['allocated_quantity' => 'decimal:3'];

    public function shipmentItem() { return $this->belongsTo(ShipmentItem::class); }
    public function packagingListItem() { return $this->belongsTo(PackagingListItem::class); }
    public function projectProduct() { return $this->belongsTo(ProjectProduct::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
