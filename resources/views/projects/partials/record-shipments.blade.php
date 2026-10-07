<section class="master-tab-panel" id="project-panel-shipments" role="tabpanel" aria-labelledby="project-tab-shipments">
    <div class="project-blocks">
        <section class="master-card master-card--flat project-detail-card">
            <div class="master-section-head">
                <div>
                    <h2 class="master-section-title">Shipments</h2>
                    <p class="master-sub">{{ $project->shipments->count() }}
                        {{ \Illuminate\Support\Str::plural('shipment', $project->shipments->count()) }} carrying this
                    project's goods</p>
                </div>
                <div class="master-section-meta">
                    <a class="master-btn master-btn-primary" href="{{ route('shipments.create') }}">
                        <i class="fas fa-plus" aria-hidden="true"></i> Add shipment</a>
                </div>
            </div>
            <div class="master-table-wrap">
                <table class="master-table">
                    <thead>
                        <tr>
                            <th scope="col">Date</th>
                            <th scope="col">Shipment</th>
                            <th scope="col">Route</th>
                            <th scope="col">Logistics</th>
                            <th scope="col" class="is-num">Charges</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="project-col-actions">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($project->shipments as $shipment)
                            <tr>
                                <td data-label="Date">
                                    {{ $shipment->pickup_date ? $shipment->pickup_date->format('d M') : '—' }}
                                    <span class="project-fact-note">Drop {{ $shipment->drop_date ? $shipment->drop_date->format('d M') : '—' }}</span>
                                </td>
                                <td data-label="Shipment">
                                    <strong>{{ $shipment->shipment_number }}</strong>
                                    <span class="project-fact-note">{{ $shipment->identity_name ?: 'No reference' }}</span>
                                </td>
                                <td data-label="Route">
                                    {{ $shipment->from_name ?: 'Origin' }} → {{ $shipment->to_name ?: 'Destination' }}
                                    <span class="project-fact-note">{{ $shipment->from_city ?: '—' }} to {{ $shipment->to_city ?: '—' }}</span>
                                </td>
                                <td data-label="Logistics">
                                    {{ $shipment->logistic_partner ?: '—' }}
                                    <span class="project-fact-note">{{ $shipment->tracking_number ?: 'No tracking number' }}</span>
                                </td>
                                <td data-label="Charges" class="is-num">
                                    {{ $shipment->currency ? $shipment->currency.' ' : '' }}{{ $shipment->shipment_cost ?: '0' }}
                                    <span class="project-fact-note">{{ $shipment->cost_borne_by ?: 'Not set' }}</span>
                                </td>
                                <td data-label="Status">
                                    <span class="master-badge status-{{ str_replace('_', '-', (string) $shipment->status) }}">{{ $shipment->statusLabel() }}</span>
                                </td>
                                <td data-label="Action" class="project-col-actions">
                                    <div class="master-row-actions">
                                        <a class="master-icon-btn" href="{{ route('shipments.show', $shipment) }}"
                                            aria-label="Open {{ $shipment->shipment_number }}" title="Open shipment"><i class="far fa-eye" aria-hidden="true"></i></a>
                                        <a class="master-icon-btn green" href="{{ route('shipments.publicTrack', $shipment->public_token) }}"
                                            target="_blank" rel="noopener" aria-label="Public tracking for {{ $shipment->shipment_number }}"
                                            title="Public tracking page"><i class="fa-solid fa-link" aria-hidden="true"></i></a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="master-empty-state">
                                        <i class="fa-solid fa-truck-fast" aria-hidden="true"></i>
                                        <p>No shipment is linked to this project yet. Shipments are created in their own module
                                        and linked back here.</p>
                                        <a class="master-btn master-btn-soft master-btn-sm" href="{{ route('shipments.create') }}">
                                            <i class="fas fa-plus" aria-hidden="true"></i> Add shipment</a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</section>

