<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockCount extends Model
{
    protected $fillable = ['title', 'notes', 'status', 'revision', 'created_by', 'counter_name', 'submitted_at', 'posted_at', 'posted_by', 'approver_name'];
    protected $casts = ['revision' => 'integer', 'submitted_at' => 'datetime', 'posted_at' => 'datetime'];

    public function lines() { return $this->hasMany(StockCountLine::class)->orderBy('stock_item_id'); }
    public function events() { return $this->hasMany(StockCountEvent::class)->orderByDesc('id'); }
    public function counter() { return $this->belongsTo(User::class, 'created_by'); }
    public function approver() { return $this->belongsTo(User::class, 'posted_by'); }
    public function number(): string { return 'COUNT-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT); }
    public function label(): string { return ucfirst($this->status); }
}
