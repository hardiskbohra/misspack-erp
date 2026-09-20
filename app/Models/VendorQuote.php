<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VendorQuote extends Model
{
    use HasFactory;

    protected $fillable = [
        'quote_number',
        'lead_id',
        'product_id',
        'vendor_id',
        'vendor_name',
        'vendor_contact_name',
        'vendor_email',
        'vendor_mobile',
        'product_name',
        'product_image_path',
        'status',
        'currency',
        'incoterm',
        'quantity',
        'unit',
        'vendor_unit_price',
        'moq',
        'lead_time_days',
        'sample_available',
        'ready_stock_available',
        'available_colors',
        'finish_options',
        'printing_options',
        'size_details',
        'weight_details',
        'packaging_details',
        'photo_video_notes',
        'landing_cost_inr',
        'selling_price_inr',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'vendor_unit_price' => 'decimal:4',
        'moq' => 'integer',
        'lead_time_days' => 'integer',
        'sample_available' => 'boolean',
        'ready_stock_available' => 'boolean',
        'landing_cost_inr' => 'decimal:2',
        'selling_price_inr' => 'decimal:2',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function product()
    {
        return $this->belongsTo(\App\Models\Product::class, 'product_id');
    }

    public function prices()
    {
        return $this->hasMany(VendorQuotePrice::class);
    }

    // Backward compatibility for older views/code that used items().
    public function items()
    {
        return $this->prices();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function vendor()
    {
        return $this->belongsTo(\App\Models\Vendor::class, 'vendor_id');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, function (Builder $q) use ($search) {
            $q->where(function (Builder $nested) use ($search) {
                $nested->where('quote_number', 'like', "%{$search}%")
                    ->orWhere('vendor_name', 'like', "%{$search}%")
                    ->orWhere('vendor_email', 'like', "%{$search}%")
                    ->orWhere('vendor_mobile', 'like', "%{$search}%")
                    ->orWhere('product_name', 'like', "%{$search}%")
                    ->orWhere('available_colors', 'like', "%{$search}%")
                    ->orWhere('finish_options', 'like', "%{$search}%")
                    ->orWhere('printing_options', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('lead', function ($lead) use ($search) {
                        $lead->where('title', 'like', "%{$search}%")
                            ->orWhere('lead_number', 'like', "%{$search}%");
                    });
            });
        });
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? Str::headline($this->status);
    }

    public static function statusOptions(): array
    {
        return [
            'requested' => 'Requested',
            'received' => 'Received',
            'shortlisted' => 'Shortlisted',
            'rejected' => 'Rejected',
            'approved' => 'Approved',
            'converted' => 'Converted',
        ];
    }

    public static function incotermOptions(): array
    {
        return ['EXW' => 'EXW', 'FOB' => 'FOB', 'CIF' => 'CIF', 'DDP' => 'DDP', 'DAP' => 'DAP'];
    }

    public static function currencyOptions(): array
    {
        return ['RMB' => 'RMB', 'USD' => 'USD', 'INR' => 'INR'];
    }
}
