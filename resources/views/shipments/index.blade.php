@extends('layouts.app')

@section('page-title', 'Shipment Tracking')

@section('page-actions')
    {{-- The page's primary action lives in the header, so it stays reachable
         however far the list scrolls. --}}
    <button type="button" class="master-btn master-btn-primary" data-quick-shipment>
        + Quick Shipment
    </button>
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/shipments.css') }}">
    {{-- the shared list chrome (chips, applied strip, density, pinned grid,
         mobile card, totals row, empty state) — after the module sheet, so the
         chrome keeps its own properties --}}
    <link rel="stylesheet" href="{{ $assetVer('assets/css/master-list.css') }}">
@endpush

@php
    /* "Reset" and "clear filters" only make sense when something is filtered. */
    $filtersActive = trim((string) $search) !== ''
        || ($status && $status !== 'all')
        || filled($fromDate)
        || filled($attention)
        || ($currency && $currency !== 'all');

    /* One URL per removable filter: everything else stays, 'page' restarts (a
       filter change is a new list) and 'saved_view' is dropped because the
       result is no longer that view. */
    $chipUrl = function (string $key) {
        $keep = collect(request()->except([$key, 'page', 'saved_view']))
            ->reject(fn ($value) => $value === null || $value === '' || $value === 'all');

        return route('shipments.index', $keep->all());
    };
@endphp

