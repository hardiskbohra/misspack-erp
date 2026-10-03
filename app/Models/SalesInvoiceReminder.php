<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One chase: the day the office asked for the money, how, and what it said.
 *
 * This is a log, not a flag — an invoice is normally asked for several times, and
 * which attempt worked is exactly what the next chase is decided on.
 */
class SalesInvoiceReminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_invoice_id', 'channel', 'reminded_at', 'message', 'note', 'created_by',
    ];

    protected $casts = [
        'reminded_at' => 'date',
    ];

    /** How the office actually asks — WhatsApp first, because that is how it lands. */
    public static function channelOptions(): array
    {
        return [
            'whatsapp' => 'WhatsApp',
            'email' => 'Email',
            'phone' => 'Phone call',
            'other' => 'Other',
        ];
    }

    public function channelLabel(): string
    {
        return self::channelOptions()[$this->channel] ?? ucfirst((string) $this->channel);
    }

    public function invoice()
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
