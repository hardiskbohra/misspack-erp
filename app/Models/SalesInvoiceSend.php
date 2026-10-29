<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One send event: an invoice e-mail that left the office (or tried to).
 *
 * Like SalesInvoiceReminder this is a *log*, never a flag — the same invoice
 * goes out many times (first send, re-send to the accounts desk, the "we never
 * got it" forward), and which attempt carried what words is exactly what the
 * next conversation is decided on. `status = failed` keeps the transport's
 * reason so a misfire is visible on the invoice instead of vanishing into
 * the queue logs.
 */
class SalesInvoiceSend extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_invoice_id', 'channel', 'recipients', 'cc', 'subject', 'message',
        'status', 'error', 'sent_by', 'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
