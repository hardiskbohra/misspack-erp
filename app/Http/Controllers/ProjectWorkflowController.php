<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectWorkflowStep;
use App\Services\ProjectWorkflowExecution;
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

        return back()->with('success', 'Coordinator report and commercial workflow settings updated.');
    }

    public function updateStep(Request $request, Project $project, ProjectWorkflowStep $step, ProjectWorkflowExecution $execution): RedirectResponse
    {
        abort_unless($step->workflow?->project_id === $project->id, 404);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_values(array_diff(array_keys(ProjectWorkflowStep::statusOptions()), ['skipped', 'cancelled'])))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'blocked_reason' => ['nullable', 'required_if:status,blocked', 'string', 'max:2000'],
        ]);
        $execution->updateStep($step, $data['status'], Auth::id(), $data['notes'] ?? null, $data['blocked_reason'] ?? null);

        return back()->with('success', 'Workflow step updated.');
    }
}
