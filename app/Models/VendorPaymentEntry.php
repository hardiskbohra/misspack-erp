<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class VendorPaymentEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id', 'project_id', 'purchase_invoice_id', 'cashflow_entry_id', 'transaction_date', 'due_date', 'invoice_number',
        'foreign_currency', 'foreign_amount', 'exchange_rate', 'transaction_type', 'entry_category',
        'particular', 'status', 'paid_account_id', 'payment_mode', 'bank_reference_number',
        'amount_in_inr', 'remarks', 'created_by', 'source',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'due_date' => 'date',
        'foreign_amount' => 'decimal:4',
        'exchange_rate' => 'decimal:6',
        'amount_in_inr' => \App\Casts\DecimalUpToThree::class,
    ];

    /**
     * The row was derived from a cashflow entry, so the cashflow screen owns
     * it: the vendor ledger shows it but does not edit or delete it.
     */
    public function isFromCashflow(): bool
    {
        return (string) $this->source === \App\Services\CashflowVendorPaymentSync::SOURCE;
    }

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

    public function purchaseSplits()
    {
        return $this->hasMany(PurchasePaymentSplit::class, 'vendor_payment_entry_id');
    }

    public function purchaseInvoice()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    /**
     * A statement must not count a fully converted order's old credit beside
     * its bills, including an old/stale posting. Do not hide a real payment or
     * adjustment just because it is still linked to the order, and do not
     * delete any ledger history. Unlinked entries are also left untouched.
     */
    public function scopeForPartyStatement(Builder $query): Builder
    {
        if (! Schema::hasTable('purchase_invoices')
            || ! Schema::hasColumn('purchase_invoices', 'converted_invoice_id')
            || ! Schema::hasColumn('vendor_payment_entries', 'purchase_invoice_id')
            || ! Schema::hasColumn('vendor_payment_entries', 'entry_category')) {
            return $query;
        }

        $hasCashflowLink = Schema::hasColumn('vendor_payment_entries', 'cashflow_entry_id');

        return $query->where(function (Builder $visible) use ($hasCashflowLink) {
            $visible->whereNull('vendor_payment_entries.transaction_type')
                ->orWhere('vendor_payment_entries.transaction_type', '!=', 'credit')
                ->orWhereNull('vendor_payment_entries.entry_category')
                ->orWhere('vendor_payment_entries.entry_category', '!=', 'order');

            // A linked bank/cashflow movement is real money, not a PO mirror.
            if ($hasCashflowLink) {
                $visible->orWhereNotNull('vendor_payment_entries.cashflow_entry_id');
            }

            $visible->orWhereDoesntHave('purchaseInvoice', fn (Builder $order) => $order->fullyConverted()
                ->whereColumn('purchase_invoices.vendor_id', 'vendor_payment_entries.vendor_id'));
        });
    }

    /** Payments used by a partial conversion are immutable until it is reversed. */
    public function protectedByPurchaseConversion(): bool
    {
        if ($this->relationLoaded('purchaseSplits')) {
            if ($this->purchaseSplits->isNotEmpty()) return true;
        } elseif (\Illuminate\Support\Facades\Schema::hasTable('purchase_payment_splits') && $this->purchaseSplits()->exists()) {
            return true;
        }
        if (! $this->purchase_invoice_id) return false;
        if ($this->relationLoaded('purchaseInvoice')) return (bool) $this->purchaseInvoice?->conversion_kind;
        return \Illuminate\Support\Facades\Schema::hasColumn('purchase_invoices', 'conversion_kind')
            && PurchaseInvoice::whereKey($this->purchase_invoice_id)->whereNotNull('conversion_kind')->exists();
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
            'order' => 'Purchase Order',
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
