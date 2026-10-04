@extends('layouts.app')

@section('title', 'Vendors')
@section('page-title', 'Vendors')

@section('page-actions')
    <button type="button" class="master-btn master-btn-primary" id="openQuickVendorModal">
        <i class="fa-solid fa-bolt" aria-hidden="true"></i> Quick add
    </button>
    <a href="{{ route('vendors.create') }}" class="master-btn master-btn-soft">
        <i class="fa-solid fa-plus" aria-hidden="true"></i> Detailed form
    </a>
@endsection

@section('content')
@php
    $filtersActive = filled($search) || $status !== 'all' || $type !== 'all' || $country !== 'all';
    $activeFilterCount = (filled($search) ? 1 : 0) + ($status !== 'all' ? 1 : 0) + ($type !== 'all' ? 1 : 0) + ($country !== 'all' ? 1 : 0);
    $statusUrl = function (string $value) {
        $query = collect(request()->except(['status', 'page']))
            ->reject(fn ($item) => $item === null || $item === '' || $item === 'all')
            ->all();
        if ($value !== 'all') {
            $query['status'] = $value;
        }

        return route('vendors.index', $query);
    };
    $removeFilterUrl = function (string $key) {
        $query = collect(request()->except([$key, 'page']))
            ->reject(fn ($item) => $item === null || $item === '' || $item === 'all')
            ->all();

        return route('vendors.index', $query);
    };
    $vendorCount = $vendors->total();
    $firstVendor = $vendors->firstItem() ?? 0;
    $lastVendor = $vendors->lastItem() ?? 0;
@endphp

