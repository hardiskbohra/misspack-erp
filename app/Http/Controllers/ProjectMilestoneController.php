<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectLog;
use App\Models\ProjectMilestone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProjectMilestoneController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $data = $this->validatedData($request, $project);
        $data['project_id'] = $project->id;
        $data['created_by'] = Auth::id();
        $data = $this->normalizeData($data, $request);

        $milestone = ProjectMilestone::create($data);
        $this->writeLog($project, 'milestone_added', 'Milestone added', $milestone->title.' milestone was added.', ['milestone_id' => $milestone->id], $milestone->is_public, $milestone->project_product_id);
        $this->syncProjectProgress($project);

        return back()->with('success', 'Project milestone added successfully.');
    }

    public function update(Request $request, ProjectMilestone $milestone): RedirectResponse
    {
        $data = $this->validatedData($request, $milestone->project, $milestone);
        $old = $milestone->toArray();
        $data = $this->normalizeData($data, $request, $milestone);

        $milestone->update($data);
        $this->writeLog($milestone->project, 'milestone_updated', 'Milestone updated', $milestone->title.' milestone was updated.', ['old' => $old, 'new' => $milestone->fresh()->toArray()], $milestone->is_public, $milestone->project_product_id);
        $this->syncProjectProgress($milestone->project);

        return back()->with('success', 'Project milestone updated successfully.');
    }

    public function destroy(ProjectMilestone $milestone): RedirectResponse
    {
        $project = $milestone->project;
        $title = $milestone->title;
        $milestone->delete();

        $this->writeLog($project, 'milestone_deleted', 'Milestone deleted', $title.' milestone was deleted.', null, false, null);
        $this->syncProjectProgress($project);

        return back()->with('success', 'Project milestone deleted successfully.');
    }

    public function generateDefaults(Project $project): RedirectResponse
    {
        $created = 0;

        DB::transaction(function () use ($project, &$created) {
            $products = $project->products()->get();
            $targets = $products->count() ? $products : collect([null]);

            foreach ($targets as $projectProduct) {
                foreach (ProjectMilestone::defaultMilestones() as $item) {
                    $exists = ProjectMilestone::query()
                        ->where('project_id', $project->id)
                        ->where('project_product_id', $projectProduct ? $projectProduct->id : null)
                        ->where('milestone_key', $item['key'])
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    ProjectMilestone::create([
                        'project_id' => $project->id,
                        'project_product_id' => $projectProduct ? $projectProduct->id : null,
                        'milestone_key' => $item['key'],
                        'title' => $item['title'],
                        'status' => 'not_started',
                        'progress_percent' => 0,
                        'is_public' => (bool) $item['public'],
                        'is_required' => true,
                        'sort_order' => (int) $item['sort'],
                        'created_by' => Auth::id(),
                    ]);
                    $created++;
                }
            }
        });

        $this->writeLog($project, 'milestones_generated', 'Default milestones generated', $created.' milestone(s) generated from default template.', ['created' => $created], false, null);
        $this->syncProjectProgress($project);

        return back()->with('success', $created.' default milestone(s) generated successfully.');
    }

    private function validatedData(Request $request, Project $project, ?ProjectMilestone $milestone = null): array
    {
        $data = $request->validate([
            'project_product_id' => ['nullable', 'integer'],
            'milestone_key' => ['required', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(array_keys(ProjectMilestone::statusOptions()))],
            'progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'planned_start_date' => ['nullable', 'date'],
            'planned_end_date' => ['nullable', 'date'],
            'actual_start_date' => ['nullable', 'date'],
            'actual_end_date' => ['nullable', 'date'],
            'owner_id' => ['nullable', 'integer'],
            'is_public' => ['nullable', 'boolean'],
            'is_required' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
            'client_note' => ['nullable', 'string'],
            'blocked_reason' => ['nullable', 'string'],
        ]);

        if (! empty($data['project_product_id']) && ! $project->products()->whereKey($data['project_product_id'])->exists()) {
            abort(422, 'Selected product does not belong to this project.');
        }

        return $data;
    }

    private function normalizeData(array $data, Request $request, ?ProjectMilestone $milestone = null): array
    {
        $data['project_product_id'] = $data['project_product_id'] ?? null;
        $data['progress_percent'] = (int) ($data['progress_percent'] ?? 0);
        $data['is_public'] = $request->has('is_public');
        $data['is_required'] = $request->has('is_required');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        if ($data['status'] === 'completed') {
            $data['progress_percent'] = 100;
            $data['completed_at'] = $milestone && $milestone->completed_at ? $milestone->completed_at : now();
            $data['actual_end_date'] = $data['actual_end_date'] ?? now()->toDateString();
        } elseif ($data['status'] === 'skipped') {
            $data['completed_at'] = null;
        } else {
            $data['completed_at'] = null;
        }

        if ($data['status'] === 'not_started') {
            $data['progress_percent'] = 0;
        }

        if ($data['status'] === 'in_progress' && empty($data['actual_start_date'])) {
            $data['actual_start_date'] = $milestone && $milestone->actual_start_date ? $milestone->actual_start_date->toDateString() : now()->toDateString();
        }

        return $data;
    }

    private function syncProjectProgress(Project $project): void
    {
        $milestones = $project->milestones()->where('is_required', true)->get();
        if ($milestones->isEmpty()) {
            return;
        }

        $average = (int) round($milestones->avg('progress_percent'));
        $project->update(['progress_percent' => max(0, min(100, $average))]);
    }

    private function writeLog(Project $project, string $eventType, string $title, ?string $description = null, ?array $newValues = null, bool $isPublic = false, ?int $projectProductId = null): void
    {
        if (! class_exists(ProjectLog::class)) {
            return;
        }

        ProjectLog::create([
            'project_id' => $project->id,
            'project_product_id' => $projectProductId,
            'user_id' => Auth::id(),
            'actor_type' => 'internal',
            'actor_name' => Auth::user()->name ?? 'Internal Team',
            'event_type' => $eventType,
            'title' => $title,
            'description' => $description,
            'new_values' => $newValues,
            'is_public' => $isPublic,
        ]);
    }
}
