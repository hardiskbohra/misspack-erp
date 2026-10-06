@php($fieldId = fn (string $name) => $formPrefix.'_'.$name)
<div class="master-modal-grid">
    <div class="master-field">
        <label class="master-label" for="{{ $fieldId('transaction_date') }}">Date <span class="master-required" aria-hidden="true">*</span></label>
        <input class="master-input" id="{{ $fieldId('transaction_date') }}" type="date" name="transaction_date"
            value="{{ now()->toDateString() }}" required>
    </div>
    <div class="master-field">
        <label class="master-label" for="{{ $fieldId('due_date') }}">Due date</label>
        <input class="master-input" id="{{ $fieldId('due_date') }}" type="date" name="due_date">
        <div class="master-help">Leave blank on a bill and the vendor's payment terms decide it.</div>
    </div>
    <div class="master-field full">
        <label class="master-label" for="{{ $fieldId('purchase_invoice_id') }}">PO / bill for money out</label>
        <select class="master-select" id="{{ $fieldId('purchase_invoice_id') }}" name="purchase_invoice_id">
            <option value="">Not a payment — bill or expense only</option>
            @foreach (($payableDocuments ?? collect()) as $document)
                <option value="{{ $document->id }}"
                    data-currency="{{ $document->currency ?: ($vendor->preferred_currency ?: 'RMB') }}"
                    data-rate="{{ $document->exchange_rate }}">
                    {{ $document->invoice_number }} · {{ $document->typeLabel() }} · {{ $document->statusLabel() }}
                </option>
            @endforeach
        </select>
        <div class="master-help">A debit / payment must sit on an approved purchase order or a raised bill. Sent POs wait for the checker.</div>
        @if (($pendingApprovals ?? collect())->isNotEmpty())
            <div class="master-help">
                Waiting on approval:
                @foreach ($pendingApprovals as $pending)
                    <a href="{{ route('purchase-invoices.show', $pending) }}">{{ $pending->invoice_number }}</a>
                    ({{ $pending->statusLabel() }})@if (! $loop->last), @endif
                @endforeach
                — approve the PO before recording an advance.
            </div>
        @endif
    </div>
    <div class="master-field">
        <label class="master-label" for="{{ $fieldId('invoice_number') }}">Invoice / bill no.</label>
        <input class="master-input" id="{{ $fieldId('invoice_number') }}" type="text" name="invoice_number"
            placeholder="Vendor invoice number">
    </div>
    <div class="master-field full">
        <label class="master-label" for="{{ $fieldId('particular') }}">Particular <span class="master-required" aria-hidden="true">*</span></label>
        <input class="master-input" id="{{ $fieldId('particular') }}" type="text" name="particular" required
            placeholder="Bill generated / advance payment / vendor expense / adjustment">
    </div>
    <div class="master-field">
        <label class="master-label" for="{{ $fieldId('foreign_amount') }}">Vendor-currency value <span class="master-required" aria-hidden="true">*</span></label>
        <input class="master-input" id="{{ $fieldId('foreign_amount') }}" type="number" step="0.0001" min="0" name="foreign_amount" required inputmode="decimal">
    </div>
    <div class="master-field">
        <label class="master-label" for="{{ $fieldId('foreign_currency') }}">Currency <span class="master-required" aria-hidden="true">*</span></label>
        <select class="master-select" id="{{ $fieldId('foreign_currency') }}" name="foreign_currency" required>
            @foreach ($paymentOptions['currency'] as $key => $label)
                <option value="{{ $key }}" @selected($key === ($vendor->preferred_currency ?: 'RMB'))>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="master-field">
        <label class="master-label" for="{{ $fieldId('exchange_rate') }}">Exchange rate</label>
        <input class="master-input" id="{{ $fieldId('exchange_rate') }}" type="number" step="0.000001" min="0" name="exchange_rate"
            placeholder="₹ per 1 unit" inputmode="decimal">
    </div>
    <div class="master-field">
        <label class="master-label" for="{{ $fieldId('amount_in_inr') }}">Amount in INR</label>
        <input class="master-input" id="{{ $fieldId('amount_in_inr') }}" type="number" step="0.01" min="0" name="amount_in_inr"
            placeholder="Calculated from the rate when left blank" inputmode="decimal">
    </div>
    <div class="master-field">
        <label class="master-label" for="{{ $fieldId('transaction_type') }}">Credit / debit <span class="master-required" aria-hidden="true">*</span></label>
        <select class="master-select" id="{{ $fieldId('transaction_type') }}" name="transaction_type" required>
            @foreach ($paymentOptions['type'] as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="master-field">
        <label class="master-label" for="{{ $fieldId('entry_category') }}">Entry category <span class="master-required" aria-hidden="true">*</span></label>
        <select class="master-select" id="{{ $fieldId('entry_category') }}" name="entry_category" required>
            @foreach ($paymentOptions['category'] as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="master-field">
        <label class="master-label" for="{{ $fieldId('status') }}">Status <span class="master-required" aria-hidden="true">*</span></label>
        <select class="master-select" id="{{ $fieldId('status') }}" name="status" required>
            @foreach ($paymentOptions['status'] as $key => $label)
                <option value="{{ $key }}" @selected($key === 'pending')>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="master-field">
        <label class="master-label" for="{{ $fieldId('project_id') }}">Project</label>
        <select class="master-select" id="{{ $fieldId('project_id') }}" name="project_id">
            <option value="">No project mapping</option>
            @foreach ($projectsForPayment as $project)
                <option value="{{ $project->id }}">{{ $project->project_number ?? '#'.$project->id }} - {{ $project->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="master-field">
        <label class="master-label" for="{{ $fieldId('paid_account_id') }}">Paid account</label>
        <select class="master-select" id="{{ $fieldId('paid_account_id') }}" name="paid_account_id">
            <option value="">Select paid account</option>
            @foreach ($cashflowAccounts as $account)
                <option value="{{ $account->id }}">{{ $account->account_name }}</option>
            @endforeach
        </select>
    </div>
    <div class="master-field">
        <label class="master-label" for="{{ $fieldId('payment_mode') }}">Payment mode</label>
        <select class="master-select" id="{{ $fieldId('payment_mode') }}" name="payment_mode">
            <option value="">Select mode</option>
            @foreach ($paymentOptions['mode'] as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="master-field">
        <label class="master-label" for="{{ $fieldId('bank_reference_number') }}">Bank reference</label>
        <input class="master-input" id="{{ $fieldId('bank_reference_number') }}" type="text" name="bank_reference_number"
            placeholder="UTR / TT / reference no.">
    </div>
    <div class="master-field">
        <label class="master-label" for="{{ $fieldId('remarks') }}">Remarks</label>
        <input class="master-input" id="{{ $fieldId('remarks') }}" type="text" name="remarks" placeholder="Additional details">
    </div>
    <div class="master-field full">
        <label class="master-label" for="{{ $fieldId('attachments') }}">Bill / proof attachments</label>
        <input class="master-input" id="{{ $fieldId('attachments') }}" type="file" name="attachments[]" multiple
            accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip">
    </div>
    <div class="master-field full vendor-sync-field">
        <label class="master-check" for="{{ $fieldId('also_create_cashflow') }}">
            <input type="hidden" name="also_create_cashflow" value="0">
            <input type="checkbox" id="{{ $fieldId('also_create_cashflow') }}" name="also_create_cashflow" value="1"
                class="vendor-cashflow-sync" checked>
            Record this payment in the INR cashflow
        </label>
        <small class="vendor-sync-hint">One entry here also creates the INR entry in the Cashflow module — it stays in sync when you edit or delete this payment.</small>
    </div>
</div>
