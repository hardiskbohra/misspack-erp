@extends('layouts.app')

@section('title', 'Client Portal Support')

@section('content')
<div class="cp-office-support">
    <div class="master-header">
        <div>
            <p class="master-eyebrow">Client workspace · {{ $client->client_number }}</p>
            <h1>Support inbox</h1>
            <p>Requests raised by {{ $client->company_name }} through the client portal.</p>
        </div>
        <div class="master-actions"><a class="master-btn master-btn-light" href="{{ route('clients.portal.show', $client) }}"><i class="fa-solid fa-arrow-left"></i> Portal settings</a></div>
    </div>

    <div class="master-card cp-office-support-card">
        <form method="GET" action="{{ route('clients.portal.support.index', $client) }}" class="cp-office-support-filter">
            <div class="core-filter-toolbar">
                <x-filter-trigger drawer="clientSupportFiltersDrawer" :count="($status !== 'all' ? 1 : 0)" />
            </div>
            <x-drawer id="clientSupportFiltersDrawer" title="Filter support requests" eyebrow="Client support"
                subtitle="Narrow this client's support inbox by request status." size="medium">
                <section class="core-drawer-section">
                    <h3 class="core-drawer-section-title">Request status</h3>
                    <div class="core-drawer-fields">
                        <div class="master-field">
                            <label class="master-label" for="support-status">Status</label>
                            <select class="master-select" id="support-status" name="status">
                                <option value="all">All requests</option>
                                @foreach($statusOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </section>
                <x-slot:footer>
                    <a class="master-btn master-btn-soft" href="{{ route('clients.portal.support.index', $client) }}">Reset</a>
                    <button class="master-btn master-btn-primary" type="submit">Apply filters</button>
                </x-slot:footer>
            </x-drawer>
        </form>
        <div class="master-table-wrap">
            <table class="master-table">
                <thead><tr><th>Request</th><th>Portal user</th><th>Topic</th><th>Messages</th><th>Status</th><th>Updated</th><th></th></tr></thead>
                <tbody>
                    @forelse($conversations as $conversation)
                        <tr>
                            <td><strong>{{ $conversation->subject }}</strong>@if($conversation->unread_client_messages_count)<span class="cp-office-unread">{{ $conversation->unread_client_messages_count }} new</span>@endif</td>
                            <td>{{ $conversation->portalUser?->displayName() ?: 'Client team' }}</td>
                            <td>{{ $conversation->categoryLabel() }}</td>
                            <td>{{ $conversation->messages_count }}</td>
                            <td><span class="cp-office-status cp-office-status-{{ $conversation->status }}">{{ $conversation->statusLabel() }}</span></td>
                            <td>{{ optional($conversation->last_message_at)->diffForHumans() ?: '—' }}</td>
                            <td><a class="master-btn master-btn-soft master-btn-sm" href="{{ route('clients.portal.support.show', [$client, $conversation->id]) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="master-empty">No client support requests yet.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-pagination :items="$conversations" />
    </div>
</div>

@push('styles')
<link rel="stylesheet" href="{{ $assetVer('assets/css/client-portal-workspace.css') }}">
@endpush
@endsection
