<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SalesInvoiceAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_invoice_id', 'title', 'file_path', 'original_name', 'mime_type',
        'file_size', 'extension', 'is_public', 'uploaded_by',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    public function invoice()
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
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
