@extends('layouts.app')

@section('page-title', $entry->exists ? 'Edit Cashflow Entry' : 'Add Cashflow Entry')

@section('content')

@php
    $isEdit = $entry->exists;
    $allocationRows = old('allocations');
    if ($allocationRows === null) {
        $allocationRows = $entry->relationLoaded('allocations')
            ? $entry->allocations->map(fn ($allocation) => [
                'project_id' => $allocation->project_id,
                'sales_invoice_id' => $allocation->sales_invoice_id,
                'purchase_invoice_id' => $allocation->purchase_invoice_id,
                'amount' => $allocation->amount,
                'document_amount' => $allocation->document_amount,
                'note' => $allocation->note,
            ])->all()
            : [];

        /* An untouched legacy entry still has its single project/invoice
           columns. Present it as one allocation so the first edit upgrades it
           without losing the link. */
        if ($allocationRows === [] && ($entry->project_id || $entry->sales_invoice_id)) {
            $allocationRows[] = [
                'project_id' => $entry->project_id,
                'sales_invoice_id' => $entry->sales_invoice_id,
                'purchase_invoice_id' => null,
                'amount' => $entry->amountMoved(),
                'document_amount' => $entry->sales_invoice_id ? $entry->amountMoved() : null,
                'note' => null,
            ];
        }
    }
    $allocationRows = array_values($allocationRows ?: []);
@endphp

