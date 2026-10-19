<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectGateEvaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_workflow_id', 'project_product_id', 'gate_key', 'result', 'content_hash',
        'summary', 'details', 'evaluated_by', 'evaluated_at',
    ];

    protected $casts = ['details' => 'array', 'evaluated_at' => 'datetime'];

    public function workflow() { return $this->belongsTo(ProjectWorkflow::class, 'project_workflow_id'); }
    public function product() { return $this->belongsTo(ProjectProduct::class, 'project_product_id'); }
    public function evaluator() { return $this->belongsTo(User::class, 'evaluated_by'); }
    public function overrides() { return $this->hasMany(ProjectGateOverride::class); }
    public function activeOverride() { return $this->hasOne(ProjectGateOverride::class)->whereNull('revoked_at')->latestOfMany(); }

    public function effectiveResult(): string
    {
        return $this->result === 'blocked' && ($this->relationLoaded('activeOverride') ? $this->activeOverride : $this->activeOverride()->exists())
            ? 'overridden'
            : $this->result;
    }

    public function resultLabel(): string
    {
        return ['passed' => 'Passed', 'blocked' => 'Blocked', 'overridden' => 'Manager override'][$this->effectiveResult()] ?? str($this->effectiveResult())->headline()->toString();
    }

    public function gateLabel(): string
    {
        return [
            'commercial_baseline' => 'Commercial baseline',
            'advance_received' => 'Production advance',
            'dispatch_balance' => 'Pre-dispatch balance',
            'dispatch_approved' => 'Shipment dispatch authorization',
            'procurement_ready' => 'Procurement readiness',
            'production_complete' => 'Production readiness',
            'quality_passed' => 'Quality readiness',
            'packing_verified' => 'Verified packing',
            'shipment_dispatched' => 'Quantity dispatched',
            'delivery_complete' => 'Quantity delivered',
            'delivery_reconciled' => 'Project delivery reconciliation',
        ][$this->gate_key] ?? str($this->gate_key)->headline()->toString();
    }
}
