<?php

namespace App\Models;

use App\Helpers\CommonHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class SalesInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number', 'invoice_type', 'status', 'public_token', 'client_id', 'project_id',
        'customer_quote_id', 'invoice_date', 'due_date', 'valid_until', 'currency', 'exchange_rate',
        'gst_type', 'place_of_supply', 'po_number', 'po_date',
        'seller_company_name', 'seller_address', 'seller_city', 'seller_state', 'seller_country',
        'seller_pincode', 'seller_gstin', 'seller_pan', 'seller_email', 'seller_mobile',
        'seller_website', 'seller_bank_name', 'seller_account_holder', 'seller_account_number',
        'seller_ifsc', 'seller_branch', 'seller_swift',
        'client_company_name', 'client_brand_name', 'client_contact_name', 'client_email',
        'client_mobile', 'client_gstin', 'client_pan', 'billing_address', 'billing_city',
        'billing_state', 'billing_country', 'billing_pincode', 'shipping_address', 'shipping_city',
        'shipping_state', 'shipping_country', 'shipping_pincode', 'payment_terms', 'delivery_terms',
        'dispatch_terms', 'transport_mode', 'sales_person', 'subtotal', 'discount_type',
        'discount_value', 'discount_amount', 'taxable_amount', 'cgst_amount', 'sgst_amount',
        'igst_amount', 'freight_amount', 'packing_amount', 'other_charges', 'round_off',
        'total_amount', 'amount_paid', 'balance_amount', 'amount_in_words', 'terms_conditions',
        'notes', 'internal_notes', 'show_client_portal', 'sent_at', 'accepted_at', 'cancelled_at',
        'created_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'valid_until' => 'date',
        'po_date' => 'date',
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

    public function items()
    {
        return $this->hasMany(SalesInvoiceItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function payments()
    {
        return $this->hasMany(CashflowEntry::class)->orderBy('entry_date');
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

    /** Sales: tax invoices, cancellations aside. A proforma is never sales. */
    public function scopeSales(Builder $query): Builder
    {
        return $query->where('invoice_type', 'tax')->where('status', '!=', 'cancelled');
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

    public function customerQuote()
    {
        return $this->belongsTo(\App\Models\CustomerQuote::class, 'customer_quote_id');
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
    public const RECEIVED_SQL = '(coalesce(sales_invoices.amount_paid, 0) + (select coalesce(sum(credit_amount - debit_amount), 0) from cashflow_entries where cashflow_entries.sales_invoice_id = sales_invoices.id))';

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
            ), 2);
        }

        if (array_key_exists('received_credit', $this->attributes) || array_key_exists('received_debit', $this->attributes)) {
            return round(
                (float) ($this->attributes['received_credit'] ?? 0) - (float) ($this->attributes['received_debit'] ?? 0),
                2
            );
        }

        if (! $this->exists) {
            return 0.0;
        }

        return round((float) ($this->payments()
            ->selectRaw('coalesce(sum(credit_amount - debit_amount), 0) as received')
            ->first()?->received ?? 0), 2);
    }

    /** Everything received: the invoice's opening figure plus the ledger's receipts. */
    public function receivedAmount(): float
    {
        return round((float) $this->amount_paid + $this->ledgerReceived(), 2);
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

        return round(max((float) $this->total_amount - $this->receivedAmount(), 0), 2);
    }

    /** unpaid | partial | paid — read from the money, never from a stored word. */
    public function paymentState(): string
    {
        $total = round((float) $this->total_amount, 2);
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
     * Load the ledger totals for a whole page of invoices in two subqueries
     * rather than one query per row.
     */
    public function scopeWithReceived(Builder $query): Builder
    {
        return $query
            ->withSum('payments as received_credit', 'credit_amount')
            ->withSum('payments as received_debit', 'debit_amount');
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
            .' for '.$currency((float) $this->total_amount).'.';

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
        return [
            'seller_company_name' => 'MissPack India Pvt Ltd',
            'seller_address' => 'E-410, 4th Floor, City Centre, Near Idgah Circle, Prem Darwaja Road, Idgah',
            'seller_city' => 'Ahmedabad',
            'seller_state' => 'Gujarat',
            'seller_country' => 'India',
            'seller_pincode' => '380016',
            'seller_gstin' => '24AATCM8816E1Z5',
            'seller_pan' => 'AATCM8816E',
            'seller_email' => 'misspackindia@gmail.com',
            'seller_mobile' => '7041110823',
            'seller_website' => 'www.themisspack.com',
            'seller_bank_name' => 'HDFC BANK LTD',
            'seller_account_holder' => 'MISSPACK INDIA PRIVATE LIMITED',
            'seller_account_number' => '50200115168612',
            'seller_ifsc' => 'HDFC0000006 (0=Zero)',
            'seller_branch' => 'NAVRANGPURA',
            'seller_swift' => 'HDFCINBBXXX',
        ];
    }

    public static function defaultTerms(): string
    {
        return implode("\n", [
            '1. This invoice is subject to Ahmedabad, Gujarat jurisdiction only.',
            '2. Payment terms will be as mentioned in this invoice. Goods will be dispatched after payment confirmation where advance payment is applicable.',
            '3. Prices are valid only up to the invoice validity date and are subject to change after expiry.',
            '4. Taxes, freight, packing, insurance, duties and any government charges are extra unless specifically mentioned.',
            '5. Production, dispatch and delivery timelines are indicative and depend on payment, artwork approval, sample approval and material availability.',
            '6. Any product customization, printing, artwork or packaging changes after approval may affect price and timeline.',
            '7. Goods once sold/customized/printed will not be returned or exchanged unless there is a proven manufacturing defect.',
            '8. Please verify product name, quantity, capacity, color, artwork, shipping address and GST details before confirmation.',
            '9. Bank charges, forex fluctuation, logistics charges and third-party charges are to be borne by the buyer unless agreed otherwise.',
            '10. This is a computer-generated document and does not require a physical signature unless specifically requested.',
        ]);
    }
}
