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

    /**
     * The icon a file row wears, decided by the file itself: the same
     * vocabulary the ledger's attachments use. It lives on the model so that
     * no view has to guess an icon from a file name.
     */
    public function icon(): string
    {
        if ($this->isImage()) {
            return 'fa-file-image';
        }

        return match (strtolower((string) $this->extension)) {
            'pdf' => 'fa-file-pdf',
            'doc', 'docx' => 'fa-file-word',
            'xls', 'xlsx', 'csv' => 'fa-file-excel',
            'ppt', 'pptx' => 'fa-file-powerpoint',
            'zip' => 'fa-file-zipper',
            'txt' => 'fa-file-lines',
            default => 'fa-file',
        };
    }

    /**
     * Bytes as somebody would say them out loud. Kept here rather than in a
     * helper because it is only ever the size of a file on this model.
     */
    public function sizeLabel(): string
    {
        $bytes = (int) $this->file_size;

        if ($bytes <= 0) {
            return '—';
        }

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return rtrim(rtrim(number_format($bytes / 1024, 1), '0'), '.').' KB';
        }

        return rtrim(rtrim(number_format($bytes / (1024 * 1024), 1), '0'), '.').' MB';
    }
}
