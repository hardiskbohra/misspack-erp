@extends('layouts.app')

@section('title', $client->company_name)
@section('page-title', 'Client profile')

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/clients.css') }}">
@endpush

@php
    $statusClass = str_replace('_', '-', $client->status);
    $portalEnabled = (bool) ($client->portal_enabled ?? false);
    $portalLoginRouteExists = \Illuminate\Support\Facades\Route::has('client-portal.login');
    $initials = collect(explode(' ', trim($client->company_name)))
        ->filter()
        ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
        ->take(2)
        ->implode('');
    $formatAddress = fn (...$parts) => collect($parts)->filter(fn ($part) => filled($part))->implode(', ');
    $billingAddress = $formatAddress($client->billing_address, $client->billing_city, $client->billing_state, $client->billing_country, $client->billing_pincode);
    $shippingAddress = $formatAddress($client->shipping_address, $client->shipping_city, $client->shipping_state, $client->shipping_country, $client->shipping_pincode);
    $contactGroups = [
        ['label' => 'CEO / Director', 'name' => 'ceo_name', 'email' => 'ceo_email', 'phone' => 'ceo_contact'],
        ['label' => 'Accounts', 'name' => 'account_person_name', 'email' => 'account_person_email', 'phone' => 'account_person_contact'],
        ['label' => 'Marketing / Purchase', 'name' => 'marketing_person_name', 'email' => 'marketing_person_email', 'phone' => 'marketing_person_contact'],
        ['label' => 'Inward Dispatch', 'name' => 'dispatch_person_name', 'email' => 'dispatch_person_email', 'phone' => 'dispatch_person_contact'],
    ];
@endphp

