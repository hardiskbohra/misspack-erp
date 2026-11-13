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

    /** Present current policy without rewriting immutable historical snapshots. */
    public function displaySummary(): string
    {
        if ($this->gate_key !== 'dispatch_balance' || $this->result !== 'blocked') {
            return (string) $this->summary;
        }

        $details = $this->details ?: [];
        if (! array_key_exists('invoice_usable', $details)) {
            return 'The pre-dispatch balance needs review. Shipment tracking may continue; please follow up with Accounts.';
        }
        if (! $details['invoice_usable']) {
            return 'Pre-dispatch balance cannot be verified without a valid project Proforma. Shipment tracking may continue; please review it with Accounts.';
        }

        return 'Pre-dispatch balance is outstanding by '.\App\Helpers\CommonHelper::decimal($details['shortfall'] ?? 0)
            .'. Shipment tracking may continue; the balance remains due.';
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
            'packing_verified' => 'Packing ready',
            'shipment_dispatched' => 'Quantity dispatched',
            'delivery_complete' => 'Quantity delivered',
            'delivery_reconciled' => 'Project delivery reconciliation',
            'commercial_closure' => 'Financial closure reconciliation',
            'feedback_closure' => 'Final feedback closure',
        ][$this->gate_key] ?? str($this->gate_key)->headline()->toString();
    }
}
