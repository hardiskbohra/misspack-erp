@php($row = $bank)
<div class="master-form-grid is-three">
    <div class="master-field">
        <label class="master-label">Label</label>
        <input class="master-input" name="label" value="{{ $row->label ?? '' }}" placeholder="HDFC operating">
    </div>
    <div class="master-field">
        <label class="master-label">Bank</label>
        <input class="master-input" name="bank_name" value="{{ $row->bank_name ?? '' }}" required>
    </div>
    <div class="master-field">
        <label class="master-label">Account holder</label>
        <input class="master-input" name="account_holder" value="{{ $row->account_holder ?? '' }}" required>
    </div>
    <div class="master-field">
        <label class="master-label">Account number</label>
        <input class="master-input" name="account_number" value="{{ $row->account_number ?? '' }}" required>
    </div>
    <div class="master-field">
        <label class="master-label">IFSC</label>
        <input class="master-input" name="ifsc" value="{{ $row->ifsc ?? '' }}">
    </div>
    <div class="master-field">
        <label class="master-label">Branch</label>
        <input class="master-input" name="branch" value="{{ $row->branch ?? '' }}">
    </div>
    <div class="master-field">
        <label class="master-label">SWIFT</label>
        <input class="master-input" name="swift" value="{{ $row->swift ?? '' }}">
    </div>
    <div class="master-field">
        <label class="master-label">&nbsp;</label>
        <label class="master-check"><input type="checkbox" name="is_default" value="1" @checked($row?->is_default ?? false)> Default for invoices</label>
    </div>
</div>
