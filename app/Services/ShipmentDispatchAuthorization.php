<?php

namespace App\Services;

use App\Models\PackagingList;
use App\Models\PackagingListItem;
use App\Models\ProjectGateEvaluation;
use App\Models\ProjectProduct;
use App\Models\ProjectQualityInspection;
use App\Models\ProjectWorkflow;
use App\Models\ProjectWorkflowStep;
use App\Models\Shipment;
use App\Models\ShipmentPackingAllocation;
use Illuminate\Support\Collection;

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
            ? 'Shipment quantities are allocated to confirmed packing that passes automatic checks, covered by QC and ready for dispatch.'
            : count($details['issues']).' dispatch authorization issue(s) must be resolved or manager-overridden.';
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
        $issues = []; $lineEvidence = []; $productQuantities = []; $currentPackingTotals = [];
        foreach (array_values($rows) as $index => $row) {
            if (blank($row['product_name'] ?? null)) continue;
            $productId = (int) ($row['project_product_id'] ?? 0);
            $shipmentQuantity = round((float) ($row['quantity'] ?? 0), 3);
            $allocations = collect($row['allocations'] ?? [])->filter(fn ($allocation) => (int) ($allocation['packaging_list_item_id'] ?? 0) > 0 && (float) ($allocation['allocated_quantity'] ?? 0) > 0)
                ->groupBy(fn ($allocation) => (int) $allocation['packaging_list_item_id'])
                ->map(fn (Collection $group, int $packingItemId) => ['packaging_list_item_id'=>$packingItemId, 'allocated_quantity'=>round((float)$group->sum('allocated_quantity'), 3)])
                ->values();
            $allocated = round((float) $allocations->sum('allocated_quantity'), 3);
            if ($productId <= 0) $issues[] = "Line ".($index+1).' is not linked to a project product.';
            if ($shipmentQuantity <= 0) $issues[] = "Line ".($index+1).' must have a positive shipment quantity.';
            if (abs($allocated - $shipmentQuantity) > 0.0001) $issues[] = "Line ".($index+1).' allocates '.number_format($allocated, 3).' of '.number_format($shipmentQuantity, 3).'.';
            $allocationEvidence = [];
            foreach ($allocations as $allocation) {
                $packingItem = PackagingListItem::query()->when($lockSources, fn ($query) => $query->lockForUpdate())->with(['carton.packagingList', 'carton.packagingList.cartons.items'])->find($allocation['packaging_list_item_id']);
                if (! $packingItem) {
                    $issues[] = 'A selected Packaging List item no longer exists.';
                    continue;
                }
                $list = $packingItem->carton?->packagingList;
                $inspection = $list ? $this->verifier->inspect($list) : null;
                $automaticChecksPassed = $list
                    && in_array($list->status, ['confirmed', 'dispatched'], true)
                    && $inspection['failed'] === 0;
                $reservedElsewhere = round((float) ShipmentPackingAllocation::query()
                    ->where('packaging_list_item_id', $packingItem->id)
                    ->whereHas('shipmentItem.shipment', fn ($query) => $query->where('status', '!=', Shipment::STATUS_CANCELLED)->when($shipment->exists, fn ($nested) => $nested->where('id', '!=', $shipment->id)))
                    ->sum('allocated_quantity'), 3);
                $available = round(max((float)$packingItem->total_quantity - $reservedElsewhere, 0), 3);
                $quantity = (float) $allocation['allocated_quantity'];
                $currentPackingTotals[$packingItem->id] = round(($currentPackingTotals[$packingItem->id] ?? 0) + $quantity, 3);
                if ((int)$packingItem->project_product_id !== $productId) $issues[] = 'Packing allocation product identity does not match its shipment line.';
                if ((int)($list?->project_id) !== (int)$shipment->project_id) $issues[] = 'Packing allocation belongs to a different project.';
                if (! $automaticChecksPassed) $issues[] = ($list?->packing_number ?: 'Packaging List').' must be confirmed and pass its automatic checks.';
                if ($currentPackingTotals[$packingItem->id] > $available + 0.0001) $issues[] = ($list?->packing_number ?: 'Packaging List').' has only '.number_format($available, 3).' available for this item across the shipment.';
                $allocationEvidence[] = [
                    'packaging_list_item_id'=>$packingItem->id, 'packaging_list_id'=>$list?->id,
                    'packing_number'=>$list?->packing_number, 'quantity'=>$quantity,
                    'packed_quantity'=>(float)$packingItem->total_quantity, 'reserved_elsewhere'=>$reservedElsewhere,
                    'available_quantity'=>$available, 'automatic_checks_passed'=>$automaticChecksPassed,
                    'failed_checks'=>$inspection['failed'] ?? null,
                ];
            }
            $productQuantities[$productId] = round(($productQuantities[$productId] ?? 0) + $shipmentQuantity, 3);
            $lineEvidence[] = [
                'line_number'=>$index+1, 'project_product_id'=>$productId,
                'product_name'=>$row['product_name'] ?? null, 'shipment_quantity'=>$shipmentQuantity,
                'allocated_quantity'=>$allocated, 'allocations'=>$allocationEvidence,
            ];
        }

        $productEvidence = [];
        foreach ($productQuantities as $productId => $currentQuantity) {
            if ($productId <= 0) continue;
            $latestQc = ProjectQualityInspection::query()->where('project_product_id', $productId)->latest('inspected_at')->latest('id')->first();
            $qcSamplePassed = $latestQc && in_array($latestQc->result, ['passed', 'passed_with_observations'], true);
            $passedQc = round((float) ($latestQc?->passed_quantity ?: 0), 3);
            $qcAuthorized = $qcSamplePassed ? round((float) ProjectProduct::query()->whereKey($productId)->value('quantity'), 3) : 0.0;
            $reservedElsewhere = round((float) ShipmentPackingAllocation::query()->where('project_product_id', $productId)
                ->whereHas('shipmentItem.shipment', fn ($query) => $query->where('status', '!=', Shipment::STATUS_CANCELLED)->when($shipment->exists, fn ($nested) => $nested->where('id', '!=', $shipment->id)))
                ->sum('allocated_quantity'), 3);
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
        $hasDedicatedPacking = collect($lineEvidence)->flatMap(fn ($line) => $line['allocations'])->isNotEmpty();
        $missingPaperwork = collect($paperwork['missing'])->reject(fn ($label) => $hasDedicatedPacking && $label === 'Packing List')->values()->all();
        if ($missingPaperwork !== []) $issues[] = 'Mandatory shipment paperwork is incomplete: '.implode(', ', $missingPaperwork).'.';
        $issues = array_values(array_unique($issues));
        return [
            'shipment_id'=>$shipment->id, 'shipment_number'=>$shipment->shipment_number,
            'project_id'=>$shipment->project_id, 'lines'=>$lineEvidence, 'products'=>$productEvidence,
            'paperwork'=>['complete'=>$missingPaperwork === [], 'missing'=>$missingPaperwork, 'dedicated_packing_allocated'=>$hasDedicatedPacking],
            'issues'=>$issues,
        ];
    }

    public function rowsFromShipment(Shipment $shipment): array
    {
        $shipment->loadMissing('items.packingAllocations');
        return $shipment->items->map(fn ($item) => array_merge(
            $item->only(['project_product_id','product_name','quantity','unit']),
            ['allocations'=>$item->packingAllocations->map->only(['packaging_list_item_id','allocated_quantity'])->values()->all()]
        ))->values()->all();
    }

    public function allocationOptions(Shipment $shipment): Collection
    {
        if (! $shipment->project_id) return collect();
        PackagingList::query()->where('project_id', $shipment->project_id)
            ->whereIn('status', ['confirmed', 'dispatched'])->with('cartons.items')->get()
            ->each(fn ($list) => $this->packingLinker->reconcile($list));
        return PackagingListItem::query()
            ->whereNotNull('project_product_id')
            ->whereHas('carton.packagingList', fn ($query) => $query->where('project_id', $shipment->project_id)->whereIn('status', ['confirmed', 'dispatched']))
            ->with(['carton.packagingList', 'carton.packagingList.cartons.items'])
            ->get()
            ->map(function ($item) use ($shipment) {
                $list = $item->carton->packagingList;
                $current = $this->verifier->inspect($list)['failed'] === 0;
                $reserved = (float) ShipmentPackingAllocation::query()->where('packaging_list_item_id', $item->id)
                    ->whereHas('shipmentItem.shipment', fn ($query) => $query->where('status', '!=', Shipment::STATUS_CANCELLED)->when($shipment->exists, fn ($nested) => $nested->where('id', '!=', $shipment->id)))
                    ->sum('allocated_quantity');
                $item->setAttribute('dispatch_available_quantity', round(max((float)$item->total_quantity-$reserved, 0), 3));
                $item->setAttribute('dispatch_verification_current', $current);
                return $item;
            })->filter(fn ($item) => ((bool)$item->dispatch_verification_current && (float)$item->dispatch_available_quantity > 0)
                || $shipment->items->flatMap->packingAllocations->contains('packaging_list_item_id', $item->id))->values();
    }
}
