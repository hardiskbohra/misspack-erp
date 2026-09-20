<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Vendor extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_ON_HOLD = 'on_hold';
    public const STATUS_BLACKLISTED = 'blacklisted';

    protected $fillable = [
        'vendor_number', 'vendor_name', 'brand_name', 'vendor_type', 'category', 'status', 'image_path',
        'contact_person_name', 'contact_person_email', 'contact_person_mobile', 'whatsapp_number', 'alternate_contact',
        'website', 'alibaba_link', 'country', 'state', 'city', 'pincode', 'address',
        'gstin', 'pan', 'tax_id', 'import_export_code',
        'bank_name', 'account_holder_name', 'account_number', 'ifsc_code', 'swift_code', 'bank_branch',
        'preferred_currency', 'payment_terms', 'lead_time_days', 'minimum_order_value', 'rating', 'notes', 'created_by',
    ];

    protected $casts = [
        'minimum_order_value' => 'decimal:2',
        'lead_time_days' => 'integer',
        'rating' => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comments()
    {
        return $this->hasMany(VendorComment::class)->latest('is_pinned')->latest('id');
    }

    public function attachments()
    {
        return $this->hasMany(VendorAttachment::class)->latest('id');
    }

    public function paymentEntries()
    {
        return $this->hasMany(VendorPaymentEntry::class)->latest('transaction_date')->latest('id');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, function (Builder $q) use ($search) {
            $q->where(function (Builder $nested) use ($search) {
                $nested->where('vendor_number', 'like', "%{$search}%")
                    ->orWhere('vendor_name', 'like', "%{$search}%")
                    ->orWhere('brand_name', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('contact_person_name', 'like', "%{$search}%")
                    ->orWhere('contact_person_email', 'like', "%{$search}%")
                    ->orWhere('contact_person_mobile', 'like', "%{$search}%")
                    ->orWhere('country', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('gstin', 'like', "%{$search}%")
                    ->orWhere('pan', 'like', "%{$search}%")
                    ->orWhere('tax_id', 'like', "%{$search}%");
            });
        });
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? Str::headline($this->status);
    }

    public function typeLabel(): string
    {
        return self::typeOptions()[$this->vendor_type] ?? Str::headline($this->vendor_type);
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_INACTIVE => 'Inactive',
            self::STATUS_ON_HOLD => 'On Hold',
            self::STATUS_BLACKLISTED => 'Blacklisted',
        ];
    }

    public static function typeOptions(): array
    {
        return [
            'manufacturer' => 'Manufacturer',
            'trader' => 'Trader',
            'distributor' => 'Distributor',
            'service_provider' => 'Service Provider',
        ];
    }

    public static function currencyOptions(): array
    {
        return ['INR' => 'INR', 'USD' => 'USD', 'RMB' => 'RMB'];
    }
}
