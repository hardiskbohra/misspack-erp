@extends('layouts.app')

@section('page-title', 'Shipment Detail')

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/master-media.css') }}">
    <link rel="stylesheet" href="{{ $assetVer('assets/css/shipments.css') }}">
@endpush

@php($statusClass = str_replace('_', '-', $shipment->status))

<div class="ship">
    <div class="master-card master-header">
        <div>
            <h1>{{ $shipment->identity_name }}</h1>
            <p style="font-size:16px;font-weight:500;">{{ $shipment->shipment_number }} · {{ $shipment->tracking_number ?: 'No tracking number' }}</p>
        </div>
        <div class="master-actions">
            <a href="{{ route('shipments.index') }}" class="master-btn master-btn-light">Back</a>
            <a href="{{ route('shipments.edit', $shipment) }}" class="master-btn master-btn-soft">Edit Shipment</a>
            <a href="{{ route('shipments.shipping-mark', $shipment) }}" target="_blank" class="master-btn master-btn-soft"><i class="fa-solid fa-tag"></i> Shipping Mark</a>
            <a href="{{ route('shipments.print', [$shipment, 'packing-list']) }}" target="_blank" class="master-btn master-btn-soft"><i class="fas fa-print"></i> Packing List</a>
            <a href="{{ route('shipments.print', [$shipment, 'delivery-challan']) }}" target="_blank" class="master-btn master-btn-soft"><i class="fas fa-print"></i> Challan</a>
            <a href="{{ route('shipments.print', [$shipment, 'summary']) }}" target="_blank" class="master-btn master-btn-soft"><i class="fas fa-print"></i> Summary</a>
            <a href="{{ route('shipments.publicTrack', $shipment->public_token) }}" target="_blank" class="master-btn master-btn-primary">Public Tracking</a>
        </div>
    </div>

    <div class="master-grid">
        <div>
            <div class="master-card master-card--flat master-section">
                <h3 class="master-section-title">Shipment Overview</h3>
                <div class="master-info-grid">
                    <div class="master-info"><span>Status</span><strong class="master-badge status-{{ $statusClass }}">{{ $shipment->statusLabel() }}</strong></div>
                    <div class="master-info"><span>Label</span><strong class="master-badge {{ $shipment->labelColorClass() }}">{{ $shipment->shipment_label ?? '-' }}</strong></div>
                    <div class="master-info"><span>Pickup Date</span><strong>{{ $shipment->pickup_date ? $shipment->pickup_date->format('d M Y') : '-' }}</strong></div>
                    <div class="master-info"><span>Drop Date</span><strong>{{ $shipment->drop_date ? $shipment->drop_date->format('d M Y') : '-' }}</strong></div>
                    <div class="master-info">
                        <span>Expected Delivery</span>
                        <strong class="ship-eta ship-eta-{{ $shipment->etaState() }}">{{ $shipment->eta_date ? $shipment->eta_date->format('d M Y') : 'Not set' }}</strong>
                        @if ($shipment->eta_date)
                            <span>{{ $shipment->etaLabel() }}</span>
                        @endif
                    </div>
                    <div class="master-info">
                        <span>Paperwork</span>
                        <strong>
                            @if ($documentSummary['complete'])
                                <span class="master-badge status-delivered">Complete</span>
                            @else
                                <span class="master-badge status-delayed">{{ $documentSummary['done'] }}/{{ $documentSummary['required'] }}</span>
                            @endif
                        </strong>
                        @if (! $documentSummary['complete'])
                            <span>Missing: {{ implode(', ', $documentSummary['missing']) }}</span>
                        @endif
                    </div>
                    @if ($shipment->delay_reason)
                        <div class="master-info">
                            <span>Delay Reason</span>
                            <strong>{{ $shipment->delay_reason }}</strong>
                        </div>
                    @endif
                    <div class="master-info"><span>Logistic Partner</span><strong>{{ $shipment->logistic_partner ?: '-' }}</strong></div>
                    <div class="master-info"><span>Bill of Entry</span><strong>{{ $shipment->bill_of_entry_number ?: '-' }}</strong></div>
                    <div class="master-info">
                        <span>E-way Bill</span>
                        <strong>
                            @if ($shipment->eway_bill_number)
                                <span class="master-badge {{ in_array($shipment->ewayState(), ['expiring', 'expired'], true) ? 'status-delayed' : 'status-delivered' }}">{{ $shipment->eway_bill_number }}</span>
                            @else
                                -
                            @endif
                        </strong>
                        @if ($shipment->eway_bill_valid_until)
                            <span class="ship-eway ship-eway-{{ in_array($shipment->ewayState(), ['expiring', 'expired'], true) ? $shipment->ewayState() : 'expiring' }}">
                                {{ $shipment->eway_bill_valid_until->format('d M Y') }} · {{ $shipment->ewayLabel() }}
                            </span>
                        @endif
                    </div>
                    <div class="master-info"><span>Client Name</span><strong>{{ $shipment->client->company_name ?? '-' }}</strong></div>
                    <div class="master-info"><span>Project</span><strong>{{ $shipment->project->name ?? '-' }}</strong>
                        <span>{{ $shipment->project->project_number ?? '-' }}</span></div>
                    <div class="master-info">
                        <span>Cost</span>
                        <strong>{{ $shipment->shipment_cost ? \App\Models\Shipment::formatAmount($shipment->currency, $shipment->shipment_cost) : '-' }}</strong>
                        <span>{{ $shipment->package_count ? $shipment->package_count.' package(s) · print '.$shipment->package_count.' sticker(s)' : 'No package count recorded' }}</span>
                    </div>
                    <div class="master-info"><span>Cost Borne By</span><strong>{{ $costBorneByOptions[$shipment->cost_borne_by] ?? '-' }}</strong></div>
                </div>
            </div>

            <div class="master-card master-card--flat master-section">
                <h3 class="master-section-title">Tracking Progress</h3>
                @include('shipments.partials.tracker', ['shipment' => $shipment])
                <p class="master-sub" style="margin-top:8px;">
                    The same steps are shown to the client on the portal and on the public tracking page.
                </p>
            </div>

            @include('shipments.partials.costs-card')

            <div class="master-card master-card--flat master-section">
                <h3 class="master-section-title">Route Details</h3>
                <div class="master-info-grid">
                    
                    <div class="master-info">
                        <span>From</span>
                        <strong>{{ $shipment->from_name ?: '-' }}</strong><br>
                        <p class="master-address-sub">{{ $shipment->from_address ? $shipment->from_address . "," : "" }}<br>
                            {{ $shipment->from_city ? $shipment->from_city . "," : "" }}
                            {{ $shipment->from_state ? $shipment->from_state . "," : "" }}
                            {{ $shipment->from_country ? $shipment->from_country . " - " : "" }}
                            {{ $shipment->from_pincode ?? "" }}
                        </p>
                        <p class="master-address-sub">
                            <b>Email:</b>{{ $shipment->from_email ?? " -" }}<br>
                            <b>Mobile:</b>{{ $shipment->from_mobile ?? " -" }}
                        </p>
                    </div>
                    
                    <div class="master-info">
                        <span>To</span>
                        <strong>{{ $shipment->to_name ?: '-' }}</strong><br>
                        <p class="master-address-sub">{{ $shipment->to_address ? $shipment->to_address . "," : "" }}<br>
                            {{ $shipment->to_city ? $shipment->to_city . "," : "" }}
                            {{ $shipment->to_state ? $shipment->to_state . "," : "" }}
                            {{ $shipment->to_country ? $shipment->to_country . " - " : "" }}
                            {{ $shipment->to_pincode ?? "" }}
                        </p>
                        <p class="master-address-sub">
                            <b>Email:</b>{{ $shipment->to_email ?? " -" }}<br>
                            <b>Mobile:</b>{{ $shipment->to_mobile ?? " -" }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="master-card master-card--flat master-section">
                <h3 class="master-section-title">Shipment Products</h3>
                <div class="shipment-products">

    {{-- Desktop --}}
    <div class="master-table-wrap desktop-products">
        <table class="master-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>HS Code</th>
                    <th>Qty</th>
                    <th>Value</th>
                    <th>Weight</th>
                </tr>
            </thead>

            <tbody>
            @forelse($shipment->items as $item)
                <tr>
                    <td>
                        <strong style="font-weight:500;">{{ $item->product_name }}</strong>
                        <span class="master-sub">{{ $item->description }}</span>
                    </td>

                    <td style="font-weight:500;">{{ $item->hs_code ?: '-' }}</td>

                    <td style="font-weight:500;">{{ (int)$item->quantity }} {{ $item->unit }}</td>

                    <td style="font-weight:500;">
                        {{ $item->declared_value
                            ? $item->currency.' '.number_format($item->declared_value,2)
                            : '-' }}
                    </td>

                    <td style="font-weight:500;">{{ $item->gross_weight ?: '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">No product data added.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile --}}
    <div class="mobile-products">

        @forelse($shipment->items as $item)

            <div class="product-card">

                <div class="product-title">
                    {{ $item->product_name }}
                </div>

                @if($item->description)
                    <div class="product-desc">
                        {{ $item->description }}
                    </div>
                @endif

                <div class="product-grid">

                    <div>
                        <span>HS Code</span>
                        <strong>{{ $item->hs_code ?: '-' }}</strong>
                    </div>

                    <div>
                        <span>Qty</span>
                        <strong>{{ (int)$item->quantity }} {{ $item->unit }}</strong>
                    </div>

                    <div>
                        <span>Value</span>
                        <strong>
                            {{ $item->declared_value
                                ? $item->currency.' '.number_format($item->declared_value,2)
                                : '-' }}
                        </strong>
                    </div>

                    <div>
                        <span>Weight</span>
                        <strong>{{ $item->gross_weight ?: '-' }}</strong>
                    </div>

                </div>

            </div>

        @empty

            <div class="master-empty">
                No product data added.
            </div>

        @endforelse
                
                    </div>
                
                </div>
            </div>
        </div>

        <div>
            <div class="master-card master-card--flat master-section">
                <h3 class="master-section-title">Public Readonly Link</h3>
                <div class="public-box">
                    <input class="master-input" id="publicTrackingLink" readonly value="{{ route('shipments.publicTrack', $shipment->public_token) }}">
                    <button type="button" class="master-btn master-btn-soft" onclick="maCopy('publicTrackingLink', 'Copy tracking link')">Copy</button>
                </div>
            </div>

            <div class="master-card master-card--flat master-section">
                <h3 class="master-section-title">Add Tracking Update</h3>
                <form method="POST" action="{{ route('shipments.history.store', $shipment) }}" class="history-form">
                    @csrf
                    <div class="row"><select class="master-select" name="status">@foreach($statusOptions as $key=>$label)<option value="{{ $key }}" @selected($shipment->status === $key)>{{ $label }}</option>@endforeach</select><input class="master-input" name="location" placeholder="Location"></div>
                    <input class="master-input" type="datetime-local" name="event_time" value="{{ now('Asia/Kolkata')->format('Y-m-d\TH:i') }}">
                    <textarea class="master-textarea" name="remarks" placeholder="Tracking remarks"></textarea>
                    <label class="master-check"><input type="checkbox" name="is_public" value="1" checked> Show on public tracking</label>
                    <label class="master-check"><input type="checkbox" name="notify_client" value="1" @checked($shipment->client_id && $shipment->show_client_portal)> Notify client on status change</label>
                    <button class="master-btn master-btn-primary" type="submit">Add History</button>
                </form>
            </div>
            
            @include('shipments.partials.documents-card')

            <div class="master-card master-card--flat master-section">
                <h3 class="master-section-title">Shipment Photos</h3>
                <div class="photo-grid">
                    
                    @forelse($shipment->attachments as $attachment)
                    
                        
                        <a class="photo-card"
                           href="{{ asset('storage/'.$attachment->file_path) }}"
                           target="_blank">
                            @if (str_starts_with($attachment->mime_type, 'image/'))
                                <img
                                    src="{{ asset('storage/'.$attachment->file_path) }}"
                                    alt="{{ $attachment->title ?: $attachment->original_name }}">
                                
                            @else

                                <div class="file-card">
                                        <i class="file-icon fa-solid fa-file"></i>
                                </div>
                                
                                <div class="file-name">
                                    {{ $attachment->original_name }}
                                </div>
                        
                            @endif
                
                            <div class="photo-card-body">
                                <div class="photo-meta">
                                    {{ $attachment->is_public ? 'Public' : 'Internal Only' }}
                                </div>
                            </div>
                
                        </a>
                    @empty
                        <p style="color:var(--master-muted);font-weight:600;">
                            No shipment photos uploaded yet.
                        </p>
                    @endforelse
                </div>
            </div>

            <div class="master-card master-card--flat master-section">
                <h3 class="master-section-title">Tracking History</h3>
                <div class="timeline">
                    @forelse($shipment->histories as $history)
                        @php($historyStatusClass = str_replace('_', '-', $history->status))
                        <div class="timeline-item">
                            <div class="timeline-title"><span class="master-badge status-{{ $historyStatusClass }}">{{ $statusOptions[$history->status] ?? $history->status }}</span></div>
                            <div class="timeline-meta">{{ $history->event_time ? $history->event_time->format('d M Y, h:i A') : '-' }} @if($history->location) · {{ $history->location }} @endif</div>
                            @if($history->remarks)<div class="timeline-remarks">{{ $history->remarks }}</div>@endif
                            <div class="timeline-meta">{{ $history->is_public ? 'Public' : 'Internal only' }}</div>
                        </div>
                    @empty
                        <p style="color:var(--master-muted);font-weight:600;">No tracking history added yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
