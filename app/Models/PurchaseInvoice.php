<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A purchase order, or the purchase bill raised from it.
 *
 * The two are one document at two stages of its life — the way a proforma and a
 * tax invoice are on the sales side — so they share a table, a numbering style,
 * and every rule below. What differs is who is owed and what the state words
 * mean, and that is what `invoice_type` decides.
 */
class PurchaseInvoice extends Model
{
    use HasFactory;

    public const TYPE_ORDER = 'order';
    public const TYPE_BILL = 'bill';

    public const DOC_LABELS = [
        'order' => 'Purchase Order',
        'bill' => 'Purchase Bill',
    ];

    /**
     * The type words the controller may name in a status transition.
     *
     * `order` walks draft -> sent -> approved -> billed; `bill` walks
     * draft -> received -> partial -> paid. `cancelled` ends either. The
     * money's own words (`partial`, `paid`) are written by
     * `refreshInvoiceMoney()`, never typed, which is why they are not offered
     * in the form's status list.
     */
    public const STATUS_FLOW = [
        'order' => [
            'draft' => ['sent', 'approved', 'cancelled'],
            'sent' => ['approved', 'cancelled'],
            'approved' => ['cancelled'],
            'billed' => [],
            'cancelled' => [],
        ],
        'bill' => [
            'draft' => ['received', 'cancelled'],
            'received' => ['cancelled'],
            'partial' => ['cancelled'],
            'paid' => [],
            'cancelled' => [],
        ],
    ];

    /** The paid figure is upstream of every balance: one SQL, read everywhere. */
    public const PAID_SQL = '(coalesce(purchase_invoices.amount_paid, 0) + (select coalesce(sum(foreign_amount), 0) from vendor_payment_entries where vendor_payment_entries.purchase_invoice_id = purchase_invoices.id and vendor_payment_entries.transaction_type = \'debit\'))';

    /** The supplier's own bill number, falling back to our document number. */
    public function referenceNumber(): string
    {
        return (string) ($this->vendor_bill_number ?: $this->invoice_number);
    }

