@extends('layouts.app')

@section('page-title', 'Client Detail')

@section('content')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/clients.css') }}">
@endpush

@php
    $statusClass = str_replace('_', '-', $client->status);
    $portalInstalled = class_exists(\App\Models\ClientPortalUser::class) && \Illuminate\Support\Facades\Schema::hasTable('client_portal_users');
    $portalUser = $portalInstalled ? \App\Models\ClientPortalUser::where('client_id', $client->id)->first() : null;
    $portalRouteExists = \Illuminate\Support\Facades\Route::has('clients.portal.show');
    $portalLoginRouteExists = \Illuminate\Support\Facades\Route::has('client-portal.login');
    $portalEnabled = (bool) ($client->portal_enabled ?? false);
@endphp

<div class="client client-show">
    <div class="master-card master-header">
        <div>
            <h1>{{ $client->company_name }}</h1>
            <p style="font-size:16px;font-weight:500;">{{ $client->client_number }} · Brand:
                {{ $client->brand_name ?: '-' }}</p>
        </div>
        <div class="master-actions">
            <a href="{{ route('clients.index') }}" class="master-btn master-btn-light">Back</a>
            <a href="{{ route('clients.edit', $client) }}" class="master-btn master-btn-soft">Edit Client</a>
            <a href="{{ route('clients.publicKyc', $client->public_token) }}" target="_blank"
                class="master-btn master-btn-primary">Open KYC Link</a>
                
            <a href="{{ route('clients.portal.show', $client) }}" class="master-btn master-btn-primary">
                <i class="fa-solid fa-user-lock"></i> Client Portal
            </a>
        </div>
    </div>

    <div class="master-grid">
        <div>
            <div class="master-card master-section">
                <h3 class="master-section-title">Client Overview</h3>
                <div class="master-info-grid">
                    <div class="master-info"><span>Status</span><strong><span
                                class="master-badge status-{{ $statusClass }}">{{ $client->statusLabel() }}</span></strong>
                    </div>
                    <div class="master-info"><span>Industry</span><strong>{{ $client->industry ?: '-' }}</strong></div>
                    <div class="master-info"><span>Portal</span><strong class="master-badge {{ $portalEnabled ? 'portal-enabled' : 'portal-disabled' }}">{{ $portalEnabled ? 'Enabled' : 'Disabled' }}</strong><br><strong>{{ $portalUser ? $portalUser->username : 'No credentials yet' }}</strong></div>
                    <div class="master-info"><span>Website</span><strong>{{ $client->website ?: '-' }}</strong></div>
                    <div class="master-info"><span>GSTIN</span><strong>{{ $client->gstin ?: '-' }}</strong></div>
                    <div class="master-info"><span>PAN / TAN</span><strong>{{ $client->pan ?: '-' }} /
                            {{ $client->tan ?: '-' }}</strong></div>
                    <div class="master-info"><span>KYC
                            Submitted</span><strong>{{ $client->kyc_submitted_at ? $client->kyc_submitted_at->format('d M Y, h:i A') : '-' }}</strong>
                    </div>
                    <div class="master-info"><span>Reviewed
                            By</span><strong>{{ $client->reviewer?->name ?? $client->reviewer?->email ?? '-' }}</strong>
                    </div>
                </div>
            </div>

            <div class="master-card master-section">
                <h3 class="master-section-title">Contact Persons</h3>
                <div class="master-info-grid">
                    <div class="master-info"><span>CEO /
                            Director</span><strong>{{ $client->ceo_name ?: '-' }}</strong><span
                            class="master-sub">{{ $client->ceo_email ?: '-' }}</span><span
                            class="master-sub">{{ $client->ceo_contact ?: '-' }}</span></div>
                    <div class="master-info"><span>Account
                            Person</span><strong>{{ $client->account_person_name ?: '-' }}</strong><span
                            class="master-sub">{{ $client->account_person_email ?: '-' }}</span><span
                            class="master-sub">{{ $client->account_person_contact ?: '-' }}</span></div>
                    <div class="master-info"><span>Marketing /
                            Purchase</span><strong>{{ $client->marketing_person_name ?: '-' }}</strong><span
                            class="master-sub">{{ $client->marketing_person_email ?: '-' }}</span><span
                            class="master-sub">{{ $client->marketing_person_contact ?: '-' }}</span></div>
                    <div class="master-info"><span>Inward
                            Dispatch</span><strong>{{ $client->dispatch_person_name ?: '-' }}</strong><span
                            class="master-sub">{{ $client->dispatch_person_email ?: '-' }}</span><span
                            class="master-sub">{{ $client->dispatch_person_contact ?: '-' }}</span></div>
                </div>
            </div>

            <div class="master-card master-section">
                <h3 class="master-section-title">Address Details</h3>
                <div class="master-info-grid">
                    <div class="master-info">
                            <span>Billing Address</span>
                            <strong>{{ $client->billing_address ?: '-' }}</strong>,
                            <strong>{{ $client->billing_city }}</strong>,
                            <strong>{{ $client->billing_state }}</strong>,
                            <strong>{{ $client->billing_country }} - {{ $client->billing_pincode }}</strong></div>
                    <div class="master-info">
                        <span>Shipping Address</span>
                        <strong>{{ $client->shipping_address ?: '-' }}</strong>,
                        <strong>{{ $client->shipping_city }}</strong>,
                        <strong>{{ $client->shipping_state }}</strong>,
                        <strong>{{ $client->shipping_country }} - {{ $client->shipping_pincode }}</strong>
                    </div>
                </div>
            </div>

            <div class="master-card master-section">
                <h3 class="master-section-title">Bank & Commercial Details</h3>
                <div class="master-info-grid">
                    <div class="master-info"><span>Bank Name</span><strong>{{ $client->bank_name ?: '-' }}</strong>
                    </div>
                    <div class="master-info"><span>Account
                            Holder</span><strong>{{ $client->account_holder_name ?: '-' }}</strong></div>
                    <div class="master-info"><span>Account
                            Number</span><strong>{{ $client->account_number ?: '-' }}</strong></div>
                    <div class="master-info"><span>IFSC /
                            Branch</span><strong>{{ $client->ifsc_code ?: '-' }}</strong><span
                            class="master-sub">{{ $client->bank_branch ?: '-' }}</span></div>
                    <div class="master-info"><span>Credit
                            Limit</span><strong>{{ $client->credit_limit ? $client->preferred_currency . ' ' . number_format((float) $client->credit_limit, 2) : '-' }}</strong>
                    </div>
                    <div class="master-info"><span>Credit Days</span><strong>{{ $client->credit_days ?: '-' }}</strong>
                    </div>
                    <div class="master-info full"><span>Payment
                            Terms</span><strong>{{ $client->payment_terms ?: '-' }}</strong></div>
                    <div class="master-info full"><span>Notes</span><strong>{{ $client->notes ?: '-' }}</strong></div>
                </div>
            </div>
        </div>

        <div>
            <div class="master-card master-section">
                <h3 class="master-section-title">Client Portal Access</h3>
                @if($portalInstalled)
                    <div class="portal-mini-list">
                        <div class="portal-mini-item"><span>Portal Status</span><strong class="master-badge {{ $portalEnabled ? 'portal-enabled' : 'portal-disabled' }}">{{ $portalEnabled ? 'Enabled' : 'Disabled' }}</strong></div>
                        <div class="portal-mini-item"><span>Username</span><strong>{{ $portalUser ? $portalUser->username : '-' }}</strong></div>
                        <div class="portal-mini-item"><span>Email</span><strong>{{ $portalUser ? ($portalUser->email ?: '-') : '-' }}</strong></div>
                        <div class="portal-mini-item"><span>Last Login</span><strong>{{ $portalUser && $portalUser->last_login_at ? $portalUser->last_login_at->format('d M Y, h:i A') : '-' }}</strong></div>
                        
                    </div>
                    @if($portalLoginRouteExists)
                        <div class="portal-credential-box"><span>Client Login URL</span><strong>{{ route('client-portal.login') }}</strong></div>
                    @endif
                    <div class="master-actions">
                        @if($portalRouteExists)
                            <a href="{{ route('clients.portal.show', $client) }}" class="master-btn master-btn-pink">Manage Portal</a>
                        @endif
                        @if($portalLoginRouteExists)
                            <a href="{{ route('client-portal.login') }}" target="_blank" class="master-btn master-btn-soft">Open Login</a>
                        @endif
                    </div>
                @else
                    <div class="master-muted-box">Client portal module files/tables are not installed yet.</div>
                @endif
            </div>
            <div class="master-card master-section">
                <h3 class="master-section-title">Public KYC Link</h3>
                <div class="public-box"><input class="master-input" id="clientKycLink" readonly
                        value="{{ route('clients.publicKyc', $client->public_token) }}"><button type="button"
                        class="master-btn master-btn-soft"
                        onclick="navigator.clipboard ? navigator.clipboard.writeText(document.getElementById('clientKycLink').value) : prompt('Copy link', document.getElementById('clientKycLink').value)">Copy</button>
                </div>
                <form method="POST" action="{{ route('clients.sendKyc', $client) }}" style="margin-top:12px;">@csrf
                    @method('PATCH')<button class="master-btn master-btn-primary" type="submit">Mark KYC Link
                        Sent</button></form>
                <p class="master-sub">Sent:
                    {{ $client->kyc_sent_at ? $client->kyc_sent_at->format('d M Y, h:i A') : 'Not marked sent' }}</p>
            </div>

            <div class="master-card master-section">
                <h3 class="master-section-title">KYC Review Action</h3>
                <form method="POST" action="{{ route('clients.status.update', $client) }}" class="review-form">
                    @csrf
                    @method('PATCH')
                    <div style="margin-top:18px;">
                        <input class="master-input" name="revision_note"
                            placeholder="Revision note for client, if revision is required" value="{{ old('revision_note', $client->revision_note) }}">
                    </div>
                    <div style="margin-top:18px;margin-bottom:18px;">
                        <input class="master-input" name="rejection_reason"
                            placeholder="Rejection reason, if rejected" value="{{ old('rejection_reason', $client->rejection_reason) }}">
                    </div>
                    <div class="review-form-buttons">
                        <button class="master-btn master-btn-green" type="submit" name="status"
                            value="approved">Approve</button>
                        <button class="master-btn master-btn-orange" type="submit" name="status"
                            value="revision">Revision</button>
                        <button class="master-btn master-btn-danger" type="submit" name="status"
                            value="rejected">Reject</button>
                    </div>
                </form>
            </div>

            @if($client->revision_note)
                <div class="master-card master-section">
                    <h3 class="master-section-title">Revision Note</h3>
                    <div class="master-muted-box">{{ $client->revision_note }}</div>
                </div>
            @endif
            @if($client->rejection_reason)
                <div class="master-card master-section">
                    <h3 class="master-section-title">Rejection Reason</h3>
                    <div class="master-muted-box">{{ $client->rejection_reason }}</div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection