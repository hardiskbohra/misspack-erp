<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectApprovalRequest;
use App\Models\ProjectGateEvaluation;
use App\Models\ProjectGateOverride;
use App\Models\ProjectLog;
use App\Models\ProjectWorkflowStep;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Explicit, source-scoped administrator decisions; never a blanket admin exemption. */
class ProjectWorkflowBypass
{
    public const MISSING_APPROVAL = 'missing_approval_request';
    public const MISSING_DISPATCH = 'missing_planning_shipment';
    public const EARLY_FEEDBACK = 'feedback_before_commercial_closure';
    public const MANUAL_STEP = 'manual_step';

    public function bypass(Project $project, ProjectWorkflowStep $step, User $actor, string $reason, ?string $expectedDate = null, ?int $approvalSource = null, ?int $approvalVersion = null): void
    {
        abort_unless($actor->canOverrideProjects(), 403);
        $this->validateReason($reason);

        DB::transaction(function () use ($project, $step, $actor, $reason, $expectedDate, $approvalSource, $approvalVersion) {
            Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
            $step = ProjectWorkflowStep::query()->whereKey($step->id)->lockForUpdate()->firstOrFail();
            $workflow = $step->workflow;
            abort_unless($workflow && (int) $workflow->project_id === (int) $project->id, 404);
            app(ProjectWorkflowExecution::class)->sync($workflow, $project);
            $step->refresh();
            if ($step->project_product_id !== null && ! app(ProjectWorkflowSalesScope::class)->contains($project->id, $step->project_product_id, true)) {
                throw ValidationException::withMessages(['reason' => 'This historical control is not for a current sales-invoice item.']);
            }
            if ($step->step_key === ProjectFinancialClosure::GATE) {
                throw ValidationException::withMessages(['closure' => 'Use manual project closure to record the financial snapshot and accepted risk.']);
            }
            if (in_array($step->status, ['completed', 'cancelled'], true)) {
                throw ValidationException::withMessages(['reason' => 'This workflow control is already complete or cancelled.']);
            }

            if (in_array($step->step_key, ProjectApprovals::CONTROLLED_STEPS, true)) {
                $type = $step->step_key === 'artwork_approved' ? ProjectApprovalRequest::TYPE_ARTWORK : ProjectApprovalRequest::TYPE_PPS;
                $approval = $workflow->approvals()->where('project_product_id', $step->project_product_id)
                    ->where('approval_type', $type)->where('status', '!=', 'revoked')->lockForUpdate()->first();
                if ($approvalSource === null || ($approval && ($approvalSource !== (int) $approval->id || $approvalVersion !== $approval->current_version_number))
                    || (! $approval && $approvalSource !== 0)) {
                    throw ValidationException::withMessages(['reason' => 'The client approval request or file changed. Refresh the workflow and review its current version.']);
                }
                if ($approval) {
                    if ($approval->status === 'approved') {
                        throw ValidationException::withMessages(['reason' => 'This approval already passed for the current version.']);
                    }
                    app(ProjectApprovals::class)->override($approval, $reason, $actor->id, $approvalVersion);
                    $this->log($project, $step, $actor, 'workflow_bypass', 'Administrator bypassed client approval', $reason, [
                        'approval_id' => $approval->id, 'version' => $approval->current_version_number,
                    ]);
                    return;
                }
                $evaluation = $this->synthetic($step, self::MISSING_APPROVAL, 'No client approval request exists for this product and approval type.', $actor->id);
            } else {
                $evaluation = $this->evaluate($step, $actor);
            }

            $this->approveEvaluation($project, $step, $evaluation, $actor, $reason, $expectedDate);
        });
    }

