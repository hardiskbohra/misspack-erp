@extends('layouts.app')

@section('title', 'Vendors')
@section('page-title', 'Vendors')

@section('page-actions')
    {{-- The page's primary action lives in the header, so it stays reachable
         however far the list scrolls — the same slot every other list uses. --}}
    <button type="button" class="master-btn master-btn-primary" data-open-quick-vendor>
        <i class="fa-solid fa-bolt" aria-hidden="true"></i> Quick add
    </button>
    <a href="{{ route('vendors.create') }}" class="master-btn master-btn-soft">
        <i class="fa-solid fa-plus" aria-hidden="true"></i> Detailed form
    </a>
@endsection

@section('content')

@php
    /* One URL per removable filter: everything else stays, the page restarts,
       and a filter that has no visible control above (it arrived from a URL or
       a bookmark) can still be taken off. */
    $chipUrl = function (string $key) {
        $keep = collect(request()->except([$key, 'page', 'saved_view']))
            ->reject(fn ($value) => $value === null || $value === '' || $value === 'all');

        return route('vendors.index', $keep->all());
    };
    $baseFilters = collect(request()->except(['page', 'saved_view']))
        ->reject(fn ($value) => $value === null || $value === '' || $value === 'all');
    $statusUrl = function ($value) use ($baseFilters) {
        $query = $baseFilters->except(['status'])->all();
        if ($value !== 'all') {
            $query['status'] = $value;
        }

        return route('vendors.index', $query);
    };
    $vendorCount = $vendors->total();
    $firstVendor = $vendors->firstItem() ?? 0;
    $lastVendor = $vendors->lastItem() ?? 0;
@endphp

