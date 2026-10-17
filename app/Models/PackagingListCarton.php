<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackagingListCarton extends Model
{
    use HasFactory;

    protected $fillable = [
        'packaging_list_id', 'carton_label', 'carton_count', 'length_cm', 'width_cm', 'height_cm',
        'net_weight_per_carton', 'gross_weight_per_carton', 'sort_order',
    ];

    protected $casts = [
        'carton_count' => 'integer', 'length_cm' => 'decimal:2', 'width_cm' => 'decimal:2',
        'height_cm' => 'decimal:2', 'net_weight_per_carton' => 'decimal:3', 'gross_weight_per_carton' => 'decimal:3',
    ];

    public function packagingList() { return $this->belongsTo(PackagingList::class); }
    public function items() { return $this->hasMany(PackagingListItem::class)->orderBy('sort_order')->orderBy('id'); }
    public function cbmPerCarton(): float { return round((float) $this->length_cm * (float) $this->width_cm * (float) $this->height_cm / 1000000, 4); }
    public function totalCbm(): float { return round($this->cbmPerCarton() * (int) $this->carton_count, 4); }
    public function totalNetWeight(): float { return round((float) $this->net_weight_per_carton * (int) $this->carton_count, 3); }
    public function totalGrossWeight(): float { return round((float) $this->gross_weight_per_carton * (int) $this->carton_count, 3); }
    public function dimensionsLabel(): string { return $this->length_cm.' × '.$this->width_cm.' × '.$this->height_cm.' cm'; }
}