    /** Existing evaluation-specific forms use the same locking and auditing path. */
    public function overrideEvaluation(Project $project, ProjectGateEvaluation $evaluation, User $actor, string $reason, ?string $expectedDate = null): void
    {
        abort_unless($actor->canOverrideProjects(), 403);
        $this->validateReason($reason);
        DB::transaction(function () use ($project, $evaluation, $actor, $reason, $expectedDate) {
            Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
            $evaluation = ProjectGateEvaluation::query()->whereKey($evaluation->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $evaluation->workflow?->project_id === (int) $project->id, 404);
            if ($evaluation->gate_key === ProjectFinancialClosure::GATE) {
                throw ValidationException::withMessages(['closure' => 'Use the financial closure action to record the closure decision and snapshot.']);
            }
            app(ProjectWorkflowExecution::class)->sync($evaluation->workflow, $project);
            $step = app(ProjectWorkflowSalesScope::class)->steps($evaluation->workflow->allSteps(), true)
                ->where('project_product_id', $evaluation->project_product_id)->where('step_key', $evaluation->gate_key)
                ->lockForUpdate()->firstOrFail();
            if (self::isSynthetic($evaluation)) {
                throw ValidationException::withMessages(['reason' => 'Use the individual step bypass so that missing-source facts are checked again.']);
            }
            if ($evaluation->gate_key === ShipmentDispatchAuthorization::GATE_KEY) {
                $shipment = Shipment::query()->where('project_id', $project->id)->where('status', Shipment::STATUS_PLANNING)
                    ->whereKey(data_get($evaluation->details, 'shipment_id'))->first();
                if (! $shipment) {
                    throw ValidationException::withMessages(['reason' => 'The planning shipment changed. Refresh the workflow before bypassing this control.']);
                }
                $authorization = app(ShipmentDispatchAuthorization::class);
                $fresh = $authorization->evaluate($shipment, $step->workflow, $authorization->rowsFromShipment($shipment), $actor->id);
            } else {
                $fresh = $this->evaluate($step, $actor);
            }
            if ((int) $fresh->id !== (int) $evaluation->id) {
                throw ValidationException::withMessages(['reason' => 'The source facts changed. Refresh the workflow before bypassing this control.']);
            }
            $this->approveEvaluation($project, $step, $evaluation, $actor, $reason, $expectedDate);
        });
    }

    public function revoke(Project $project, ProjectGateOverride $override, User $actor, string $reason): void
    {
        abort_unless($actor->canOverrideProjects(), 403);
        if (mb_strlen(trim($reason)) < 5) throw ValidationException::withMessages(['revocation_reason' => 'Explain why this bypass is revoked.']);
        DB::transaction(function () use ($project, $override, $actor, $reason) {
            Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
            $override = ProjectGateOverride::query()->whereKey($override->id)->lockForUpdate()->firstOrFail();
            $evaluation = $override->evaluation;
            abort_unless($evaluation && (int) $evaluation->workflow?->project_id === (int) $project->id && ! $override->revoked_at, 404);
            $step = ProjectWorkflowStep::query()->where('project_workflow_id', $evaluation->project_workflow_id)
                ->where('project_product_id', $evaluation->project_product_id)->where('step_key', $evaluation->gate_key)
                ->lockForUpdate()->first();
            $latest = $step ? self::currentEvaluation($step) : null;
            $override->update(['revoked_at' => now(), 'revoked_by' => $actor->id, 'revocation_reason' => $reason]);
            if ($step && (int) $latest?->id === (int) $evaluation->id) {
                $evaluation->unsetRelation('activeOverride');
                if (self::isSynthetic($evaluation)) {
                    $prior = $evaluation->details['prior_step'] ?? [];
                    $step->update([
                        'status' => $prior['status'] ?? 'not_started',
                        'blocked_reason' => $prior['blocked_reason'] ?? null,
                        'notes' => $prior['notes'] ?? null,
                        'actual_started_at' => $prior['actual_started_at'] ?? null,
                        'completed_at' => $prior['completed_at'] ?? null,
                        'completed_by' => $prior['completed_by'] ?? null,
                    ]);
                    app(ProjectWorkflowExecution::class)->recalculate($step->workflow);
                } elseif ($evaluation->gate_key === ProjectFeedbackClosure::GATE) {
                    // Re-read the request state so a live request returns to
                    // waiting_client rather than a generic blocked state.
                    app(ProjectFeedbackClosure::class)->sync($step->workflow->fresh(), $actor->id, false);
                } else {
                    $this->apply($evaluation, $actor->id);
                }
            }
            // In particular, never apply an old evaluation after a newer source
            // snapshot has taken over this same step.
            $this->log($project, $step, $actor, 'workflow_bypass_revoked', 'Administrator revoked workflow bypass', $reason, [
                'override_id' => $override->id, 'evaluation_id' => $evaluation->id,
                'was_current' => $latest && (int) $latest->id === (int) $evaluation->id,
            ]);
        });
    }

