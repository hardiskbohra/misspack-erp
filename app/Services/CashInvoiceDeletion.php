<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CashflowAllocation;
use App\Models\CashflowEntry;
use App\Models\ProjectWorkflow;
use App\Models\SalesInvoice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** A converted Cash Invoice is deleted by reversing its settlement, never by unlinking money. */
class CashInvoiceDeletion
{
    public const AUDIT_EVENT = 'sales-invoices.cash-conversion-deleted';

    public static function numberWasReversed(string $number): bool
    {
        return Schema::hasTable('audit_logs') && AuditLog::where('event', self::AUDIT_EVENT)
            ->where('context->invoice->invoice_number', $number)->exists();
    }

    public function delete(SalesInvoice $invoice): SalesInvoice
    {
        return DB::transaction(function () use ($invoice) {
            $current = SalesInvoice::findOrFail($invoice->id);
            if (! $current->isConvertedCash()) {
                throw ValidationException::withMessages(['invoice' => 'Only a converted Cash Invoice can use this reversal.']);
            }
            // Every conversion/deletion in a settlement family takes the PI lock first.
            $pi = SalesInvoice::whereKey($current->source_proforma_id)->lockForUpdate()->firstOrFail();
            $children = SalesInvoice::where('source_proforma_id', $pi->id)->orderBy('id')->lockForUpdate()->get();
            $cash = $children->firstWhere('id', $current->id);
            if (! $cash || ! $cash->isConvertedCash() || $pi->invoice_type !== 'proforma' || $pi->status === 'cancelled') {
                throw ValidationException::withMessages(['invoice' => 'The conversion changed or its original PI is unavailable. Reload before deleting.']);
            }
            if ($children->contains(fn ($child) => ! $child->isCash())) {
                throw ValidationException::withMessages(['invoice' => 'This PI remainder already has a tax invoice. Its tax conversion must be reversed before deleting a linked Cash Invoice. Nothing was changed.']);
            }
            if (! Schema::hasTable('audit_logs')) {
                throw ValidationException::withMessages(['invoice' => 'The audit register must be installed before reversing a Cash Invoice. Run the pending database migrations.']);
            }
            $base = round((float) $children->sum('conversion_base_amount'), 2);
            $gross = round((float) $children->sum('conversion_total_amount'), 2);
            if (abs($base - (float) $pi->cash_converted_base_amount) > 0.001
                || abs($gross - (float) $pi->cash_converted_total_amount) > 0.001
                || $base > $pi->cashConvertibleTotal() + 0.001 || $gross > (float) $pi->total_amount + 0.001
                || $children->contains(fn ($child) => (float) $child->conversion_base_amount <= 0 || (float) $child->conversion_total_amount < 0)
                || ($pi->converted_invoice_id && ! $children->contains('id', $pi->converted_invoice_id))
                || strtoupper($cash->currency ?: 'INR') !== strtoupper($pi->currency ?: 'INR')
                || $cash->client_id != $pi->client_id || $cash->project_id != $pi->project_id) {
                throw ValidationException::withMessages(['invoice' => 'Conversion totals or ownership do not reconcile with the original PI. Ask Accounts to check the conversion before deleting. Nothing was changed.']);
            }

            $allocations = CashflowAllocation::where('sales_invoice_id', $cash->id)->orderBy('id')->lockForUpdate()->get();
            $entries = CashflowEntry::where('sales_invoice_id', $cash->id)->orderBy('id')->lockForUpdate()->get();
            $workflows = Schema::hasTable('project_workflows')
                ? ProjectWorkflow::where('commercial_sales_invoice_id', $cash->id)->orderBy('id')->lockForUpdate()->get() : new Collection;
            if ($workflows->contains(fn ($workflow) => $workflow->project_id != $pi->project_id)) {
                throw ValidationException::withMessages(['invoice' => 'This Cash Invoice is a baseline for another project. Correct that project link before deleting.']);
            }
            $snapshot = [
                'invoice' => Arr::except($cash->getAttributes(), ['public_token']),
                'items' => $cash->items()->get()->map(fn ($item) => $item->getAttributes())->all(),
                'attachments' => $cash->attachments()->get()->map(fn ($file) => $file->getAttributes())->all(),
                'pi_id' => $pi->id, 'pi_number' => $pi->invoice_number,
                'restored_base_amount' => (float) $cash->conversion_base_amount,
                'restored_gross_amount' => (float) $cash->conversion_total_amount,
                'returned_opening_credit' => (float) $cash->amount_paid,
                'returned_received_amount' => $cash->receivedAmount(),
                'receipt_entries' => $entries->map(fn ($entry) => $entry->only(['id', 'account_id', 'currency', 'credit_amount', 'debit_amount', 'invoice_bill_number']))->all(),
                'allocations' => $allocations->map(fn ($allocation) => $allocation->only(['id', 'cashflow_entry_id', 'amount', 'document_amount', 'currency', 'document_currency']))->all(),
                'pi_before' => $pi->only(['converted_invoice_id', 'cash_converted_base_amount', 'cash_converted_total_amount', 'amount_paid', 'balance_amount']),
                'restored_workflow_ids' => $workflows->modelKeys(),
            ];

            // Move all credits, including later receipts, refunds and FX slices.
            // Amounts, exchange rates, bank rows and account balances never change.
            foreach ($allocations as $allocation) {
                $this->requireSaved($allocation->update(['sales_invoice_id' => $pi->id]));
            }
            foreach ($entries as $entry) {
                $data = ['sales_invoice_id' => $pi->id];
                if ($entry->invoice_bill_number === $cash->invoice_number) {
                    $data['invoice_bill_number'] = $pi->invoice_number;
                }
                $this->requireSaved($entry->update($data));
            }
            $remaining = $children->reject(fn ($child) => $child->id === $cash->id);
            $pi->amount_paid = round((float) $pi->amount_paid + (float) $cash->amount_paid, 2);
            $pi->cash_converted_base_amount = round((float) $remaining->sum('conversion_base_amount'), 2);
            $pi->cash_converted_total_amount = round((float) $remaining->sum('conversion_total_amount'), 2);
            // Even deleting an earlier sibling of a fully converted PI reopens it.
            $pi->converted_invoice_id = null;
            $pi->balance_amount = $pi->balanceDue();
            $this->requireSaved($pi->save());
            foreach ($workflows as $workflow) {
                $this->requireSaved($workflow->update(['commercial_sales_invoice_id' => $pi->id]));
            }

            $snapshot['pi_after'] = $pi->only(['converted_invoice_id', 'cash_converted_base_amount', 'cash_converted_total_amount', 'amount_paid', 'balance_amount']);
            $user = Auth::user();
            app(AuditTrail::class)->event(self::AUDIT_EVENT, [
                'actor_type' => 'office_user', 'actor_id' => $user?->id,
                'actor_name' => $user?->name, 'actor_identifier' => $user?->email,
                'auditable_type' => SalesInvoice::class, 'auditable_id' => $cash->id,
                'route_name' => 'sales-invoices.destroy', 'method' => 'DELETE', 'context' => $snapshot,
            ]);
            $this->requireSaved(AuditLog::where('event', self::AUDIT_EVENT)
                ->where('auditable_type', SalesInvoice::class)->where('auditable_id', $cash->id)
                ->where('context->invoice->invoice_number', $cash->invoice_number)->exists());
            // Retain attachment files for audit evidence; their metadata is above.
            // Foreign keys remove the deleted document's item/attachment/reminder rows.
            if (! $cash->delete()) {
                throw ValidationException::withMessages(['invoice' => 'The Cash Invoice could not be deleted. No reversal was saved.']);
            }
            if ($workflow = $pi->project?->workflow) {
                $workflows->push($workflow);
            }
            foreach ($workflows->unique('id') as $workflow) {
                app(ProjectWorkflowGates::class)->evaluateAll($workflow, Auth::id());
            }

            return $pi;
        });
    }

    private function requireSaved(bool $saved): void
    {
        if (! $saved) {
            throw ValidationException::withMessages(['invoice' => 'The financial reversal could not be saved. Nothing was changed.']);
        }
    }
}
