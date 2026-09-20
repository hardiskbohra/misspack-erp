<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProjectPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id', 'cashflow_entry_id', 'transaction_type', 'payment_date', 'amount', 'currency',
        'payment_mode', 'reference_number', 'party_type', 'party_name', 'category', 'status',
        'is_public', 'notes', 'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'is_public' => 'boolean',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function cashflowEntry()
    {
        return $this->belongsTo(\App\Models\CashflowEntry::class, 'cashflow_entry_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments()
    {
        return $this->hasMany(ProjectAttachment::class, 'project_payment_id')->latest('id');
    }

    public function typeLabel(): string
    {
        return self::transactionTypeOptions()[$this->transaction_type] ?? Str::headline((string) $this->transaction_type);
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? Str::headline((string) $this->status);
    }

    public static function transactionTypeOptions(): array
    {
        return [
            'inward' => 'Payment Inward',
            'outward' => 'Payment Outward / Expense',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            'pending' => 'Pending',
            'booked' => 'Booked',
            'reconciled' => 'Reconciled',
            'cancelled' => 'Cancelled',
        ];
    }

    public static function paymentModeOptions(): array
    {
        return [
            'neft' => 'NEFT',
            'rtgs' => 'RTGS',
            'imps' => 'IMPS',
            'upi' => 'UPI',
            'cash' => 'Cash',
            'cheque' => 'Cheque',
            'card' => 'Card',
            'other' => 'Other',
        ];
    }
}
