<?php

namespace App\Models;

use App\Helpers\CommonHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SalesInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number', 'invoice_type', 'status', 'public_token', 'client_id', 'project_id',
        'invoice_date', 'due_date', 'valid_until', 'currency', 'exchange_rate',
        'gst_type', 'place_of_supply', 'po_number', 'po_date',
        'seller_company_name', 'seller_address', 'seller_city', 'seller_state', 'seller_country',
        'seller_pincode', 'seller_gstin', 'seller_pan', 'seller_email', 'seller_mobile',
        'seller_website', 'seller_bank_name', 'seller_account_holder', 'seller_account_number',
        'seller_ifsc', 'seller_branch', 'seller_swift',
        'client_company_name', 'client_brand_name', 'client_contact_name', 'client_email',
        'client_mobile', 'client_gstin', 'client_pan', 'billing_address', 'billing_city',
        'billing_state', 'billing_country', 'billing_pincode', 'shipping_address', 'shipping_city',
        'shipping_state', 'shipping_country', 'shipping_pincode', 'payment_terms', 'advance_required',
        'advance_percentage', 'balance_before_dispatch', 'payment_tolerance', 'delivery_terms',
        'dispatch_terms', 'transport_mode', 'sales_person', 'subtotal', 'discount_type',
        'discount_value', 'discount_amount', 'taxable_amount', 'cgst_amount', 'sgst_amount',
        'igst_amount', 'freight_amount', 'packing_amount', 'other_charges', 'round_off',
        'total_amount', 'amount_paid', 'balance_amount', 'amount_in_words', 'terms_conditions',
        'notes', 'internal_notes', 'show_client_portal', 'sent_at', 'accepted_at', 'cancelled_at',
        'created_by', 'source_proforma_id', 'conversion_base_amount', 'conversion_total_amount',
        'cash_converted_base_amount', 'cash_converted_total_amount',
    ];

    protected $casts = [
        'conversion_base_amount' => 'decimal:2', 'conversion_total_amount' => 'decimal:2',
        'cash_converted_base_amount' => 'decimal:2', 'cash_converted_total_amount' => 'decimal:2',
        'invoice_date' => 'date',
        'due_date' => 'date',
        'valid_until' => 'date',
        'po_date' => 'date',
        'advance_required' => 'boolean',
        'advance_percentage' => 'decimal:2',
        'balance_before_dispatch' => 'boolean',
        'payment_tolerance' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'discount_value' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'taxable_amount' => 'decimal:2',
        'cgst_amount' => 'decimal:2',
        'sgst_amount' => 'decimal:2',
        'igst_amount' => 'decimal:2',
        'freight_amount' => 'decimal:2',
        'packing_amount' => 'decimal:2',
        'other_charges' => 'decimal:2',
        'round_off' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'balance_amount' => 'decimal:2',
        'show_client_portal' => 'boolean',
        'sent_at' => 'datetime',
        'accepted_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (SalesInvoice $invoice) {
            if (! $invoice->public_token) {
                $invoice->public_token = Str::random(48);
            }
        });
    }

    public const RECEIVABLE_TOTAL_SQL = '(sales_invoices.total_amount - coalesce(sales_invoices.cash_converted_total_amount, 0))';

    public function isCash(): bool
    {
        return $this->invoice_type === 'cash';
    }

    public function isConvertedCash(): bool
    {
        return $this->isCash() && (int) $this->source_proforma_id > 0;
    }

    public function deleteConfirmation(): string
    {
        return $this->isConvertedCash()
            ? 'Delete '.$this->invoice_number.' and reverse its cash conversion? The converted value, including the PI’s original GST, and all opening credit/receipts will return to the original PI. Bank balances will not change.'
            : 'Delete '.$this->invoice_number.'?';
    }

    public function conversionSource()
    {
        return $this->belongsTo(self::class, 'source_proforma_id');
    }

    public function cashInvoices()
    {
        return $this->hasMany(self::class, 'source_proforma_id')->where('invoice_type', 'cash');
    }

    public function hasMonetaryConversions(): bool
    {
        return (float) $this->cash_converted_base_amount > 0 || (int) $this->source_proforma_id > 0;
    }

    /** Original document less the gross value already replaced by cash invoices. */
    public function receivableTotal(): float
    {
        return round(max((float) $this->total_amount - (float) $this->cash_converted_total_amount, 0), 2);
    }

    public function cashConvertibleTotal(): float
    {
        return round(max((float) $this->total_amount - (float) $this->cgst_amount - (float) $this->sgst_amount - (float) $this->igst_amount, 0), 2);
    }

    public function cashConvertibleRemaining(): float
    {
        return round(max($this->cashConvertibleTotal() - (float) $this->cash_converted_base_amount, 0), 2);
    }

    /** All monetary claims/credits continuing a baseline PI, each exactly once. */
    public function settlementDocuments(): \Illuminate\Support\Collection
    {
        if ($this->invoice_type === 'proforma' && $this->hasMonetaryConversions()) {
            $documents = self::where('source_proforma_id', $this->id)->orderBy('id')->get();
            if (! $this->isSuperseded()) {
                $documents->push($this);
            }
            return $documents;
        }

        return collect([$this->isSuperseded() ? $this->convertedInvoice : $this])->filter();
    }

    public function items()
    {
        return $this->hasMany(SalesInvoiceItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function payments()
    {
        return $this->hasMany(CashflowEntry::class)->orderBy('entry_date');
    }

    /** The GST-network registration of this invoice, once it exists. */
    public function eInvoice()
    {
        return $this->hasOne(SalesInvoiceEInvoice::class, 'sales_invoice_id');
    }

    public function cashflowAllocations()
    {
        return $this->hasMany(CashflowAllocation::class, 'sales_invoice_id')->orderBy('id');
    }

    public function attachments()
    {
        return $this->hasMany(SalesInvoiceAttachment::class)->latest('id');
    }

    /** Every chase, newest first — the record page's own timeline. */
    public function reminders()
    {
        return $this->hasMany(SalesInvoiceReminder::class)
            ->orderByDesc('reminded_at')
            ->orderByDesc('id');
    }

    public function publicAttachments()
    {
        return $this->hasMany(SalesInvoiceAttachment::class)->where('is_public', true)->latest('id');
    }

    /**
     * The tax invoice this proforma became.
     *
     * The link is one column on the proforma, which is what makes "a proforma
     * becomes a tax invoice once" structural rather than a rule somebody has to
     * remember: there is one place to write it and one place to read it.
     */
    public function convertedInvoice()
    {
        return $this->belongsTo(self::class, 'converted_invoice_id');
    }

    /** The proforma a tax invoice was raised from, when there was one. */
    public function sourceInvoice()
    {
        return $this->hasOne(self::class, 'converted_invoice_id');
    }

    /**
     * A proforma that has become a tax invoice.
     *
     * It is history: the client was shown it, it stays readable, and it is not a
     * second receivable — the tax invoice stands for that money now.
     */
    public function isSuperseded(): bool
    {
        return $this->invoice_type === 'proforma' && (int) $this->converted_invoice_id > 0;
    }

    /**
     * The documents that stand for money.
     *
     * Everything that counts receivable — the figures, the statement, the
     * ageing — reads this scope. Without it, one advance is claimed by two
     * documents and the office's own totals say the client owes twice.
     */
    public function scopeNotSuperseded(Builder $query): Builder
    {
        return $query->where(fn ($query) => $query
            ->where('invoice_type', '!=', 'proforma')
            ->orWhereNull('converted_invoice_id'));
    }

    /** Sales: tax and cash invoices, cancellations aside. A proforma is never sales. */
    public function scopeSales(Builder $query): Builder
    {
        return $query->whereIn('invoice_type', ['tax', 'cash'])->where('status', '!=', 'cancelled');
    }

    /** Potential revenue: proformas no tax invoice has been raised from yet. */
    public function scopePotential(Builder $query): Builder
    {
        return $query->where('invoice_type', 'proforma')
            ->whereNull('converted_invoice_id')
            ->where('status', '!=', 'cancelled');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function project()
    {
        return $this->belongsTo(\App\Models\Project::class, 'project_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Money received against this invoice, as SQL.
     *
     * The same rule as `receivedAmount()` below, written once for the screens
     * that must not load a row per invoice: the opening figure the office typed
     * on the invoice, plus every receipt filed against it in the cashflow
     * ledger. A list, a set of figures or a filter uses this string; a single
     * invoice uses the method. Two spellings of one rule is how the ledger and
     * the statement started disagreeing in the first place.
     */
    public const RECEIVED_SQL = '(coalesce(sales_invoices.amount_paid, 0)'
        .' + (select coalesce(sum(credit_amount - debit_amount), 0) from cashflow_entries where cashflow_entries.sales_invoice_id = sales_invoices.id)'
        .' + (select coalesce(sum(coalesce(cashflow_allocations.document_amount, cashflow_allocations.amount)), 0) from cashflow_allocations join cashflow_entries on cashflow_entries.id = cashflow_allocations.cashflow_entry_id where cashflow_allocations.sales_invoice_id = sales_invoices.id))';

    /**
     * The receipts filed against this invoice in the ledger.
     *
     * Three ways in, cheapest first: a loaded relation (one query for a page of
     * invoices), the aggregates `withSum()` put on the row, or a query of its
     * own for a single invoice fetched on its own.
     */
    public function ledgerReceived(): float
    {
        if ($this->relationLoaded('payments')) {
            return round((float) $this->payments->sum(
                fn ($payment) => (float) $payment->credit_amount - (float) $payment->debit_amount
            ) + $this->allocatedReceived(), 2);
        }

        if (array_key_exists('received_credit', $this->attributes) || array_key_exists('received_debit', $this->attributes)) {
            return round(
                (float) ($this->attributes['received_credit'] ?? 0)
                    - (float) ($this->attributes['received_debit'] ?? 0)
                    + $this->allocatedReceived(),
                2
            );
        }

        if (! $this->exists) {
            return 0.0;
        }

        return round((float) ($this->payments()
            ->reorder()
            ->selectRaw('coalesce(sum(credit_amount - debit_amount), 0) as received')
            ->first()?->received ?? 0) + $this->allocatedReceived(), 2);
    }

    /**
     * Explicit allocations are positive applications to this document.
     *
     * The parent cashflow's credit/debit controls the account ledger, not the
     * sign of `document_amount`. A user who allocates 200,000 to an invoice has
     * applied 200,000; interpreting a debit parent as -200,000 produces a false
     * refund and increases the invoice balance instead of settling it.
     */
    private function allocatedReceived(bool $finalizedOnly = false): float
    {
        $attribute = $finalizedOnly ? 'client_portal_allocation_received' : 'allocation_received';
        if (array_key_exists($attribute, $this->attributes)) {
            return round((float) ($this->attributes[$attribute] ?? 0), 2);
        }
        if (! $this->exists || ! Schema::hasTable('cashflow_allocations')) {
            return 0.0;
        }

        $total = CashflowAllocation::query()
            ->join('cashflow_entries', 'cashflow_entries.id', '=', 'cashflow_allocations.cashflow_entry_id')
            ->where('cashflow_allocations.sales_invoice_id', $this->id)
            ->when($finalizedOnly, fn ($query) => $query->whereIn('cashflow_entries.accounting_status', ['booked', 'reconciled']))
            ->selectRaw("coalesce(sum(coalesce(cashflow_allocations.document_amount, cashflow_allocations.amount)), 0) as total")
            ->first()?->total;

        return round((float) ($total ?? 0), 2);
    }

    /** Everything received: the invoice's opening figure plus the ledger's receipts. */
    public function receivedAmount(): float
    {
        return round((float) $this->amount_paid + $this->ledgerReceived(), 2);
    }

    /** Client-safe received amount: the opening figure plus finalized receipts only. */
    public function clientPortalReceivedAmount(): float
    {
        if ($this->relationLoaded('payments')) {
            $ledger = $this->payments
                ->whereIn('accounting_status', ['booked', 'reconciled'])
                ->sum(fn ($payment) => (float) $payment->credit_amount - (float) $payment->debit_amount);

            return round((float) $this->amount_paid + (float) $ledger + $this->allocatedReceived(true), 2);
        }

        if (array_key_exists('client_portal_received_credit', $this->attributes)
            || array_key_exists('client_portal_received_debit', $this->attributes)) {
            return round(
                (float) $this->amount_paid
                    + (float) ($this->attributes['client_portal_received_credit'] ?? 0)
                    - (float) ($this->attributes['client_portal_received_debit'] ?? 0)
                    + $this->allocatedReceived(true),
                2
            );
        }

        if (! $this->exists) {
            return (float) $this->amount_paid;
        }

        $ledger = $this->payments()
            ->whereIn('accounting_status', ['booked', 'reconciled'])
            ->reorder()
            ->selectRaw('coalesce(sum(credit_amount - debit_amount), 0) as received')
            ->first()?->received;

        return round((float) $this->amount_paid + (float) ($ledger ?? 0) + $this->allocatedReceived(true), 2);
    }

    public function clientPortalBalanceDue(): float
    {
        if ($this->isSuperseded()) {
            return 0.0;
        }

        return round(max($this->receivableTotal() - $this->clientPortalReceivedAmount(), 0), 2);
    }

    public function clientPortalStateKey(?\DateTimeInterface $today = null): string
    {
        if ($this->status === 'cancelled') {
            return 'cancelled';
        }
        if ($this->isSuperseded()) {
            return 'converted';
        }
        if ($this->status === 'draft') {
            return 'draft';
        }
        if ($this->due_date && $this->clientPortalBalanceDue() > 0.01
            && $this->due_date->lt($today ?: Carbon::today())) {
            return 'overdue';
        }

        $received = $this->clientPortalReceivedAmount();
        if ($this->receivableTotal() > 0 && $received >= $this->receivableTotal() - 0.01) {
            return 'paid';
        }
        if ($received > 0.01) {
            return 'partial';
        }

        return in_array($this->status, ['sent', 'accepted'], true) ? $this->status : 'sent';
    }

    public function clientPortalStateLabel(?\DateTimeInterface $today = null): string
    {
        $key = $this->clientPortalStateKey($today);

        return [
            'draft' => 'Draft',
            'cancelled' => 'Cancelled',
            'converted' => 'Converted',
            'overdue' => 'Overdue',
            'paid' => 'Paid',
            'partial' => 'Partly paid',
            'accepted' => 'Accepted',
            'sent' => 'Awaiting payment',
        ][$key] ?? Str::headline($key);
    }

    /** What is still owed on it. */
    public function balanceDue(): float
    {
        /* A proforma that has become a tax invoice owes nothing: the opening
           figure and the receipts moved to the tax invoice with it, and that is
           the document the client pays. Reading "total − received" here would
           put the same 50,000 back on the books as the day it was raised. */
        if ($this->isSuperseded()) {
            return 0.0;
        }

        return round(max($this->receivableTotal() - $this->receivedAmount(), 0), 2);
    }

    public function advanceAmount(): float
    {
        return round(max($this->receivedAmount() - $this->receivableTotal(), 0), 2);
    }

    /** unpaid | partial | paid — read from the money, never from a stored word. */
    public function paymentState(): string
    {
        $total = $this->receivableTotal();
        $received = $this->receivedAmount();

        if ($total > 0 && $received >= $total - 0.01) {
            return 'paid';
        }

        return $received > 0.01 ? 'partial' : 'unpaid';
    }

    /**
     * Late: the money was due on a date that has passed and is still owed.
     *
     * A draft or a cancelled invoice is never late — nobody has been asked for
     * the money yet, and asking twice for one nobody owes is how a worklist
     * stops being read.
     */
    public function isOverdue(?\DateTimeInterface $today = null): bool
    {
        if (in_array($this->status, ['draft', 'cancelled'], true) || ! $this->due_date) {
            return false;
        }

        return $this->balanceDue() > 0.01
            && $this->due_date->lt($today ?: Carbon::today());
    }

    public function daysOverdue(?\DateTimeInterface $today = null): int
    {
        if (! $this->isOverdue($today)) {
            return 0;
        }

        /* `$today` may be any date the caller has (Carbon, CarbonImmutable, a
           plain DateTime) — normalise before asking it for Carbon's own methods. */
        $today = $today ? Carbon::parse($today) : Carbon::today();

        /* Signed the way the rest of the office reads a date difference
           (`PartyStatement`): due date to today, so a passed due date is a
           positive number of days late. */
        return (int) $this->due_date->copy()->startOfDay()
            ->diffInDays($today->copy()->startOfDay(), false);
    }

    /**
     * How late, in the buckets an office chases in.
     *
     * The keys are the filter's own vocabulary (`SalesInvoiceFilters`), so a
     * chip that says "31–60 days" filters by the bucket this method names.
     */
    public function ageingBucket(?\DateTimeInterface $today = null): string
    {
        if (! $this->isOverdue($today)) {
            return 'current';
        }

        $days = $this->daysOverdue($today);

        return match (true) {
            $days <= 30 => '1_30',
            $days <= 60 => '31_60',
            $days <= 90 => '61_90',
            default => '90_plus',
        };
    }

    /** @return array<string, string> bucket key => the words on the chip */
    public static function ageingBuckets(): array
    {
        return [
            'current' => 'Not yet due',
            '1_30' => '1–30 days late',
            '31_60' => '31–60 days late',
            '61_90' => '61–90 days late',
            '90_plus' => 'Over 90 days late',
        ];
    }

    /**
     * The one word the list's state chip wears.
     *
     * Draft and cancelled are the office's own decision and stand. Everything
     * else is the money's: late, paid, part paid — and when none of those, the
     * document's own state (sent, accepted) if the office set one.
     */
    public function stateKey(?\DateTimeInterface $today = null): string
    {
        if ($this->status === 'cancelled') {
            return 'cancelled';
        }

        /* Before draft and before the money: a converted proforma is history,
           and "paid" on it would be the wrong sentence twice over. */
        if ($this->isSuperseded()) {
            return 'converted';
        }

        if ($this->status === 'draft') {
            return 'draft';
        }

        if ($this->isOverdue($today)) {
            return 'overdue';
        }

        $payment = $this->paymentState();

        if ($payment !== 'unpaid') {
            return $payment;
        }

        return in_array($this->status, ['sent', 'accepted'], true) ? $this->status : 'sent';
    }

    public function stateLabel(?\DateTimeInterface $today = null): string
    {
        return [
            'draft' => 'Draft',
            'cancelled' => 'Cancelled',
            'converted' => 'Converted',
            'overdue' => 'Overdue',
            'paid' => 'Paid',
            'partial' => 'Partly paid',
            'accepted' => 'Accepted',
            'sent' => 'Awaiting payment',
        ][$this->stateKey($today)] ?? Str::headline($this->stateKey($today));
    }

    /**
     * Whether this document counts towards the project it is tagged to.
     *
     * A cancelled invoice is not owed, and a proforma a tax invoice has already
     * carried (`isSuperseded()`) is history: it keeps its row on the project's
     * Invoices tab and is not counted a second time. The project's estimated
     * value reads this predicate and nothing else.
     */
    public function countsTowardsProject(): bool
    {
        return $this->status !== 'cancelled' && ! $this->isSuperseded();
    }

    /**
     * Load the ledger totals for a whole page of invoices in two subqueries
     * rather than one query per row.
     */
    public function scopeWithReceived(Builder $query): Builder
    {
        $query->withSum('payments as received_credit', 'credit_amount')
            ->withSum('payments as received_debit', 'debit_amount');

        if (Schema::hasTable('cashflow_allocations')) {
            if ($query->getQuery()->columns === null) {
                $query->select('sales_invoices.*');
            }
            $query->addSelect(['allocation_received' => CashflowAllocation::query()
                ->join('cashflow_entries', 'cashflow_entries.id', '=', 'cashflow_allocations.cashflow_entry_id')
                ->whereColumn('cashflow_allocations.sales_invoice_id', 'sales_invoices.id')
                ->selectRaw("coalesce(sum(coalesce(cashflow_allocations.document_amount, cashflow_allocations.amount)), 0)")]);
        }

        return $query;
    }

    /** Portal totals count only receipts the office has finalized. */
    public function scopeWithClientPortalReceived(Builder $query): Builder
    {
        $finalizedPayments = fn (Builder $payments) => $payments->whereIn('accounting_status', ['booked', 'reconciled']);

        $query->withSum(['payments as client_portal_received_credit' => $finalizedPayments], 'credit_amount')
            ->withSum(['payments as client_portal_received_debit' => $finalizedPayments], 'debit_amount');

        if (Schema::hasTable('cashflow_allocations')) {
            if ($query->getQuery()->columns === null) {
                $query->select('sales_invoices.*');
            }
            $query->addSelect(['client_portal_allocation_received' => CashflowAllocation::query()
                ->join('cashflow_entries', 'cashflow_entries.id', '=', 'cashflow_allocations.cashflow_entry_id')
                ->whereColumn('cashflow_allocations.sales_invoice_id', 'sales_invoices.id')
                ->whereIn('cashflow_entries.accounting_status', ['booked', 'reconciled'])
                ->selectRaw("coalesce(sum(coalesce(cashflow_allocations.document_amount, cashflow_allocations.amount)), 0)")]);
        }

        return $query;
    }

    /**
     * The chase columns for a page of invoices, in two subqueries.
     *
     * "How many times" and "when last" are asked of the log, never stored on the
     * invoice: a stored counter drifts the first time a log row is deleted, and
     * the office would then be chased about the chasing.
     */
    public function scopeWithReminders(Builder $query): Builder
    {
        return $query
            ->withCount('reminders')
            ->withMax('reminders as last_reminded_at', 'reminded_at');
    }

    /** When this invoice was last chased (the row's own attribute when loaded). */
    public function lastRemindedAt(): ?\DateTimeInterface
    {
        if (array_key_exists('last_reminded_at', $this->attributes)) {
            return $this->attributes['last_reminded_at']
                ? Carbon::parse($this->attributes['last_reminded_at'])
                : null;
        }

        if ($this->relationLoaded('reminders')) {
            return $this->reminders->first()?->reminded_at;
        }

        if (! $this->exists) {
            return null;
        }

        $last = $this->reminders()->max('reminded_at');

        return $last ? Carbon::parse($last) : null;
    }

    /** How many times the office has asked for this money. */
    public function reminderCount(): int
    {
        if (array_key_exists('reminders_count', $this->attributes)) {
            return (int) $this->attributes['reminders_count'];
        }

        if ($this->relationLoaded('reminders')) {
            return $this->reminders->count();
        }

        return $this->exists ? $this->reminders()->count() : 0;
    }

    /**
     * The text the office sends when it chases this invoice.
     *
     * Written by the model, not by a screen: the list, the record page and the
     * reminder dialog all send the same words, and the amount in them is the same
     * `balanceDue()` every other surface prints.
     *
     * The link is only in the message when the client can open it — the public
     * page exists only while the invoice is on the portal, and a reminder that
     * links to a 404 is worse than a reminder with no link at all.
     */
    public function reminderMessage(?\DateTimeInterface $today = null): string
    {
        $today = $today ?: Carbon::today();
        $balance = $this->balanceDue();
        /* one formatter for money in either currency: rupees keep the symbol and
           lakh grouping, anything else keeps its own code (never a ₹ in front of a
           dollar figure). */
        $currency = fn (float $amount) => CommonHelper::amount($amount, $this->currency);

        $lines = ['Dear '.($this->client_contact_name ?: $this->client_company_name ?: 'Sir/Madam').',', ''];

        $lines[] = 'Invoice '.$this->invoice_number
            .($this->invoice_date ? ' dated '.$this->invoice_date->format('d M Y') : '')
            .' for '.$currency($this->receivableTotal()).'.';

        if ($balance > 0.01) {
            $lines[] = $currency($balance).' is still outstanding'
                .($this->due_date
                    ? ($this->isOverdue($today)
                        ? ', '.$this->daysOverdue($today).' days past the due date of '.$this->due_date->format('d M Y')
                        : ', due on '.$this->due_date->format('d M Y'))
                    : '')
                .'.';
        } else {
            $lines[] = 'This invoice is fully settled — thank you.';
        }

        if ($this->show_client_portal && $this->public_token) {
            $lines[] = '';
            $lines[] = 'View the invoice here: '.route('sales-invoices.public', $this->public_token);
        }

        $lines[] = '';
        $lines[] = trim(implode(' · ', array_filter([
            $this->seller_company_name,
            $this->seller_mobile,
        ]))) ?: 'Thank you.';

        return implode("\n", $lines);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, function (Builder $q) use ($search) {
            $q->where(function (Builder $nested) use ($search) {
                $nested->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('client_company_name', 'like', "%{$search}%")
                    ->orWhere('client_brand_name', 'like', "%{$search}%")
                    ->orWhere('client_gstin', 'like', "%{$search}%")
                    ->orWhere('po_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        });
    }

    public function typeLabel(): string
    {
        return self::typeOptions()[$this->invoice_type] ?? Str::headline((string) $this->invoice_type);
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? Str::headline((string) $this->status);
    }

    public function gstTypeLabel(): string
    {
        return self::gstTypeOptions()[$this->gst_type] ?? Str::headline((string) $this->gst_type);
    }

    public static function typeOptions(): array
    {
        return [
            'proforma' => 'Proforma Invoice',
            'tax' => 'Tax Invoice',
            'cash' => 'Cash Invoice',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            'draft' => 'Draft',
            'sent' => 'Sent',
            'accepted' => 'Accepted',
            'partial' => 'Partially Paid',
            'paid' => 'Paid',
            'invoiced' => 'Invoiced',
            'overdue' => 'Overdue',
            'cancelled' => 'Cancelled',
        ];
    }

    public static function currencyOptions(): array
    {
        return ['INR' => 'INR', 'USD' => 'USD', 'RMB' => 'RMB'];
    }

    public static function gstTypeOptions(): array
    {
        return [
            'intra_state' => 'CGST + SGST',
            'inter_state' => 'IGST',
            'export' => 'Export / LUT',
        ];
    }

    public static function defaultSellerDetails(): array
    {
        return Organisation::current()->sellerDetails();
    }

    public static function defaultTerms(bool $cash = false): string
    {
        return implode("\n", [
            '1. This invoice is subject to Ahmedabad, Gujarat jurisdiction only.',
            '2. Payment terms will be as mentioned in this invoice. Goods will be dispatched after payment confirmation where advance payment is applicable.',
            '3. Prices are valid only up to the invoice validity date and are subject to change after expiry.',
            $cash ? '4. Freight, packing, insurance and other charges apply only as mentioned in this invoice.' : '4. Taxes, freight, packing, insurance, duties and any government charges are extra unless specifically mentioned.',
            '5. Production, dispatch and delivery timelines are indicative and depend on payment, artwork approval, sample approval and material availability.',
            '6. Any product customization, printing, artwork or packaging changes after approval may affect price and timeline.',
            '7. Goods once sold/customized/printed will not be returned or exchanged unless there is a proven manufacturing defect.',
            $cash ? '8. Please verify product name, quantity, capacity, color, artwork and shipping address before confirmation.' : '8. Please verify product name, quantity, capacity, color, artwork, shipping address and GST details before confirmation.',
            '9. Bank charges, forex fluctuation, logistics charges and third-party charges are to be borne by the buyer unless agreed otherwise.',
            '10. This is a computer-generated document and does not require a physical signature unless specifically requested.',
        ]);
    }

    public function packagingLists()
    {
        return $this->hasMany(PackagingList::class);
    }
}
