<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectWorkflow;
use App\Models\ProjectWorkflowStep;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectWorkflowExecution
{
    public const TEMPLATE_KEY = 'standard_execution';
    public const TEMPLATE_VERSION = 2;

    private const PROJECT_STEPS = [
        ['setup', 'project_setup', 'Project setup complete', 10, 4, true],
        ['commercial', 'commercial_baseline', 'Commercial baseline Proforma confirmed', 20, 3, false],
        ['commercial', 'advance_received', 'Required production advance received', 30, 3, false],
        ['dispatch', 'dispatch_balance', 'Complete pre-dispatch balance received', 100, 3, false],
        ['dispatch', 'dispatch_approved', 'Dispatch approved', 110, 2, false],
        ['delivery', 'delivery_reconciled', 'All product quantities delivered and reconciled', 125, 3, true],
        ['closure', 'commercial_closure', 'Commercial closure reviewed', 130, 2, false],
        ['closure', 'feedback_closure', 'Feedback and final closure completed', 140, 1, true],
    ];

    private const PRODUCT_STEPS = [
        ['procurement', 'procurement_ready', 'Procurement ready', 40, 2, false],
        ['artwork', 'artwork_approved', 'Current artwork approved', 50, 2, true],
        ['sampling', 'pps_approved', 'PPS approved or not applicable', 60, 2, true],
        ['production', 'production_complete', 'Production quantity completed', 70, 4, true],
        ['quality', 'quality_passed', 'Quality inspection passed', 80, 2, true],
        ['packing', 'packing_verified', 'Packing confirmed and automatically checked', 90, 2, true],
        ['dispatch', 'shipment_dispatched', 'Required quantity dispatched', 115, 2, true],
        ['delivery', 'delivery_complete', 'Required quantity delivered', 120, 3, true],
    ];

    public static function stageOptions(): array
    {
        return [
            'setup' => 'Project setup', 'commercial' => 'Commercial readiness',
            'procurement' => 'Procurement', 'artwork' => 'Artwork approval',
            'sampling' => 'PPS approval', 'production' => 'Production',
            'quality' => 'Quality control', 'packing' => 'Packing',
            'dispatch' => 'Dispatch readiness', 'delivery' => 'Delivery',
            'closure' => 'Closure', 'completed' => 'Completed',
        ];
    }

    public function start(Project $project, ?int $userId = null): ProjectWorkflow
    {
        return DB::transaction(function () use ($project, $userId) {
            $project = Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
            // A current read is needed even if the caller's transaction opened
            // a repeatable-read snapshot before waiting for the project lock.
            $workflow = ProjectWorkflow::query()->where('project_id', $project->id)->lockForUpdate()->first();
            if (! $workflow) {
                $workflow = ProjectWorkflow::query()->create([
                    'project_id' => $project->id,
                    'template_key' => self::TEMPLATE_KEY,
                    'template_version' => self::TEMPLATE_VERSION,
                    'status' => 'active',
                    'calculated_stage' => 'setup',
                    'calculated_progress' => 0,
                    'started_by' => $userId,
                    'started_at' => now(),
                ]);
            }

            $this->sync($workflow, $project);

            return $this->recalculate($workflow);
        });
    }

    public function sync(ProjectWorkflow $workflow, ?Project $project = null): void
    {
        DB::transaction(function () use ($workflow) {
            // Every caller, including invoice saves, must serialize on the same
            // project before looking for or creating steps. Re-read products;
            // a previously loaded relationship may be stale.
            $project = Project::query()->whereKey($workflow->project_id)->lockForUpdate()->firstOrFail();
            $workflow = ProjectWorkflow::query()->whereKey($workflow->id)->lockForUpdate()->firstOrFail();
            $products = app(ProjectWorkflowSalesScope::class)->products($project->id, true)->lockForUpdate()->get();
            $ids = $products->modelKeys();
            $historical = $workflow->allSteps()->whereNotNull('project_product_id')->lockForUpdate()->get()->groupBy('project_product_id');
            foreach ($historical as $productId => $steps) {
                $active = in_array((int) $productId, $ids, true);
                $reentered = $active && $steps->contains(fn ($step) => data_get($step->metadata, 'sales_workflow_scope.active') === false);
                if ($reentered) $this->retireObsoleteDecisions($workflow, (int) $productId);
                foreach ($steps as $step) {
                    $metadata = $step->metadata ?: [];
                    $previous = data_get($metadata, 'sales_workflow_scope.active');
                    if ($reentered) {
                        $metadata['sales_workflow_scope']['previous_steps'][] = $step->only([
                            'status', 'blocked_reason', 'notes', 'actual_started_at', 'completed_at', 'completed_by',
                        ]) + ['archived_at' => now()->toISOString()];
                        $step->fill(['status' => 'not_started', 'blocked_reason' => null,
                            'actual_started_at' => null, 'completed_at' => null, 'completed_by' => null]);
                    }
                    if ($previous !== $active || $reentered) {
                        $metadata['sales_workflow_scope']['active'] = $active;
                        $step->metadata = $metadata;
                    }
                    if ($step->isDirty()) $step->save();
                }
                if ($reentered) $this->restoreGenuineApprovals($workflow, (int) $productId);
            }

            if ((int) $workflow->template_version !== self::TEMPLATE_VERSION) {
                $workflow->update(['template_version' => self::TEMPLATE_VERSION]);
            }
            foreach (self::PROJECT_STEPS as $definition) {
                $this->ensureStep($workflow, null, $definition, 'project', $project->assigned_to);
            }
            foreach ($products as $product) {
                foreach (self::PRODUCT_STEPS as $definition) {
                    $this->ensureStep($workflow, $product->id, $definition, 'product', $project->assigned_to);
                }
            }
        });
    }

    /** A historical bypass must not authorize a newly reintroduced sales item. */
    private function retireObsoleteDecisions(ProjectWorkflow $workflow, int $productId): void
    {
        $reason = 'The item left the sales workflow scope and later returned; a fresh administrator decision is required.';
        $evaluationIds = $workflow->evaluations()->where('project_product_id', $productId)->lockForUpdate()->pluck('id');
        \App\Models\ProjectGateOverride::query()->whereIn('project_gate_evaluation_id', $evaluationIds)
            ->whereNull('revoked_at')->update(['revoked_at' => now(), 'revoked_by' => null, 'revocation_reason' => $reason]);
        $approvalIds = $workflow->approvals()->where('project_product_id', $productId)->lockForUpdate()->pluck('id');
        \App\Models\ProjectApprovalOverride::query()->whereIn('project_approval_request_id', $approvalIds)
            ->whereNull('revoked_at')->update(['revoked_at' => now(), 'revoked_by' => null, 'revocation_reason' => $reason]);
        \App\Models\ProjectLog::query()->create([
            'project_id' => $workflow->project_id, 'project_product_id' => $productId,
            'user_id' => auth()->id(), 'actor_type' => auth()->id() ? 'internal' : 'system',
            'actor_name' => auth()->user()?->name ?: 'System', 'event_type' => 'workflow_sales_scope_reentered',
            'title' => 'Sales workflow item reintroduced', 'description' => $reason,
            'new_values' => ['project_product_id' => $productId, 'evaluation_ids' => $evaluationIds->all(), 'approval_ids' => $approvalIds->all()],
            'is_public' => false,
        ]);
    }

    /** Restore real client decisions, never a now-revoked administrator override. */
    private function restoreGenuineApprovals(ProjectWorkflow $workflow, int $productId): void
    {
        $approvals = $workflow->approvals()->where('project_product_id', $productId)->where('status', '!=', 'revoked')->lockForUpdate()->get();
        foreach ($approvals as $approval) {
            $key = $approval->approval_type === 'artwork' ? 'artwork_approved' : 'pps_approved';
            $step = $workflow->allSteps()->where('project_product_id', $productId)->where('step_key', $key)->lockForUpdate()->first();
            if (! $step) continue;
            $status = match ($approval->status) {
                'approved' => 'completed', 'open' => 'waiting_client',
                'rejected', 'changes_requested' => 'blocked', default => 'not_started',
            };
            $step->update([
                'status' => $status,
                'blocked_reason' => $status === 'blocked' ? $approval->statusLabel().' on version '.$approval->current_version_number.'.' : null,
                'notes' => 'Approval request #'.$approval->id.' · version '.$approval->current_version_number,
                'actual_started_at' => $status === 'not_started' ? null : now(),
                'completed_at' => $status === 'completed' ? now() : null, 'completed_by' => null,
            ]);
        }
    }

    public function recalculate(ProjectWorkflow $workflow): ProjectWorkflow
    {
        return DB::transaction(function () use ($workflow) {
            // Lock the published project state before reading or updating the
            // workflow. A concurrent manual closure or override must win over
            // an earlier, stale recalculation, not be silently overwritten.
            $project = Project::query()->whereKey($workflow->project_id)->lockForUpdate()->firstOrFail();
            $workflow = ProjectWorkflow::query()->whereKey($workflow->id)->lockForUpdate()->firstOrFail();
            $this->sync($workflow, $project);
            $workflow->setRelation('steps', app(ProjectWorkflowSalesScope::class)->steps($workflow->allSteps(), true)->lockForUpdate()->get());
            $eligible = $workflow->steps
                ->where('is_required', true)
                ->whereNotIn('status', ['cancelled']);
            $totalWeight = (float) $eligible->sum(fn ($step) => (float) $step->weight);
            $completedWeight = (float) $eligible
                ->whereIn('status', ['completed', 'skipped'])
                ->sum(fn ($step) => (float) $step->weight);
            $progress = $totalWeight > 0 ? (int) round($completedWeight / $totalWeight * 100) : 0;
            $next = $eligible->whereNotIn('status', ['completed', 'skipped'])->sortBy('sort_order')->first();

            $calculatedProgress = max(0, min(100, $progress));
            $workflow->fill([
                'calculated_progress' => $calculatedProgress,
                'calculated_stage' => $next?->stage_key ?: 'completed',
                'status' => $next ? 'active' : 'completed',
            ]);
            if ($workflow->isDirty()) $workflow->save();
            // Keep calculated evidence on the workflow. An explicitly authorised
            // project override publishes separately until an administrator clears it;
            // it never changes a step or makes a gate pass. Closure and legacy
            // cancellation are terminal decisions, not ordinary workflow statuses.
            $terminalDecision = in_array($project->status, Project::closureStatuses(), true)
                || ($project->status === 'cancelled' && $project->manual_status === null);
            $publishedStatus = $terminalDecision
                ? $project->status
                : ($project->manual_status ?? ($next ? 'in_progress' : 'completed'));
            $projectState = [
                'progress_percent' => $project->manual_progress_percent ?? $calculatedProgress,
                'stage' => $terminalDecision ? $project->stage : ($next?->stage_key ?: 'completed'),
                'status' => $publishedStatus,
                'completed_at' => $terminalDecision
                    ? $project->completed_at
                    : ($publishedStatus === 'completed' ? ($project->completed_at ?: now()) : null),
            ];
            $project->forceFill($projectState);
            if ($project->isDirty()) $project->save();

            // Keep the locking-read steps in this return value; a fresh ordinary
            // SELECT could fall back to an older repeatable-read snapshot.
            return $workflow->loadMissing(['steps.product', 'steps.owner', 'reporter', 'commercialInvoice']);
        });
    }

    public function updateStep(ProjectWorkflowStep $step, string $status, ?int $userId, ?string $notes = null, ?string $blockedReason = null): ProjectWorkflow
    {
        return DB::transaction(function () use ($step, $status, $userId, $notes, $blockedReason) {
            Project::query()->whereKey($step->workflow->project_id)->lockForUpdate()->firstOrFail();
            $workflow = $step->workflow;
            $this->sync($workflow);
            $step = ProjectWorkflowStep::query()->whereKey($step->id)->lockForUpdate()->firstOrFail();
            if ($step->project_product_id !== null && ! app(ProjectWorkflowSalesScope::class)->contains($workflow->project_id, $step->project_product_id, true)) {
                throw ValidationException::withMessages(['status' => 'This historical control is not for a current sales-invoice item.']);
            }
            $current = ProjectWorkflowBypass::currentEvaluation($step);
            if (ProjectWorkflowBypass::isSynthetic($current) && $current?->activeOverride) {
                throw ValidationException::withMessages(['status' => 'Ask an administrator to revoke the active bypass before changing this control.']);
            }
            $wasStarted = $step->actual_started_at;
            $isComplete = in_array($status, ['completed', 'skipped'], true);
            $step->update([
                'status' => $status,
                'notes' => $notes,
                'blocked_reason' => $status === 'blocked' ? $blockedReason : null,
                'actual_started_at' => $wasStarted ?: ($status !== 'not_started' ? now() : null),
                'completed_at' => $isComplete ? now() : null,
                'completed_by' => $isComplete ? $userId : null,
            ]);

            return $this->recalculate($step->workflow);
        });
    }

    private function ensureStep(ProjectWorkflow $workflow, ?int $productId, array $definition, string $scope, ?int $ownerId): void
    {
        [$stage, $key, $title, $sort, $weight, $clientVisible] = $definition;
        $query = $workflow->allSteps()->where('step_key', $key)->lockForUpdate();
        $productId === null ? $query->whereNull('project_product_id') : $query->where('project_product_id', $productId);
        if ($existing = $query->first()) {
            $existing->update([
                'stage_key' => $stage, 'title' => $title, 'scope' => $scope,
                'sort_order' => $sort, 'weight' => $weight,
                'is_client_visible' => $clientVisible, 'owner_id' => $ownerId,
            ]);
            return;
        }

        $workflow->allSteps()->create([
            'project_product_id' => $productId,
            'step_key' => $key,
            'stage_key' => $stage,
            'title' => $title,
            'scope' => $scope,
            'sort_order' => $sort,
            'weight' => $weight,
            'status' => 'not_started',
            'is_required' => true,
            'is_client_visible' => $clientVisible,
            'metadata' => $productId === null ? null : ['sales_workflow_scope' => ['active' => true]],
            'owner_id' => $ownerId,
        ]);
    }
}
