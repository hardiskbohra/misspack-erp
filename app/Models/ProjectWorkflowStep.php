<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProjectWorkflowStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_workflow_id', 'project_product_id', 'step_key', 'stage_key', 'title',
        'scope', 'sort_order', 'weight', 'status', 'is_required', 'is_client_visible',
        'owner_id', 'planned_start_date', 'planned_end_date', 'actual_started_at',
        'completed_at', 'completed_by', 'blocked_reason', 'notes', 'metadata',
    ];

    protected $casts = [
        'weight' => 'decimal:3',
        'is_required' => 'boolean',
        'is_client_visible' => 'boolean',
        'planned_start_date' => 'date',
        'planned_end_date' => 'date',
        'actual_started_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function workflow() { return $this->belongsTo(ProjectWorkflow::class, 'project_workflow_id'); }
    public function product() { return $this->belongsTo(ProjectProduct::class, 'project_product_id'); }
    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    public function completedBy() { return $this->belongsTo(User::class, 'completed_by'); }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? Str::headline((string) $this->status);
    }

    public static function statusOptions(): array
    {
        return [
            'not_started' => 'Not started', 'ready' => 'Ready', 'in_progress' => 'In progress',
            'waiting_client' => 'Waiting for client', 'waiting_vendor' => 'Waiting for vendor',
            'blocked' => 'Blocked', 'completed' => 'Completed', 'skipped' => 'Skipped',
            'cancelled' => 'Cancelled',
        ];
    }
}
