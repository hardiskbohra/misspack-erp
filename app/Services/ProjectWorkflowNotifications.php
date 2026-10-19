<?php

namespace App\Services;

use App\Models\OfficeAlert;
use App\Models\ProjectGateEvaluation;
use App\Models\ProjectGateOverride;
use App\Models\ProjectWorkflow;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/** Scheduled push layer for work that should not depend on opening the centre. */
class ProjectWorkflowNotifications
{
    public function run(): int
    {
        if (! Schema::hasTable('office_alerts') || ! Schema::hasTable('project_workflows')) return 0;
        $raised = 0;

        foreach (app(ProjectCoordinatorQueue::class)->items() as $item) {
            $workflow = $item['workflow'];
            $project = $workflow->project;
            $step = $item['step'];
            $overdue = (bool) $item['is_overdue'];
            $alert = app(OfficeBriefing::class)->workflowNotice([
                'event_key' => $overdue ? 'workflow.action_overdue' : 'workflow.current_action',
                'fingerprint' => ($overdue ? 'workflow.action_overdue:' : 'workflow.current_action:').$step->id.($overdue ? ':'.$item['due_date']?->format('Y-m-d') : ''),
                'title' => ($project->project_number ?: $project->name).' · '.$step->title,
                'body' => $overdue
                    ? 'This coordinator action is overdue since '.$item['due_date']?->format('d M Y').'. '.($step->blocked_reason ?: $step->notes ?: 'Open the workflow and record the next source action.')
                    : ($step->blocked_reason ?: $step->notes ?: 'This is the current source-controlled action for the project.'),
                'severity' => $overdue ? OfficeAlert::SEVERITY_CRITICAL : OfficeAlert::SEVERITY_ATTENTION,
                'requires_ack' => true,
                'team' => 'operations',
                'action_url' => Route::has('projects.show') ? route('projects.show', [$project, 'tab' => 'workflow']) : null,
                'action_label' => 'Open current action',
                'subject_type' => get_class($project),
                'subject_id' => $project->id,
                'meta' => ['coordinator_id' => $project->assigned_to, 'workflow_step_id' => $step->id],
            ]);
            if ($alert?->wasRecentlyCreated) $raised++;
        }

        if (Schema::hasTable('project_gate_overrides')) {
            ProjectGateOverride::query()->whereNull('revoked_at')->with('evaluation.workflow.project')->chunkById(100, function ($overrides) use (&$raised) {
                foreach ($overrides as $override) {
                    $evaluation = $override->evaluation;
                    $workflow = $evaluation?->workflow;
                    $project = $workflow?->project;
                    if (! $workflow || ! $project) continue;

                    $latest = ProjectGateEvaluation::query()
                        ->where('project_workflow_id', $workflow->id)
                        ->where('gate_key', $evaluation->gate_key)
                        ->where('project_product_id', $evaluation->project_product_id)
                        ->latest('evaluated_at')->latest('id')->first();
                    $sourceResolved = $latest && $latest->id !== $evaluation->id && $latest->result === 'passed';
                    $resolutionOverdue = $override->expected_resolution_date?->isPast() ?? false;
                    if (! $sourceResolved && ! $resolutionOverdue) continue;

                    $kind = $sourceResolved ? 'source_resolved' : 'resolution_overdue';
                    $alert = app(OfficeBriefing::class)->workflowNotice([
                        'event_key' => 'workflow.override_'.$kind,
                        'fingerprint' => 'workflow.override_'.$kind.':'.$override->id.':'.($sourceResolved ? $latest->content_hash : $override->expected_resolution_date?->format('Y-m-d')),
                        'title' => ($project->project_number ?: $project->name).' · manager override '.($sourceResolved ? 'can be resolved' : 'is overdue'),
                        'body' => $sourceResolved
                            ? 'The source condition now passes. Review and revoke the old override so its resolution is explicit.'
                            : 'The expected resolution date has passed. Review the accepted risk and record the current resolution.',
                        'severity' => $resolutionOverdue ? OfficeAlert::SEVERITY_CRITICAL : OfficeAlert::SEVERITY_ATTENTION,
                        'requires_ack' => true,
                        'team' => 'operations',
                        'action_url' => Route::has('projects.show') ? route('projects.show', [$project, 'tab' => 'workflow']) : null,
                        'action_label' => 'Review override',
                        'subject_type' => get_class($project),
                        'subject_id' => $project->id,
                        'meta' => ['override_id' => $override->id, 'coordinator_id' => $project->assigned_to],
                    ]);
                    if ($alert?->wasRecentlyCreated) $raised++;
                }
            });
        }

        return $raised;
    }
}
