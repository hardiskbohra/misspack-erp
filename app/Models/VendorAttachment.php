<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VendorAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id', 'category', 'title', 'file_path', 'original_name', 'mime_type',
        'file_size', 'extension', 'notes', 'uploaded_by',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function fileUrl(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }

    public function isImage(): bool
    {
        return Str::startsWith((string) $this->mime_type, 'image/');
    }

    public function categoryLabel(): string
    {
        return self::categoryOptions()[$this->category] ?? Str::headline((string) $this->category);
    }

    public static function categoryOptions(): array
    {
        return [
            'agreement' => 'Agreement / Contract',
            'scanner' => 'Scanner',
            'bank' => 'Bank Document',
            'tax' => 'Tax / GST / PAN',
            'catalog' => 'Product Catalogue',
            'payment_proof' => 'Payment Proof',
            'shipment' => 'Shipment Document',
            'kyc' => 'KYC / Compliance',
            'other' => 'Other',
        ];
    }
}
