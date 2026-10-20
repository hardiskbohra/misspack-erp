@extends('layouts.app')

@section('page-title', 'Shipment Detail')

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/master-media.css') }}">
    <link rel="stylesheet" href="{{ $assetVer('assets/css/shipments.css') }}">
@endpush

@php($statusClass = str_replace('_', '-', $shipment->status))

<div class="ship">
    {{-- Header: what the record is, what state it is in, what you can do with
         it. The state chips come first so the page answers "where is this
         shipment?" before any action is taken. --}}
    <header class="master-card master-header ship-head">
        <div class="ship-head-main record-head-main">
            <h1>{{ $shipment->identity_name }}</h1>
            <p>
                {{ $shipment->shipment_number }}
                @if ($shipment->tracking_number)
                    · <span class="ship-head-tracking">{{ $shipment->tracking_number }}</span>
                @else
                    · <span class="master-empty-value">No tracking number</span>
                @endif
            </p>
            <div class="record-head-chips">
                <span class="master-badge status-{{ $statusClass }}">{{ $shipment->statusLabel() }}</span>

                @if ($shipment->shipment_label)
                    <span class="master-badge {{ $shipment->labelColorClass() }}">{{ $shipment->shipment_label }}</span>
                @endif

                <span class="ship-eta ship-eta-{{ $shipment->etaState() }}">
                    @if ($shipment->eta_date)
                        ETA {{ $shipment->eta_date->format('d M Y') }} · {{ $shipment->etaLabel() }}
                    @else
                        No ETA recorded
                    @endif
                </span>

                @if ($shipment->client)
                    <span class="master-chip"><i class="fa-solid fa-building"></i>{{ $shipment->client->company_name }}</span>
                @endif

                @if ($documentSummary['complete'])
                    <span class="master-chip"><i class="fa-solid fa-check"></i>Paperwork complete</span>
                @else
                    <span class="master-chip ship-chip-warn"><i class="fa-solid fa-triangle-exclamation"></i>Paperwork {{ $documentSummary['done'] }}/{{ $documentSummary['required'] }}</span>
                @endif
            </div>
        </div>

        <div class="record-head-actions">
            <a href="{{ route('shipments.index') }}" class="master-btn master-btn-light" title="Back to shipments">
                <i class="fa-solid fa-arrow-left"></i> Back
            </a>
            <a href="{{ route('shipments.edit', $shipment) }}" class="master-btn master-btn-primary">
                <i class="fa-solid fa-pen"></i> Edit Shipment
            </a>
            @if ($shipment->latestPackagingList)
                <a href="{{ route('packaging-lists.show', $shipment->latestPackagingList) }}" class="master-btn master-btn-soft">
                    <i class="fa-solid fa-boxes-stacked"></i> View Packaging List
                </a>
                <a href="{{ route('packaging-lists.create', ['shipment' => $shipment->id]) }}" class="master-btn master-btn-light">
                    <i class="fa-solid fa-plus"></i> New Packaging List
                </a>
            @else
                <a href="{{ route('packaging-lists.create', ['shipment' => $shipment->id]) }}" class="master-btn master-btn-soft">
                    <i class="fa-solid fa-boxes-stacked"></i> Create Packaging List
                </a>
            @endif

            {{-- Print previews are one capability, so they read as one group. --}}
            <div class="record-action-group" role="group" aria-label="Print and preview">
                <a href="{{ route('shipments.shipping-mark', $shipment) }}" target="_blank" class="master-btn master-btn-soft">
                    <i class="fa-solid fa-tag"></i> Shipping Mark
                </a>
                <a href="{{ route('shipments.print', [$shipment, 'delivery-challan']) }}" target="_blank" class="master-btn master-btn-soft">
                    <i class="fas fa-print"></i> Challan
                </a>
                <a href="{{ route('shipments.print', [$shipment, 'summary']) }}" target="_blank" class="master-btn master-btn-soft">
                    <i class="fas fa-print"></i> Summary
                </a>
            </div>

            <a href="{{ route('shipments.publicTrack', $shipment->public_token) }}" target="_blank" class="master-btn master-btn-soft">
                <i class="fa-solid fa-arrow-up-right-from-square"></i> Public Tracking
            </a>
        </div>
    </header>

    <div class="master-grid">
        <div>
            <section class="master-card master-card--flat master-section">
                <h3 class="master-section-title">Shipment Overview</h3>

                <div class="master-facts">
                    <x-fact label="Status">
                        <strong class="master-badge status-{{ $statusClass }}">{{ $shipment->statusLabel() }}</strong>
                    </x-fact>

                    <x-fact label="Label">
                        @if ($shipment->shipment_label)
                            <strong class="master-badge {{ $shipment->labelColorClass() }}">{{ $shipment->shipment_label }}</strong>
                        @else
                            <strong class="master-empty-value">No label printed</strong>
                        @endif
                    </x-fact>

                    <x-fact label="Pickup date" :value="$shipment->pickup_date?->format('d M Y')" />
                    <x-fact label="Drop date" :value="$shipment->drop_date?->format('d M Y')" />

                    <x-fact label="Expected delivery">
                        <strong class="ship-eta ship-eta-{{ $shipment->etaState() }}">
                            {{ $shipment->eta_date ? $shipment->eta_date->format('d M Y') : 'Not set' }}
                        </strong>
                        @if ($shipment->eta_date)
                            <span>{{ $shipment->etaLabel() }}</span>
                        @endif
                    </x-fact>

                    <x-fact label="Paperwork">
                        @if ($documentSummary['complete'])
                            <strong class="master-badge status-delivered">Complete</strong>
                        @else
                            <strong class="master-badge status-delayed">{{ $documentSummary['done'] }}/{{ $documentSummary['required'] }}</strong>
                            <span>Missing {{ implode(', ', $documentSummary['missing']) }}</span>
                        @endif
                    </x-fact>

                    @if ($shipment->delay_reason)
                        <x-fact label="Delay reason" :value="$shipment->delay_reason" class="is-wide" />
                    @endif

                    <x-fact label="Logistic partner" :value="$shipment->logistic_partner" />
                    <x-fact label="Bill of entry" :value="$shipment->bill_of_entry_number" />

                    <x-fact label="E-way bill">
                        @if ($shipment->eway_bill_number)
                            <strong class="master-badge {{ in_array($shipment->ewayState(), ['expiring', 'expired'], true) ? 'status-delayed' : 'status-delivered' }}">{{ $shipment->eway_bill_number }}</strong>
                            @if ($shipment->eway_bill_valid_until)
                                <span class="ship-eway ship-eway-{{ in_array($shipment->ewayState(), ['expiring', 'expired'], true) ? $shipment->ewayState() : 'expiring' }}">
                                    Valid till {{ $shipment->eway_bill_valid_until->format('d M Y') }} · {{ $shipment->ewayLabel() }}
                                </span>
                            @endif
                        @else
                            <strong class="master-empty-value">Not set</strong>
                        @endif
                    </x-fact>

                    <x-fact label="Client" :value="$shipment->client->company_name ?? null" />

                    <x-fact label="Project">
                        @if ($shipment->project)
                            <strong>{{ $shipment->project->name }}</strong>
                            <span>{{ $shipment->project->project_number }}</span>
                        @else
                            <strong class="master-empty-value">Not linked</strong>
                        @endif
                    </x-fact>

                    <x-fact label="Declared cost">
                        <strong>{{ $shipment->shipment_cost ? \App\Models\Shipment::formatAmount($shipment->currency, $shipment->shipment_cost) : '—' }}</strong>
                        <span>
                            {{ $shipment->package_count
                                ? $shipment->package_count.' package(s) · '.$shipment->package_count.' sticker(s)'
                                : 'No package count recorded' }}
                        </span>
                    </x-fact>

                    <x-fact label="Cost borne by" :value="$costBorneByOptions[$shipment->cost_borne_by] ?? null" />
                </div>
            </section>

            <section class="master-card master-card--flat master-section">
                <h3 class="master-section-title">Tracking Progress</h3>
                @include('shipments.partials.tracker', ['shipment' => $shipment])
                <p class="master-sub ship-track-hint">
                    The same steps are shown to the client on the portal and on the public tracking page.
                </p>
            </section>

            @include('shipments.partials.costs-card')

            <section class="master-card master-card--flat master-section">
                <h3 class="master-section-title">Route Details</h3>

                <div class="master-facts">
                    <x-fact label="From">
                        <strong>{{ $shipment->from_name ?: 'Not set' }}</strong>
                        <p class="master-address-sub">
                            {{ collect([$shipment->from_address, $shipment->from_city, $shipment->from_state, $shipment->from_country])->filter()->implode(', ') }}
                            {{ $shipment->from_pincode ? '- '.$shipment->from_pincode : '' }}
                        </p>
                        <p class="master-address-sub">
                            <b>Email</b> {{ $shipment->from_email ?: '—' }}<br>
                            <b>Mobile</b> {{ $shipment->from_mobile ?: '—' }}
                        </p>
                    </x-fact>

                    <x-fact label="To">
                        <strong>{{ $shipment->to_name ?: 'Not set' }}</strong>
                        <p class="master-address-sub">
                            {{ collect([$shipment->to_address, $shipment->to_city, $shipment->to_state, $shipment->to_country])->filter()->implode(', ') }}
                            {{ $shipment->to_pincode ? '- '.$shipment->to_pincode : '' }}
                        </p>
                        <p class="master-address-sub">
                            <b>Email</b> {{ $shipment->to_email ?: '—' }}<br>
                            <b>Mobile</b> {{ $shipment->to_mobile ?: '—' }}
                        </p>
                    </x-fact>
                </div>
            </section>

            <section class="master-card master-card--flat master-section">
                <div class="master-section-head">
                    <h3 class="master-section-title">Shipment Products</h3>
                    @if ($shipment->items->isNotEmpty())
                        <span class="master-chip">{{ $shipment->items->count() }} line{{ $shipment->items->count() === 1 ? '' : 's' }}</span>
                    @endif
                </div>

                @if ($shipment->items->isEmpty())
                    <div class="master-empty-state">
                        <i class="fa-solid fa-box-open"></i>
                        <p>No products on this shipment yet. Add them from <strong>Edit Shipment</strong>.</p>
                    </div>
                @else
                    {{-- Desktop table --}}
                    <div class="master-table-wrap desktop-products">
                        <table class="master-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>HS Code</th>
                                    <th class="is-num">Qty</th>
                                    <th class="is-num">Value</th>
                                    <th class="is-num">Weight</th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach ($shipment->items as $item)
                                <tr>
                                    <td>
                                        <strong>{{ $item->product_name }}</strong>
                                        @if ($item->description)
                                            <span class="master-sub">{{ $item->description }}</span>
                                        @endif
                                        @foreach($item->packingAllocations as $allocation)
                                            <span class="master-sub">{{ $allocation->packagingListItem?->carton?->packagingList?->packing_number }} · {{ number_format((float)$allocation->allocated_quantity,3) }} {{ $item->unit }}</span>
                                        @endforeach
                                    </td>
                                    <td class="ship-cell-muted">{{ $item->hs_code ?: '—' }}</td>
                                    <td class="is-num">{{ (int) $item->quantity }} {{ $item->unit }}</td>
                                    <td class="is-num">
                                        {{ $item->declared_value ? \App\Helpers\CommonHelper::amount($item->declared_value, $item->currency) : '—' }}
                                    </td>
                                    <td class="is-num">{{ $item->gross_weight ?: '—' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Mobile cards --}}
                    <div class="mobile-products">
                        @foreach ($shipment->items as $item)
                            <div class="product-card">
                                <div class="product-title">{{ $item->product_name }}</div>
                                @if ($item->description)
                                    <div class="product-desc">{{ $item->description }}</div>
                                @endif
                                <div class="product-grid">
                                    <div><span>HS Code</span><strong>{{ $item->hs_code ?: '—' }}</strong></div>
                                    <div><span>Qty</span><strong>{{ (int) $item->quantity }} {{ $item->unit }}</strong></div>
                                    <div><span>Value</span><strong>{{ $item->declared_value ? \App\Helpers\CommonHelper::amount($item->declared_value, $item->currency) : '—' }}</strong></div>
                                    <div><span>Weight</span><strong>{{ $item->gross_weight ?: '—' }}</strong></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="master-card master-card--flat master-section">
                <div class="master-section-head"><div><h3 class="master-section-title">Delivery receipt reconciliation</h3><p class="master-sub">Accepted quantities drive project delivery readiness. Short, damaged, rejected and returned quantities remain open until replacement receipts reconcile them.</p></div><span class="master-chip">{{ $shipment->deliveryReceipts->count() }} receipt{{ $shipment->deliveryReceipts->count()===1?'':'s' }}</span></div>
                <div class="master-table-wrap">
                    <table class="master-table"><thead><tr><th>Product</th><th class="is-num">Dispatched</th><th class="is-num">Net accepted</th><th class="is-num">Remaining</th><th>Status</th></tr></thead><tbody>
                        @each('shipments.partials.delivery-reconciliation-row', $shipment->items, 'item')
                    </tbody></table>
                </div>

                @if(in_array($shipment->status, \App\Models\Shipment::dispatchedStatuses(), true))
                    <details class="shipment-receipt-entry"><summary class="master-btn master-btn-soft"><i class="fa-solid fa-clipboard-check"></i> Record delivery receipt</summary>
                        <form method="POST" enctype="multipart/form-data" action="{{ route('shipments.delivery-receipts.store',$shipment) }}" class="master-form-card">@csrf
                            <div class="master-detail-grid">
                                <label class="master-field"><span class="master-label">Receipt / POD reference</span><input class="master-input" name="receipt_reference" maxlength="255" aria-label="Receipt or POD reference"></label>
                                <label class="master-field"><span class="master-label">Recipient name</span><input class="master-input" name="recipient_name" maxlength="255" required aria-label="Recipient name"></label>
                                <label class="master-field"><span class="master-label">Received at</span><input class="master-input" type="datetime-local" name="received_at" value="{{ now()->format('Y-m-d\TH:i') }}" required aria-label="Received at"></label>
                                <label class="master-field"><span class="master-label">POD / receipt evidence</span><input class="master-input" type="file" name="evidence_file" accept=".jpg,.jpeg,.png,.webp,.pdf" required></label>
                            </div>
                            <div class="master-table-wrap"><table class="master-table"><thead><tr><th>Product</th><th>Received</th><th>Accepted</th><th>Short</th><th>Damaged</th><th>Rejected</th><th>Returned</th><th>Replacement</th><th>Reason</th></tr></thead><tbody>
                                @foreach($shipment->items as $index=>$item)<tr><td><input type="hidden" name="lines[{{ $index }}][shipment_item_id]" value="{{ $item->id }}"><strong>{{ $item->product_name }}</strong><span class="master-sub">Dispatched {{ number_format((float)$item->quantity,3) }}</span></td>@foreach(['received_quantity','accepted_quantity','short_quantity','damaged_quantity','rejected_quantity','returned_quantity','replacement_quantity'] as $field)<td><input class="master-input" type="number" min="0" step="0.001" name="lines[{{ $index }}][{{ $field }}]" value="0" aria-label="{{ str($field)->headline() }} for {{ $item->product_name }}"></td>@endforeach<td><input class="master-input" name="lines[{{ $index }}][discrepancy_reason]" maxlength="2000" aria-label="Discrepancy reason for {{ $item->product_name }}"></td></tr>@endforeach
                            </tbody></table></div>
                            <label class="master-field"><span class="master-label">Receipt notes</span><textarea class="master-textarea" name="notes" rows="3" maxlength="4000" aria-label="Receipt notes"></textarea></label>
                            <div class="master-form-actions"><button class="master-btn master-btn-primary" type="submit">Record immutable receipt</button></div>
                        </form>
                    </details>
                @endif

                <div class="shipment-receipt-history">
                    @forelse($shipment->deliveryReceipts as $receipt)
                        <article class="shipment-receipt-card">
                            <header><div><strong>{{ $receipt->receipt_reference ?: 'Receipt #'.$receipt->id }}</strong><span>{{ $receipt->received_at?->format('d M Y, H:i') }} · {{ $receipt->recipient_name }}</span></div><span class="master-badge {{ $receipt->hasDiscrepancy() ? 'status-delayed' : 'status-completed' }}">{{ $receipt->hasDiscrepancy() ? 'Discrepancy recorded' : 'Accepted' }}</span></header>
                            <div class="shipment-receipt-lines">
                                @foreach($receipt->lines as $line)
                                    <span><strong>{{ $line->shipmentItem?->product_name }}</strong> · {{ number_format((float)$line->accepted_quantity,3) }} accepted
                                        @if((float)$line->short_quantity>0) · {{ number_format((float)$line->short_quantity,3) }} short @endif
                                        @if((float)$line->damaged_quantity>0) · {{ number_format((float)$line->damaged_quantity,3) }} damaged @endif
                                        @if((float)$line->rejected_quantity>0) · {{ number_format((float)$line->rejected_quantity,3) }} rejected @endif
                                        @if((float)$line->returned_quantity>0) · {{ number_format((float)$line->returned_quantity,3) }} returned @endif
                                    </span>
                                @endforeach
                            </div>
                            <footer><a href="{{ $receipt->evidenceUrl() }}" target="_blank" rel="noopener"><i class="fa-solid fa-paperclip"></i> {{ $receipt->evidence_original_name }}</a><small>SHA-256 {{ substr($receipt->evidence_hash,0,12) }} · recorded by {{ $receipt->recorder?->name ?: 'System' }}</small></footer>
                        </article>
                    @empty
                        <div class="master-empty-state"><i class="fa-solid fa-clipboard"></i><p>No line-level delivery receipt has been recorded. Shipment status alone does not complete delivered quantities.</p></div>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="ship-side">
            <section class="master-card master-card--flat master-section">
                <h3 class="master-section-title">Share Tracking</h3>
                <p class="master-sub">Anyone with this link can follow the shipment without logging in.</p>
                <div class="public-box">
                    <input class="master-input" id="publicTrackingLink" readonly value="{{ route('shipments.publicTrack', $shipment->public_token) }}" aria-label="Public tracking link">
                    <button type="button" class="master-btn master-btn-soft" onclick="maCopy('publicTrackingLink', 'Copy tracking link')">Copy</button>
                </div>
            </section>

            <section class="master-card master-card--flat master-section">
                <h3 class="master-section-title">Add Tracking Update</h3>
                <form method="POST" action="{{ route('shipments.history.store', $shipment) }}" class="history-form">
                    @csrf
                    <div class="row">
                        <div class="ship-form-field">
                            <label class="ship-form-label" for="historyStatus">Status</label>
                            <select class="master-select" id="historyStatus" name="status">
                                @foreach ($statusOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($shipment->status === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="ship-form-field">
                            <label class="ship-form-label" for="historyLocation">Location</label>
                            <input class="master-input" id="historyLocation" name="location" placeholder="e.g. Mundra Port">
                        </div>
                    </div>

                    {{-- Marking it delivered records a delivery date: the field
                         appears with today in it, and leaving it empty records
                         today anyway. --}}
                    <div class="ship-form-field" data-delivery-optional
                        @if ($shipment->status !== \App\Models\Shipment::STATUS_DELIVERED) hidden @endif>
                        <label class="ship-form-label" for="historyDropDate">Delivery (drop) date</label>
                        <input class="master-input" id="historyDropDate" type="date" name="drop_date"
                            data-today="{{ now(config('app.business_timezone'))->toDateString() }}"
                            value="{{ old('drop_date', now(config('app.business_timezone'))->toDateString()) }}">
                        <small class="master-sub">Used only when the status becomes delivered. An existing delivery date on the shipment is never overwritten.</small>
                    </div>

                    <div class="ship-form-field">
                        <label class="ship-form-label" for="historyTime">When</label>
                        <input class="master-input" id="historyTime" type="datetime-local" name="event_time"
                            value="{{ now(config('app.business_timezone'))->format('Y-m-d\TH:i') }}">
                    </div>

                    <div class="ship-form-field">
                        <label class="ship-form-label" for="historyRemarks">Remarks</label>
                        <textarea class="master-textarea" id="historyRemarks" name="remarks" rows="3" placeholder="What changed?"></textarea>
                    </div>

                    <div class="ship-form-checks">
                        <label class="master-check"><input type="checkbox" name="is_public" value="1" checked> Show on public tracking</label>
                        <label class="master-check"><input type="checkbox" name="notify_client" value="1" @checked($shipment->client_id && $shipment->show_client_portal)> Notify client on status change</label>
                    </div>

                    <button class="master-btn master-btn-primary" type="submit">Add History</button>
                </form>
            </section>


            <section class="master-card master-card--flat master-section">
                <div class="master-section-head">
                    <h3 class="master-section-title">Shipment Photos</h3>
                    @if ($shipment->attachments->isNotEmpty())
                        <span class="master-chip">{{ $shipment->attachments->count() }}</span>
                    @endif
                </div>

                @if ($shipment->attachments->isEmpty())
                    <div class="master-empty-state">
                        <i class="fa-regular fa-images"></i>
                        <p>No shipment photos yet. Upload them while editing this shipment.</p>
                    </div>
                @else
                    <div class="photo-grid">
                        @foreach ($shipment->attachments as $attachment)
                            <a class="photo-card" href="{{ asset('storage/'.$attachment->file_path) }}" target="_blank">
                                @if (str_starts_with($attachment->mime_type, 'image/'))
                                    <img src="{{ asset('storage/'.$attachment->file_path) }}" alt="{{ $attachment->title ?: $attachment->original_name }}">
                                @else
                                    <div class="file-card"><i class="file-icon fa-solid fa-file"></i></div>
                                @endif

                                <div class="photo-card-body">
                                    <div class="photo-name">{{ $attachment->original_name }}</div>
                                    <div class="photo-meta">
                                        <span class="master-chip">{{ $attachment->is_public ? 'Public' : 'Internal' }}</span>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="master-card master-card--flat master-section">
                <div class="master-section-head">
                    <h3 class="master-section-title">Tracking History</h3>
                    @if ($shipment->histories->isNotEmpty())
                        <span class="master-chip">{{ $shipment->histories->count() }} update{{ $shipment->histories->count() === 1 ? '' : 's' }}</span>
                    @endif
                </div>

                @if ($shipment->histories->isEmpty())
                    <div class="master-empty-state">
                        <i class="fa-regular fa-clock"></i>
                        <p>No tracking updates yet. Add the first one above.</p>
                    </div>
                @else
                    <div class="timeline">
                        @foreach ($shipment->histories as $history)
                            @php($historyStatusClass = str_replace('_', '-', $history->status))
                            <div class="timeline-item">
                                <div class="timeline-title">
                                    <span class="master-badge status-{{ $historyStatusClass }}">{{ $statusOptions[$history->status] ?? $history->status }}</span>
                                    <span class="master-chip">{{ $history->is_public ? 'Public' : 'Internal' }}</span>
                                </div>
                                <div class="timeline-meta">
                                    {{ $history->event_time ? $history->event_time->format('d M Y, h:i A') : '—' }}@if ($history->location) · {{ $history->location }}@endif
                                </div>
                                @if ($history->remarks)
                                    <div class="timeline-remarks">{{ $history->remarks }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    {{-- The detail page owns the cost modal (open / edit / close), the tracking
         form helpers and the print-pack shortcuts — all driven by shipments.js. --}}
    <script src="{{ $assetVer('assets/js/shipments.js') }}"></script>
@endpush
