<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockCountEvent extends Model
{
    protected $fillable = ['stock_count_id', 'action', 'count_revision', 'actor_id', 'actor_name', 'reason', 'snapshot'];
    protected $casts = ['count_revision' => 'integer', 'snapshot' => 'array'];

    public function count() { return $this->belongsTo(StockCount::class, 'stock_count_id'); }
    public function actor() { return $this->belongsTo(User::class, 'actor_id'); }
}
