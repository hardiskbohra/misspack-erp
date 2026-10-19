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
            'reported_progress' => ['required', 'integer', 'min:0', 'max:100'],
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

    public function override(Request $request, Project $project, ProjectGateEvaluation $evaluation, ProjectWorkflowGates $gates, ProjectWorkflowExecution $execution): RedirectResponse
    {
        abort_unless(Auth::user()?->isAdmin(), 403);
        abort_unless($evaluation->workflow?->project_id === $project->id && $evaluation->result === 'blocked', 404);
        abort_if($evaluation->gate_key === \App\Services\ProjectFinancialClosure::GATE, 422, 'Use the financial closure manager-exception action so the closure decision and risk snapshot are recorded together.');
        abort_unless($this->isLatestEvaluation($evaluation), 422, 'Only the current gate evaluation can be overridden.');
        abort_if($evaluation->activeOverride()->exists(), 422, 'This gate already has an active override.');
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'expected_resolution_date' => ['nullable', 'date'],
        ]);
        $override = $evaluation->overrides()->create($data + [
            'risk_snapshot' => $evaluation->details,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);
        $evaluation->setRelation('activeOverride', $override);
        if (in_array($evaluation->gate_key, ProjectOperationalReadiness::CONTROLLED_STEPS, true)) {
            app(ProjectOperationalReadiness::class)->applyToStep($evaluation);
            app(ProjectOperationalReadiness::class)->evaluateAll($evaluation->workflow->fresh(), Auth::id());
        } elseif ($evaluation->gate_key === ProjectFeedbackClosure::GATE) {
            app(ProjectFeedbackClosure::class)->applyToStep($evaluation);
            $execution->recalculate($evaluation->workflow);
        } else {
            $gates->applyToStep($evaluation);
            $execution->recalculate($evaluation->workflow);
        }

        return back()->with('warning', 'Manager override recorded. The failed condition remains visible and auditable.');
    }

    public function revokeOverride(Request $request, Project $project, ProjectGateOverride $override, ProjectWorkflowGates $gates, ProjectWorkflowExecution $execution): RedirectResponse
    {
        abort_unless(Auth::user()?->isAdmin(), 403);
        $evaluation = $override->evaluation;
        abort_unless($evaluation?->workflow?->project_id === $project->id && ! $override->revoked_at, 404);
        $data = $request->validate(['revocation_reason' => ['required', 'string', 'min:5', 'max:2000']]);
        $override->update($data + ['revoked_by' => Auth::id(), 'revoked_at' => now()]);
        $evaluation->unsetRelation('activeOverride');
        if (in_array($evaluation->gate_key, ProjectOperationalReadiness::CONTROLLED_STEPS, true)) {
            app(ProjectOperationalReadiness::class)->applyToStep($evaluation);
            app(ProjectOperationalReadiness::class)->evaluateAll($evaluation->workflow->fresh(), Auth::id());
        } elseif ($evaluation->gate_key === ProjectFeedbackClosure::GATE) {
            app(ProjectFeedbackClosure::class)->applyToStep($evaluation);
            app(ProjectFeedbackClosure::class)->sync($evaluation->workflow->fresh(), Auth::id(), false);
        } else {
            $gates->applyToStep($evaluation);
            $execution->recalculate($evaluation->workflow);
        }

        return back()->with('success', 'Manager override revoked; the gate is blocked again.');
    }

    public function updateStep(Request $request, Project $project, ProjectWorkflowStep $step, ProjectWorkflowExecution $execution): RedirectResponse
    {
        abort_unless($step->workflow?->project_id === $project->id, 404);
        abort_if(in_array($step->step_key, array_merge(ProjectWorkflowGates::AUTOMATED_GATES, ProjectOperationalReadiness::CONTROLLED_STEPS, \App\Services\ProjectApprovals::CONTROLLED_STEPS, \App\Services\ProjectFinancialClosure::CONTROLLED_STEPS, ProjectFeedbackClosure::CONTROLLED_STEPS), true), 422, 'This step is controlled by its current source records or client approval.');
        $data = $request->validate([
            'status' => ['required', Rule::in(array_values(array_diff(array_keys(ProjectWorkflowStep::statusOptions()), ['skipped', 'cancelled'])))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'blocked_reason' => ['nullable', 'required_if:status,blocked', 'string', 'max:2000'],
        ]);
        $execution->updateStep($step, $data['status'], Auth::id(), $data['notes'] ?? null, $data['blocked_reason'] ?? null);

        return back()->with('success', 'Workflow step updated.');
    }

    private function isLatestEvaluation(ProjectGateEvaluation $evaluation): bool
    {
        return (int) $evaluation->workflow->evaluations()
            ->where('gate_key', $evaluation->gate_key)
            ->where('project_product_id', $evaluation->project_product_id)
            ->latest('evaluated_at')->latest('id')->value('id') === $evaluation->id;
    }
}
