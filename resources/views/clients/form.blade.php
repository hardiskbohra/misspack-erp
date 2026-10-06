@extends('layouts.app')

@section('title', $client->exists ? 'Edit client' : 'Add client')
@section('page-title', $client->exists ? 'Edit client' : 'Add client')

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/clients.css') }}">
@endpush

@php($isEdit = $client->exists)

<div class="client-form">
    <header class="master-card master-header">
        <div class="client-form-heading">
            <h1>{{ $isEdit ? 'Edit client' : 'Add a client' }}</h1>
            <p>{{ $isEdit ? 'Keep this client’s company, contacts and commercial details up to date.' : 'Create a client profile. You can add more details now or complete them later.' }}</p>
            <nav class="master-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('clients.index') }}">Clients</a><span aria-hidden="true">/</span>
                <a href="{{ route('clients.index') }}">Clients</a><span aria-hidden="true">/</span>
                <span class="active" aria-current="page">{{ $isEdit ? 'Edit' : 'New' }}</span>
            </nav>
        </div>
        <a href="{{ $isEdit ? route('clients.show', $client) : route('clients.index') }}" class="master-btn master-btn-light">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> {{ $isEdit ? 'Back to client' : 'Back to clients' }}
        </a>
    </header>

    @if($errors->any())
        <div class="master-error-summary" role="alert" aria-labelledby="clientErrorTitle">
            <strong id="clientErrorTitle">Please review {{ $errors->count() }} {{ \Illuminate\Support\Str::plural('field', $errors->count()) }}:</strong>
            <ul>
                @foreach($errors->messages() as $field => $messages)
                    <li><a href="#client_{{ $field }}">{{ $messages[0] }}</a></li>
                @endforeach
            </ul>
        </div>
    @endif

    <nav class="client-form-nav" aria-label="Jump to client details">
        <a href="#client-company"><i class="fa-regular fa-building" aria-hidden="true"></i> Company</a>
        <a href="#client-contacts"><i class="fa-regular fa-address-card" aria-hidden="true"></i> Contacts</a>
        <a href="#client-addresses"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> Addresses</a>
        <a href="#client-tax-bank"><i class="fa-solid fa-file-invoice" aria-hidden="true"></i> Tax & bank</a>
        <a href="#client-commercial"><i class="fa-solid fa-coins" aria-hidden="true"></i> Commercial</a>
    </nav>

    <form method="POST" action="{{ $isEdit ? route('clients.update', $client) : route('clients.store') }}"
        class="master-card master-form-card" id="clientForm" data-client-form>
        @csrf
        @if($isEdit) @method('PUT') @endif

        <section class="client-form-section" id="client-company" aria-labelledby="client-company-title">
            <h2 class="master-section-title" id="client-company-title">Company information</h2>
            <p class="client-form-section-note">Identify the client and set their KYC workflow status.</p>
            <div class="master-detail-grid">
                <div class="master-field">
                    <label class="master-label" for="client_client_number">Client number</label>
                    <input class="master-input @error('client_number') is-invalid @enderror" id="client_client_number" name="client_number"
                        value="{{ old('client_number', $client->client_number) }}" maxlength="255" placeholder="Auto-generated if left blank" autocomplete="off">
                    <div class="master-help">New client numbers are assigned automatically. Change only if you use an external reference.</div>
                </div>
                <div class="master-field">
                    <label class="master-label" for="client_company_name">Company name <span class="master-required" aria-hidden="true">*</span></label>
                    <input class="master-input @error('company_name') is-invalid @enderror" id="client_company_name" name="company_name"
                        value="{{ old('company_name', $client->company_name) }}" maxlength="255" required autocomplete="organization">
                </div>
                <div class="master-field">
                    <label class="master-label" for="client_brand_name">Brand name</label>
                    <input class="master-input @error('brand_name') is-invalid @enderror" id="client_brand_name" name="brand_name"
                        value="{{ old('brand_name', $client->brand_name) }}" maxlength="255">
                </div>
                <div class="master-field">
                    <label class="master-label" for="client_client_type">Client type <span class="master-required" aria-hidden="true">*</span></label>
                    <select class="master-select @error('client_type') is-invalid @enderror" id="client_client_type" name="client_type" required>
                        @foreach($typeOptions as $key => $label)
                            <option value="{{ $key }}" @selected(old('client_type', $client->client_type) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="master-field">
                    <span class="master-label">KYC status</span>
                    <input type="hidden" name="status" value="{{ $client->status }}">
                    <div class="client-form-status">
                        <span class="master-badge status-{{ str_replace('_', '-', $client->status) }}">{{ $client->statusLabel() }}</span>
                        <span>{{ $isEdit ? 'Manage KYC decisions from the client profile.' : 'New clients start as Draft. KYC status changes when the form is submitted or reviewed.' }}</span>
                    </div>
                </div>
                <div class="master-field">
                    <label class="master-label" for="client_industry">Industry</label>
                    <input class="master-input @error('industry') is-invalid @enderror" id="client_industry" name="industry"
                        value="{{ old('industry', $client->industry) }}" maxlength="255" autocomplete="organization-title">
                </div>
                <div class="master-field full">
                    <label class="master-label" for="client_website">Website</label>
                    <input class="master-input @error('website') is-invalid @enderror" id="client_website" name="website" type="text" inputmode="url"
                        value="{{ old('website', $client->website) }}" maxlength="255" placeholder="https://example.com" autocomplete="url">
                </div>
            </div>
        </section>

        <section class="client-form-section" id="client-contacts" aria-labelledby="client-contacts-title">
            <h2 class="master-section-title" id="client-contacts-title">Contact people</h2>
            <p class="client-form-section-note">Add the people your team should reach for leadership, accounts, purchasing and dispatch.</p>

            @foreach($contactGroups as $groupIndex => $group)
                <div class="client-subsection{{ $groupIndex === 0 ? '' : ' client-subsection--divider' }}">
                    <h3 class="client-subsection-title">{{ $group['title'] }}</h3>
                    <div class="master-detail-grid">
                        <div class="master-field">
                            <label class="master-label" for="client_{{ $group['name'] }}">Name</label>
                            <input class="master-input @error($group['name']) is-invalid @enderror" id="client_{{ $group['name'] }}" name="{{ $group['name'] }}"
                                value="{{ old($group['name'], data_get($client, $group['name'])) }}" maxlength="255" autocomplete="name">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="client_{{ $group['email'] }}">Email</label>
                            <input class="master-input @error($group['email']) is-invalid @enderror" id="client_{{ $group['email'] }}" name="{{ $group['email'] }}" type="email"
                                value="{{ old($group['email'], data_get($client, $group['email'])) }}" maxlength="255" autocomplete="email">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="client_{{ $group['phone'] }}">Phone</label>
                            <input class="master-input @error($group['phone']) is-invalid @enderror" id="client_{{ $group['phone'] }}" name="{{ $group['phone'] }}" type="tel"
                                value="{{ old($group['phone'], data_get($client, $group['phone'])) }}" maxlength="40" autocomplete="tel">
                        </div>
                    </div>
                </div>
            @endforeach
        </section>

        <section class="client-form-section" id="client-addresses" aria-labelledby="client-addresses-title">
            <h2 class="master-section-title" id="client-addresses-title">Billing & shipping addresses</h2>
            <p class="client-form-section-note">The billing address is used for client records and invoices. Copy it to shipping when both locations match.</p>

            <div class="client-subsection">
                <h3 class="client-subsection-title">Billing address</h3>
                <div class="master-detail-grid">
                    <div class="master-field full">
                        <label class="master-label" for="client_billing_address">Address</label>
                        <input class="master-input @error('billing_address') is-invalid @enderror" id="client_billing_address" name="billing_address"
                            value="{{ old('billing_address', $client->billing_address) }}" autocomplete="billing street-address">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="client_billing_city">City</label>
                        <input class="master-input @error('billing_city') is-invalid @enderror" id="client_billing_city" name="billing_city"
                            value="{{ old('billing_city', $client->billing_city) }}" maxlength="255" autocomplete="billing address-level2">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="client_billing_state">State / Province</label>
                        <input class="master-input @error('billing_state') is-invalid @enderror" id="client_billing_state" name="billing_state"
                            value="{{ old('billing_state', $client->billing_state) }}" maxlength="255" autocomplete="billing address-level1">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="client_billing_country">Country</label>
                        <input class="master-input @error('billing_country') is-invalid @enderror" id="client_billing_country" name="billing_country"
                            value="{{ old('billing_country', $client->billing_country) }}" maxlength="255" autocomplete="billing country-name">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="client_billing_pincode">Postal code</label>
                        <input class="master-input @error('billing_pincode') is-invalid @enderror" id="client_billing_pincode" name="billing_pincode"
                            value="{{ old('billing_pincode', $client->billing_pincode) }}" maxlength="30" autocomplete="billing postal-code">
                    </div>
                </div>
            </div>

            <label class="client-checkbox-row" for="client_shipping_same_as_billing">
                <input type="checkbox" id="client_shipping_same_as_billing" name="shipping_same_as_billing" value="1"
                    @checked(old('shipping_same_as_billing', $client->shipping_same_as_billing)) aria-controls="client-shipping-fields">
                <span>Shipping address is the same as billing</span>
            </label>

            <div class="client-subsection" id="client-shipping-fields">
                <h3 class="client-subsection-title">Shipping address</h3>
                <div class="master-detail-grid">
                    <div class="master-field full">
                        <label class="master-label" for="client_shipping_address">Address</label>
                        <input class="master-input @error('shipping_address') is-invalid @enderror" id="client_shipping_address" name="shipping_address"
                            value="{{ old('shipping_address', $client->shipping_address) }}" autocomplete="shipping street-address">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="client_shipping_city">City</label>
                        <input class="master-input @error('shipping_city') is-invalid @enderror" id="client_shipping_city" name="shipping_city"
                            value="{{ old('shipping_city', $client->shipping_city) }}" maxlength="255" autocomplete="shipping address-level2">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="client_shipping_state">State / Province</label>
                        <input class="master-input @error('shipping_state') is-invalid @enderror" id="client_shipping_state" name="shipping_state"
                            value="{{ old('shipping_state', $client->shipping_state) }}" maxlength="255" autocomplete="shipping address-level1">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="client_shipping_country">Country</label>
                        <input class="master-input @error('shipping_country') is-invalid @enderror" id="client_shipping_country" name="shipping_country"
                            value="{{ old('shipping_country', $client->shipping_country) }}" maxlength="255" autocomplete="shipping country-name">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="client_shipping_pincode">Postal code</label>
                        <input class="master-input @error('shipping_pincode') is-invalid @enderror" id="client_shipping_pincode" name="shipping_pincode"
                            value="{{ old('shipping_pincode', $client->shipping_pincode) }}" maxlength="30" autocomplete="shipping postal-code">
                    </div>
                </div>
            </div>
        </section>

        <section class="client-form-section" id="client-tax-bank" aria-labelledby="client-tax-bank-title">
            <h2 class="master-section-title" id="client-tax-bank-title">Tax & bank details</h2>
            <p class="client-form-section-note">Keep statutory and payment details on the record for accurate invoicing and remittance.</p>

            <div class="client-subsection">
                <h3 class="client-subsection-title">Tax registrations</h3>
                <div class="master-detail-grid">
                    <div class="master-field"><label class="master-label" for="client_gstin">GSTIN</label><input class="master-input @error('gstin') is-invalid @enderror" id="client_gstin" name="gstin" value="{{ old('gstin', $client->gstin) }}" maxlength="30" autocomplete="off"></div>
                    <div class="master-field"><label class="master-label" for="client_pan">PAN</label><input class="master-input @error('pan') is-invalid @enderror" id="client_pan" name="pan" value="{{ old('pan', $client->pan) }}" maxlength="20" autocomplete="off"></div>
                    <div class="master-field"><label class="master-label" for="client_tan">TAN</label><input class="master-input @error('tan') is-invalid @enderror" id="client_tan" name="tan" value="{{ old('tan', $client->tan) }}" maxlength="20" autocomplete="off"></div>
                    <div class="master-field"><label class="master-label" for="client_cin">CIN</label><input class="master-input @error('cin') is-invalid @enderror" id="client_cin" name="cin" value="{{ old('cin', $client->cin) }}" maxlength="255" autocomplete="off"></div>
                    <div class="master-field"><label class="master-label" for="client_msme_number">MSME number</label><input class="master-input @error('msme_number') is-invalid @enderror" id="client_msme_number" name="msme_number" value="{{ old('msme_number', $client->msme_number) }}" maxlength="255" autocomplete="off"></div>
                </div>
            </div>

            <div class="client-subsection">
                <h3 class="client-subsection-title">Bank details</h3>
                <div class="master-detail-grid">
                    <div class="master-field"><label class="master-label" for="client_bank_name">Bank name</label><input class="master-input @error('bank_name') is-invalid @enderror" id="client_bank_name" name="bank_name" value="{{ old('bank_name', $client->bank_name) }}" maxlength="255" autocomplete="off"></div>
                    <div class="master-field"><label class="master-label" for="client_account_holder_name">Account holder</label><input class="master-input @error('account_holder_name') is-invalid @enderror" id="client_account_holder_name" name="account_holder_name" value="{{ old('account_holder_name', $client->account_holder_name) }}" maxlength="255" autocomplete="off"></div>
                    <div class="master-field"><label class="master-label" for="client_account_number">Account number</label><input class="master-input @error('account_number') is-invalid @enderror" id="client_account_number" name="account_number" value="{{ old('account_number', $client->account_number) }}" maxlength="255" autocomplete="off"></div>
                    <div class="master-field"><label class="master-label" for="client_ifsc_code">IFSC code</label><input class="master-input @error('ifsc_code') is-invalid @enderror" id="client_ifsc_code" name="ifsc_code" value="{{ old('ifsc_code', $client->ifsc_code) }}" maxlength="30" autocomplete="off"></div>
                    <div class="master-field"><label class="master-label" for="client_bank_branch">Bank branch</label><input class="master-input @error('bank_branch') is-invalid @enderror" id="client_bank_branch" name="bank_branch" value="{{ old('bank_branch', $client->bank_branch) }}" maxlength="255" autocomplete="off"></div>
                    <div class="master-field"><label class="master-label" for="client_swift_code">SWIFT code</label><input class="master-input @error('swift_code') is-invalid @enderror" id="client_swift_code" name="swift_code" value="{{ old('swift_code', $client->swift_code) }}" maxlength="255" autocomplete="off"></div>
                </div>
            </div>
        </section>

        <section class="client-form-section" id="client-commercial" aria-labelledby="client-commercial-title">
            <h2 class="master-section-title" id="client-commercial-title">Commercial details</h2>
            <p class="client-form-section-note">Set the agreed terms for this client. These are reference details and do not replace invoice-level terms.</p>
            <div class="master-detail-grid">
                <div class="master-field">
                    <label class="master-label" for="client_credit_limit">Credit limit</label>
                    <input class="master-input @error('credit_limit') is-invalid @enderror" id="client_credit_limit" type="number" min="0" step="0.01" name="credit_limit"
                        value="{{ old('credit_limit', $client->credit_limit) }}" inputmode="decimal">
                </div>
                <div class="master-field">
                    <label class="master-label" for="client_credit_days">Credit days</label>
                    <input class="master-input @error('credit_days') is-invalid @enderror" id="client_credit_days" type="number" min="0" step="1" name="credit_days"
                        value="{{ old('credit_days', $client->credit_days) }}" inputmode="numeric">
                </div>
                <div class="master-field">
                    <label class="master-label" for="client_preferred_currency">Preferred currency</label>
                    <select class="master-select @error('preferred_currency') is-invalid @enderror" id="client_preferred_currency" name="preferred_currency">
                        @foreach($currencyOptions as $key => $label)
                            <option value="{{ $key }}" @selected(old('preferred_currency', $client->preferred_currency) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="master-field full">
                    <label class="master-label" for="client_payment_terms">Payment terms</label>
                    <input class="master-input @error('payment_terms') is-invalid @enderror" id="client_payment_terms" name="payment_terms" maxlength="255"
                        value="{{ old('payment_terms', $client->payment_terms) }}" placeholder="e.g. 30 days from invoice date">
                </div>
                <div class="master-field full">
                    <label class="master-label" for="client_notes">Internal notes</label>
                    <textarea class="master-textarea @error('notes') is-invalid @enderror" id="client_notes" name="notes" rows="4" placeholder="Add context for your team">{{ old('notes', $client->notes) }}</textarea>
                </div>
            </div>
        </section>

        <div class="master-actions">
            <a href="{{ $isEdit ? route('clients.show', $client) : route('clients.index') }}" class="master-btn master-btn-light">Cancel</a>
            <button type="submit" class="master-btn master-btn-primary">
                <i class="fa-solid fa-check" aria-hidden="true"></i> {{ $isEdit ? 'Save changes' : 'Create client' }}
            </button>
        </div>
    </form>
</div>

@push('scripts')
    <script src="{{ $assetVer('assets/js/client-form.js') }}"></script>
@endpush
@endsection