<div class="vendor vendor-index master-list">
    <div class="master-stats desktop-only" aria-label="Vendor overview">
        <div class="master-stat master-stat--flat blue">
            <span class="icon"><i class="fa-solid fa-industry" aria-hidden="true"></i></span>
            <div><p class="master-stat-title">Total vendors</p><p class="master-stat-value">{{ number_format($stats['total']) }}</p></div>
        </div>
        <div class="master-stat master-stat--flat green">
            <span class="icon"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span>
            <div><p class="master-stat-title">Active</p><p class="master-stat-value">{{ number_format($stats['active']) }}</p></div>
        </div>
        <div class="master-stat master-stat--flat orange">
            <span class="icon"><i class="fa-regular fa-circle-pause" aria-hidden="true"></i></span>
            <div><p class="master-stat-title">On hold</p><p class="master-stat-value">{{ number_format($stats['on_hold']) }}</p></div>
        </div>
        <div class="master-stat master-stat--flat red">
            <span class="icon"><i class="fa-solid fa-ban" aria-hidden="true"></i></span>
            <div><p class="master-stat-title">Blacklisted</p><p class="master-stat-value">{{ number_format($stats['blacklisted']) }}</p></div>
        </div>
        <div class="master-stat master-stat--flat purple">
            <span class="icon"><i class="fa-solid fa-earth-asia" aria-hidden="true"></i></span>
            <div><p class="master-stat-title">International</p><p class="master-stat-value">{{ number_format($stats['international']) }}</p></div>
        </div>
    </div>

    <section class="master-card master-card--flat" aria-label="Search and filter vendors">
        <nav class="master-list-chips" aria-label="Quick vendor status filters">
            <a class="master-list-chip {{ $status === 'all' ? 'is-active' : '' }}" href="{{ $statusUrl('all') }}">
                All vendors <span class="master-list-chip-count">{{ $stats['total'] }}</span>
            </a>
            @foreach (['active', 'inactive', 'on_hold', 'blacklisted'] as $statusKey)
                <a class="master-list-chip {{ $status === $statusKey ? 'is-active' : '' }}" href="{{ $statusUrl($statusKey) }}">
                    {{ $statusOptions[$statusKey] }}
                    <span class="master-list-chip-count">{{ $stats[$statusKey] }}</span>
                </a>
            @endforeach
        </nav>

        <form method="GET" action="{{ route('vendors.index') }}">
            <div class="master-filter-row core-filter-toolbar">
                <label class="master-search">
                    <span aria-hidden="true"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input class="master-input" type="search" name="search" value="{{ $search }}" maxlength="150"
                        placeholder="Search vendor, brand, category, tax ID or contact" aria-label="Search vendors">
                </label>
                <x-filter-trigger drawer="vendorFiltersDrawer" :count="$activeFilterCount" />
            </div>

            <x-drawer id="vendorFiltersDrawer" title="Filter vendors" eyebrow="Vendor filters"
                subtitle="Narrow the list by status, type, and country." size="medium">
                <section class="core-drawer-section">
                    <h3 class="core-drawer-section-title">Vendor profile</h3>
                    <div class="core-drawer-fields">
                        <div class="master-field">
                            <label class="master-label" for="vendorFilterStatus">Status</label>
                            <select class="master-select" id="vendorFilterStatus" name="status">
                                <option value="all">All statuses</option>
                                @foreach ($statusOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="vendorFilterType">Vendor type</label>
                            <select class="master-select" id="vendorFilterType" name="type">
                                <option value="all">All types</option>
                                @foreach ($typeOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($type === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="vendorFilterCountry">Country</label>
                            <select class="master-select" id="vendorFilterCountry" name="country">
                                <option value="all">All countries</option>
                                @foreach ($countries as $countryName)
                                    <option value="{{ $countryName }}" @selected($country === $countryName)>{{ $countryName }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </section>
                <x-slot:footer>
                    @if ($filtersActive)
                        <a class="master-btn master-btn-soft" href="{{ route('vendors.index') }}">Reset</a>
                    @endif
                    <button class="master-btn master-btn-primary" type="submit">
                        <i class="fa-solid fa-filter" aria-hidden="true"></i> Apply filters
                    </button>
                </x-slot:footer>
            </x-drawer>

            @if ($filtersActive)
                <div class="master-list-applied" aria-label="Active filters">
                    <span class="master-list-applied-title">Filtered by</span>
                    @if (filled($search))
                        <span class="master-list-applied-chip">
                            <span class="master-list-applied-key">Search</span>
                            <span class="master-list-applied-value">{{ $search }}</span>
                            <a class="master-list-applied-x" href="{{ $removeFilterUrl('search') }}"
                                aria-label="Remove the search filter" title="Remove the search filter">&times;</a>
                        </span>
                    @endif
                    @if ($status !== 'all')
                        <span class="master-list-applied-chip">
                            <span class="master-list-applied-key">Status</span>
                            <span class="master-list-applied-value">{{ $statusOptions[$status] ?? $status }}</span>
                            <a class="master-list-applied-x" href="{{ $removeFilterUrl('status') }}"
                                aria-label="Remove the status filter" title="Remove the status filter">&times;</a>
                        </span>
                    @endif
                    @if ($type !== 'all')
                        <span class="master-list-applied-chip">
                            <span class="master-list-applied-key">Type</span>
                            <span class="master-list-applied-value">{{ $typeOptions[$type] ?? $type }}</span>
                            <a class="master-list-applied-x" href="{{ $removeFilterUrl('type') }}"
                                aria-label="Remove the vendor type filter" title="Remove the vendor type filter">&times;</a>
                        </span>
                    @endif
                    @if ($country !== 'all')
                        <span class="master-list-applied-chip">
                            <span class="master-list-applied-key">Country</span>
                            <span class="master-list-applied-value">{{ $country }}</span>
                            <a class="master-list-applied-x" href="{{ $removeFilterUrl('country') }}"
                                aria-label="Remove the country filter" title="Remove the country filter">&times;</a>
                        </span>
                    @endif
                    <a class="master-list-applied-clear" href="{{ route('vendors.index') }}">Clear all filters</a>
                </div>
            @endif
        </form>
    </section>

    <section class="master-card master-table-card master-card--flat" aria-label="Vendor records">
        <div class="master-list-toolbar">
            <p class="master-list-hint">
                {{ $vendorCount === 0 ? 'No matching vendors' : 'Newest first · Showing '.$firstVendor.'–'.$lastVendor.' of '.$vendorCount }}
            </p>
            <div class="master-list-toolbar-actions">
                <div class="master-list-density desktop-only" role="group" aria-label="Table density">
                    <button type="button" class="master-list-density-btn" data-density="standard" aria-pressed="true">Standard</button>
                    <button type="button" class="master-list-density-btn" data-density="comfortable" aria-pressed="false">Comfortable</button>
                    <button type="button" class="master-list-density-btn" data-density="compact" aria-pressed="false">Compact</button>
                </div>
            </div>
        </div>

        <div class="master-table-wrap ui-mobile-cards">
            <table class="master-table vendor-table" data-table-settings data-table-key="vendors">
                <thead>
                    <tr>
                        <th scope="col">Vendor</th>
                        <th scope="col">Primary contact</th>
                        <th scope="col">Type & category</th>
                        <th scope="col" class="ui-mobile-secondary">Location</th>
                        <th scope="col" class="ui-mobile-secondary">Rating</th>
                        <th scope="col">Status</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($vendors as $vendor)
                        @php
                            $statusClass = str_replace('_', '-', $vendor->status);
                            $typeClass = str_replace('_', '-', $vendor->vendor_type);
                            $initials = collect(explode(' ', trim($vendor->vendor_name)))
                                ->filter()
                                ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
                                ->take(2)
                                ->implode('');
                            $avatarTone = abs(crc32($vendor->vendor_name)) % 6;
                            $location = collect([$vendor->city, $vendor->state, $vendor->country])->filter()->implode(', ');
                        @endphp
                        <tr>
                            <td data-label="Vendor">
                                <div class="vendor-table-identity">
                                    @if ($vendor->image_path)
                                        <img class="vendor-table-avatar" src="{{ asset('storage/'.$vendor->image_path) }}" alt="">
                                    @else
                                        <span class="vendor-table-avatar vendor-avatar-tone-{{ $avatarTone }}" aria-hidden="true">{{ $initials }}</span>
                                    @endif
                                    <div class="vendor-table-copy">
                                        <a class="vendor-table-name" href="{{ route('vendors.show', $vendor) }}">{{ $vendor->vendor_name }}</a>
                                        <span class="vendor-table-meta">{{ $vendor->vendor_number ?: 'Vendor record' }}@if($vendor->brand_name) <span aria-hidden="true">·</span> {{ $vendor->brand_name }}@endif</span>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Primary contact">
                                <div class="vendor-primary-contact">
                                    <strong>{{ $vendor->contact_person_name ?: 'No contact added' }}</strong>
                                    @if ($vendor->contact_person_email)
                                        <a href="mailto:{{ $vendor->contact_person_email }}">{{ $vendor->contact_person_email }}</a>
                                    @endif
                                    @if ($vendor->contact_person_mobile)
                                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $vendor->contact_person_mobile) }}">{{ $vendor->contact_person_mobile }}</a>
                                    @endif
                                </div>
                            </td>
                            <td data-label="Type & category">
                                <span class="master-badge vendor-type vendor-type-{{ $typeClass }}">{{ $vendor->typeLabel() }}</span>
                                <span class="vendor-table-meta">{{ $vendor->category ?: 'No category' }}</span>
                            </td>
                            <td data-label="Location" class="ui-mobile-secondary">{{ $location ?: 'Not on file' }}</td>
                            <td data-label="Rating" class="ui-mobile-secondary">
                                @if ($vendor->rating)
                                    <span class="vendor-rating" aria-label="{{ $vendor->rating }} out of 5 stars">{{ str_repeat('★', $vendor->rating) }}<span>{{ str_repeat('☆', 5 - $vendor->rating) }}</span></span>
                                @else
                                    <span class="vendor-table-meta">Not rated</span>
                                @endif
                            </td>
                            <td data-label="Status">
                                <span class="master-badge vendor-status vendor-status-{{ $statusClass }}">{{ $vendor->statusLabel() }}</span>
                            </td>
                            <td data-label="Action" class="vendor-table-actions-cell">
                                <div class="master-row-actions">
                                    <button type="button" class="master-icon-btn vendor-quick-view"
                                        title="Quick details" aria-label="Quick details for {{ $vendor->vendor_name }}"
                                        aria-haspopup="dialog" aria-controls="vendorQuickDetails" aria-expanded="false"
                                        data-drawer-open="vendorQuickDetails" data-drawer-eyebrow="Vendor"
                                        data-drawer-title="{{ $vendor->vendor_name }}"
                                        data-drawer-subtitle="{{ $vendor->vendor_number }}"
                                        data-drawer-contact-name="{{ $vendor->contact_person_name }}"
                                        data-drawer-contact-email="{{ $vendor->contact_person_email }}"
                                        data-drawer-contact-mailto="{{ $vendor->contact_person_email ? 'mailto:'.$vendor->contact_person_email : '' }}"
                                        data-drawer-contact-mobile="{{ $vendor->contact_person_mobile }}"
                                        data-drawer-contact-tel="{{ $vendor->contact_person_mobile ? 'tel:'.preg_replace('/[^0-9+]/', '', $vendor->contact_person_mobile) : '' }}"
                                        data-drawer-type="{{ $vendor->typeLabel() }}"
                                        data-drawer-category="{{ $vendor->category }}"
                                        data-drawer-location="{{ $location }}"
                                        data-drawer-status="{{ $vendor->statusLabel() }}"
                                        data-drawer-website="{{ $vendor->website }}"
                                        data-drawer-alibaba="{{ $vendor->alibaba_link }}"
                                        data-drawer-record-url="{{ route('vendors.show', $vendor) }}">
                                        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                                    </button>
                                    <div class="master-dropdown">
                                        <button type="button" class="master-dropdown-toggle"
                                            aria-label="Actions for {{ $vendor->vendor_name }}" aria-haspopup="true" aria-expanded="false">
                                            <i class="fa-solid fa-ellipsis" aria-hidden="true"></i>
                                        </button>
                                        <div class="master-dropdown-menu">
                                            <a href="{{ route('vendors.show', $vendor) }}"><i class="fa-solid fa-eye" aria-hidden="true"></i> View vendor</a>
                                            <a href="{{ route('vendors.edit', $vendor) }}"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit vendor</a>
                                            <button type="button" class="danger master-delete-btn" aria-label="Delete {{ $vendor->vendor_name }}"
                                                data-name="{{ $vendor->vendor_name }}" data-delete-url="{{ route('vendors.destroy', $vendor) }}">
                                                <i class="fa-regular fa-trash-can" aria-hidden="true"></i> Delete vendor
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="master-list-empty">
                                    <span class="master-list-empty-icon"><i class="fa-solid fa-industry" aria-hidden="true"></i></span>
                                    <h2 class="master-list-empty-title">{{ $filtersActive ? 'No vendors match these filters' : 'Your vendor list is empty' }}</h2>
                                    <p class="master-list-empty-text">{{ $filtersActive ? 'Try another search or clear the filters to see all vendor records.' : 'Create a vendor profile to keep contacts, commercial terms and supplier activity together.' }}</p>
                                    <div class="master-list-empty-actions">
                                        @if ($filtersActive)
                                            <a href="{{ route('vendors.index') }}" class="master-btn master-btn-soft">Clear filters</a>
                                        @else
                                            <a href="{{ route('vendors.create') }}" class="master-btn master-btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add first vendor</a>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-pagination :items="$vendors" />
    </section>

    <x-drawer id="vendorQuickDetails" title="Vendor details" eyebrow="Vendor quick view" size="medium">
        <section class="core-drawer-section">
            <h3 class="core-drawer-section-title">Vendor profile</h3>
            <div class="core-drawer-fields">
                <div class="core-drawer-field" data-drawer-field>
                    <span class="core-drawer-field-label">Vendor type</span>
                    <span class="core-drawer-field-value" data-drawer-bind="type"></span>
                </div>
                <div class="core-drawer-field" data-drawer-field>
                    <span class="core-drawer-field-label">Category</span>
                    <span class="core-drawer-field-value" data-drawer-bind="category" data-drawer-empty="Not set"></span>
                </div>
                <div class="core-drawer-field" data-drawer-field>
                    <span class="core-drawer-field-label">Status</span>
                    <span class="core-drawer-field-value" data-drawer-bind="status"></span>
                </div>
                <div class="core-drawer-field" data-drawer-field>
                    <span class="core-drawer-field-label">Location</span>
                    <span class="core-drawer-field-value" data-drawer-bind="location" data-drawer-empty="Not provided"></span>
                </div>
            </div>
        </section>
        <section class="core-drawer-section">
            <h3 class="core-drawer-section-title">Contact</h3>
            <div class="core-drawer-fields">
                <div class="core-drawer-field" data-drawer-field>
                    <span class="core-drawer-field-label">Contact person</span>
                    <span class="core-drawer-field-value" data-drawer-bind="contact-name" data-drawer-empty="Not provided"></span>
                </div>
                <div class="core-drawer-field" data-drawer-field>
                    <span class="core-drawer-field-label">Mobile</span>
                    <a class="core-drawer-field-value" data-drawer-href-bind="contact-tel" data-drawer-hide-if-empty><span data-drawer-bind="contact-mobile"></span></a>
                </div>
                <div class="core-drawer-field" data-drawer-field>
                    <span class="core-drawer-field-label">Email</span>
                    <a class="core-drawer-field-value" data-drawer-href-bind="contact-mailto" data-drawer-hide-if-empty><span data-drawer-bind="contact-email"></span></a>
                </div>
                <div class="core-drawer-field" data-drawer-field>
                    <span class="core-drawer-field-label">Website</span>
                    <a class="core-drawer-field-value" data-drawer-href-bind="website" target="_blank" rel="noopener noreferrer" data-drawer-hide-if-empty>Visit website</a>
                </div>
                <div class="core-drawer-field" data-drawer-field>
                    <span class="core-drawer-field-label">Alibaba</span>
                    <a class="core-drawer-field-value" data-drawer-href-bind="alibaba" target="_blank" rel="noopener noreferrer" data-drawer-hide-if-empty>Open Alibaba profile</a>
                </div>
            </div>
        </section>
        <x-slot:footer>
            <a class="master-btn master-btn-primary" data-drawer-href-bind="record-url">Open full vendor record</a>
        </x-slot:footer>
    </x-drawer>

    <div class="master-modal" id="quickVendorModal" aria-hidden="true" @if($errors->any()) data-auto-open="true" @endif>
        <div class="master-modal-card vendor-modal-card" role="dialog" aria-modal="true" aria-labelledby="quickVendorTitle"
            aria-describedby="quickVendorDescription" tabindex="-1">
            <form method="POST" action="{{ route('vendors.quickStore') }}" enctype="multipart/form-data">
                @csrf
                <div class="master-modal-header">
                    <div class="master-modal-heading">
                        <span class="master-modal-icon"><i class="fa-solid fa-industry" aria-hidden="true"></i></span>
                        <div>
                            <h2 class="master-modal-title" id="quickVendorTitle">Quick add vendor</h2>
                            <p class="master-modal-subtitle" id="quickVendorDescription">Create the supplier record with the essentials. Complete commercial and compliance details later.</p>
                        </div>
                    </div>
                    <button type="button" class="master-modal-close" id="closeQuickVendorModal" aria-label="Close quick add dialog">&times;</button>
                </div>
                <div class="master-modal-body">
                    @if ($errors->any())
                        <div class="vendor-form-errors" role="alert">
                            <strong>Check the vendor details and try again.</strong>
                            <ul>@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
                        </div>
                    @endif
                    <div class="master-modal-grid">
                        <div class="master-field full">
                            <label class="master-label" for="quick_vendor_image">Company image</label>
                            <input class="master-input @error('vendor_image') is-invalid @enderror" id="quick_vendor_image" type="file"
                                name="vendor_image" accept="image/jpeg,image/png,image/webp,image/gif">
                            <small class="master-help">Optional · JPG, PNG, WEBP or GIF · up to 2 MB</small>
                            @error('vendor_image')<span class="master-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="master-field full">
                            <label class="master-label" for="quick_vendor_name">Company name <span class="master-required" aria-hidden="true">*</span></label>
                            <input class="master-input @error('vendor_name') is-invalid @enderror" id="quick_vendor_name" name="vendor_name"
                                value="{{ old('vendor_name') }}" maxlength="255" autocomplete="organization" required placeholder="Vendor company name">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quick_vendor_type">Vendor type <span class="master-required" aria-hidden="true">*</span></label>
                            <select class="master-select @error('vendor_type') is-invalid @enderror" id="quick_vendor_type" name="vendor_type" required>
                                @foreach ($typeOptions as $key => $label)
                                    <option value="{{ $key }}" @selected(old('vendor_type', 'manufacturer') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quick_vendor_status">Status <span class="master-required" aria-hidden="true">*</span></label>
                            <select class="master-select @error('status') is-invalid @enderror" id="quick_vendor_status" name="status" required>
                                @foreach ($statusOptions as $key => $label)
                                    <option value="{{ $key }}" @selected(old('status', 'active') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quick_vendor_category">Category</label>
                            <input class="master-input" id="quick_vendor_category" name="category" value="{{ old('category') }}" maxlength="255" placeholder="Raw material / Packaging">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quick_vendor_brand">Brand name</label>
                            <input class="master-input" id="quick_vendor_brand" name="brand_name" value="{{ old('brand_name') }}" maxlength="255">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quick_vendor_contact">Contact person</label>
                            <input class="master-input" id="quick_vendor_contact" name="contact_person_name" value="{{ old('contact_person_name') }}" maxlength="255" autocomplete="name">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quick_vendor_email">Email</label>
                            <input class="master-input" id="quick_vendor_email" type="email" name="contact_person_email" value="{{ old('contact_person_email') }}" maxlength="255" autocomplete="email">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quick_vendor_mobile">Mobile</label>
                            <input class="master-input" id="quick_vendor_mobile" type="tel" name="contact_person_mobile" value="{{ old('contact_person_mobile') }}" maxlength="40" autocomplete="tel">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quick_vendor_currency">Preferred currency</label>
                            <select class="master-select" id="quick_vendor_currency" name="preferred_currency">
                                @foreach ($currencyOptions as $key => $label)
                                    <option value="{{ $key }}" @selected(old('preferred_currency', 'INR') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quick_vendor_country">Country</label>
                            <input class="master-input" id="quick_vendor_country" name="country" value="{{ old('country', 'China') }}" maxlength="255" autocomplete="country-name">
                        </div>
                    </div>
                </div>
                <div class="master-modal-footer">
                    <button type="button" class="master-btn master-btn-light" id="cancelQuickVendorModal">Cancel</button>
                    <button type="submit" class="master-btn master-btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Create vendor</button>
                </div>
            </form>
        </div>
    </div>

    <div class="master-modal" id="deleteVendorModal" aria-hidden="true">
        <div class="master-modal-card vendor-delete-dialog" role="dialog" aria-modal="true" aria-labelledby="deleteVendorTitle" aria-describedby="deleteVendorDesc" tabindex="-1">
            <div class="master-modal-header">
                <div class="master-modal-heading">
                    <span class="master-modal-icon vendor-delete-icon"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></span>
                    <div>
                        <h2 class="master-modal-title" id="deleteVendorTitle">Delete vendor?</h2>
                        <p class="master-modal-subtitle">This action cannot be undone.</p>
                    </div>
                </div>
                <button type="button" class="master-modal-close" id="closeDeleteVendorModal" aria-label="Close delete dialog">&times;</button>
            </div>
            <div class="master-modal-body"><p class="vendor-delete-description" id="deleteVendorDesc">Are you sure you want to delete this vendor?</p></div>
            <div class="master-modal-footer">
                <button type="button" class="master-btn master-btn-light" id="cancelDeleteVendorModal">Cancel</button>
                <form method="POST" id="deleteVendorForm">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="master-btn master-btn-danger">Delete vendor</button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script src="{{ asset('assets/js/vendors.js') }}"></script>
@endpush
@endsection
