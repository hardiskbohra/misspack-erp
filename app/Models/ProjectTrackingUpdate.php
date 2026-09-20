<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProjectTrackingUpdate extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id', 'project_product_id', 'title', 'status', 'progress_percent', 'location',
        'occurred_at', 'notes', 'is_public', 'created_by',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'progress_percent' => 'integer',
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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments()
    {
        return $this->hasMany(ProjectAttachment::class, 'project_tracking_update_id')->latest('id');
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? Str::headline((string) $this->status);
    }

    public static function statusOptions(): array
    {
        return [
            'info' => 'Information',
            'started' => 'Started',
            'in_progress' => 'In Progress',
            'waiting' => 'Waiting',
            'completed' => 'Completed',
            'issue' => 'Issue / Blocker',
            'resolved' => 'Resolved',
        ];
    }
}
