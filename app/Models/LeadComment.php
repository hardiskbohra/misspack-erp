<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LeadComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id', 'comment', 'comment_type', 'next_follow_up_at', 'is_pinned', 'created_by',
    ];

    protected $casts = [
        'next_follow_up_at' => 'datetime',
        'is_pinned' => 'boolean',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeLabel(): string
    {
        return self::typeOptions()[$this->comment_type] ?? Str::headline($this->comment_type);
    }

    public static function typeOptions(): array
    {
        return [
            'internal' => 'Internal Comment',
            'customer_update' => 'Customer Update',
            'follow_up' => 'Follow Up',
            'purchase_note' => 'Purchase Note',
        ];
    }
}