<div class="client client-show">
    <header class="master-card master-header client-record-header">
        <div class="client-record-identity">
            <span class="client-record-mark" aria-hidden="true">{{ $initials }}</span>
            <div class="client-record-copy">
                <h1>{{ $client->company_name }}</h1>
                <div class="client-record-meta">
                    <span>{{ $client->client_number }}</span>
                    @if($client->brand_name)
                        <span aria-hidden="true">·</span><span>{{ $client->brand_name }}</span>
                    @endif
                    <span class="master-badge status-{{ $statusClass }}">{{ $client->statusLabel() }}</span>
                    <span class="master-chip"><i class="fa-solid fa-building" aria-hidden="true"></i> {{ $client->typeLabel() }}</span>
                </div>
            </div>
        </div>
        <nav class="client-record-actions" aria-label="Client actions">
            <a href="{{ route('clients.index') }}" class="master-btn master-btn-light"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Clients</a>
            <a href="{{ route('clients.edit', $client) }}" class="master-btn master-btn-primary"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit client</a>
            <a href="{{ route('cashflows.statements.show', ['partyType' => 'client', 'party' => $client->id]) }}" class="master-btn master-btn-soft"><i class="fa-solid fa-file-invoice" aria-hidden="true"></i> Statement</a>
            @if($portalInstalled && \Illuminate\Support\Facades\Route::has('clients.portal.show'))
                <a href="{{ route('clients.portal.show', $client) }}" class="master-btn master-btn-soft"><i class="fa-solid fa-user-lock" aria-hidden="true"></i> Portal</a>
            @endif
        </nav>
    </header>

    <div class="master-grid">
        <div class="client-record-main">
            <section class="master-card master-section client-record-section" aria-labelledby="client-overview-heading">
                <h2 class="master-section-title" id="client-overview-heading">Company overview</h2>
                <div class="master-info-grid">
                    <div class="master-info"><span>Client type</span><strong>{{ $client->typeLabel() }}</strong></div>
                    <div class="master-info"><span>Industry</span><strong>{{ $client->industry ?: 'Not provided' }}</strong></div>
                    <div class="master-info"><span>Website</span><strong>{{ $client->website ?: 'Not provided' }}</strong></div>
                    <div class="master-info"><span>GSTIN</span><strong>{{ $client->gstin ?: 'Not provided' }}</strong></div>
                    <div class="master-info"><span>PAN</span><strong>{{ $client->pan ?: 'Not provided' }}</strong></div>
                    <div class="master-info"><span>TAN</span><strong>{{ $client->tan ?: 'Not provided' }}</strong></div>
                    <div class="master-info"><span>CIN</span><strong>{{ $client->cin ?: 'Not provided' }}</strong></div>
                    <div class="master-info"><span>MSME number</span><strong>{{ $client->msme_number ?: 'Not provided' }}</strong></div>
                    <div class="master-info"><span>KYC submitted</span><strong>{{ $client->kyc_submitted_at?->format('d M Y, h:i A') ?: 'Not submitted' }}</strong></div>
                    <div class="master-info"><span>KYC reviewed</span><strong>{{ $client->kyc_reviewed_at?->format('d M Y, h:i A') ?: 'Not reviewed' }}</strong></div>
                    <div class="master-info"><span>Reviewed by</span><strong>{{ $client->reviewer?->name ?? $client->reviewer?->email ?? '—' }}</strong></div>
                    <div class="master-info"><span>Client record created</span><strong>{{ $client->created_at?->format('d M Y') ?: '—' }}</strong></div>
                </div>
            </section>

            <section class="master-card master-section client-record-section" aria-labelledby="client-contacts-heading">
                <h2 class="master-section-title" id="client-contacts-heading">Contact people</h2>
                <div class="master-info-grid">
                    @foreach($contactGroups as $contact)
                        @php
                            $contactName = $client->{$contact['name']};
                            $contactEmail = $client->{$contact['email']};
                            $contactPhone = $client->{$contact['phone']};
                        @endphp
                        <div class="master-info client-contact-card">
                            <span>{{ $contact['label'] }}</span>
                            <strong>{{ $contactName ?: 'Not provided' }}</strong>
                            @if($contactEmail)
                                <a class="master-sub client-contact-email" href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>
                            @else
                                <span class="master-sub">No email</span>
                            @endif
                            @if($contactPhone)
                                <a class="master-sub client-contact-email" href="tel:{{ preg_replace('/[^0-9+]/', '', $contactPhone) }}">{{ $contactPhone }}</a>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="master-card master-section client-record-section" aria-labelledby="client-addresses-heading">
                <h2 class="master-section-title" id="client-addresses-heading">Addresses</h2>
                <div class="master-info-grid">
                    <div class="master-info">
                        <span>Billing address</span>
                        <strong class="client-address-line">{{ $billingAddress ?: 'Not provided' }}</strong>
                    </div>
                    <div class="master-info">
                        <span>Shipping address</span>
                        <strong class="client-address-line">{{ $shippingAddress ?: 'Not provided' }}</strong>
                        @if($client->shipping_same_as_billing)
                            <span class="master-sub">Same as billing</span>
                        @endif
                    </div>
                </div>
            </section>

            <section class="master-card master-section client-record-section" aria-labelledby="client-commercial-heading">
                <h2 class="master-section-title" id="client-commercial-heading">Bank & commercial details</h2>
                <div class="master-info-grid">
                    <div class="master-info"><span>Bank</span><strong>{{ $client->bank_name ?: 'Not provided' }}</strong></div>
                    <div class="master-info"><span>Account holder</span><strong>{{ $client->account_holder_name ?: 'Not provided' }}</strong></div>
                    <div class="master-info"><span>Account number</span><strong>{{ $client->account_number ?: 'Not provided' }}</strong></div>
                    <div class="master-info"><span>IFSC / branch</span><strong>{{ $client->ifsc_code ?: 'Not provided' }}@if($client->bank_branch)<span class="master-sub">{{ $client->bank_branch }}</span>@endif</strong></div>
                    <div class="master-info"><span>SWIFT code</span><strong>{{ $client->swift_code ?: 'Not provided' }}</strong></div>
                    <div class="master-info"><span>Credit limit</span><strong>{{ $client->credit_limit !== null ? \App\Helpers\CommonHelper::amount($client->credit_limit, $client->preferred_currency) : 'Not set' }}</strong></div>
                    <div class="master-info"><span>Credit days</span><strong>{{ $client->credit_days !== null ? $client->credit_days : 'Not set' }}</strong></div>
                    <div class="master-info"><span>Preferred currency</span><strong>{{ $client->preferred_currency ?: 'INR' }}</strong></div>
                    <div class="master-info full"><span>Payment terms</span><strong>{{ $client->payment_terms ?: 'Not provided' }}</strong></div>
                    <div class="master-info full"><span>Internal notes</span><strong class="client-address-line">{{ $client->notes ?: 'No notes' }}</strong></div>
                </div>
            </section>
        </div>

        <aside class="client-record-aside" aria-label="Client access and KYC">
            <section class="master-card master-section client-record-section" aria-labelledby="client-portal-heading">
                <h2 class="master-section-title" id="client-portal-heading">Client portal</h2>
                @if($portalInstalled)
                    <div class="portal-mini-list">
                        <div class="portal-mini-item"><span>Access</span><strong><span class="master-badge {{ $portalEnabled ? 'portal-enabled' : 'portal-disabled' }}">{{ $portalEnabled ? 'Enabled' : 'Disabled' }}</span></strong></div>
                        <div class="portal-mini-item"><span>Username</span><strong>{{ $portalUser?->username ?: 'No credentials' }}</strong></div>
                        <div class="portal-mini-item"><span>Email</span><strong>{{ $portalUser?->email ?: 'Not set' }}</strong></div>
                        <div class="portal-mini-item"><span>Last login</span><strong>{{ $portalUser?->last_login_at?->format('d M Y, h:i A') ?: 'No login yet' }}</strong></div>
                    </div>
                    @if($portalLoginRouteExists)
                        <div class="portal-credential-box"><span>Portal sign-in</span><strong>{{ route('client-portal.login') }}</strong></div>
                    @endif
                    <div class="master-actions">
                        @if(\Illuminate\Support\Facades\Route::has('clients.portal.show'))
                            <a href="{{ route('clients.portal.show', $client) }}" class="master-btn master-btn-primary"><i class="fa-solid fa-sliders" aria-hidden="true"></i> Manage access</a>
                        @endif
                        @if($portalLoginRouteExists)
                            <a href="{{ route('client-portal.login') }}" target="_blank" rel="noopener" class="master-btn master-btn-soft">Open portal</a>
                        @endif
                    </div>
                @else
                    <div class="master-empty-state"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><p>Client portal access is not available in this installation.</p></div>
                @endif
            </section>

            <section class="master-card master-section client-record-section" aria-labelledby="client-kyc-link-heading">
                <h2 class="master-section-title" id="client-kyc-link-heading">KYC form link</h2>
                <p class="review-help">Share this secure link so the client can submit or update their KYC details.</p>
                <div class="public-box">
                    <input class="master-input" id="clientKycLink" type="text" readonly aria-label="Public KYC link" value="{{ route('clients.publicKyc', $client->public_token) }}">
                    <button type="button" class="master-btn master-btn-soft" onclick="maCopy('clientKycLink', 'KYC link')"><i class="fa-regular fa-copy" aria-hidden="true"></i> Copy</button>
                </div>
                <div class="client-kyc-sent-row">
                    <span class="master-sub">{{ $client->kyc_sent_at ? 'Last marked sent '.$client->kyc_sent_at->format('d M Y, h:i A') : 'Link has not been marked as sent' }}</span>
                    <form method="POST" action="{{ route('clients.sendKyc', $client) }}">
                        @csrf
                        @method('PATCH')
                        <button class="master-btn master-btn-light master-btn-sm" type="submit"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Mark as sent</button>
                    </form>
                </div>
                <a href="{{ route('clients.publicKyc', $client->public_token) }}" target="_blank" rel="noopener" class="client-public-link">Preview public KYC form <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
            </section>

            <section class="master-card master-section client-record-section" aria-labelledby="client-review-heading">
                <h2 class="master-section-title" id="client-review-heading">KYC review</h2>
                <p class="review-help">Choose a decision. Add a reason when requesting changes or rejecting a submission.</p>
                <form method="POST" action="{{ route('clients.status.update', $client) }}" class="review-form" data-client-review-form>
                    @csrf
                    @method('PATCH')
                    <div class="master-field">
                        <label class="master-label" for="client_revision_note">Revision note</label>
                        <textarea class="master-textarea" id="client_revision_note" name="revision_note" maxlength="5000" rows="3" placeholder="Tell the client which details need an update">{{ old('revision_note', $client->revision_note) }}</textarea>
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="client_rejection_reason">Rejection reason</label>
                        <textarea class="master-textarea" id="client_rejection_reason" name="rejection_reason" maxlength="5000" rows="3" placeholder="Explain why this KYC cannot be approved">{{ old('rejection_reason', $client->rejection_reason) }}</textarea>
                    </div>
                    <div class="review-form-buttons">
                        <button class="master-btn master-btn-green" type="submit" name="status" value="approved"><i class="fa-solid fa-check" aria-hidden="true"></i> Approve</button>
                        <button class="master-btn master-btn-orange" type="submit" name="status" value="revision"><i class="fa-solid fa-rotate" aria-hidden="true"></i> Request changes</button>
                        <button class="master-btn master-btn-danger" type="submit" name="status" value="rejected"><i class="fa-solid fa-xmark" aria-hidden="true"></i> Reject</button>
                    </div>
                </form>
                @if($client->kyc_reviewed_at)
                    <p class="master-sub client-review-stamp">Last review {{ $client->kyc_reviewed_at->format('d M Y, h:i A') }}@if($client->reviewer) by {{ $client->reviewer->name ?: $client->reviewer->email }}@endif</p>
                @endif
            </section>

            @if($client->revision_note || $client->rejection_reason)
                <section class="master-card master-section client-record-section" aria-labelledby="client-review-notes-heading">
                    <h2 class="master-section-title" id="client-review-notes-heading">Previous review notes</h2>
                    @if($client->revision_note)
                        <div class="client-review-note"><strong>Revision requested</strong><p>{{ $client->revision_note }}</p></div>
                    @endif
                    @if($client->rejection_reason)
                        <div class="client-review-note"><strong>Rejection reason</strong><p>{{ $client->rejection_reason }}</p></div>
                    @endif
                </section>
            @endif
        </aside>
    </div>
</div>

@push('scripts')
    <script src="{{ $assetVer('assets/js/client-form.js') }}"></script>
@endpush
@endsection