<div class="master-form">
    <div class="master-card master-header">
        <h1>{{ $isEdit ? 'Edit Cashflow Entry' : 'Add Cashflow Entry' }}</h1>
        <div class="master-breadcrumb"><a href="{{ url('/') }}">Home</a><span>•</span><a
                href="{{ route('cashflows.index') }}">Cashflow</a><span>•</span><span
                class="active">{{ $isEdit ? 'Edit' : 'Add' }}</span></div>
    </div>
    
    @if ($isEdit && ! empty($linkedVendorPayment))
        <div class="master-card master-section cf-sync-note">
            <p style="margin:0;font-weight:700;">
                Auto-synced from vendor payment #{{ $linkedVendorPayment->id }}
                ({{ $linkedVendorPayment->relationLoaded('vendor') && $linkedVendorPayment->vendor ? $linkedVendorPayment->vendor->vendor_name : 'Vendor' }}).
                Amount, date, reference and account come from the vendor ledger —
                @if (\Illuminate\Support\Facades\Route::has('vendors.show'))
                    <a href="{{ route('vendors.show', $linkedVendorPayment->vendor_id) }}#payments">edit the vendor payment</a> to change them here as well.
                @else
                    edit the vendor payment to change them here as well.
                @endif
            </p>
        </div>
    @endif

    <form method="POST" action="{{ $isEdit ? route('cashflows.update', $entry) : route('cashflows.store') }}"
        class="master-card master-form-card">
        @csrf
        @if($isEdit) @method('PUT') @endif

        <div class="master-section">
            <h3 class="master-section-title">Basic Information</h3>
            <div class="master-detail-grid">
                <div class="master-field"><label class="master-label">Date <span class="master-required">*</span></label><input
                        class="master-input" type="date" name="entry_date"
                        value="{{ old('entry_date', optional($entry->entry_date)->format('Y-m-d')) }}"
                        required>@error('entry_date')<div class="master-error">{{ $message }}</div>@enderror</div>
                        
                <div class="master-field">
                    <label class="master-label">
                        Transaction Type <span class="master-required">*</span>
                    </label>
                
                    <div class="master-choice-group">
                        @foreach($transactionTypeOptions as $key => $label)
                            <label class="master-choice-chip {{ $key === 'credit' ? 'credit-chip' : 'debit-chip' }}">
                                <input type="radio"
                                    name="transaction_type"
                                    value="{{ $key }}"
                                    {{ old('transaction_type', $entry->transaction_type ?? 'debit') === $key ? 'checked' : '' }}
                                    required
                                >
                
                                <span>
                                    @if($key === 'credit')
                                        <i class="fa-solid fa-arrow-down"></i>
                                    @else
                                        <i class="fa-solid fa-arrow-up"></i>
                                    @endif
                
                                    {{ $label }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
                
                
                <div class="master-field"><label class="master-label">Currency</label><select class="master-select"
                        name="currency">@foreach($currencyOptions as $key => $label)<option value="{{ $key }}"
                            @selected(old('currency', $entry->currency) === $key)>{{ $label }}</option>
                        @endforeach</select></div>
                <div class="master-field"><label class="master-label">Particular <span
                            class="master-required">*</span></label><input class="master-input" name="particular"
                        value="{{ old('particular', $entry->particular) }}" required
                        placeholder="Statement narration / particular">@error('particular')<div class="master-error">
                        {{ $message }}</div>@enderror</div>
                
                <div class="master-field">
                    <label class="master-label">
                        Amount <span class="master-required">*</span>
                    </label>
                
                    <input class="master-input"
                           type="number"
                           step="0.01"
                           min="0.01"
                           name="amount"
                           value="{{ old('amount', $entry->transaction_type === 'credit' ? $entry->credit_amount : $entry->debit_amount) }}"
                           placeholder="Enter amount"
                           required>
                
                    @error('amount')
                        <div class="master-error">{{ $message }}</div>
                    @enderror
                </div>
                
                
                <div class="master-field"><label class="master-label">Paid / Received Account <span
                            class="master-required">*</span></label><select class="master-select" name="account_id" required>
                        <option value="">Select account</option>@foreach($accounts as $account)<option
                            value="{{ $account->id }}" @selected((string) old('account_id', $entry->account_id) === (string) $account->id)>{{ $account->account_name }} -
                        {{ $account->typeLabel() }}</option>@endforeach
                    </select>@error('account_id')<div class="master-error">{{ $message }}</div>@enderror</div>
                <div class="master-field full">
                    <div class="master-help"
                        style="padding:12px 14px;border:1px solid green;border-radius:12px;background:#fbfdff;color:green;">
                        Balance is not manually entered. It is automatically calculated account-wise from opening
                        balance + credit entries - debit entries in date order.</div>
                </div>
            </div>
        </div>

        <div class="master-section cf-allocation-section" data-allocation-builder>
            <div class="master-section-head">
                <div>
                    <h3 class="master-section-title">Allocation breakdown</h3>
                    <p class="master-sub">Split this entry across projects and documents. A line is one monetary slice and may carry both its project and one sales or purchase document.</p>
                </div>
                <button type="button" class="master-btn master-btn-soft master-btn-sm" data-add-allocation>
                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Add allocation
                </button>
            </div>

            <div class="cf-allocation-summary" aria-live="polite">
                <div><span>Entry amount</span><strong data-allocation-entry>0.00</strong></div>
                <div><span>Allocated</span><strong data-allocation-total>0.00</strong></div>
                <div><span>Unallocated / advance</span><strong data-allocation-remaining>0.00</strong></div>
            </div>
            <p class="master-help">The total allocated amount cannot exceed the cash entry. It may be lower, leaving an unapplied advance. An allocation may exceed a document’s outstanding amount; the document will then show an advance/overpayment.</p>
            @error('allocations')<div class="master-error">{{ $message }}</div>@enderror

            <div class="cf-allocation-list" data-allocation-list>
                @foreach($allocationRows as $index => $allocation)
                    <div class="cf-allocation-row" data-allocation-row>
                        <div class="master-field">
                            <label class="master-label">Project</label>
                            <select class="master-select" name="allocations[{{ $index }}][project_id]">
                                <option value="">No project</option>
                                @foreach($projects as $project)
                                    <option value="{{ $project->id }}" @selected((string) ($allocation['project_id'] ?? '') === (string) $project->id)>{{ $project->project_number }} · {{ $project->name }}</option>
                                @endforeach
                            </select>
                            @error('allocations.'.$index.'.project_id')<div class="master-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="master-field">
                            <label class="master-label">Sales invoice / proforma</label>
                            <select class="master-select" name="allocations[{{ $index }}][sales_invoice_id]" data-allocation-sales>
                                <option value="">No sales document</option>
                                @foreach($salesInvoices as $invoice)
                                    <option value="{{ $invoice->id }}" data-currency="{{ $invoice->currency }}" data-project="{{ $invoice->project_id }}" @selected((string) ($allocation['sales_invoice_id'] ?? '') === (string) $invoice->id)>{{ $invoice->invoice_number }} · {{ $invoice->currency }} {{ number_format((float) $invoice->total_amount, 2) }}</option>
                                @endforeach
                            </select>
                            @error('allocations.'.$index.'.sales_invoice_id')<div class="master-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="master-field">
                            <label class="master-label">Purchase order / bill</label>
                            <select class="master-select" name="allocations[{{ $index }}][purchase_invoice_id]" data-allocation-purchase>
                                <option value="">No purchase document</option>
                                @foreach($purchaseInvoices as $invoice)
                                    <option value="{{ $invoice->id }}" data-currency="{{ $invoice->currency }}" data-project="{{ $invoice->project_id }}" @selected((string) ($allocation['purchase_invoice_id'] ?? '') === (string) $invoice->id)>{{ $invoice->invoice_number }} · {{ $invoice->typeLabel() }} · {{ $invoice->currency }} {{ number_format((float) $invoice->total_amount, 2) }}</option>
                                @endforeach
                            </select>
                            @error('allocations.'.$index.'.purchase_invoice_id')<div class="master-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="master-field">
                            <label class="master-label">Cash amount</label>
                            <input class="master-input" type="number" min="0.01" step="0.01" name="allocations[{{ $index }}][amount]" value="{{ $allocation['amount'] ?? '' }}" data-allocation-amount>
                            @error('allocations.'.$index.'.amount')<div class="master-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="master-field">
                            <label class="master-label">Document amount</label>
                            <input class="master-input" type="number" min="0.01" step="0.01" name="allocations[{{ $index }}][document_amount]" value="{{ $allocation['document_amount'] ?? '' }}" data-document-amount placeholder="Same as cash amount">
                            @error('allocations.'.$index.'.document_amount')<div class="master-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="master-field cf-allocation-note">
                            <label class="master-label">Allocation note</label>
                            <input class="master-input" name="allocations[{{ $index }}][note]" value="{{ $allocation['note'] ?? '' }}" maxlength="500" placeholder="Duty, freight, advance…">
                        </div>
                        <button type="button" class="master-btn master-btn-ghost master-btn-sm cf-allocation-remove" data-remove-allocation aria-label="Remove allocation"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                    </div>
                @endforeach
            </div>

            <template data-allocation-template>
                <div class="cf-allocation-row" data-allocation-row>
                    <div class="master-field"><label class="master-label">Project</label><select class="master-select" name="allocations[__INDEX__][project_id]"><option value="">No project</option>@foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->project_number }} · {{ $project->name }}</option>@endforeach</select></div>
                    <div class="master-field"><label class="master-label">Sales invoice / proforma</label><select class="master-select" name="allocations[__INDEX__][sales_invoice_id]" data-allocation-sales><option value="">No sales document</option>@foreach($salesInvoices as $invoice)<option value="{{ $invoice->id }}" data-currency="{{ $invoice->currency }}" data-project="{{ $invoice->project_id }}">{{ $invoice->invoice_number }} · {{ $invoice->currency }} {{ number_format((float) $invoice->total_amount, 2) }}</option>@endforeach</select></div>
                    <div class="master-field"><label class="master-label">Purchase order / bill</label><select class="master-select" name="allocations[__INDEX__][purchase_invoice_id]" data-allocation-purchase><option value="">No purchase document</option>@foreach($purchaseInvoices as $invoice)<option value="{{ $invoice->id }}" data-currency="{{ $invoice->currency }}" data-project="{{ $invoice->project_id }}">{{ $invoice->invoice_number }} · {{ $invoice->typeLabel() }} · {{ $invoice->currency }} {{ number_format((float) $invoice->total_amount, 2) }}</option>@endforeach</select></div>
                    <div class="master-field"><label class="master-label">Cash amount</label><input class="master-input" type="number" min="0.01" step="0.01" name="allocations[__INDEX__][amount]" data-allocation-amount></div>
                    <div class="master-field"><label class="master-label">Document amount</label><input class="master-input" type="number" min="0.01" step="0.01" name="allocations[__INDEX__][document_amount]" data-document-amount placeholder="Same as cash amount"></div>
                    <div class="master-field cf-allocation-note"><label class="master-label">Allocation note</label><input class="master-input" name="allocations[__INDEX__][note]" maxlength="500" placeholder="Duty, freight, advance…"></div>
                    <button type="button" class="master-btn master-btn-ghost master-btn-sm cf-allocation-remove" data-remove-allocation aria-label="Remove allocation"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                </div>
            </template>
        </div>

        <div class="master-section">
            <h3 class="master-section-title">Account & Accounting Classification</h3>
            <div class="master-detail-grid">
                <div class="master-field"><label class="master-label">Category</label><select class="master-select" name="category_id">
                    <option value="">Uncategorized</option>
                    @forelse($categories as $category)
                        <option value="{{ $category->id }}"
                            {{ old('category_id', $entry->category_id ?? null) == $category->id ? 'selected' : '' }}>
                            {{ $category->name }} - {{ $category->typeLabel() }}
                        </option>
                    @empty
                        <option value="" disabled>No categories found - add from Cashflow Settings</option>
                    @endforelse
                </select></div>
                <div class="master-field"><label class="master-label">Accounting Status</label><select class="master-select"
                        name="accounting_status">@foreach($accountingStatusOptions as $key => $label)<option
                            value="{{ $key }}" @selected(old('accounting_status', $entry->accounting_status) === $key)>
                        {{ $label }}</option>@endforeach</select></div>
                <div class="master-field"><label class="master-label">Payment Mode</label><select class="master-select"
                        name="payment_mode">
                        <option value="">Select mode</option>@foreach($paymentModeOptions as $key => $label)<option
                            value="{{ $key }}" @selected(old('payment_mode', $entry->payment_mode) === $key)>{{ $label }}
                        </option>@endforeach
                    </select></div>
                <div class="master-field"><label class="master-label">Related Party Type</label><select class="master-select"
                        name="related_party_type">@foreach($relatedPartyOptions as $key => $label)<option
                            value="{{ $key }}" @selected(old('related_party_type', $entry->related_party_type) === $key)>
                        {{ $label }}</option>@endforeach</select></div>
                <div class="master-field"><label class="master-label">Related Party Name</label><input class="master-input"
                        name="related_party_name" value="{{ old('related_party_name', $entry->related_party_name) }}"
                        placeholder="Optional if client/vendor not selected"></div>
                <div class="master-field"><label class="master-label">Client</label><select class="master-select" name="client_id">
                        <option value="">No client</option>@foreach($clients as $client)<option
                        value="{{ $client->id }}" @selected((string) old('client_id', $entry->client_id) === (string) $client->id)>{{ $client->company_name }}</option>@endforeach
                    </select></div>
                <div class="master-field"><label class="master-label">Vendor</label><select class="master-select" name="vendor_id">
                        <option value="">No vendor</option>@foreach($vendors as $vendor)<option
                        value="{{ $vendor->id }}" @selected((string) old('vendor_id', $entry->vendor_id) === (string) $vendor->id)>{{ $vendor->vendor_name }}</option>@endforeach
                    </select></div>
                <div class="master-field"><label class="master-label">Office service</label><select class="master-select" name="office_service_id">
                        <option value="">No office service</option>@foreach(($officeServices ?? []) as $officeService)<option
                        value="{{ $officeService->id }}" @selected((string) old('office_service_id', $entry->office_service_id) === (string) $officeService->id)>{{ $officeService->name }} — {{ $officeService->classLabel() }}</option>@endforeach
                    </select></div>
                {{-- The link the free-text name never had: "paid to Ramesh" becomes
                     a row against Ramesh, so the report can group by him and the
                     entry can be found by searching his name. --}}
                <div class="master-field"><label class="master-label">Employee</label><select class="master-select" name="employee_id">
                        <option value="">No employee</option>@foreach($employees as $employee)<option
                        value="{{ $employee->id }}" @selected((string) old('employee_id', $entry->employee_id) === (string) $employee->id)>{{ $employee->name }}@if($employee->designation) — {{ $employee->designation }}@endif</option>@endforeach
                    </select></div>
                <div class="master-field"><label class="master-label">Expense Head</label><input class="master-input"
                        name="expense_head" value="{{ old('expense_head', $entry->expense_head) }}"
                        placeholder="Cash expense head"></div>
            </div>
        </div>

        <div class="master-section">
            <h3 class="master-section-title">Metadata Information</h3>
            <div class="master-detail-grid">
                <div class="master-field"><label class="master-label">Against Invoice / Bill Number</label><input
                        class="master-input" name="invoice_bill_number"
                        value="{{ old('invoice_bill_number', $entry->invoice_bill_number) }}"></div>
                <div class="master-field"><label class="master-label">Bank Reference Number</label><input class="master-input"
                        name="bank_reference_number"
                        value="{{ old('bank_reference_number', $entry->bank_reference_number) }}"></div>
                <div class="master-field full"><label class="master-label">Notes</label><input class="master-input"
                        name="notes" value="{{ old('notes', $entry->notes) }}"></div>
            </div>
        </div>

        <div class="master-actions"><a href="{{ $isEdit ? route('cashflows.show', $entry) : route('cashflows.index') }}"
                class="master-btn master-btn-light">Cancel</a><button type="submit"
                class="master-btn master-btn-primary">{{ $isEdit ? 'Update Entry' : 'Create Entry' }}</button></div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var builder = document.querySelector('[data-allocation-builder]');
    if (!builder) return;

    var list = builder.querySelector('[data-allocation-list]');
    var template = builder.querySelector('[data-allocation-template]');
    var add = builder.querySelector('[data-add-allocation]');
    var amountInput = document.querySelector('input[name="amount"]');
    var nextIndex = list.querySelectorAll('[data-allocation-row]').length;

    function money(value) {
        return (Number(value) || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function totals() {
        var entry = parseFloat(amountInput ? amountInput.value : 0) || 0;
        var allocated = 0;
        list.querySelectorAll('[data-allocation-amount]').forEach(function (input) {
            allocated += parseFloat(input.value) || 0;
        });
        builder.querySelector('[data-allocation-entry]').textContent = money(entry);
        builder.querySelector('[data-allocation-total]').textContent = money(allocated);
        var remaining = entry - allocated;
        var remainingNode = builder.querySelector('[data-allocation-remaining]');
        remainingNode.textContent = money(remaining);
        builder.classList.toggle('has-allocation-error', remaining < -0.009);
    }

    function addRow() {
        list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', nextIndex++));
        totals();
    }

    add.addEventListener('click', addRow);
    if (!list.querySelector('[data-allocation-row]')) addRow();
    if (amountInput) amountInput.addEventListener('input', totals);

    builder.addEventListener('input', function (event) {
        if (event.target.matches('[data-allocation-amount]')) totals();
    });
    builder.addEventListener('click', function (event) {
        var remove = event.target.closest('[data-remove-allocation]');
        if (!remove) return;
        remove.closest('[data-allocation-row]').remove();
        totals();
    });
    builder.addEventListener('change', function (event) {
        if (!event.target.matches('[data-allocation-sales], [data-allocation-purchase]')) return;
        var row = event.target.closest('[data-allocation-row]');
        var opposite = event.target.matches('[data-allocation-sales]')
            ? row.querySelector('[data-allocation-purchase]')
            : row.querySelector('[data-allocation-sales]');
        if (event.target.value && opposite && opposite.value) {
            opposite.value = '';
            if (window.jQuery) window.jQuery(opposite).trigger('change');
        }
        var option = event.target.options[event.target.selectedIndex];
        var projectId = option ? option.getAttribute('data-project') : '';
        var project = row.querySelector('select[name$="[project_id]"]');
        if (projectId && project && !project.value) {
            project.value = projectId;
            if (window.jQuery) window.jQuery(project).trigger('change');
        }
    });

    totals();
});
</script>
@endpush
@endsection