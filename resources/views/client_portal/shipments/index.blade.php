@extends('client_portal.layouts.app')

@section('title', 'Shipments')
@section('page-title', 'Shipments')

@section('content')
<div class="master-header" style="padding:5px;margin-bottom:15px;">
    <div>
        <h1>Shipments</h1>
        <p style="font-size:16px;font-weight:500;">Track shipments published by MissPack for your account.</p>
    </div>
</div>
<div class="cp-card" style="padding:18px;margin-bottom:18px;">
    <form method="GET" class="cp-form-grid">
        <div class="cp-field">
            <label>Search</label>
            <input name="search" value="{{ $search }}" placeholder="Search shipment, tracking, partner...">
        </div>
        <div style="display:flex;align-items:end;gap:10px;">
            <button class="cp-btn cp-btn-primary">Filter</button>
            <a class="cp-btn cp-btn-light" href="{{ route('client-portal.shipments.index') }}" style="padding:8px 15px;">Reset</a>
        </div>
    </form>
</div>
<div class="cp-card">
    <div class="cp-table-wrap">
        <table class="cp-table">
            <thead>
                <tr>
                    <th>Shipment</th>
                    <th>Route</th>
                    <th>Pickup / Drop</th>
                    <th>Logistic</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($shipments as $shipment)
                    <tr>
                        <td><strong>{{ $shipment->shipment_number }}</strong><span class="cp-muted"
                                style="display:block;">{{ $shipment->identity_name }}</span></td>
                        <td>{{ $shipment->from_name ?: '-' }} → {{ $shipment->to_name ?: '-' }}<span class="cp-muted"
                                style="display:block;">{{ $shipment->from_city ?: '-' }} to
                                {{ $shipment->to_city ?: '-' }}</span></td>
                        <td>{{ optional($shipment->pickup_date)->format('d M Y') ?: '-' }}<span class="cp-muted"
                                style="display:block;">Drop:
                                {{ optional($shipment->drop_date)->format('d M Y') ?: '-' }}</span></td>
                        <td>{{ $shipment->logistic_partner ?: '-' }}<span class="cp-muted"
                                style="display:block;">{{ $shipment->tracking_number ?: 'No tracking' }}</span></td>
                        <td><span
                                class="cp-badge status-{{ $shipment->status }}">{{ $shipment->statusLabel() }}</span>
                        </td>
                        <td><a class="cp-btn cp-btn-soft cp-btn-sm"
                                href="{{ route('client-portal.shipments.show', $shipment->id) }}">Open</a></td>
                </tr>@empty<tr>
                        <td colspan="6">
                            <div class="cp-empty">No public shipments found.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-pagination :items="$shipments" />
</div>

@endsection