    public static function currentEvaluation(ProjectWorkflowStep $step): ?ProjectGateEvaluation
    {
        $current = DB::transactionLevel() > 0;
        $evaluation = $step->workflow->evaluations()->where('gate_key', $step->step_key)
            ->where('project_product_id', $step->project_product_id)
            ->when(! $current, fn ($query) => $query->with('activeOverride.approver'))
            ->when($current, fn ($query) => $query->lockForUpdate())
            ->latest('evaluated_at')->latest('id')->first();
        if ($evaluation && $current) {
            $override = $evaluation->overrides()->whereNull('revoked_at')->latest('id')->lockForUpdate()->first();
            $override?->load('approver');
            $evaluation->setRelation('activeOverride', $override);
        }
        return $evaluation;
    }

    public static function isSynthetic(?ProjectGateEvaluation $evaluation): bool
    {
        return (bool) data_get($evaluation?->details, 'admin_bypass_context');
    }

    /** A newly-created client request supersedes a bypass of a missing request. */
    public function supersedeMissingApproval(ProjectWorkflowStep $step, ?int $userId): void
    {
        $evaluation = self::currentEvaluation($step);
        if (data_get($evaluation?->details, 'admin_bypass_context') !== self::MISSING_APPROVAL || ! $evaluation?->activeOverride) return;
        $evaluation->activeOverride->update([
            'revoked_at' => now(), 'revoked_by' => $userId,
            'revocation_reason' => 'A client approval request was created; the missing-request bypass no longer applies.',
        ]);
    }

    private function evaluate(ProjectWorkflowStep $step, User $actor): ProjectGateEvaluation
    {
        $workflow = $step->workflow;
        $key = $step->step_key;
        if (in_array($key, [ProjectWorkflowGates::GATE_BASELINE, ProjectWorkflowGates::GATE_ADVANCE, ProjectWorkflowGates::GATE_DISPATCH_BALANCE], true)) {
            app(ProjectWorkflowGates::class)->evaluateAll($workflow, $actor->id);
        } elseif (in_array($key, ProjectOperationalReadiness::CONTROLLED_STEPS, true)) {
            app(ProjectOperationalReadiness::class)->evaluateAll($workflow, $actor->id);
        } elseif ($key === ProjectFeedbackClosure::GATE) {
            $evaluation = app(ProjectFeedbackClosure::class)->sync($workflow->fresh(), $actor->id, false);
            if (! $evaluation) return $this->synthetic($step->fresh(), self::EARLY_FEEDBACK, 'Final feedback is not yet eligible because commercial closure is incomplete.', $actor->id);
        } elseif ($key === ShipmentDispatchAuthorization::GATE_KEY) {
            $shipment = $workflow->project->shipments()->where('status', Shipment::STATUS_PLANNING)->latest('id')->first();
            if (! $shipment) return $this->synthetic($step, self::MISSING_DISPATCH, 'No planning shipment exists to evaluate for dispatch authorization.', $actor->id);
            $authorization = app(ShipmentDispatchAuthorization::class);
            return $authorization->evaluate($shipment, $workflow, $authorization->rowsFromShipment($shipment), $actor->id);
        } else {
            return $this->synthetic($step, self::MANUAL_STEP, 'This manual workflow control has not been completed.', $actor->id);
        }

        return self::currentEvaluation($step->fresh())
            ?? throw ValidationException::withMessages(['reason' => 'The current source evaluation could not be created. Refresh the workflow and retry.']);
    }

