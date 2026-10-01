@extends('client_portal.layouts.app')

@section('title', 'Change Password')
@section('page-title', 'Change Password')

@section('content')
<div class="cp-page-head">
    <div>
        <p class="cp-eyebrow">Security</p>
        <h1>Change Password</h1>
        <p>Create a private password for your client portal account.</p>
    </div>
</div>
<div class="cp-card" style="padding:22px;max-width:620px;">
    <form method="POST" action="{{ route('client-portal.password.update') }}" class="cp-form-grid">
        @csrf
        @if(! $portalUser->must_change_password)
            <div class="master-field" style="grid-column:1/-1;"><label class="master-label">Current Password</label><input class="master-input" type="password" name="current_password" required></div>
        @endif
        <div class="master-field"><label class="master-label">New Password</label><input class="master-input" type="password" name="password" required></div>
        <div class="master-field"><label class="master-label">Confirm Password</label><input class="master-input" type="password" name="password_confirmation" required></div>
        <div style="grid-column:1/-1;display:flex;justify-content:flex-end;gap:10px;"><button class="master-btn master-btn-primary" type="submit">Update Password</button></div>
    </form>
</div>
@endsection
