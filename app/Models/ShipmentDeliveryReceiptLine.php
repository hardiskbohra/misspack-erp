<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipmentDeliveryReceiptLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_delivery_receipt_id','shipment_item_id','project_product_id',
        'received_quantity','accepted_quantity','short_quantity','damaged_quantity',
        'rejected_quantity','returned_quantity','replacement_quantity','discrepancy_reason',
    ];

    protected $casts = [
        'received_quantity'=>'decimal:3','accepted_quantity'=>'decimal:3','short_quantity'=>'decimal:3',
        'damaged_quantity'=>'decimal:3','rejected_quantity'=>'decimal:3','returned_quantity'=>'decimal:3',
        'replacement_quantity'=>'decimal:3',
    ];

    public function receipt() { return $this->belongsTo(ShipmentDeliveryReceipt::class, 'shipment_delivery_receipt_id'); }
    public function shipmentItem() { return $this->belongsTo(ShipmentItem::class); }
    public function projectProduct() { return $this->belongsTo(ProjectProduct::class); }

    public function hasDiscrepancy(): bool
    {
        return (float)$this->short_quantity > 0 || (float)$this->damaged_quantity > 0
            || (float)$this->rejected_quantity > 0 || (float)$this->returned_quantity > 0;
    }
}
