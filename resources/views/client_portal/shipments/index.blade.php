@extends('client_portal.layouts.app')

@section('title', 'Shipments')
@section('page-title', 'Shipments')

@section('content')
<div class="cp-page-head"><div><p class="cp-eyebrow">Logistics</p><h1>Shipments</h1><p>Track routes, delivery dates, logistics partners and status updates shared with your account.</p></div></div>
<section class="cp-card cp-filter-card"><form method="GET" action="{{ route('client-portal.shipments.index') }}"><div class="core-filter-toolbar"><div class="master-field"><label class="master-label" for="shipment-search">Search shipments</label><input class="master-input" id="shipment-search" name="search" value="{{ $search }}" placeholder="Shipment, tracking number or partner"></div><div class="cp-filter-actions"><a class="master-btn master-btn-soft" href="{{ route('client-portal.shipments.index') }}">Reset</a><button class="master-btn master-btn-primary" type="submit">Search</button></div></div></form></section>
<section class="cp-card cp-table-card"><div class="cp-table-wrap ui-mobile-cards"><table class="cp-table"><thead><tr><th>Shipment</th><th>Route</th><th class="ui-mobile-secondary">Pickup / drop</th><th class="ui-mobile-secondary">Logistics</th><th>ETA</th><th>Status</th><th>Action</th></tr></thead><tbody>
@forelse($shipments as $shipment)
<tr>
    <td data-label="Shipment"><a class="cp-record-link" href="{{ route('client-portal.shipments.show', $shipment) }}"><strong>{{ $shipment->shipment_number }}</strong></a><span class="cp-table-sub">{{ $shipment->identity_name }}</span></td>
    <td data-label="Route"><strong>{{ $shipment->from_name ?: 'Origin' }} → {{ $shipment->to_name ?: 'Destination' }}</strong><span class="cp-table-sub">{{ $shipment->from_city ?: '—' }} to {{ $shipment->to_city ?: '—' }}</span></td>
    <td data-label="Pickup / drop" class="ui-mobile-secondary">{{ optional($shipment->pickup_date)->format('d M Y') ?: '—' }}<span class="cp-table-sub">Drop: {{ optional($shipment->drop_date)->format('d M Y') ?: 'Not set' }}</span></td>
    <td data-label="Logistics" class="ui-mobile-secondary">{{ $shipment->logistic_partner ?: 'Not assigned' }}<span class="cp-table-sub">{{ $shipment->tracking_number ?: 'No tracking number' }}</span></td>
    <td data-label="ETA">@if($shipment->eta_date)<span class="ship-eta ship-eta-{{ $shipment->etaState() }}">{{ $shipment->eta_date->format('d M') }}</span><span class="cp-table-sub">{{ $shipment->etaLabel() }}</span>@else<span class="cp-muted">Not set</span>@endif</td>
    <td data-label="Status"><span class="cp-badge status-{{ $shipment->status }}">{{ $shipment->statusLabel() }}</span></td>
    <td data-label="Action"><a class="master-btn master-btn-soft master-btn-sm" href="{{ route('client-portal.shipments.show', $shipment) }}">Track</a></td>
</tr>
@empty<tr><td colspan="7"><div class="cp-empty cp-empty-spacious"><i class="fa-solid fa-truck-fast"></i><strong>No shipments found</strong><span>Published shipments matching your search will appear here.</span></div></td></tr>@endforelse
</tbody></table></div><x-pagination :items="$shipments" /></section>

@endsection
