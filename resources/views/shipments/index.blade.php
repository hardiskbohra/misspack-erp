@extends('layouts.app')

@section('page-title', 'Shipment Tracking')

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/shipments.css') }}">
@endpush

<div class="ship ship-index">

    <div class="master-stats desktop-only">
        <div class="master-stat master-stat--flat blue"><span class="icon">⇄</span><div><p class="master-stat-title">Total Shipments</p><p class="master-stat-value">{{ $stats['total'] }}</p></div></div>
        <div class="master-stat master-stat--flat purple"><span class="icon">✈</span><div><p class="master-stat-title">In Transit</p><p class="master-stat-value">{{ $stats['in_transit'] + $stats['out_for_delivery'] }}</p></div></div>
        <div class="master-stat master-stat--flat teal"><span class="icon">✓</span><div><p class="master-stat-title">Delivered</p><p class="master-stat-value">{{ $stats['delivered'] }}</p></div></div>
        <div class="master-stat master-stat--flat orange"><span class="icon">!</span><div><p class="master-stat-title">Hold / Delayed</p><p class="master-stat-value">{{ $stats['custom_hold'] + $stats['delayed'] }}</p></div></div>
        <div class="master-stat master-stat--flat green tooltip-container">
            <span class="icon">₹</span>
            <div>
                <p class="master-stat-title">Spent (filtered)</p>
                <p class="master-stat-value ship-spend-total">{{ \App\Models\Shipment::formatTotals($spendByCurrency) }}</p>
                <p class="master-sub">{{ $spendEntries }} costed {{ \Illuminate\Support\Str::plural('entry', $spendEntries) }} in this filter</p>
                <span class="tooltip-text">Charges recorded on the shipments currently listed by the filters above. Currencies stay separate — they are never added together.</span>
            </div>
        </div>
    </div>

    <div class="master-card master-card--flat">
        @php($baseFilters = request()->except(['attention', 'page', 'saved_view']))
        <div class="ship-chip-bar">
            <div class="ship-chips">
                <a class="ship-chip {{ ! $attention ? 'is-active' : '' }}" href="{{ route('shipments.index', $baseFilters) }}">All shipments</a>
                <a class="ship-chip {{ $attention === 'needs_attention' ? 'is-active' : '' }}"
                    href="{{ route('shipments.index', $baseFilters + ['attention' => 'needs_attention']) }}">
                    Needs attention <span class="ship-chip-count">{{ $attentionCounts['needs_attention'] ?? 0 }}</span>
                </a>
                <a class="ship-chip {{ $attention === 'overdue' ? 'is-active' : '' }}"
                    href="{{ route('shipments.index', $baseFilters + ['attention' => 'overdue']) }}">
                    Overdue ETA <span class="ship-chip-count">{{ $attentionCounts['overdue'] ?? 0 }}</span>
                </a>
                <a class="ship-chip {{ $attention === 'due_soon' ? 'is-active' : '' }}"
                    href="{{ route('shipments.index', $baseFilters + ['attention' => 'due_soon']) }}">
                    Arriving &le; 7 days <span class="ship-chip-count">{{ $attentionCounts['due_soon'] ?? 0 }}</span>
                </a>
                <a class="ship-chip {{ $attention === 'hold' ? 'is-active' : '' }}"
                    href="{{ route('shipments.index', $baseFilters + ['attention' => 'hold']) }}">
                    Hold / delayed <span class="ship-chip-count">{{ $attentionCounts['hold'] ?? 0 }}</span>
                </a>
                <a class="ship-chip {{ $attention === 'docs_pending' ? 'is-active' : '' }}"
                    href="{{ route('shipments.index', $baseFilters + ['attention' => 'docs_pending']) }}">
                    Docs pending <span class="ship-chip-count">{{ $attentionCounts['docs_pending'] ?? 0 }}</span>
                </a>
                <a class="ship-chip {{ $attention === 'eway_expiring' ? 'is-active' : '' }}"
                    href="{{ route('shipments.index', $baseFilters + ['attention' => 'eway_expiring']) }}">
                    E-way expiring <span class="ship-chip-count">{{ $attentionCounts['eway_expiring'] ?? 0 }}</span>
                </a>
            </div>

            <div class="ship-saved-views">
                @foreach ($savedViews as $view)
                    <span class="ship-saved-chip">
                        <a href="{{ route('shipments.index', ['saved_view' => $view->id]) }}"
                            title="{{ $view->is_shared ? 'Shared view' : 'Your view' }}">{{ $view->name }}</a>
                        @if ((int) $view->user_id === (int) auth()->id())
                            <form method="POST" action="{{ route('shipments.saved-views.destroy', $view) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" title="Remove saved view">×</button>
                            </form>
                        @endif
                    </span>
                @endforeach
                <button type="button" class="master-btn master-btn-soft master-btn-sm" id="toggleSaveView">☆ Save this view</button>
                <form method="POST" action="{{ route('shipments.saved-views.store', $baseFilters) }}" class="ship-save-view" id="saveViewForm" hidden>
                    @csrf
                    <input class="master-input" name="name" placeholder="View name" maxlength="60" required>
                    <label class="master-check"><input type="checkbox" name="is_shared" value="1"> Share</label>
                    <button class="master-btn master-btn-primary master-btn-sm">Save</button>
                </form>
            </div>
        </div>

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

    <div class="master-card master-table-card master-card--flat">
        <p class="ship-order-hint">
            Order: <strong>open shipments first</strong> (newest pickup date on top) &mdash; delivered &amp; cancelled sit in a closed block below, also newest first.
        </p>
        <div class="master-table-wrap">
            <table class="master-table">
                <thead>
                    <tr>
                        <th>Pickup / Drop</th>
                        <th>Shipment</th>
                        <th>Route</th>
                        <th>Logistic</th>
                        <th>ETA</th>
                        <th>Charges</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @php($closedDividerShown = false)
                    @forelse($shipments as $shipment)
                        @php($statusClass = str_replace('_', '-', $shipment->status))
                        @if (! $closedDividerShown && $shipment->isClosed())
                            @php($closedDividerShown = true)
                            <tr class="ship-group-row">
                                <td colspan="8">
                                    <span>Closed — delivered / cancelled</span>
                                </td>
                            </tr>
                        @endif
                        <tr style="line-height:1.5;" @class(['ship-row-closed' => $shipment->isClosed()])>
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
                                @if ($shipment->eta_date)
                                    <span class="ship-eta ship-eta-{{ $shipment->etaState() }}">{{ $shipment->eta_date->format('d M') }}</span>
                                    <span class="master-sub">{{ $shipment->etaLabel() }}</span>
                                @else
                                    <span class="master-sub">No ETA</span>
                                @endif
                                @if ($shipment->delay_reason)
                                    <span class="master-sub">{{ $shipment->delay_reason }}</span>
                                @endif
                                @if (in_array($shipment->ewayState(), ['expiring', 'expired'], true))
                                    <span class="ship-eway ship-eway-{{ $shipment->ewayState() }}">
                                        E-way {{ $shipment->ewayLabel() }}
                                    </span>
                                @endif
                            </td>
                            <td style="font-weight:500">
                                @if ((int) $shipment->costs_count > 0)
                                    @if ((float) $shipment->cost_same_currency > 0)
                                        {{ \App\Models\Shipment::formatAmount($shipment->currency, $shipment->cost_same_currency) }}
                                    @else
                                        ≈ {{ \App\Models\Shipment::formatAmount('INR', $shipment->cost_inr_total) }}
                                    @endif
                                    <span class="master-sub">{{ $shipment->costs_count }} cost head{{ (int) $shipment->costs_count === 1 ? '' : 's' }} · {{ $shipment->cost_borne_by ?: '' }}</span>
                                @else
                                    {{ $shipment->currency ?: '' }} {{ $shipment->shipment_cost ?: '0' }}
                                    <span class="master-sub">{{ $shipment->cost_borne_by ?: '' }}</span>
                                @endif
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

                                            <a href="{{ route('shipments.shipping-mark', $shipment) }}" target="_blank">
                                                <i class="fa-solid fa-tag"></i>
                                                Shipping Mark / Stickers
                                            </a>

                                            <a href="{{ route('shipments.print', [$shipment, 'packing-list']) }}" target="_blank">
                                                <i class="fas fa-print"></i>
                                                Packing List
                                            </a>

                                            <a href="{{ route('shipments.print', [$shipment, 'summary']) }}" target="_blank">
                                                <i class="fas fa-print"></i>
                                                Shipment Summary
                                            </a>
                                            
                                            <button type="button" class="master-btn master-btn-soft master-btn-sm" onclick="copyShipmentLink('{{ route('shipments.publicTrack', $shipment->public_token) }}')">Public Link</button>
                                
                                            <form method="POST"
                                                  action="{{ route('shipments.destroy', $shipment) }}"
                                                  data-confirm="Delete this shipment?">
                                
                                                @csrf
                                                @method('DELETE')
                                
                                                <button type="submit" class="master-btn master-btn-danger master-btn-sm">
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
                        <tr><td colspan="10"><div class="master-empty">No shipments found. Create your first shipment.</div></td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="ship-total-row">
                        <td colspan="5">
                            <strong>Total — {{ $shipments->count() }} {{ \Illuminate\Support\Str::plural('entry', $shipments->count()) }} shown</strong>
                            <span class="master-sub">Charges recorded on the shipments on this page</span>
                        </td>
                        <td colspan="3">
                            <strong>{{ \App\Models\Shipment::formatTotals($pageSpendByCurrency) }}</strong>
                            <span class="master-sub">Filtered total (all pages): {{ \App\Models\Shipment::formatTotals($spendByCurrency) }}</span>
                        </td>
                    </tr>
                </tfoot>
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
                        <div class="master-field">
                            <label class="master-label">From Name</label>
                            <input class="master-input" name="from_name" list="quickFromNames" placeholder="Shipper name" autocomplete="off">
                            <datalist id="quickFromNames">
                                @foreach ($partyNames['from'] as $partyName)
                                    <option value="{{ $partyName }}"></option>
                                @endforeach
                            </datalist>
                        </div>
                        <div class="master-field">
                            <label class="master-label">To Name</label>
                            <input class="master-input" name="to_name" list="quickToNames" placeholder="Receiver name" autocomplete="off">
                            <datalist id="quickToNames">
                                @foreach ($partyNames['to'] as $partyName)
                                    <option value="{{ $partyName }}"></option>
                                @endforeach
                            </datalist>
                        </div>
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
    <script src="{{ $assetVer('assets/js/shipments.js') }}"></script>
@endpush
@endsection
