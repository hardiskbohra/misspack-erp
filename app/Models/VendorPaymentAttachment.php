<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VendorPaymentAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_payment_entry_id', 'title', 'file_path', 'original_name', 'mime_type',
        'file_size', 'extension', 'uploaded_by',
    ];

    public function entry()
    {
        return $this->belongsTo(VendorPaymentEntry::class, 'vendor_payment_entry_id');
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
}
