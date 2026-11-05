<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** An administrator's published project state, separate from calculated evidence. */
class ProjectAdminState
{
    public function update(Project $project, User $actor, ?string $status, ?int $progress, string $reason): Project
    {
        abort_unless($actor->canOverrideProjects(), 403);
        if ($status !== null && ! array_key_exists($status, Project::manualStatusOptions())) {
            throw ValidationException::withMessages(['manual_status' => 'Choose a valid ordinary project status; use the closure controls to close.']);
        }
        if ($progress !== null && ($progress < 0 || $progress > 100)) {
            throw ValidationException::withMessages(['manual_progress_percent' => 'Progress must be between 0 and 100.']);
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages(['reason' => 'Explain the decision in 10 to 2000 characters.']);
        }
        if (! Schema::hasColumn('projects', 'manual_status')) {
            throw ValidationException::withMessages(['manual_status' => 'Run the project administrator controls migration before setting manual state.']);
        }

        return DB::transaction(function () use ($project, $actor, $status, $progress, $reason) {
            $locked = Project::query()->lockForUpdate()->findOrFail($project->id);
            if (in_array($locked->status, Project::closureStatuses(), true) && $status !== null && $status !== $locked->manual_status) {
                throw ValidationException::withMessages(['manual_status' => 'Reopen the closed project before setting a different ordinary status.']);
            }
            $before = $locked->only(['status', 'stage', 'progress_percent', 'manual_status', 'manual_progress_percent']);
            $oldStatus = $locked->manual_status;
            $oldProgress = $locked->manual_progress_percent;
            $workflow = $locked->workflow()->first();

            if (! $workflow && $oldStatus === null && $status !== null) {
                $locked->manual_status_previous = $locked->status;
            }
            if (! $workflow && $oldProgress === null && $progress !== null) {
                $locked->manual_progress_previous = $locked->progress_percent;
            }
            $locked->manual_status = $status;
            $locked->manual_progress_percent = $progress;

            if ($workflow) {
                // A manually cancelled project must be able to return to the
                // workflow; legacy cancellation without a manual override stays terminal.
                if (! in_array($locked->status, Project::closureStatuses(), true) && $oldStatus === 'cancelled' && $status !== 'cancelled') {
                    $locked->status = 'in_progress';
                }
                if ($status === null) $locked->manual_status_previous = null;
                if ($progress === null) $locked->manual_progress_previous = null;
                $locked->save();
                app(ProjectWorkflowExecution::class)->recalculate($workflow);
                $locked->refresh();
            } else {
                if ($status !== null) {
                    $locked->status = $status;
                } elseif ($oldStatus !== null) {
                    $locked->status = $locked->manual_status_previous ?? 'planned';
                    $locked->manual_status_previous = null;
                }
                if ($progress !== null) {
                    $locked->progress_percent = $progress;
                } elseif ($oldProgress !== null) {
                    $locked->progress_percent = $locked->manual_progress_previous ?? 0;
                    $locked->manual_progress_previous = null;
                }
                if (! in_array($locked->status, Project::closureStatuses(), true)) {
                    $locked->completed_at = $locked->status === 'completed' ? ($locked->completed_at ?: now()) : null;
                }
                $locked->save();
            }

            ProjectLog::query()->create([
                'project_id' => $locked->id, 'user_id' => $actor->id,
                'actor_type' => 'internal', 'actor_name' => $actor->name,
                'event_type' => 'admin_state_changed', 'title' => 'Administrator changed project state',
                'description' => $reason, 'old_values' => $before,
                'new_values' => $locked->only(['status', 'stage', 'progress_percent', 'manual_status', 'manual_progress_percent']),
                'is_public' => false,
            ]);

            return $locked;
        });
    }
}
