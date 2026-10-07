{{--
    The fields of a recurring rule, written once.

    A rule is created from the list and edited from its own page, and both forms
    are this partial: the same fields, the same order, the same vocabularies. Two
    copies would drift the day a field is added to one of them — and the day the
    office notices is the day a rule asks for something the other form cannot
    express.

    Every field reads `old()` first, so a save the server rejected comes back with
    the typing kept (the dialog reopens on the marker the page prints; see
    `cashflows/recurring/index.blade.php`).

    The schedule block is the one that needs reading twice: the **effective date**
    is both the first occurrence and the day-of-month every later one is anchored
    on, and the two boxes underneath are the two ways the window closes. Either,
    both, or neither — "until we say stop" is a real answer.
--}}
@php
    $editing = ($rule ?? null) !== null;
    $value = fn (string $field, $default = '') => old($field, $editing ? ($rule->{$field} ?? $default) : $default);
    $date = fn (string $field) => old($field, $editing && $rule->{$field} ? $rule->{$field}->format('Y-m-d') : '');
@endphp

<div class="master-modal-grid">
    <div class="master-field full">
        <label class="master-label" for="{{ $dialogId }}Title">What is it? <span class="master-required" aria-hidden="true">*</span></label>
        <input class="master-input" id="{{ $dialogId }}Title" type="text" name="title"
            value="{{ $value('title') }}" maxlength="{{ \App\Services\RecurrenceVocabulary::TITLE_LIMIT }}"
            placeholder="Office rent — Shivalik Complex" required>
        @error('title')<p class="master-error">{{ $message }}</p>@enderror
    </div>

    <div class="master-field full">
        <label class="master-label" for="{{ $dialogId }}Particular">The ledger line <span class="master-required" aria-hidden="true">*</span></label>
        <input class="master-input" id="{{ $dialogId }}Particular" type="text" name="particular"
            value="{{ $value('particular') }}" maxlength="{{ \App\Services\RecurrenceVocabulary::PARTICULAR_LIMIT }}"
            placeholder="Rent for the month" required>
        <p class="master-help">What every posted entry will be titled — this line reaches the ledger and the bank statement.</p>
    </div>

    <div class="master-field">
        <label class="master-label" for="{{ $dialogId }}Direction">Direction <span class="master-required" aria-hidden="true">*</span></label>
        <select class="master-select" id="{{ $dialogId }}Direction" name="transaction_type" required>
            @foreach ($transactionTypeOptions as $key => $label)
                <option value="{{ $key }}" @selected($value('transaction_type', 'debit') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="master-field">
        <label class="master-label" for="{{ $dialogId }}Amount">Amount <span class="master-required" aria-hidden="true">*</span></label>
        <input class="master-input" id="{{ $dialogId }}Amount" type="number" name="amount" step="0.01" min="0.01"
            value="{{ $value('amount') }}" placeholder="0.00" required>
        @error('amount')<p class="master-error">{{ $message }}</p>@enderror
    </div>

    <div class="master-field">
        <label class="master-label" for="{{ $dialogId }}Currency">Currency</label>
        <select class="master-select" id="{{ $dialogId }}Currency" name="currency">
            @foreach ($currencyOptions as $key => $label)
                <option value="{{ $key }}" @selected($value('currency', 'INR') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="master-field">
        <label class="master-label" for="{{ $dialogId }}Account">Paid from / received in <span class="master-required" aria-hidden="true">*</span></label>
        <select class="master-select" id="{{ $dialogId }}Account" name="account_id" required>
            <option value="">Choose an account</option>
            @foreach ($accounts as $account)
                <option value="{{ $account->id }}" @selected((string) $value('account_id') === (string) $account->id)>
                    {{ $account->account_name }} — {{ $account->typeLabel() }}
                </option>
            @endforeach
        </select>
        @error('account_id')<p class="master-error">{{ $message }}</p>@enderror
    </div>

    <div class="master-field">
        <label class="master-label" for="{{ $dialogId }}Category">Category</label>
        <select class="master-select" id="{{ $dialogId }}Category" name="category_id">
            <option value="">No category</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((string) $value('category_id') === (string) $category->id)>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="master-field">
        <label class="master-label" for="{{ $dialogId }}Mode">Payment mode</label>
        <select class="master-select" id="{{ $dialogId }}Mode" name="payment_mode">
            <option value="">Not set</option>
            @foreach ($paymentModeOptions as $key => $label)
                <option value="{{ $key }}" @selected($value('payment_mode') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="master-field">
        <label class="master-label" for="{{ $dialogId }}Head">Expense head</label>
        <input class="master-input" id="{{ $dialogId }}Head" type="text" name="expense_head"
            value="{{ $value('expense_head') }}" maxlength="{{ \App\Services\RecurrenceVocabulary::EXPENSE_HEAD_LIMIT }}"
            placeholder="Rent, Salary, Internet…">
    </div>

    <div class="master-field">
        <label class="master-label" for="{{ $dialogId }}PartyType">Party is a</label>
        <select class="master-select" id="{{ $dialogId }}PartyType" name="related_party_type">
            @foreach ($relatedPartyOptions as $key => $label)
                <option value="{{ $key }}" @selected($value('related_party_type', 'other') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="master-field">
        <label class="master-label" for="{{ $dialogId }}PartyName">Party name</label>
        <input class="master-input" id="{{ $dialogId }}PartyName" type="text" name="related_party_name"
            value="{{ $value('related_party_name') }}" maxlength="{{ \App\Services\RecurrenceVocabulary::NAME_LIMIT }}"
            placeholder="Who is paid, if they are not on a list">
    </div>

    <div class="master-field">
        <label class="master-label" for="{{ $dialogId }}Client">Client</label>
        <select class="master-select" id="{{ $dialogId }}Client" name="client_id">
            <option value="">No client</option>
            @foreach ($clients as $client)
                <option value="{{ $client->id }}" @selected((string) $value('client_id') === (string) $client->id)>{{ $client->company_name }}</option>
            @endforeach
        </select>
    </div>

    <div class="master-field">
        <label class="master-label" for="{{ $dialogId }}Vendor">Vendor</label>
        <select class="master-select" id="{{ $dialogId }}Vendor" name="vendor_id">
            <option value="">No vendor</option>
            @foreach ($vendors as $vendor)
                <option value="{{ $vendor->id }}" @selected((string) $value('vendor_id') === (string) $vendor->id)>{{ $vendor->vendor_name }}</option>
            @endforeach
        </select>
    </div>

    <div class="master-field">
        <label class="master-label" for="{{ $dialogId }}Employee">Employee</label>
        <select class="master-select" id="{{ $dialogId }}Employee" name="employee_id">
            <option value="">No employee</option>
            @foreach ($employees as $employee)
                <option value="{{ $employee->id }}" @selected((string) $value('employee_id') === (string) $employee->id)>
                    {{ $employee->name }}@if ($employee->designation) — {{ $employee->designation }}@endif
                </option>
            @endforeach
        </select>
    </div>

    <div class="master-field">
        <label class="master-label" for="{{ $dialogId }}OfficeService">Office service</label>
        <select class="master-select" id="{{ $dialogId }}OfficeService" name="office_service_id">
            <option value="">No office service</option>
            @foreach ($officeServices as $officeService)
                <option value="{{ $officeService->id }}" @selected((string) $value('office_service_id') === (string) $officeService->id)>{{ $officeService->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="master-field cfr-schedule-start">
        <label class="master-label" for="{{ $dialogId }}Frequency">How often? <span class="master-required" aria-hidden="true">*</span></label>
        <select class="master-select" id="{{ $dialogId }}Frequency" name="frequency" required>
            @foreach ($frequencyOptions as $key => $label)
                <option value="{{ $key }}" @selected($value('frequency', 'monthly') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @error('frequency')<p class="master-error">{{ $message }}</p>@enderror
    </div>

    <div class="master-field">
        <label class="master-label" for="{{ $dialogId }}StartsOn">Effective from <span class="master-required" aria-hidden="true">*</span></label>
        <input class="master-input" id="{{ $dialogId }}StartsOn" type="date" name="starts_on"
            value="{{ $date('starts_on') ?: now()->toDateString() }}" required>
        <p class="master-help">The first date, and the day every later one keeps — a rule on the 31st pays on the 28th in February, then on the 31st again.</p>
        @error('starts_on')<p class="master-error">{{ $message }}</p>@enderror
    </div>

    <div class="master-field">
        <label class="master-label" for="{{ $dialogId }}EndsOn">Until (optional)</label>
        <input class="master-input" id="{{ $dialogId }}EndsOn" type="date" name="ends_on" value="{{ $date('ends_on') }}">
        @error('ends_on')<p class="master-error">{{ $message }}</p>@enderror
    </div>

    <div class="master-field">
        <label class="master-label" for="{{ $dialogId }}Limit">Number of occurrences (optional)</label>
        <input class="master-input" id="{{ $dialogId }}Limit" type="number" name="occurrence_limit" min="1" max="1000"
            value="{{ $value('occurrence_limit') }}" placeholder="No limit">
        <p class="master-help">Leave both empty and the rule runs until it is ended by hand. The two boxes agree: whichever comes first stops it.</p>
    </div>

    <div class="master-field full">
        <label class="master-label" for="{{ $dialogId }}Notes">Notes</label>
        <textarea class="master-textarea" id="{{ $dialogId }}Notes" name="notes" rows="3"
            maxlength="{{ \App\Services\RecurrenceVocabulary::NOTES_LIMIT }}"
            placeholder="Anything the person approving this should know">{{ $value('notes') }}</textarea>
    </div>
</div>
