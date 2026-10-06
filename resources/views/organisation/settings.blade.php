@extends('layouts.app')

@section('title', $organisation->legal_name ?: 'Organisation')
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

@php
    $tabUrl = fn (string $key) => route('organisation.settings', ['tab' => $key]);
    $defaultBank = $organisation->defaultBank();
@endphp

<div class="org-record user-account master-list">

    <div class="master-card master-card--flat account-head">
        <span class="account-head-avatar" aria-hidden="true">
            <img src="{{ $organisation->logoUrl() }}" alt="">
        </span>
        <div class="account-head-text">
            <h2 class="account-head-name">{{ $organisation->legal_name }}</h2>
            <p class="account-head-meta">
                {{ $organisation->trade_name }}
                @if ($organisation->tagline) · {{ $organisation->tagline }} @endif
                @if ($organisation->gstin) · GSTIN {{ $organisation->gstin }} @endif
            </p>
            <p class="master-sub">
                One record for the company. Invoices, purchase orders, payslips, statements and the mark all read from here.
            </p>
        </div>
    </div>

    <div class="master-stats desktop-only">
        <div class="master-stat master-stat--flat blue">
            <span class="icon" aria-hidden="true"><i class="fas fa-id-card"></i></span>
            <div>
                <p class="master-stat-title">GSTIN</p>
                <p class="master-stat-value">{{ $organisation->gstin ?: '—' }}</p>
                <p class="master-sub">{{ $organisation->pan ? 'PAN '.$organisation->pan : 'PAN not on file' }}</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat purple">
            <span class="icon" aria-hidden="true"><i class="fas fa-map-marker-alt"></i></span>
            <div>
                <p class="master-stat-title">Addresses</p>
                <p class="master-stat-value">{{ $counts['addresses'] }}</p>
                <p class="master-sub">billing, shipping and branches</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat teal">
            <span class="icon" aria-hidden="true"><i class="fas fa-university"></i></span>
            <div>
                <p class="master-stat-title">Bank accounts</p>
                <p class="master-stat-value">{{ $counts['banks'] }}</p>
                <p class="master-sub">{{ $defaultBank?->bank_name ?: 'No default account' }}</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat green">
            <span class="icon" aria-hidden="true"><i class="fas fa-envelope"></i></span>
            <div>
                <p class="master-stat-title">Contact</p>
                <p class="master-stat-value">{{ $organisation->mobile ?: '—' }}</p>
                <p class="master-sub">{{ $organisation->email ?: 'No email on file' }}</p>
            </div>
        </div>
    </div>

    <div class="master-tabs-card">
        <nav class="master-tabs" role="tablist" aria-label="Organisation sections">
            <a class="master-tab {{ $tab === 'company' ? 'is-active' : '' }}" role="tab" aria-selected="{{ $tab === 'company' ? 'true' : 'false' }}" href="{{ $tabUrl('company') }}">Company</a>
            <a class="master-tab {{ $tab === 'addresses' ? 'is-active' : '' }}" role="tab" aria-selected="{{ $tab === 'addresses' ? 'true' : 'false' }}" href="{{ $tabUrl('addresses') }}">
                Addresses &amp; branches
                @if ($counts['addresses'] > 0)<span class="master-tab-count">{{ $counts['addresses'] }}</span>@endif
            </a>
            <a class="master-tab {{ $tab === 'banks' ? 'is-active' : '' }}" role="tab" aria-selected="{{ $tab === 'banks' ? 'true' : 'false' }}" href="{{ $tabUrl('banks') }}">
                Bank accounts
                @if ($counts['banks'] > 0)<span class="master-tab-count">{{ $counts['banks'] }}</span>@endif
            </a>
        </nav>

        <div class="master-tabs-panels">
            @if ($tab === 'company')
                <section class="master-tab-panel" aria-label="Company">
                    <form id="organisationProfile" method="POST" action="{{ route('organisation.update') }}" enctype="multipart/form-data">
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
                                    <div class="master-field full">
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
                                    <div class="master-field">
                                        <label class="master-label" for="logo">Screen logo</label>
                                        <img class="org-logo-preview" src="{{ $organisation->logoUrl() }}" alt="">
                                        <input class="master-input" id="logo" type="file" name="logo" accept="image/*">
                                        @if ($organisation->logo_path)
                                            <label class="master-check"><input type="checkbox" name="remove_logo" value="1"> Remove uploaded screen logo</label>
                                        @endif
                                    </div>
                                    <div class="master-field">
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
                </section>
            @elseif ($tab === 'addresses')
                <section class="master-tab-panel org-stack" aria-label="Addresses">
                    <div class="master-card master-card--flat master-section">
                        <h3 class="master-section-title">Add an address</h3>
                        <p class="master-sub account-note">Billing prints on sales invoices. Shipping is dispatch. Branch is another office.</p>
                        <form method="POST" action="{{ route('organisation.addresses.store') }}">
                            @csrf
                            @include('organisation.partials.address-fields', ['address' => null, 'kinds' => $kinds])
                            <button class="master-btn master-btn-primary" type="submit">Add address</button>
                        </form>
                    </div>

                    @forelse ($organisation->addresses as $address)
                        <form class="master-card master-card--flat master-section" method="POST" action="{{ route('organisation.addresses.update', $address) }}">
                            @csrf
                            @method('PUT')
                            <div class="master-section-head">
                                <div>
                                    <h3 class="master-section-title">{{ $address->label ?: $address->kindLabel() }}</h3>
                                    @if ($address->is_default)
                                        <span class="master-status-chip success">Default {{ strtolower($address->kindLabel()) }}</span>
                                    @endif
                                </div>
                                <button class="master-btn master-btn-ghost master-btn-sm" type="submit" form="org-addr-del-{{ $address->id }}" data-confirm="Remove this address?">Remove</button>
                            </div>
                            @include('organisation.partials.address-fields', ['address' => $address, 'kinds' => $kinds])
                            <button class="master-btn master-btn-primary" type="submit">Save address</button>
                        </form>
                        <form id="org-addr-del-{{ $address->id }}" method="POST" action="{{ route('organisation.addresses.destroy', $address) }}">
                            @csrf
                            @method('DELETE')
                        </form>
                    @empty
                        <p class="master-sub">No addresses yet. Add the registered office first.</p>
                    @endforelse
                </section>
            @else
                <section class="master-tab-panel org-stack" aria-label="Bank accounts">
                    <div class="master-card master-card--flat master-section">
                        <h3 class="master-section-title">Add a bank account</h3>
                        <p class="master-sub account-note">The default account prints on sales invoices and payslips.</p>
                        <form method="POST" action="{{ route('organisation.banks.store') }}">
                            @csrf
                            @include('organisation.partials.bank-fields', ['bank' => null])
                            <button class="master-btn master-btn-primary" type="submit">Add bank</button>
                        </form>
                    </div>

                    @forelse ($organisation->banks as $bank)
                        <form class="master-card master-card--flat master-section" method="POST" action="{{ route('organisation.banks.update', $bank) }}">
                            @csrf
                            @method('PUT')
                            <div class="master-section-head">
                                <div>
                                    <h3 class="master-section-title">{{ $bank->label ?: $bank->bank_name }}</h3>
                                    @if ($bank->is_default)
                                        <span class="master-status-chip success">Prints on invoices</span>
                                    @endif
                                </div>
                                <button class="master-btn master-btn-ghost master-btn-sm" type="submit" form="org-bank-del-{{ $bank->id }}" data-confirm="Remove this bank account?">Remove</button>
                            </div>
                            @include('organisation.partials.bank-fields', ['bank' => $bank])
                            <button class="master-btn master-btn-primary" type="submit">Save bank</button>
                        </form>
                        <form id="org-bank-del-{{ $bank->id }}" method="POST" action="{{ route('organisation.banks.destroy', $bank) }}">
                            @csrf
                            @method('DELETE')
                        </form>
                    @empty
                        <p class="master-sub">No bank accounts yet. Add the operating account first.</p>
                    @endforelse
                </section>
            @endif
        </div>
    </div>
</div>
@endsection
