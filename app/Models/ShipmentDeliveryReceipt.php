<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ShipmentDeliveryReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id','receipt_reference','recipient_name','received_at','notes',
        'evidence_path','evidence_original_name','evidence_mime','evidence_size',
        'evidence_hash','content_hash','recorded_by',
    ];

    protected $casts = ['received_at'=>'datetime', 'evidence_size'=>'integer'];

    public function shipment() { return $this->belongsTo(Shipment::class); }
    public function lines() { return $this->hasMany(ShipmentDeliveryReceiptLine::class); }
    public function recorder() { return $this->belongsTo(User::class, 'recorded_by'); }
    public function evidenceUrl(): string { return Storage::disk('public')->url($this->evidence_path); }

    public function hasDiscrepancy(): bool
    {
        $lines = $this->relationLoaded('lines') ? $this->lines : $this->lines()->get();
        return $lines->contains(fn ($line) => $line->hasDiscrepancy());
    }
}
