<?php

namespace App\Services;

use App\Models\ProjectGateEvaluation;
use App\Models\ProjectWorkflow;
use App\Models\ProjectWorkflowStep;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProjectWorkflowGates
{
    public const GATE_BASELINE = 'commercial_baseline';
    public const GATE_ADVANCE = 'advance_received';
    public const GATE_DISPATCH_BALANCE = 'dispatch_balance';
    public const GATE_DISPATCH_AUTHORIZATION = 'dispatch_approved';
    public const AUTOMATED_GATES = [self::GATE_BASELINE, self::GATE_ADVANCE, self::GATE_DISPATCH_BALANCE, self::GATE_DISPATCH_AUTHORIZATION];

    public function evaluateAll(ProjectWorkflow $workflow, ?int $userId = null): array
    {
        return DB::transaction(function () use ($workflow, $userId) {
            $workflow->load('commercialInvoice.convertedInvoice');
            $baseline = $workflow->commercialInvoice;
            $invoice = $baseline?->isSuperseded() ? $baseline->convertedInvoice : $baseline;
            $documents = $baseline?->settlementDocuments() ?? collect();
            $invoiceUsable = $baseline && $baseline->status !== 'cancelled' && $documents->isNotEmpty()
                && $documents->every(fn ($document) => $document->status !== 'cancelled');
            $received = round((float) $documents->sum(fn ($document) => $document->clientPortalReceivedAmount()), 3);
            $invoiceTotal = round((float) $documents->sum(fn ($document) => $document->receivableTotal()), 3);
            $tolerance = $baseline ? (float) $baseline->payment_tolerance : 0.0;
            $advancePercentage = $workflow->advance_percentage_override !== null
                ? (float) $workflow->advance_percentage_override
                : ($baseline && $baseline->advance_required ? (float) ($baseline->advance_percentage ?? 0) : 0.0);
            $advanceRequired = round($invoiceTotal * $advancePercentage / 100, 3);

            $evaluations = [];
            $evaluations[] = $this->record($workflow, self::GATE_BASELINE, [
                'invoice_id' => $baseline?->id,
                'invoice_number' => $baseline?->invoice_number,
                'invoice_status' => $baseline?->status,
                'settlement_invoice_id' => $invoice?->id,
                'settlement_invoice_status' => $invoice?->status,
                'invoice_usable' => $invoiceUsable,
                'invoice_total' => $invoiceTotal,
                'settlement_invoice_ids' => $documents->pluck('id')->all(),
                'invoice_updated_at' => $invoice?->updated_at?->toISOString(),
            ], $invoiceUsable ? 'passed' : 'blocked', $invoiceUsable
                ? 'Commercial baseline '.$baseline->invoice_number.' is selected'.($baseline->isSuperseded() ? ' and continues as '.$invoice->invoice_number.'.' : '.')
                : ($baseline ? 'The selected baseline Proforma or its tax invoice is cancelled.' : 'Select a project Proforma Invoice as the commercial baseline.'), $userId);

            $advancePassed = $invoiceUsable && $received + $tolerance >= $advanceRequired;
            $evaluations[] = $this->record($workflow, self::GATE_ADVANCE, [
                'baseline_invoice_id' => $baseline?->id,
                'invoice_id' => $invoice?->id,
                'invoice_status' => $invoice?->status,
                'invoice_usable' => $invoiceUsable,
                'invoice_total' => $invoiceTotal,
                'settlement_invoice_ids' => $documents->pluck('id')->all(),
                'advance_percentage' => $advancePercentage,
                'advance_source' => $workflow->advance_percentage_override !== null ? 'project_override' : 'proforma_terms',
                'required_amount' => $advanceRequired,
                'received_amount' => $received,
                'shortfall' => round(max($advanceRequired - $received, 0), 3),
                'tolerance' => $tolerance,
            ], $advancePassed ? 'passed' : 'blocked', ! $invoiceUsable
                ? ($baseline ? 'Advance cannot use a cancelled baseline Proforma or tax invoice.' : 'Advance cannot be evaluated until a baseline Proforma is selected.')
                : ($advancePassed
                    ? 'Required production advance has been received.'
                    : 'Production advance is short by '.\App\Helpers\CommonHelper::decimal(max($advanceRequired - $received, 0)).'.'), $userId);

            $requiresBalance = (bool) ($baseline?->balance_before_dispatch);
            $balanceRequired = $requiresBalance ? $invoiceTotal : 0.0;
            $dispatchPassed = $invoiceUsable && ($received + $tolerance >= $balanceRequired);
            $evaluations[] = $this->record($workflow, self::GATE_DISPATCH_BALANCE, [
                'baseline_invoice_id' => $baseline?->id,
                'invoice_id' => $invoice?->id,
                'invoice_status' => $invoice?->status,
                'invoice_usable' => $invoiceUsable,
                'invoice_total' => $invoiceTotal,
                'settlement_invoice_ids' => $documents->pluck('id')->all(),
                'complete_balance_required' => $requiresBalance,
                'required_amount' => $balanceRequired,
                'received_amount' => $received,
                'shortfall' => round(max($balanceRequired - $received, 0), 3),
                'tolerance' => $tolerance,
            ], $dispatchPassed ? 'passed' : 'blocked', ! $invoiceUsable
                ? ($baseline ? 'Dispatch cannot use a cancelled baseline Proforma or tax invoice.' : 'Dispatch balance cannot be evaluated until a baseline Proforma is selected.')
                : ($dispatchPassed
                    ? ($requiresBalance ? 'Complete pre-dispatch balance has been received.' : 'The baseline Proforma does not require a pre-dispatch balance.')
                    : 'Dispatch is blocked by an outstanding balance of '.\App\Helpers\CommonHelper::decimal(max($balanceRequired - $received, 0)).'.'), $userId);

            foreach ($evaluations as $evaluation) {
                $this->applyToStep($evaluation);
            }
            app(ProjectWorkflowExecution::class)->recalculate($workflow);

            return $evaluations;
        });
    }

    public function applyToStep(ProjectGateEvaluation $evaluation): void
    {
        $evaluation->loadMissing('activeOverride');
        $step = ProjectWorkflowStep::query()
            ->where('project_workflow_id', $evaluation->project_workflow_id)
            ->whereNull('project_product_id')
            ->where('step_key', $evaluation->gate_key)
            ->first();
        if (! $step) return;

        $overridden = (bool) $evaluation->activeOverride;
        $passed = $evaluation->result === 'passed';
        $step->update([
            'status' => $passed ? 'completed' : ($overridden ? 'skipped' : 'blocked'),
            'blocked_reason' => $passed || $overridden ? null : $evaluation->summary,
            'notes' => $overridden ? 'Gate continued under manager override #'.$evaluation->activeOverride->id.'.' : $evaluation->summary,
            'actual_started_at' => $step->actual_started_at ?: now(),
            'completed_at' => $passed || $overridden ? now() : null,
            'completed_by' => $passed || $overridden ? ($evaluation->evaluated_by ?: Auth::id()) : null,
        ]);
    }

    private function record(ProjectWorkflow $workflow, string $gateKey, array $details, string $result, string $summary, ?int $userId): ProjectGateEvaluation
    {
        $hash = hash('sha256', json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $latest = $workflow->evaluations()->where('gate_key', $gateKey)->latest('evaluated_at')->latest('id')->first();
        if ($latest && hash_equals((string) $latest->content_hash, $hash)) return $latest;

        return $workflow->evaluations()->create([
            'gate_key' => $gateKey,
            'result' => $result,
            'content_hash' => $hash,
            'summary' => $summary,
            'details' => $details,
            'evaluated_by' => $userId,
            'evaluated_at' => now(),
        ]);
    }
}
