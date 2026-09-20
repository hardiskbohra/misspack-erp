<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProjectMilestone extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id', 'project_product_id', 'milestone_key', 'title', 'description', 'status',
        'progress_percent', 'planned_start_date', 'planned_end_date', 'actual_start_date',
        'actual_end_date', 'completed_at', 'owner_id', 'is_public', 'is_required', 'sort_order',
        'notes', 'internal_notes', 'client_note', 'blocked_reason', 'created_by',
    ];

    protected $casts = [
        'planned_start_date' => 'date',
        'planned_end_date' => 'date',
        'actual_start_date' => 'date',
        'actual_end_date' => 'date',
        'completed_at' => 'datetime',
        'progress_percent' => 'integer',
        'is_public' => 'boolean',
        'is_required' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function product()
    {
        return $this->belongsTo(ProjectProduct::class, 'project_product_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? Str::headline((string) $this->status);
    }

    public function milestoneLabel(): string
    {
        return self::milestoneOptions()[$this->milestone_key] ?? $this->title;
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isOverdue(): bool
    {
        return ! $this->isCompleted()
            && $this->planned_end_date
            && $this->planned_end_date->lt(today());
    }

    public function statusClass(): string
    {
        return str_replace('_', '-', (string) $this->status);
    }

    public static function statusOptions(): array
    {
        return [
            'not_started' => 'Not Started',
            'in_progress' => 'In Progress',
            'waiting' => 'Waiting',
            'completed' => 'Completed',
            'blocked' => 'Blocked',
            'skipped' => 'Skipped',
        ];
    }

    public static function milestoneOptions(): array
    {
        return [
            'product_finalisation' => 'Product Finalisation',
            'artwork_design' => 'Artwork / Design',
            'sample_development' => 'Sample Development',
            'sample_revision' => 'Sample Revision',
            'sample_approval' => 'Sample Approval',
            'production_planning' => 'Production Planning',
            'production' => 'Production',
            'quality_inspection' => 'Quality Inspection',
            'packing' => 'Packing',
            'ready_for_dispatch' => 'Ready for Dispatch',
            'dispatched' => 'Dispatched',
            'internal_qc' => 'Internal QC',
            'inspection_by_client' => 'Inspection By Client',
            'shipment_planning' => 'Shipment Planning',
            'delivered' => 'Delivered',
            'custom' => 'Custom Milestone',
        ];
    }

    public static function defaultMilestones(): array
    {
        return [
            ['key' => 'product_finalisation', 'title' => 'Product Finalisation', 'public' => true, 'sort' => 10],
            ['key' => 'artwork_design', 'title' => 'Artwork / Design', 'public' => true, 'sort' => 20],
            ['key' => 'sample_development', 'title' => 'Sample Development', 'public' => true, 'sort' => 30],
            ['key' => 'sample_revision', 'title' => 'Sample Revision', 'public' => false, 'sort' => 40],
            ['key' => 'sample_approval', 'title' => 'Sample Approval', 'public' => true, 'sort' => 50],
            ['key' => 'production_planning', 'title' => 'Production Planning', 'public' => false, 'sort' => 60],
            ['key' => 'production', 'title' => 'Production', 'public' => true, 'sort' => 70],
            ['key' => 'quality_inspection', 'title' => 'Quality Inspection', 'public' => true, 'sort' => 80],
            ['key' => 'packing', 'title' => 'Packing', 'public' => true, 'sort' => 90],
            ['key' => 'ready_for_dispatch', 'title' => 'Ready for Dispatch', 'public' => true, 'sort' => 100],
            ['key' => 'dispatched', 'title' => 'Dispatched', 'public' => true, 'sort' => 110],
            ['key' => 'internal_qc', 'title' => 'Internal QC', 'public' => false, 'sort' => 120],
            ['key' => 'inspection_by_client', 'title' => 'Inspection By Client', 'public' => true, 'sort' => 130],
            ['key' => 'shipment_planning', 'title' => 'Shipment Planning', 'public' => false, 'sort' => 140],
            ['key' => 'delivered', 'title' => 'Delivered', 'public' => true, 'sort' => 150],
        ];
    }
}
