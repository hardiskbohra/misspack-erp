<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function index(Request $request): View
    {
        $scope = $request->query('scope', 'all');       // all | mine
        $status = $request->query('status', 'all');     // all | backlog | new_request | in_progress | completed
        $priority = $request->query('priority', 'all'); // all | low | medium | high | urgent
        $assigneeId = $request->query('assignee_id');
        $category = $request->query('category');
        $search = $request->query('search');
        $showCompleted = $request->query('show_completed', '1') !== '0';

        $baseQuery = Task::query()
            ->with(['assignee', 'creator'])
            ->when($scope === 'mine', function ($q) {
                $q->where('assignee_id', Auth::id());
            });

        $countsQuery = clone $baseQuery;
        $counts = [
            'all' => (clone $countsQuery)->count(),
            'backlog' => (clone $countsQuery)->where('status', Task::STATUS_BACKLOG)->count(),
            'new_request' => (clone $countsQuery)->where('status', Task::STATUS_NEW_REQUEST)->count(),
            'in_progress' => (clone $countsQuery)->where('status', Task::STATUS_IN_PROGRESS)->count(),
            'completed' => (clone $countsQuery)->completed()->count(),
            'pending' => (clone $countsQuery)->incomplete()->count(),
            'mine' => Task::query()->where('assignee_id', Auth::id())->count(),
        ];

        $query = $baseQuery
            ->search($search)
            ->when(in_array($status, array_keys(Task::statusOptions()), true), function ($q) use ($status) {
                $q->where('status', $status);
            })
            ->when(! $showCompleted && $status !== Task::STATUS_COMPLETED, function ($q) {
                $q->incomplete();
            })
            ->when(in_array($priority, array_keys(Task::priorityOptions()), true), function ($q) use ($priority) {
                $q->where('priority', $priority);
            })
            ->when(in_array($category, array_keys(Task::categoryOptions()), true), function ($q) use ($category) {
                $q->where('category', $category);
            })
            ->when($assigneeId, function ($q) use ($assigneeId) {
                $q->where('assignee_id', $assigneeId);
            });

        $tasks = $query
            ->orderBy('sort_order')
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->latest('id')
            ->get();

        $columns = [];
        foreach (Task::statusOptions() as $key => $label) {
            if (! $showCompleted && $key === Task::STATUS_COMPLETED && $status !== Task::STATUS_COMPLETED) {
                continue;
            }

            $columns[$key] = [
                'label' => $label,
                'tasks' => $tasks->where('status', $key)->values(),
                'count' => $counts[$key] ?? 0,
            ];
        }

        $users = User::query()->orderBy('name')->orderBy('id')->get();
        $statusOptions = Task::statusOptions();
        $priorityOptions = Task::priorityOptions();
        $categoryOptions = Task::categoryOptions();

        return view('tasks.index', compact(
            'tasks',
            'columns',
            'users',
            'counts',
            'scope',
            'category',
            'status',
            'priority',
            'assigneeId',
            'search',
            'showCompleted',
            'statusOptions',
            'priorityOptions',
            'categoryOptions'
        ));
    }

    public function create(): View
    {
        $task = new Task([
            'priority' => Task::PRIORITY_MEDIUM,
            'status' => Task::STATUS_NEW_REQUEST,
            'category' => 'General',
            'due_date' => now()->toDateString(),
        ]);

        $users = User::query()->orderBy('name')->orderBy('id')->get();
        $statusOptions = Task::statusOptions();
        $priorityOptions = Task::priorityOptions();
        $categoryOptions = Task::categoryOptions();

        return view('tasks.form', compact('task', 'users', 'statusOptions', 'priorityOptions', 'categoryOptions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['created_by'] = Auth::id();
        $data['status'] = $data['status'] ?? Task::STATUS_NEW_REQUEST;
        $data['sort_order'] = $this->nextSortOrder($data['status']);

        if (($data['status'] ?? Task::STATUS_NEW_REQUEST) === Task::STATUS_COMPLETED) {
            $data['completed_at'] = now();
        }

        Task::create($data);

        return redirect()
            ->route('tasks.index')
            ->with('success', 'Task created successfully.');
    }

    public function edit(Task $task): View
    {
        $users = User::query()->orderBy('name')->orderBy('id')->get();
        $statusOptions = Task::statusOptions();
        $priorityOptions = Task::priorityOptions();
        $categoryOptions = Task::categoryOptions();

        return view('tasks.form', compact('task', 'users', 'statusOptions', 'priorityOptions', 'categoryOptions'));
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $data = $this->validatedData($request);

        if (($data['status'] ?? Task::STATUS_NEW_REQUEST) === Task::STATUS_COMPLETED && ! $task->completed_at) {
            $data['completed_at'] = now();
        }

        if (($data['status'] ?? Task::STATUS_NEW_REQUEST) !== Task::STATUS_COMPLETED) {
            $data['completed_at'] = null;
        }

        $task->update($data);

        return redirect()
            ->route('tasks.index')
            ->with('success', 'Task updated successfully.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $task->delete();

        return redirect()
            ->route('tasks.index')
            ->with('success', 'Task deleted successfully.');
    }

    public function toggle(Task $task): RedirectResponse
    {
        $isCompleting = ! $task->isCompleted();

        $task->update([
            'status' => $isCompleting ? Task::STATUS_COMPLETED : Task::STATUS_NEW_REQUEST,
            'completed_at' => $isCompleting ? now() : null,
        ]);

        return back()->with('success', $isCompleting ? 'Task marked completed.' : 'Task moved to New Request.');
    }

    public function updateStatus(Request $request, Task $task)
    {
        $data = $request->validate([
            'status' => ['required', 'in:backlog,new_request,in_progress,completed'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $task->update([
            'status' => $data['status'],
            'sort_order' => $data['sort_order'] ?? $this->nextSortOrder($data['status']),
            'completed_at' => $data['status'] === Task::STATUS_COMPLETED ? ($task->completed_at ?: now()) : null,
        ]);

        if ($request->expectsJson()) {
            return new JsonResponse([
                'success' => true,
                'message' => 'Task board updated.',
                'task' => [
                    'id' => $task->id,
                    'status' => $task->status,
                    'sort_order' => $task->sort_order,
                ],
            ]);
        }

        return back()->with('success', 'Task moved successfully.');
    }

    public function markAll(Request $request): RedirectResponse
    {
        $scope = $request->input('scope', 'all');

        Task::query()
            ->when($scope === 'mine', function ($q) {
                $q->where('assignee_id', Auth::id());
            })
            ->incomplete()
            ->update([
                'status' => Task::STATUS_COMPLETED,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

        return back()->with('success', 'All open tasks marked as completed.');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'status' => ['nullable', 'in:backlog,new_request,in_progress,completed'],
            'category' => ['nullable', 'string', 'max:255'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'assignee_id' => ['nullable', 'exists:users,id'],
        ]);
    }

    private function nextSortOrder(string $status): int
    {
        return ((int) Task::query()->where('status', $status)->max('sort_order')) + 1;
    }
}
