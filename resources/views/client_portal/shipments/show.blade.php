@extends('client_portal.layouts.app')

@section('title', $shipment->identity_name)
@section('page-title', 'Shipment Detail')

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/master-media.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/shipments.css') }}">
@endpush
<div class="cp-page-head cp-record-head">
    <div>
        <a class="cp-back-link" href="{{ route('client-portal.shipments.index') }}"><i class="fa-solid fa-arrow-left"></i> Shipments</a>
        <p class="cp-eyebrow">{{ $shipment->shipment_number }}</p>
        <h1>{{ $shipment->identity_name }}</h1>
        <p>{{ $shipment->from_name ?: 'Origin' }} → {{ $shipment->to_name ?: 'Destination' }} · {{ $shipment->statusLabel() }}</p>
    </div>
    @if($shipment->tracking_number)<span class="cp-status-pill"><i class="fa-solid fa-location-dot"></i>&nbsp; {{ $shipment->tracking_number }}</span>@endif
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
                        <strong>{{ $item->product_name }}</strong>
                        <span class="master-sub">{{ $item->description }}</span>
                    </td>

                    <td>{{ $item->hs_code ?: '-' }}</td>

                    <td>{{ (int)$item->quantity }} {{ $item->unit }}</td>
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
                        <p class="cp-muted">
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
                        <p class="cp-muted">No tracking history added yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
    <div class="cp-grid-2 cp-content-grid">
        <section class="cp-card cp-section-card">
            <div class="cp-section-heading"><div><p class="cp-eyebrow">Comments</p><h2>Shipment discussion</h2></div></div>
            <form method="POST" action="{{ route('client-portal.shipments.comments.store', $shipment->id) }}" class="cp-inline-form">@csrf<div class="master-field"><label class="master-label" for="shipment-comment">Comment</label><textarea class="master-textarea" id="shipment-comment" name="body" rows="4" maxlength="4000" required></textarea></div><button class="master-btn master-btn-primary" type="submit">Submit comment</button></form>
            <div class="cp-comment-list">@forelse($comments as $comment)<article class="cp-comment"><div class="cp-comment-head"><strong>{{ $comment->authorName() }}</strong><time>{{ $comment->created_at->format('d M Y, h:i A') }}</time></div><p>{{ $comment->body }}</p></article>@empty<div class="cp-empty">No comments yet.</div>@endforelse</div>
        </section>
        <section class="cp-card cp-section-card">
            <div class="cp-section-heading"><div><p class="cp-eyebrow">Documents</p><h2>Upload shipment file</h2></div><span class="cp-support-icon"><i class="fa-solid fa-cloud-arrow-up"></i></span></div>
            <form method="POST" action="{{ route('client-portal.shipments.documents.store', $shipment->id) }}" enctype="multipart/form-data" class="cp-form-grid">@csrf<div class="master-field"><label class="master-label" for="shipment-category">Category</label><select class="master-select" id="shipment-category" name="category"><option value="shipment">Shipment document</option><option value="payment_proof">Payment proof</option><option value="other">Other</option></select></div><div class="master-field"><label class="master-label" for="shipment-file-title">Title</label><input class="master-input" id="shipment-file-title" name="title"></div><div class="master-field cp-field-full"><label class="master-label" for="shipment-files">Files</label><input class="master-input" id="shipment-files" type="file" name="attachments[]" multiple required></div><div class="master-field cp-field-full"><label class="master-label" for="shipment-file-notes">Notes</label><textarea class="master-textarea" id="shipment-file-notes" name="notes"></textarea></div><div class="cp-card-actions cp-field-full"><button class="master-btn master-btn-primary" type="submit">Upload files</button></div></form>
        </section>
    </div>
@endsection