<div class="vendor vendor-index master-list">

    {{-- The five figures the office actually has about its suppliers. They
         count the module, not the page: a tile that changed with the filter
         would be a tile you cannot trust. --}}
    <div class="master-stats desktop-only" aria-label="Vendor overview">
        <div class="master-stat master-stat--flat blue">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-industry"></i></span>
            <div>
                <p class="master-stat-title">Total vendors</p>
                <p class="master-stat-value">{{ number_format($stats['total']) }}</p>
                <p class="master-sub">{{ number_format($stats['international']) }} outside India</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat green">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></span>
            <div>
                <p class="master-stat-title">Active</p>
                <p class="master-stat-value">{{ number_format($stats['active']) }}</p>
                <p class="master-sub">{{ number_format($stats['inactive']) }} inactive</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat {{ ($stats['on_hold'] + $stats['blacklisted']) ? 'orange' : 'teal' }}">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-triangle-exclamation"></i></span>
            <div>
                <p class="master-stat-title">Needs attention</p>
                <p class="master-stat-value">{{ number_format($stats['on_hold'] + $stats['blacklisted']) }}</p>
                <p class="master-sub">{{ $stats['on_hold'] }} on hold · {{ $stats['blacklisted'] }} blacklisted</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat {{ ($stats['owing_vendors'] ?? 0) > 0 ? 'purple' : 'teal' }}">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-file-invoice-dollar"></i></span>
            <div>
                <p class="master-stat-title">Owing</p>
                <p class="master-stat-value">{{ number_format($stats['owing_vendors'] ?? 0) }}</p>
                <p class="master-sub">vendors with an open balance</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat {{ $stats['overdue_vendors'] ? 'red' : 'teal' }}">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-clock-rotate-left"></i></span>
            <div>
                <p class="master-stat-title">Overdue</p>
                <p class="master-stat-value">{{ number_format($stats['overdue_vendors']) }}</p>
                <p class="master-sub">
                    {{ $stats['overdue_vendors'] ? 'vendors with a late bill' : 'nothing past its due date' }}
                </p>
            </div>
        </div>
    </div>

    <section class="master-card master-card--flat" aria-label="Search and filter vendors">
        <div class="master-list-bar">
            <nav class="master-list-chips" aria-label="Quick vendor filters">
                <a class="master-list-chip {{ $status === 'all' ? 'is-active' : '' }}" href="{{ $statusUrl('all') }}">
                    All vendors <span class="master-list-chip-count">{{ $stats['total'] }}</span>
                </a>
                <a class="master-list-chip {{ $status === 'active' ? 'is-active' : '' }}" href="{{ $statusUrl('active') }}">
                    Active <span class="master-list-chip-count">{{ $stats['active'] }}</span>
                </a>
                <a class="master-list-chip {{ $status === 'inactive' ? 'is-active' : '' }}" href="{{ $statusUrl('inactive') }}">
                    Inactive <span class="master-list-chip-count">{{ $stats['inactive'] }}</span>
                </a>
                <a class="master-list-chip {{ $status === 'on_hold' ? 'is-active' : '' }}" href="{{ $statusUrl('on_hold') }}">
                    On hold <span class="master-list-chip-count">{{ $stats['on_hold'] }}</span>
                </a>
                <a class="master-list-chip {{ $status === 'blacklisted' ? 'is-active' : '' }}" href="{{ $statusUrl('blacklisted') }}">
                    Blacklisted <span class="master-list-chip-count">{{ $stats['blacklisted'] }}</span>
                </a>
                <a class="master-list-chip {{ $overdue === '1' ? 'is-active' : '' }}"
                    href="{{ route('vendors.index', $baseFilters->except(['overdue', 'page'])->all() + ($overdue === '1' ? [] : ['overdue' => '1'])) }}"
                    @if ($overdue === '1') aria-current="true" @endif>
                    Owing &amp; overdue <span class="master-list-chip-count">{{ $stats['overdue_vendors'] }}</span>
                </a>
            </nav>

            <div class="master-list-saved" aria-label="Saved vendor views">
                @foreach ($savedViews as $view)
                    <span class="master-list-saved-chip">
                        <a href="{{ route('vendors.index', ['saved_view' => $view->id]) }}"
                            title="{{ $view->is_shared ? 'Shared view' : 'Your view' }}">{{ $view->name }}</a>
                        @if ((int) $view->user_id === (int) auth()->id())
                            <form method="POST" action="{{ route('vendors.saved-views.destroy', $view) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" title="Remove saved view" aria-label="Remove {{ $view->name }}">&times;</button>
                            </form>
                        @endif
                    </span>
                @endforeach
                <button type="button" class="master-btn master-btn-soft master-btn-sm" id="toggleSaveView">☆ Save this view</button>
                <form method="POST" action="{{ route('vendors.saved-views.store', $baseFilters->all()) }}"
                    class="master-list-save-view" id="saveViewForm" hidden>
                    @csrf
                    <input class="master-input" name="name" placeholder="View name" maxlength="60" aria-label="Saved view name" required>
                    <label class="master-check"><input type="checkbox" name="is_shared" value="1"> Share</label>
                    <button class="master-btn master-btn-primary master-btn-sm" type="submit">Save</button>
                </form>
            </div>
        </div>

        <form method="GET" action="{{ route('vendors.index') }}">
            {{-- The chip and the drawer drive the same filter; carrying it in
                 the form keeps a search or a filter change from dropping it. --}}
            @if ($overdue === '1')
                <input type="hidden" name="overdue" value="1">
            @endif
            <div class="master-filter-row core-filter-toolbar">
                <label class="master-search">
                    <span aria-hidden="true"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input class="master-input" type="search" name="search" value="{{ $search }}" maxlength="150"
                        placeholder="Search vendor, brand, category, GSTIN, contact or country" aria-label="Search vendors">
                </label>
                <x-filter-trigger drawer="vendorFiltersDrawer"
                    :count="(filled($search) ? 1 : 0) + ($status !== 'all' ? 1 : 0) + ($type !== 'all' ? 1 : 0) + ($country !== 'all' ? 1 : 0) + ($overdue === '1' ? 1 : 0)" />
            </div>

            <x-drawer id="vendorFiltersDrawer" title="Filter vendors" eyebrow="Vendor filters"
                subtitle="Narrow the list by status, vendor type, or country." size="medium">
                <section class="core-drawer-section">
                    <h3 class="core-drawer-section-title">Vendor profile</h3>
                    <div class="core-drawer-fields">
                        <div class="master-field">
                            <label class="master-label" for="vendorFilterStatus">Status</label>
                            <select class="master-select" id="vendorFilterStatus" name="status" aria-label="Filter by vendor status">
                                <option value="all">All statuses</option>
                                @foreach($statusOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="vendorFilterType">Vendor type</label>
                            <select class="master-select" id="vendorFilterType" name="type" aria-label="Filter by vendor type">
                                <option value="all">All types</option>
                                @foreach($typeOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($type === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="vendorFilterOverdue">Money owed</label>
                            <label class="master-check" for="vendorFilterOverdue">
                                <input type="checkbox" id="vendorFilterOverdue" name="overdue" value="1" @checked($overdue === '1')>
                                Only vendors with a bill past its due date
                            </label>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="vendorFilterCountry">Country</label>
                            <select class="master-select" id="vendorFilterCountry" name="country" aria-label="Filter by country">
                                <option value="all">All countries</option>
                                @foreach($countries as $countryName)
                                    <option value="{{ $countryName }}" @selected($country === $countryName)>{{ $countryName }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </section>
                <x-slot:footer>
                    @if($filtersActive)
                        <a class="master-btn master-btn-soft" href="{{ route('vendors.index') }}">Reset</a>
                    @endif
                    <button class="master-btn master-btn-primary" type="submit">
                        <i class="fa-solid fa-filter" aria-hidden="true"></i> Apply filters
                    </button>
                </x-slot:footer>
            </x-drawer>

            @if($filtersActive)
                <div class="master-list-applied" aria-label="Active filters">
                    <span class="master-list-applied-title">Filtered by</span>
                    @if(filled($search))
                        <span class="master-list-applied-chip">
                            <span class="master-list-applied-key">Search</span>
                            <span class="master-list-applied-value">{{ $search }}</span>
                            <a class="master-list-applied-x" href="{{ $chipUrl('search') }}"
                                aria-label="Remove the search filter" title="Remove the search filter">&times;</a>
                        </span>
                    @endif
                    @if($status !== 'all')
                        <span class="master-list-applied-chip">
                            <span class="master-list-applied-key">Status</span>
                            <span class="master-list-applied-value">{{ $statusOptions[$status] ?? $status }}</span>
                            <a class="master-list-applied-x" href="{{ $chipUrl('status') }}"
                                aria-label="Remove the status filter" title="Remove the status filter">&times;</a>
                        </span>
                    @endif
                    @if($type !== 'all')
                        <span class="master-list-applied-chip">
                            <span class="master-list-applied-key">Type</span>
                            <span class="master-list-applied-value">{{ $typeOptions[$type] ?? $type }}</span>
                            <a class="master-list-applied-x" href="{{ $chipUrl('type') }}"
                                aria-label="Remove the vendor type filter" title="Remove the vendor type filter">&times;</a>
                        </span>
                    @endif
                    @if($overdue === '1')
                        <span class="master-list-applied-chip">
                            <span class="master-list-applied-key">Showing</span>
                            <span class="master-list-applied-value">Vendors with an overdue bill</span>
                            <a class="master-list-applied-x" href="{{ $chipUrl('overdue') }}"
                                aria-label="Remove the overdue filter" title="Remove the overdue filter">&times;</a>
                        </span>
                    @endif
                    @if($country !== 'all')
                        <span class="master-list-applied-chip">
                            <span class="master-list-applied-key">Country</span>
                            <span class="master-list-applied-value">{{ $country }}</span>
                            <a class="master-list-applied-x" href="{{ $chipUrl('country') }}"
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
            <p class="master-list-hint" title="Vendor records are shown newest first.">
                {{ $vendorCount === 0 ? 'No matching vendors' : 'Newest first · Showing '.$firstVendor.'–'.$lastVendor.' of '.$vendorCount }}
            </p>
            <div class="master-list-toolbar-actions">
                <a class="master-btn master-btn-light master-btn-sm"
                    href="{{ route('vendors.payables.export', $baseFilters->all()) }}"
                    title="Every bill with a due date these filters match, as a spreadsheet">
                    <i class="fa-solid fa-file-csv" aria-hidden="true"></i> Payables
                </a>
                <div class="master-list-density desktop-only" role="group" aria-label="Table density">
                    <button type="button" class="master-list-density-btn" data-density="standard" aria-pressed="true">Standard</button>
                    <button type="button" class="master-list-density-btn" data-density="comfortable" aria-pressed="false">Comfortable</button>
                    <button type="button" class="master-list-density-btn" data-density="compact" aria-pressed="false">Compact</button>
                </div>
            </div>
        </div>

        {{-- The bulk bar appears only when a row is ticked: one status change
             across the vendors the office selected, without opening them one
             by one. The table's checkboxes belong to this form through the
             form attribute, so the table stays a table. --}}
        <form method="POST" action="{{ route('vendors.bulk-status') }}" id="vendorBulkForm"
            class="master-list-bulk" data-bulk-bar hidden>
            @csrf
            @method('PATCH')
            <span class="master-list-bulk-count" data-bulk-count>0 vendors selected</span>
            <select class="master-select" name="action" aria-label="Bulk action" required>
                <option value="">Choose an action…</option>
                @foreach ($statusActions as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" class="master-btn master-btn-primary master-btn-sm">Apply to selected</button>
            <button type="button" class="master-btn master-btn-light master-btn-sm" data-bulk-clear>Clear</button>
        </form>

        <div class="master-table-wrap ui-mobile-cards">
            <table class="master-table vendor-table" data-table-settings data-table-key="vendors">
                <thead>
                    <tr>
                        <th scope="col" class="master-list-pick vendor-pick-cell">
                            <input type="checkbox" data-bulk-all aria-label="Select every vendor on this page">
                        </th>
                        <th scope="col">Vendor</th>
                        <th scope="col">Type</th>
                        <th scope="col">Contact</th>
                        <th scope="col" class="ui-mobile-secondary">Location</th>
                        <th scope="col" class="ui-mobile-secondary">Terms</th>
                        <th scope="col" class="is-num">Payable</th>
                        <th scope="col">Status</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($vendors as $vendor)
                        @php
                            $statusClass = str_replace('_', '-', $vendor->status);
                            $typeClass = str_replace('_', '-', $vendor->vendor_type);
                            $initials = collect(explode(' ', trim($vendor->vendor_name)))
                                ->filter()
                                ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
                                ->take(2)
                                ->implode('');
                            $avatarTone = abs(crc32($vendor->vendor_name)) % 6;
                        @endphp
                        @php
                            $vendorListCurrency = $vendor->preferred_currency ?: 'RMB';
                            $billed = (float) ($vendor->billed_foreign ?? 0);
                            $paid = (float) ($vendor->paid_foreign ?? 0);
                            $payable = max($billed - $paid, 0);
                        @endphp
                        <tr class="vendor-row is-clickable" data-href="{{ route('vendors.show', $vendor) }}">
                            <td class="master-list-pick vendor-pick-cell" data-label="Select">
                                <input type="checkbox" name="ids[]" value="{{ $vendor->id }}" form="vendorBulkForm"
                                    data-bulk-pick aria-label="Select {{ $vendor->vendor_name }}">
                            </td>
                            <td data-label="Vendor">
                                <div class="vendor-table-identity">
                                    @if($vendor->image_path)
                                        <img class="vendor-table-avatar" src="{{ asset('storage/' . $vendor->image_path) }}"
                                            alt="" loading="lazy">
                                    @else
                                        <span class="vendor-table-avatar vendor-avatar-tone-{{ $avatarTone }}" aria-hidden="true">{{ $initials }}</span>
                                    @endif
                                    <div class="vendor-table-copy">
                                        <a class="vendor-table-name" href="{{ route('vendors.show', $vendor) }}">{{ $vendor->vendor_name }}</a>
                                        <span class="vendor-table-meta">{{ $vendor->vendor_number }}@if($vendor->brand_name) <span aria-hidden="true">·</span> {{ $vendor->brand_name }}@endif</span>
                                        <span class="master-sub ui-mobile-secondary">{{ $vendor->category ?: 'No category' }}</span>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Type">
                                <span class="vendor-type-chip type-{{ $typeClass }}">{{ $vendor->typeLabel() }}</span>
                                @if($vendor->rating)
                                    <span class="vendor-table-meta ui-mobile-secondary" aria-label="Rated {{ $vendor->rating }} of 5">
                                        {{ str_repeat('★', $vendor->rating) }}
                                    </span>
                                @endif
                            </td>
                            <td data-label="Contact">
                                @if($vendor->contact_person_name)
                                    <strong>{{ $vendor->contact_person_name }}</strong>
                                @else
                                    <span class="master-empty-value">No contact added</span>
                                @endif
                                @if($vendor->contact_person_email)
                                    <a class="vendor-contact-line" href="mailto:{{ $vendor->contact_person_email }}">{{ $vendor->contact_person_email }}</a>
                                @endif
                                @if($vendor->contact_person_mobile)
                                    <a class="vendor-contact-line" href="tel:{{ preg_replace('/[^0-9+]/', '', $vendor->contact_person_mobile) }}">{{ $vendor->contact_person_mobile }}</a>
                                @endif
                            </td>
                            <td data-label="Location" class="ui-mobile-secondary">
                                {{ $vendor->city ?: '—' }}
                                <span class="master-sub">{{ $vendor->country ?: 'Country not set' }}</span>
                            </td>
                            <td data-label="Terms" class="ui-mobile-secondary">
                                {{ $vendorListCurrency }}
                                <span class="master-sub">{{ $vendor->payment_terms ?: 'No terms on file' }}</span>
                            </td>
                            <td data-label="Payable" class="is-num">
                                @if ($payable > 0)
                                    <strong class="vendor-payable">{{ \App\Helpers\CommonHelper::amount($payable, $vendorListCurrency) }}</strong>
                                    <span class="master-sub">{{ $billed > 0 ? 'of '.\App\Helpers\CommonHelper::amount($billed, $vendorListCurrency).' billed' : '' }}</span>
                                @else
                                    <span class="master-empty-value">Settled</span>
                                @endif
                            </td>
                            <td data-label="Status">
                                <span class="master-badge status-{{ $statusClass }}">{{ $vendor->statusLabel() }}</span>
                            </td>
                            <td data-label="Action" class="vendor-table-actions-cell">
                                <div class="master-row-actions">
                                    <button type="button" class="master-icon-btn" title="Quick details"
                                        aria-label="Quick details for {{ $vendor->vendor_name }}"
                                        aria-haspopup="dialog" aria-controls="vendorQuickDetails" aria-expanded="false"
                                        data-drawer-open="vendorQuickDetails" data-drawer-eyebrow="Vendor"
                                        data-drawer-title="{{ $vendor->vendor_name }}"
                                        data-drawer-subtitle="{{ $vendor->vendor_number }}"
                                        data-drawer-type="{{ $vendor->typeLabel() }}"
                                        data-drawer-category="{{ $vendor->category }}"
                                        data-drawer-status="{{ $vendor->statusLabel() }}"
                                        data-drawer-location="{{ collect([$vendor->city, $vendor->state, $vendor->country])->filter()->implode(', ') }}"
                                        data-drawer-contact-name="{{ $vendor->contact_person_name }}"
                                        data-drawer-contact-email="{{ $vendor->contact_person_email }}"
                                        data-drawer-contact-mailto="{{ $vendor->contact_person_email ? 'mailto:'.$vendor->contact_person_email : '' }}"
                                        data-drawer-contact-mobile="{{ $vendor->contact_person_mobile }}"
                                        data-drawer-contact-tel="{{ $vendor->contact_person_mobile ? 'tel:'.preg_replace('/[^0-9+]/', '', $vendor->contact_person_mobile) : '' }}"
                                        data-drawer-currency="{{ $vendor->preferred_currency }}"
                                        data-drawer-terms="{{ $vendor->payment_terms }}"
                                        data-drawer-website="{{ $vendor->website }}"
                                        data-drawer-alibaba="{{ $vendor->alibaba_link }}"
                                        data-drawer-record-url="{{ route('vendors.show', $vendor) }}">
                                        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                                    </button>

                                    <div class="master-dropdown">
                                        <button type="button" class="master-dropdown-toggle"
                                            aria-label="Actions for {{ $vendor->vendor_name }}"
                                            aria-haspopup="true" aria-expanded="false">
                                            <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                        </button>
                                        <div class="master-dropdown-menu">
                                            <a href="{{ route('vendors.show', $vendor) }}">
                                                <i class="fa-solid fa-folder-open" aria-hidden="true"></i> Open vendor
                                            </a>
                                            <a href="{{ route('vendors.edit', $vendor) }}">
                                                <i class="fas fa-pen" aria-hidden="true"></i> Edit vendor
                                            </a>
                                            <a href="{{ route('vendors.show', [$vendor, 'tab' => 'money']) }}">
                                                <i class="fa-solid fa-scale-balanced" aria-hidden="true"></i> Ledger &amp; payables
                                            </a>
                                            @if($vendor->contact_person_email)
                                                <a href="mailto:{{ $vendor->contact_person_email }}">
                                                    <i class="fa-solid fa-envelope" aria-hidden="true"></i> Email contact
                                                </a>
                                            @endif
                                            <button type="button" class="danger master-delete-btn"
                                                aria-label="Delete {{ $vendor->vendor_name }}" title="Delete vendor"
                                                data-name="{{ $vendor->vendor_name }}" data-delete-url="{{ route('vendors.destroy', $vendor) }}">
                                                <i class="far fa-trash-alt" aria-hidden="true"></i> Delete vendor
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <div class="master-list-empty">
                                    <span class="master-list-empty-icon" aria-hidden="true"><i class="fa-solid fa-industry"></i></span>
                                    <h2 class="master-list-empty-title">{{ $filtersActive ? 'No vendors match these filters' : 'Your vendor list is empty' }}</h2>
                                    <p class="master-list-empty-text">
                                        {{ $filtersActive
                                            ? 'Try a different search or clear the filters to see every supplier record.'
                                            : 'Add a vendor to keep their contacts, tax and bank details, and payment ledger together.' }}
                                    </p>
                                    <div class="master-list-empty-actions">
                                        @if($filtersActive)
                                            <a href="{{ route('vendors.index') }}" class="master-btn master-btn-soft">Clear filters</a>
                                        @else
                                            <a href="{{ route('vendors.create') }}" class="master-btn master-btn-primary">
                                                <i class="fa-solid fa-plus" aria-hidden="true"></i> Add first vendor
                                            </a>
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

    {{-- The quick view: the facts an office checks before it rings a supplier,
         without leaving the list. Opens with data-drawer-open and the shared
         drawer script traps focus, closes on Escape, and returns focus to this
         row's button. --}}
    <x-drawer id="vendorQuickDetails" title="Vendor details" eyebrow="Vendor quick view" size="medium">
        <section class="core-drawer-section">
            <h3 class="core-drawer-section-title">Vendor profile</h3>
            <div class="core-drawer-fields">
                <div class="core-drawer-field" data-drawer-field>
                    <span class="core-drawer-field-label">Vendor type</span>
                    <span class="core-drawer-field-value" data-drawer-bind="type"></span>
                </div>
                <div class="core-drawer-field" data-drawer-field>
                    <span class="core-drawer-field-label">Status</span>
                    <span class="core-drawer-field-value" data-drawer-bind="status"></span>
                </div>
                <div class="core-drawer-field" data-drawer-field>
                    <span class="core-drawer-field-label">Category</span>
                    <span class="core-drawer-field-value" data-drawer-bind="category" data-drawer-empty="Not set"></span>
                </div>
                <div class="core-drawer-field" data-drawer-field>
                    <span class="core-drawer-field-label">Location</span>
                    <span class="core-drawer-field-value" data-drawer-bind="location" data-drawer-empty="Not provided"></span>
                </div>
                <div class="core-drawer-field" data-drawer-field>
                    <span class="core-drawer-field-label">Preferred currency</span>
                    <span class="core-drawer-field-value" data-drawer-bind="currency" data-drawer-empty="Not set"></span>
                </div>
                <div class="core-drawer-field" data-drawer-field>
                    <span class="core-drawer-field-label">Payment terms</span>
                    <span class="core-drawer-field-value" data-drawer-bind="terms" data-drawer-empty="Not on file"></span>
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
                    <a class="core-drawer-field-value" data-drawer-href-bind="contact-tel" data-drawer-hide-if-empty>
                        <span data-drawer-bind="contact-mobile"></span>
                    </a>
                </div>
                <div class="core-drawer-field" data-drawer-field>
                    <span class="core-drawer-field-label">Email</span>
                    <a class="core-drawer-field-value" data-drawer-href-bind="contact-mailto" data-drawer-hide-if-empty>
                        <span data-drawer-bind="contact-email"></span>
                    </a>
                </div>
                <div class="core-drawer-field" data-drawer-field>
                    <span class="core-drawer-field-label">Website</span>
                    <a class="core-drawer-field-value" data-drawer-href-bind="website" target="_blank" rel="noopener noreferrer"
                        data-drawer-hide-if-empty>Visit website</a>
                </div>
                <div class="core-drawer-field" data-drawer-field>
                    <span class="core-drawer-field-label">Alibaba</span>
                    <a class="core-drawer-field-value" data-drawer-href-bind="alibaba" target="_blank" rel="noopener noreferrer"
                        data-drawer-hide-if-empty>Open Alibaba profile</a>
                </div>
            </div>
        </section>
        <x-slot:footer>
            <a class="master-btn master-btn-primary" data-drawer-href-bind="record-url">Open full vendor record</a>
        </x-slot:footer>
    </x-drawer>

    {{-- ================= Quick add =================
         The shared modal sheet (.master-modal → .master-modal-card →
         .master-modal-body): header and footer pinned, the body carries the
         scroll. The essentials only — the detailed form holds the rest. --}}
    <div class="master-modal" id="quickVendorModal" aria-hidden="true">
        <div class="master-modal-card" role="dialog" aria-modal="true"
            aria-labelledby="quickVendorTitle" aria-describedby="quickVendorDescription">
            <form method="POST" action="{{ route('vendors.quickStore') }}" enctype="multipart/form-data" id="quickVendorForm">
                @csrf
                {{-- A failed save comes back to the dialog it came from. --}}
                <input type="hidden" name="_dialog" value="quick-vendor">
                <div class="master-modal-header">
                    <div class="master-modal-heading">
                        <span class="master-modal-icon" aria-hidden="true"><i class="fa-solid fa-industry"></i></span>
                        <div>
                            <h2 class="master-modal-title" id="quickVendorTitle">Quick add vendor</h2>
                            <p class="master-modal-subtitle" id="quickVendorDescription">Create a supplier with the essentials. Add the rest later.</p>
                        </div>
                    </div>
                    <button type="button" class="master-modal-close" data-close-modal="quickVendorModal" aria-label="Close quick add dialog">&times;</button>
                </div>
                <div class="master-modal-body">
                    <div class="master-modal-grid">
                        <div class="master-field full">
                            <label class="master-label" for="quick_vendor_name">Vendor name <span class="master-required" aria-hidden="true">*</span></label>
                            <input class="master-input" id="quick_vendor_name" name="vendor_name" required maxlength="255"
                                autocomplete="organization" placeholder="e.g. Sunrise Packaging Co.">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quick_vendor_type">Vendor type</label>
                            <select class="master-select" id="quick_vendor_type" name="vendor_type">
                                @foreach($typeOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($key === 'manufacturer')>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quick_category">Category</label>
                            <input class="master-input" id="quick_category" name="category" maxlength="255"
                                placeholder="Raw material / Packaging">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quick_brand_name">Brand name</label>
                            <input class="master-input" id="quick_brand_name" name="brand_name" maxlength="255" placeholder="Optional">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quick_country">Country</label>
                            <input class="master-input" id="quick_country" name="country" maxlength="255" value="India">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quick_contact_person_name">Contact person</label>
                            <input class="master-input" id="quick_contact_person_name" name="contact_person_name" maxlength="255" autocomplete="name">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quick_contact_person_email">Contact email</label>
                            <input class="master-input" id="quick_contact_person_email" type="email" name="contact_person_email"
                                maxlength="255" autocomplete="email" placeholder="name@company.com">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quick_contact_person_mobile">Contact phone</label>
                            <input class="master-input" id="quick_contact_person_mobile" type="tel" name="contact_person_mobile"
                                maxlength="40" autocomplete="tel" placeholder="+91">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quick_preferred_currency">Preferred currency</label>
                            <select class="master-select" id="quick_preferred_currency" name="preferred_currency">
                                @foreach($currencyOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($key === 'INR')>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="master-modal-footer">
                    <button type="button" class="master-btn master-btn-light" data-close-modal="quickVendorModal">Cancel</button>
                    <button type="submit" class="master-btn master-btn-primary">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i> Create vendor
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ================= Delete ================= --}}
    <div class="master-modal" id="deleteVendorModal" aria-hidden="true">
        <div class="master-modal-card is-narrow" role="dialog" aria-modal="true"
            aria-labelledby="deleteVendorTitle" aria-describedby="deleteVendorDesc">
            <div class="master-modal-header">
                <div class="master-modal-heading">
                    <span class="master-modal-icon is-danger" aria-hidden="true"><i class="fa-regular fa-trash-can"></i></span>
                    <div>
                        <h2 class="master-modal-title" id="deleteVendorTitle">Delete this vendor</h2>
                        <p class="master-modal-subtitle">This cannot be undone</p>
                    </div>
                </div>
                <button type="button" class="master-modal-close" data-close-modal="deleteVendorModal" aria-label="Close delete confirmation">&times;</button>
            </div>
            <div class="master-modal-body">
                <p class="master-modal-text" id="deleteVendorDesc">Are you sure you want to delete this vendor?</p>
                <p class="master-sub vendor-modal-foot">Their uploaded documents go with the record. Ledger rows already filed
                    against them stay, with the vendor name kept on the row.</p>
            </div>
            <form method="POST" action="" id="deleteVendorForm">
                @csrf
                @method('DELETE')
                <div class="master-modal-footer">
                    <button type="button" class="master-btn master-btn-light" data-close-modal="deleteVendorModal">Cancel</button>
                    <button type="submit" class="master-btn master-btn-danger">
                        <i class="fa-regular fa-trash-can" aria-hidden="true"></i> Delete vendor
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- A validation failure on the quick form re-opens the dialog, so the
         reader does not have to find their way back to it. --}}
    <span hidden data-open-dialog="{{ $errors->any() ? old('_dialog') : '' }}"></span>
</div>
@endsection

@push('scripts')
    <script src="{{ $assetVer('assets/js/vendors.js') }}"></script>
@endpush
