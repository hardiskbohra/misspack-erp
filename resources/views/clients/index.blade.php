@extends('layouts.app')

@section('title', 'Clients')
@section('page-title', 'Clients')

@section('page-actions')
    <button type="button" class="master-btn master-btn-primary" id="openQuickClientModal">
        <i class="fa-solid fa-bolt" aria-hidden="true"></i> Quick add
    </button>
    <a href="{{ route('clients.create') }}" class="master-btn master-btn-soft">
        <i class="fa-solid fa-plus" aria-hidden="true"></i> Detailed form
    </a>
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/clients.css') }}">
@endpush

@php
    $filtersActive = filled($search) || $status !== 'all' || $type !== 'all';
    $baseFilters = collect(request()->except(['page', 'saved_view']))
        ->reject(fn ($value) => $value === null || $value === '' || $value === 'all');
    $chipBase = collect(request()->except(['status', 'page', 'saved_view']))
        ->reject(fn ($value) => $value === null || $value === '' || $value === 'all');
    $statusUrl = function ($value) use ($chipBase) {
        $query = $chipBase->all();
        if ($value !== 'all') {
            $query['status'] = $value;
        }

        return route('clients.index', $query);
    };
    $chipUrl = function (string $key) {
        $keep = collect(request()->except([$key, 'page', 'saved_view']))
            ->reject(fn ($value) => $value === null || $value === '' || $value === 'all');

        return route('clients.index', $keep->all());
    };
    $clientCount = $clients->total();
    $firstClient = $clients->firstItem() ?? 0;
    $lastClient = $clients->lastItem() ?? 0;
@endphp

