<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockItemEvent extends Model
{
    protected $fillable = ['stock_item_id', 'action', 'record_revision', 'actor_id', 'actor_name', 'reason',
        'before_snapshot', 'after_snapshot', 'stock_count_id'];

    protected $casts = ['record_revision' => 'integer', 'before_snapshot' => 'array', 'after_snapshot' => 'array'];

    public function item() { return $this->belongsTo(StockItem::class, 'stock_item_id'); }
    public function actor() { return $this->belongsTo(User::class, 'actor_id'); }
    public function stockCount() { return $this->belongsTo(StockCount::class, 'stock_count_id'); }
}
