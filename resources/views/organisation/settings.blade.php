@extends('layouts.app')

@section('title', $organisation->legal_name ?: 'Organisation')
@section('page-title', 'Organisation')

@section('page-actions')
    @if ($tab === 'company')
        <button type="submit" form="organisationProfile" class="master-btn master-btn-primary">Save organisation</button>
    @elseif ($tab === 'addresses')
        <button type="button" class="master-btn master-btn-primary" data-org-add="orgAddress">
            <i class="fas fa-plus" aria-hidden="true"></i> Add address
        </button>
    @elseif ($tab === 'contacts')
        <button type="button" class="master-btn master-btn-primary" data-org-add="orgContact">
            <i class="fas fa-plus" aria-hidden="true"></i> Add contact
        </button>
    @elseif ($tab === 'socials')
        <button type="button" class="master-btn master-btn-primary" data-org-add="orgSocial">
            <i class="fas fa-plus" aria-hidden="true"></i> Add social link
        </button>
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
            <span class="icon" aria-hidden="true"><i class="fas fa-user"></i></span>
            <div>
                <p class="master-stat-title">Contacts</p>
                <p class="master-stat-value">{{ $counts['contacts'] }}</p>
                <p class="master-sub">{{ $counts['socials'] }} social {{ \Illuminate\Support\Str::plural('link', $counts['socials']) }}</p>
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
            <a class="master-tab {{ $tab === 'contacts' ? 'is-active' : '' }}" role="tab" aria-selected="{{ $tab === 'contacts' ? 'true' : 'false' }}" href="{{ $tabUrl('contacts') }}">
                Contacts
                @if ($counts['contacts'] > 0)<span class="master-tab-count">{{ $counts['contacts'] }}</span>@endif
            </a>
            <a class="master-tab {{ $tab === 'socials' ? 'is-active' : '' }}" role="tab" aria-selected="{{ $tab === 'socials' ? 'true' : 'false' }}" href="{{ $tabUrl('socials') }}">
                Social
                @if ($counts['socials'] > 0)<span class="master-tab-count">{{ $counts['socials'] }}</span>@endif
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
                <section class="master-tab-panel" aria-label="Addresses">
                    <p class="master-sub account-note" style="margin-top:0">
                        Billing prints on sales invoices. Shipping is dispatch. Branch is another office.
                    </p>
                    <div class="master-table-wrap">
                        <table class="master-table">
                            <thead>
                                <tr>
                                    <th>Kind</th>
                                    <th>Label</th>
                                    <th>Address</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($organisation->addresses as $address)
                                    @php
                                        $editPayload = [
                                            'kind' => $address->kind,
                                            'label' => $address->label,
                                            'line1' => $address->line1,
                                            'line2' => $address->line2,
                                            'city' => $address->city,
                                            'state' => $address->state,
                                            'pincode' => $address->pincode,
                                            'country' => $address->country,
                                            'is_default' => $address->is_default,
                                            'update_url' => route('organisation.addresses.update', $address),
                                        ];
                                    @endphp
                                    <tr>
                                        <td>
                                            <strong>{{ $address->kindLabel() }}</strong>
                                            @if ($address->is_default)
                                                <div><span class="master-status-chip success">Default</span></div>
                                            @endif
                                        </td>
                                        <td>{{ $address->label ?: '—' }}</td>
                                        <td>{{ $address->oneLine() ?: '—' }}</td>
                                        <td class="master-list-actions">
                                            <button type="button" class="master-btn master-btn-soft master-btn-sm" data-org-edit="orgAddress" data-org-payload='@json($editPayload)'>Edit</button>
                                            <form method="POST" action="{{ route('organisation.addresses.destroy', $address) }}" class="org-inline-form">
                                                @csrf
                                                @method('DELETE')
                                                <button class="master-btn master-btn-ghost master-btn-sm" type="submit" data-confirm="Remove this address?">Remove</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4">
                                            <div class="master-list-empty">
                                                <span class="master-list-empty-icon" aria-hidden="true"><i class="fas fa-map-marker-alt"></i></span>
                                                <p class="master-list-empty-title">No addresses yet</p>
                                                <p class="master-list-empty-text">Add the registered office first. Billing, shipping and branches all live in this list.</p>
                                                <div class="master-list-empty-actions">
                                                    <button type="button" class="master-btn master-btn-primary" data-org-add="orgAddress">Add address</button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @include('organisation.partials.address-modal')
                    @if ($errors->any() && $tab === 'addresses')
                        <div hidden data-open-org-modal="orgAddress"></div>
                    @endif
                </section>
            @elseif ($tab === 'contacts')
                <section class="master-tab-panel" aria-label="Contacts">
                    <p class="master-sub account-note" style="margin-top:0">
                        People the office names by desk — sales, accounts, operations, purchase.
                    </p>
                    <div class="master-table-wrap">
                        <table class="master-table">
                            <thead>
                                <tr>
                                    <th>Department</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Mobile</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($organisation->contacts as $contact)
                                    @php
                                        $payload = [
                                            'department' => $contact->department,
                                            'name' => $contact->name,
                                            'designation' => $contact->designation,
                                            'email' => $contact->email,
                                            'mobile' => $contact->mobile,
                                            'is_primary' => $contact->is_primary,
                                            'update_url' => route('organisation.contacts.update', $contact),
                                            'title' => $contact->name,
                                        ];
                                    @endphp
                                    <tr>
                                        <td>
                                            <strong>{{ $contact->departmentLabel() }}</strong>
                                            @if ($contact->is_primary)
                                                <div><span class="master-status-chip success">Primary</span></div>
                                            @endif
                                        </td>
                                        <td>
                                            {{ $contact->name }}
                                            @if ($contact->designation)
                                                <div class="master-sub">{{ $contact->designation }}</div>
                                            @endif
                                        </td>
                                        <td>{{ $contact->email ?: '—' }}</td>
                                        <td>{{ $contact->mobile ?: '—' }}</td>
                                        <td class="master-list-actions">
                                            <button type="button" class="master-btn master-btn-soft master-btn-sm" data-org-edit="orgContact" data-org-payload='@json($payload)'>Edit</button>
                                            <form method="POST" action="{{ route('organisation.contacts.destroy', $contact) }}" class="org-inline-form">
                                                @csrf
                                                @method('DELETE')
                                                <button class="master-btn master-btn-ghost master-btn-sm" type="submit" data-confirm="Remove this contact?">Remove</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5">
                                            <div class="master-list-empty">
                                                <span class="master-list-empty-icon" aria-hidden="true"><i class="fas fa-user"></i></span>
                                                <p class="master-list-empty-title">No department contacts yet</p>
                                                <p class="master-list-empty-text">Add who sales, accounts and operations should name on a letterhead.</p>
                                                <div class="master-list-empty-actions">
                                                    <button type="button" class="master-btn master-btn-primary" data-org-add="orgContact">Add contact</button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @include('organisation.partials.contact-modal')
                    @if ($errors->any() && $tab === 'contacts')
                        <div hidden data-open-org-modal="orgContact"></div>
                    @endif
                </section>
            @elseif ($tab === 'socials')
                <section class="master-tab-panel" aria-label="Social">
                    <p class="master-sub account-note" style="margin-top:0">
                        Instagram, Facebook, LinkedIn, Pinterest and any other profile the company keeps.
                    </p>
                    <div class="master-table-wrap">
                        <table class="master-table">
                            <thead>
                                <tr>
                                    <th>Network</th>
                                    <th>Handle</th>
                                    <th>URL</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($organisation->socials as $social)
                                    @php
                                        $payload = [
                                            'network' => $social->network,
                                            'handle' => $social->handle,
                                            'url' => $social->url,
                                            'update_url' => route('organisation.socials.update', $social),
                                            'title' => $social->networkLabel(),
                                        ];
                                    @endphp
                                    <tr>
                                        <td>
                                            <i class="{{ $social->networkIcon() }}" aria-hidden="true"></i>
                                            {{ $social->networkLabel() }}
                                        </td>
                                        <td>{{ $social->handle ?: '—' }}</td>
                                        <td><a href="{{ $social->url }}" target="_blank" rel="noopener">{{ $social->url }}</a></td>
                                        <td class="master-list-actions">
                                            <button type="button" class="master-btn master-btn-soft master-btn-sm" data-org-edit="orgSocial" data-org-payload='@json($payload)'>Edit</button>
                                            <form method="POST" action="{{ route('organisation.socials.destroy', $social) }}" class="org-inline-form">
                                                @csrf
                                                @method('DELETE')
                                                <button class="master-btn master-btn-ghost master-btn-sm" type="submit" data-confirm="Remove this social link?">Remove</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4">
                                            <div class="master-list-empty">
                                                <span class="master-list-empty-icon" aria-hidden="true"><i class="fa-solid fa-share-nodes"></i></span>
                                                <p class="master-list-empty-title">No social links yet</p>
                                                <p class="master-list-empty-text">Add Instagram first — stickers still read that profile.</p>
                                                <div class="master-list-empty-actions">
                                                    <button type="button" class="master-btn master-btn-primary" data-org-add="orgSocial">Add social link</button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @include('organisation.partials.social-modal')
                    @if ($errors->any() && $tab === 'socials')
                        <div hidden data-open-org-modal="orgSocial"></div>
                    @endif
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

@push('scripts')
    <script src="{{ $assetVer('assets/js/organisation.js') }}"></script>
@endpush