<div class="client client-index master-list">
    <div class="master-stats desktop-only" aria-label="Client overview">
        <div class="master-stat master-stat--flat blue">
            <span class="icon"><i class="fa-solid fa-building" aria-hidden="true"></i></span>
            <div><p class="master-stat-title">Total clients</p><p class="master-stat-value">{{ number_format($stats['total']) }}</p></div>
        </div>
        <div class="master-stat master-stat--flat orange">
            <span class="icon"><i class="fa-regular fa-clock" aria-hidden="true"></i></span>
            <div><p class="master-stat-title">Under review</p><p class="master-stat-value">{{ number_format($stats['under_review']) }}</p></div>
        </div>
        <div class="master-stat master-stat--flat teal">
            <span class="icon"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span>
            <div><p class="master-stat-title">Approved</p><p class="master-stat-value">{{ number_format($stats['approved']) }}</p></div>
        </div>
        <div class="master-stat master-stat--flat purple">
            <span class="icon"><i class="fa-solid fa-rotate" aria-hidden="true"></i></span>
            <div><p class="master-stat-title">Needs KYC action</p><p class="master-stat-value">{{ number_format($stats['revision'] + $stats['rejected']) }}</p></div>
        </div>
        <div class="master-stat master-stat--flat green">
            <span class="icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span>
            <div><p class="master-stat-title">Portal enabled</p><p class="master-stat-value">{{ number_format($stats['portal_enabled']) }}</p></div>
        </div>
    </div>

    <section class="master-card master-card--flat" aria-label="Search and filter clients">
        <div class="master-list-bar">
            <nav class="master-list-chips" aria-label="Quick client filters">
                <a class="master-list-chip {{ $status === 'all' ? 'is-active' : '' }}" href="{{ $statusUrl('all') }}">
                    All clients <span class="master-list-chip-count">{{ $stats['total'] }}</span>
                </a>
                <a class="master-list-chip {{ $status === 'draft' ? 'is-active' : '' }}" href="{{ $statusUrl('draft') }}">
                    Draft <span class="master-list-chip-count">{{ $stats['draft'] ?? 0 }}</span>
                </a>
                <a class="master-list-chip {{ $status === 'under_review' ? 'is-active' : '' }}" href="{{ $statusUrl('under_review') }}">
                    Under review <span class="master-list-chip-count">{{ $stats['under_review'] }}</span>
                </a>
                <a class="master-list-chip {{ $status === 'approved' ? 'is-active' : '' }}" href="{{ $statusUrl('approved') }}">
                    Approved <span class="master-list-chip-count">{{ $stats['approved'] }}</span>
                </a>
                <a class="master-list-chip {{ $status === 'revision' ? 'is-active' : '' }}" href="{{ $statusUrl('revision') }}">
                    Revision <span class="master-list-chip-count">{{ $stats['revision'] }}</span>
                </a>
                <a class="master-list-chip {{ $status === 'rejected' ? 'is-active' : '' }}" href="{{ $statusUrl('rejected') }}">
                    Rejected <span class="master-list-chip-count">{{ $stats['rejected'] }}</span>
                </a>
            </nav>

            <div class="master-list-saved" aria-label="Saved client views">
                @foreach ($savedViews as $view)
                    <span class="master-list-saved-chip">
                        <a href="{{ route('clients.index', ['saved_view' => $view->id]) }}"
                            title="{{ $view->is_shared ? 'Shared view' : 'Your view' }}">{{ $view->name }}</a>
                        @if ((int) $view->user_id === (int) auth()->id())
                            <form method="POST" action="{{ route('clients.saved-views.destroy', $view) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" title="Remove saved view" aria-label="Remove {{ $view->name }}">&times;</button>
                            </form>
                        @endif
                    </span>
                @endforeach
                <button type="button" class="master-btn master-btn-soft master-btn-sm" id="toggleSaveView">☆ Save this view</button>
                <form method="POST" action="{{ route('clients.saved-views.store', $baseFilters->all()) }}"
                    class="master-list-save-view" id="saveViewForm" hidden>
                    @csrf
                    <input class="master-input" name="name" placeholder="View name" maxlength="60" aria-label="Saved view name" required>
                    <label class="master-check"><input type="checkbox" name="is_shared" value="1"> Share</label>
                    <button class="master-btn master-btn-primary master-btn-sm" type="submit">Save</button>
                </form>
            </div>
        </div>

        <form method="GET" action="{{ route('clients.index') }}">
            <div class="master-filter-row">
                <label class="master-search">
                    <span aria-hidden="true"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input class="master-input" type="search" name="search" value="{{ $search }}" maxlength="150"
                        placeholder="Search company, brand, client ID, tax ID or contact" aria-label="Search clients">
                </label>
                <select class="master-select" name="status" aria-label="Filter by KYC status">
                    <option value="all">All statuses</option>
                    @foreach($statusOptions as $key => $label)
                        <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <select class="master-select" name="type" aria-label="Filter by client type">
                    <option value="all">All client types</option>
                    @foreach($typeOptions as $key => $label)
                        <option value="{{ $key }}" @selected($type === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="master-list-filter-group">
                    @if($filtersActive)
                        <a href="{{ route('clients.index') }}" class="master-btn master-btn-soft">Reset</a>
                    @endif
                    <button class="master-btn master-btn-primary" type="submit">
                        <i class="fa-solid fa-filter" aria-hidden="true"></i> Apply filters
                    </button>
                </div>
            </div>

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
                                aria-label="Remove the client type filter" title="Remove the client type filter">&times;</a>
                        </span>
                    @endif
                    <a class="master-list-applied-clear" href="{{ route('clients.index') }}">Clear all filters</a>
                </div>
            @endif
        </form>
    </section>

    <section class="master-card master-table-card master-card--flat" aria-label="Client records">
        <div class="master-list-toolbar">
            <p class="master-list-hint" title="Client profiles are shown newest first.">
                {{ $clientCount === 0 ? 'No matching clients' : 'Newest first · Showing '.$firstClient.'–'.$lastClient.' of '.$clientCount }}
            </p>
            <div class="master-list-toolbar-actions">
                <div class="master-list-density desktop-only" role="group" aria-label="Row density">
                    <button type="button" class="master-list-density-btn" data-density="comfortable" aria-pressed="true">Comfortable</button>
                    <button type="button" class="master-list-density-btn" data-density="compact" aria-pressed="false">Compact</button>
                </div>
            </div>
        </div>
        <div class="master-table-wrap">
            <table class="master-table client-table">
                <thead>
                    <tr>
                        <th scope="col">Client</th>
                        <th scope="col">Primary contact</th>
                        <th scope="col">KYC</th>
                        <th scope="col">Portal</th>
                        <th scope="col">Status</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($clients as $client)
                        @php
                            $statusClass = str_replace('_', '-', $client->status);
                            $portalUser = $portalInstalled ? $client->portalUser : null;
                            $portalEnabled = (bool) ($client->portal_enabled ?? false);
                            $initials = collect(explode(' ', trim($client->company_name)))
                                ->filter()
                                ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
                                ->take(2)
                                ->implode('');
                            $avatarTone = abs(crc32($client->company_name)) % 6;
                        @endphp
                        <tr class="client-row is-clickable" data-href="{{ route('clients.show', $client) }}">
                            <td data-label="Client">
                                <div class="client-table-identity">
                                    <span class="client-table-avatar client-avatar-tone-{{ $avatarTone }}" aria-hidden="true">{{ $initials }}</span>
                                    <div class="client-table-copy">
                                        <a class="client-table-name" href="{{ route('clients.show', $client) }}">{{ $client->company_name }}</a>
                                        <span class="client-table-meta">{{ $client->client_number }}@if($client->brand_name) <span aria-hidden="true">·</span> {{ $client->brand_name }}@endif</span>
                                        <span class="master-chip client-type-chip type-{{ str_replace('_', '-', $client->client_type) }}">{{ $client->typeLabel() }}</span>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Primary contact">
                                <div class="client-primary-contact">
                                    <strong>{{ $client->ceo_name ?: $client->account_person_name ?: 'No contact added' }}</strong>
                                    @if($client->ceo_email || $client->account_person_email)
                                        <a href="mailto:{{ $client->ceo_email ?: $client->account_person_email }}">{{ $client->ceo_email ?: $client->account_person_email }}</a>
                                    @endif
                                    @if($client->ceo_contact || $client->account_person_contact)
                                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $client->ceo_contact ?: $client->account_person_contact) }}">{{ $client->ceo_contact ?: $client->account_person_contact }}</a>
                                    @endif
                                </div>
                            </td>
                            <td data-label="KYC">
                                <span class="client-table-kyc-label">{{ $client->kyc_submitted_at ? 'Submitted' : 'Not submitted' }}</span>
                                <span class="master-sub">{{ $client->kyc_submitted_at ? $client->kyc_submitted_at->format('d M Y') : 'Awaiting client details' }}</span>
                                @if($client->kyc_sent_at)
                                    <span class="client-table-meta">Link sent {{ $client->kyc_sent_at->format('d M Y') }}</span>
                                @endif
                            </td>
                            <td data-label="Portal">
                                @if($portalInstalled)
                                    <span class="master-badge {{ $portalEnabled ? 'portal-enabled' : 'portal-disabled' }}">{{ $portalEnabled ? 'Enabled' : 'Disabled' }}</span>
                                    <span class="master-sub">{{ $portalUser?->username ?: 'No portal user' }}</span>
                                @else
                                    <span class="master-badge portal-disabled">Unavailable</span>
                                @endif
                            </td>
                            <td data-label="Status">
                                <span class="master-badge status-{{ $statusClass }}">{{ $client->statusLabel() }}</span>
                            </td>
                            <td data-label="Action" class="client-table-actions-cell">
                                <div class="master-row-actions">
                                    <div class="master-dropdown">
                                        <button type="button" class="master-dropdown-toggle"
                                            aria-label="Actions for {{ $client->company_name }}"
                                            aria-haspopup="true" aria-expanded="false">
                                            <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                        </button>
                                        <div class="master-dropdown-menu">
                                            <a href="{{ route('clients.show', $client) }}"><i class="fas fa-eye" aria-hidden="true"></i> View client</a>
                                            <a href="{{ route('clients.edit', $client) }}"><i class="fas fa-pen" aria-hidden="true"></i> Edit client</a>
                                            <button type="button" class="client-copy-kyc" data-kyc-url="{{ route('clients.publicKyc', $client->public_token) }}">
                                                <i class="fas fa-link" aria-hidden="true"></i> Copy KYC link
                                            </button>
                                            <button type="button" class="danger master-delete-btn"
                                                aria-label="Delete {{ $client->company_name }}" title="Delete client"
                                                data-name="{{ $client->company_name }}" data-delete-url="{{ route('clients.destroy', $client) }}">
                                                <i class="far fa-trash-alt" aria-hidden="true"></i> Delete client
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="master-list-empty">
                                    <span class="master-list-empty-icon"><i class="fa-regular fa-building" aria-hidden="true"></i></span>
                                    <h2 class="master-list-empty-title">{{ $filtersActive ? 'No clients match these filters' : 'Your client list is empty' }}</h2>
                                    <p class="master-list-empty-text">{{ $filtersActive ? 'Try a different search or clear the filters to see all client records.' : 'Create a client profile to keep company details, KYC and portal access together.' }}</p>
                                    <div class="master-list-empty-actions">
                                        @if($filtersActive)
                                            <a href="{{ route('clients.index') }}" class="master-btn master-btn-soft">Clear filters</a>
                                        @else
                                            <a href="{{ route('clients.create') }}" class="master-btn master-btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add first client</a>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-pagination :items="$clients" />
    </section>

    <div class="master-modal" id="quickClientModal" aria-hidden="true" aria-labelledby="quickClientTitle">
        <div class="master-modal-card client-modal-card" role="dialog" aria-modal="true" aria-labelledby="quickClientTitle" aria-describedby="quickClientDescription" tabindex="-1">
            <form method="POST" action="{{ route('clients.quickStore') }}" id="quickClientForm">
                @csrf
                <div class="master-modal-header">
                    <div class="master-modal-heading">
                        <span class="master-modal-icon"><i class="fa-solid fa-building" aria-hidden="true"></i></span>
                        <div>
                            <h2 class="master-modal-title" id="quickClientTitle">Quick add client</h2>
                            <p class="master-modal-subtitle" id="quickClientDescription">Create a profile with the essentials. Add the remaining details later.</p>
                        </div>
                    </div>
                    <button type="button" class="master-modal-close" id="closeQuickClientModal" aria-label="Close quick add dialog">&times;</button>
                </div>
                <div class="master-modal-body">
                    <div class="master-modal-grid">
                        <div class="master-field">
                            <label class="master-label" for="quick_company_name">Company name <span class="master-required" aria-hidden="true">*</span></label>
                            <input class="master-input" id="quick_company_name" name="company_name" required maxlength="255" autocomplete="organization" placeholder="e.g. Acme Packaging Ltd.">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quick_brand_name">Brand name</label>
                            <input class="master-input" id="quick_brand_name" name="brand_name" maxlength="255" placeholder="Optional">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quick_client_type">Client type</label>
                            <select class="master-select" id="quick_client_type" name="client_type">
                                @foreach($typeOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($key === 'customer')>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quick_ceo_name">Primary contact</label>
                            <input class="master-input" id="quick_ceo_name" name="ceo_name" maxlength="255" autocomplete="name" placeholder="Contact person">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quick_ceo_email">Contact email</label>
                            <input class="master-input" id="quick_ceo_email" type="email" name="ceo_email" maxlength="255" autocomplete="email" placeholder="name@company.com">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quick_ceo_contact">Contact phone</label>
                            <input class="master-input" id="quick_ceo_contact" type="tel" name="ceo_contact" maxlength="40" autocomplete="tel" placeholder="+91">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quick_gstin">GSTIN</label>
                            <input class="master-input" id="quick_gstin" name="gstin" maxlength="30" autocomplete="off">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quick_pan">PAN</label>
                            <input class="master-input" id="quick_pan" name="pan" maxlength="20" autocomplete="off">
                        </div>
                    </div>
                </div>
                <div class="master-modal-footer">
                    <button type="button" class="master-btn master-btn-light" id="cancelQuickClientModal">Cancel</button>
                    <button type="submit" class="master-btn master-btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Create client</button>
                </div>
            </form>
        </div>
    </div>

    <div class="master-modal" id="deleteClientModal" aria-hidden="true" aria-labelledby="deleteClientTitle">
        <div class="master-modal-card client-delete-dialog" role="dialog" aria-modal="true" aria-labelledby="deleteClientTitle" aria-describedby="deleteClientDesc" tabindex="-1">
            <div class="master-modal-header">
                <div class="master-modal-heading">
                    <span class="master-modal-icon client-delete-icon"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></span>
                    <div>
                        <h2 class="master-modal-title" id="deleteClientTitle">Delete client?</h2>
                        <p class="master-modal-subtitle">This action cannot be undone.</p>
                    </div>
                </div>
                <button type="button" class="master-modal-close" id="closeDeleteClientModal" aria-label="Close delete confirmation">&times;</button>
            </div>
            <div class="master-modal-body">
                <p id="deleteClientDesc" class="client-delete-description">Are you sure you want to delete this client?</p>
            </div>
            <form method="POST" action="" id="deleteClientForm">
                @csrf
                @method('DELETE')
                <div class="master-modal-footer">
                    <button type="button" class="master-btn master-btn-light" id="cancelDeleteClientModal">Cancel</button>
                    <button type="submit" class="master-btn master-btn-danger"><i class="fa-regular fa-trash-can" aria-hidden="true"></i> Delete client</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
    <script src="{{ $assetVer('assets/js/clients.js') }}"></script>
@endpush
@endsection
