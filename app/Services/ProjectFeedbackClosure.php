<?php

namespace App\Services;

use App\Models\FeedbackAction;
use App\Models\FeedbackRequest;
use App\Models\OfficeAlert;
use App\Models\Project;
use App\Models\ProjectGateEvaluation;
use App\Models\ProjectWorkflow;
use App\Models\ProjectWorkflowStep;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Final closure is evidence, not a coordinator checkbox.
 *
 * A close-out ask is issued once commercial closure is complete. The final step
 * then follows the request, its immutable response and every resulting action.
 * An answer cannot close a project while any follow-up is still open.
 */
class ProjectFeedbackClosure
{
    public const GATE = 'feedback_closure';
    public const CONTROLLED_STEPS = [self::GATE];
    public const FIRST_REMINDER_DAYS = 3;
    public const SECOND_REMINDER_DAYS = 7;

    public function sync(ProjectWorkflow $workflow, ?int $userId = null, bool $issueIfEligible = true): ?ProjectGateEvaluation
    {
        if (! Schema::hasTable('feedback_requests')) return null;

        return DB::transaction(function () use ($workflow, $userId, $issueIfEligible) {
            $workflow->loadMissing(['project', 'steps', 'evaluations.activeOverride']);
            $step = $workflow->steps->first(fn ($row) => $row->step_key === self::GATE && $row->project_product_id === null);
            if (! $step) return null;

            $commercial = $workflow->steps->first(fn ($row) => $row->step_key === ProjectFinancialClosure::GATE && $row->project_product_id === null);
            $eligible = $commercial && in_array($commercial->status, ['completed', 'skipped'], true);
            if (! $eligible) {
                $step->update([
                    'status' => 'not_started',
                    'blocked_reason' => null,
                    'notes' => 'Final feedback begins after commercial closure.',
                    'completed_at' => null,
                    'completed_by' => null,
                ]);
                app(ProjectWorkflowExecution::class)->recalculate($workflow);
                return null;
            }

            $request = FeedbackRequest::query()
                ->where('project_id', $workflow->project_id)
                ->where('kind', FeedbackRequest::KIND_CLOSE_OUT)
                ->with(['response.actions'])
                ->latest('id')
                ->first();

            if (! $request && $issueIfEligible && $workflow->project?->client_id) {
                $request = $this->issue($workflow->project);
                $request->load(['response.actions']);
            }

            [$result, $summary, $details, $status] = $this->snapshot($workflow, $request);
            $evaluation = $this->record($workflow, $details, $result, $summary, $userId);
            $this->applyToStep($evaluation, $status);
            app(ProjectWorkflowExecution::class)->recalculate($workflow);

            return $evaluation;
        });
    }

    public function runScheduled(): array
    {
        if (! Schema::hasTable('project_workflows') || ! Schema::hasTable('feedback_requests')) {
            return ['projects' => 0, 'issued' => 0, 'reminded' => 0, 'alerts' => 0];
        }

        $stats = ['projects' => 0, 'issued' => 0, 'reminded' => 0, 'alerts' => 0];
        ProjectWorkflow::query()->where('status', '!=', 'cancelled')->with(['project', 'steps'])->chunkById(100, function ($workflows) use (&$stats) {
            foreach ($workflows as $workflow) {
                $commercial = $workflow->steps->first(fn ($row) => $row->step_key === ProjectFinancialClosure::GATE && $row->project_product_id === null);
                if (! $commercial || ! in_array($commercial->status, ['completed', 'skipped'], true)) continue;
                $stats['projects']++;

                $before = FeedbackRequest::query()->where('project_id', $workflow->project_id)->where('kind', FeedbackRequest::KIND_CLOSE_OUT)->count();
                $evaluation = $this->sync($workflow, null, true);
                $request = FeedbackRequest::query()->where('project_id', $workflow->project_id)->where('kind', FeedbackRequest::KIND_CLOSE_OUT)->latest('id')->first();
                if ($before === 0 && $request) $stats['issued']++;
                if ($request && $this->reminderDue($request)) {
                    $this->sendReminder($request);
                    $stats['reminded']++;
                    $evaluation = $this->sync($workflow->fresh(), null, false);
                }
                if ($evaluation && $evaluation->result === 'blocked') {
                    $alert = $this->alert($workflow->project, $evaluation);
                    if ($alert?->wasRecentlyCreated) $stats['alerts']++;
                }
                if ($request?->response) {
                    foreach ($request->response->actions()->open()->whereDate('due_on', '<', now()->toDateString())->get() as $action) {
                        $alert = $this->overdueActionAlert($workflow->project, $action);
                        if ($alert?->wasRecentlyCreated) $stats['alerts']++;
                    }
                }
            }
        });

        return $stats;
    }

