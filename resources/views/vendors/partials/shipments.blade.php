<section class="master-tab-panel" id="vendor-panel-shipments" role="tabpanel" aria-labelledby="vendor-tab-shipments">
    <div class="vendor-panel-head">
        <div>
            <h2 class="vendor-detail-title">Vendor-related shipments</h2>
            <p class="vendor-detail-help">Shipments linked to this vendor by record, or where the vendor is the pickup, drop or logistics partner.</p>
        </div>
        <div class="vendor-panel-meta">
            <span class="vendor-pill">{{ $shipments->count() }} {{ \Illuminate\Support\Str::plural('shipment', $shipments->count()) }}</span>
            @if ($routes['shipments'] !== '#')
                <a class="master-btn master-btn-soft master-btn-sm" href="{{ $routes['shipments'] }}">
                    <i class="fa-solid fa-truck" aria-hidden="true"></i> All shipments
                </a>
            @endif
        </div>
    </div>

    <div class="master-card master-card--flat vendor-table-card">
        <div class="master-table-wrap ui-mobile-cards">
            <table class="master-table vendor-table">
                <thead>
                    <tr>
                        <th scope="col">Shipment</th>
                        <th scope="col">Route</th>
                        <th scope="col" class="ui-mobile-secondary">Pickup / drop</th>
                        <th scope="col" class="ui-mobile-secondary">Logistics</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="is-num">Cost</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($shipments as $shipment)
                        <tr>
                            <td data-label="Shipment">
                                @if (\Illuminate\Support\Facades\Route::has('shipments.show'))
                                    <a class="vendor-table-link" href="{{ route('shipments.show', $shipment) }}">
                                        <strong>{{ $shipment->shipment_number }}</strong>
                                        <span class="master-sub">{{ $shipment->identity_name }}</span>
                                    </a>
                                @else
                                    <strong>{{ $shipment->shipment_number }}</strong>
                                @endif
                            </td>
                            <td data-label="Route">
                                {{ $shipment->from_name ?: '—' }} <span aria-hidden="true">→</span> {{ $shipment->to_name ?: '—' }}
                                <span class="master-sub">{{ $shipment->from_city ?: '—' }} to {{ $shipment->to_city ?: '—' }}</span>
                            </td>
                            <td data-label="Pickup / drop" class="ui-mobile-secondary">
                                {{ optional($shipment->pickup_date)->format('d M Y') ?: '—' }}
                                <span class="master-sub">Drop {{ optional($shipment->drop_date)->format('d M Y') ?: '—' }}</span>
                            </td>
                            <td data-label="Logistics" class="ui-mobile-secondary">
                                {{ $shipment->logistic_partner ?: '—' }}
                                <span class="master-sub">{{ $shipment->tracking_number ?: 'No tracking number' }}</span>
                            </td>
                            <td data-label="Status">
                                <span class="vendor-meta-chip">{{ method_exists($shipment, 'statusLabel') ? $shipment->statusLabel() : $shipment->status }}</span>
                            </td>
                            <td data-label="Cost" class="is-num">{{ $money($shipment->shipment_cost, $shipment->currency) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="master-list-empty">
                                    <span class="master-list-empty-icon" aria-hidden="true"><i class="fa-solid fa-truck"></i></span>
                                    <h3 class="master-list-empty-title">No shipments matched</h3>
                                    <p class="master-list-empty-text">A shipment appears here when its vendor, pickup, drop or logistics partner is this supplier.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
