<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipmentTrackingHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id', 'status', 'location', 'remarks', 'event_time', 'is_public', 'created_by',
    ];

    protected $casts = [
        'event_time' => 'datetime',
        'is_public' => 'boolean',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
