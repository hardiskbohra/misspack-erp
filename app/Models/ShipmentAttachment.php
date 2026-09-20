<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipmentAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id', 'attachment_type', 'title', 'file_path', 'original_name', 'mime_type',
        'file_size', 'is_public', 'sort_order', 'uploaded_by',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
