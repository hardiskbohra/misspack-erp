<?php

namespace App\Models;

use App\Services\StockQuantity;
use Illuminate\Database\Eloquent\Model;

class StockItem extends Model
{
    protected $fillable = ['product_id', 'variant', 'batch_code', 'expires_on', 'unit', 'identity_hash',
        'quantity_milli', 'ledger_version', 'record_revision', 'identity_revision', 'archived_at', 'archived_by', 'created_by', 'creator_name'];

    protected $casts = ['expires_on' => 'date', 'quantity_milli' => 'integer', 'ledger_version' => 'integer',
        'record_revision' => 'integer', 'identity_revision' => 'integer', 'archived_at' => 'datetime'];

    public function getQuantityOnHandAttribute(): string { return StockQuantity::format($this->quantity_milli); }

    public function product() { return $this->belongsTo(Product::class); }
    public function movements() { return $this->hasMany(StockMovement::class)->orderByDesc('id'); }
    public function countLines() { return $this->hasMany(StockCountLine::class); }
    public function events() { return $this->hasMany(StockItemEvent::class)->orderByDesc('id'); }
    public function archiver() { return $this->belongsTo(User::class, 'archived_by'); }

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
        return ['item_number' => $this->number(), 'product_id' => $this->product_id, 'product_number' => $this->product->product_number,
            'product_name' => $this->product->name, 'product_sku' => $this->product->sku, 'variant' => $this->variant, 'batch_code' => $this->batch_code,
            'expires_on' => $this->expires_on?->format('Y-m-d'), 'unit' => $this->unit, 'identity_revision' => $this->identity_revision];
    }
}
