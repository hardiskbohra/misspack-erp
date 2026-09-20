@extends('layouts.app')

@section('page-title', 'Client Management')

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/clients.css') }}">
@endpush

@php
    $portalInstalled = class_exists(\App\Models\ClientPortalUser::class) && \Illuminate\Support\Facades\Schema::hasTable('client_portal_users');
    $portalEnabledCount = \Illuminate\Support\Facades\Schema::hasColumn('clients', 'portal_enabled') ? \App\Models\Client::where('portal_enabled', true)->count() : 0;
    $portalRouteExists = \Illuminate\Support\Facades\Route::has('clients.portal.show');
@endphp

<div class="client client-index">

    <div class="master-stats">
        <div class="master-stat blue"><span class="icon">🏢</span>
            <div>
                <p class="master-stat-title">Total Clients</p>
                <p class="master-stat-value">{{ $stats['total'] }}</p>
            </div>
        </div>
        <div class="master-stat purple"><span class="icon">⌛</span>
            <div>
                <p class="master-stat-title">Under Review</p>
                <p class="master-stat-value">{{ $stats['under_review'] }}</p>
            </div>
        </div>
        <div class="master-stat teal"><span class="icon">✓</span>
            <div>
                <p class="master-stat-title">Approved</p>
                <p class="master-stat-value">{{ $stats['approved'] }}</p>
            </div>
        </div>
        <div class="master-stat orange"><span class="icon">↻</span>
            <div>
                <p class="master-stat-title">Revision / Rejected</p>
                <p class="master-stat-value">{{ $stats['revision'] + $stats['rejected'] }}</p>
            </div>
        </div>
        <div class="master-stat green">
            <span class="icon">🔐</span>
            <div>
                <p class="master-stat-title">Portal Enabled</p>
                <p class="master-stat-value">{{ $portalEnabledCount }}</p>
            </div>
        </div>
    </div>

    <div class="master-card">
        <form method="GET" action="{{ route('clients.index') }}">
            <div class="master-toolbar">
                <div class="master-search"><span>⌕</span><input class="master-input" type="text" name="search"
                        value="{{ $search }}" placeholder="Search company, brand, GSTIN, PAN, contact..."></div>
                <div class="master-actions-top"><button type="button" class="master-btn master-btn-primary"
                        id="openQuickClientModal">+ Quick Client</button><a href="{{ route('clients.create') }}"
                        class="master-btn master-btn-soft">Detailed Form</a></div>
            </div>
            <div class="master-filter-row" style="display: none;">
                <div>
                    <select class="master-select" name="status">
                        <option value="all">All Status</option>@foreach($statusOptions as $key => $label)<option
                        value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                
                <button class="master-btn master-btn-primary" type="submit">Filter</button><a
                    class="master-btn master-btn-light" href="{{ route('clients.index') }}">Reset</a>
            </div>
        </form>
    </div>

    <div class="master-card master-table-card">
        <div class="master-table-wrap">
            <table class="master-table">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>CEO / Director</th>
                        <th>KYC</th>
                        <th>Portal</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($clients as $client)
                    @php
                        $statusClass = str_replace('_', '-', $client->status);
                        $portalUser = $portalInstalled ? \App\Models\ClientPortalUser::where('client_id', $client->id)->first() : null;
                        $portalEnabled = (bool) ($client->portal_enabled ?? false);
                    @endphp
                    <tr style="line-height:1.5">
                        <td>
                            <div class="user-cell">
                                <div class="u-avatar"
                                     style="background: {{ ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'][crc32($client->company_name) % 6] }};">
                                    {{ collect(explode(' ', trim($client->company_name)))
                                        ->map(fn($word) => strtoupper(substr($word, 0, 1)))
                                        ->take(2)
                                        ->implode('') }}
                                </div>
                        
                                <div>
                                    <div class="master-sub">{{ $client->client_number }}</div>
                                    <div class="master-id">
                                        {{ \Illuminate\Support\Str::limit($client->company_name ?: '-', 25, '...') }}
                                    </div>
                                    <div class="master-sub">Brand: {{ $client->brand_name ?: '-' }}</div>
                                </div>
                            </div>
                        </td>
                        
                        <td>{{ $client->ceo_name ?: '-' }}<span
                                class="master-sub">{{ $client->ceo_email ?: '-' }}</span><span
                                class="master-sub">{{ $client->ceo_contact ?: '-' }}</span></td>
                        </td>
                        <td>{{ $client->kyc_submitted_at ? $client->kyc_submitted_at->format('d M Y') : 'Not submitted' }}<span
                                class="master-sub">Sent:
                                {{ $client->kyc_sent_at ? $client->kyc_sent_at->format('d M Y') : '-' }}</span></td>
                        <td>
                            <span class="master-badge {{ $portalEnabled ? 'portal-enabled' : 'portal-disabled' }}">{{ $portalEnabled ? 'Enabled' : 'Disabled' }}</span>
                            <span class="master-sub">{{ $portalUser ? $portalUser->username : 'No credentials' }}</span>
                        </td>
                        <td><span class="master-badge status-{{ $statusClass }}">{{ $client->statusLabel() }}</span>
                        </td>
                        <td>
                            <div class="master-row-actions"><a href="{{ route('clients.show', $client) }}"
                                    class="master-icon-btn green" title="View">👁</a><a
                                    href="{{ route('clients.edit', $client) }}" class="master-icon-btn"
                                    title="Edit">✎</a><button type="button" class="master-icon-btn"
                                    title="Copy KYC link"
                                    onclick="copyClientKycLink('{{ route('clients.publicKyc', $client->public_token) }}')">🔗</button><button
                                    type="button" class="master-icon-btn danger master-delete-btn" title="Delete"
                                    data-id="{{ $client->id }}" data-name="{{ $client->company_name }}"
                                    data-delete-url="{{ route('clients.destroy', $client) }}">🗑</button></div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8">
                            <div class="master-empty">No clients found. Create your first client.</div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination :items="$clients" />

    </div>

    <div class="master-modal" id="quickClientModal" aria-hidden="true">
        <div class="master-modal-card" role="dialog" aria-modal="true" aria-labelledby="quickClientTitle">
            <form method="POST" action="{{ route('clients.quickStore') }}">
                @csrf
                <div class="master-modal-header">
                    <div class="master-modal-heading"><span class="master-modal-icon">🏢</span>
                        <div>
                            <h3 class="master-modal-title" id="quickClientTitle">Quick Client</h3>
                            <p class="master-modal-subtitle">Create client with important details only</p>
                        </div>
                    </div><button type="button" class="master-modal-close" id="closeQuickClientModal">×</button>
                </div>
                <div class="master-modal-body">
                    <div class="master-modal-grid">
                        <div class="master-field"><label class="master-label">Company Name <span
                                    class="master-required">*</span></label><input class="master-input"
                                name="company_name" required placeholder="Company name"></div>
                        <div class="master-field"><label class="master-label">Brand Name</label><input
                                class="master-input" name="brand_name" placeholder="Brand name"></div>
                    </div>
                    <div class="master-modal-three-grid">
                        <div class="master-field"><label class="master-label">CEO / Director Name</label><input
                                class="master-input" name="ceo_name" placeholder="Name"></div>
                        <div class="master-field"><label class="master-label">CEO / Director Email</label><input
                                class="master-input" type="email" name="ceo_email" placeholder="Email"></div>
                        <div class="master-field"><label class="master-label">CEO / Director Contact</label><input
                                class="master-input" name="ceo_contact" placeholder="Mobile"></div>
                    </div>
                    <div class="master-modal-three-grid">
                        <div class="master-field"><label class="master-label">Account Person Name</label><input
                                class="master-input" name="account_person_name" placeholder="Name"></div>
                        <div class="master-field"><label class="master-label">Account Person Email</label><input
                                class="master-input" type="email" name="account_person_email" placeholder="Email"></div>
                        <div class="master-field"><label class="master-label">Account Person Contact</label><input
                                class="master-input" name="account_person_contact" placeholder="Mobile"></div>
                    </div>
                    <div class="master-modal-grid" style="margin-top:12px;">
                        <div class="master-field"><label class="master-label">GSTIN</label><input class="master-input"
                                name="gstin"></div>
                        <div class="master-field"><label class="master-label">PAN</label><input class="master-input"
                                name="pan"></div>
                    </div>
                </div>
                <div class="master-modal-footer"><button type="button" class="master-btn master-btn-light"
                        id="cancelQuickClientModal">Cancel</button><button type="submit"
                        class="master-btn master-btn-primary">Create Client</button></div>
            </form>
        </div>
    </div>

    <div class="master-modal" id="deleteClientModal" aria-hidden="true">
        <div class="master-modal-card" style="max-width: 440px;" role="dialog" aria-modal="true"
            aria-labelledby="deleteClientTitle">
            <div class="master-modal-header">
                <div class="master-modal-heading"><span class="master-modal-icon"
                        style="background:#fff0f4;color:var(--master-red);">🗑</span>
                    <div>
                        <h3 class="master-modal-title" id="deleteClientTitle">Delete Client</h3>
                        <p class="master-modal-subtitle">This action cannot be undone</p>
                    </div>
                </div><button type="button" class="master-modal-close" id="closeDeleteClientModal">×</button>
            </div>
            <div class="master-modal-body">
                <p id="deleteClientDesc" style="margin:0;font-weight:700;color:#536079;">Are you sure you want to delete
                    this client?</p>
            </div>
            <form method="POST" action="" id="deleteClientForm">@csrf @method('DELETE')
                <div class="master-modal-footer"><button type="button" class="master-btn master-btn-light"
                        id="cancelDeleteClientModal">Cancel</button><button type="submit"
                        class="master-btn master-btn-danger">Delete Client</button></div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
    <script src="{{ asset('assets/js/clients.js') }}"></script>
@endpush
@endsection