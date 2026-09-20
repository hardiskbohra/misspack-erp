<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id', 'project_product_id', 'user_id', 'actor_type', 'actor_name', 'event_type',
        'title', 'description', 'old_values', 'new_values', 'is_public',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'is_public' => 'boolean',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function product()
    {
        return $this->belongsTo(ProjectProduct::class, 'project_product_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
