<?php

namespace App\Services;

use App\Models\PackagingList;
use App\Models\Project;
use App\Models\ShipmentItem;
use App\Models\ProjectGateEvaluation;
use App\Models\ProjectProduct;
use App\Models\ProjectQualityInspection;
use App\Models\ProjectWorkflow;
use App\Models\ProjectWorkflowStep;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;

class ShipmentDispatchAuthorization
{
    public const GATE_KEY = 'dispatch_approved';

    public function __construct(
        private readonly PackagingListVerifier $verifier,
        private readonly PackagingListProjectLinker $packingLinker,
        private readonly ShipmentDocuments $documents,
    ) {}

    public function evaluate(Shipment $shipment, ProjectWorkflow $workflow, array $rows, ?int $userId = null): ProjectGateEvaluation
    {
        return $this->evaluateForTracking($shipment, $workflow, $rows, $userId)['evaluation'];
    }

    /**
     * Keep the immutable readiness evaluation and its historical fingerprint
     * independent from the policy for saving a tracking status. The latter is
     * classified during the same inspection, not by parsing message text.
     *
     * @return array{evaluation: ProjectGateEvaluation, warnings: array, blocking_issues: array}
     */
    public function evaluateForTracking(Shipment $shipment, ProjectWorkflow $workflow, array $rows, ?int $userId = null, bool $lockSources = false): array
    {
        return DB::transaction(function () use ($shipment, $workflow, $rows, $userId, $lockSources) {
            Project::query()->whereKey($workflow->project_id)->lockForUpdate()->firstOrFail();
            app(ProjectWorkflowExecution::class)->sync($workflow);
            $inspection = $this->inspectForTracking($shipment, $workflow, $rows, $lockSources);
            $details = $inspection['details'];
            $passed = $details['issues'] === [];
            $summary = $passed
                ? 'Shipment quantities are covered by confirmed linked Packaging Lists, QC, approvals and Project dispatch documents.'
                : count($details['issues']).' dispatch authorization issue(s): '.implode(' ', $details['issues']);
            $hash = $this->fingerprint($details);
            $latest = $workflow->evaluations()->whereNull('project_product_id')->where('gate_key', self::GATE_KEY)
                ->latest('evaluated_at')->latest('id')->lockForUpdate()->first();
            // History edits can open a MySQL snapshot before waiting for the
            // project lock; a revoked or newly approved bypass needs a current read.
            $latestOverride = $latest?->overrides()->whereNull('revoked_at')->latest('id')->lockForUpdate()->first();
            $matches = $latest && hash_equals((string) $latest->content_hash, $hash);
            if (! $matches && $latest && $latestOverride) {
                // The new checklist context is not itself a source risk. Keep
                // an existing decision when every actual fact still matches,
                // including snapshots created before this field existed.
                $compatibleDetails = $details;
                $previousPaperwork = (array) data_get($latest->details, 'paperwork', []);
                if (array_key_exists('delivery_checklist', $previousPaperwork)) {
                    $compatibleDetails['paperwork']['delivery_checklist'] = $previousPaperwork['delivery_checklist'];
                } else {
                    unset($compatibleDetails['paperwork']['delivery_checklist']);
                }
                $matches = hash_equals((string) $latest->content_hash, $this->fingerprint($compatibleDetails));
            }
            $evaluation = $matches
                ? $latest
                : $workflow->evaluations()->create([
                    'project_product_id'=>null, 'gate_key'=>self::GATE_KEY,
                    'result'=>$passed ? 'passed' : 'blocked', 'content_hash'=>$hash,
                    'summary'=>$summary, 'details'=>$details, 'evaluated_by'=>$userId, 'evaluated_at'=>now(),
                ]);
            $evaluation->setRelation('activeOverride', $matches ? $latestOverride : null);
            app(ProjectWorkflowGates::class)->applyToStep($evaluation);
            app(ProjectWorkflowExecution::class)->recalculate($workflow);
            return ['evaluation' => $evaluation, 'warnings' => $inspection['warnings'], 'blocking_issues' => $inspection['blocking_issues']];
        });
    }

