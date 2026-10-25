<?php

namespace App\Services;

use App\Models\PackagingList;
use App\Models\ProjectGateEvaluation;
use App\Models\ProjectProduct;
use App\Models\ProjectWorkflow;
use App\Models\ProjectWorkflowStep;
use App\Models\PurchaseInvoiceItem;
use App\Models\Shipment;
use App\Models\ShipmentDeliveryReceiptLine;
use App\Models\ShipmentItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProjectOperationalReadiness
{
    public const GATE_PROCUREMENT = 'procurement_ready';
    public const GATE_PRODUCTION = 'production_complete';
    public const GATE_QUALITY = 'quality_passed';
    public const GATE_PACKING = 'packing_verified';
    public const GATE_SHIPMENT = 'shipment_dispatched';
    public const GATE_DELIVERY = 'delivery_complete';
    public const GATE_DELIVERY_RECONCILED = 'delivery_reconciled';
    public const PRODUCT_CONTROLLED_STEPS = [
        self::GATE_PROCUREMENT, self::GATE_PRODUCTION, self::GATE_QUALITY,
        self::GATE_PACKING, self::GATE_SHIPMENT, self::GATE_DELIVERY,
    ];
    public const CONTROLLED_STEPS = [...self::PRODUCT_CONTROLLED_STEPS, self::GATE_DELIVERY_RECONCILED];

    public function __construct(
        private readonly PackagingListVerifier $packingVerifier,
        private readonly PackagingListProjectLinker $packingLinker,
    ) {}

    public function evaluateAll(ProjectWorkflow $workflow, ?int $userId = null): array
    {
        return DB::transaction(function () use ($workflow, $userId) {
            $workflow->loadMissing('project.products.qualityInspections');
            app(ProjectWorkflowExecution::class)->sync($workflow, $workflow->project);
            $evaluations = [];
            $deliveryEvidence = [];
            foreach ($workflow->project->products->where('status', '!=', 'cancelled') as $product) {
                $productEvaluations = $this->evaluateProduct($workflow, $product, $userId);
                foreach ($productEvaluations as $evaluation) {
                    $this->applyToStep($evaluation);
                    $evaluations[] = $evaluation;
                }
                $delivery = collect($productEvaluations)->firstWhere('gate_key', self::GATE_DELIVERY);
                $deliveryEvidence[] = [
                    'project_product_id' => $product->id,
                    'product_name' => $product->product_name,
                    'evaluation_id' => $delivery?->id,
                    'result' => $delivery?->effectiveResult(),
                    'required_quantity' => (float) $product->quantity,
                    'delivered_quantity' => (float) data_get($delivery?->details, 'delivered_quantity', 0),
                    'remaining_quantity' => (float) data_get($delivery?->details, 'remaining_quantity', $product->quantity),
                ];
            }
            $allDelivered = $deliveryEvidence !== [] && collect($deliveryEvidence)->every(fn ($row) => in_array($row['result'], ['passed', 'overridden'], true));
            $aggregate = $this->recordProject($workflow, self::GATE_DELIVERY_RECONCILED, [
                'products' => $deliveryEvidence,
                'product_count' => count($deliveryEvidence),
                'delivered_product_count' => collect($deliveryEvidence)->whereIn('result', ['passed', 'overridden'])->count(),
            ], $allDelivered, $allDelivered ? 'Every active project product is fully delivered or covered by a current manager override.' : 'One or more project products still have undelivered quantities.', $userId);
            $this->applyToStep($aggregate);
            $evaluations[] = $aggregate;
            app(ProjectWorkflowExecution::class)->recalculate($workflow);
            return $evaluations;
        });
    }

    private function evaluateProduct(ProjectWorkflow $workflow, ProjectProduct $product, ?int $userId): array
    {
        $required = round((float) $product->quantity, 3);
        $poLines = PurchaseInvoiceItem::query()
            ->where('project_product_id', $product->id)
            ->whereHas('invoice', fn ($query) => $query
                ->where('invoice_type', 'order')
                ->where('vendor_id', $product->vendor_id)
                ->whereIn('status', ['sent', 'approved', 'billed']))
            ->with('invoice:id,invoice_number,status,vendor_id')
            ->get();
        $ordered = round((float) $poLines->sum('quantity'), 3);
        $vendorAssigned = (bool) $product->vendor_id;
        $procurementPassed = $vendorAssigned && $required > 0 && $ordered + 0.0001 >= $required;

        $production = round(min((float) $product->production_completed_quantity, $required), 3);
        $productionEvidence = filled($product->production_evidence) && $product->production_recorded_at;
        $productionStateComplete = in_array($product->status, ['qc_pending', 'ready', 'shipped', 'delivered'], true);
        $productionPassed = $required > 0 && $production + 0.0001 >= $required && $productionEvidence && $productionStateComplete;

        $latestQc = $product->qualityInspections->sortByDesc('inspected_at')->first();
        $sampledQc = round((float) ($latestQc?->inspected_quantity ?: 0), 3);
        $passedQc = round((float) ($latestQc?->passed_quantity ?: 0), 3);
        $qualityPassed = $required > 0 && $sampledQc > 0
            && in_array($latestQc?->result, ['passed', 'passed_with_observations'], true);

        [$packed, $packingLists] = $this->verifiedPackedQuantity($product);
        $packingPassed = $required > 0 && $packed + 0.0001 >= $required;

        [$dispatched, $delivered, $shipmentEvidence] = $this->shipmentQuantities($product);
        $shipmentPassed = $required > 0 && $dispatched + 0.0001 >= $required;
        $deliveryPassed = $required > 0 && $delivered + 0.0001 >= $required;

        return [
            $this->record($workflow, $product, self::GATE_PROCUREMENT, [
                'required_quantity' => $required, 'vendor_id' => $product->vendor_id,
                'vendor_assigned' => $vendorAssigned, 'valid_po_quantity' => $ordered,
                'valid_po_lines' => $poLines->map(fn ($line) => ['line_id'=>$line->id, 'po_id'=>$line->purchase_invoice_id, 'po_number'=>$line->invoice?->invoice_number, 'status'=>$line->invoice?->status, 'quantity'=>(float)$line->quantity])->values()->all(),
                'remaining_quantity' => round(max($required - $ordered, 0), 3),
            ], $procurementPassed, $procurementPassed ? 'Vendor and valid purchase-order quantity cover this product.' : ($vendorAssigned ? 'Valid purchase-order quantity is short by '.number_format(max($required-$ordered, 0), 3).'.' : 'Assign a vendor and link valid purchase-order lines to this product.'), $userId),
            $this->record($workflow, $product, self::GATE_PRODUCTION, [
                'required_quantity'=>$required, 'production_completed_quantity'=>$production,
                'remaining_quantity'=>round(max($required-$production, 0), 3),
                'production_evidence'=>$product->production_evidence,
                'production_recorded_at'=>$product->production_recorded_at?->toISOString(),
                'production_recorded_by'=>$product->production_recorded_by, 'operational_status'=>$product->status,
                'operational_state_complete'=>$productionStateComplete,
            ], $productionPassed, $productionPassed ? 'Recorded production quantity, evidence and operational state cover this product.' : (! $productionStateComplete ? 'Move the product to QC pending or a later operational state after production evidence is complete.' : ($productionEvidence ? 'Production remains partial: '.number_format($production, 3).' of '.number_format($required, 3).' recorded.' : 'Record production quantity and operational evidence.')), $userId),
            $this->record($workflow, $product, self::GATE_QUALITY, [
                'required_quantity'=>$required, 'sample_inspected_quantity'=>$sampledQc, 'sample_passed_quantity'=>$passedQc,
                'sample_rejected_quantity'=>(float) ($latestQc?->rejected_quantity ?: 0),
                'sample_rework_quantity'=>(float) ($latestQc?->rework_quantity ?: 0),
                'latest_inspection_id'=>$latestQc?->id, 'latest_result'=>$latestQc?->result,
                'inspection_ids'=>$product->qualityInspections->pluck('id')->values()->all(),
                'inspection_count'=>$product->qualityInspections->count(),
            ], $qualityPassed,
                $qualityPassed
                    ? 'Latest QC sample passed: '.number_format($passedQc, 3).' of '.number_format($sampledQc, 3).' sampled units passed.'
                    : ($latestQc ? 'Latest QC sample result is '.$latestQc->resultLabel().'; record a passing sample inspection to continue.' : 'Record a representative QC sample inspection to continue.'),
                $userId),
            $this->record($workflow, $product, self::GATE_PACKING, [
                'required_quantity'=>$required, 'verified_packed_quantity'=>$packed,
                'remaining_quantity'=>round(max($required-$packed, 0), 3), 'packaging_lists'=>$packingLists,
            ], $packingPassed, $packingPassed ? 'Confirmed Packaging Lists pass automatic checks and cover this product.' : 'Confirmed packing that passes automatic checks covers '.number_format($packed, 3).' of '.number_format($required, 3).'; the balance remains open.', $userId),
            $this->record($workflow, $product, self::GATE_SHIPMENT, [
                'required_quantity'=>$required, 'dispatched_quantity'=>$dispatched,
                'remaining_quantity'=>round(max($required-$dispatched, 0), 3),
                'excess_quantity'=>round(max($dispatched-$required, 0), 3), 'shipments'=>$shipmentEvidence,
            ], $shipmentPassed, $shipmentPassed ? ($dispatched > $required + 0.0001 ? 'Dispatch exceeds the required quantity by '.number_format($dispatched-$required, 3).'; review the linked shipment lines.' : 'Dispatched shipment quantities cover this product.') : 'Dispatch covers '.number_format($dispatched, 3).' of '.number_format($required, 3).'; the remaining quantity stays open.', $userId),
            $this->record($workflow, $product, self::GATE_DELIVERY, [
                'required_quantity'=>$required, 'delivered_quantity'=>$delivered,
                'remaining_quantity'=>round(max($required-$delivered, 0), 3),
                'excess_quantity'=>round(max($delivered-$required, 0), 3), 'shipments'=>$shipmentEvidence,
            ], $deliveryPassed, $deliveryPassed ? ($delivered > $required + 0.0001 ? 'Net accepted delivery receipts exceed the requirement by '.number_format($delivered-$required, 3).'; review receipt lines.' : 'Net accepted delivery receipts cover this product.') : 'Accepted delivery receipts cover '.number_format($delivered, 3).' of '.number_format($required, 3).'; the remaining quantity or discrepancy stays open.', $userId),
        ];
    }

    private function verifiedPackedQuantity(ProjectProduct $product): array
    {
        $lists = PackagingList::query()
            ->where('project_id', $product->project_id)
            ->whereIn('status', ['confirmed', 'dispatched'])
            ->with('cartons.items')
            ->get();
        $quantity = 0.0; $evidence = [];
        foreach ($lists as $list) {
            $linking = $this->packingLinker->reconcile($list);
            $inspection = $this->packingVerifier->inspect($list);
            $automaticChecksPassed = $inspection['failed'] === 0;
            $listQuantity = round((float) $list->cartons->flatMap->items->where('project_product_id', $product->id)->sum('total_quantity'), 3);
            $evidence[] = [
                'packaging_list_id'=>$list->id, 'packing_number'=>$list->packing_number,
                'status'=>$list->status, 'quantity'=>$listQuantity,
                'automatic_checks_passed'=>$automaticChecksPassed,
                'failed_checks'=>$inspection['failed'], 'warning_checks'=>$inspection['warnings'],
                'project_items_linked'=>$linking['linked'], 'unresolved_project_items'=>$linking['unresolved'],
            ];
            if ($automaticChecksPassed) $quantity += $listQuantity;
        }
        return [round($quantity, 3), $evidence];
    }

    private function shipmentQuantities(ProjectProduct $product): array
    {
        $dispatchedStatuses = Shipment::dispatchedStatuses();
        $lines = ShipmentItem::query()
            ->where('project_product_id', $product->id)
            ->whereHas('shipment', fn ($query) => $query->where('project_id', $product->project_id)->whereIn('status', $dispatchedStatuses))
            ->with('shipment:id,shipment_number,status,pickup_date,drop_date')
            ->get();
        $dispatched = round((float) $lines->sum('quantity'), 3);
        $receiptLines = ShipmentDeliveryReceiptLine::query()
            ->where('project_product_id', $product->id)
            ->whereHas('receipt.shipment', fn ($query) => $query->where('project_id', $product->project_id)->whereIn('status', $dispatchedStatuses))
            ->with(['receipt:id,shipment_id,receipt_reference,recipient_name,received_at,evidence_hash', 'receipt.shipment:id,shipment_number,status'])
            ->get();
        $delivered = round(max((float)$receiptLines->sum('accepted_quantity') - (float)$receiptLines->sum('returned_quantity'), 0), 3);
        $shipmentEvidence = $lines->map(fn ($line) => [
            'shipment_item_id'=>$line->id, 'shipment_id'=>$line->shipment_id,
            'shipment_number'=>$line->shipment?->shipment_number, 'status'=>$line->shipment?->status,
            'dispatched_quantity'=>(float)$line->quantity,
            'pickup_date'=>$line->shipment?->pickup_date?->toDateString(),
            'drop_date'=>$line->shipment?->drop_date?->toDateString(),
        ])->values();
        $receiptEvidence = $receiptLines->map(fn ($line) => [
            'delivery_receipt_id'=>$line->shipment_delivery_receipt_id,
            'shipment_item_id'=>$line->shipment_item_id,
            'shipment_id'=>$line->receipt?->shipment_id,
            'shipment_number'=>$line->receipt?->shipment?->shipment_number,
            'receipt_reference'=>$line->receipt?->receipt_reference,
            'received_at'=>$line->receipt?->received_at?->toISOString(),
            'accepted_quantity'=>(float)$line->accepted_quantity,
            'short_quantity'=>(float)$line->short_quantity,
            'damaged_quantity'=>(float)$line->damaged_quantity,
            'rejected_quantity'=>(float)$line->rejected_quantity,
            'returned_quantity'=>(float)$line->returned_quantity,
            'replacement_quantity'=>(float)$line->replacement_quantity,
            'evidence_hash'=>$line->receipt?->evidence_hash,
        ])->values();
        return [$dispatched, $delivered, ['shipments'=>$shipmentEvidence->all(), 'delivery_receipts'=>$receiptEvidence->all()]];
    }

    private function record(ProjectWorkflow $workflow, ProjectProduct $product, string $key, array $details, bool $passed, string $summary, ?int $userId): ProjectGateEvaluation
    {
        $hash = hash('sha256', json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $latest = $workflow->evaluations()->where('project_product_id', $product->id)->where('gate_key', $key)->latest('evaluated_at')->latest('id')->first();
        if ($latest && hash_equals((string) $latest->content_hash, $hash)) return $latest;
        return $workflow->evaluations()->create([
            'project_product_id'=>$product->id, 'gate_key'=>$key, 'result'=>$passed ? 'passed' : 'blocked',
            'content_hash'=>$hash, 'summary'=>$summary, 'details'=>$details,
            'evaluated_by'=>$userId, 'evaluated_at'=>now(),
        ]);
    }

    private function recordProject(ProjectWorkflow $workflow, string $key, array $details, bool $passed, string $summary, ?int $userId): ProjectGateEvaluation
    {
        $hash = hash('sha256', json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $latest = $workflow->evaluations()->whereNull('project_product_id')->where('gate_key', $key)->latest('evaluated_at')->latest('id')->first();
        if ($latest && hash_equals((string) $latest->content_hash, $hash)) return $latest;
        return $workflow->evaluations()->create([
            'project_product_id'=>null, 'gate_key'=>$key, 'result'=>$passed ? 'passed' : 'blocked',
            'content_hash'=>$hash, 'summary'=>$summary, 'details'=>$details,
            'evaluated_by'=>$userId, 'evaluated_at'=>now(),
        ]);
    }

    public function applyToStep(ProjectGateEvaluation $evaluation): void
    {
        $evaluation->loadMissing('activeOverride');
        $step = ProjectWorkflowStep::query()->where('project_workflow_id', $evaluation->project_workflow_id)
            ->where('project_product_id', $evaluation->project_product_id)->where('step_key', $evaluation->gate_key)->first();
        if (! $step) return;
        $overridden = (bool) $evaluation->activeOverride; $passed = $evaluation->result === 'passed';
        $step->update([
            'status'=>$passed ? 'completed' : ($overridden ? 'skipped' : 'blocked'),
            'blocked_reason'=>$passed || $overridden ? null : $evaluation->summary,
            'notes'=>$overridden ? 'Gate continued under manager override #'.$evaluation->activeOverride->id.'.' : $evaluation->summary,
            'actual_started_at'=>($step->actual_started_at ?: now()),
            'completed_at'=>(($passed || $overridden) ? now() : null),
            'completed_by'=>(($passed || $overridden) ? ($evaluation->evaluated_by ?: Auth::id()) : null),
        ]);
    }
}