    public function applyToStep(ProjectGateEvaluation $evaluation, ?string $sourceStatus = null): void
    {
        $evaluation->loadMissing('activeOverride');
        $step = ProjectWorkflowStep::query()
            ->where('project_workflow_id', $evaluation->project_workflow_id)
            ->whereNull('project_product_id')->where('step_key', self::GATE)->first();
        if (! $step) return;

        $overridden = (bool) $evaluation->activeOverride;
        $passed = $evaluation->result === 'passed';
        $status = $passed ? 'completed' : ($overridden ? 'skipped' : ($sourceStatus ?: 'blocked'));
        $step->update([
            'status' => $status,
            'blocked_reason' => $passed || $overridden ? null : $evaluation->summary,
            'notes' => $overridden ? 'Final closure continued under manager override #'.$evaluation->activeOverride->id.'. '.$evaluation->summary : $evaluation->summary,
            'actual_started_at' => $step->actual_started_at ?: now(),
            'completed_at' => $passed || $overridden ? ($step->completed_at ?: now()) : null,
            'completed_by' => $passed || $overridden ? ($evaluation->evaluated_by ?: Auth::id()) : null,
        ]);
    }

    private function issue(Project $project): FeedbackRequest
    {
        $request = FeedbackRequest::issue([
            'project_id' => $project->id,
            'client_id' => $project->client_id,
            'kind' => FeedbackRequest::KIND_CLOSE_OUT,
            'note' => 'Automatically issued after commercial closure.',
            'shared_via' => 'portal',
        ]);
        app(ClientPortalNotifier::class)->notifyClient(
            $project->client_id,
            'How did we do on '.$project->name.'?',
            'The project is commercially closed. Please share your close-out feedback; any concern will remain owned until resolved.',
            'info', FeedbackRequest::class, $request->id,
            Route::has('client-portal.feedback.show') ? route('client-portal.feedback.show', $request) : null
        );
        return $request;
    }

    private function snapshot(ProjectWorkflow $workflow, ?FeedbackRequest $request): array
    {
        if (! $request) {
            return ['blocked', 'A close-out feedback request could not be issued because the project has no client.', [
                'project_id' => $workflow->project_id, 'request_id' => null, 'request_state' => 'missing',
                'response_id' => null, 'open_action_ids' => [], 'resolved_action_ids' => [],
            ], 'blocked'];
        }

        $response = $request->response;
        $actions = $response?->actions ?? collect();
        $open = $actions->whereIn('status', [FeedbackAction::STATUS_OPEN, FeedbackAction::STATUS_IN_PROGRESS]);
        $resolved = $actions->whereIn('status', [FeedbackAction::STATUS_RESOLVED, FeedbackAction::STATUS_DISMISSED]);
        $details = [
            'project_id' => $workflow->project_id,
            'request_id' => $request->id,
            'request_state' => $request->state(),
            'shared_via' => $request->shared_via,
            'request_expires_at' => $request->expires_at?->toISOString(),
            'reminder_count' => (int) $request->reminder_count,
            'last_reminded_at' => $request->last_reminded_at?->toISOString(),
            'response_id' => $response?->id,
            'response_band' => $response?->band(),
            'response_submitted_at' => $response?->submitted_at?->toISOString(),
            'open_action_ids' => $open->pluck('id')->sort()->values()->all(),
            'resolved_action_ids' => $resolved->pluck('id')->sort()->values()->all(),
        ];

        if (! $response) {
            $state = $request->state();
            if (in_array($state, [FeedbackRequest::STATE_LIVE, FeedbackRequest::STATE_OPENED], true)) {
                return ['blocked', 'Waiting for close-out feedback ('.$request->reminder_count.'/'.FeedbackRequest::MAX_REMINDERS.' reminders sent).', $details, 'waiting_client'];
            }
            return ['blocked', 'Close-out feedback is '.$state.'. Issue a fresh request or use a documented manager override.', $details, 'blocked'];
        }
        if ($open->isNotEmpty()) {
            return ['blocked', $open->count().' feedback follow-up '.($open->count() === 1 ? 'action remains' : 'actions remain').' unresolved.', $details, 'blocked'];
        }

        return ['passed', 'Close-out feedback is answered and every required follow-up is resolved.', $details, 'completed'];
    }