    private function synthetic(ProjectWorkflowStep $step, string $context, string $summary, int $userId): ProjectGateEvaluation
    {
        $current = self::currentEvaluation($step);
        if (self::isSynthetic($current) && $current?->activeOverride) {
            throw ValidationException::withMessages(['reason' => 'This control already has an active administrator bypass.']);
        }
        $details = [
            'admin_bypass_context' => $context, 'step_id' => $step->id, 'step_key' => $step->step_key,
            'project_product_id' => $step->project_product_id,
            'prior_step' => [
                'status' => $step->status, 'blocked_reason' => $step->blocked_reason, 'notes' => $step->notes,
                'actual_started_at' => $step->actual_started_at?->toISOString(),
                'completed_at' => $step->completed_at?->toISOString(), 'completed_by' => $step->completed_by,
            ],
        ];
        return $step->workflow->evaluations()->create([
            'project_product_id' => $step->project_product_id, 'gate_key' => $step->step_key,
            'result' => 'blocked', 'content_hash' => hash('sha256', json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'summary' => $summary, 'details' => $details, 'evaluated_by' => $userId, 'evaluated_at' => now(),
        ]);
    }

    private function approveEvaluation(Project $project, ProjectWorkflowStep $step, ProjectGateEvaluation $evaluation, User $actor, string $reason, ?string $expectedDate): void
    {
        $latest = self::currentEvaluation($step);
        if (! $latest || (int) $latest->id !== (int) $evaluation->id) {
            throw ValidationException::withMessages(['reason' => 'The source facts changed. Refresh the workflow before bypassing this control.']);
        }
        if ($evaluation->result !== 'blocked') {
            throw ValidationException::withMessages(['reason' => 'This source control already passed; a bypass is not needed.']);
        }
        if ($latest->activeOverride) {
            throw ValidationException::withMessages(['reason' => 'This control already has an active administrator bypass.']);
        }
        $override = $evaluation->overrides()->create([
            'reason' => $reason, 'expected_resolution_date' => $expectedDate,
            'risk_snapshot' => $evaluation->details, 'approved_by' => $actor->id, 'approved_at' => now(),
        ]);
        $evaluation->setRelation('activeOverride', $override);
        if (self::isSynthetic($evaluation)) {
            $step->update([
                'status' => 'skipped', 'blocked_reason' => null,
                'notes' => 'Administrator override #'.$override->id.'. '.$evaluation->summary,
                'actual_started_at' => $step->actual_started_at ?: now(),
                'completed_at' => now(), 'completed_by' => $actor->id,
            ]);
            app(ProjectWorkflowExecution::class)->recalculate($step->workflow);
        } else {
            $this->apply($evaluation, $actor->id);
        }
        $this->log($project, $step, $actor, 'workflow_bypass', 'Administrator bypassed workflow control', $reason, [
            'evaluation_id' => $evaluation->id, 'override_id' => $override->id, 'risk_snapshot' => $evaluation->details,
        ]);
    }

    private function apply(ProjectGateEvaluation $evaluation, int $userId): void
    {
        $workflow = $evaluation->workflow;
        if (in_array($evaluation->gate_key, ProjectOperationalReadiness::CONTROLLED_STEPS, true)) {
            app(ProjectOperationalReadiness::class)->applyToStep($evaluation);
            app(ProjectOperationalReadiness::class)->evaluateAll($workflow->fresh(), $userId);
        } elseif ($evaluation->gate_key === ProjectFeedbackClosure::GATE) {
            app(ProjectFeedbackClosure::class)->applyToStep($evaluation);
            app(ProjectWorkflowExecution::class)->recalculate($workflow);
        } else {
            app(ProjectWorkflowGates::class)->applyToStep($evaluation);
            app(ProjectWorkflowExecution::class)->recalculate($workflow);
        }
    }

    private function validateReason(string $reason): void
    {
        if (mb_strlen(trim($reason)) < 10) throw ValidationException::withMessages(['reason' => 'Explain the accepted risk in at least 10 characters.']);
    }

    private function log(Project $project, ?ProjectWorkflowStep $step, User $actor, string $event, string $title, string $reason, array $details): void
    {
        ProjectLog::query()->create([
            'project_id' => $project->id, 'project_product_id' => $step?->project_product_id,
            'user_id' => $actor->id, 'actor_type' => 'internal', 'actor_name' => $actor->name,
            'event_type' => $event, 'title' => $title, 'description' => $reason,
            'new_values' => ['step_id' => $step?->id, 'step_key' => $step?->step_key] + $details,
            'is_public' => false,
        ]);
    }
}
