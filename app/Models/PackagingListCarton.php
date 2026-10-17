<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackagingListCarton extends Model
{
    use HasFactory;

    protected $fillable = [
        'packaging_list_id', 'carton_label', 'packing_mode', 'carton_number', 'carton_type', 'barcode',
        'public_token', 'shipping_mark', 'material', 'pallet_number', 'seal_number', 'carton_count',
        'length_cm', 'width_cm', 'height_cm', 'net_weight_per_carton', 'gross_weight_per_carton',
        'tare_weight', 'is_fragile', 'keep_dry', 'this_side_up', 'is_stackable', 'is_hazardous',
        'handling_instructions', 'sort_order',
    ];

    protected $casts = [
        'carton_count' => 'integer', 'length_cm' => 'decimal:2', 'width_cm' => 'decimal:2',
        'height_cm' => 'decimal:2', 'net_weight_per_carton' => 'decimal:3', 'gross_weight_per_carton' => 'decimal:3',
        'tare_weight' => 'decimal:3', 'is_fragile' => 'boolean', 'keep_dry' => 'boolean',
        'this_side_up' => 'boolean', 'is_stackable' => 'boolean', 'is_hazardous' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $carton) {
            $carton->public_token ??= \Illuminate\Support\Str::random(48);
            $carton->barcode ??= 'CTN-'.strtoupper(\Illuminate\Support\Str::random(10));
        });
    }

    public function packagingList() { return $this->belongsTo(PackagingList::class); }
    public function items() { return $this->hasMany(PackagingListItem::class)->orderBy('sort_order')->orderBy('id'); }
    public function labelPrints() { return $this->hasMany(PackagingListLabelPrint::class); }
    public function cbmPerCarton(): float { return round((float) $this->length_cm * (float) $this->width_cm * (float) $this->height_cm / 1000000, 4); }
    public function totalCbm(): float { return round($this->cbmPerCarton() * (int) $this->carton_count, 4); }
    public function totalNetWeight(): float { return round((float) $this->net_weight_per_carton * (int) $this->carton_count, 3); }
    public function totalGrossWeight(): float { return round((float) $this->gross_weight_per_carton * (int) $this->carton_count, 3); }
    public function dimensionsLabel(): string { return $this->length_cm.' × '.$this->width_cm.' × '.$this->height_cm.' cm'; }
}
