@extends('layouts.app')

@section('page-title', 'Shipment Tracking')

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/shipments.css') }}">
@endpush

<div class="ship ship-index">

    <div class="master-stats desktop-only">
        <div class="master-stat blue"><span class="icon">⇄</span><div><p class="master-stat-title">Total Shipments</p><p class="master-stat-value">{{ $stats['total'] }}</p></div></div>
        <div class="master-stat purple"><span class="icon">✈</span><div><p class="master-stat-title">In Transit</p><p class="master-stat-value">{{ $stats['in_transit'] + $stats['out_for_delivery'] }}</p></div></div>
        <div class="master-stat teal"><span class="icon">✓</span><div><p class="master-stat-title">Delivered</p><p class="master-stat-value">{{ $stats['delivered'] }}</p></div></div>
        <div class="master-stat orange"><span class="icon">!</span><div><p class="master-stat-title">Hold / Delayed</p><p class="master-stat-value">{{ $stats['custom_hold'] + $stats['delayed'] }}</p></div></div>
    </div>

    <div class="master-card">
        <form method="GET" action="{{ route('shipments.index') }}">

            <div class="master-filter-row" style="padding-top:22px;">
                <div class="master-search">
                    <span>⌕</span>
                    <input class="master-input" type="text" name="search" value="{{ $search }}" placeholder="Search shipment, tracking, BOE, partner...">
                </div>
                <select class="master-select" name="status">
                    <option value="all">All Status</option>
                    @foreach($statusOptions as $key => $label)
                        <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <select class="master-select desktop-only" name="currency" hidden>
                    <option value="all">All Currency</option>
                    @foreach($currencyOptions as $key => $label)
                        <option value="{{ $key }}" @selected($currency === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <input class="master-input desktop-only" type="date" name="from_date" value="{{ $fromDate }}" title="Pickup date">
                <button class="master-btn master-btn-primary" type="submit">Filter</button>
                <a class="master-btn master-btn-soft" href="{{ route('shipments.index') }}">Reset</a>
                <button type="button" class="master-btn master-btn-primary" id="openQuickShipmentModal">+ Quick Shipment</button>
            </div>
        </form>
    </div>

    <div class="master-card master-table-card">
        <div class="master-table-wrap">
            <table class="master-table">
                <thead>
                    <tr>
                        <th>Pickup / Drop</th>
                        <th>Shipment</th>
                        <th>Route</th>
                        <th>Logistic</th>
                        <th>Charges</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($shipments as $shipment)
                        @php($statusClass = str_replace('_', '-', $shipment->status))
                        <tr style="line-height:1.5;">
                            <td style="font-weight:500">
                                {{ $shipment->pickup_date ? $shipment->pickup_date->format('d M') : '-' }}
                                <span class="master-sub">
                                    @if($shipment->project)
                                        <span class="master-badge type-export tooltip-container" style="font-size:9px;border:1px solid grey">P
                                            <span class="tooltip-text">{{ $shipment->project->project_number }} - {{ $shipment->project->name }}</span>
                                        </span>
                                    @endif
                                    @if($shipment->client)
                                        <span class="master-badge type-import tooltip-container" style="font-size:9px;border:1px solid grey">C
                                            <span class="tooltip-text">{{ $shipment->client->company_name }}</span>
                                        </span>
                                    @endif
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('shipments.show', $shipment) }}" style="text-decoration:none;">
                                    <span class="master-sub">{{ $shipment->shipment_number }} &nbsp;
                                    @if($shipment->shipment_label)
                                        <span class="master-badge {{ $shipment->labelColorClass() }}" style="margin-top:5px;font-size:9px;">{{ $shipment->shipment_label }}</span>
                                    @endif
                                    </span>
                                    <span class="master-id">{{ $shipment->identity_name }}</span>
                                </a>
                            </td>
                            <td class="master-route">
                                <strong style="font-weight:500">{{ $shipment->from_name ?: 'Origin' }} → {{ $shipment->to_name ?: 'Destination' }}</strong>
                                <span class="master-sub desktop-only">{{ $shipment->from_city ?: '-' }} to {{ $shipment->to_city ?: '-' }}</span>
                            </td>
                            <td style="font-weight:500">
                                {{ $shipment->logistic_partner ?: '-' }}
                                <span class="master-sub">{{ $shipment->tracking_number ?: 'No tracking' }}</span>
                            </td>
                            <td style="font-weight:500">
                                {{ $shipment->currency ?: '' }} {{ $shipment->shipment_cost ?: '0' }}
                                <span class="master-sub">{{ $shipment->cost_borne_by ?: '' }}</span>
                            </td>
                            <td style="font-weight:500"><span class="master-badge status-{{ $statusClass }}">{{ $shipment->statusLabel() }}</span></td>
                            <td style="font-weight:500">
                                <div class="master-row-actions">

                                    <div class="master-dropdown">
                                        <button type="button" class="master-dropdown-toggle">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                
                                        <div class="master-dropdown-menu">
                                
                                            <a href="{{ route('shipments.show', $shipment) }}">
                                                <i class="fas fa-eye"></i>
                                                View Shipment
                                            </a>
                                
                                            <a href="{{ route('shipments.edit', $shipment) }}">
                                                <i class="fas fa-pen"></i>
                                                Edit Shipment
                                            </a>
                                            
                                            <button type="button" onclick="copyShipmentLink('{{ route('shipments.publicTrack', $shipment->public_token) }}')">Public Link</button>
                                
                                            <form method="POST"
                                                  action="{{ route('shipments.destroy', $shipment) }}"
                                                  onsubmit="return confirm('Delete this shipment?')">
                                
                                                @csrf
                                                @method('DELETE')
                                
                                                <button type="submit" class="danger">
                                                    <i class="fas fa-trash"></i>
                                                    Delete Shipment
                                                </button>
                                
                                            </form>
                                
                                        </div>
                                    </div>
                                
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="master-empty">No shipments found. Create your first shipment.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination :items="$shipments" />

    </div>

    <div class="master-modal" id="quickShipmentModal" aria-hidden="true">
        <div class="master-modal-card" role="dialog" aria-modal="true" aria-labelledby="quickShipmentTitle">
            <form method="POST" action="{{ route('shipments.quickStore') }}">
                @csrf
                <div class="master-modal-header">
                    <div class="master-modal-heading">
                        <span class="master-modal-icon">⇄</span>
                        <div>
                            <h3 class="master-modal-title" id="quickShipmentTitle">Quick Shipment</h3>
                            <p class="master-modal-subtitle desktop-only">Create shipment with essential details only</p>
                        </div>
                    </div>
                    <button type="button" class="master-modal-close" id="closeQuickShipmentModal">×</button>
                </div>
                <div class="master-modal-body">
                    <div class="master-modal-grid">
                        <div class="master-field full">
                            <label class="master-label">Shipment Identity Name <span class="master-required">*</span></label>
                            <input class="master-input" name="identity_name" placeholder="e.g. Green pigment samples from China" required>
                        </div>
                        <div class="master-field">
                            <label class="master-label">Shipment Type <span class="master-required">*</span></label>
                            <select class="master-select" name="shipment_type" required>
                                @foreach($typeOptions as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field"><label class="master-label">Pickup Date</label><input class="master-input" type="date" name="pickup_date" value="{{ now()->toDateString() }}"></div>
                        <div class="master-field"><label class="master-label">From Name</label><input class="master-input" name="from_name" placeholder="Shipper name"></div>
                        <div class="master-field"><label class="master-label">To Name</label><input class="master-input" name="to_name" placeholder="Receiver name"></div>
                        <div class="master-field"><label class="master-label">Logistic Partner</label><input class="master-input" name="logistic_partner" placeholder="DHL / FedEx / BlueDart"></div>
                        <div class="master-field"><label class="master-label">Tracking Number</label><input class="master-input" name="tracking_number" placeholder="Tracking number"></div>
                    </div>
                </div>
                <div class="master-modal-footer">
                    <button type="button" class="master-btn master-btn-light" id="cancelQuickShipmentModal">Cancel</button>
                    <button type="submit" class="master-btn master-btn-primary">Create Shipment</button>
                </div>
            </form>
        </div>
    </div>

    <div class="master-modal" id="deleteShipmentModal" aria-hidden="true">
        <div class="master-modal-card" style="max-width: 440px;" role="dialog" aria-modal="true" aria-labelledby="deleteShipmentTitle">
            <div class="master-modal-header">
                <div class="master-modal-heading"><span class="master-modal-icon" style="background:#fff0f4;color:var(--master-red);">🗑</span><div><h3 class="master-modal-title" id="deleteShipmentTitle">Delete Shipment</h3><p class="master-modal-subtitle">This action cannot be undone</p></div></div>
                <button type="button" class="master-modal-close" id="closeDeleteShipmentModal">×</button>
            </div>
            <div class="master-modal-body"><p id="deleteShipmentDesc" style="margin:0;font-weight:700;color:#536079;">Are you sure you want to delete this shipment?</p></div>
            <form method="POST" action="" id="deleteShipmentForm">
                @csrf
                @method('DELETE')
                <div class="master-modal-footer"><button type="button" class="master-btn master-btn-light" id="cancelDeleteShipmentModal">Cancel</button><button type="submit" class="master-btn master-btn-danger">Delete Shipment</button></div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
    <script src="{{ asset('assets/js/shipments.js') }}"></script>
@endpush
@endsection
