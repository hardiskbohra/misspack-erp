@extends('layouts.app')

@section('title', $client->company_name)
@section('page-title', 'Client profile')

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/clients.css') }}">
@endpush

@php
    $statusClass = str_replace('_', '-', $client->status);
    $initials = collect(explode(' ', trim($client->company_name)))
        ->filter()
        ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
        ->take(2)
        ->implode('');
    $formatAddress = fn (...$parts) => collect($parts)->filter(fn ($part) => filled($part))->implode(', ');
    $billingAddress = $formatAddress($client->billing_address, $client->billing_city, $client->billing_state, $client->billing_country, $client->billing_pincode);
    $shippingAddress = $formatAddress($client->shipping_address, $client->shipping_city, $client->shipping_state, $client->shipping_country, $client->shipping_pincode);
    $tabUrl = fn (string $key) => route('clients.show', ['client' => $client, 'tab' => $key]);
    $contactGroups = [
        ['label' => 'CEO / Director', 'name' => 'ceo_name', 'email' => 'ceo_email', 'phone' => 'ceo_contact'],
        ['label' => 'Accounts', 'name' => 'account_person_name', 'email' => 'account_person_email', 'phone' => 'account_person_contact'],
        ['label' => 'Marketing / Purchase', 'name' => 'marketing_person_name', 'email' => 'marketing_person_email', 'phone' => 'marketing_person_contact'],
        ['label' => 'Inward Dispatch', 'name' => 'dispatch_person_name', 'email' => 'dispatch_person_email', 'phone' => 'dispatch_person_contact'],
    ];
@endphp

