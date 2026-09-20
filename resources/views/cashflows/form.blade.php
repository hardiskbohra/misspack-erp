@extends('layouts.app')

@section('page-title', $entry->exists ? 'Edit Cashflow Entry' : 'Add Cashflow Entry')

@section('content')

@php($isEdit = $entry->exists)

<div class="master-form">
    <div class="master-card master-header">
        <h1>{{ $isEdit ? 'Edit Cashflow Entry' : 'Add Cashflow Entry' }}</h1>
        <div class="master-breadcrumb"><a href="{{ url('/') }}">Home</a><span>•</span><a
                href="{{ route('cashflows.index') }}">Cashflow</a><span>•</span><span
                class="active">{{ $isEdit ? 'Edit' : 'Add' }}</span></div>
    </div>
    
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
                
                    <div class="master-chip-group">
                        @foreach($transactionTypeOptions as $key => $label)
                            <label class="master-chip {{ $key === 'credit' ? 'credit-chip' : 'debit-chip' }}">
                                <input
                                    type="radio"
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
                <div class="master-field">
                    <label class="master-label">Project / Deal</label>
                    <select name="project_id" class="master-select">
                        <option value="">No project mapping</option>
                        @foreach(\App\Models\Project::query()->latest('id')->get() as $project)
                            <option value="{{ $project->id }}" {{ (string) old('project_id', $entry->project_id ?? '') === (string) $project->id ? 'selected' : '' }}>
                                {{ $project->project_number }} - {{ $project->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
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
                
                <div class="master-field">
                    <label class="master-label">Invoice</label>
                    <select name="sales_invoice_id" class="master-select">
                        <option value="">No invoice mapping</option>
                        @foreach(\App\Models\SalesInvoice::query()->latest('id')->get() as $invoice)
                            <option value="{{ $invoice->id }}" {{ (string) old('sales_invoice_id', $entry->sales_invoice_id ?? '') === (string) $invoice->id ? 'selected' : '' }}>
                                {{ $invoice->invoice_number }} - {{ $invoice->client->company_name ?? '-' }}
                            </option>
                        @endforeach
                    </select>
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
@endsection