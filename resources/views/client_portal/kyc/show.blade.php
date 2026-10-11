@extends('client_portal.layouts.app')

@section('title', 'KYC workspace')
@section('page-title', 'KYC workspace')

@section('content')
<div class="cp-page-head">
    <div>
        <p class="cp-eyebrow">Company verification</p>
        <h1>KYC workspace</h1>
        <p>Review your company verification status and securely update the information MissPack needs.</p>
    </div>
    <a href="{{ $kycUrl }}" target="_blank" rel="noopener" class="master-btn master-btn-primary"><i class="fa-solid fa-arrow-up-right-from-square"></i> Open KYC form</a>
</div>

<div class="cp-grid-2 cp-content-grid">
    <section class="cp-card cp-section-card">
        <div class="cp-section-heading">
            <div><p class="cp-eyebrow">Verification</p><h2>Current status</h2></div>
            <span class="cp-status-pill">{{ method_exists($client, 'statusLabel') ? $client->statusLabel() : ucfirst($client->status) }}</span>
        </div>
        <dl class="cp-fact-list">
            <div><dt>Client number</dt><dd>{{ $client->client_number }}</dd></div>
            <div><dt>Submitted</dt><dd>{{ $client->kyc_submitted_at ? $client->kyc_submitted_at->format('d M Y') : 'Not submitted' }}</dd></div>
            <div><dt>Reviewed</dt><dd>{{ $client->kyc_reviewed_at ? $client->kyc_reviewed_at->format('d M Y') : 'Not reviewed' }}</dd></div>
        </dl>
        @if ($client->revision_note)
            <div class="cp-inline-alert cp-inline-alert-warning"><i class="fa-solid fa-triangle-exclamation"></i><div><strong>Changes requested</strong><p>{{ $client->revision_note }}</p></div></div>
        @endif
        @if ($client->rejection_reason)
            <div class="cp-inline-alert cp-inline-alert-danger"><i class="fa-solid fa-circle-exclamation"></i><div><strong>Review note</strong><p>{{ $client->rejection_reason }}</p></div></div>
        @endif
    </section>

    <section class="cp-card cp-section-card">
        <div class="cp-section-heading">
            <div><p class="cp-eyebrow">Secure access</p><h2>Your KYC form link</h2></div>
            <span class="cp-support-icon"><i class="fa-solid fa-shield-halved"></i></span>
        </div>
        <p class="cp-section-copy">Use this private link to open your company KYC form. Editing may be locked while the form is under review or after approval.</p>
        <div class="master-field">
            <label class="master-label" for="kycLink">KYC URL</label>
            <input class="master-input" readonly value="{{ $kycUrl }}" id="kycLink">
        </div>
        <div class="cp-card-actions cp-card-actions-start">
            <button class="master-btn master-btn-soft" type="button" onclick="copyKycLink()"><i class="fa-regular fa-copy"></i> Copy link</button>
            <span id="copySuccess" class="cp-copy-success" hidden><i class="fa-solid fa-circle-check"></i> Copied</span>
        </div>
    </section>
</div>

@push('scripts')
<script src="{{ asset('assets/js/client-portal-kyc.js') }}"></script>
@endpush
@endsection
