<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CashflowEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'entry_date', 'particular', 'invoice_bill_number', 'bank_reference_number', 'transaction_type','project_id','sales_invoice_id',
        'credit_amount', 'debit_amount', 'balance', 'currency', 'account_id', 'category_id',
        'accounting_status', 'payment_mode', 'client_id', 'vendor_id', 'employee_id', 'expense_head',
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

    /**
     * The bills and slips filed against this entry. An entry with none is
     * what the ledger's "Missing documents" chip counts.
     */
    public function attachments()
    {
        return $this->hasMany(CashflowAttachment::class, 'cashflow_entry_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function client()
    {
        return $this->belongsTo(\App\Models\Client::class, 'client_id');
    }

    /**
     * The employee this entry is about — a real link, not the name typed into
     * the box (see the 2026_10_03_020000 migration: the old text is kept, so an
     * entry that named somebody the users table does not know still reads).
     */
    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    /**
     * Whose money this is, decided once: the linked party if there is one, else
     * the name written on the entry, else the expense head. Every screen that
     * prints a party prints this.
     */
    public function partyLabel(): string
    {
        return (string) ($this->client?->company_name
            ?? $this->vendor?->vendor_name
            ?? $this->employee?->name
            ?? $this->related_party_name
            ?? $this->expense_head
            ?? '');
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

                /* and the employee the entry is linked to, whose name is not on
                   the row: searching "Ramesh" has to find the payment that was
                   filed against him by id */
                if (class_exists(User::class) && Schema::hasColumn('cashflow_entries', 'employee_id')) {
                    $nested->orWhereHas('employee', fn (Builder $employee) => $employee->where('name', 'like', "%{$search}%"));
                }
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

    /**
     * Which way the money went, decided once.
     *
     * The ledger is the company's own cash book: `transaction_type` picks the
     * column that holds the money (the form writes `credit_amount` for a credit
     * and `debit_amount` for a debit), so a credit is money *in* and a debit is
     * money *out*. Everything that asks "what did this entry do" asks here.
     */
    public function isMoneyOut(): bool
    {
        return $this->transaction_type !== 'credit';
    }

    /** The entries where money left the company. */
    public function scopeMoneyOut(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('transaction_type')->orWhere('transaction_type', 'debit');
        });
    }

    /** The entries where money came in. */
    public function scopeMoneyIn(Builder $query): Builder
    {
        return $query->where('transaction_type', 'credit');
    }

    /** How much money this entry moved, whichever column holds it. */
    public function amountMoved(): float
    {
        return round((float) ($this->isMoneyOut()
            ? $this->debit_amount
            : $this->credit_amount), 2);
    }

    /**
     * What this entry did for the person named on it: positive when the company
     * paid money out to them, negative when money came back from them.
     */
    public function signedAmount(): float
    {
        return $this->isMoneyOut() ? $this->amountMoved() : -$this->amountMoved();
    }

    /**
     * The signed amount as it reads on a page, in the entry's own currency.
     * One place decides how the minus is drawn, so the office's record and the
     * employee's own pages cannot print the same movement two different ways.
     */
    public function signedAmountLabel(): string
    {
        $amount = \App\Helpers\CommonHelper::amount(abs($this->signedAmount()), $this->currency ?: 'INR');

        return $this->signedAmount() < 0 ? '−'.$amount : $amount;
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

    /**
     * Map any module's payment-mode key onto this module's vocabulary.
     *
     * Every writer of cashflow entries (vendor payments, shipment costs, and
     * whatever comes next) funnels through here, so a payment always shows how
     * it moved instead of silently losing the mode.
     */
    public static function normalisePaymentMode(?string $mode): ?string
    {
        $mode = $mode !== null ? strtolower(trim($mode)) : '';

        if ($mode === '') {
            return null;
        }

        $aliases = [
            'bank_transfer' => 'neft',
            'bank' => 'neft',
            'wire' => 'rtgs',
            'tt' => 'rtgs',
            'adjustment' => null, // book entry, not a bank movement
        ];

        if (array_key_exists($mode, $aliases)) {
            return $aliases[$mode];
        }

        return array_key_exists($mode, self::paymentModeOptions()) ? $mode : 'other';
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

    /**
     * Which column holds the link for each party type.
     *
     * The type is a label; the link is the fact. A client's statement is built
     * from `client_id` (`PartyStatement` reads the column, never the label), so
     * an entry linked to a client but labelled "Other" is on the client's
     * statement while the ledger filter for "Client" does not find it. The two
     * have to agree, and this is where that is decided.
     */
    public static function partyLinkColumns(): array
    {
        return [
            'client' => 'client_id',
            'vendor' => 'vendor_id',
            'employee' => 'employee_id',
        ];
    }

    /**
     * Make the label agree with the link.
     *
     *   - nothing linked: the chosen type stands (a cash expense, an owner
     *     drawing, a name typed in by hand);
     *   - the chosen type is one of the links that is set: fine, leave it;
     *   - otherwise the link wins, first match in `partyLinkColumns()` order —
     *     a payment to a client is a client entry even if the selector was left
     *     on its first option.
     */
    public static function alignPartyType(array $data): array
    {
        $linked = [];

        foreach (static::partyLinkColumns() as $party => $column) {
            if (! empty($data[$column])) {
                $linked[$party] = $column;
            }
        }

        if ($linked === []) {
            return $data;
        }

        $chosen = $data['related_party_type'] ?? null;

        if (is_string($chosen) && array_key_exists($chosen, $linked)) {
            return $data;
        }

        $data['related_party_type'] = array_key_first($linked);

        return $data;
    }

    public static function currencyOptions(): array
    {
        return ['INR' => 'INR', 'USD' => 'USD', 'RMB' => 'RMB'];
    }
}
