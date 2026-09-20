<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id', 'project_product_id', 'parent_id', 'author_type', 'user_id', 'client_name',
        'client_email', 'body', 'is_public', 'is_pinned',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_pinned' => 'boolean',
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

    public function parent()
    {
        return $this->belongsTo(ProjectComment::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(ProjectComment::class, 'parent_id')->oldest('id');
    }

    public function authorName(): string
    {
        if ($this->author_type === 'client') {
            return $this->client_name ?: 'Client';
        }

        if ($this->relationLoaded('user') && $this->user) {
            return $this->user->name;
        }

        return 'Internal Team';
    }
}
