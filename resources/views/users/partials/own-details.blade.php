{{--
    The fields that belong to the person, whoever they are.

    Five names, and they are not chosen here: `EmployeeAccess::ownEditableFields()`
    is the rule (the mobile number, the address, the birthday, and who to call in
    an emergency are things only the person knows; designation, pay and bank
    account are the office's record). The controllers validate with the service's
    own rule list and filter the result through the same list, so this form
    cannot by itself widen what a person may write.

    Two pages render it — the employee's own profile and the account page both
    roles share — which is why it is one file: the second copy of a form is how
    the two start asking different questions.

    Variables: $action (the route to post to), $submit (the button's words),
    $me (the person), $grid (the field grid class), $sticky (pin the action bar).
--}}
@php
    $grid = $grid ?? 'master-form-grid';
    $submit = $submit ?? 'Save my details';
@endphp

<form method="POST" action="{{ $action }}">
    @csrf
    @method('PUT')

    <div class="{{ $grid }}">
        <div class="master-field">
            <label class="master-label" for="{{ $prefix = ($prefix ?? 'own') }}-mobile">Mobile</label>
            <input class="master-input" id="{{ $prefix }}-mobile" type="text" name="mobile"
                placeholder="+91 …" value="{{ old('mobile', $me->mobile) }}">
            @error('mobile')<p class="master-error">{{ $message }}</p>@enderror
        </div>
        <div class="master-field">
            <label class="master-label" for="{{ $prefix }}-dob">Date of birth</label>
            <input class="master-input" id="{{ $prefix }}-dob" type="date" name="date_of_birth"
                value="{{ old('date_of_birth', $me->date_of_birth?->format('Y-m-d')) }}">
            @error('date_of_birth')<p class="master-error">{{ $message }}</p>@enderror
        </div>
        <div class="master-field">
            <label class="master-label" for="{{ $prefix }}-emergency">Emergency contact</label>
            <input class="master-input" id="{{ $prefix }}-emergency" type="text" name="emergency_contact_name"
                placeholder="Who to call" value="{{ old('emergency_contact_name', $me->emergency_contact_name) }}">
            @error('emergency_contact_name')<p class="master-error">{{ $message }}</p>@enderror
        </div>
        <div class="master-field">
            <label class="master-label" for="{{ $prefix }}-emergency-mobile">Emergency mobile</label>
            <input class="master-input" id="{{ $prefix }}-emergency-mobile" type="text" name="emergency_contact_mobile"
                placeholder="+91 …" value="{{ old('emergency_contact_mobile', $me->emergency_contact_mobile) }}">
            @error('emergency_contact_mobile')<p class="master-error">{{ $message }}</p>@enderror
        </div>
        <div class="master-field full">
            <label class="master-label" for="{{ $prefix }}-address">Address</label>
            <textarea class="master-input" id="{{ $prefix }}-address" name="address" rows="3"
                placeholder="Where you live">{{ old('address', $me->address) }}</textarea>
            @error('address')<p class="master-error">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="master-actions{{ ($sticky ?? false) ? ' is-sticky' : '' }}">
        <button class="master-btn master-btn-primary" type="submit">{{ $submit }}</button>
    </div>
</form>