    private function fingerprint(array $details): string
    {
        return hash('sha256', json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function inspect(Shipment $shipment, ProjectWorkflow $workflow, array $rows, bool $lockSources = false): array
    {
        return $this->inspectForTracking($shipment, $workflow, $rows, $lockSources)['details'];
    }

    /** @return array{details: array, warnings: array, blocking_issues: array} */
    public function inspectForTracking(Shipment $shipment, ProjectWorkflow $workflow, array $rows, bool $lockSources = false): array
    {
        $issues = []; $warnings = []; $blockingIssues = []; $lineEvidence = []; $productQuantities = [];
        $addIssue = static function (string $message, bool $blocksTracking = false) use (&$issues, &$warnings, &$blockingIssues): void {
            $issues[] = $message;
            if ($blocksTracking) $blockingIssues[] = $message;
            else $warnings[] = $message;
        };
        $linkedLists = PackagingList::query()
            ->when($lockSources, fn ($query) => $query->lockForUpdate())
            ->where('shipment_id', $shipment->id)
            ->where('project_id', $shipment->project_id)
            ->whereIn('status', ['confirmed', 'dispatched'])
            ->when(! $lockSources, fn ($query) => $query->with('cartons.items'))
            ->get();
        $validPackingItems = collect();
        $packingEvidence = [];
        if ($linkedLists->isEmpty()) {
            $addIssue('No confirmed Packaging List is linked to '.$shipment->shipment_number.'. Create one from this shipment, or edit an existing project Packaging List, select this shipment and confirm it.');
        }
        foreach ($linkedLists as $list) {
            $this->packingLinker->reconcile($list, $lockSources);
            $inspection = $this->verifier->inspect($list, $lockSources);
            $checksPassed = $inspection['failed'] === 0;
            if (! $checksPassed) {
                $addIssue($list->packing_number.' is linked but must pass its automatic Packaging List checks.');
            }
            if ($checksPassed) $validPackingItems = $validPackingItems->concat($list->cartons->flatMap->items);
            $packingEvidence[] = [
                'packaging_list_id'=>$list->id, 'packing_number'=>$list->packing_number,
                'automatic_checks_passed'=>$checksPassed, 'failed_checks'=>$inspection['failed'],
            ];
        }

        foreach (array_values($rows) as $index => $row) {
            if (blank($row['product_name'] ?? null)) continue;
            $productId = (int) ($row['project_product_id'] ?? 0);
            $shipmentQuantity = round((float) ($row['quantity'] ?? 0), 3);
            $packed = round((float) $validPackingItems->where('project_product_id', $productId)->sum('total_quantity'), 3);
            if ($productId <= 0) $addIssue('Line '.($index+1).' is not linked to a project product.', true);
            if ($shipmentQuantity <= 0) $addIssue('Line '.($index+1).' must have a positive shipment quantity.', true);
            $productQuantities[$productId] = round(($productQuantities[$productId] ?? 0) + $shipmentQuantity, 3);
            $lineEvidence[] = [
                'line_number'=>$index+1, 'project_product_id'=>$productId,
                'product_name'=>$row['product_name'] ?? null, 'shipment_quantity'=>$shipmentQuantity,
                'linked_packed_quantity'=>$packed,
            ];
        }

        $currentScope = DB::transactionLevel() > 0;
        $salesProductIds = app(ProjectWorkflowSalesScope::class)->products($workflow->project_id, $currentScope)
            ->when($currentScope, fn ($query) => $query->lockForUpdate())->pluck('id')->map(fn ($id) => (int) $id)->all();
        $productEvidence = [];
        foreach ($productQuantities as $productId => $currentQuantity) {
            $linkedPackedQuantity = round((float) $validPackingItems->where('project_product_id', $productId)->sum('total_quantity'), 3);
            $productName = data_get(collect($lineEvidence)->firstWhere('project_product_id', $productId), 'product_name', 'shipment product');
            if ($linkedLists->isNotEmpty() && $productId > 0 && $linkedPackedQuantity + 0.0001 < $currentQuantity) {
                $addIssue(($productName ?: 'Shipment product').' has '.number_format($linkedPackedQuantity, 3).' confirmed packed of '.number_format($currentQuantity, 3).' required. Check that the linked Packaging List uses the same project product and quantity, then confirm it.');
            }
            if ($productId <= 0) continue;
            $product = ProjectProduct::query()->when($lockSources, fn ($query) => $query->lockForUpdate())->whereKey($productId)->first();
            if (! $product || (int) $product->project_id !== (int) $shipment->project_id) {
                $addIssue(($productName ?: 'Shipment product').' does not belong to this project. Choose the matching project product.', true);
                continue;
            }
            if ($product->status === 'cancelled') {
                $addIssue(($productName ?: 'Shipment product').' refers to a cancelled project product. Choose an active product.', true);
            }
            $projectQuantity = round((float) $product->quantity, 3);
            $latestQc = ProjectQualityInspection::query()->where('project_product_id', $productId)
                ->when($lockSources, fn ($query) => $query->lockForUpdate())->latest('inspected_at')->latest('id')->first();
            $qcSamplePassed = $latestQc && in_array($latestQc->result, ['passed', 'passed_with_observations'], true);
            $passedQc = round((float) ($latestQc?->passed_quantity ?: 0), 3);
            $qcAuthorized = $qcSamplePassed ? $projectQuantity : 0.0;
            // A locking join is a current read of both the shipment status and
            // its lines on MySQL, even if an earlier query opened a snapshot.
            $reservedQuery = ShipmentItem::query()
                ->join('shipments as reserved_shipments', 'reserved_shipments.id', '=', 'shipment_items.shipment_id')
                ->where('shipment_items.project_product_id', $productId)
                ->where('reserved_shipments.status', '!=', Shipment::STATUS_CANCELLED)
                ->when($shipment->exists, fn ($query) => $query->where('reserved_shipments.id', '!=', $shipment->id));
            $reservedElsewhere = round((float) ($lockSources
                ? $reservedQuery->lockForUpdate()->get(['shipment_items.quantity'])->sum('quantity')
                : $reservedQuery->sum('shipment_items.quantity')), 3);
            $salesItem = in_array((int) $productId, $salesProductIds, true);
            $steps = $salesItem ? app(ProjectWorkflowSalesScope::class)->steps($workflow->allSteps(), $currentScope)
                ->where('project_product_id', $productId)->whereIn('step_key', ['artwork_approved','pps_approved'])
                ->when($currentScope, fn ($query) => $query->lockForUpdate())->get()->groupBy('step_key') : collect();
            // Never select an arbitrary duplicate as the sole approval evidence.
            $approvalsReady = $salesItem ? collect(['artwork_approved','pps_approved'])->every(function ($key) use ($steps) {
                $checks = $steps->get($key, collect());
                return $checks->isNotEmpty() && $checks->every(fn ($step) => in_array($step->status, ['completed','skipped'], true));
            }) : null;
            if ($reservedElsewhere + $currentQuantity > $qcAuthorized + 0.0001) {
                $addIssue($qcSamplePassed
                    ? 'Recorded QC authorization is insufficient for this shipment quantity.'
                    : 'A current passing QC sample inspection is required for this shipment product.');
            }
            // Do not turn missing QC into permission to exceed the project's
            // ordered quantity: that remains a separate quantity safeguard.
            if ($reservedElsewhere + $currentQuantity > $projectQuantity + 0.0001) {
                $addIssue(($productName ?: 'Shipment product').' has '.number_format($reservedElsewhere + $currentQuantity, 3)
                    .' allocated across shipments, exceeding its project product quantity of '.number_format($projectQuantity, 3).'.', true);
            }
            if ($salesItem && ! $approvalsReady) $addIssue('Artwork and PPS approvals are not complete for a shipment product.');
            $productEvidence[] = [
                'project_product_id'=>$productId, 'workflow_sales_item'=>$salesItem, 'current_shipment_quantity'=>$currentQuantity,
                'reserved_elsewhere'=>$reservedElsewhere, 'qc_passed_quantity'=>$passedQc,
                'qc_sample_inspected_quantity'=>(float) ($latestQc?->inspected_quantity ?: 0),
                'qc_latest_result'=>$latestQc?->result, 'qc_authorized_quantity'=>$qcAuthorized,
                'qc_available_quantity'=>round(max($qcAuthorized-$reservedElsewhere, 0), 3),
                'approvals_ready'=>$approvalsReady,
            ];
        }

        $paperwork = $this->documents->summary($shipment, $lockSources);
        $missingPaperwork = $paperwork['missing'];
        if ($missingPaperwork !== []) $addIssue('Mandatory project dispatch documents are incomplete: '.implode(', ', $missingPaperwork).'.');
        return [
            // Keep the established evidence shape. New identity or quantity
            // diagnostics produce a fresh snapshot; historical ones stay intact.
            'details' => [
                'shipment_id'=>$shipment->id, 'shipment_number'=>$shipment->shipment_number,
                'project_id'=>$shipment->project_id, 'lines'=>$lineEvidence, 'products'=>$productEvidence,
                'packing_lists'=>$packingEvidence,
                // Record whether the delivery checklist was assessed, even if
                // POD was already present. A failed direct-delivery attempt can
                // then be rechecked without guessing from the missing labels.
                'paperwork'=>['complete'=>$missingPaperwork === [], 'missing'=>$missingPaperwork,
                    'stored_on_project'=>true, 'delivery_checklist'=>$shipment->status === Shipment::STATUS_DELIVERED],
                'issues'=>array_values(array_unique($issues)),
            ],
            'warnings' => array_values(array_unique($warnings)),
            'blocking_issues' => array_values(array_unique($blockingIssues)),
        ];
    }

    /**
     * Later tracking changes are advisory. Update the project-level snapshot
     * only when it already represents this shipment; another partial shipment
     * (or a synthetic administrator decision) must remain the current gate.
     *
     * @return array{details?: array, evaluation?: ProjectGateEvaluation, warnings: array, blocking_issues: array}
     */
    public function assessLaterStatus(Shipment $shipment, ProjectWorkflow $workflow, ?int $userId = null): array
    {
        return DB::transaction(function () use ($shipment, $workflow, $userId) {
            Project::query()->whereKey($workflow->project_id)->lockForUpdate()->firstOrFail();
            $current = $workflow->evaluations()->whereNull('project_product_id')->where('gate_key', self::GATE_KEY)
                ->latest('evaluated_at')->latest('id')->lockForUpdate()->first();
            $rows = $this->rowsFromShipment($shipment, true);
            if ((int) data_get($current?->details, 'shipment_id', 0) === (int) $shipment->id) {
                return $this->evaluateForTracking($shipment, $workflow, $rows, $userId, true);
            }

            app(ProjectWorkflowExecution::class)->sync($workflow);
            return $this->inspectForTracking($shipment, $workflow, $rows, true);
        });
    }

    /** Refresh only the shipment represented by the current project-level gate. */
    public function refreshCurrent(ProjectWorkflow $workflow, ?int $userId = null, ?int $onlyShipmentId = null): ?ProjectGateEvaluation
    {
        return $this->refresh($workflow, $userId, $onlyShipmentId, false, null);
    }

    /**
     * Source updates may initialize a gate if no shipment has been evaluated.
     * Once a gate names a shipment, even a missing or cancelled one, do not
     * silently replace it with another partial shipment.
     */
    public function refreshCurrentOrInitialize(ProjectWorkflow $workflow, ?int $userId = null, ?int $preferredShipmentId = null): ?ProjectGateEvaluation
    {
        return $this->refresh($workflow, $userId, null, true, $preferredShipmentId);
    }

    private function refresh(ProjectWorkflow $workflow, ?int $userId, ?int $onlyShipmentId, bool $initialize, ?int $preferredShipmentId): ?ProjectGateEvaluation
    {
        return DB::transaction(function () use ($workflow, $userId, $onlyShipmentId, $initialize, $preferredShipmentId) {
            Project::query()->whereKey($workflow->project_id)->lockForUpdate()->firstOrFail();
            $current = $workflow->evaluations()->whereNull('project_product_id')->where('gate_key', self::GATE_KEY)
                ->latest('evaluated_at')->latest('id')->lockForUpdate()->first();
            $shipmentId = (int) data_get($current?->details, 'shipment_id', 0);
            if ($onlyShipmentId !== null && $onlyShipmentId !== $shipmentId) return null;

            $query = Shipment::query()->where('project_id', $workflow->project_id);
            if ($shipmentId > 0) {
                $shipment = $query->whereKey($shipmentId)
                    ->whereIn('status', array_merge([Shipment::STATUS_PLANNING], Shipment::dispatchedStatuses()))
                    ->lockForUpdate()->first();
                // Do not fall back to another shipment when this one went away.
                if (! $shipment) return null;
            } elseif ($initialize) {
                $query->where('status', Shipment::STATUS_PLANNING);
                $shipment = $preferredShipmentId ? (clone $query)->whereKey($preferredShipmentId)->lockForUpdate()->first() : null;
                $shipment ??= $query->latest('id')->lockForUpdate()->first();
                if (! $shipment) return null;
            } else {
                return null;
            }

            $candidate = $this->candidateForSnapshot($shipment, $current);
            return $this->evaluateForTracking($candidate, $workflow, $this->rowsFromShipment($shipment, true), $userId, true)['evaluation'];
        });
    }

    /** Recheck the same prospective delivery checklist without changing the shipment. */
    public function candidateForSnapshot(Shipment $shipment, ?ProjectGateEvaluation $evaluation): Shipment
    {
        if ($shipment->status !== Shipment::STATUS_PLANNING
            || (int) data_get($evaluation?->details, 'shipment_id', 0) !== (int) $shipment->id) return $shipment;

        $paperwork = (array) data_get($evaluation?->details, 'paperwork', []);
        if (array_key_exists('delivery_checklist', $paperwork)) {
            $deliveryChecklist = $paperwork['delivery_checklist'] === true;
        } else {
            // Older snapshots did not record the target. Missing POD proves a
            // delivery checklist only if Planning itself did not require POD.
            $deliveryChecklist = in_array(ShipmentDocuments::label('pod'), (array) ($paperwork['missing'] ?? []), true)
                && ! in_array('pod', $this->documents->requiredFor($shipment), true);
        }
        if (! $deliveryChecklist) return $shipment;

        $candidate = clone $shipment;
        $candidate->status = Shipment::STATUS_DELIVERED;
        return $candidate;
    }

    public function rowsFromShipment(Shipment $shipment, bool $lockSources = false): array
    {
        return $shipment->items()
            ->when($lockSources, fn ($query) => $query->lockForUpdate())
            ->get(['project_product_id', 'product_name', 'quantity', 'unit'])
            ->map->only(['project_product_id', 'product_name', 'quantity', 'unit'])
            ->values()
            ->all();
    }
}
