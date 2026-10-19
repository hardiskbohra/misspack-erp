<?php

namespace App\Services;

use App\Models\ProjectWorkflow;
use Illuminate\Support\Collection;

/**
 * Turns the standard execution path into one useful next action per active lane.
 *
 * Project controls remain sequential. Product lanes advance independently, but
 * neither a product nor a project control is surfaced before an earlier shared
 * prerequisite. This avoids a queue full of future gates which are correctly
 * blocked but cannot be acted on yet.
 */
class ProjectCoordinatorQueue
{
    private const TERMINAL = ['completed', 'skipped', 'cancelled'];

    public function items(?int $coordinatorId = null, bool $mineOnly = false): Collection
    {
        $workflows = ProjectWorkflow::query()
            ->with([
                'project.client', 'project.assignedUser',
                'steps.product', 'steps.owner', 'approvals.currentVersion',
            ])
            ->where('status', '!=', 'completed')
            ->whereHas('project', function ($query) use ($coordinatorId, $mineOnly) {
                /* Financially closed projects stay here until their final-feedback
                   step is complete; workflow completion, not project status,
                   removes them from the coordinator's queue. */
                $query->where('status', '!=', 'cancelled');
                if ($mineOnly || $coordinatorId) {
                    $query->where('assigned_to', $coordinatorId);
                }
            })
            ->orderBy('id')
            ->get();

        return $workflows->flatMap(fn (ProjectWorkflow $workflow) => $this->currentForWorkflow($workflow))
            ->sortBy(fn (array $item) => sprintf(
                '%02d-%s-%05d-%05d',
                $item['attention_rank'],
                $item['due_date']?->format('Y-m-d') ?: '9999-12-31',
                $item['step']->sort_order,
                $item['step']->id
            ))
            ->values();
    }

    public static function attentionOptions(): array
    {
        return [
            'all' => 'All current work',
            'overdue' => 'Overdue',
            'blocked' => 'Blocked',
            'waiting' => 'Waiting externally',
            'unassigned' => 'No coordinator',
            'ready' => 'Ready to progress',
        ];
    }

    public static function dueOptions(): array
    {
        return [
            'all' => 'Any schedule',
            'overdue' => 'Overdue',
            'today' => 'Due today',
            'week' => 'Due in 7 days',
            'unscheduled' => 'Unscheduled',
        ];
    }

    public function matchesAttention(array $item, string $attention): bool
    {
        return match ($attention) {
            'overdue' => $item['is_overdue'],
            'blocked' => $item['is_blocked'],
            'waiting' => $item['is_waiting'],
            'unassigned' => $item['coordinator'] === null,
            'ready' => ! $item['is_overdue'] && ! $item['is_blocked'] && ! $item['is_waiting'],
            default => true,
        };
    }

    public function matchesDue(array $item, string $due): bool
    {
        $date = $item['due_date'];
        $today = now()->startOfDay();

        return match ($due) {
            'overdue' => $item['is_overdue'],
            'today' => $date?->isSameDay($today) ?? false,
            'week' => $date && ! $date->lt($today) && $date->lte($today->copy()->addDays(7)),
            'unscheduled' => $date === null,
            default => true,
        };
    }

    public function currentForWorkflow(ProjectWorkflow $workflow): Collection
    {
        $open = $workflow->steps
            ->where('is_required', true)
            ->whereNotIn('status', self::TERMINAL)
            ->sortBy('sort_order')
            ->values();
        if ($open->isEmpty()) return collect();

        $projectOpen = $open->whereNull('project_product_id')->values();
        $productOpen = $open->whereNotNull('project_product_id')->groupBy('project_product_id');
        $current = collect();

        if ($candidate = $projectOpen->first()) {
            $earlierProductOpen = $open->contains(fn ($step) => $step->project_product_id !== null && $step->sort_order < $candidate->sort_order);
            if (! $earlierProductOpen) $current->push($candidate);
        }

        foreach ($productOpen as $lane) {
            $candidate = $lane->first();
            $earlierProjectOpen = $projectOpen->contains(fn ($step) => $step->sort_order < $candidate->sort_order);
            if (! $earlierProjectOpen) $current->push($candidate);
        }

        return $current->map(fn ($step) => $this->item($workflow, $step));
    }

    private function item(ProjectWorkflow $workflow, $step): array
    {
        $approvalType = ['artwork_approved' => 'artwork', 'pps_approved' => 'pps'][$step->step_key] ?? null;
        $approval = $approvalType ? $workflow->approvals->first(fn ($request) =>
            $request->project_product_id === $step->project_product_id && $request->approval_type === $approvalType && $request->status === 'open'
        ) : null;
        $due = $step->planned_end_date ?: $approval?->response_due_at;
        $dueSource = $step->planned_end_date ? 'Planned completion' : ($approval?->response_due_at ? 'Client response due' : null);
        $today = now()->startOfDay();
        $overdue = $due?->lt($today) ?? false;
        $blocked = $step->status === 'blocked';
        $waiting = in_array($step->status, ['waiting_client', 'waiting_vendor'], true);
        $coordinator = $workflow->project->assignedUser;
        $attentionRank = match (true) {
            $coordinator === null => 0,
            $overdue => 1,
            $blocked => 2,
            $waiting => 3,
            $step->status === 'in_progress' => 4,
            default => 5,
        };
        $attention = match (true) {
            $coordinator === null => 'No coordinator',
            $overdue => 'Overdue',
            $blocked => 'Blocked',
            $step->status === 'waiting_client' => 'Waiting for client',
            $step->status === 'waiting_vendor' => 'Waiting for vendor',
            $step->status === 'in_progress' => 'In progress',
            $due === null => 'Needs schedule',
            default => 'Ready',
        };

        return [
            'workflow' => $workflow,
            'project' => $workflow->project,
            'step' => $step,
            'product' => $step->product,
            'coordinator' => $coordinator,
            'owner' => $step->owner,
            'due_date' => $due,
            'due_source' => $dueSource,
            'is_overdue' => $overdue,
            'is_blocked' => $blocked,
            'is_waiting' => $waiting,
            'attention' => $attention,
            'attention_key' => str($attention)->slug()->toString(),
            'attention_rank' => $attentionRank,
        ];
    }
}
