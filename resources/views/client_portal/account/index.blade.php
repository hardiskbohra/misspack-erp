@extends('client_portal.layouts.app')

@section('title', 'My profile')
@section('page-title', 'My account')

@section('content')
<div class="cp-page-head">
    <div><p class="cp-eyebrow">Account settings</p><h1>My profile</h1><p>Update your personal contact details and keep your sign-in secure.</p></div>
</div>

<div class="cp-account-grid">
    <section class="cp-card cp-account-card">
        <div class="cp-section-heading"><div><p class="cp-eyebrow">Your details</p><h2>Profile information</h2></div><span class="cp-support-icon"><i class="fa-regular fa-user"></i></span></div>
        <form method="POST" action="{{ route('client-portal.account.profile.update') }}" class="cp-form-grid">
            @csrf @method('PUT')
            <div class="master-field"><label class="master-label" for="profile-name">Display name</label><input class="master-input" id="profile-name" name="name" value="{{ old('name', $portalUser->name) }}" maxlength="255" required></div>
            <div class="master-field"><label class="master-label" for="profile-username">Username</label><input class="master-input" id="profile-username" value="{{ $portalUser->username }}" readonly><small class="cp-field-help">Contact MissPack to change your username.</small></div>
            <div class="master-field"><label class="master-label" for="profile-email">Sign-in email</label><input class="master-input" id="profile-email" type="email" name="email" value="{{ old('email', $portalUser->email) }}" maxlength="255"><small class="cp-field-help">Changing this also changes the email you can use to sign in.</small></div>
            <div class="master-field"><label class="master-label" for="profile-mobile">Mobile</label><input class="master-input" id="profile-mobile" name="mobile" value="{{ old('mobile', $portalUser->mobile) }}" maxlength="40"></div>
            <div class="master-field cp-current-password-field"><label class="master-label" for="profile-current-password">Current password <span>(required to change sign-in email)</span></label><input class="master-input" id="profile-current-password" type="password" name="current_password" autocomplete="current-password"></div>
            <div class="cp-account-form-actions"><button class="master-btn master-btn-primary" type="submit">Save profile</button></div>
        </form>
    </section>

    <aside class="cp-account-side">
        <section class="cp-card cp-account-card cp-security-card">
            <span class="cp-security-icon"><i class="fa-solid fa-shield-halved"></i></span>
            <p class="cp-eyebrow">Security</p><h2>Password & access</h2>
            <p>Use a unique passphrase and keep your credentials private. MissPack support will never ask you to share your current password.</p>
            <div class="cp-security-meta"><span>Last signed in</span><strong>{{ $portalUser->last_login_at?->format('d M Y, h:i A') ?: 'This session' }}</strong></div>
            <div class="cp-security-meta"><span>Password last updated</span><strong>{{ $portalUser->password_changed_at?->format('d M Y') ?: 'Not recorded' }}</strong></div>
            <a class="master-btn master-btn-primary" href="{{ route('client-portal.password.edit') }}">Change password <i class="fa-solid fa-arrow-right"></i></a>
        </section>
        <section class="cp-card cp-account-card cp-account-company">
            <p class="cp-eyebrow">Client account</p><h2>{{ $portalUser->client?->company_name ?: 'MissPack Client' }}</h2>
            <p>Company and KYC information is managed in the client profile workspace.</p>
            <a class="cp-text-link" href="{{ route('client-portal.kyc.show') }}">Open KYC workspace <i class="fa-solid fa-arrow-right"></i></a>
        </section>
    </aside>
</div>
@endsection
