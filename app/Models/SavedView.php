<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SavedView extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'module', 'name', 'query', 'is_shared', 'sort_order',
    ];

    protected $casts = [
        'is_shared' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
