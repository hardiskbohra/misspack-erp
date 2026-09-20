<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Lead extends Model
{
    use HasFactory;

    public const STATUS_NEW = 'new';
    public const STATUS_REQUIREMENT_RECEIVED = 'requirement_received';
    public const STATUS_SOURCING = 'sourcing';
    public const STATUS_QUOTED = 'quoted';
    public const STATUS_NEGOTIATION = 'negotiation';
    public const STATUS_WON = 'won';
    public const STATUS_LOST = 'lost';
    public const STATUS_ON_HOLD = 'on_hold';

    protected $fillable = [
        'lead_number', 'public_token', 'title', 'client_id', 'client_company_name', 'client_contact_name', 'client_email', 'client_mobile',
        'lead_source', 'priority', 'status', 'assigned_to', 'product_name', 'product_image_path', 'product_description',
        'capacity_value', 'capacity_unit', 'required_quantity', 'quantity_notes', 'quote_quantities', 'finish_required',
        'printing_required', 'printing_details', 'ready_stock_required', 'ready_stock_color_requirement', 'ready_stock_moq_notes',
        'custom_color_required', 'custom_color_specification', 'target_price', 'target_currency', 'expected_order_date',
        'required_delivery_date', 'sales_notes', 'purchase_notes', 'created_by',
    ];

    protected $casts = [
        'capacity_value' => 'decimal:3',
        'required_quantity' => 'integer',
        'quote_quantities' => 'array',
        'ready_stock_required' => 'boolean',
        'custom_color_required' => 'boolean',
        'target_price' => 'decimal:2',
        'expected_order_date' => 'date',
        'required_delivery_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (Lead $lead) {
            if (! $lead->public_token) {
                $lead->public_token = Str::random(48);
            }
        });
    }

    public function attachments()
    {
        return $this->hasMany(LeadAttachment::class);
    }

    public function vendorQuotes()
    {
        return $this->hasMany(VendorQuote::class);
    }

    public function customerQuotes()
    {
        return $this->hasMany(\App\Models\LeadQuote::class, 'lead_id');
    }

    public function comments()
    {
        return $this->hasMany(LeadComment::class)->latest('is_pinned')->latest('created_at');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function client()
    {
        return $this->belongsTo(\App\Models\Client::class, 'client_id');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, function (Builder $q) use ($search) {
            $q->where(function (Builder $nested) use ($search) {
                $nested->where('lead_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('client_company_name', 'like', "%{$search}%")
                    ->orWhere('client_contact_name', 'like', "%{$search}%")
                    ->orWhere('client_email', 'like', "%{$search}%")
                    ->orWhere('client_mobile', 'like', "%{$search}%")
                    ->orWhere('product_name', 'like', "%{$search}%")
                    ->orWhere('product_description', 'like', "%{$search}%")
                    ->orWhere('sales_notes', 'like', "%{$search}%");
            });
        });
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? Str::headline($this->status);
    }

    public function priorityLabel(): string
    {
        return self::priorityOptions()[$this->priority] ?? Str::headline($this->priority);
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_NEW => 'New',
            self::STATUS_REQUIREMENT_RECEIVED => 'Requirement Received',
            self::STATUS_SOURCING => 'Sourcing',
            self::STATUS_QUOTED => 'Quoted',
            self::STATUS_NEGOTIATION => 'Negotiation',
            self::STATUS_WON => 'Won',
            self::STATUS_LOST => 'Lost',
            self::STATUS_ON_HOLD => 'On Hold',
        ];
    }

    public static function priorityOptions(): array
    {
        return ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent'];
    }

    public static function sourceOptions(): array
    {
        return ['whatsapp' => 'WhatsApp', 'email' => 'Email', 'website' => 'Website', 'call' => 'Call', 'referral' => 'Referral', 'exhibition' => 'Exhibition', 'other' => 'Other'];
    }

    public static function finishOptions(): array
    {
        return ['matte' => 'Matte', 'glossy' => 'Glossy', 'both' => 'Matte + Glossy', 'any' => 'Any', 'custom' => 'Custom'];
    }

    public static function printingOptions(): array
    {
        return ['none' => 'No Printing', 'one_color' => 'One Color Printing', 'multi_color' => 'Multi Color Printing', 'label' => 'Label', 'embossing' => 'Embossing', 'custom' => 'Custom'];
    }

    public static function currencyOptions(): array
    {
        return ['INR' => 'INR', 'USD' => 'USD', 'RMB' => 'RMB'];
    }
}
