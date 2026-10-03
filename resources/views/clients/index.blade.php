@extends('layouts.app')

@section('title', 'Clients')
@section('page-title', 'Clients')

@section('page-actions')
    <button type="button" class="master-btn master-btn-soft" id="openQuickClientModal">
        <i class="fa-solid fa-bolt" aria-hidden="true"></i> Quick add
    </button>
    <a href="{{ route('clients.create') }}" class="master-btn master-btn-primary">
        <i class="fa-solid fa-plus" aria-hidden="true"></i> Add client
    </a>
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/clients.css') }}">
@endpush

@php
    $filtersActive = filled($search) || $status !== 'all' || $type !== 'all';
    $chipBase = request()->except(['status', 'page']);
    $statusUrl = fn ($value) => route('clients.index', array_merge($chipBase, ['status' => $value]));
    $clientCount = $clients->total();
    $firstClient = $clients->firstItem() ?? 0;
    $lastClient = $clients->lastItem() ?? 0;
@endphp

<div class="client client-index master-list">
    <div class="client-index-intro">
        <div>
            <h1>Client directory</h1>
            <p>Manage company details, KYC reviews and client portal access in one place.</p>
        </div>
        <span class="client-index-count"><i class="fa-solid fa-building" aria-hidden="true"></i> {{ number_format($stats['total']) }} {{ \Illuminate\Support\Str::plural('client', $stats['total']) }}</span>
    </div>

    <section class="master-stats client-stat-grid" aria-label="Client overview">
        <a class="master-stat master-stat--flat blue client-stat" href="{{ $statusUrl('all') }}">
            <span class="client-stat-icon"><i class="fa-solid fa-building" aria-hidden="true"></i></span>
            <span><span class="master-stat-title">Total clients</span><strong class="master-stat-value">{{ number_format($stats['total']) }}</strong></span>
        </a>
        <a class="master-stat master-stat--flat orange client-stat" href="{{ $statusUrl('under_review') }}">
            <span class="client-stat-icon"><i class="fa-regular fa-clock" aria-hidden="true"></i></span>
            <span><span class="master-stat-title">Under review</span><strong class="master-stat-value">{{ number_format($stats['under_review']) }}</strong></span>
        </a>
        <a class="master-stat master-stat--flat teal client-stat" href="{{ $statusUrl('approved') }}">
            <span class="client-stat-icon"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span>
            <span><span class="master-stat-title">Approved</span><strong class="master-stat-value">{{ number_format($stats['approved']) }}</strong></span>
        </a>
        <a class="master-stat master-stat--flat purple client-stat" href="{{ $statusUrl('revision') }}">
            <span class="client-stat-icon"><i class="fa-solid fa-rotate" aria-hidden="true"></i></span>
            <span><span class="master-stat-title">Revision requested</span><strong class="master-stat-value">{{ number_format($stats['revision']) }}</strong></span>
        </a>
        <a class="master-stat master-stat--flat red client-stat" href="{{ $statusUrl('rejected') }}">
            <span class="client-stat-icon"><i class="fa-solid fa-circle-xmark" aria-hidden="true"></i></span>
            <span><span class="master-stat-title">Rejected</span><strong class="master-stat-value">{{ number_format($stats['rejected']) }}</strong></span>
        </a>
        <div class="master-stat master-stat--flat green client-stat client-stat--static">
            <span class="client-stat-icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span>
            <span><span class="master-stat-title">Portal enabled</span><strong class="master-stat-value">{{ number_format($stats['portal_enabled']) }}</strong></span>
        </div>
    </section>

    <section class="master-card master-card--flat client-filter-card" aria-label="Search and filter clients">
        <div class="master-list-bar">
            <nav class="master-list-chips" aria-label="Quick client filters">
                <a class="master-list-chip {{ $status === 'all' ? 'is-active' : '' }}" href="{{ $statusUrl('all') }}">All clients <span class="master-list-chip-count">{{ $stats['total'] }}</span></a>
                <a class="master-list-chip {{ $status === 'under_review' ? 'is-active' : '' }}" href="{{ $statusUrl('under_review') }}">Under review <span class="master-list-chip-count">{{ $stats['under_review'] }}</span></a>
                <a class="master-list-chip {{ $status === 'approved' ? 'is-active' : '' }}" href="{{ $statusUrl('approved') }}">Approved <span class="master-list-chip-count">{{ $stats['approved'] }}</span></a>
                <a class="master-list-chip {{ $status === 'rejected' ? 'is-active' : '' }}" href="{{ $statusUrl('rejected') }}">Rejected <span class="master-list-chip-count">{{ $stats['rejected'] }}</span></a>
            </nav>
        </div>

        <form method="GET" action="{{ route('clients.index') }}" class="client-filter-form">
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
                        <a href="{{ route('clients.index') }}" class="master-btn master-btn-soft">Clear</a>
                    @endif
                    <button class="master-btn master-btn-primary" type="submit">
                        <i class="fa-solid fa-filter" aria-hidden="true"></i> Apply filters
                    </button>
                </div>
            </div>
            @if($filtersActive)
                <div class="master-list-applied" aria-label="Active filters">
                    <span class="master-list-applied-title">Showing results for</span>
                    @if(filled($search))
                        <span class="master-list-applied-chip"><span class="master-list-applied-key">Search</span><span class="master-list-applied-value">{{ $search }}</span></span>
                    @endif
                    @if($status !== 'all')
                        <span class="master-list-applied-chip"><span class="master-list-applied-key">Status</span><span class="master-list-applied-value">{{ $statusOptions[$status] ?? $status }}</span></span>
                    @endif
                    @if($type !== 'all')
                        <span class="master-list-applied-chip"><span class="master-list-applied-key">Type</span><span class="master-list-applied-value">{{ $typeOptions[$type] ?? $type }}</span></span>
                    @endif
                    <a class="master-list-applied-clear" href="{{ route('clients.index') }}">Clear all</a>
                </div>
            @endif
        </form>
    </section>

    <section class="master-card master-table-card master-card--flat client-table-card" aria-label="Client records">
        <div class="master-list-toolbar client-list-toolbar">
            <p class="master-list-hint">{{ $clientCount === 0 ? 'No matching clients' : "Showing {$firstClient}–{$lastClient} of {$clientCount} clients" }}</p>
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
                        <th scope="col" class="client-actions-heading">Actions</th>
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
                            $avatarColors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'];
                            $avatarColor = $avatarColors[abs(crc32($client->company_name)) % count($avatarColors)];
                        @endphp
                        <tr>
                            <td data-label="Client">
                                <div class="client-table-identity">
                                    <span class="client-table-avatar" style="--client-avatar-color: {{ $avatarColor }}" aria-hidden="true">{{ $initials }}</span>
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
                            <td data-label="Actions" class="client-table-actions-cell">
                                <div class="master-row-actions client-row-actions">
                                    <a href="{{ route('clients.show', $client) }}" class="master-icon-btn green" aria-label="View {{ $client->company_name }}" title="View client"><i class="fa-regular fa-eye" aria-hidden="true"></i></a>
                                    <a href="{{ route('clients.edit', $client) }}" class="master-icon-btn" aria-label="Edit {{ $client->company_name }}" title="Edit client"><i class="fa-solid fa-pen" aria-hidden="true"></i></a>
                                    <button type="button" class="master-icon-btn client-copy-kyc" data-kyc-url="{{ route('clients.publicKyc', $client->public_token) }}" aria-label="Copy KYC link for {{ $client->company_name }}" title="Copy KYC link"><i class="fa-solid fa-link" aria-hidden="true"></i></button>
                                    <button type="button" class="master-icon-btn danger master-delete-btn" aria-label="Delete {{ $client->company_name }}" title="Delete client" data-name="{{ $client->company_name }}" data-delete-url="{{ route('clients.destroy', $client) }}"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>
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
        @if($clients->hasPages())
            <div class="client-pagination">
                <x-pagination :items="$clients" />
            </div>
        @endif
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
