@extends('client_portal.layouts.app')

@section('title', $shipment->identity_name)
@section('page-title', 'Shipment Detail')

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/master-media.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/shipments.css') }}">
@endpush
<div class="master-card master-header" style="margin-bottom:15px;line-height:1.4;">
    <div>
        <p style="font-size:16px;font-weight:500;">{{ $shipment->shipment_number }}</p>
        <h1>{{ $shipment->identity_name }}</h1>
    </div>
</div>

@php($statusClass = str_replace('_', '-', $shipment->status))

<div class="master-grid">
        <div>
            <div class="master-card master-section">
                <h3 class="master-section-title">Shipment Overview</h3>
                <div class="master-info-grid">
                    <div class="master-info"><span>Status</span><strong class="master-badge status-{{ $statusClass }}">{{ $shipment->statusLabel() }}</strong></div>
                    <div class="master-info"><span>Pickup Date</span><strong>{{ $shipment->pickup_date ? $shipment->pickup_date->format('d M Y') : '-' }}</strong></div>
                    <div class="master-info">
                        <span>Expected Delivery</span>
                        <strong class="ship-eta ship-eta-{{ $shipment->etaState() }}">{{ $shipment->eta_date ? $shipment->eta_date->format('d M Y') : 'Not set' }}</strong>
                        @if ($shipment->eta_date)
                            <span>{{ $shipment->etaLabel() }}</span>
                        @endif
                    </div>
                    <div class="master-info"><span>Logistic Partner</span><strong>{{ $shipment->logistic_partner ?: '-' }}</strong></div>
                    <div class="master-info"><span>Tracking Number</span><strong>{{ $shipment->tracking_number ?: '-' }}</strong></div>
                    <div class="master-info"><span>Project</span><strong>{{ $shipment->project->name ?? '-' }}</strong>
                        <span>{{ $shipment->project->project_number ?? '-' }}</span></div>

                </div>
            </div>

            <div class="master-card master-section">
                <h3 class="master-section-title">Tracking Progress</h3>
                @include('shipments.partials.tracker', ['shipment' => $shipment, 'showDelayReason' => false])
            </div>

            <div class="master-card master-section">
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
                    </div>
                </div>
            </div>

            <div class="master-card master-section">
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

            <div class="master-card master-section">
                <h3 class="master-section-title">Shipment Photos</h3>
                <div class="photo-grid">

                    @forelse($shipment->publicAttachments as $attachment)
                        <a class="photo-card"
                           href="{{ route('client-portal.shipments.attachments.file', [$shipment->id, $attachment->id]) }}"
                           target="_blank" rel="noopener">
                            @if (in_array(strtolower(pathinfo($attachment->file_path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) && str_starts_with(strtolower((string) $attachment->mime_type), 'image/'))
                                <img
                                    src="{{ route('client-portal.shipments.attachments.file', [$shipment->id, $attachment->id]) }}"
                                    alt="{{ $attachment->title ?: $attachment->original_name }}">
                            @else
                                <div class="file-card">
                                        <i class="file-icon fa-solid fa-file"></i>
                                </div>
                                <div class="file-name">
                                    {{ $attachment->original_name }}
                                </div>
                            @endif
                        </a>
                    @empty
                        <p style="color:var(--master-muted);font-weight:600;">
                            No shipment photos uploaded yet.
                        </p>
                    @endforelse
                </div>
            </div>

            @if($documents->isNotEmpty())
                <div class="master-card master-section">
                    <h3 class="master-section-title">Files shared in this portal</h3>
                    <div class="cp-document-grid">
                        @foreach($documents as $document)
                            <article class="cp-document-card">
                                @if($document->isImage())
                                    <a class="cp-document-preview" href="{{ route('client-portal.attachments.file', $document) }}" target="_blank" rel="noopener"><img src="{{ route('client-portal.attachments.file', $document) }}" alt="{{ $document->title ?: $document->original_name }}"></a>
                                @else
                                    <span class="cp-file-icon"><i class="fa-solid fa-file-lines"></i></span>
                                @endif
                                <div class="cp-document-copy"><strong>{{ $document->title ?: $document->original_name }}</strong><small>{{ $document->created_at->format('d M Y') }}</small></div>
                                <div class="cp-document-actions"><a class="master-btn master-btn-soft master-btn-sm" href="{{ route('client-portal.attachments.file', $document) }}?download=1">Download</a></div>
                            </article>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="master-card master-section">
                <h3 class="master-section-title">Tracking History</h3>
                <div class="timeline">
                    @forelse($publicHistories as $history)
                        @php($historyStatusClass = str_replace('_', '-', $history->status))
                        <div class="timeline-item">
                            <div class="timeline-title"><span class="master-badge status-{{ $historyStatusClass }}">{{ $statusOptions[$history->status] ?? $history->status }}</span></div>
                            <div class="timeline-meta">{{ $history->event_time ? $history->event_time->format('d M Y, h:i A') : '-' }} @if($history->location) · {{ $history->location }} @endif</div>
                            @if($history->remarks)<div class="timeline-remarks">{{ $history->remarks }}</div>@endif
                        </div>
                    @empty
                        <p style="color:var(--master-muted);font-weight:600;">No tracking history added yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
    <div class="cp-grid-2" style="margin-top:18px;">
        <div class="cp-card" style="padding:20px;"><p class="cp-eyebrow">Comments</p><h2 style="margin-top:0;">Shipment Discussion</h2><form method="POST" action="{{ route('client-portal.shipments.comments.store', $shipment->id) }}" style="margin-bottom:15px;">@csrf<div class="master-field"><label class="master-label">Comment</label><textarea class="master-textarea" name="body" maxlength="4000" required></textarea></div><button class="master-btn master-btn-primary" style="margin-top:10px;">Submit Comment</button></form>@forelse($comments as $comment)<div class="cp-comment"><div class="cp-comment-head"><strong>{{ $comment->authorName() }}</strong><span>{{ $comment->created_at->format('d M Y, h:i A') }}</span></div><p>{{ $comment->body }}</p></div>@empty<div class="cp-empty">No comments yet.</div>@endforelse</div>
        <div class="cp-card" style="padding:20px;"><p class="cp-eyebrow">Upload</p><h2 style="margin-top:0;">Upload Shipment Document</h2><form method="POST" action="{{ route('client-portal.shipments.documents.store', $shipment->id) }}" enctype="multipart/form-data" class="cp-form-grid">@csrf<div class="master-field"><label class="master-label">Category</label><select class="master-select" name="category"><option value="shipment">Shipment Document</option><option value="payment_proof">Payment Proof</option><option value="other">Other</option></select></div><div class="master-field"><label class="master-label">Title</label><input class="master-input" name="title"></div><div class="master-field" style="grid-column:1/-1;"><label class="master-label">Files</label><input class="master-input" type="file" name="attachments[]" multiple required></div><div class="master-field" style="grid-column:1/-1;"><label class="master-label">Notes</label><textarea class="master-textarea" name="notes"></textarea></div><button class="master-btn master-btn-primary" style="grid-column:1/-1;">Upload</button></form></div>
    </div>
@endsection
