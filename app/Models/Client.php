<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Client extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_REVISION = 'revision';

    protected $fillable = [
        'client_number', 'company_name', 'brand_name', 'client_type', 'industry', 'website', 'status',
        'ceo_name', 'ceo_email', 'ceo_contact',
        'account_person_name', 'account_person_email', 'account_person_contact',
        'marketing_person_name', 'marketing_person_email', 'marketing_person_contact',
        'dispatch_person_name', 'dispatch_person_email', 'dispatch_person_contact',
        'billing_address', 'billing_city', 'billing_state', 'billing_country', 'billing_pincode',
        'shipping_address', 'shipping_city', 'shipping_state', 'shipping_country', 'shipping_pincode', 'shipping_same_as_billing',
        'gstin', 'pan', 'tan', 'cin', 'msme_number',
        'bank_name', 'account_holder_name', 'account_number', 'ifsc_code', 'bank_branch', 'swift_code',
        'credit_limit', 'credit_days', 'payment_terms', 'preferred_currency', 'notes',
        'public_token', 'kyc_sent_at', 'kyc_submitted_at', 'kyc_reviewed_at', 'kyc_reviewed_by',
        'revision_note', 'rejection_reason', 'created_by',
    ];

    protected $casts = [
        'shipping_same_as_billing' => 'boolean',
        'credit_limit' => 'decimal:2',
        'kyc_sent_at' => 'datetime',
        'kyc_submitted_at' => 'datetime',
        'kyc_reviewed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Client $client) {
            if (! $client->public_token) {
                $client->public_token = Str::random(48);
            }
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'kyc_reviewed_by');
    }
    
    public function projects()
    {
        return $this->hasMany(\App\Models\Project::class, 'client_id');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, function (Builder $q) use ($search) {
            $q->where(function (Builder $nested) use ($search) {
                $nested->where('client_number', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('brand_name', 'like', "%{$search}%")
                    ->orWhere('gstin', 'like', "%{$search}%")
                    ->orWhere('pan', 'like', "%{$search}%")
                    ->orWhere('ceo_name', 'like', "%{$search}%")
                    ->orWhere('ceo_email', 'like', "%{$search}%")
                    ->orWhere('account_person_name', 'like', "%{$search}%")
                    ->orWhere('marketing_person_name', 'like', "%{$search}%")
                    ->orWhere('dispatch_person_name', 'like', "%{$search}%");
            });
        });
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? Str::headline($this->status);
    }

    public function typeLabel(): string
    {
        return self::typeOptions()[$this->client_type] ?? Str::headline($this->client_type);
    }

    public function isPublicKycEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REVISION], true);
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_UNDER_REVIEW => 'Under Review',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_REVISION => 'Revision Required',
        ];
    }

    public static function typeOptions(): array
    {
        return [
            'customer' => 'Customer',
            'vendor' => 'Vendor',
            'both' => 'Customer & Vendor',
        ];
    }

    public static function currencyOptions(): array
    {
        return ['INR' => 'INR', 'USD' => 'USD', 'RMB' => 'RMB'];
    }
    
    public function portalUsers()
    {
        return $this->hasMany(\App\Models\ClientPortalUser::class, 'client_id');
    }
    
    public function portalUser()
    {
        return $this->hasOne(\App\Models\ClientPortalUser::class, 'client_id');
    }
    
    public function portalInvoices()
    {
        return $this->hasMany(\App\Models\ClientPortalInvoice::class, 'client_id');
    }
}
