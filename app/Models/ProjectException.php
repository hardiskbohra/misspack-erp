<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProjectException extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_ACKNOWLEDGED = 'acknowledged';
    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'project_id', 'project_workflow_id', 'project_workflow_step_id', 'project_product_id',
        'source_type', 'source_key', 'source_fingerprint', 'category', 'severity', 'status',
        'title', 'summary', 'source_snapshot', 'owner_id', 'due_at', 'next_review_at',
        'escalation_level', 'detected_at', 'acknowledged_at', 'acknowledged_by',
        'response_plan', 'escalated_at', 'escalated_by', 'resolved_at', 'resolved_by',
        'resolution_note',
    ];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new \LogicException('Project exceptions retain their audit history and cannot be deleted.'));
    }

    protected $casts = [
        'source_snapshot' => 'array',
        'due_at' => 'datetime',
        'next_review_at' => 'datetime',
        'detected_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'escalated_at' => 'datetime',
        'resolved_at' => 'datetime',
        'escalation_level' => 'integer',
    ];

    public function project() { return $this->belongsTo(Project::class)->withTrashed(); }
    public function workflow() { return $this->belongsTo(ProjectWorkflow::class, 'project_workflow_id'); }
    public function step() { return $this->belongsTo(ProjectWorkflowStep::class, 'project_workflow_step_id'); }
    public function product() { return $this->belongsTo(ProjectProduct::class, 'project_product_id'); }
    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    public function acknowledger() { return $this->belongsTo(User::class, 'acknowledged_by'); }
    public function escalator() { return $this->belongsTo(User::class, 'escalated_by'); }
    public function resolver() { return $this->belongsTo(User::class, 'resolved_by'); }
    public function events() { return $this->hasMany(ProjectExceptionEvent::class)->latest('occurred_at')->latest('id'); }

    public function categoryLabel(): string
    {
        return [
            'unassigned' => 'Coordinator unassigned',
            'overdue' => 'Workflow overdue',
            'blocked' => 'Workflow blocked',
            'manager_override' => 'Manager override follow-up',
            'approval_override' => 'Approval override follow-up',
            'outstanding_financials' => 'Outstanding financial closure',
        ][$this->category] ?? Str::headline($this->category);
    }

    public function statusLabel(): string
    {
        return [self::STATUS_OPEN => 'Unacknowledged', self::STATUS_ACKNOWLEDGED => 'In follow-up', self::STATUS_RESOLVED => 'Resolved'][$this->status] ?? Str::headline($this->status);
    }

    public function isFollowUpOverdue(): bool
    {
        $deadline = $this->status === self::STATUS_ACKNOWLEDGED ? $this->next_review_at : $this->due_at;

        return $this->status !== self::STATUS_RESOLVED && $deadline && $deadline->isPast();
    }
}