<div class="client client-show" data-client-tab="{{ $tab }}">
    <header class="master-card master-header client-record-header">
        <div class="client-record-identity">
            <span class="client-record-mark" aria-hidden="true">{{ $initials }}</span>
            <div class="client-record-copy">
                <h1>{{ $client->company_name }}</h1>
                <div class="client-record-meta">
                    <span>{{ $client->client_number ?: 'Client record' }}</span>
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
            <a href="{{ $tabUrl('statement') }}" class="master-btn master-btn-soft"><i class="fa-solid fa-file-invoice" aria-hidden="true"></i> Statement</a>
            @if($portalInstalled)
                <a href="{{ $tabUrl('portal') }}" class="master-btn master-btn-soft"><i class="fa-solid fa-user-lock" aria-hidden="true"></i> Manage portal</a>
            @endif
        </nav>
    </header>

    <div class="master-tabs-card client-detail-tabs-card">
        <nav class="master-tabs" role="tablist" aria-label="Client profile sections">
            @foreach($tabs as $key => $label)
                <a class="master-tab {{ $tab === $key ? 'is-active' : '' }}"
                    id="client-tab-{{ $key }}"
                    role="tab"
                    aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                    aria-controls="client-panel-{{ $key }}"
                    href="{{ $tabUrl($key) }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        <div class="master-tabs-panels client-detail-panels">
            @if($tab === 'overview')
                <section class="master-tab-panel" id="client-panel-overview" role="tabpanel" aria-labelledby="client-tab-overview">
                    <div class="client-detail-grid">
                        <section class="master-card master-card--flat client-detail-card" aria-labelledby="client-identity-heading">
                            <h2 class="client-detail-title" id="client-identity-heading">Identity</h2>
                            <div class="master-facts">
                                <div class="master-info"><span>Client number</span><strong @class(['master-empty-value' => blank($client->client_number)])>{{ $client->client_number ?: 'Not on file' }}</strong></div>
                                <div class="master-info"><span>Brand name</span><strong @class(['master-empty-value' => blank($client->brand_name)])>{{ $client->brand_name ?: 'Not on file' }}</strong></div>
                                <div class="master-info"><span>Client type</span><strong>{{ $client->typeLabel() }}</strong></div>
                                <div class="master-info"><span>Industry</span><strong @class(['master-empty-value' => blank($client->industry)])>{{ $client->industry ?: 'Not on file' }}</strong></div>
                                <div class="master-info is-wide"><span>Website</span><strong @class(['master-empty-value' => blank($client->website)])>{{ $client->website ?: 'Not on file' }}</strong></div>
                            </div>
                        </section>

                        @php
                            $primaryContact = $contactGroups[0];
                            $primaryName = $client->{$primaryContact['name']};
                            $primaryEmail = $client->{$primaryContact['email']};
                            $primaryPhone = $client->{$primaryContact['phone']};
                        @endphp
                        <section class="master-card master-card--flat client-detail-card" aria-labelledby="client-primary-contact-heading">
                            <h2 class="client-detail-title" id="client-primary-contact-heading">Primary contact</h2>
                            <div class="master-facts">
                                <div class="master-info"><span>Contact</span><strong @class(['master-empty-value' => blank($primaryName)])>{{ $primaryName ?: 'Not on file' }}</strong></div>
                                <div class="master-info"><span>Email</span>
                                    @if($primaryEmail)<a class="client-detail-link" href="mailto:{{ $primaryEmail }}">{{ $primaryEmail }}</a>
                                    @else<strong class="master-empty-value">Not on file</strong>@endif
                                </div>
                                <div class="master-info"><span>Mobile</span>
                                    @if($primaryPhone)<a class="client-detail-link" href="tel:{{ preg_replace('/[^0-9+]/', '', $primaryPhone) }}">{{ $primaryPhone }}</a>
                                    @else<strong class="master-empty-value">Not on file</strong>@endif
                                </div>
                                <div class="master-info"><span>KYC status</span><strong>{{ $client->statusLabel() }}</strong></div>
                            </div>
                        </section>

                        <section class="master-card master-card--flat client-detail-card" aria-labelledby="client-registration-heading">
                            <h2 class="client-detail-title" id="client-registration-heading">Tax & registration</h2>
                            <div class="master-facts">
                                <div class="master-info"><span>GSTIN</span><strong @class(['master-empty-value' => blank($client->gstin)])>{{ $client->gstin ?: 'Not on file' }}</strong></div>
                                <div class="master-info"><span>PAN</span><strong @class(['master-empty-value' => blank($client->pan)])>{{ $client->pan ?: 'Not on file' }}</strong></div>
                                <div class="master-info"><span>TAN</span><strong @class(['master-empty-value' => blank($client->tan)])>{{ $client->tan ?: 'Not on file' }}</strong></div>
                                <div class="master-info"><span>CIN</span><strong @class(['master-empty-value' => blank($client->cin)])>{{ $client->cin ?: 'Not on file' }}</strong></div>
                                <div class="master-info is-wide"><span>MSME number</span><strong @class(['master-empty-value' => blank($client->msme_number)])>{{ $client->msme_number ?: 'Not on file' }}</strong></div>
                            </div>
                        </section>

                        <section class="master-card master-card--flat client-detail-card" aria-labelledby="client-record-activity-heading">
                            <h2 class="client-detail-title" id="client-record-activity-heading">Record activity</h2>
                            <div class="master-facts">
                                <div class="master-info"><span>KYC submitted</span><strong @class(['master-empty-value' => ! $client->kyc_submitted_at])>{{ $client->kyc_submitted_at?->format('d M Y, h:i A') ?: 'Not submitted' }}</strong></div>
                                <div class="master-info"><span>KYC reviewed</span><strong @class(['master-empty-value' => ! $client->kyc_reviewed_at])>{{ $client->kyc_reviewed_at?->format('d M Y, h:i A') ?: 'Not reviewed' }}</strong></div>
                                <div class="master-info"><span>Reviewed by</span><strong @class(['master-empty-value' => ! $client->reviewer])>{{ $client->reviewer?->name ?? $client->reviewer?->email ?? 'Not on file' }}</strong></div>
                                <div class="master-info"><span>Record created</span><strong>{{ $client->created_at?->format('d M Y') ?: '—' }}</strong></div>
                            </div>
                        </section>
                    </div>
                </section>
            @elseif($tab === 'contacts')
                <section class="master-tab-panel" id="client-panel-contacts" role="tabpanel" aria-labelledby="client-tab-contacts">
                    <div class="client-detail-grid">
                        @foreach($contactGroups as $index => $contact)
                            @php
                                $contactName = $client->{$contact['name']};
                                $contactEmail = $client->{$contact['email']};
                                $contactPhone = $client->{$contact['phone']};
                            @endphp
                            <section class="master-card master-card--flat client-detail-card" aria-labelledby="client-contact-{{ $index }}-heading">
                                <h2 class="client-detail-title" id="client-contact-{{ $index }}-heading">{{ $contact['label'] }}</h2>
                                <div class="master-facts">
                                    <div class="master-info"><span>Name</span><strong @class(['master-empty-value' => blank($contactName)])>{{ $contactName ?: 'Not on file' }}</strong></div>
                                    <div class="master-info"><span>Email</span>
                                        @if($contactEmail)<a class="client-detail-link" href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>
                                        @else<strong class="master-empty-value">Not on file</strong>@endif
                                    </div>
                                    <div class="master-info"><span>Mobile</span>
                                        @if($contactPhone)<a class="client-detail-link" href="tel:{{ preg_replace('/[^0-9+]/', '', $contactPhone) }}">{{ $contactPhone }}</a>
                                        @else<strong class="master-empty-value">Not on file</strong>@endif
                                    </div>
                                </div>
                            </section>
                        @endforeach
                    </div>
                </section>
            @elseif($tab === 'addresses')
                <section class="master-tab-panel" id="client-panel-addresses" role="tabpanel" aria-labelledby="client-tab-addresses">
                    <div class="client-detail-grid">
                        <section class="master-card master-card--flat client-detail-card" aria-labelledby="client-billing-heading">
                            <h2 class="client-detail-title" id="client-billing-heading">Billing address</h2>
                            <div class="master-facts">
                                <div class="master-info is-wide">
                                    <span>Address</span>
                                    @if($billingAddress)<address class="client-detail-address">{{ $billingAddress }}</address>
                                    @else<strong class="master-empty-value">Not on file</strong>@endif
                                </div>
                            </div>
                        </section>
                        <section class="master-card master-card--flat client-detail-card" aria-labelledby="client-shipping-heading">
                            <h2 class="client-detail-title" id="client-shipping-heading">Shipping address</h2>
                            <div class="master-facts">
                                <div class="master-info is-wide">
                                    <span>Address</span>
                                    @if($shippingAddress)<address class="client-detail-address">{{ $shippingAddress }}</address>
                                    @else<strong class="master-empty-value">Not on file</strong>@endif
                                    @if($client->shipping_same_as_billing)<small class="client-detail-note">Same as billing</small>@endif
                                </div>
                            </div>
                        </section>
                    </div>
                </section>
            @elseif($tab === 'commercial')
                <section class="master-tab-panel" id="client-panel-commercial" role="tabpanel" aria-labelledby="client-tab-commercial">
                    <div class="client-detail-grid">
                        <section class="master-card master-card--flat client-detail-card" aria-labelledby="client-bank-heading">
                            <h2 class="client-detail-title" id="client-bank-heading">Bank details</h2>
                            <div class="master-facts">
                                <div class="master-info"><span>Bank</span><strong @class(['master-empty-value' => blank($client->bank_name)])>{{ $client->bank_name ?: 'Not on file' }}</strong></div>
                                <div class="master-info"><span>Account name</span><strong @class(['master-empty-value' => blank($client->account_holder_name)])>{{ $client->account_holder_name ?: 'Not on file' }}</strong></div>
                                <div class="master-info"><span>Account number</span><strong @class(['master-empty-value' => blank($client->account_number)])>{{ $client->account_number ?: 'Not on file' }}</strong></div>
                                <div class="master-info"><span>IFSC</span><strong @class(['master-empty-value' => blank($client->ifsc_code)])>{{ $client->ifsc_code ?: 'Not on file' }}</strong></div>
                                <div class="master-info"><span>Branch</span><strong @class(['master-empty-value' => blank($client->bank_branch)])>{{ $client->bank_branch ?: 'Not on file' }}</strong></div>
                                <div class="master-info"><span>SWIFT</span><strong @class(['master-empty-value' => blank($client->swift_code)])>{{ $client->swift_code ?: 'Not on file' }}</strong></div>
                            </div>
                        </section>
                        <section class="master-card master-card--flat client-detail-card" aria-labelledby="client-credit-heading">
                            <h2 class="client-detail-title" id="client-credit-heading">Credit & payment</h2>
                            <div class="master-facts">
                                <div class="master-info"><span>Credit limit</span><strong @class(['master-empty-value' => $client->credit_limit === null])>{{ $client->credit_limit !== null ? \App\Helpers\CommonHelper::amount($client->credit_limit, $client->preferred_currency) : 'Not set' }}</strong></div>
                                <div class="master-info"><span>Credit days</span><strong @class(['master-empty-value' => $client->credit_days === null])>{{ $client->credit_days !== null ? $client->credit_days : 'Not set' }}</strong></div>
                                <div class="master-info"><span>Preferred currency</span><strong>{{ $client->preferred_currency ?: 'INR' }}</strong></div>
                                <div class="master-info is-wide"><span>Payment terms</span><strong @class(['master-empty-value' => blank($client->payment_terms)])>{{ $client->payment_terms ?: 'Not on file' }}</strong></div>
                            </div>
                        </section>
                        <section class="master-card master-card--flat client-detail-card client-detail-card--wide" aria-labelledby="client-notes-heading">
                            <h2 class="client-detail-title" id="client-notes-heading">Internal notes</h2>
                            <div class="master-facts">
                                <div class="master-info is-wide"><span>Notes</span><strong class="client-detail-notes {{ blank($client->notes) ? 'master-empty-value' : '' }}">{{ $client->notes ?: 'No notes on file' }}</strong></div>
                            </div>
                        </section>
                    </div>
                </section>
            @elseif($tab === 'kyc')
                <section class="master-tab-panel" id="client-panel-kyc" role="tabpanel" aria-labelledby="client-tab-kyc">
                    <div class="client-detail-grid">
                        <section class="master-card master-card--flat client-detail-card" aria-labelledby="client-kyc-status-heading">
                            <h2 class="client-detail-title" id="client-kyc-status-heading">KYC status</h2>
                            <div class="master-facts">
                                <div class="master-info"><span>Current status</span><strong>{{ $client->statusLabel() }}</strong></div>
                                <div class="master-info"><span>Submitted</span><strong @class(['master-empty-value' => ! $client->kyc_submitted_at])>{{ $client->kyc_submitted_at?->format('d M Y, h:i A') ?: 'Not submitted' }}</strong></div>
                                <div class="master-info"><span>Last reviewed</span><strong @class(['master-empty-value' => ! $client->kyc_reviewed_at])>{{ $client->kyc_reviewed_at?->format('d M Y, h:i A') ?: 'Not reviewed' }}</strong></div>
                                <div class="master-info"><span>Reviewer</span><strong @class(['master-empty-value' => ! $client->reviewer])>{{ $client->reviewer?->name ?? $client->reviewer?->email ?? 'Not on file' }}</strong></div>
                                <div class="master-info is-wide"><span>Last marked sent</span><strong @class(['master-empty-value' => ! $client->kyc_sent_at])>{{ $client->kyc_sent_at?->format('d M Y, h:i A') ?: 'Not marked as sent' }}</strong></div>
                            </div>
                        </section>

                        <section class="master-card master-card--flat client-detail-card" aria-labelledby="client-kyc-link-heading">
                            <h2 class="client-detail-title" id="client-kyc-link-heading">KYC form link</h2>
                            <p class="client-detail-help">Share this secure link so the client can submit or update their KYC details.</p>
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

                        <section class="master-card master-card--flat client-detail-card client-detail-card--wide" aria-labelledby="client-review-heading">
                            <h2 class="client-detail-title" id="client-review-heading">Review decision</h2>
                            <p class="client-detail-help">Choose a decision. Add a reason when requesting changes or rejecting a submission.</p>
                            <form method="POST" action="{{ route('clients.status.update', $client) }}" class="review-form" data-client-review-form>
                                @csrf
                                @method('PATCH')
                                <div class="client-review-fields">
                                    <div class="master-field">
                                        <label class="master-label" for="client_revision_note">Revision note</label>
                                        <textarea class="master-textarea" id="client_revision_note" name="revision_note" maxlength="5000" rows="3" placeholder="Tell the client which details need an update">{{ old('revision_note', $client->revision_note) }}</textarea>
                                    </div>
                                    <div class="master-field">
                                        <label class="master-label" for="client_rejection_reason">Rejection reason</label>
                                        <textarea class="master-textarea" id="client_rejection_reason" name="rejection_reason" maxlength="5000" rows="3" placeholder="Explain why this KYC cannot be approved">{{ old('rejection_reason', $client->rejection_reason) }}</textarea>
                                    </div>
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
                            <section class="master-card master-card--flat client-detail-card client-detail-card--wide" aria-labelledby="client-review-notes-heading">
                                <h2 class="client-detail-title" id="client-review-notes-heading">Previous review notes</h2>
                                <div class="client-review-note-grid">
                                    @if($client->revision_note)
                                        <div class="client-review-note"><strong>Revision requested</strong><p>{{ $client->revision_note }}</p></div>
                                    @endif
                                    @if($client->rejection_reason)
                                        <div class="client-review-note"><strong>Rejection reason</strong><p>{{ $client->rejection_reason }}</p></div>
                                    @endif
                                </div>
                            </section>
                        @endif
                    </div>
                </section>
            @elseif($tab === 'portal')
                <section class="master-tab-panel" id="client-panel-portal" role="tabpanel" aria-labelledby="client-tab-portal">
                    @include('clients.partials.portal-management')
                </section>
            @elseif($tab === 'notifications')
                <section class="master-tab-panel" id="client-panel-notifications" role="tabpanel" aria-labelledby="client-tab-notifications">
                    @include('clients.partials.notifications')
                </section>
            @elseif($tab === 'documents')
                <section class="master-tab-panel" id="client-panel-documents" role="tabpanel" aria-labelledby="client-tab-documents">
                    @include('clients.partials.documents')
                </section>
            @elseif($tab === 'invoices')
                <section class="master-tab-panel" id="client-panel-invoices" role="tabpanel" aria-labelledby="client-tab-invoices">
                    @include('clients.partials.invoices')
                </section>
            @elseif($tab === 'payments')
                <section class="master-tab-panel" id="client-panel-payments" role="tabpanel" aria-labelledby="client-tab-payments">
                    @include('clients.partials.payments')
                </section>
            @elseif($tab === 'statement')
                <section class="master-tab-panel" id="client-panel-statement" role="tabpanel" aria-labelledby="client-tab-statement">
                    @include('clients.partials.statement')
                </section>
            @elseif($tab === 'feedback')
                <section class="master-tab-panel" id="client-panel-feedback" role="tabpanel" aria-labelledby="client-tab-feedback">
                    @include('clients.partials.feedback')
                </section>
            @endif
        </div>
    </div>
</div>

@push('scripts')
    <script src="{{ $assetVer('assets/js/client-form.js') }}"></script>
@endpush
@endsection
