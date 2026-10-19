<?php

namespace App\Services;

use App\Models\PackagingList;
use App\Models\ProjectGateEvaluation;
use App\Models\ProjectProduct;
use App\Models\ProjectWorkflow;
use App\Models\ProjectWorkflowStep;
use App\Models\PurchaseInvoiceItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProjectOperationalReadiness
{
    public const GATE_PROCUREMENT = 'procurement_ready';
    public const GATE_PRODUCTION = 'production_complete';
    public const GATE_QUALITY = 'quality_passed';
    public const GATE_PACKING = 'packing_verified';
    public const CONTROLLED_STEPS = [self::GATE_PROCUREMENT, self::GATE_PRODUCTION, self::GATE_QUALITY, self::GATE_PACKING];

    public function __construct(private readonly PackagingListVerifier $packingVerifier) {}

    public function evaluateAll(ProjectWorkflow $workflow, ?int $userId = null): array
    {
        return DB::transaction(function () use ($workflow, $userId) {
            $workflow->loadMissing('project.products.qualityInspections');
            $evaluations = [];
            foreach ($workflow->project->products->where('status', '!=', 'cancelled') as $product) {
                foreach ($this->evaluateProduct($workflow, $product, $userId) as $evaluation) {
                    $this->applyToStep($evaluation);
                    $evaluations[] = $evaluation;
                }
            }
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

        $passedQc = round((float) $product->qualityInspections->sum('passed_quantity'), 3);
        $qualityPassed = $required > 0 && $passedQc + 0.0001 >= $required;

        [$packed, $packingLists] = $this->verifiedPackedQuantity($product);
        $packingPassed = $required > 0 && $packed + 0.0001 >= $required;

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
                'required_quantity'=>$required, 'passed_quantity'=>$passedQc,
                'remaining_quantity'=>round(max($required-$passedQc, 0), 3),
                'inspection_ids'=>$product->qualityInspections->pluck('id')->values()->all(),
                'inspection_count'=>$product->qualityInspections->count(),
            ], $qualityPassed, $qualityPassed ? 'Immutable passed QC quantities cover this product.' : 'QC passed '.number_format($passedQc, 3).' of '.number_format($required, 3).'; the balance remains visible.', $userId),
            $this->record($workflow, $product, self::GATE_PACKING, [
                'required_quantity'=>$required, 'verified_packed_quantity'=>$packed,
                'remaining_quantity'=>round(max($required-$packed, 0), 3), 'packaging_lists'=>$packingLists,
            ], $packingPassed, $packingPassed ? 'Current verified Packaging Lists cover this product.' : 'Verified packing covers '.number_format($packed, 3).' of '.number_format($required, 3).'; the balance remains open.', $userId),
        ];
    }

    private function verifiedPackedQuantity(ProjectProduct $product): array
    {
        $lists = PackagingList::query()
            ->where('project_id', $product->project_id)
            ->where('status', '!=', 'cancelled')
            ->whereHas('cartons.items', fn ($query) => $query->where('project_product_id', $product->id))
            ->with(['cartons.items', 'latestVerification'])
            ->get();
        $quantity = 0.0; $evidence = [];
        foreach ($lists as $list) {
            $verification = $list->latestVerification;
            $current = $verification && $verification->passed()
                && hash_equals((string) $verification->content_hash, $this->packingVerifier->fingerprint($list));
            $listQuantity = round((float) $list->cartons->flatMap->items->where('project_product_id', $product->id)->sum('total_quantity'), 3);
            $evidence[] = ['packaging_list_id'=>$list->id, 'packing_number'=>$list->packing_number, 'quantity'=>$listQuantity, 'verification_id'=>$verification?->id, 'verification_current'=>$current];
            if ($current) $quantity += $listQuantity;
        }
        return [round($quantity, 3), $evidence];
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
