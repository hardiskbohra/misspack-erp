<section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-shipments-heading">
    <div class="master-section-head">
        <div>
            <h2 class="master-section-title" id="vendor-shipments-heading">Vendor shipments</h2>
            <p class="master-sub">Logistics records associated with {{ $vendor->vendor_name }}.</p>
        </div>
        <div class="vendor-shipment-toolbar">
            <span class="master-chip">{{ number_format($shipments->count()) }} {{ \Illuminate\Support\Str::plural('shipment', $shipments->count()) }}</span>
            @if (\Illuminate\Support\Facades\Route::has('shipments.index'))
                <a class="master-btn master-btn-soft" href="{{ route('shipments.index') }}"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> Open shipments</a>
            @endif
        </div>
    </div>

    @if ($shipments->isNotEmpty())
        <div class="master-table-wrap ui-mobile-cards">
            <table class="master-table vendor-detail-table vendor-shipment-table">
                <thead>
                    <tr>
                        <th scope="col">Shipment</th>
                        <th scope="col">Route</th>
                        <th scope="col">Pickup / ETA / drop</th>
                        <th scope="col">Logistics</th>
                        <th scope="col">Status</th>
                        <th scope="col">Shipment cost</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($shipments as $shipment)
                        @php
                            $etaState = method_exists($shipment, 'etaState') ? $shipment->etaState() : 'none';
                            $shipmentNumber = $shipment->shipment_number ?: 'Shipment #'.$shipment->id;
                        @endphp
                        <tr>
                            <td data-label="Shipment">
                                @if (\Illuminate\Support\Facades\Route::has('shipments.show'))
                                    <a class="vendor-table-name" href="{{ route('shipments.show', $shipment) }}">{{ $shipmentNumber }}</a>
                                @else
                                    <strong>{{ $shipmentNumber }}</strong>
                                @endif
                                <span class="vendor-table-meta">{{ $shipment->identity_name ?: 'Unnamed shipment' }} · {{ method_exists($shipment, 'typeLabel') ? $shipment->typeLabel() : \Illuminate\Support\Str::headline($shipment->shipment_type) }}</span>
                            </td>
                            <td data-label="Route">
                                <strong>{{ $shipment->from_name ?: 'Origin not set' }} <span aria-hidden="true">→</span> {{ $shipment->to_name ?: 'Destination not set' }}</strong>
                                <span class="vendor-table-meta">{{ $shipment->from_city ?: '—' }}{{ $shipment->from_country ? ', '.$shipment->from_country : '' }} <span aria-hidden="true">→</span> {{ $shipment->to_city ?: '—' }}{{ $shipment->to_country ? ', '.$shipment->to_country : '' }}</span>
                            </td>
                            <td data-label="Pickup / ETA / drop">
                                <span>Pickup {{ optional($shipment->pickup_date)->format('d M Y') ?: '—' }}</span>
                                <span class="vendor-table-meta">ETA {{ optional($shipment->eta_date)->format('d M Y') ?: '—' }}</span>
                                <span class="vendor-table-meta">Drop {{ optional($shipment->drop_date)->format('d M Y') ?: '—' }}</span>
                            </td>
                            <td data-label="Logistics">
                                <strong>{{ $shipment->logistic_partner ?: 'Carrier not set' }}</strong>
                                <span class="vendor-table-meta">{{ $shipment->tracking_number ?: 'No tracking number' }}</span>
                                @if ($shipment->shipment_mode)<span class="vendor-table-meta">{{ \Illuminate\Support\Str::headline($shipment->shipment_mode) }}</span>@endif
                            </td>
                            <td data-label="Status">
                                <span class="master-badge vendor-shipment-status vendor-shipment-status-{{ str_replace('_', '-', $shipment->status) }}">{{ method_exists($shipment, 'statusLabel') ? $shipment->statusLabel() : \Illuminate\Support\Str::headline($shipment->status) }}</span>
                                @if ($etaState !== 'none')
                                    <span class="vendor-table-meta vendor-shipment-eta vendor-shipment-eta-{{ str_replace('_', '-', $etaState) }}">{{ $shipment->etaLabel() }}</span>
                                @endif
                            </td>
                            <td data-label="Shipment cost" class="vendor-numeric">{{ $money($shipment->shipment_cost, $shipment->currency ?: 'INR') }}
                                @if ($shipment->cost_borne_by)<span class="vendor-table-meta">Borne by {{ \App\Models\Shipment::costBorneByOptions()[$shipment->cost_borne_by] ?? \Illuminate\Support\Str::headline($shipment->cost_borne_by) }}</span>@endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="master-empty-state"><i class="fa-solid fa-truck-fast" aria-hidden="true"></i><p>No shipments are currently linked to this vendor.</p>
            @if (\Illuminate\Support\Facades\Route::has('shipments.index'))<a class="vendor-inline-link" href="{{ route('shipments.index') }}">Browse shipment register <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>@endif
        </div>
    @endif
</section>