    protected $fillable = [
        'invoice_number', 'invoice_type', 'status', 'public_token',
        'vendor_id', 'project_id', 'purchase_order_id', 'converted_invoice_id',
        'invoice_date', 'due_date', 'valid_until', 'expected_date',
        'currency', 'exchange_rate', 'gst_type', 'place_of_supply',
        'vendor_bill_number', 'vendor_bill_date', 'our_reference',
        'buyer_company_name', 'buyer_address', 'buyer_city', 'buyer_state', 'buyer_country',
        'buyer_pincode', 'buyer_gstin', 'buyer_pan', 'buyer_email', 'buyer_mobile', 'buyer_website',
        'vendor_company_name', 'vendor_contact_name', 'vendor_email', 'vendor_mobile',
        'vendor_gstin', 'vendor_pan', 'vendor_address', 'vendor_city', 'vendor_state',
        'vendor_country', 'vendor_pincode',
        'payment_terms', 'delivery_terms', 'dispatch_terms', 'transport_mode', 'purchase_person',
        'subtotal', 'discount_type', 'discount_value', 'discount_amount', 'taxable_amount',
        'cgst_amount', 'sgst_amount', 'igst_amount', 'freight_amount', 'packing_amount',
        'other_charges', 'round_off', 'total_amount', 'amount_paid', 'balance_amount', 'amount_in_words',
        'terms_conditions', 'notes', 'internal_notes',
        'sent_at', 'approved_at', 'received_at', 'cancelled_at', 'created_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'valid_until' => 'date',
        'expected_date' => 'date',
        'vendor_bill_date' => 'date',
        'exchange_rate' => 'decimal:6',
        'subtotal' => 'decimal:2',
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
        'sent_at' => 'datetime',
        'approved_at' => 'datetime',
        'received_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /* ------------------------------------------------------------------
       Relations
       ------------------------------------------------------------------ */

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseInvoiceItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /** The order a bill came from, when it came from one. */
    public function purchaseOrder()
    {
        return $this->belongsTo(self::class, 'purchase_order_id');
    }

    /** The bill an order became. */
    public function convertedInvoice()
    {
        return $this->belongsTo(self::class, 'converted_invoice_id');
    }

    /** The order a bill was raised from. */
    public function sourceOrder()
    {
        return $this->hasOne(self::class, 'converted_invoice_id');
    }

    /**
     * Every ledger line filed against this document.
     *
     * A bill's own posting is a credit; every payment is a debit. They are read
     * together (`paidAmount()`) and apart (`payments()`), and the ordering is
     * the ledger's own so the record page's table reads oldest first.
     */
    public function ledgerEntries()
    {
        return $this->hasMany(VendorPaymentEntry::class, 'purchase_invoice_id')->orderBy('transaction_date')->orderBy('id');
    }

    public function payments()
    {
        return $this->hasMany(VendorPaymentEntry::class, 'purchase_invoice_id')
            ->where('transaction_type', 'debit')
            ->orderBy('transaction_date');
    }

    /* ------------------------------------------------------------------
       What the document is
       ------------------------------------------------------------------ */

    public function isOrder(): bool
    {
        return $this->invoice_type === self::TYPE_ORDER;
    }

    public function isBill(): bool
    {
        return $this->invoice_type === self::TYPE_BILL;
    }

    /** An order that has become a bill. It is history, and it owes nothing. */
    public function isSuperseded(): bool
    {
        return $this->isOrder() && (int) $this->converted_invoice_id > 0;
    }

    /** An approved order that can still become a bill. */
    public function canConvert(): bool
    {
        return $this->isOrder()
            && ! $this->isSuperseded()
            && $this->status === 'approved';
    }

    /**
     * Maker-checker for money out: a debit is allowed only against an
     * approved purchase order, or a bill that has actually been raised
     * (not a draft). Sent and draft orders wait for approval.
     */
    public function canReceiveMoney(): bool
    {
        if ($this->status === 'cancelled') {
            return false;
        }

        if ($this->isBill()) {
            return $this->status !== 'draft';
        }

        return $this->isOrder()
            && $this->status === 'approved'
            && ! $this->isSuperseded();
    }

    /** What the office must do before this document can take an advance or payment. */
    public function moneyGateMessage(): ?string
    {
        if ($this->canReceiveMoney()) {
            return null;
        }

        if ($this->isOrder() && $this->isSuperseded()) {
            return 'This order has already become a bill. Record the payment on the bill.';
        }

        if ($this->isOrder() && $this->status === 'draft') {
            return 'Send this purchase order, then approve it, before recording an advance.';
        }

        if ($this->isOrder() && $this->status === 'sent') {
            return 'Approve this purchase order before recording an advance. It has been sent; the checker has not approved it yet.';
        }

        if ($this->isOrder() && $this->status === 'cancelled') {
            return $this->invoice_number.' is cancelled, so money cannot be filed against it.';
        }

        if ($this->isBill() && $this->status === 'draft') {
            return 'Mark this purchase bill as received before recording a payment.';
        }

        if ($this->status === 'cancelled') {
            return $this->invoice_number.' is cancelled, so a payment cannot be filed against it.';
        }

        return 'An approved purchase order or a raised bill is required before money can go to this vendor.';
    }

    /**
     * The documents that stand for money.
     *
     * Everything that counts payable reads this scope. An order is what was
     * asked for; only the bill is owed, and a converted order owes nothing —
     * without this, one purchase is on the books twice.
     */
    public function scopeNotSuperseded(Builder $query): Builder
    {
        return $query->where(fn ($query) => $query
            ->whereNull('converted_invoice_id')
            ->orWhere('converted_invoice_id', 0));
    }

    public function scopeOrders(Builder $query): Builder
    {
        return $query->where('invoice_type', self::TYPE_ORDER);
    }

    public function scopeBills(Builder $query): Builder
    {
        return $query->where('invoice_type', self::TYPE_BILL);
    }

    public function typeLabel(): string
    {
        return self::DOC_LABELS[$this->invoice_type] ?? Str::headline((string) $this->invoice_type);
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? Str::headline((string) $this->status);
    }

    public function gstTypeLabel(): string
    {
        return self::gstTypeOptions()[$this->gst_type] ?? Str::headline((string) $this->gst_type);
    }

    /** The route prefix this document's screens live under. */
    public function routePrefix(): string
    {
        return 'purchase-invoices';
    }

    /* ------------------------------------------------------------------
       The money
       ------------------------------------------------------------------ */

    /** Payments against this document, in the document's own currency. */
    public function ledgerPaid(): float
    {
        if ($this->relationLoaded('payments')) {
            return round((float) $this->payments->sum('foreign_amount'), 2);
        }

        return round((float) $this->payments()->sum('foreign_amount'), 2);
    }

    public function paidAmount(): float
    {
        return round((float) $this->amount_paid + $this->ledgerPaid(), 2);
    }

    /**
     * What is still owed on this bill.
     *
     * An order owes nothing: it is not a document the vendor can be paid
     * against. A cancelled document owes nothing either.
     */
    public function balanceDue(): float
    {
        if (! $this->isBill() || $this->status === 'cancelled') {
            return 0.0;
        }

        return round(max((float) $this->total_amount - $this->paidAmount(), 0), 2);
    }

    /** What is still open to record as an advance or a payment. */
    public function openAmount(): float
    {
        if ($this->status === 'cancelled' || $this->isSuperseded() || ! $this->canReceiveMoney()) {
            return 0.0;
        }

        return round(max((float) $this->total_amount - $this->paidAmount(), 0), 2);
    }

    /** unpaid | partial | paid — read from the money, never from a stored word. */
    public function paymentState(): string
    {
        if ($this->balanceDue() <= 0.01) {
            return 'paid';
        }

        return $this->paidAmount() > 0.01 ? 'partial' : 'unpaid';
    }

    /** How much of this order has been billed — 0 until its bill exists, then all of it. */
    public function billedAmount(): float
    {
        if (! $this->isOrder()) {
            return round((float) $this->total_amount, 2);
        }

        return round((float) ($this->convertedInvoice?->total_amount ?? 0), 2);
    }

    public function isOverdue(?\DateTimeInterface $today = null): bool
    {
        if (! $this->isBill() || in_array($this->status, ['draft', 'cancelled'], true) || ! $this->due_date) {
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

        return (int) $this->due_date->diffInDays($today ?: Carbon::today());
    }

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

    /* ------------------------------------------------------------------
       The one word the chip wears
       ------------------------------------------------------------------ */

    /**
     * Draft and cancelled are the office's own decision and stand. An order
     * that became a bill is history. Everything else is the money's: late,
     * paid, part paid — and when none of those, the document's own state.
     */
    public function stateKey(?\DateTimeInterface $today = null): string
    {
        if ($this->status === 'cancelled') {
            return 'cancelled';
        }

        if ($this->isSuperseded()) {
            return 'billed';
        }

        if ($this->status === 'draft') {
            return 'draft';
        }

        if ($this->isBill()) {
            if ($this->isOverdue($today)) {
                return 'overdue';
            }

            $payment = $this->paymentState();

            return $payment === 'unpaid' ? 'received' : $payment;
        }

        return in_array($this->status, ['sent', 'approved'], true) ? $this->status : 'approved';
    }

    public function stateLabel(?\DateTimeInterface $today = null): string
    {
        return [
            'draft' => 'Draft',
            'cancelled' => 'Cancelled',
            'billed' => 'Billed',
            'overdue' => 'Overdue',
            'paid' => 'Paid',
            'partial' => 'Partly paid',
            'received' => 'Received',
            'sent' => 'Sent to vendor',
            'approved' => 'Approved',
        ][$this->stateKey($today)] ?? Str::headline($this->stateKey($today));
    }

    /**
     * Load the ledger totals for a whole page in two subqueries rather than one
     * query per row.
     */
    public function scopeWithPaid(Builder $query): Builder
    {
        return $query
            ->withSum('payments as paid_total', 'amount_in_inr');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);

        if ($search === '') {
            return $query;
        }

        $like = '%'.$search.'%';

        return $query->where(function ($query) use ($like) {
            $query->where('invoice_number', 'like', $like)
                ->orWhere('vendor_bill_number', 'like', $like)
                ->orWhere('vendor_company_name', 'like', $like)
                ->orWhere('our_reference', 'like', $like)
                ->orWhereHas('vendor', fn ($vendor) => $vendor->where('vendor_name', 'like', $like));
        });
    }

    /* ------------------------------------------------------------------
       The vocabulary
       ------------------------------------------------------------------ */

    public static function typeOptions(): array
    {
        return self::DOC_LABELS;
    }

    public static function statusOptions(): array
    {
        return [
            'draft' => 'Draft',
            'sent' => 'Sent to Vendor',
            'approved' => 'Approved',
            'received' => 'Received',
            'partial' => 'Partially Paid',
            'paid' => 'Paid',
            'billed' => 'Billed',
            'cancelled' => 'Cancelled',
        ];
    }

    /** The statuses the form may offer for a given document type. */
    public static function statusOptionsFor(string $type): array
    {
        $allowed = $type === self::TYPE_ORDER
            ? ['draft', 'sent', 'approved', 'billed', 'cancelled']
            : ['draft', 'received', 'partial', 'paid', 'cancelled'];

        return array_intersect_key(self::statusOptions(), array_flip($allowed));
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
            'export' => 'Import / No GST',
        ];
    }

