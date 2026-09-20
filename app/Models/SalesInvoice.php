<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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

    public function publicAttachments()
    {
        return $this->hasMany(SalesInvoiceAttachment::class)->where('is_public', true)->latest('id');
    }

    public function client()
    {
        return $this->belongsTo(\App\Models\Client::class, 'client_id');
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
