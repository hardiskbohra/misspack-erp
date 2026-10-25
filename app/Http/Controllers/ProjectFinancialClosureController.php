<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ProjectFinancialClosure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectFinancialClosureController extends Controller
{
    public function evaluate(Project $project, ProjectFinancialClosure $closure): RedirectResponse
    {
        abort_unless($project->workflow, 404);
        $closure->evaluate($project->workflow, Auth::id());

        return back()->with('success', 'Financial closure was reviewed against current source documents and ledger allocations.');
    }

    public function close(Project $project, ProjectFinancialClosure $closure): RedirectResponse
    {
        $closure->close($project, Auth::id());

        return back()->with('success', 'Project closed as financially complete. The signed source snapshot is preserved.');
    }

    public function closeOutstanding(Request $request, Project $project, ProjectFinancialClosure $closure): RedirectResponse
    {
        abort_unless(Auth::user()?->isAdmin(), 403);
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
            'risk_acknowledgement' => ['accepted'],
            'expected_resolution_date' => ['required', 'date', 'after_or_equal:today'],
        ]);
        $closure->close($project, Auth::id(), true, $data['reason'], $data['expected_resolution_date']);

        return back()->with('warning', 'Manager exception recorded. The project is closed with outstanding financials; every underlying balance remains active.');
    }

    public function reopen(Request $request, Project $project, ProjectFinancialClosure $closure): RedirectResponse
    {
        abort_unless(Auth::user()?->isAdmin(), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:2000']]);
        $closure->reopen($project, Auth::id(), $data['reason']);

        return back()->with('success', 'Project reopened. Previous closure decisions remain in immutable history.');
    }
}
