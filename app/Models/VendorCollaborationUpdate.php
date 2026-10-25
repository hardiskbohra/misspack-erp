<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VendorCollaborationUpdate extends Model
{
    use HasFactory;

    public const STATUSES = [
        'acknowledged' => 'Order acknowledged',
        'question' => 'Question / clarification',
        'in_production' => 'In production',
        'ready_to_dispatch' => 'Ready to dispatch',
        'dispatch_planned' => 'Dispatch planned',
        'document_submitted' => 'Document submitted',
        'general' => 'General update',
    ];

    protected $fillable = [
        'vendor_id', 'purchase_invoice_id', 'status', 'message', 'expected_date', 'author_type',
        'vendor_portal_user_id', 'created_by', 'vendor_share_link_id', 'read_by_office_at',
    ];

    protected $casts = ['expected_date' => 'date', 'read_by_office_at' => 'datetime'];

    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function purchaseInvoice() { return $this->belongsTo(PurchaseInvoice::class); }
    public function portalUser() { return $this->belongsTo(VendorPortalUser::class, 'vendor_portal_user_id'); }
    public function shareLink() { return $this->belongsTo(VendorShareLink::class, 'vendor_share_link_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    public function statusLabel(): string { return self::STATUSES[$this->status] ?? Str::headline($this->status); }
    public function authorLabel(): string
    {
        return match ($this->author_type) {
            'staff' => $this->creator?->name ?: 'MissPack team',
            'secure_link' => 'Vendor secure link',
            default => $this->portalUser?->displayName() ?: $this->vendor?->vendor_name ?: 'Vendor',
        };
    }
}
