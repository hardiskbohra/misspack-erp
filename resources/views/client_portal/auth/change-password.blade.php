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
            <div class="cp-field" style="grid-column:1/-1;"><label>Current Password</label><input type="password" name="current_password" required></div>
        @endif
        <div class="cp-field"><label>New Password</label><input type="password" name="password" required></div>
        <div class="cp-field"><label>Confirm Password</label><input type="password" name="password_confirmation" required></div>
        <div style="grid-column:1/-1;display:flex;justify-content:flex-end;gap:10px;"><button class="cp-btn cp-btn-primary" type="submit">Update Password</button></div>
    </form>
</div>
@endsection
