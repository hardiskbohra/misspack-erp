<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectGateEvaluation;
use App\Models\ProjectGateOverride;
use App\Models\ProjectWorkflowStep;
use App\Services\ProjectWorkflowExecution;
use App\Services\ProjectWorkflowGates;
use App\Services\ProjectOperationalReadiness;
use App\Services\ProjectFeedbackClosure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProjectWorkflowController extends Controller
{
    public function start(Project $project, ProjectWorkflowExecution $execution): RedirectResponse
    {
        $execution->start($project, Auth::id());

        return redirect()->route('projects.show', ['project' => $project, 'tab' => 'workflow'])
            ->with('success', 'The standard execution workflow is ready.');
    }

    public function report(Request $request, Project $project, ProjectWorkflowExecution $execution): RedirectResponse
    {
        $workflow = $project->workflow;
        abort_unless($workflow, 404);
        $data = $request->validate([
            'reported_stage' => ['required', Rule::in(array_keys(ProjectWorkflowExecution::stageOptions()))],
            'reported_reason' => ['required', 'string', 'max:2000'],
            'advance_percentage_override' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commercial_sales_invoice_id' => ['nullable', Rule::exists('sales_invoices', 'id')->where(fn ($query) => $query->where('project_id', $project->id)->where('invoice_type', 'proforma'))],
        ]);
        $workflow->update($data + ['reported_by' => Auth::id(), 'reported_at' => now()]);
        $execution->recalculate($workflow);
        app(ProjectWorkflowGates::class)->evaluateAll($workflow->fresh(), Auth::id());
        app(ProjectOperationalReadiness::class)->evaluateAll($workflow->fresh(), Auth::id());
        app(ProjectFeedbackClosure::class)->sync($workflow->fresh(), Auth::id());

        return back()->with('success', 'Coordinator report and all source-controlled gates updated.');
    }

    public function evaluate(Project $project, ProjectWorkflowGates $gates): RedirectResponse
    {
        $workflow = $project->workflow;
        abort_unless($workflow, 404);
        $gates->evaluateAll($workflow, Auth::id());
        app(ProjectOperationalReadiness::class)->evaluateAll($workflow->fresh(), Auth::id());
        app(ProjectFeedbackClosure::class)->sync($workflow->fresh(), Auth::id());

        return back()->with('success', 'Financial, product and final-feedback gates evaluated against current source records.');
    }

    public function override(Request $request, Project $project, ProjectGateEvaluation $evaluation, \App\Services\ProjectWorkflowBypass $bypass): RedirectResponse
    {
        abort_unless(Auth::user()?->canOverrideProjects(), 403);
        abort_unless($evaluation->workflow?->project_id === $project->id, 404);
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'expected_resolution_date' => ['nullable', 'date'],
        ]);
        $bypass->overrideEvaluation($project, $evaluation, Auth::user(), trim($data['reason']), $data['expected_resolution_date'] ?? null);

        return back()->with('warning', 'Administrator bypass recorded. The failed condition remains visible and auditable.');
    }

    public function revokeOverride(Request $request, Project $project, ProjectGateOverride $override, \App\Services\ProjectWorkflowBypass $bypass): RedirectResponse
    {
        abort_unless(Auth::user()?->canOverrideProjects(), 403);
        abort_unless($override->evaluation?->workflow?->project_id === $project->id && ! $override->revoked_at, 404);
        $data = $request->validate(['revocation_reason' => ['required', 'string', 'min:5', 'max:2000']]);
        $bypass->revoke($project, $override, Auth::user(), trim($data['revocation_reason']));

        return back()->with('success', 'Administrator bypass revoked. Current source evaluations remain authoritative.');
    }

    public function updateStep(Request $request, Project $project, ProjectWorkflowStep $step, ProjectWorkflowExecution $execution): RedirectResponse
    {
        abort_unless($step->workflow?->project_id === $project->id, 404);
        abort_if(in_array($step->step_key, array_merge(ProjectWorkflowGates::AUTOMATED_GATES, ProjectOperationalReadiness::CONTROLLED_STEPS, \App\Services\ProjectApprovals::CONTROLLED_STEPS, \App\Services\ProjectFinancialClosure::CONTROLLED_STEPS, ProjectFeedbackClosure::CONTROLLED_STEPS), true), 422, 'This step is controlled by its current source records or client approval.');
        $current = \App\Services\ProjectWorkflowBypass::currentEvaluation($step);
        abort_if(\App\Services\ProjectWorkflowBypass::isSynthetic($current) && $current?->activeOverride, 422, 'Ask an administrator to revoke the active bypass before changing this control.');
        $data = $request->validate([
            'status' => ['required', Rule::in(array_values(array_diff(array_keys(ProjectWorkflowStep::statusOptions()), ['skipped', 'cancelled'])))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'blocked_reason' => ['nullable', 'required_if:status,blocked', 'string', 'max:2000'],
        ]);
        $execution->updateStep($step, $data['status'], Auth::id(), $data['notes'] ?? null, $data['blocked_reason'] ?? null);

        return back()->with('success', 'Workflow step updated.');
    }

}
