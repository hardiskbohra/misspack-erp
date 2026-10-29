<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The e-invoice registration of one sales invoice (IRN, acknowledgement and
 * the signed QR string the portal returns).
 *
 * Statuses move one way except through failure: pending → registered →
 * cancelled, with pending → failed when a gateway rejects the request. A
 * failed record is re-registered by generating a fresh request — the IRN
 * portal itself disallows a second registration only once an IRN exists.
 */
class SalesInvoiceEInvoice extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_REGISTERED = 'registered';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_FAILED = 'failed';

    /** Cancel codes as the portal knows them. */
    public const CANCEL_REASONS = ['1' => 'Duplicate', '2' => 'Data entry mistake', '3' => 'Order cancelled', '4' => 'Other'];

    /** The portal only accepts a cancellation within 24 hours of the ack. */
    public const CANCEL_WINDOW_HOURS = 24;

    protected $table = 'sales_invoice_e_invoices';

    protected $fillable = [
        'sales_invoice_id', 'status', 'provider',
        'irn', 'ack_no', 'ack_date', 'signed_invoice', 'signed_qr_code',
        'request_payload', 'response_payload', 'error',
        'registered_by', 'cancelled_at', 'cancel_reason', 'cancel_remark',
    ];

    protected $casts = [
        'ack_date' => 'datetime',
        'request_payload' => 'array',
        'response_payload' => 'array',
        'cancelled_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }

    public function registrar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function isRegistered(): bool
    {
        return $this->status === self::STATUS_REGISTERED;
    }

    /** Cancellation is a portal rule, not our preference: past the window the answer is a credit note. */
    public function isCancellable(): bool
    {
        return $this->isRegistered()
            && $this->cancelled_at === null
            && $this->ack_date !== null
            && $this->ack_date->greaterThan(now()->subHours(self::CANCEL_WINDOW_HOURS));
    }
}