<div class="ship ship-index master-list">

    <div class="master-stats desktop-only">
        <div class="master-stat master-stat--flat blue"><span class="icon">⇄</span><div><p class="master-stat-title">Total Shipments</p><p class="master-stat-value">{{ $stats['total'] }}</p></div></div>
        <div class="master-stat master-stat--flat purple"><span class="icon">✈</span><div><p class="master-stat-title">In Transit</p><p class="master-stat-value">{{ $stats['in_transit'] + $stats['out_for_delivery'] }}</p></div></div>
        <div class="master-stat master-stat--flat teal"><span class="icon">✓</span><div><p class="master-stat-title">Delivered</p><p class="master-stat-value">{{ $stats['delivered'] }}</p></div></div>
        <div class="master-stat master-stat--flat orange"><span class="icon">!</span><div><p class="master-stat-title">Hold / Delayed</p><p class="master-stat-value">{{ $stats['custom_hold'] + $stats['delayed'] }}</p></div></div>
        <div class="master-stat master-stat--flat green tooltip-container">
            <span class="icon">₹</span>
            <div>
                <p class="master-stat-title">Spent (filtered)</p>
                <p class="master-stat-value ship-spend-total">{{ \App\Models\Shipment::formatInr($spendInr) }}</p>
                <p class="master-sub">{{ $spendEntries }} costed {{ \Illuminate\Support\Str::plural('entry', $spendEntries) }} in this filter</p>
                <span class="tooltip-text">Charges on the shipments currently listed, each one converted to rupees at the rate recorded on the day it was entered. A shipment still on the old single “Shipment Cost” figure counts here while it is billed in rupees.</span>
            </div>
        </div>
    </div>

    <div class="master-card master-card--flat">
        @php($baseFilters = request()->except(['attention', 'page', 'saved_view']))
        <div class="master-list-bar">
            <div class="master-list-chips">
                <a class="master-list-chip {{ ! $attention ? 'is-active' : '' }}" href="{{ route('shipments.index', $baseFilters) }}">All shipments</a>
                <a class="master-list-chip {{ $attention === 'needs_attention' ? 'is-active' : '' }}"
                    href="{{ route('shipments.index', $baseFilters + ['attention' => 'needs_attention']) }}">
                    Needs attention <span class="master-list-chip-count">{{ $attentionCounts['needs_attention'] ?? 0 }}</span>
                </a>
                <a class="master-list-chip {{ $attention === 'overdue' ? 'is-active' : '' }}"
                    href="{{ route('shipments.index', $baseFilters + ['attention' => 'overdue']) }}">
                    Overdue ETA <span class="master-list-chip-count">{{ $attentionCounts['overdue'] ?? 0 }}</span>
                </a>
                <a class="master-list-chip {{ $attention === 'due_soon' ? 'is-active' : '' }}"
                    href="{{ route('shipments.index', $baseFilters + ['attention' => 'due_soon']) }}">
                    Arriving &le; 7 days <span class="master-list-chip-count">{{ $attentionCounts['due_soon'] ?? 0 }}</span>
                </a>
                <a class="master-list-chip {{ $attention === 'hold' ? 'is-active' : '' }}"
                    href="{{ route('shipments.index', $baseFilters + ['attention' => 'hold']) }}">
                    Hold / delayed <span class="master-list-chip-count">{{ $attentionCounts['hold'] ?? 0 }}</span>
                </a>
                <a class="master-list-chip {{ $attention === 'docs_pending' ? 'is-active' : '' }}"
                    href="{{ route('shipments.index', $baseFilters + ['attention' => 'docs_pending']) }}">
                    Docs pending <span class="master-list-chip-count">{{ $attentionCounts['docs_pending'] ?? 0 }}</span>
                </a>
                <a class="master-list-chip {{ $attention === 'eway_expiring' ? 'is-active' : '' }}"
                    href="{{ route('shipments.index', $baseFilters + ['attention' => 'eway_expiring']) }}">
                    E-way expiring <span class="master-list-chip-count">{{ $attentionCounts['eway_expiring'] ?? 0 }}</span>
                </a>
            </div>

            <div class="master-list-saved">
                @foreach ($savedViews as $view)
                    <span class="master-list-saved-chip">
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
                <form method="POST" action="{{ route('shipments.saved-views.store', $baseFilters) }}" class="master-list-save-view" id="saveViewForm" hidden>
                    @csrf
                    <input class="master-input" name="name" placeholder="View name" maxlength="60" aria-label="Saved view name" required>
                    <label class="master-check"><input type="checkbox" name="is_shared" value="1"> Share</label>
                    <button class="master-btn master-btn-primary master-btn-sm">Save</button>
                </form>
            </div>
        </div>

        <form method="GET" action="{{ route('shipments.index') }}">

            <div class="master-filter-row">
                <div class="master-search">
                    <span aria-hidden="true">⌕</span>
                    <input class="master-input" type="text" name="search" value="{{ $search }}"
                        placeholder="Search shipment, tracking, BOE, partner..." aria-label="Search shipments">
                </div>
                <select class="master-select" name="status" aria-label="Filter by status">
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
                <input class="master-input desktop-only" type="date" name="from_date"
                    value="{{ $fromDate }}" aria-label="Pickup date from" title="Pickup date from">

                <div class="master-list-filter-group">
                    @if ($filtersActive)
                        <a class="master-btn master-btn-soft" href="{{ route('shipments.index') }}">Reset</a>
                    @endif
                    <button class="master-btn master-btn-primary" type="submit">Apply filters</button>
                </div>
            </div>

            {{-- What is actually filtering, one removable chip each — including
                 filters that arrived from a saved view or a URL and therefore
                 have no visible control above. --}}
            @if ($filtersActive)
                <div class="master-list-applied">
                    <span class="master-list-applied-title">Filtered by</span>

                    @if (trim((string) $search) !== '')
                        <span class="master-list-applied-chip">
                            <span class="master-list-applied-key">Search</span>
                            <span class="master-list-applied-value">{{ $search }}</span>
                            <a class="master-list-applied-x" href="{{ $chipUrl('search') }}"
                                aria-label="Remove the search filter" title="Remove the search filter">&times;</a>
                        </span>
                    @endif

                    @if ($status && $status !== 'all')
                        <span class="master-list-applied-chip">
                            <span class="master-list-applied-key">Status</span>
                            <span class="master-list-applied-value">{{ $statusOptions[$status] ?? $status }}</span>
                            <a class="master-list-applied-x" href="{{ $chipUrl('status') }}"
                                aria-label="Remove the status filter" title="Remove the status filter">&times;</a>
                        </span>
                    @endif

                    @if ($currency && $currency !== 'all')
                        <span class="master-list-applied-chip">
                            <span class="master-list-applied-key">Currency</span>
                            <span class="master-list-applied-value">{{ $currencyOptions[$currency] ?? $currency }}</span>
                            <a class="master-list-applied-x" href="{{ $chipUrl('currency') }}"
                                aria-label="Remove the currency filter" title="Remove the currency filter">&times;</a>
                        </span>
                    @endif

                    @if (filled($fromDate))
                        <span class="master-list-applied-chip">
                            <span class="master-list-applied-key">Pickup from</span>
                            <span class="master-list-applied-value">{{ \App\Helpers\DateRanges::display($fromDate) }}</span>
                            <a class="master-list-applied-x" href="{{ $chipUrl('from_date') }}"
                                aria-label="Remove the pickup-date filter" title="Remove the pickup-date filter">&times;</a>
                        </span>
                    @endif

                    @if (filled($attention))
                        <span class="master-list-applied-chip">
                            <span class="master-list-applied-key">Attention</span>
                            <span class="master-list-applied-value">{{ str_replace('_', ' ', $attention) }}</span>
                            <a class="master-list-applied-x" href="{{ $chipUrl('attention') }}"
                                aria-label="Remove the attention filter" title="Remove the attention filter">&times;</a>
                        </span>
                    @endif

                    <a class="master-list-applied-clear" href="{{ route('shipments.index') }}">Clear all filters</a>
                </div>
            @endif
        </form>
    </div>

    <div class="master-card master-table-card master-card--flat">
        {{-- One quiet line instead of a sentence: the ordering is visible in the
             table itself (the closed block has its own divider), so this only
             has to name the rule. The full wording is the tooltip. --}}
        <div class="master-list-toolbar">
            <p class="master-list-hint"
                title="Open shipments first, newest pickup date on top. Delivered and cancelled shipments sit in a closed block below, also newest first.">
                Open shipments first &middot; closed block below
            </p>

            {{-- How much of the list fits on screen is a preference, not a
                 filter, so it lives beside the ordering rule — in the same
                 right-hand slot every list uses, so a module can put its own
                 destinations next to the density switch. --}}
            <div class="master-list-toolbar-actions">
                <div class="master-list-density desktop-only" role="group" aria-label="Row density">
                    <button type="button" class="master-list-density-btn" data-density="comfortable"
                        aria-pressed="true">Comfortable</button>
                    <button type="button" class="master-list-density-btn" data-density="compact"
                        aria-pressed="false">Compact</button>
                </div>
            </div>
        </div>
        <div class="master-table-wrap">
            <table class="master-table">
                <thead>
                    <tr>
                        <th scope="col">Pickup</th>
                        <th scope="col">Shipment</th>
                        <th scope="col">Route</th>
                        <th scope="col">Logistic</th>
                        <th scope="col">ETA</th>
                        <th scope="col" class="is-num">Charges</th>
                        <th scope="col">Status</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @php($closedDividerShown = false)
                    @forelse($shipments as $shipment)
                        @php($statusClass = str_replace('_', '-', $shipment->status))
                        @if (! $closedDividerShown && $shipment->isClosed())
                            @php($closedDividerShown = true)
                            <tr class="master-list-group">
                                {{-- the cell stays a table cell: display:flex on a
                                     <td> takes it out of the table layout and the
                                     colspan stops spanning, so the row lives in a
                                     flex wrapper inside it --}}
                                <td colspan="8">
                                    <div class="master-list-group-inner">
                                        <span>Closed — delivered / cancelled</span>
                                        <span class="master-list-group-count">
                                            {{ $shipments->where('status', \App\Models\Shipment::STATUS_DELIVERED)->count() + $shipments->where('status', \App\Models\Shipment::STATUS_CANCELLED)->count() }}
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        @endif
                        {{-- The whole row opens the record (assets/js/shipments.js);
                             anything interactive inside it keeps its own click. --}}
                        <tr class="ship-row {{ $shipment->isClosed() ? 'ship-row-closed' : '' }} is-clickable"
                            data-href="{{ route('shipments.show', $shipment) }}">
                            <td data-label="Pickup">
                                <span class="ship-date">{{ $shipment->pickup_date ? $shipment->pickup_date->format('d M') : '—' }}</span>
                                @if ($shipment->project || $shipment->client)
                                    <span class="ship-tags">
                                        @if ($shipment->project)
                                            <span class="ship-tag" title="{{ $shipment->project->project_number }} — {{ $shipment->project->name }}"
                                                aria-label="Project: {{ $shipment->project->name }}">P</span>
                                        @endif
                                        @if ($shipment->client)
                                            <span class="ship-tag" title="{{ $shipment->client->company_name }}"
                                                aria-label="Client: {{ $shipment->client->company_name }}">C</span>
                                        @endif
                                    </span>
                                @endif
                            </td>
                            <td data-label="Shipment">
                                {{-- the number is what people search by, so it is the
                                     link and the product is the second line --}}
                                <a class="ship-cell" href="{{ route('shipments.show', $shipment) }}">
                                    <span class="ship-cell-id">
                                        {{ $shipment->shipment_number }}
                                        @if ($shipment->shipment_label)
                                            <span class="master-badge ship-label-chip {{ $shipment->labelColorClass() }}">{{ $shipment->shipment_label }}</span>
                                        @endif
                                    </span>
                                    <span class="ship-cell-name">{{ $shipment->identity_name }}</span>
                                </a>
                            </td>
                            <td class="ship-route" data-label="Route">
                                <strong>{{ $shipment->from_name ?: 'Origin' }} → {{ $shipment->to_name ?: 'Destination' }}</strong>
                                <span class="master-sub desktop-only">{{ $shipment->from_city ?: '—' }} to {{ $shipment->to_city ?: '—' }}</span>
                            </td>
                            <td class="ship-logistic" data-label="Logistic">
                                {{ $shipment->logistic_partner ?: '—' }}
                                <span class="master-sub">{{ $shipment->tracking_number ?: 'No tracking' }}</span>
                            </td>
                            <td data-label="ETA">
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
                            <td class="ship-money is-num" data-label="Charges">
                                @if ((int) $shipment->costs_count > 0)
                                    @if ((float) $shipment->cost_same_currency > 0)
                                        {{ \App\Models\Shipment::formatAmount($shipment->currency, $shipment->cost_same_currency) }}
                                    @else
                                        ≈ {{ \App\Models\Shipment::formatInr($shipment->cost_inr_total) }}
                                    @endif
                                    <span class="master-sub">{{ $shipment->costs_count }} cost head{{ (int) $shipment->costs_count === 1 ? '' : 's' }} · {{ $shipment->cost_borne_by ?: '' }}</span>
                                @else
                                    {{ $shipment->shipment_cost ? \App\Models\Shipment::formatAmount($shipment->currency, $shipment->shipment_cost) : '—' }}
                                    <span class="master-sub">{{ $shipment->cost_borne_by ?: '' }}</span>
                                @endif
                            </td>
                            <td data-label="Status">
                                <span class="master-badge ship-status status-{{ $statusClass }}">{{ $shipment->statusLabel() }}</span>
                            </td>
                            <td data-label="Action">
                                <div class="master-row-actions">

                                    <div class="master-dropdown">
                                        <button type="button" class="master-dropdown-toggle"
                                            aria-label="Actions for {{ $shipment->shipment_number }}"
                                            aria-haspopup="true" aria-expanded="false">
                                            <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
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
                                            
                                            <button type="button"
                                                onclick="copyShipmentLink('{{ route('shipments.publicTrack', $shipment->public_token) }}')">
                                                <i class="fas fa-link" aria-hidden="true"></i>
                                                Public Link
                                            </button>
                                
                                            {{-- the whole module confirms deletes in
                                                 one modal, not a browser dialog --}}
                                            <button type="button" class="danger master-delete-btn"
                                                data-delete-url="{{ route('shipments.destroy', $shipment) }}"
                                                data-name="{{ $shipment->shipment_number }}">
                                                <i class="fas fa-trash" aria-hidden="true"></i>
                                                Delete Shipment
                                            </button>
                                
                                        </div>
                                    </div>
                                
                                </div>
                            </td>
                        </tr>
                    @empty
                        {{-- colspan must match the column count: it was 10 on an
                             8-column table, so the message hung past the card --}}
                        <tr>
                            <td colspan="8">
                                <div class="master-list-empty">
                                    <span class="master-list-empty-icon" aria-hidden="true">⇄</span>
                                    <p class="master-list-empty-title">
                                        {{ $filtersActive ? 'No shipments match these filters' : 'No shipments yet' }}
                                    </p>
                                    <p class="master-list-empty-text">
                                        {{ $filtersActive
                                            ? 'Adjust the search or the filters above — the counts on each filter chip show what is available.'
                                            : 'Create the first shipment to start tracking pickups, documents and costs.' }}
                                    </p>
                                    <div class="master-list-empty-actions">
                                        @if ($filtersActive)
                                            <a class="master-btn master-btn-soft" href="{{ route('shipments.index') }}">Clear filters</a>
                                        @endif
                                        <button type="button" class="master-btn master-btn-primary" data-quick-shipment>+ Quick Shipment</button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="master-list-total">
                        <td colspan="5">
                            <strong>Total — {{ $shipments->count() }} {{ \Illuminate\Support\Str::plural('entry', $shipments->count()) }} shown</strong>
                            <span class="master-sub">Charges recorded on the shipments on this page</span>
                        </td>
                        {{-- the money sits in the Charges column so it lines up
                             with the figures above it — and says so when the
                             footer is stacked into a card on a phone --}}
                        <td class="is-num" data-label="Charges">
                            <strong>{{ \App\Models\Shipment::formatInr($pageSpendInr) }}</strong>
                            <span class="master-sub">Filtered total (all pages): {{ \App\Models\Shipment::formatInr($spendInr) }}</span>
                        </td>
                        <td colspan="2"></td>
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
                            <label class="master-label" for="quickIdentityName">Shipment Identity Name <span class="master-required">*</span></label>
                            <input class="master-input" id="quickIdentityName" name="identity_name" placeholder="e.g. Green pigment samples from China" required>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quickShipmentType">Shipment Type <span class="master-required">*</span></label>
                            <select class="master-select" id="quickShipmentType" name="shipment_type" required>
                                @foreach($typeOptions as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field"><label class="master-label" for="quickPickupDate">Pickup Date</label><input class="master-input" id="quickPickupDate" type="date" name="pickup_date" value="{{ now()->toDateString() }}"></div>
                        <div class="master-field">
                            <label class="master-label" for="quickFromName">From Name</label>
                            <input class="master-input" id="quickFromName" name="from_name" list="quickFromNames" placeholder="Shipper name" autocomplete="off">
                            <datalist id="quickFromNames">
                                @foreach ($partyNames['from'] as $partyName)
                                    <option value="{{ $partyName }}"></option>
                                @endforeach
                            </datalist>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quickToName">To Name</label>
                            <input class="master-input" id="quickToName" name="to_name" list="quickToNames" placeholder="Receiver name" autocomplete="off">
                            <datalist id="quickToNames">
                                @foreach ($partyNames['to'] as $partyName)
                                    <option value="{{ $partyName }}"></option>
                                @endforeach
                            </datalist>
                        </div>
                        <div class="master-field"><label class="master-label" for="quickLogisticPartner">Logistic Partner</label><input class="master-input" id="quickLogisticPartner" name="logistic_partner" placeholder="DHL / FedEx / BlueDart"></div>
                        <div class="master-field"><label class="master-label" for="quickTrackingNumber">Tracking Number</label><input class="master-input" id="quickTrackingNumber" name="tracking_number" placeholder="Tracking number"></div>
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
        <div class="master-modal-card is-narrow" role="dialog" aria-modal="true" aria-labelledby="deleteShipmentTitle">
            <div class="master-modal-header">
                <div class="master-modal-heading"><span class="master-modal-icon is-danger">🗑</span><div><h3 class="master-modal-title" id="deleteShipmentTitle">Delete Shipment</h3><p class="master-modal-subtitle">This action cannot be undone</p></div></div>
                <button type="button" class="master-modal-close" id="closeDeleteShipmentModal">×</button>
            </div>
            <div class="master-modal-body"><p class="master-modal-text" id="deleteShipmentDesc">Are you sure you want to delete this shipment?</p></div>
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
