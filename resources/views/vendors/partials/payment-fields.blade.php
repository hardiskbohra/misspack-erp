@php($paymentOld = old('_vendor_payment_form') === ($isEdit ? 'edit' : 'add'))
<div class="master-modal-grid vendor-payment-grid">
    <div class="master-field">
        <label class="master-label" for="{{ $prefix }}_transaction_date">Date <span class="master-required" aria-hidden="true">*</span></label>
        <input class="master-input @error('transaction_date') is-invalid @enderror" id="{{ $prefix }}_transaction_date" type="date" name="transaction_date"
            value="{{ $paymentOld ? old('transaction_date') : ($isEdit ? '' : now()->toDateString()) }}" required>

        @error('transaction_date')<span class="master-error">{{ $message }}</span>@enderror
    </div>
    <div class="master-field">
        <label class="master-label" for="{{ $prefix }}_invoice_number">Invoice / bill number</label>
        <input class="master-input @error('invoice_number') is-invalid @enderror" id="{{ $prefix }}_invoice_number" type="text" name="invoice_number"
            value="{{ $paymentOld ? old('invoice_number') : '' }}" maxlength="255" placeholder="Vendor invoice reference">

        @error('invoice_number')<span class="master-error">{{ $message }}</span>@enderror
    </div>
    <div class="master-field full">
        <label class="master-label" for="{{ $prefix }}_particular">Particular <span class="master-required" aria-hidden="true">*</span></label>
        <input class="master-input @error('particular') is-invalid @enderror" id="{{ $prefix }}_particular" type="text" name="particular" required
            value="{{ $paymentOld ? old('particular') : '' }}" placeholder="Bill, advance payment, expense or adjustment">

        @error('particular')<span class="master-error">{{ $message }}</span>@enderror
    </div>
    <div class="master-field">
        <label class="master-label" for="{{ $prefix }}_foreign_amount">Vendor-currency amount <span class="master-required" aria-hidden="true">*</span></label>
        <input class="master-input @error('foreign_amount') is-invalid @enderror" id="{{ $prefix }}_foreign_amount" type="number" step="0.0001" min="0" name="foreign_amount" required
            value="{{ $paymentOld ? old('foreign_amount') : '' }}" inputmode="decimal">

        @error('foreign_amount')<span class="master-error">{{ $message }}</span>@enderror
    </div>
    <div class="master-field">
        <label class="master-label" for="{{ $prefix }}_foreign_currency">Currency <span class="master-required" aria-hidden="true">*</span></label>
        <select class="master-select @error('foreign_currency') is-invalid @enderror" id="{{ $prefix }}_foreign_currency" name="foreign_currency" required>
            @foreach ($paymentOptions['currency'] as $key => $label)
                <option value="{{ $key }}" @selected(($paymentOld ? old('foreign_currency', $vendor->preferred_currency ?: 'RMB') : ($isEdit ? '' : ($vendor->preferred_currency ?: 'RMB'))) === $key)>{{ $label }}</option>
            @endforeach
        </select>

        @error('foreign_currency')<span class="master-error">{{ $message }}</span>@enderror
    </div>
    <div class="master-field">
        <label class="master-label" for="{{ $prefix }}_exchange_rate">Exchange rate</label>
        <input class="master-input @error('exchange_rate') is-invalid @enderror" id="{{ $prefix }}_exchange_rate" type="number" step="0.000001" min="0" name="exchange_rate"
            value="{{ $paymentOld ? old('exchange_rate') : '' }}" inputmode="decimal" placeholder="₹ per unit">

        @error('exchange_rate')<span class="master-error">{{ $message }}</span>@enderror
    </div>
    <div class="master-field">
        <label class="master-label" for="{{ $prefix }}_amount_in_inr">Amount in INR</label>
        <input class="master-input @error('amount_in_inr') is-invalid @enderror" id="{{ $prefix }}_amount_in_inr" type="number" step="0.01" min="0" name="amount_in_inr"
            value="{{ $paymentOld ? old('amount_in_inr') : '' }}" inputmode="decimal" placeholder="Calculated from exchange rate">

        @error('amount_in_inr')<span class="master-error">{{ $message }}</span>@enderror
    </div>
    <div class="master-field">
        <label class="master-label" for="{{ $prefix }}_transaction_type">Entry direction <span class="master-required" aria-hidden="true">*</span></label>
        <select class="master-select @error('transaction_type') is-invalid @enderror" id="{{ $prefix }}_transaction_type" name="transaction_type" required>
            @foreach ($paymentOptions['type'] as $key => $label)
                <option value="{{ $key }}" @selected(($paymentOld ? old('transaction_type', 'credit') : ($isEdit ? '' : 'credit')) === $key)>{{ $label }}</option>
            @endforeach
        </select>

        @error('transaction_type')<span class="master-error">{{ $message }}</span>@enderror
    </div>
    <div class="master-field">
        <label class="master-label" for="{{ $prefix }}_entry_category">Entry category <span class="master-required" aria-hidden="true">*</span></label>
        <select class="master-select @error('entry_category') is-invalid @enderror" id="{{ $prefix }}_entry_category" name="entry_category" required>
            @foreach ($paymentOptions['category'] as $key => $label)
                <option value="{{ $key }}" @selected(($paymentOld ? old('entry_category', 'bill') : ($isEdit ? '' : 'bill')) === $key)>{{ $label }}</option>
            @endforeach
        </select>

        @error('entry_category')<span class="master-error">{{ $message }}</span>@enderror
    </div>
    <div class="master-field">
        <label class="master-label" for="{{ $prefix }}_status">Status <span class="master-required" aria-hidden="true">*</span></label>
        <select class="master-select @error('status') is-invalid @enderror" id="{{ $prefix }}_status" name="status" required>
            @foreach ($paymentOptions['status'] as $key => $label)
                <option value="{{ $key }}" @selected(($paymentOld ? old('status', 'pending') : ($isEdit ? '' : 'pending')) === $key)>{{ $label }}</option>
            @endforeach
        </select>

        @error('status')<span class="master-error">{{ $message }}</span>@enderror
    </div>
    <div class="master-field">
        <label class="master-label" for="{{ $prefix }}_project_id">Project</label>
        <select class="master-select @error('project_id') is-invalid @enderror" id="{{ $prefix }}_project_id" name="project_id">
            <option value="">No project mapping</option>
            @foreach ($projectsForPayment as $project)
                <option value="{{ $project->id }}" @selected($paymentOld && (string) old('project_id') === (string) $project->id)>{{ $project->project_number ?? '#'.$project->id }} · {{ $project->name }}</option>
            @endforeach
        </select>

        @error('project_id')<span class="master-error">{{ $message }}</span>@enderror
    </div>
    <div class="master-field">
        <label class="master-label" for="{{ $prefix }}_paid_account_id">Paid account</label>
        <select class="master-select @error('paid_account_id') is-invalid @enderror" id="{{ $prefix }}_paid_account_id" name="paid_account_id">
            <option value="">Select account</option>
            @foreach ($cashflowAccounts as $account)
                <option value="{{ $account->id }}" @selected($paymentOld && (string) old('paid_account_id') === (string) $account->id)>{{ $account->account_name }}</option>
            @endforeach
        </select>
        @if ($errors->has('paid_account_id'))<span class="master-error">{{ $errors->first('paid_account_id') }}</span>@endif
    </div>
    <div class="master-field">
        <label class="master-label" for="{{ $prefix }}_payment_mode">Payment mode</label>
        <select class="master-select @error('payment_mode') is-invalid @enderror" id="{{ $prefix }}_payment_mode" name="payment_mode">
            <option value="">Select mode</option>
            @foreach ($paymentOptions['mode'] as $key => $label)
                <option value="{{ $key }}" @selected($paymentOld && old('payment_mode') === $key)>{{ $label }}</option>
            @endforeach
        </select>

        @error('payment_mode')<span class="master-error">{{ $message }}</span>@enderror
    </div>
    <div class="master-field">
        <label class="master-label" for="{{ $prefix }}_bank_reference_number">Bank reference</label>
        <input class="master-input @error('bank_reference_number') is-invalid @enderror" id="{{ $prefix }}_bank_reference_number" type="text" name="bank_reference_number"
            value="{{ $paymentOld ? old('bank_reference_number') : '' }}" maxlength="255" placeholder="UTR / TT / reference">

        @error('bank_reference_number')<span class="master-error">{{ $message }}</span>@enderror
    </div>
    <div class="master-field full">
        <label class="master-label" for="{{ $prefix }}_remarks">Remarks</label>
        <textarea class="master-textarea @error('remarks') is-invalid @enderror" id="{{ $prefix }}_remarks" name="remarks" rows="2" placeholder="Additional notes for this entry">{{ $paymentOld ? old('remarks') : '' }}</textarea>

        @error('remarks')<span class="master-error">{{ $message }}</span>@enderror
    </div>
    <div class="master-field full">
        <label class="master-label" for="{{ $prefix }}_attachments">Bills or payment proof</label>
        <input class="master-input @error('attachments') is-invalid @enderror" id="{{ $prefix }}_attachments" type="file" name="attachments[]" multiple
            accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip">
        <small class="master-help">You can attach more than one supporting file.</small>

        @error('attachments')<span class="master-error">{{ $message }}</span>@enderror
        @if ($errors->has('attachments.*'))<span class="master-error">{{ $errors->first('attachments.*') }}</span>@endif
    </div>
    <div class="master-field full vendor-sync-field">
        <label class="master-check">
            <input type="hidden" name="also_create_cashflow" value="0">
            <input type="checkbox" name="also_create_cashflow" value="1" class="vendor-cashflow-sync" @error('also_create_cashflow') aria-invalid="true" @enderror
                @checked($paymentOld && old('also_create_cashflow'))>
            Record this payment in the INR cashflow
        </label>
        <small class="vendor-sync-hint">For a payment, this creates and keeps a matching INR cashflow entry in sync. Bills do not move cash unless you choose this option.</small>

        @error('also_create_cashflow')<span class="master-error">{{ $message }}</span>@enderror
    </div>
</div>
