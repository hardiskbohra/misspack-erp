<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectFinancialClosure extends Model
{
    public const TYPE_COMPLETE = 'financially_complete';
    public const TYPE_OUTSTANDING = 'closed_with_outstanding_financials';
    public const TYPE_REOPENED = 'reopened';

    protected $fillable = [
        'project_id',
        'project_workflow_id',
        'closure_type',
        'content_hash',
        'financial_snapshot',
        'reason',
        'expected_resolution_date',
        'approved_by',
        'closed_at',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Financial closure decisions are immutable.'));
        static::deleting(fn () => throw new \LogicException('Financial closure decisions are immutable.'));
    }

    protected $casts = [
        'financial_snapshot' => 'array',
        'expected_resolution_date' => 'date',
        'closed_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ProjectWorkflow::class, 'project_workflow_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getClosureTypeLabelAttribute(): string
    {
        return match ($this->closure_type) {
            self::TYPE_COMPLETE => 'Financially complete',
            self::TYPE_OUTSTANDING => 'Closed with outstanding financials',
            self::TYPE_REOPENED => 'Reopened',
            default => str($this->closure_type)->headline()->toString(),
        };
    }
}
