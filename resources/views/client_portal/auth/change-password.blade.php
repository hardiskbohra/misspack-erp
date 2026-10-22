@extends('client_portal.layouts.app')

@section('title', 'Change password')
@section('page-title', 'Security')

@section('content')
<div class="cp-page-head">
    <div><p class="cp-eyebrow">Account security</p><h1>Change password</h1><p>Use at least 12 characters and choose a password you do not use elsewhere.</p></div>
</div>
<div class="cp-password-layout">
    <section class="cp-card cp-section-card">
        <div class="cp-section-heading"><div><p class="cp-eyebrow">Credentials</p><h2>Set a new password</h2></div><span class="cp-support-icon"><i class="fa-solid fa-key"></i></span></div>
        <form method="POST" action="{{ route('client-portal.password.update') }}" class="cp-form-grid">
            @csrf
            @if(! $portalUser->must_change_password)
                <div class="master-field cp-field-full"><label class="master-label" for="current-password">Current password</label><input class="master-input" id="current-password" type="password" name="current_password" autocomplete="current-password" required></div>
            @endif
            <div class="master-field"><label class="master-label" for="new-password">New password</label><input class="master-input" id="new-password" type="password" name="password" autocomplete="new-password" minlength="12" required><small class="cp-field-help">Minimum 12 characters.</small></div>
            <div class="master-field"><label class="master-label" for="confirm-password">Confirm password</label><input class="master-input" id="confirm-password" type="password" name="password_confirmation" autocomplete="new-password" minlength="12" required></div>
            <div class="cp-card-actions cp-field-full"><a class="master-btn master-btn-soft" href="{{ route('client-portal.account.index') }}">Cancel</a><button class="master-btn master-btn-primary" type="submit">Update password</button></div>
        </form>
    </section>
    <aside class="cp-card cp-section-card cp-password-guidance">
        <span class="cp-security-icon"><i class="fa-solid fa-shield-halved"></i></span>
        <h2>Keep your account protected</h2>
        <ul><li>Use a unique passphrase.</li><li>Never share your password by email or chat.</li><li>Sign out on shared devices.</li></ul>
    </aside>
</div>
@endsection
