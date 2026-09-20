<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectLog;
use App\Models\ProjectPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class ProjectPaymentController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $data = $this->validatedData($request);
        $cashflowAccountId = $data['cashflow_account_id'] ?? null;
        $cashflowCategoryId = $data['cashflow_category_id'] ?? null;
        $createCashflow = $request->has('also_create_cashflow');

        unset($data['cashflow_account_id'], $data['cashflow_category_id'], $data['also_create_cashflow']);

        if ($createCashflow && empty($data['cashflow_entry_id'])) {
            if (! $this->cashflowAvailable()) {
                return back()->with('error', 'Cashflow module is not installed or migrated.');
            }
            if (! $cashflowAccountId) {
                return back()->with('error', 'Please select a cashflow account to create cashflow entry.');
            }

            $data['cashflow_entry_id'] = $this->createCashflowEntry($project, $data, $cashflowAccountId, $cashflowCategoryId);
        }

        $data['project_id'] = $project->id;
        $data['created_by'] = Auth::id();
        $data['is_public'] = $request->has('is_public');

        $payment = ProjectPayment::create($data);
        $this->writeLog($project, 'payment_added', 'Project payment added', $payment->typeLabel().' of '.$payment->currency.' '.$payment->amount.' recorded.', ['payment_id' => $payment->id], $payment->is_public);

        return back()->with('success', 'Project payment recorded successfully.');
    }

    public function update(Request $request, ProjectPayment $projectPayment): RedirectResponse
    {
        $data = $this->validatedData($request);
        unset($data['cashflow_account_id'], $data['cashflow_category_id'], $data['also_create_cashflow']);
        $data['is_public'] = $request->has('is_public');

        $old = $projectPayment->toArray();
        $projectPayment->update($data);

        $this->writeLog($projectPayment->project, 'payment_updated', 'Project payment updated', 'Payment details were updated.', ['old' => $old, 'new' => $projectPayment->fresh()->toArray()], $projectPayment->is_public);

        return back()->with('success', 'Project payment updated successfully.');
    }

    public function destroy(ProjectPayment $projectPayment): RedirectResponse
    {
        $project = $projectPayment->project;
        $projectPayment->delete();
        $this->writeLog($project, 'payment_deleted', 'Project payment deleted', 'A project payment entry was deleted. Linked cashflow entry was not deleted.', null, false);

        return back()->with('success', 'Project payment deleted successfully.');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'cashflow_entry_id' => ['nullable', 'integer'],
            'transaction_type' => ['required', Rule::in(array_keys(ProjectPayment::transactionTypeOptions()))],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'max:10'],
            'payment_mode' => ['nullable', Rule::in(array_keys(ProjectPayment::paymentModeOptions()))],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'party_type' => ['nullable', 'string', 'max:30'],
            'party_name' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(array_keys(ProjectPayment::statusOptions()))],
            'is_public' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'also_create_cashflow' => ['nullable', 'boolean'],
            'cashflow_account_id' => ['nullable', 'integer'],
            'cashflow_category_id' => ['nullable', 'integer'],
        ]);
    }

    private function createCashflowEntry(Project $project, array $data, $accountId, $categoryId = null): ?int
    {
        $entryClass = \App\Models\CashflowEntry::class;
        $entry = new $entryClass;
        $entry->entry_date = $data['payment_date'];
        $entry->particular = 'Project '.$project->project_number.' - '.$project->name;
        $entry->invoice_bill_number = $data['reference_number'] ?? null;
        $entry->bank_reference_number = $data['reference_number'] ?? null;
        $entry->transaction_type = $data['transaction_type'] === 'inward' ? 'credit' : 'debit';
        $entry->credit_amount = $data['transaction_type'] === 'inward' ? $data['amount'] : 0;
        $entry->debit_amount = $data['transaction_type'] === 'outward' ? $data['amount'] : 0;
        $entry->balance = null;
        $entry->currency = $data['currency'] ?? $project->currency;
        $entry->account_id = $accountId;
        $entry->category_id = $categoryId ?: null;
        $entry->accounting_status = $data['status'] === 'reconciled' ? 'reconciled' : 'booked';
        $entry->payment_mode = $data['payment_mode'] ?? null;
        $entry->client_id = $project->client_id;
        $entry->vendor_id = null;
        $entry->expense_head = $data['category'] ?? null;
        $entry->related_party_type = $data['transaction_type'] === 'inward' ? 'client' : ($data['party_type'] ?: 'expense');
        $entry->related_party_name = $data['party_name'] ?: $project->clientName();
        $entry->notes = $data['notes'] ?? null;
        $entry->created_by = Auth::id();

        if (Schema::hasColumn('cashflow_entries', 'project_id')) {
            $entry->project_id = $project->id;
        }

        $entry->save();

        return $entry->id;
    }

    private function cashflowAvailable(): bool
    {
        return class_exists(\App\Models\CashflowEntry::class) && Schema::hasTable('cashflow_entries');
    }

    private function writeLog(Project $project, string $eventType, string $title, ?string $description = null, ?array $newValues = null, bool $isPublic = false): void
    {
        ProjectLog::create([
            'project_id' => $project->id,
            'user_id' => Auth::id(),
            'actor_type' => 'internal',
            'actor_name' => Auth::user()->name ?? 'Internal Team',
            'event_type' => $eventType,
            'title' => $title,
            'description' => $description,
            'new_values' => $newValues,
            'is_public' => $isPublic,
        ]);
    }
}
