<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_number', 'name', 'sku', 'category', 'status', 'ml_capacities',
        'finish_details', 'printing_details', 'ready_stock_available', 'ready_stock_moq',
        'customisation_moq', 'available_stock_colors', 'customisation_details',
        'size_measurements', 'weight_measurements', 'material_details', 'packaging_details',
        'description', 'show_price_ladder_public', 'public_token', 'created_by',
    ];

    protected $casts = [
        'ml_capacities' => 'array',
        'ready_stock_available' => 'boolean',
        'show_price_ladder_public' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if (! $product->public_token) {
                $product->public_token = Str::random(48);
            }
        });
    }

    public function media()
    {
        return $this->hasMany(ProductMedia::class)->orderBy('sort_order')->orderBy('id');
    }

    public function priceLadders()
    {
        return $this->hasMany(ProductPriceLadder::class)->orderBy('quantity')->orderBy('id');
    }

    public function vendorQuotes()
    {
        return $this->hasMany(\App\Models\VendorQuote::class, 'product_id', 'id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, function (Builder $q) use ($search) {
            $q->where(function (Builder $nested) use ($search) {
                $nested->where('product_number', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('material_details', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        });
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? Str::headline($this->status);
    }

    public function primaryMedia()
    {
        return $this->media->firstWhere('is_primary', true) ?: $this->media->first();
    }

    public static function statusOptions(): array
    {
        return [
            'active' => 'Active',
            'inactive' => 'Inactive',
            'discontinued' => 'Discontinued',
        ];
    }

    public static function currencyOptions(): array
    {
        return ['INR' => 'INR', 'USD' => 'USD', 'RMB' => 'RMB'];
    }
    
    public function projectProducts()
    {
        return $this->hasMany(\App\Models\ProjectProduct::class, 'product_id');
    }
    
    public function projects()
    {
        return $this->belongsToMany(\App\Models\Project::class, 'project_products', 'product_id', 'project_id')
            ->withPivot(['product_name', 'quantity', 'unit', 'unit_price', 'total_amount', 'status', 'stage'])
            ->withTimestamps();
    }
}
