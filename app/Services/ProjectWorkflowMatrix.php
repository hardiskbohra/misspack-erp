<?php

namespace App\Services;

use App\Models\ProjectApprovalRequest;
use App\Models\ProjectWorkflow;
use App\Models\ProjectWorkflowStep;
use App\Services\ProjectFinancialClosure;
use Illuminate\Support\Collection;

class ProjectWorkflowMatrix
{
    private const STATE_PRIORITY = [
        'blocked' => 0, 'waiting' => 1, 'active' => 2, 'ready' => 3,
        'planned' => 4, 'overridden' => 5, 'completed' => 6, 'not_applicable' => 7,
    ];

    public function build(ProjectWorkflow $workflow, ?Collection $exceptions = null): array
    {
        $workflow->loadMissing([
            'project.assignedUser', 'steps.product', 'steps.owner',
            'evaluations.activeOverride', 'approvals.currentVersion', 'approvals.overrides',
        ]);
        $exceptions ??= collect();
        $stages = collect(ProjectWorkflowExecution::stageOptions())->except('completed');
        $currentIds = app(ProjectCoordinatorQueue::class)->currentForWorkflow($workflow)->pluck('step.id')->all();
        $rows = collect();

        $projectSteps = $workflow->steps->whereNull('project_product_id');
        $rows->push($this->row(
            'project',
            'Project controls',
            $workflow->project->project_number,
            $projectSteps,
            $stages,
            $workflow,
            $exceptions,
            $currentIds,
            $workflow->project->assignedUser?->name
        ));

        $workflow->steps->whereNotNull('project_product_id')->groupBy('project_product_id')
            ->sortBy(fn (Collection $steps) => $steps->first()?->product?->sort_order ?? $steps->first()?->project_product_id)
            ->each(function (Collection $steps) use ($rows, $stages, $workflow, $exceptions, $currentIds) {
                $product = $steps->first()?->product;
                if (! $product || $product->status === 'cancelled') return;
                $rows->push($this->row(
                    'product-'.$product->id,
                    $product->product_name,
                    'Product lane · '.rtrim(rtrim(number_format((float) $product->quantity, 3, '.', ''), '0'), '.'),
                    $steps,
                    $stages,
                    $workflow,
                    $exceptions->where('project_product_id', $product->id),
                    $currentIds,
                    $workflow->project->assignedUser?->name
                ));
            });

        $stageSummary = $stages->map(function ($label, $key) use ($rows) {
            $cells = $rows->pluck("cells.{$key}")->filter(fn ($cell) => $cell['state'] !== 'not_applicable');
            $done = $cells->whereIn('state', ['completed', 'overridden'])->count();
            return ['key' => $key, 'label' => $label, 'applicable' => $cells->count(), 'done' => $done];
        })->values();
        $allCells = $rows->flatMap(fn ($row) => collect($row['cells'])->filter(fn ($cell) => $cell['state'] !== 'not_applicable'));

        return [
            'stages' => $stages,
            'rows' => $rows,
            'stage_summary' => $stageSummary,
            'summary' => [
                'lanes' => $rows->count(),
                'completed' => $allCells->where('state', 'completed')->count(),
                'overridden' => $allCells->where('state', 'overridden')->count(),
                'blocked' => $allCells->where('state', 'blocked')->count(),
                'waiting' => $allCells->where('state', 'waiting')->count(),
                'current' => $allCells->filter(fn ($cell) => $cell['is_current'])->count(),
                'exceptions' => $exceptions->count(),
            ],
        ];
    }

    private function row(string $key, string $label, string $subLabel, Collection $steps, Collection $stages, ProjectWorkflow $workflow, Collection $exceptions, array $currentIds, ?string $coordinator): array
    {
        $cells = $stages->mapWithKeys(function ($stageLabel, $stageKey) use ($steps, $workflow, $exceptions, $currentIds) {
            $stageSteps = $steps->where('stage_key', $stageKey)->sortBy('sort_order')->values();
            return [$stageKey => $this->cell($stageKey, $stageSteps, $workflow, $exceptions, $currentIds)];
        })->all();
        $applicable = collect($cells)->reject(fn ($cell) => $cell['state'] === 'not_applicable');
        $done = $applicable->whereIn('state', ['completed', 'overridden'])->count();

        return [
            'key' => $key,
            'label' => $label,
            'sub_label' => $subLabel,
            'coordinator' => $coordinator ?: 'Unassigned',
            'cells' => $cells,
            'done' => $done,
            'applicable' => $applicable->count(),
            'progress' => $applicable->count() ? (int) round($done / $applicable->count() * 100) : 0,
            'has_risk' => $applicable->contains(fn ($cell) => in_array($cell['state'], ['blocked', 'waiting'], true) || $cell['exception_count'] > 0),
        ];
    }

    private function cell(string $stageKey, Collection $steps, ProjectWorkflow $workflow, Collection $exceptions, array $currentIds): array
    {
        if ($steps->isEmpty()) {
            return ['state' => 'not_applicable', 'label' => 'Not applicable', 'steps' => [], 'is_current' => false, 'exception_count' => 0];
        }

        $items = $steps->map(fn (ProjectWorkflowStep $step) => $this->step($step, $workflow, $exceptions, $currentIds));
        $state = $items->sortBy(fn ($item) => self::STATE_PRIORITY[$item['state']] ?? 99)->first()['state'];

        return [
            'state' => $state,
            'label' => $this->stateLabel($state),
            'steps' => $items->all(),
            'is_current' => $items->contains('is_current', true),
            'exception_count' => $items->sum('exception_count'),
        ];
    }

