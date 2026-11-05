<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectWorkflowStep;
use App\Services\ProjectAdminState;
use App\Services\ProjectWorkflowBypass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProjectAdminController extends Controller
{
    public function state(Request $request, Project $project, ProjectAdminState $state): RedirectResponse
    {
        abort_unless(Auth::user()?->canOverrideProjects(), 403);
        $data = $request->validate([
            'manual_status' => ['present', 'nullable', Rule::in(array_keys(Project::manualStatusOptions()))],
            'manual_progress_percent' => ['present', 'nullable', 'integer', 'min:0', 'max:100'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        $state->update($project, Auth::user(), $data['manual_status'] ?: null,
            $data['manual_progress_percent'] === null ? null : (int) $data['manual_progress_percent'], trim($data['reason']));

        return back()->with('success', 'Administrator project state saved. Workflow calculations and source records remain separate.');
    }

    public function bypass(Request $request, Project $project, ProjectWorkflowStep $step, ProjectWorkflowBypass $bypass): RedirectResponse
    {
        abort_unless(Auth::user()?->canOverrideProjects(), 403);
        abort_unless($step->workflow?->project_id === $project->id, 404);
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'expected_resolution_date' => ['nullable', 'date'],
            'approval_source' => ['nullable', 'integer', 'min:0'],
            'approval_version' => ['nullable', 'integer', 'min:1'],
        ]);
        $bypass->bypass($project, $step, Auth::user(), trim($data['reason']), $data['expected_resolution_date'] ?? null,
            isset($data['approval_source']) ? (int) $data['approval_source'] : null,
            isset($data['approval_version']) ? (int) $data['approval_version'] : null);

        return back()->with('warning', 'Administrator bypass recorded for this control. The underlying source remains unchanged.');
    }
}
