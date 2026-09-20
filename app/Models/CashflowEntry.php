<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CashflowEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'entry_date', 'particular', 'invoice_bill_number', 'bank_reference_number', 'transaction_type','project_id','sales_invoice_id',
        'credit_amount', 'debit_amount', 'balance', 'currency', 'account_id', 'category_id',
        'accounting_status', 'payment_mode', 'client_id', 'vendor_id', 'expense_head',
        'related_party_type', 'related_party_name', 'notes', 'created_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'credit_amount' => 'decimal:2',
        'debit_amount' => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    public function account()
    {
        return $this->belongsTo(CashflowAccount::class, 'account_id');
    }

    public function category()
    {
        return $this->belongsTo(CashflowCategory::class, 'category_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function client()
    {
        return $this->belongsTo(\App\Models\Client::class, 'client_id');
    }

    public function vendor()
    {
        return $this->belongsTo(\App\Models\Vendor::class, 'vendor_id');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, function (Builder $q) use ($search) {
            $q->where(function (Builder $nested) use ($search) {
                $nested->where('particular', 'like', "%{$search}%")
                    ->orWhere('invoice_bill_number', 'like', "%{$search}%")
                    ->orWhere('bank_reference_number', 'like', "%{$search}%")
                    ->orWhere('related_party_name', 'like', "%{$search}%")
                    ->orWhere('expense_head', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        });
    }

    public function amount(): float
    {
        return (float) ($this->transaction_type === 'credit' ? $this->credit_amount : $this->debit_amount);
    }
    
    public function project()
    {
        return $this->belongsTo(\App\Models\Project::class, 'project_id');
    }
    
    public function salesInvoice()
    {
        return $this->belongsTo(\App\Models\SalesInvoice::class, 'sales_invoice_id');
    }

    public function statusLabel(): string
    {
        return self::accountingStatusOptions()[$this->accounting_status] ?? Str::headline($this->accounting_status);
    }

    public static function transactionTypeOptions(): array
    {
        return ['credit' => 'Credit', 'debit' => 'Debit'];
    }

    public static function accountingStatusOptions(): array
    {
        return [
            'pending' => 'Pending',
            'booked' => 'Booked',
            'reconciled' => 'Reconciled',
            'disputed' => 'Disputed',
            'ignored' => 'Ignored',
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

    public static function relatedPartyOptions(): array
    {
        return [
            'client' => 'Client',
            'vendor' => 'Vendor',
            'expense' => 'Cash Expense',
            'owner' => 'Owner / Capital',
            'employee' => 'Employee',
            'other' => 'Other',
        ];
    }

    public static function currencyOptions(): array
    {
        return ['INR' => 'INR', 'USD' => 'USD', 'RMB' => 'RMB'];
    }
}
