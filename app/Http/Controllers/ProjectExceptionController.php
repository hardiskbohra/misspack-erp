<?php

namespace App\Http\Controllers;

use App\Models\ProjectException;
use App\Models\User;
use App\Services\ProjectExceptionRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectExceptionController extends Controller
{
    public function index(Request $request, ProjectExceptionRegistry $registry): View
    {
        $registry->synchronize();
        $status = in_array($request->query('status'), ['active', 'open', 'acknowledged', 'resolved', 'all'], true) ? $request->query('status') : 'active';
        $severity = in_array($request->query('severity'), ['all', 'warning', 'critical'], true) ? $request->query('severity') : 'all';
        $category = (string) $request->query('category', 'all');
        $ownerId = $request->integer('owner_id') ?: null;
        $search = trim((string) $request->query('search'));

        $base = ProjectException::query()->with(['project.client', 'product', 'step', 'owner', 'acknowledger', 'events.actor']);
        $counts = [
            'active' => (clone $base)->whereIn('status', [ProjectException::STATUS_OPEN, ProjectException::STATUS_ACKNOWLEDGED])->count(),
            'critical' => (clone $base)->whereIn('status', [ProjectException::STATUS_OPEN, ProjectException::STATUS_ACKNOWLEDGED])->where('severity', 'critical')->count(),
            'unacknowledged' => (clone $base)->where('status', ProjectException::STATUS_OPEN)->count(),
            'escalated' => (clone $base)->whereIn('status', [ProjectException::STATUS_OPEN, ProjectException::STATUS_ACKNOWLEDGED])->where('escalation_level', '>', 0)->count(),
            'review_overdue' => (clone $base)->where('status', ProjectException::STATUS_ACKNOWLEDGED)->whereNotNull('next_review_at')->where('next_review_at', '<', now())->count(),
        ];

        $exceptions = $base
            ->when($status === 'active', fn ($query) => $query->whereIn('status', [ProjectException::STATUS_OPEN, ProjectException::STATUS_ACKNOWLEDGED]))
            ->when(in_array($status, ['open', 'acknowledged', 'resolved'], true), fn ($query) => $query->where('status', $status))
            ->when($severity !== 'all', fn ($query) => $query->where('severity', $severity))
            ->when($category !== 'all', fn ($query) => $query->where('category', $category))
            ->when($ownerId, fn ($query) => $query->where('owner_id', $ownerId))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('title', 'like', "%{$search}%")
                        ->orWhere('summary', 'like', "%{$search}%")
                        ->orWhereHas('project', fn ($project) => $project->where('project_number', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
                });
            })
            ->orderByRaw("case when status = 'resolved' then 1 else 0 end")
            ->orderByRaw("case when severity = 'critical' then 0 else 1 end")
            ->orderByDesc('escalation_level')
            ->orderByRaw('coalesce(next_review_at, due_at) asc')
            ->latest('detected_at')->paginate(25)->withQueryString();

        return view('projects.exceptions', [
            'exceptions' => $exceptions,
            'counts' => $counts,
            'status' => $status,
            'severity' => $severity,
            'category' => $category,
            'ownerId' => $ownerId,
            'search' => $search,
            'focus' => $request->integer('focus') ?: null,
            'owners' => User::query()->orderBy('name')->get(),
            'categoryOptions' => $this->categoryOptions(),
        ]);
    }

    public function refresh(ProjectExceptionRegistry $registry): RedirectResponse
    {
        $result = $registry->synchronize(Auth::id());

        return back()->with('success', "Exception sources refreshed: {$result['created']} detected, {$result['changed']} changed, {$result['resolved']} resolved.");
    }

    public function acknowledge(Request $request, ProjectException $exception, ProjectExceptionRegistry $registry): RedirectResponse
    {
        $data = $request->validate([
            'owner_id' => ['required', Rule::exists('users', 'id')],
            'response_plan' => ['required', 'string', 'min:10', 'max:2000'],
            'next_review_at' => ['required', 'date', 'after:now'],
        ]);
        $registry->acknowledge($exception, Auth::id(), (int) $data['owner_id'], $data['response_plan'], $data['next_review_at']);

        return back()->with('success', 'Exception acknowledged with an owner, response plan and next review.');
    }

    public function escalate(Request $request, ProjectException $exception, ProjectExceptionRegistry $registry): RedirectResponse
    {
        abort_unless(Auth::user()?->isAdmin(), 403);
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'next_review_at' => ['required', 'date', 'after:now'],
        ]);
        $registry->escalate($exception, Auth::id(), $data['reason'], $data['next_review_at']);

        return back()->with('warning', 'Exception escalated. Its underlying workflow or financial condition remains active.');
    }

    private function categoryOptions(): array
    {
        return [
            'all' => 'Every source', 'unassigned' => 'Coordinator unassigned',
            'overdue' => 'Workflow overdue', 'blocked' => 'Workflow blocked',
            'manager_override' => 'Manager overrides', 'approval_override' => 'Approval overrides',
            'outstanding_financials' => 'Outstanding financial closure',
        ];
    }
}