    private function step(ProjectWorkflowStep $step, ProjectWorkflow $workflow, Collection $exceptions, array $currentIds): array
    {
        $evaluation = $workflow->evaluations->first(fn ($row) =>
            $row->gate_key === $step->step_key && (int) ($row->project_product_id ?? 0) === (int) ($step->project_product_id ?? 0)
        );
        $approvalType = ['artwork_approved' => ProjectApprovalRequest::TYPE_ARTWORK, 'pps_approved' => ProjectApprovalRequest::TYPE_PPS][$step->step_key] ?? null;
        $approval = $approvalType ? $workflow->approvals->first(fn ($row) =>
            $row->approval_type === $approvalType && (int) $row->project_product_id === (int) $step->project_product_id
        ) : null;
        $details = $evaluation?->details ?: [];
        $metric = $this->metric($step->step_key, $details, $workflow->project->currency);
        $state = $this->stepState($step, $evaluation, $approval);
        $stepExceptions = $exceptions->filter(fn ($exception) =>
            (int) ($exception->project_workflow_step_id ?? 0) === $step->id
            || ($step->step_key === ProjectFinancialClosure::GATE && $exception->project_workflow_step_id === null)
        );

        return [
            'id' => $step->id,
            'key' => $step->step_key,
            'title' => $step->title,
            'state' => $state,
            'status_label' => $step->statusLabel(),
            'is_current' => in_array($step->id, $currentIds, true),
            'is_required' => $step->is_required,
            'owner' => $step->owner?->name,
            'due_date' => $step->planned_end_date?->format('Y-m-d'),
            'blocked_reason' => $step->blocked_reason,
            'summary' => $evaluation?->summary,
            'evaluated_at' => $evaluation?->evaluated_at?->format('d M, H:i'),
            'fingerprint' => $evaluation?->content_hash,
            'metric' => $metric,
            'approval' => $approval ? [
                'status' => $approval->effectiveStatus(),
                'label' => $approval->statusLabel(),
                'version' => $approval->current_version_number,
                'due_at' => $approval->response_due_at?->format('d M'),
                'overdue' => $approval->isOverdue(),
            ] : null,
            'exception_count' => $stepExceptions->count(),
            'override_id' => $evaluation?->activeOverride?->id,
        ];
    }

    private function stepState(ProjectWorkflowStep $step, $evaluation, $approval): string
    {
        if ($approval?->effectiveStatus() === 'overridden' || $evaluation?->effectiveResult() === 'overridden' || $step->status === 'skipped') return 'overridden';
        return match ($step->status) {
            'completed' => 'completed',
            'blocked' => 'blocked',
            'waiting_client', 'waiting_vendor' => 'waiting',
            'in_progress' => 'active',
            'ready' => 'ready',
            default => 'planned',
        };
    }

    private function metric(string $key, array $details, ?string $currency): ?array
    {
        $coveredKey = [
            'procurement_ready' => 'valid_po_quantity',
            'production_complete' => 'production_completed_quantity',
            'quality_passed' => 'passed_quantity',
            'packing_verified' => 'verified_packed_quantity',
            'shipment_dispatched' => 'dispatched_quantity',
            'delivery_complete' => 'delivered_quantity',
        ][$key] ?? null;
        if ($coveredKey && array_key_exists('required_quantity', $details)) {
            $required = (float) $details['required_quantity'];
            $covered = (float) ($details[$coveredKey] ?? 0);
            return [
                'type' => 'quantity', 'covered' => $covered, 'required' => $required,
                'remaining' => (float) ($details['remaining_quantity'] ?? max($required - $covered, 0)),
                'percent' => $required > 0 ? min(100, (int) round($covered / $required * 100)) : 0,
            ];
        }
        if (in_array($key, ['advance_received', 'dispatch_balance'], true) && array_key_exists('required_amount', $details)) {
            $required = (float) $details['required_amount'];
            $received = (float) ($details['received_amount'] ?? 0);
            return [
                'type' => 'money', 'covered' => $received, 'required' => $required,
                'remaining' => (float) ($details['shortfall'] ?? max($required - $received, 0)),
                'currency' => ($currency ?: 'INR'), 'percent' => $required > 0 ? min(100, (int) round($received / $required * 100)) : 100,
            ];
        }
        if ($key === 'delivery_reconciled' && array_key_exists('active_product_count', $details)) {
            $required = (float) $details['active_product_count'];
            $covered = (float) ($details['delivered_product_count'] ?? 0);
            return ['type' => 'products', 'covered' => $covered, 'required' => $required, 'remaining' => max($required - $covered, 0), 'percent' => $required > 0 ? min(100, (int) round($covered / $required * 100)) : 0];
        }
        return null;
    }

    private function stateLabel(string $state): string
    {
        return [
            'completed' => 'Complete', 'overridden' => 'Override', 'blocked' => 'Blocked',
            'waiting' => 'Waiting', 'active' => 'In progress', 'ready' => 'Ready',
            'planned' => 'Not reached', 'not_applicable' => '—',
        ][$state] ?? str($state)->headline()->toString();
    }
}
