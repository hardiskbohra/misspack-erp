<?php

namespace App\Services;

use App\Models\PackagingList;
use App\Models\ShipmentItem;
use App\Models\ProjectGateEvaluation;
use App\Models\ProjectProduct;
use App\Models\ProjectQualityInspection;
use App\Models\ProjectWorkflow;
use App\Models\ProjectWorkflowStep;
use App\Models\Shipment;

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
        $details = $this->inspect($shipment, $workflow, $rows);
        $passed = $details['issues'] === [];
        $summary = $passed
            ? 'Shipment quantities are covered by confirmed linked Packaging Lists, QC, approvals and Project dispatch documents.'
            : count($details['issues']).' dispatch authorization issue(s): '.implode(' ', $details['issues']);
        $hash = hash('sha256', json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $latest = $workflow->evaluations()->whereNull('project_product_id')->where('gate_key', self::GATE_KEY)->latest('evaluated_at')->latest('id')->first();
        $evaluation = $latest && hash_equals((string) $latest->content_hash, $hash)
            ? $latest
            : $workflow->evaluations()->create([
                'project_product_id'=>null, 'gate_key'=>self::GATE_KEY,
                'result'=>$passed ? 'passed' : 'blocked', 'content_hash'=>$hash,
                'summary'=>$summary, 'details'=>$details, 'evaluated_by'=>$userId, 'evaluated_at'=>now(),
            ]);
        app(ProjectWorkflowGates::class)->applyToStep($evaluation);
        app(ProjectWorkflowExecution::class)->recalculate($workflow);
        return $evaluation;
    }

    public function inspect(Shipment $shipment, ProjectWorkflow $workflow, array $rows, bool $lockSources = false): array
    {
        $issues = []; $lineEvidence = []; $productQuantities = [];
        $linkedLists = PackagingList::query()
            ->when($lockSources, fn ($query) => $query->lockForUpdate())
            ->where('shipment_id', $shipment->id)
            ->where('project_id', $shipment->project_id)
            ->whereIn('status', ['confirmed', 'dispatched'])
            ->with('cartons.items')
            ->get();
        $validPackingItems = collect();
        $packingEvidence = [];
        if ($linkedLists->isEmpty()) {
            $issues[] = 'No confirmed Packaging List is linked to '.$shipment->shipment_number.'. Create one from this shipment, or edit an existing project Packaging List, select this shipment and confirm it.';
        }
        foreach ($linkedLists as $list) {
            $this->packingLinker->reconcile($list);
            $list->load('cartons.items');
            $inspection = $this->verifier->inspect($list);
            $checksPassed = $inspection['failed'] === 0;
            if (! $checksPassed) {
                $issues[] = $list->packing_number.' is linked but must pass its automatic Packaging List checks.';
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
            if ($productId <= 0) $issues[] = "Line ".($index+1).' is not linked to a project product.';
            if ($shipmentQuantity <= 0) $issues[] = "Line ".($index+1).' must have a positive shipment quantity.';
            $productQuantities[$productId] = round(($productQuantities[$productId] ?? 0) + $shipmentQuantity, 3);
            $lineEvidence[] = [
                'line_number'=>$index+1, 'project_product_id'=>$productId,
                'product_name'=>$row['product_name'] ?? null, 'shipment_quantity'=>$shipmentQuantity,
                'linked_packed_quantity'=>$packed,
            ];
        }

        $productEvidence = [];
        foreach ($productQuantities as $productId => $currentQuantity) {
            $linkedPackedQuantity = round((float) $validPackingItems->where('project_product_id', $productId)->sum('total_quantity'), 3);
            if ($linkedLists->isNotEmpty() && $productId > 0 && $linkedPackedQuantity + 0.0001 < $currentQuantity) {
                $productName = data_get(collect($lineEvidence)->firstWhere('project_product_id', $productId), 'product_name', 'shipment product');
                $issues[] = ($productName ?: 'Shipment product').' has '.number_format($linkedPackedQuantity, 3).' confirmed packed of '.number_format($currentQuantity, 3).' required. Check that the linked Packaging List uses the same project product and quantity, then confirm it.';
            }
            if ($productId <= 0) continue;
            $latestQc = ProjectQualityInspection::query()->where('project_product_id', $productId)->latest('inspected_at')->latest('id')->first();
            $qcSamplePassed = $latestQc && in_array($latestQc->result, ['passed', 'passed_with_observations'], true);
            $passedQc = round((float) ($latestQc?->passed_quantity ?: 0), 3);
            $qcAuthorized = $qcSamplePassed ? round((float) ProjectProduct::query()->whereKey($productId)->value('quantity'), 3) : 0.0;
            $reservedElsewhere = round((float) ShipmentItem::query()->where('project_product_id', $productId)
                ->whereHas('shipment', fn ($query) => $query->where('status', '!=', Shipment::STATUS_CANCELLED)->when($shipment->exists, fn ($nested) => $nested->where('id', '!=', $shipment->id)))
                ->sum('quantity'), 3);
            $steps = ProjectWorkflowStep::query()->where('project_workflow_id', $workflow->id)->where('project_product_id', $productId)->whereIn('step_key', ['artwork_approved','pps_approved'])->get()->keyBy('step_key');
            $approvalsReady = collect(['artwork_approved','pps_approved'])->every(fn ($key) => in_array($steps->get($key)?->status, ['completed','skipped'], true));
            if ($reservedElsewhere + $currentQuantity > $qcAuthorized + 0.0001) $issues[] = 'A current passing QC sample inspection is required for this shipment product.';
            if (! $approvalsReady) $issues[] = 'Artwork and PPS approvals are not complete for a shipment product.';
            $productEvidence[] = [
                'project_product_id'=>$productId, 'current_shipment_quantity'=>$currentQuantity,
                'reserved_elsewhere'=>$reservedElsewhere, 'qc_passed_quantity'=>$passedQc,
                'qc_sample_inspected_quantity'=>(float) ($latestQc?->inspected_quantity ?: 0),
                'qc_latest_result'=>$latestQc?->result, 'qc_authorized_quantity'=>$qcAuthorized,
                'qc_available_quantity'=>round(max($qcAuthorized-$reservedElsewhere, 0), 3),
                'approvals_ready'=>$approvalsReady,
            ];
        }

        $paperwork = $this->documents->summary($shipment);
        $missingPaperwork = $paperwork['missing'];
        if ($missingPaperwork !== []) $issues[] = 'Mandatory project dispatch documents are incomplete: '.implode(', ', $missingPaperwork).'.';
        $issues = array_values(array_unique($issues));
        return [
            'shipment_id'=>$shipment->id, 'shipment_number'=>$shipment->shipment_number,
            'project_id'=>$shipment->project_id, 'lines'=>$lineEvidence, 'products'=>$productEvidence,
            'packing_lists'=>$packingEvidence, 'paperwork'=>['complete'=>$missingPaperwork === [], 'missing'=>$missingPaperwork, 'stored_on_project'=>true],
            'issues'=>$issues,
        ];
    }

    public function rowsFromShipment(Shipment $shipment): array
    {
        return $shipment->items()
            ->get(['project_product_id', 'product_name', 'quantity', 'unit'])
            ->map->only(['project_product_id', 'product_name', 'quantity', 'unit'])
            ->values()
            ->all();
    }
}
