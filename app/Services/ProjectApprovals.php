<?php

namespace App\Services;

use App\Models\ClientPortalUser;
use App\Models\Project;
use App\Models\ProjectApprovalRequest;
use App\Models\ProjectAttachment;
use App\Models\ProjectWorkflow;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectApprovals
{
    public const CONTROLLED_STEPS = ['artwork_approved', 'pps_approved'];

    public function create(ProjectWorkflow $workflow, int $productId, string $type, string $title, ?string $instructions, $dueAt, ProjectAttachment $attachment, string $contentHash, ?int $userId): ProjectApprovalRequest
    {
        return DB::transaction(function () use ($workflow, $productId, $type, $title, $instructions, $dueAt, $attachment, $contentHash, $userId) {
            Project::query()->whereKey($workflow->project_id)->lockForUpdate()->firstOrFail();
            $this->assertSalesItem((int) $workflow->project_id, $productId);
            app(ProjectWorkflowExecution::class)->sync($workflow);
            $existing = $workflow->approvals()->where('project_product_id', $productId)->where('approval_type', $type)->whereNotIn('status', ['revoked'])->first();
            if ($existing) {
                throw ValidationException::withMessages(['approval_type' => 'This product already has an approval request of this type. Add a new version to that request.']);
            }
            $approval = $workflow->approvals()->create([
                'project_id' => $workflow->project_id, 'project_product_id' => $productId,
                'approval_type' => $type, 'title' => $title, 'instructions' => $instructions,
                'status' => 'open', 'current_version_number' => 1, 'response_due_at' => $dueAt,
                'requested_by' => $userId, 'requested_at' => now(),
            ]);
            $approval->versions()->create([
                'version_number' => 1, 'project_attachment_id' => $attachment->id,
                'content_hash' => $contentHash, 'submission_notes' => $instructions,
                'status' => 'pending', 'submitted_by' => $userId, 'submitted_at' => now(),
            ]);
            $this->syncStep($approval);
            return $approval->fresh(['product', 'versions.attachment', 'currentVersion.attachment']);
        });
    }

    public function addVersion(ProjectApprovalRequest $approval, ProjectAttachment $attachment, string $contentHash, ?string $notes, $dueAt, ?int $userId): ProjectApprovalRequest
    {
        return DB::transaction(function () use ($approval, $attachment, $contentHash, $notes, $dueAt, $userId) {
            Project::query()->whereKey($approval->project_id)->lockForUpdate()->firstOrFail();
            $approval = ProjectApprovalRequest::query()->whereKey($approval->id)->lockForUpdate()->firstOrFail();
            $this->assertSalesItem((int) $approval->project_id, (int) $approval->project_product_id);
            $approval->versions()->where('status', '!=', 'superseded')->update(['status' => 'superseded']);
            $next = $approval->versions()->max('version_number') + 1;
            $approval->versions()->create([
                'version_number' => $next, 'project_attachment_id' => $attachment->id,
                'content_hash' => $contentHash, 'submission_notes' => $notes,
                'status' => 'pending', 'submitted_by' => $userId, 'submitted_at' => now(),
            ]);
            $approval->update([
                'current_version_number' => $next, 'status' => 'open', 'response_due_at' => $dueAt,
                'resolved_at' => null,
            ]);
            $this->syncStep($approval);
            return $approval->fresh(['versions.attachment', 'currentVersion.attachment']);
        });
    }

    public function decide(ProjectApprovalRequest $approval, string $decision, ?string $comment, ClientPortalUser $portalUser): void
    {
        DB::transaction(function () use ($approval, $decision, $comment, $portalUser) {
            Project::query()->whereKey($approval->project_id)->lockForUpdate()->firstOrFail();
            $approval = ProjectApprovalRequest::query()->whereKey($approval->id)->lockForUpdate()->firstOrFail();
            $this->assertSalesItem((int) $approval->project_id, (int) $approval->project_product_id);
            app(ProjectWorkflowExecution::class)->sync($approval->workflow);
            $version = $approval->versions()->where('version_number', $approval->current_version_number)->lockForUpdate()->first();
            if ($approval->status !== 'open' || ! $version || $version->status !== 'pending') {
                throw ValidationException::withMessages(['decision' => 'This approval version is no longer awaiting a decision.']);
            }
            if ($decision !== 'approved' && trim((string) $comment) === '') {
                throw ValidationException::withMessages(['client_comment' => 'Please explain the rejection or requested changes.']);
            }
            $version->update([
                'status' => $decision, 'decided_by_portal_user_id' => $portalUser->id,
                'decided_at' => now(), 'client_comment' => $comment,
            ]);
            if ($override = $approval->activeOverrideRecord()) {
                $override->update(['revoked_at' => now(), 'revocation_reason' => 'Client decision received for this version.']);
                $approval->unsetRelation('overrides');
            }
            $approval->update(['status' => $decision, 'resolved_at' => now()]);
            $this->syncStep($approval);
        });
    }

    public function override(ProjectApprovalRequest $approval, string $reason, int $userId, int $expectedVersion): void
    {
        abort_unless(User::query()->find($userId)?->canOverrideProjects(), 403);
        DB::transaction(function () use ($approval, $reason, $userId, $expectedVersion) {
            Project::query()->whereKey($approval->project_id)->lockForUpdate()->firstOrFail();
            $approval = ProjectApprovalRequest::query()->whereKey($approval->id)->lockForUpdate()->firstOrFail();
            $this->assertSalesItem((int) $approval->project_id, (int) $approval->project_product_id);
            app(ProjectWorkflowExecution::class)->sync($approval->workflow);
            $approval->refresh();
            if ($approval->current_version_number !== $expectedVersion) {
                throw ValidationException::withMessages(['override_reason' => 'The approval file changed. Review the current version before overriding it.']);
            }
            if (in_array($approval->status, ['approved', 'revoked'], true)) {
                throw ValidationException::withMessages(['override_reason' => 'This approval is already approved or revoked.']);
            }
            if ($approval->hasActiveOverride()) {
                throw ValidationException::withMessages(['override_reason' => 'This version already has an active administrator override.']);
            }
            $approval->overrides()->create([
                'version_number' => $expectedVersion, 'reason' => $reason,
                'approved_by' => $userId, 'approved_at' => now(),
            ]);
            $approval->unsetRelation('overrides');
            $this->syncStep($approval);
        });
    }

    public function revokeOverride(ProjectApprovalRequest $approval, string $reason, int $userId, int $expectedOverrideId): void
    {
        abort_unless(User::query()->find($userId)?->canOverrideProjects(), 403);
        DB::transaction(function () use ($approval, $reason, $userId, $expectedOverrideId) {
            Project::query()->whereKey($approval->project_id)->lockForUpdate()->firstOrFail();
            $approval = ProjectApprovalRequest::query()->whereKey($approval->id)->lockForUpdate()->firstOrFail();
            $override = $approval->activeOverrideRecord();
            if (! $override || (int) $override->id !== $expectedOverrideId) {
                throw ValidationException::withMessages(['override_revocation_reason' => 'The active override changed. Review the current approval before revoking it.']);
            }
            $override->update(['revoked_at' => now(), 'revoked_by' => $userId, 'revocation_reason' => $reason]);
            $approval->unsetRelation('overrides');
            $this->syncStep($approval);
        });
    }

    private function assertSalesItem(int $projectId, int $productId): void
    {
        if (! app(ProjectWorkflowSalesScope::class)->contains($projectId, $productId, true)) {
            throw ValidationException::withMessages(['project_product_id' => 'Approvals are available only for current sales-invoice items.']);
        }
    }

    public function syncStep(ProjectApprovalRequest $approval): void
    {
        $stepKey = $approval->approval_type === ProjectApprovalRequest::TYPE_ARTWORK ? 'artwork_approved' : 'pps_approved';
        $current = DB::transactionLevel() > 0;
        $step = app(ProjectWorkflowSalesScope::class)->steps($approval->workflow->allSteps(), $current)
            ->where('project_product_id', $approval->project_product_id)->where('step_key', $stepKey)
            ->when($current, fn ($query) => $query->lockForUpdate())->first();
        if (! $step) return;
        // A missing-request bypass cannot stand in for a subsequently submitted
        // client file. The new request and every later version decide afresh.
        app(ProjectWorkflowBypass::class)->supersedeMissingApproval($step, auth()->id());
        $effective = $approval->effectiveStatus();
        $complete = in_array($effective, ['approved', 'overridden'], true);
        $status = match ($effective) {
            'approved' => 'completed', 'overridden' => 'skipped', 'open' => 'waiting_client',
            'rejected', 'changes_requested' => 'blocked', default => 'not_started',
        };
        $activeOverride = $approval->activeOverrideRecord();
        $step->update([
            'status' => $status,
            'blocked_reason' => in_array($effective, ['rejected', 'changes_requested'], true) ? $approval->statusLabel().' on version '.$approval->current_version_number.'.' : null,
            'notes' => $effective === 'overridden' ? 'Client approval continued under manager override: '.$activeOverride?->reason : 'Approval request #'.$approval->id.' · version '.$approval->current_version_number,
            'actual_started_at' => $step->actual_started_at ?: now(),
            'completed_at' => $complete ? now() : null,
            'completed_by' => $effective === 'overridden' ? $activeOverride?->approved_by : null,
        ]);
        app(ProjectWorkflowExecution::class)->recalculate($approval->workflow);
    }
}
