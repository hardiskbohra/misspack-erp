{{--
    Changing your own password.

    Shared by the same two pages as the details form, so the rules are asked once
    — at least eight characters, and the current password even though the person
    is already signed in, because a session left open on a shared machine is
    exactly what a password change is meant to close (`AccountController`).

    Variables: $action, $submit, $prefix.
--}}
@php
    $prefix = $prefix ?? 'own';
    $submit = $submit ?? 'Change password';
@endphp

<form method="POST" action="{{ $action }}">
    @csrf
    @method('PUT')

    <div class="master-form-grid">
        <div class="master-field full">
            <label class="master-label" for="{{ $prefix }}-current">Current password</label>
            <div class="master-password-wrap">
                <input class="master-input {{ $errors->has('current_password') ? 'is-invalid' : '' }}"
                    id="{{ $prefix }}-current" type="password" name="current_password" autocomplete="current-password">
                <button type="button" class="master-password-toggle" data-toggle-password="{{ $prefix }}-current"
                    aria-label="Toggle password visibility"><i class="fas fa-eye" aria-hidden="true"></i></button>
            </div>
            @error('current_password')<p class="master-error">{{ $message }}</p>@enderror
        </div>
        <div class="master-field">
            <label class="master-label" for="{{ $prefix }}-new">New password</label>
            <div class="master-password-wrap">
                <input class="master-input {{ $errors->has('password') ? 'is-invalid' : '' }}"
                    id="{{ $prefix }}-new" type="password" name="password" placeholder="Min. 8 characters"
                    autocomplete="new-password">
                <button type="button" class="master-password-toggle" data-toggle-password="{{ $prefix }}-new"
                    aria-label="Toggle password visibility"><i class="fas fa-eye" aria-hidden="true"></i></button>
            </div>
            @error('password')<p class="master-error">{{ $message }}</p>@enderror
        </div>
        <div class="master-field">
            <label class="master-label" for="{{ $prefix }}-repeat">Repeat it</label>
            <div class="master-password-wrap">
                <input class="master-input" id="{{ $prefix }}-repeat" type="password" name="password_confirmation"
                    placeholder="Repeat password" autocomplete="new-password">
                <button type="button" class="master-password-toggle" data-toggle-password="{{ $prefix }}-repeat"
                    aria-label="Toggle password visibility"><i class="fas fa-eye" aria-hidden="true"></i></button>
            </div>
        </div>
    </div>

    <div class="master-actions">
        <button class="master-btn master-btn-soft" type="submit">{{ $submit }}</button>
    </div>
</form>