    /** Our own company details — the buyer on every purchase document. */
    public static function defaultBuyerDetails(): array
    {
        return [
            'buyer_company_name' => 'MissPack India Pvt Ltd',
            'buyer_address' => 'E-410, 4th Floor, City Centre, Near Idgah Circle, Prem Darwaja Road, Idgah',
            'buyer_city' => 'Ahmedabad',
            'buyer_state' => 'Gujarat',
            'buyer_country' => 'India',
            'buyer_pincode' => '380016',
            'buyer_gstin' => '24AATCM8816E1Z5',
            'buyer_pan' => 'AATCM8816E',
            'buyer_email' => 'misspackindia@gmail.com',
            'buyer_mobile' => '7041110823',
            'buyer_website' => 'www.themisspack.com',
        ];
    }

    public static function defaultTerms(): string
    {
        return implode("\n", [
            '1. This purchase order is subject to Ahmedabad, Gujarat jurisdiction only.',
            '2. Supply the quantity, specification and packing stated here; the bill is matched against this order.',
            '3. Goods are to be delivered by the expected date. Any delay must be informed in writing in advance.',
            '4. Material must conform to the approved sample and specification; rejected material is returned at the vendor\'s cost.',
            '5. Invoice the billed quantities at the order rate. Price changes need our written approval first.',
            '6. Mention this purchase order number and our reference on every bill, packing list and carton.',
            '7. Payment terms are as agreed in this document and count from the date of a correct bill.',
            '8. Taxes, freight, packing and any other charges are payable as stated in this document only.',
            '9. Statutory documents — GST invoice, e-way bill and test certificates — must accompany the consignment.',
            '10. This is a computer-generated document and does not require a physical signature unless specifically requested.',
        ]);
    }
}
