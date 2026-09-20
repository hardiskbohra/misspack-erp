<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VendorPaymentEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id', 'project_id', 'cashflow_entry_id', 'transaction_date', 'invoice_number',
        'foreign_currency', 'foreign_amount', 'exchange_rate', 'transaction_type', 'entry_category',
        'particular', 'status', 'paid_account_id', 'payment_mode', 'bank_reference_number',
        'amount_in_inr', 'remarks', 'created_by',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'foreign_amount' => 'decimal:4',
        'exchange_rate' => 'decimal:6',
        'amount_in_inr' => 'decimal:2',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function project()
    {
        return $this->belongsTo(\App\Models\Project::class, 'project_id');
    }

    public function cashflowEntry()
    {
        return $this->belongsTo(\App\Models\CashflowEntry::class, 'cashflow_entry_id');
    }

    public function paidAccount()
    {
        return $this->belongsTo(\App\Models\CashflowAccount::class, 'paid_account_id');
    }

    public function attachments()
    {
        return $this->hasMany(VendorPaymentAttachment::class)->latest('id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeLabel(): string
    {
        return self::transactionTypeOptions()[$this->transaction_type] ?? Str::headline((string) $this->transaction_type);
    }

    public function categoryLabel(): string
    {
        return self::categoryOptions()[$this->entry_category] ?? Str::headline((string) $this->entry_category);
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? Str::headline((string) $this->status);
    }

    public static function transactionTypeOptions(): array
    {
        return [
            'credit' => 'Credit / Bill Generated',
            'debit' => 'Debit / Paid To Vendor',
        ];
    }

    public static function categoryOptions(): array
    {
        return [
            'bill' => 'Vendor Bill / Invoice',
            'payment' => 'Payment To Vendor',
            'expense' => 'Expense Done By Vendor',
            'adjustment' => 'Adjustment',
            'refund' => 'Refund / Credit Note',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            'pending' => 'Pending',
            'booked' => 'Booked',
            'paid' => 'Paid',
            'reconciled' => 'Reconciled',
            'cancelled' => 'Cancelled',
        ];
    }

    public static function currencyOptions(): array
    {
        return ['RMB' => 'RMB', 'USD' => 'USD', 'INR' => 'INR'];
    }

    public static function paymentModeOptions(): array
    {
        return [
            'bank_transfer' => 'Bank Transfer',
            'wire' => 'Wire / TT',
            'cash' => 'Cash',
            'upi' => 'UPI',
            'cheque' => 'Cheque',
            'card' => 'Card',
            'adjustment' => 'Adjustment',
            'other' => 'Other',
        ];
    }
}
