<?php

namespace App\Models;

use App\Services\StockQuantity;
use Illuminate\Database\Eloquent\Model;

class StockCountLine extends Model
{
    protected $fillable = ['stock_count_id', 'stock_item_id', 'item_snapshot', 'book_milli', 'ledger_version', 'counted_milli', 'reason'];
    protected $casts = ['item_snapshot' => 'array', 'book_milli' => 'integer', 'counted_milli' => 'integer', 'ledger_version' => 'integer'];

    public function getBookQtyAttribute(): string { return StockQuantity::format($this->book_milli); }
    public function getCountedQtyAttribute(): ?string { return $this->counted_milli === null ? null : StockQuantity::format($this->counted_milli); }

    public function count() { return $this->belongsTo(StockCount::class, 'stock_count_id'); }
    public function item() { return $this->belongsTo(StockItem::class, 'stock_item_id'); }
    public function movement() { return $this->hasOne(StockMovement::class, 'stock_count_line_id'); }

    public function variance(): ?string
    {
        if ($this->counted_milli === null) return null;
        return StockQuantity::format($this->counted_milli - $this->book_milli);
    }

    public function isStale(): bool { return $this->item && $this->item->ledger_version !== $this->ledger_version; }
}