    private function record(ProjectWorkflow $workflow, array $details, string $result, string $summary, ?int $userId): ProjectGateEvaluation
    {
        $hash = hash('sha256', json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $latest = $workflow->evaluations()->where('gate_key', self::GATE)->latest('evaluated_at')->latest('id')->first();
        if ($latest && hash_equals((string) $latest->content_hash, $hash)) return $latest;
        return $workflow->evaluations()->create([
            'gate_key' => self::GATE, 'result' => $result, 'content_hash' => $hash,
            'summary' => $summary, 'details' => $details, 'evaluated_by' => $userId, 'evaluated_at' => now(),
        ]);
    }

    private function reminderDue(FeedbackRequest $request): bool
    {
        if (! $request->canRemind()) return false;
        $days = $request->reminder_count === 0 ? self::FIRST_REMINDER_DAYS : self::SECOND_REMINDER_DAYS;
        $from = $request->last_reminded_at ?: $request->created_at;
        return $from && $from->copy()->addDays($days)->isPast();
    }

    private function sendReminder(FeedbackRequest $request): void
    {
        $request->increment('reminder_count');
        $request->forceFill(['last_reminded_at' => now()])->save();
        app(ClientPortalNotifier::class)->notifyClient(
            $request->client_id, 'Reminder: close-out feedback',
            'Please share your feedback for '.$request->project?->name.'. This is reminder '.$request->reminder_count.' of '.FeedbackRequest::MAX_REMINDERS.'.',
            'warning', FeedbackRequest::class, $request->id,
            Route::has('client-portal.feedback.show') ? route('client-portal.feedback.show', $request) : null
        );
    }

    private function overdueActionAlert(Project $project, FeedbackAction $action): ?OfficeAlert
    {
        if (! Schema::hasTable('office_alerts')) return null;
        return app(OfficeBriefing::class)->workflowNotice([
            'event_key' => 'workflow.feedback_action_overdue',
            'fingerprint' => 'workflow.feedback_action_overdue:'.$action->id.':'.$action->due_on?->format('Y-m-d'),
            'title' => ($project->project_number ?: $project->name).' · feedback action overdue',
            'body' => 'Client feedback follow-up #'.$action->id.' was due '.$action->due_on?->format('d M Y').'. Resolve the concern before final closure.',
            'severity' => OfficeAlert::SEVERITY_CRITICAL,
            'requires_ack' => true,
            'team' => 'operations',
            'action_url' => Route::has('feedback.show') ? route('feedback.show', $action->response->request) : null,
            'action_label' => 'Open feedback action',
            'subject_type' => FeedbackAction::class,
            'subject_id' => $action->id,
            'meta' => ['owner_id' => $action->owner_id, 'coordinator_id' => $project->assigned_to],
        ]);
    }

    private function alert(Project $project, ProjectGateEvaluation $evaluation): ?OfficeAlert
    {
        if (! Schema::hasTable('office_alerts')) return null;
        return app(OfficeBriefing::class)->workflowNotice([
            'event_key' => 'workflow.feedback_closure',
            'fingerprint' => 'workflow.feedback_closure:'.$project->id.':'.$evaluation->content_hash,
            'title' => ($project->project_number ?: $project->name).' needs final closure attention',
            'body' => $evaluation->summary,
            'severity' => OfficeAlert::SEVERITY_ATTENTION,
            'requires_ack' => true,
            'team' => 'operations',
            'action_url' => Route::has('projects.show') ? route('projects.show', [$project, 'tab' => 'workflow']) : null,
            'action_label' => 'Open workflow',
            'subject_type' => Project::class,
            'subject_id' => $project->id,
            'meta' => ['coordinator_id' => $project->assigned_to, 'evaluation_id' => $evaluation->id],
        ]);
    }
}
