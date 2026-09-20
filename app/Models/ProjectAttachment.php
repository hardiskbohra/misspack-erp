<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProjectAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id', 'project_product_id', 'project_comment_id', 'project_tracking_update_id',
        'project_payment_id', 'category', 'title', 'file_path', 'original_name', 'mime_type',
        'file_size', 'extension', 'is_photo', 'is_public', 'uploaded_by', 'uploaded_by_type',
        'client_name', 'notes',
    ];

    protected $casts = [
        'is_photo' => 'boolean',
        'is_public' => 'boolean',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function product()
    {
        return $this->belongsTo(ProjectProduct::class, 'project_product_id');
    }

    public function comment()
    {
        return $this->belongsTo(ProjectComment::class, 'project_comment_id');
    }

    public function trackingUpdate()
    {
        return $this->belongsTo(ProjectTrackingUpdate::class, 'project_tracking_update_id');
    }

    public function payment()
    {
        return $this->belongsTo(ProjectPayment::class, 'project_payment_id');
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
        if ($this->is_photo) {
            return true;
        }

        return Str::startsWith((string) $this->mime_type, 'image/');
    }

    public function categoryLabel(): string
    {
        return self::categoryOptions()[$this->category] ?? Str::headline((string) $this->category);
    }

    public static function categoryOptions(): array
    {
        return [
            'project_document' => 'Project Document',
            'product_photo' => 'Product Photo',
            'pps_photo' => 'PPS Photo',
            'production_photo' => 'Production Photo',
            'vendor_invoice' => 'Vendor Invoice',
            'proforma_invoice' => 'Proforma Invoice',
            'purchase_order' => 'Purchase Order',
            'packaging_list' => 'Packaging List',
            'artwork' => 'Artwork / Design',
            'qc_report' => 'QC Report',
            'payment_proof' => 'Payment Proof',
            'client_document' => 'Client Document',
            'other' => 'Other',
        ];
    }
}
