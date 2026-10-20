<?php

namespace App\Services;

use App\Models\OfficeAlert;
use App\Models\OfficeSetting;
use App\Models\Project;
use App\Models\ProjectApprovalOverride;
use App\Models\ProjectException;
use App\Models\ProjectGateOverride;
use App\Services\ProjectFinancialClosure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProjectExceptionRegistry
{
    public function synchronize(?int $actorId = null): array
    {
        return DB::transaction(function () use ($actorId) {
            $sources = $this->discover()->keyBy('source_key');
            $created = 0;
            $changed = 0;
            $resolved = 0;

            foreach ($sources as $source) {
                [$exception, $wasCreated, $wasChanged] = $this->upsert($source, $actorId);
                $created += $wasCreated ? 1 : 0;
                $changed += $wasChanged ? 1 : 0;
                $this->applyAutomaticEscalation($exception, $actorId);
            }

            ProjectException::query()->whereIn('status', [ProjectException::STATUS_OPEN, ProjectException::STATUS_ACKNOWLEDGED])
                ->whereNotIn('source_key', $sources->keys()->all() ?: ['__none__'])
                ->lockForUpdate()->get()->each(function (ProjectException $exception) use ($actorId, &$resolved) {
                    $from = $exception->status;
                    $exception->update([
                        'status' => ProjectException::STATUS_RESOLVED,
                        'resolved_at' => now(),
                        'resolved_by' => $actorId,
                        'resolution_note' => 'The source condition is no longer active.',
                    ]);
                    $this->event($exception, 'auto_resolved', $actorId, 'The source condition is no longer active.', $from, ProjectException::STATUS_RESOLVED);
                    $resolved++;
                });

            return compact('created', 'changed', 'resolved');
        });
    }

    public function acknowledge(ProjectException $exception, int $actorId, int $ownerId, string $plan, string $nextReview): void
    {
        DB::transaction(function () use ($exception, $actorId, $ownerId, $plan, $nextReview) {
            $locked = ProjectException::query()->lockForUpdate()->findOrFail($exception->id);
            if ($locked->status === ProjectException::STATUS_RESOLVED) return;
            $from = $locked->status;
            $locked->update([
                'status' => ProjectException::STATUS_ACKNOWLEDGED,
                'owner_id' => $ownerId,
                'acknowledged_at' => $locked->acknowledged_at ?: now(),
                'acknowledged_by' => $locked->acknowledged_by ?: $actorId,
                'response_plan' => $plan,
                'next_review_at' => $nextReview,
            ]);
            $this->event($locked, $from === ProjectException::STATUS_OPEN ? 'acknowledged' : 'follow_up_updated', $actorId, $plan, $from, ProjectException::STATUS_ACKNOWLEDGED);
        });
    }

    public function escalate(ProjectException $exception, int $actorId, string $reason, string $nextReview): void
    {
        DB::transaction(function () use ($exception, $actorId, $reason, $nextReview) {
            $locked = ProjectException::query()->lockForUpdate()->findOrFail($exception->id);
            if ($locked->status === ProjectException::STATUS_RESOLVED) return;
            $level = min(2, max(1, $locked->escalation_level + 1));
            $locked->update([
                'severity' => 'critical',
                'escalation_level' => $level,
                'escalated_at' => now(),
                'escalated_by' => $actorId,
                'next_review_at' => $nextReview,
            ]);
            $this->event($locked, 'manager_escalated', $actorId, $reason, $locked->status, $locked->status);
            $this->alert($locked, $reason);
        });
    }

    private function discover(): Collection
    {
        $sources = collect();
        foreach (app(ProjectCoordinatorQueue::class)->items(null, false) as $item) {
            if (! $item['is_overdue'] && ! $item['is_blocked'] && $item['coordinator'] !== null) continue;
            $category = $item['coordinator'] === null ? 'unassigned' : ($item['is_overdue'] ? 'overdue' : 'blocked');
            $snapshot = [
                'step_id' => $item['step']->id,
                'step_key' => $item['step']->step_key,
                'step_status' => $item['step']->status,
                'blocked_reason' => $item['step']->blocked_reason,
                'project_status' => $item['project']->status,
                'coordinator_id' => $item['project']->assigned_to,
                'owner_id' => $item['step']->owner_id,
                'due_at' => $item['due_date']?->toISOString(),
                'due_source' => $item['due_source'],
                'is_overdue' => $item['is_overdue'],
                'is_blocked' => $item['is_blocked'],
                'updated_at' => $item['step']->updated_at?->toISOString(),
            ];
            $sources->push($this->source([
                'project_id' => $item['project']->id,
                'project_workflow_id' => $item['workflow']->id,
                'project_workflow_step_id' => $item['step']->id,
                'project_product_id' => $item['product']?->id,
                'source_type' => 'workflow_step',
                'source_key' => 'workflow_step:'.$item['step']->id,
                'category' => $category,
                'severity' => $item['is_overdue'] ? 'critical' : 'warning',
                'title' => $item['project']->project_number.' · '.$item['step']->title,
                'summary' => $item['coordinator'] === null ? 'Assign a central project coordinator.' : ($item['step']->blocked_reason ?: 'The planned completion date has passed.'),
                'due_at' => $item['due_date'],
                'source_snapshot' => $snapshot,
            ]));
        }

        $this->gateOverrideSources()->each(fn ($source) => $sources->push($source));
        $this->approvalOverrideSources()->each(fn ($source) => $sources->push($source));
        $this->financialClosureSources()->each(fn ($source) => $sources->push($source));

        return $sources;
    }

    private function gateOverrideSources(): Collection
    {
        return ProjectGateOverride::query()->whereNull('revoked_at')
            ->with(['evaluation.workflow.project', 'evaluation.product'])->get()
            ->filter(function ($override) {
                $evaluation = $override->evaluation;
                if (! $evaluation?->workflow?->project || in_array($evaluation->workflow->project->status, ['closed', 'cancelled'], true)) return false;
                if ($evaluation->gate_key === ProjectFinancialClosure::GATE) return false;
                return (int) $evaluation->workflow->evaluations()
                    ->where('gate_key', $evaluation->gate_key)
                    ->where('project_product_id', $evaluation->project_product_id)
                    ->latest('evaluated_at')->latest('id')->value('id') === $evaluation->id;
            })->map(function ($override) {
                $evaluation = $override->evaluation;
                $project = $evaluation->workflow->project;
                $snapshot = [
                    'override_id' => $override->id, 'evaluation_id' => $evaluation->id,
                    'gate_key' => $evaluation->gate_key, 'gate_result' => $evaluation->result,
                    'reason' => $override->reason, 'expected_resolution_date' => $override->expected_resolution_date?->format('Y-m-d'),
                    'approved_by' => $override->approved_by, 'approved_at' => $override->approved_at?->toISOString(),
                    'evaluation_fingerprint' => $evaluation->content_hash,
                ];
                return $this->source([
                    'project_id' => $project->id, 'project_workflow_id' => $evaluation->project_workflow_id,
                    'project_workflow_step_id' => $evaluation->workflow->steps()->where('step_key', $evaluation->gate_key)->where('project_product_id', $evaluation->project_product_id)->value('id'),
                    'project_product_id' => $evaluation->project_product_id, 'source_type' => 'gate_override',
                    'source_key' => 'gate_override:'.$override->id, 'category' => 'manager_override',
                    'severity' => $override->expected_resolution_date?->isPast() ? 'critical' : 'warning',
                    'title' => $project->project_number.' · '.$evaluation->gateLabel().' override',
                    'summary' => $override->reason, 'due_at' => $override->expected_resolution_date?->endOfDay(),
                    'source_snapshot' => $snapshot,
                ]);
            })->values();
    }

    private function approvalOverrideSources(): Collection
    {
        return ProjectApprovalOverride::query()->whereNull('revoked_at')
            ->with(['request.project', 'request.workflow'])->get()
            ->filter(fn ($override) => $override->request && $override->request->project
                && $override->request->status === 'open'
                && $override->version_number === $override->request->current_version_number
                && ! in_array($override->request->project->status, ['closed', 'cancelled'], true))
            ->map(function ($override) {
                $request = $override->request;
                $project = $request->project;
                return $this->source([
                    'project_id' => $project->id, 'project_workflow_id' => $request->project_workflow_id,
                    'project_workflow_step_id' => $request->workflow?->steps()->where('step_key', $request->approval_type.'_approved')->where('project_product_id', $request->project_product_id)->value('id'),
                    'project_product_id' => $request->project_product_id, 'source_type' => 'approval_override',
                    'source_key' => 'approval_override:'.$override->id, 'category' => 'approval_override', 'severity' => 'warning',
                    'title' => $project->project_number.' · '.$request->typeLabel().' override',
                    'summary' => $override->reason, 'due_at' => null,
                    'source_snapshot' => [
                        'override_id' => $override->id, 'approval_request_id' => $request->id,
                        'approval_type' => $request->approval_type, 'version_number' => $override->version_number,
                        'reason' => $override->reason, 'approved_by' => $override->approved_by,
                        'approved_at' => $override->approved_at?->toISOString(),
                    ],
                ]);
            })->values();
    }

    private function financialClosureSources(): Collection
    {
        return Project::query()->where('status', 'closed_with_outstanding_financials')
            ->with('latestFinancialClosure')->get()->filter(fn ($project) => $project->latestFinancialClosure)
            ->map(function ($project) {
                $closure = $project->latestFinancialClosure;
                $current = app(ProjectFinancialClosure::class)->snapshot($project);
                $snapshot = [
                    'closure_id' => $closure->id, 'closure_type' => $closure->closure_type,
                    'closure_content_hash' => $closure->content_hash,
                    'current_content_hash' => $current['content_hash'],
                    'snapshot_stale' => ! hash_equals($closure->content_hash, $current['content_hash']),
                    'totals' => $current['totals'],
                    'reason' => $closure->reason, 'expected_resolution_date' => $closure->expected_resolution_date?->format('Y-m-d'),
                    'approved_by' => $closure->approved_by, 'closed_at' => $closure->closed_at?->toISOString(),
                ];
                return $this->source([
                    'project_id' => $project->id, 'project_workflow_id' => $closure->project_workflow_id,
                    'project_workflow_step_id' => null, 'project_product_id' => null,
                    'source_type' => 'financial_closure', 'source_key' => 'financial_closure:'.$closure->id,
                    'category' => 'outstanding_financials',
                    'severity' => $closure->expected_resolution_date?->isPast() ? 'critical' : 'warning',
                    'title' => $project->project_number.' · Outstanding financial closure',
                    'summary' => $closure->reason ?: 'Financial balances remain active after project closure.',
                    'due_at' => $closure->expected_resolution_date?->endOfDay(), 'source_snapshot' => $snapshot,
                ]);
            })->values();
    }

    private function source(array $source): array
    {
        $source['source_fingerprint'] = hash('sha256', json_encode($source['source_snapshot'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $source;
    }

    private function upsert(array $source, ?int $actorId): array
    {
        $exception = ProjectException::query()->where('source_key', $source['source_key'])->lockForUpdate()->first();
        if (! $exception) {
            $source['status'] = ProjectException::STATUS_OPEN;
            $source['detected_at'] = now();
            $source['escalation_level'] = 0;
            $deadlines = OfficeSetting::workflow()['deadlines'];
            $reviewDays = match ($source['category']) {
                'unassigned' => (int) $deadlines['unassigned_review_days'],
                'blocked' => (int) $deadlines['blocked_review_days'],
                default => (int) $deadlines['standard_review_days'],
            };
            $source['due_at'] ??= now()->addDays(max(1, $reviewDays));
            $exception = ProjectException::query()->create($source);
            $this->event($exception, 'detected', $actorId, $exception->summary, null, ProjectException::STATUS_OPEN);
            return [$exception, true, false];
        }

        $wasResolved = $exception->status === ProjectException::STATUS_RESOLVED;
        $fingerprintChanged = ! hash_equals($exception->source_fingerprint, $source['source_fingerprint']);
        if (! $wasResolved && ! $fingerprintChanged) return [$exception, false, false];

        $from = $exception->status;
        $updates = $source + [
            'status' => ProjectException::STATUS_OPEN,
            'acknowledged_at' => null, 'acknowledged_by' => null, 'response_plan' => null,
            'next_review_at' => null, 'resolved_at' => null, 'resolved_by' => null, 'resolution_note' => null,
        ];
        if (! $source['due_at']) unset($updates['due_at']);
        $exception->update($updates);
        $this->event($exception, $wasResolved ? 'reopened' : 'source_changed', $actorId,
            $wasResolved ? 'The source condition became active again.' : 'Source facts changed; the previous response plan is stale.',
            $from, ProjectException::STATUS_OPEN);
        return [$exception->fresh(), false, true];
    }

    private function applyAutomaticEscalation(ProjectException $exception, ?int $actorId): void
    {
        if ($exception->status === ProjectException::STATUS_RESOLVED) return;
        $deadline = $exception->status === ProjectException::STATUS_ACKNOWLEDGED ? $exception->next_review_at : $exception->due_at;
        if (! $deadline || ! $deadline->isPast()) return;
        $target = $exception->status === ProjectException::STATUS_ACKNOWLEDGED && $exception->escalation_level === 1 ? 2 : max(1, $exception->escalation_level);
        if ($target === $exception->escalation_level && $exception->severity === 'critical') return;
        $exception->update(['severity' => 'critical', 'escalation_level' => $target, 'escalated_at' => now(), 'escalated_by' => $actorId]);
        $this->event($exception, 'auto_escalated', $actorId, 'The exception passed its response or review deadline.', $exception->status, $exception->status);
        $this->alert($exception, 'The exception passed its response or review deadline.');
    }

    private function event(ProjectException $exception, string $type, ?int $actorId, ?string $note, ?string $from, ?string $to): void
    {
        $exception->events()->create([
            'event_type' => $type, 'from_status' => $from, 'to_status' => $to,
            'severity' => $exception->severity, 'escalation_level' => $exception->escalation_level,
            'note' => $note, 'snapshot' => $exception->source_snapshot,
            'actor_id' => $actorId, 'occurred_at' => now(),
        ]);
    }

    private function alert(ProjectException $exception, string $reason): void
    {
        if (! Schema::hasTable('office_alerts')) return;
        OfficeAlert::query()->firstOrCreate([
            'fingerprint' => 'project.exception:'.$exception->id.':'.$exception->escalation_level,
        ], [
            'event_key' => 'project.exception.escalated', 'title' => $exception->title,
            'body' => $reason, 'severity' => OfficeAlert::SEVERITY_CRITICAL, 'requires_ack' => true,
            'team' => 'operations', 'action_url' => route('projects.exceptions.index', ['status' => 'active', 'focus' => $exception->id]),
            'action_label' => 'Review exception', 'subject_type' => ProjectException::class,
            'subject_id' => $exception->id, 'meta' => ['project_id' => $exception->project_id, 'level' => $exception->escalation_level],
        ]);
    }
}
