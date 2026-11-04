<?php

namespace App\Models;

use App\Services\StockQuantity;
use Illuminate\Database\Eloquent\Model;

class StockItem extends Model
{
    protected $fillable = ['product_id', 'variant', 'batch_code', 'expires_on', 'unit', 'identity_hash',
        'quantity_milli', 'ledger_version', 'created_by', 'creator_name'];

    protected $casts = ['expires_on' => 'date', 'quantity_milli' => 'integer', 'ledger_version' => 'integer'];

    public function getQuantityOnHandAttribute(): string { return StockQuantity::format($this->quantity_milli); }

    public function product() { return $this->belongsTo(Product::class); }
    public function movements() { return $this->hasMany(StockMovement::class)->orderByDesc('id'); }
    public function countLines() { return $this->hasMany(StockCountLine::class); }

    public function number(): string { return 'STK-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT); }

    public function expiryState(): ?string
    {
        if (! $this->expires_on) return null;
        if ($this->expires_on->lt(today())) return 'expired';
        if ($this->expires_on->lte(today()->addDays(30))) return 'expiring';
        return null;
    }

    public function snapshot(): array
    {
        $this->loadMissing('product');
        return ['item_number' => $this->number(), 'product_number' => $this->product->product_number,
            'product_name' => $this->product->name, 'product_sku' => $this->product->sku, 'variant' => $this->variant, 'batch_code' => $this->batch_code,
            'expires_on' => $this->expires_on?->format('Y-m-d'), 'unit' => $this->unit];
    }
}
