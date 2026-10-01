@extends('client_portal.layouts.app')

@section('title', 'KYC Form')
@section('page-title', 'KYC Form')

@section('content')
    <div class="master-header" style="padding:5px;line-height:1;">
        <div>
            <h1>Client KYC</h1>
            <p style="font-size:16px;font-weight:500;">Review and update your company KYC information.</p>
        </div>
        <div class="master-actions">
            <a href="{{ $kycUrl }}" target="_blank" class="master-btn master-btn-primary">Open KYC Form</a>
        </div>
    </div>
    <div class="cp-grid-2">
        <div class="cp-card" style="padding:20px;">
            <p class="cp-eyebrow" style="margin-bottom:0px;">Status</p>
            <h2 style="margin-top:0;">KYC Status</h2>
            <div class="cp-grid-2" style="margin-top:15px;">
                <div>
                    <span class="cp-muted">Client Number</span>
                    <strong style="display:block;font-weight:600;">{{ $client->client_number }}</strong>
                </div>
                <div>
                    <span class="cp-muted">Status</span>
                    <strong style="display:block;font-weight:600;">{{ method_exists($client, 'statusLabel') ? $client->statusLabel() : ucfirst($client->status) }}</strong>
                </div>
                <div>
                    <span class="cp-muted">Submitted</span>
                    <strong style="display:block;font-weight:600;">{{ $client->kyc_submitted_at ? $client->kyc_submitted_at->format('d M Y') : '-' }}</strong>
                </div>
                <div>
                    <span class="cp-muted">Reviewed</span>
                    <strong style="display:block;font-weight:600;">{{ $client->kyc_reviewed_at ? $client->kyc_reviewed_at->format('d M Y') : '-' }}</strong>
                </div>
            </div>
            @if ($client->revision_note)
                <div class="cp-alert cp-alert-warning" style="margin-top:14px;">Revision Note: {{ $client->revision_note }}
                </div>
            @endif
            @if ($client->rejection_reason)
                <div class="cp-alert cp-alert-error" style="margin-top:14px;">Rejection Reason:
                    {{ $client->rejection_reason }}</div>
            @endif
        </div>
        <div class="cp-card" style="padding:20px;">
            <p class="cp-eyebrow">Secure Link</p>
            <h2 style="margin-top:0;">KYC Form Link</h2>
            <p class="cp-muted" style="margin-top:15px;">
                Use this link to open the MissPack KYC form. If the form is under review or approved, editing may be locked.
            </p>
            <div class="master-field" style="margin-top:15px;">
                <label class="master-label">KYC URL</label>
                <input class="master-input" readonly value="{{ $kycUrl }}" id="kycLink">
            </div>
            <button
                class="master-btn master-btn-soft"
                type="button"
                style="margin-top:10px;"
                onclick="copyKycLink()">
                Copy Link
            </button>
            
            <span id="copySuccess"
                  style="display:none; margin-left:10px; color:#16a34a; font-size:13px; font-weight:600;">
                Copied successfully!
            </span>
        </div>
    </div>
    
@push('scripts')
    <script src="{{ asset('assets/js/client-portal-kyc.js') }}"></script>
@endpush
@endsection
