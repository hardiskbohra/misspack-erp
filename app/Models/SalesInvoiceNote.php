<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A posted correction against a tax invoice — credit or debit, and a return
 * when it carries lines back. Notes are immutable once issued: a wrong note is
 * *cancelled* with its own reason, never edited — that way the chain of
 * documents always says what actually happened.
 */
class SalesInvoiceNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_invoice_id', 'number', 'kind', 'is_return', 'restock', 'lines',
        'note_date', 'reason', 'subtotal', 'cgst_amount', 'sgst_amount', 'igst_amount',
        'total', 'created_by', 'status', 'cancelled_at', 'cancel_reason',
    ];

    protected $casts = [
        'is_return' => 'boolean',
        'restock' => 'boolean',
        'lines' => 'array',
        'note_date' => 'date',
        'cancelled_at' => 'datetime',
    ];

    public const KIND_CREDIT = 'credit';
    public const KIND_DEBIT = 'debit';

    public const STATUS_ISSUED = 'issued';
    public const STATUS_CANCELLED = 'cancelled';

    public function invoice()
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isLive(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }

    /** A credit subtracts from what the client owes; a debit adds to it. */
    public function payableDelta(): float
    {
        if (! $this->isLive()) {
            return 0.0;
        }

        return $this->kind === self::KIND_CREDIT ? -(float) $this->total : (float) $this->total;
    }

    public function kindLabel(): string
    {
        return $this->kind === self::KIND_CREDIT ? 'Credit note' : 'Debit note';
    }

    public function titleLabel(): string
    {
        return $this->is_return
            ? 'Sales return — '.$this->number
            : $this->kindLabel().' — '.$this->number;
    }
}
