@extends('layouts.app')

@section('title', 'Organisation')
@section('page-title', 'Organisation')

@section('page-actions')
    @if ($tab === 'company')
        <button type="submit" form="organisationProfile" class="master-btn master-btn-primary">Save organisation</button>
    @endif
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/users.css') }}">
    <link rel="stylesheet" href="{{ $assetVer('assets/css/organisation.css') }}">
@endpush

<div class="org-settings user-account master-list">
    <div class="master-card master-card--flat account-head">
        <span class="account-head-avatar" aria-hidden="true">
            <img src="{{ $organisation->logoUrl() }}" alt="">
        </span>
        <div class="account-head-text">
            <h2 class="account-head-name">{{ $organisation->legal_name }}</h2>
            <p class="account-head-meta">{{ $organisation->trade_name }}@if ($organisation->tagline) · {{ $organisation->tagline }} @endif</p>
            <p class="master-sub">
                One record for the company: invoices, purchase orders, payslips, statements and the mark all read from here.
            </p>
        </div>
    </div>

    <div class="master-list-bar org-tabs">
        <div class="master-list-chips">
            <a class="master-list-chip {{ $tab === 'company' ? 'is-active' : '' }}" href="{{ route('organisation.settings', ['tab' => 'company']) }}">Company</a>
            <a class="master-list-chip {{ $tab === 'addresses' ? 'is-active' : '' }}" href="{{ route('organisation.settings', ['tab' => 'addresses']) }}">Addresses &amp; branches</a>
            <a class="master-list-chip {{ $tab === 'banks' ? 'is-active' : '' }}" href="{{ route('organisation.settings', ['tab' => 'banks']) }}">Bank accounts</a>
        </div>
    </div>

    @if ($tab === 'company')
        <form id="organisationProfile" class="org-stack" method="POST" action="{{ route('organisation.update') }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="master-grid is-even">
                <div class="master-card master-card--flat master-section">
                    <h3 class="master-section-title">Identity</h3>
                    <p class="master-sub account-note">The name the office uses, and the legal name on a GST invoice.</p>
                    <div class="master-form-grid">
                        <div class="master-field">
                            <label class="master-label" for="trade_name">Trade name</label>
                            <input class="master-input" id="trade_name" name="trade_name" value="{{ old('trade_name', $organisation->trade_name) }}" required>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="legal_name">Legal name</label>
                            <input class="master-input" id="legal_name" name="legal_name" value="{{ old('legal_name', $organisation->legal_name) }}" required>
                        </div>
                        <div class="master-field master-field-full">
                            <label class="master-label" for="tagline">Tagline</label>
                            <input class="master-input" id="tagline" name="tagline" value="{{ old('tagline', $organisation->tagline) }}">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="email">Email</label>
                            <input class="master-input" id="email" type="email" name="email" value="{{ old('email', $organisation->email) }}">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="mobile">Mobile</label>
                            <input class="master-input" id="mobile" name="mobile" value="{{ old('mobile', $organisation->mobile) }}">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="website">Website</label>
                            <input class="master-input" id="website" name="website" value="{{ old('website', $organisation->website) }}">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="website_url">Website URL</label>
                            <input class="master-input" id="website_url" name="website_url" value="{{ old('website_url', $organisation->website_url) }}">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="instagram">Instagram URL</label>
                            <input class="master-input" id="instagram" name="instagram" value="{{ old('instagram', $organisation->instagram) }}">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="instagram_handle">Instagram handle</label>
                            <input class="master-input" id="instagram_handle" name="instagram_handle" value="{{ old('instagram_handle', $organisation->instagram_handle) }}">
                        </div>
                    </div>
                </div>

                <div class="master-card master-card--flat master-section">
                    <h3 class="master-section-title">Marks</h3>
                    <p class="master-sub account-note">Screen mark for the shell and dark pages. Print mark for invoices and stickers on white paper.</p>
                    <div class="org-logos">
                        <div>
                            <label class="master-label" for="logo">Screen logo</label>
                            <img class="org-logo-preview" src="{{ $organisation->logoUrl() }}" alt="">
                            <input class="master-input" id="logo" type="file" name="logo" accept="image/*">
                            @if ($organisation->logo_path)
                                <label class="master-check"><input type="checkbox" name="remove_logo" value="1"> Remove uploaded screen logo</label>
                            @endif
                        </div>
                        <div>
                            <label class="master-label" for="logo_print">Print logo</label>
                            <img class="org-logo-preview is-print" src="{{ $organisation->logoUrl('print') }}" alt="">
                            <input class="master-input" id="logo_print" type="file" name="logo_print" accept="image/*">
                            @if ($organisation->logo_print_path)
                                <label class="master-check"><input type="checkbox" name="remove_logo_print" value="1"> Remove uploaded print logo</label>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="master-card master-card--flat master-section">
                <h3 class="master-section-title">Commercial</h3>
                <p class="master-sub account-note">GST, PAN and the rest of the statutory line that prints on an invoice.</p>
                <div class="master-form-grid is-three">
                    <div class="master-field">
                        <label class="master-label" for="gstin">GSTIN</label>
                        <input class="master-input" id="gstin" name="gstin" value="{{ old('gstin', $organisation->gstin) }}">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="pan">PAN</label>
                        <input class="master-input" id="pan" name="pan" value="{{ old('pan', $organisation->pan) }}">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="cin">CIN</label>
                        <input class="master-input" id="cin" name="cin" value="{{ old('cin', $organisation->cin) }}">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="iec">IEC</label>
                        <input class="master-input" id="iec" name="iec" value="{{ old('iec', $organisation->iec) }}">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="msme">MSME / Udyam</label>
                        <input class="master-input" id="msme" name="msme" value="{{ old('msme', $organisation->msme) }}">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="lut">LUT</label>
                        <input class="master-input" id="lut" name="lut" value="{{ old('lut', $organisation->lut) }}">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="jurisdiction_city">Jurisdiction city</label>
                        <input class="master-input" id="jurisdiction_city" name="jurisdiction_city" value="{{ old('jurisdiction_city', $organisation->jurisdiction_city) }}">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="jurisdiction_state">Jurisdiction state</label>
                        <input class="master-input" id="jurisdiction_state" name="jurisdiction_state" value="{{ old('jurisdiction_state', $organisation->jurisdiction_state) }}">
                    </div>
                </div>
            </div>
        </form>
    @elseif ($tab === 'addresses')
        <div class="org-stack">
            <div class="master-card master-card--flat master-section">
                <h3 class="master-section-title">Add an address</h3>
                <p class="master-sub account-note">Billing prints on sales invoices. Shipping is dispatch. Branch is another office.</p>
                <form method="POST" action="{{ route('organisation.addresses.store') }}">
                    @csrf
                    <div class="master-form-grid is-three">
                        <div class="master-field">
                            <label class="master-label" for="addr_kind">Kind</label>
                            <select class="master-select" id="addr_kind" name="kind">
                                @foreach ($kinds as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="addr_label">Label</label>
                            <input class="master-input" id="addr_label" name="label" placeholder="Registered office">
                        </div>
                        <div class="master-field">
                            <label class="master-label">&nbsp;</label>
                            <label class="master-check"><input type="checkbox" name="is_default" value="1" checked> Default for this kind</label>
                        </div>
                        <div class="master-field master-field-full">
                            <label class="master-label" for="addr_line1">Line 1</label>
                            <input class="master-input" id="addr_line1" name="line1" required>
                        </div>
                        <div class="master-field master-field-full">
                            <label class="master-label" for="addr_line2">Line 2</label>
                            <input class="master-input" id="addr_line2" name="line2">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="addr_city">City</label>
                            <input class="master-input" id="addr_city" name="city">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="addr_state">State</label>
                            <input class="master-input" id="addr_state" name="state">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="addr_pincode">PIN</label>
                            <input class="master-input" id="addr_pincode" name="pincode">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="addr_country">Country</label>
                            <input class="master-input" id="addr_country" name="country" value="India">
                        </div>
                    </div>
                    <button class="master-btn master-btn-primary" type="submit">Add address</button>
                </form>
            </div>

            @foreach ($organisation->addresses as $address)
                <form class="master-card master-card--flat master-section" method="POST" action="{{ route('organisation.addresses.update', $address) }}">
                    @csrf
                    @method('PUT')
                    <div class="org-row-head">
                        <h3 class="master-section-title">{{ $address->label ?: $address->kindLabel() }}</h3>
                        <button class="master-btn master-btn-danger master-btn-sm" type="submit" form="org-addr-del-{{ $address->id }}" data-confirm="Remove this address?">Remove</button>
                    </div>
                    <div class="master-form-grid is-three">
                        <div class="master-field">
                            <label class="master-label">Kind</label>
                            <select class="master-select" name="kind">
                                @foreach ($kinds as $key => $label)
                                    <option value="{{ $key }}" @selected($address->kind === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label">Label</label>
                            <input class="master-input" name="label" value="{{ $address->label }}">
                        </div>
                        <div class="master-field">
                            <label class="master-label">&nbsp;</label>
                            <label class="master-check"><input type="checkbox" name="is_default" value="1" @checked($address->is_default)> Default for this kind</label>
                        </div>
                        <div class="master-field master-field-full">
                            <label class="master-label">Line 1</label>
                            <input class="master-input" name="line1" value="{{ $address->line1 }}" required>
                        </div>
                        <div class="master-field master-field-full">
                            <label class="master-label">Line 2</label>
                            <input class="master-input" name="line2" value="{{ $address->line2 }}">
                        </div>
                        <div class="master-field">
                            <label class="master-label">City</label>
                            <input class="master-input" name="city" value="{{ $address->city }}">
                        </div>
                        <div class="master-field">
                            <label class="master-label">State</label>
                            <input class="master-input" name="state" value="{{ $address->state }}">
                        </div>
                        <div class="master-field">
                            <label class="master-label">PIN</label>
                            <input class="master-input" name="pincode" value="{{ $address->pincode }}">
                        </div>
                        <div class="master-field">
                            <label class="master-label">Country</label>
                            <input class="master-input" name="country" value="{{ $address->country }}">
                        </div>
                    </div>
                    <button class="master-btn master-btn-primary" type="submit">Save address</button>
                </form>
                <form id="org-addr-del-{{ $address->id }}" method="POST" action="{{ route('organisation.addresses.destroy', $address) }}">
                    @csrf
                    @method('DELETE')
                </form>
            @endforeach
        </div>
    @else
        <div class="org-stack">
            <div class="master-card master-card--flat master-section">
                <h3 class="master-section-title">Add a bank account</h3>
                <p class="master-sub account-note">The default account prints on sales invoices and payslips.</p>
                <form method="POST" action="{{ route('organisation.banks.store') }}">
                    @csrf
                    <div class="master-form-grid is-three">
                        <div class="master-field">
                            <label class="master-label">Label</label>
                            <input class="master-input" name="label" placeholder="HDFC operating">
                        </div>
                        <div class="master-field">
                            <label class="master-label">Bank</label>
                            <input class="master-input" name="bank_name" required>
                        </div>
                        <div class="master-field">
                            <label class="master-label">Account holder</label>
                            <input class="master-input" name="account_holder" required>
                        </div>
                        <div class="master-field">
                            <label class="master-label">Account number</label>
                            <input class="master-input" name="account_number" required>
                        </div>
                        <div class="master-field">
                            <label class="master-label">IFSC</label>
                            <input class="master-input" name="ifsc">
                        </div>
                        <div class="master-field">
                            <label class="master-label">Branch</label>
                            <input class="master-input" name="branch">
                        </div>
                        <div class="master-field">
                            <label class="master-label">SWIFT</label>
                            <input class="master-input" name="swift">
                        </div>
                        <div class="master-field">
                            <label class="master-label">&nbsp;</label>
                            <label class="master-check"><input type="checkbox" name="is_default" value="1"> Default for invoices</label>
                        </div>
                    </div>
                    <button class="master-btn master-btn-primary" type="submit">Add bank</button>
                </form>
            </div>

            @foreach ($organisation->banks as $bank)
                <form class="master-card master-card--flat master-section" method="POST" action="{{ route('organisation.banks.update', $bank) }}">
                    @csrf
                    @method('PUT')
                    <div class="org-row-head">
                        <h3 class="master-section-title">{{ $bank->label ?: $bank->bank_name }}</h3>
                        <button class="master-btn master-btn-danger master-btn-sm" type="submit" form="org-bank-del-{{ $bank->id }}" data-confirm="Remove this bank account?">Remove</button>
                    </div>
                    <div class="master-form-grid is-three">
                        <div class="master-field">
                            <label class="master-label">Label</label>
                            <input class="master-input" name="label" value="{{ $bank->label }}">
                        </div>
                        <div class="master-field">
                            <label class="master-label">Bank</label>
                            <input class="master-input" name="bank_name" value="{{ $bank->bank_name }}" required>
                        </div>
                        <div class="master-field">
                            <label class="master-label">Account holder</label>
                            <input class="master-input" name="account_holder" value="{{ $bank->account_holder }}" required>
                        </div>
                        <div class="master-field">
                            <label class="master-label">Account number</label>
                            <input class="master-input" name="account_number" value="{{ $bank->account_number }}" required>
                        </div>
                        <div class="master-field">
                            <label class="master-label">IFSC</label>
                            <input class="master-input" name="ifsc" value="{{ $bank->ifsc }}">
                        </div>
                        <div class="master-field">
                            <label class="master-label">Branch</label>
                            <input class="master-input" name="branch" value="{{ $bank->branch }}">
                        </div>
                        <div class="master-field">
                            <label class="master-label">SWIFT</label>
                            <input class="master-input" name="swift" value="{{ $bank->swift }}">
                        </div>
                        <div class="master-field">
                            <label class="master-label">&nbsp;</label>
                            <label class="master-check"><input type="checkbox" name="is_default" value="1" @checked($bank->is_default)> Default for invoices</label>
                        </div>
                    </div>
                    <button class="master-btn master-btn-primary" type="submit">Save bank</button>
                </form>
                <form id="org-bank-del-{{ $bank->id }}" method="POST" action="{{ route('organisation.banks.destroy', $bank) }}">
                    @csrf
                    @method('DELETE')
                </form>
            @endforeach
        </div>
    @endif
</div>
@endsection
