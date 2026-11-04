<?php

namespace App\Models;

use App\Services\StockQuantity;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = ['stock_item_id', 'type', 'delta_milli', 'balance_after_milli', 'ledger_version', 'reason',
        'reference', 'request_key', 'stock_count_line_id', 'created_by', 'actor_name', 'item_snapshot'];

    protected $casts = ['delta_milli' => 'integer', 'balance_after_milli' => 'integer', 'ledger_version' => 'integer', 'item_snapshot' => 'array'];

    public function getQuantityDeltaAttribute(): string { return StockQuantity::format($this->delta_milli); }
    public function getBalanceAfterAttribute(): string { return StockQuantity::format($this->balance_after_milli); }

    public function item() { return $this->belongsTo(StockItem::class, 'stock_item_id'); }
    public function countLine() { return $this->belongsTo(StockCountLine::class, 'stock_count_line_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    public function label(): string
    {
        return match ($this->type) {
            'opening' => 'Opening balance', 'receipt' => 'Receipt', 'issue' => 'Issue',
            'count_adjustment' => 'Count adjustment', default => 'Stock movement',
        };
    }
}
