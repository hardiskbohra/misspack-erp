<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectFinancialClosure as Closure;
use App\Models\ProjectGateEvaluation;
use App\Models\ProjectLog;
use App\Models\ProjectWorkflow;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectFinancialClosure
{
    public const GATE = 'commercial_closure';
    public const CONTROLLED_STEPS = [self::GATE];

    public function snapshot(Project $project): array
    {
        $project->loadMissing([
            'taxInvoices.payments', 'taxInvoices.cashflowAllocations.cashflowEntry',
            'proformaInvoices.payments', 'proformaInvoices.cashflowAllocations.cashflowEntry', 'proformaInvoices.convertedInvoice',
            'bills.payments', 'bills.cashflowAllocations.cashflowEntry',
        ]);

        $sales = $project->taxInvoices->concat($project->proformaInvoices)
            ->filter(fn ($invoice) => $invoice->countsTowardsProject())
            ->sortBy('id')->values();
        $bills = $project->bills
            ->filter(fn ($bill) => $bill->status !== 'cancelled')
            ->sortBy('id')->values();

        $receivables = $sales->map(fn ($invoice) => [
            'source_type' => 'sales_invoice',
            'source_id' => $invoice->id,
            'document_number' => $invoice->invoice_number,
            'document_type' => $invoice->invoice_type,
            'status' => $invoice->status,
            'currency' => strtoupper((string) ($invoice->currency ?: 'INR')),
            'document_total' => round((float) $invoice->total_amount, 2),
            'allocated_receipts' => round($invoice->receivedAmount(), 2),
            'outstanding' => round($invoice->balanceDue(), 2),
            'advance' => round($invoice->advanceAmount(), 2),
            'settlement_sources' => $invoice->payments->map(fn ($entry) => [
                'source' => 'direct_cashflow', 'id' => $entry->id, 'date' => $entry->entry_date?->format('Y-m-d'),
                'credit' => round((float) $entry->credit_amount, 2), 'debit' => round((float) $entry->debit_amount, 2),
                'currency' => strtoupper((string) ($entry->currency ?: $invoice->currency ?: 'INR')),
                'accounting_status' => $entry->accounting_status, 'updated_at' => $entry->updated_at?->toISOString(),
            ])->concat($invoice->cashflowAllocations->map(fn ($allocation) => [
                'source' => 'cashflow_allocation', 'id' => $allocation->id, 'cashflow_entry_id' => $allocation->cashflow_entry_id,
                'amount' => round((float) $allocation->amount, 2), 'document_amount' => round($allocation->documentAmount(), 2),
                'currency' => strtoupper((string) ($allocation->currency ?: 'INR')), 'document_currency' => strtoupper((string) ($allocation->document_currency ?: $invoice->currency ?: 'INR')),
                'accounting_status' => $allocation->cashflowEntry?->accounting_status, 'updated_at' => $allocation->updated_at?->toISOString(),
            ]))->values()->all(),
            'updated_at' => $invoice->updated_at?->toISOString(),
        ])->all();

        $payables = $bills->map(fn ($bill) => [
            'source_type' => 'purchase_bill',
            'source_id' => $bill->id,
            'document_number' => $bill->display_number,
            'document_type' => $bill->invoice_type,
            'status' => $bill->status,
            'currency' => strtoupper((string) ($bill->currency ?: 'INR')),
            'document_total' => round((float) $bill->total_amount, 2),
            'allocated_payments' => round($bill->paidAmount(), 2),
            'outstanding' => round($bill->balanceDue(), 2),
            'advance' => round($bill->advanceAmount(), 2),
            'settlement_sources' => $bill->payments->map(fn ($entry) => [
                'source' => 'vendor_ledger', 'id' => $entry->id, 'cashflow_entry_id' => $entry->cashflow_entry_id,
                'date' => $entry->transaction_date?->format('Y-m-d'), 'amount' => round((float) $entry->foreign_amount, 2),
                'currency' => strtoupper((string) ($entry->foreign_currency ?: $bill->currency ?: 'INR')),
                'status' => $entry->status, 'updated_at' => $entry->updated_at?->toISOString(),
            ])->concat($bill->cashflowAllocations->map(fn ($allocation) => [
                'source' => 'cashflow_allocation', 'id' => $allocation->id, 'cashflow_entry_id' => $allocation->cashflow_entry_id,
                'amount' => round((float) $allocation->amount, 2), 'document_amount' => round($allocation->documentAmount(), 2),
                'currency' => strtoupper((string) ($allocation->currency ?: 'INR')), 'document_currency' => strtoupper((string) ($allocation->document_currency ?: $bill->currency ?: 'INR')),
                'accounting_status' => $allocation->cashflowEntry?->accounting_status, 'updated_at' => $allocation->updated_at?->toISOString(),
            ]))->values()->all(),
            'updated_at' => $bill->updated_at?->toISOString(),
        ])->all();

        $unallocated = app(UnallocatedCashflows::class)->forProject($project->id)['entries']
            ->sortBy('id')->map(fn ($entry) => [
                'source_type' => 'cashflow_entry',
                'source_id' => $entry->id,
                'entry_date' => $entry->entry_date?->format('Y-m-d'),
                'transaction_type' => $entry->transaction_type,
                'particular' => $entry->particular,
                'reference' => $entry->bank_reference_number ?: $entry->invoice_bill_number,
                'currency' => strtoupper((string) ($entry->currency ?: 'INR')),
                'amount_moved' => round($entry->amountMoved(), 2),
                'allocated' => round($entry->allocatedAmount(), 2),
                'unallocated' => round($entry->unallocatedAmount(), 2),
                'accounting_status' => $entry->accounting_status,
                'allocation_sources' => $entry->allocations->sortBy('id')->map(fn ($allocation) => [
                    'id' => $allocation->id, 'project_id' => $allocation->project_id,
                    'sales_invoice_id' => $allocation->sales_invoice_id, 'purchase_invoice_id' => $allocation->purchase_invoice_id,
                    'amount' => round((float) $allocation->amount, 2), 'document_amount' => round($allocation->documentAmount(), 2),
                    'updated_at' => $allocation->updated_at?->toISOString(),
                ])->values()->all(),
                'updated_at' => $entry->updated_at?->toISOString(),
            ])->values()->all();

        $totals = [
            'receivables' => $this->totals($receivables, 'outstanding'),
            'payables' => $this->totals($payables, 'outstanding'),
            'client_advances' => $this->totals($receivables, 'advance'),
            'vendor_advances' => $this->totals($payables, 'advance'),
            'unallocated_client_cash' => $this->totals(array_values(array_filter($unallocated, fn ($row) => $row['transaction_type'] === 'credit')), 'unallocated'),
            'unallocated_vendor_cash' => $this->totals(array_values(array_filter($unallocated, fn ($row) => $row['transaction_type'] === 'debit')), 'unallocated'),
        ];
        $openSteps = $project->workflow?->steps
            ->reject(fn ($step) => $step->step_key === self::GATE)
            ->whereNotIn('status', ['completed', 'skipped', 'cancelled'])
            ->map(fn ($step) => ['step_key' => $step->step_key, 'title' => $step->title, 'status' => $step->status])
            ->values()->all() ?? [];

        $snapshot = [
            'schema_version' => 1,
            'project_id' => $project->id,
            'project_number' => $project->project_number,
            'receivables' => $receivables,
            'payables' => $payables,
            'unallocated_cash' => $unallocated,
            'totals' => $totals,
            'open_operational_steps' => $openSteps,
        ];
        $snapshot['has_outstanding_financials'] = collect($totals)->flatten()->contains(fn ($amount) => (float) $amount > 0.009);
        $snapshot['operationally_ready'] = count($openSteps) === 0;
        $snapshot['content_hash'] = hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $snapshot;
    }

    public function evaluate(ProjectWorkflow $workflow, ?int $userId = null): ProjectGateEvaluation
    {
        $workflow->loadMissing('project', 'steps');
        $snapshot = $this->snapshot($workflow->project);
        $passed = $snapshot['operationally_ready'] && ! $snapshot['has_outstanding_financials'];
        $summary = ! $snapshot['operationally_ready']
            ? count($snapshot['open_operational_steps']).' operational workflow step(s) remain open.'
            : ($snapshot['has_outstanding_financials']
                ? 'Financial obligations or unapplied project cash remain. Manager exception is required to close.'
                : 'Operational work and all financial categories are reconciled.');

        $latest = $workflow->evaluations()->where('gate_key', self::GATE)->whereNull('project_product_id')->latest('evaluated_at')->latest('id')->first();
        if ($latest && hash_equals((string) $latest->content_hash, $snapshot['content_hash'])) {
            app(ProjectWorkflowGates::class)->applyToStep($latest);
            return $latest;
        }

        $evaluation = $workflow->evaluations()->create([
            'gate_key' => self::GATE,
            'result' => $passed ? 'passed' : 'blocked',
            'content_hash' => $snapshot['content_hash'],
            'summary' => $summary,
            'details' => $snapshot,
            'evaluated_by' => $userId,
            'evaluated_at' => now(),
        ]);
        app(ProjectWorkflowGates::class)->applyToStep($evaluation);
        app(ProjectWorkflowExecution::class)->recalculate($workflow);

        return $evaluation;
    }

    public function close(Project $project, int $userId, bool $acceptOutstanding = false, ?string $reason = null, ?string $expectedDate = null): Closure
    {
        return DB::transaction(function () use ($project, $userId, $acceptOutstanding, $reason, $expectedDate) {
            $locked = Project::query()->lockForUpdate()->findOrFail($project->id);
            if ($locked->status === 'closed' || ($acceptOutstanding && in_array($locked->status, Project::closureStatuses(), true))) {
                throw ValidationException::withMessages(['closure' => 'This project already has the requested financial closure. Reopen it before recording another exception.']);
            }
            $workflow = $locked->workflow()->with('steps')->firstOrFail();
            $evaluation = $this->evaluate($workflow, $userId)->fresh('activeOverride');
            $snapshot = $evaluation->details;

            if (! $snapshot['operationally_ready']) {
                throw ValidationException::withMessages(['closure' => 'Complete or formally override every other operational workflow step before financial closure.']);
            }
            if ($snapshot['has_outstanding_financials'] && ! $acceptOutstanding) {
                throw ValidationException::withMessages(['closure' => 'Ordinary closure is blocked while receivables, payables, advances, or unapplied cash remain.']);
            }
            if ($acceptOutstanding && ! $snapshot['has_outstanding_financials']) {
                throw ValidationException::withMessages(['closure' => 'No outstanding financial balance exists; use financially complete closure.']);
            }
            if ($acceptOutstanding && (mb_strlen(trim((string) $reason)) < 20 || ! $expectedDate)) {
                throw ValidationException::withMessages(['closure' => 'A substantive reason and expected resolution date are mandatory for an outstanding-financial exception.']);
            }

            if ($acceptOutstanding) {
                $override = $evaluation->overrides()->create([
                    'reason' => $reason,
                    'expected_resolution_date' => $expectedDate,
                    'risk_snapshot' => $snapshot,
                    'approved_by' => $userId,
                    'approved_at' => now(),
                ]);
                $evaluation->setRelation('activeOverride', $override);
                app(ProjectWorkflowGates::class)->applyToStep($evaluation);
            }

            $type = $acceptOutstanding ? Closure::TYPE_OUTSTANDING : Closure::TYPE_COMPLETE;
            $closure = Closure::query()->create([
                'project_id' => $locked->id,
                'project_workflow_id' => $workflow->id,
                'closure_type' => $type,
                'content_hash' => $snapshot['content_hash'],
                'financial_snapshot' => $snapshot,
                'reason' => $reason,
                'expected_resolution_date' => $expectedDate,
                'approved_by' => $userId,
                'closed_at' => now(),
            ]);
            $locked->update([
                'status' => $acceptOutstanding ? Closure::TYPE_OUTSTANDING : 'closed',
                'stage' => 'closed',
                'progress_percent' => 100,
                'completed_at' => now(),
            ]);
            app(ProjectWorkflowExecution::class)->recalculate($workflow);
            $this->writeAudit($locked, $userId, 'financial_closure', $acceptOutstanding ? 'Project closed with outstanding financials' : 'Project financially closed', $reason, [
                'closure_id' => $closure->id, 'closure_type' => $type, 'content_hash' => $snapshot['content_hash'],
                'totals' => $snapshot['totals'], 'expected_resolution_date' => $expectedDate,
            ]);

            return $closure;
        });
    }

    public function reopen(Project $project, int $userId, string $reason): Closure
    {
        return DB::transaction(function () use ($project, $userId, $reason) {
            $locked = Project::query()->lockForUpdate()->findOrFail($project->id);
            if (! in_array($locked->status, ['closed', Closure::TYPE_OUTSTANDING], true)) {
                throw ValidationException::withMessages(['closure' => 'Only a financially closed project can be reopened.']);
            }
            $workflow = $locked->workflow()->with('steps')->firstOrFail();
            $snapshot = $this->snapshot($locked);
            $closure = Closure::query()->create([
                'project_id' => $locked->id,
                'project_workflow_id' => $workflow->id,
                'closure_type' => Closure::TYPE_REOPENED,
                'content_hash' => $snapshot['content_hash'],
                'financial_snapshot' => $snapshot,
                'reason' => $reason,
                'approved_by' => $userId,
                'closed_at' => now(),
            ]);
            $locked->update(['status' => 'in_progress', 'stage' => $workflow->calculated_stage ?: 'delivered', 'completed_at' => null]);
            $latestEvaluation = $workflow->evaluations()->where('gate_key', self::GATE)->latest('evaluated_at')->latest('id')->first();
            if ($latestEvaluation?->activeOverride) {
                $latestEvaluation->activeOverride->update([
                    'revoked_at' => now(), 'revoked_by' => $userId,
                    'revocation_reason' => 'Project reopened: '.$reason,
                ]);
            }
            $this->evaluate($workflow->fresh('steps'), $userId);
            $this->writeAudit($locked, $userId, 'financial_closure_reopened', 'Financial closure reopened', $reason, [
                'closure_id' => $closure->id, 'content_hash' => $snapshot['content_hash'],
            ]);

            return $closure;
        });
    }

    private function writeAudit(Project $project, int $userId, string $eventType, string $title, ?string $description, array $newValues): void
    {
        ProjectLog::query()->create([
            'project_id' => $project->id,
            'user_id' => $userId,
            'actor_type' => 'internal',
            'actor_name' => User::query()->find($userId)?->name ?: 'Internal Team',
            'event_type' => $eventType,
            'title' => $title,
            'description' => $description,
            'new_values' => $newValues,
            'is_public' => false,
        ]);
    }

    private function totals(array $rows, string $column): array
    {
        return collect($rows)->filter(fn ($row) => (float) $row[$column] > 0.009)
            ->groupBy('currency')->map(fn ($group) => round((float) $group->sum($column), 2))->sortKeys()->all();
    }
}
