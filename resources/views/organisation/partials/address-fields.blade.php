@php($row = $address)
<div class="master-form-grid is-three">
    <div class="master-field">
        <label class="master-label">Kind</label>
        <select class="master-select" name="kind">
            @foreach ($kinds as $key => $label)
                <option value="{{ $key }}" @selected(($row->kind ?? 'billing') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="master-field">
        <label class="master-label">Label</label>
        <input class="master-input" name="label" value="{{ $row->label ?? '' }}" placeholder="Registered office">
    </div>
    <div class="master-field">
        <label class="master-label">&nbsp;</label>
        <label class="master-check"><input type="checkbox" name="is_default" value="1" @checked($row?->is_default ?? true)> Default for this kind</label>
    </div>
    <div class="master-field full">
        <label class="master-label">Line 1</label>
        <input class="master-input" name="line1" value="{{ $row->line1 ?? '' }}" required>
    </div>
    <div class="master-field full">
        <label class="master-label">Line 2</label>
        <input class="master-input" name="line2" value="{{ $row->line2 ?? '' }}">
    </div>
    <div class="master-field">
        <label class="master-label">City</label>
        <input class="master-input" name="city" value="{{ $row->city ?? '' }}">
    </div>
    <div class="master-field">
        <label class="master-label">State</label>
        <input class="master-input" name="state" value="{{ $row->state ?? '' }}">
    </div>
    <div class="master-field">
        <label class="master-label">PIN</label>
        <input class="master-input" name="pincode" value="{{ $row->pincode ?? '' }}">
    </div>
    <div class="master-field">
        <label class="master-label">Country</label>
        <input class="master-input" name="country" value="{{ $row->country ?? 'India' }}">
    </div>
</div>
