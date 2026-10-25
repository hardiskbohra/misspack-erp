<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectLog;
use App\Models\ProjectWorkflowStep;
use App\Models\User;
use App\Services\ProjectCoordinatorQueue;
use App\Services\ProjectWorkflowExecution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProjectCoordinatorQueueController extends Controller
{
    public function index(Request $request, ProjectCoordinatorQueue $queue): View
    {
        $scope = in_array($request->query('scope'), ['mine', 'team'], true) ? $request->query('scope') : 'mine';
        $attention = array_key_exists((string) $request->query('attention'), ProjectCoordinatorQueue::attentionOptions())
            ? (string) $request->query('attention') : 'all';
        $due = array_key_exists((string) $request->query('due'), ProjectCoordinatorQueue::dueOptions())
            ? (string) $request->query('due') : 'all';
        $stage = array_key_exists((string) $request->query('stage'), ProjectWorkflowExecution::stageOptions())
            ? (string) $request->query('stage') : 'all';
        $search = trim((string) $request->query('search'));
        $coordinatorId = $scope === 'mine' ? Auth::id() : ($request->integer('coordinator_id') ?: null);

        $baseItems = $queue->items($coordinatorId, $scope === 'mine');
        $stats = [
            'total' => $baseItems->count(),
            'overdue' => $baseItems->filter(fn (array $item) => $item['is_overdue'])->count(),
            'blocked' => $baseItems->filter(fn (array $item) => $item['is_blocked'])->count(),
            'waiting' => $baseItems->filter(fn (array $item) => $item['is_waiting'])->count(),
            'unassigned' => $baseItems->filter(fn (array $item) => $item['coordinator'] === null)->count(),
        ];

        $filtered = $baseItems
            ->filter(fn (array $item) => $queue->matchesAttention($item, $attention))
            ->filter(fn (array $item) => $queue->matchesDue($item, $due))
            ->when($stage !== 'all', fn ($items) => $items->filter(fn (array $item) => $item['step']->stage_key === $stage))
            ->when($search !== '', function ($items) use ($search) {
                $needle = mb_strtolower($search);
                return $items->filter(function (array $item) use ($needle) {
                    return str_contains(mb_strtolower(implode(' ', array_filter([
                        $item['project']->project_number,
                        $item['project']->name,
                        $item['project']->client?->company_name,
                        $item['product']?->product_name,
                        $item['step']->title,
                        $item['step']->blocked_reason,
                    ]))), $needle);
                });
            })->values();

        $perPage = 25;
        $page = max(1, $request->integer('page', 1));
        $items = new LengthAwarePaginator(
            $filtered->forPage($page, $perPage)->values(),
            $filtered->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
        $coordinators = User::query()->where('role', '!=', User::ROLE_EMPLOYEE)->orderBy('name')->get();
        $owners = User::query()->orderBy('name')->get();

        return view('projects.work-queue', [
            'items' => $items,
            'stats' => $stats,
            'scope' => $scope,
            'attention' => $attention,
            'due' => $due,
            'stage' => $stage,
            'search' => $search,
            'coordinatorId' => $coordinatorId,
            'coordinators' => $coordinators,
            'owners' => $owners,
            'attentionOptions' => ProjectCoordinatorQueue::attentionOptions(),
            'dueOptions' => ProjectCoordinatorQueue::dueOptions(),
            'stageOptions' => ProjectWorkflowExecution::stageOptions(),
        ]);
    }

    public function update(Request $request, Project $project, ProjectWorkflowStep $step): RedirectResponse
    {
        abort_unless($step->workflow?->project_id === $project->id, 404);
        $officeUser = Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', '!=', User::ROLE_EMPLOYEE));
        $data = $request->validate([
            'assigned_to' => ['nullable', $officeUser],
            'owner_id' => ['nullable', Rule::exists('users', 'id')],
            'planned_start_date' => ['nullable', 'date'],
            'planned_end_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        if (! empty($data['planned_start_date']) && ! empty($data['planned_end_date'])
            && $data['planned_end_date'] < $data['planned_start_date']) {
            throw ValidationException::withMessages(['planned_end_date' => 'Planned completion cannot be before planned start.']);
        }

        DB::transaction(function () use ($project, $step, $data) {
            $old = [
                'assigned_to' => $project->assigned_to,
                'owner_id' => $step->owner_id,
                'planned_start_date' => $step->planned_start_date?->format('Y-m-d'),
                'planned_end_date' => $step->planned_end_date?->format('Y-m-d'),
                'notes' => $step->notes,
            ];
            $project->update(['assigned_to' => $data['assigned_to'] ?? null]);
            $step->update([
                'owner_id' => $data['owner_id'] ?? null,
                'planned_start_date' => $data['planned_start_date'] ?? null,
                'planned_end_date' => $data['planned_end_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            ProjectLog::query()->create([
                'project_id' => $project->id,
                'project_product_id' => $step->project_product_id,
                'user_id' => Auth::id(),
                'actor_type' => 'internal',
                'actor_name' => Auth::user()?->name ?: 'Internal Team',
                'event_type' => 'workflow_work_planned',
                'title' => 'Coordinator queue item planned',
                'description' => $step->title,
                'old_values' => $old,
                'new_values' => $data,
                'is_public' => false,
            ]);
        });

        return back()->with('success', 'Coordinator, action owner and schedule updated.');
    }
}
