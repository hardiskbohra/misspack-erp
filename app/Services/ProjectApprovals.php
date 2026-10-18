<?php

namespace App\Services;

use App\Models\ClientPortalUser;
use App\Models\ProjectApprovalRequest;
use App\Models\ProjectAttachment;
use App\Models\ProjectWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectApprovals
{
    public const CONTROLLED_STEPS = ['artwork_approved', 'pps_approved'];

    public function create(ProjectWorkflow $workflow, int $productId, string $type, string $title, ?string $instructions, $dueAt, ProjectAttachment $attachment, string $contentHash, ?int $userId): ProjectApprovalRequest
    {
        return DB::transaction(function () use ($workflow, $productId, $type, $title, $instructions, $dueAt, $attachment, $contentHash, $userId) {
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
            $approval = ProjectApprovalRequest::query()->whereKey($approval->id)->lockForUpdate()->firstOrFail();
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
            $approval = ProjectApprovalRequest::query()->whereKey($approval->id)->lockForUpdate()->firstOrFail();
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

    public function override(ProjectApprovalRequest $approval, string $reason, int $userId): void
    {
        if ($approval->hasActiveOverride()) {
            throw ValidationException::withMessages(['override_reason' => 'This version already has an active manager override.']);
        }
        $approval->overrides()->create([
            'version_number' => $approval->current_version_number, 'reason' => $reason,
            'approved_by' => $userId, 'approved_at' => now(),
        ]);
        $approval->unsetRelation('overrides');
        $this->syncStep($approval);
    }

    public function revokeOverride(ProjectApprovalRequest $approval, string $reason, int $userId): void
    {
        $override = $approval->activeOverrideRecord();
        if (! $override) {
            throw ValidationException::withMessages(['override_revocation_reason' => 'This version has no active manager override.']);
        }
        $override->update(['revoked_at' => now(), 'revoked_by' => $userId, 'revocation_reason' => $reason]);
        $approval->unsetRelation('overrides');
        $this->syncStep($approval);
    }

    public function syncStep(ProjectApprovalRequest $approval): void
    {
        $stepKey = $approval->approval_type === ProjectApprovalRequest::TYPE_ARTWORK ? 'artwork_approved' : 'pps_approved';
        $step = $approval->workflow->steps()->where('project_product_id', $approval->project_product_id)->where('step_key', $stepKey)->first();
        if (! $step) return;
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
