<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectLog;
use App\Models\ProjectTrackingUpdate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProjectTrackingController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $data = $this->validatedData($request);

        if (! empty($data['project_product_id']) && ! $project->products()->whereKey($data['project_product_id'])->exists()) {
            return back()->with('error', 'Selected product does not belong to this project.');
        }

        $data['project_id'] = $project->id;
        $data['created_by'] = Auth::id();
        $data['is_public'] = $request->has('is_public');
        $data['occurred_at'] = $data['occurred_at'] ?? now();

        $tracking = ProjectTrackingUpdate::create($data);
        $this->syncProjectProgress($project, $tracking);

        $this->writeLog($project, 'tracking_added', 'Tracking update added', $tracking->title, ['tracking_id' => $tracking->id], $tracking->is_public, $tracking->project_product_id);

        return back()->with('success', 'Tracking update added successfully.');
    }

    public function update(Request $request, ProjectTrackingUpdate $trackingUpdate): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['is_public'] = $request->has('is_public');
        $old = $trackingUpdate->toArray();
        $trackingUpdate->update($data);
        $this->syncProjectProgress($trackingUpdate->project, $trackingUpdate);

        $this->writeLog($trackingUpdate->project, 'tracking_updated', 'Tracking update updated', $trackingUpdate->title, ['old' => $old, 'new' => $trackingUpdate->fresh()->toArray()], $trackingUpdate->is_public, $trackingUpdate->project_product_id);

        return back()->with('success', 'Tracking update updated successfully.');
    }

    public function destroy(ProjectTrackingUpdate $trackingUpdate): RedirectResponse
    {
        $project = $trackingUpdate->project;
        $trackingUpdate->delete();
        $this->writeLog($project, 'tracking_deleted', 'Tracking update deleted', 'A tracking update was deleted.', null, false, null);

        return back()->with('success', 'Tracking update deleted successfully.');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'project_product_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(array_keys(ProjectTrackingUpdate::statusOptions()))],
            'progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'occurred_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'is_public' => ['nullable', 'boolean'],
        ]);
    }

    private function syncProjectProgress(Project $project, ProjectTrackingUpdate $tracking): void
    {
        if ($tracking->progress_percent === null) {
            return;
        }

        $data = ['progress_percent' => $tracking->progress_percent];
        if ($project->status === 'planned' && $tracking->progress_percent > 0) {
            $data['status'] = 'in_progress';
        }
        if ((int) $tracking->progress_percent >= 100) {
            $data['status'] = 'completed';
            $data['stage'] = 'closed';
            $data['completed_at'] = $project->completed_at ?: now();
        }

        $project->update($data);
    }

    private function writeLog(Project $project, string $eventType, string $title, ?string $description = null, ?array $newValues = null, bool $isPublic = false, ?int $projectProductId = null): void
    {
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
