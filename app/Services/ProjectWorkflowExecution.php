<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectWorkflow;
use App\Models\ProjectWorkflowStep;
use Illuminate\Support\Facades\DB;

class ProjectWorkflowExecution
{
    public const TEMPLATE_KEY = 'standard_execution';
    public const TEMPLATE_VERSION = 2;

    private const PROJECT_STEPS = [
        ['setup', 'project_setup', 'Project setup complete', 10, 4, true],
        ['commercial', 'commercial_baseline', 'Commercial baseline Proforma confirmed', 20, 3, false],
        ['commercial', 'advance_received', 'Required production advance received', 30, 3, false],
        ['dispatch', 'dispatch_balance', 'Complete pre-dispatch balance received', 100, 3, false],
        ['dispatch', 'dispatch_approved', 'Dispatch approved', 110, 2, false],
        ['delivery', 'delivery_reconciled', 'All product quantities delivered and reconciled', 125, 3, true],
        ['closure', 'commercial_closure', 'Commercial closure reviewed', 130, 2, false],
        ['closure', 'feedback_closure', 'Feedback and final closure completed', 140, 1, true],
    ];

    private const PRODUCT_STEPS = [
        ['procurement', 'procurement_ready', 'Procurement ready', 40, 2, false],
        ['artwork', 'artwork_approved', 'Current artwork approved', 50, 2, true],
        ['sampling', 'pps_approved', 'PPS approved or not applicable', 60, 2, true],
        ['production', 'production_complete', 'Production quantity completed', 70, 4, true],
        ['quality', 'quality_passed', 'Quality inspection passed', 80, 2, true],
        ['packing', 'packing_verified', 'Packing completed and verified', 90, 2, true],
        ['dispatch', 'shipment_dispatched', 'Required quantity dispatched', 115, 2, true],
        ['delivery', 'delivery_complete', 'Required quantity delivered', 120, 3, true],
    ];

    public static function stageOptions(): array
    {
        return [
            'setup' => 'Project setup', 'commercial' => 'Commercial readiness',
            'procurement' => 'Procurement', 'artwork' => 'Artwork approval',
            'sampling' => 'PPS approval', 'production' => 'Production',
            'quality' => 'Quality control', 'packing' => 'Packing',
            'dispatch' => 'Dispatch readiness', 'delivery' => 'Delivery',
            'closure' => 'Closure', 'completed' => 'Completed',
        ];
    }

    public function start(Project $project, ?int $userId = null): ProjectWorkflow
    {
        return DB::transaction(function () use ($project, $userId) {
            $workflow = ProjectWorkflow::query()->firstOrCreate(
                ['project_id' => $project->id],
                [
                    'template_key' => self::TEMPLATE_KEY,
                    'template_version' => self::TEMPLATE_VERSION,
                    'status' => 'active',
                    'calculated_stage' => 'setup',
                    'calculated_progress' => 0,
                    'started_by' => $userId,
                    'started_at' => now(),
                ]
            );

            $this->sync($workflow, $project);

            return $this->recalculate($workflow);
        });
    }

    public function sync(ProjectWorkflow $workflow, ?Project $project = null): void
    {
        $project ??= $workflow->project;
        $owner = $project->assigned_to;
        if ((int) $workflow->template_version !== self::TEMPLATE_VERSION) {
            $workflow->update(['template_version' => self::TEMPLATE_VERSION]);
        }

        foreach (self::PROJECT_STEPS as $definition) {
            $this->ensureStep($workflow, null, $definition, 'project', $owner);
        }

        $project->loadMissing('products');
        foreach ($project->products->where('status', '!=', 'cancelled') as $product) {
            foreach (self::PRODUCT_STEPS as $definition) {
                $this->ensureStep($workflow, $product->id, $definition, 'product', $owner);
            }
        }
    }

    public function recalculate(ProjectWorkflow $workflow): ProjectWorkflow
    {
        $workflow->load('steps');
        $eligible = $workflow->steps
            ->where('is_required', true)
            ->whereNotIn('status', ['cancelled']);
        $totalWeight = (float) $eligible->sum(fn ($step) => (float) $step->weight);
        $completedWeight = (float) $eligible
            ->whereIn('status', ['completed', 'skipped'])
            ->sum(fn ($step) => (float) $step->weight);
        $progress = $totalWeight > 0 ? (int) round($completedWeight / $totalWeight * 100) : 0;
        $next = $eligible->whereNotIn('status', ['completed', 'skipped'])->sortBy('sort_order')->first();

        $workflow->update([
            'calculated_progress' => max(0, min(100, $progress)),
            'calculated_stage' => $next?->stage_key ?: 'completed',
            'status' => $next ? 'active' : 'completed',
        ]);

        return $workflow->fresh(['steps.product', 'steps.owner', 'reporter', 'commercialInvoice']);
    }

    public function updateStep(ProjectWorkflowStep $step, string $status, ?int $userId, ?string $notes = null, ?string $blockedReason = null): ProjectWorkflow
    {
        $wasStarted = $step->actual_started_at;
        $isComplete = in_array($status, ['completed', 'skipped'], true);
        $step->update([
            'status' => $status,
            'notes' => $notes,
            'blocked_reason' => $status === 'blocked' ? $blockedReason : null,
            'actual_started_at' => $wasStarted ?: ($status !== 'not_started' ? now() : null),
            'completed_at' => $isComplete ? now() : null,
            'completed_by' => $isComplete ? $userId : null,
        ]);

        return $this->recalculate($step->workflow);
    }

    private function ensureStep(ProjectWorkflow $workflow, ?int $productId, array $definition, string $scope, ?int $ownerId): void
    {
        [$stage, $key, $title, $sort, $weight, $clientVisible] = $definition;
        $query = $workflow->steps()->where('step_key', $key);
        $productId === null ? $query->whereNull('project_product_id') : $query->where('project_product_id', $productId);
        if ($existing = $query->first()) {
            $existing->update([
                'stage_key' => $stage, 'title' => $title, 'scope' => $scope,
                'sort_order' => $sort, 'weight' => $weight,
                'is_client_visible' => $clientVisible, 'owner_id' => $ownerId,
            ]);
            return;
        }

        $workflow->steps()->create([
            'project_product_id' => $productId,
            'step_key' => $key,
            'stage_key' => $stage,
            'title' => $title,
            'scope' => $scope,
            'sort_order' => $sort,
            'weight' => $weight,
            'status' => 'not_started',
            'is_required' => true,
            'is_client_visible' => $clientVisible,
            'owner_id' => $ownerId,
        ]);
    }
}
