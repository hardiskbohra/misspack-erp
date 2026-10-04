@extends('layouts.app')

@section('page-title', 'Lead Product Image')

@section('content')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/leads.css') }}">
@endpush
<div class="image-view-page">
    <div class="image-card image-header">
        <div>
            <h1>{{ $title }}</h1>
            <p>{{ $subtitle }}</p>
        </div>
        <div class="image-actions">
            <a href="{{ $backUrl }}" class="image-btn image-btn-light">Back</a>
            @if($imagePath)
                <a href="{{ asset('storage/'.$imagePath) }}" target="_blank" class="image-btn image-btn-soft">Open Original</a>
                <a href="{{ asset('storage/'.$imagePath) }}" download class="image-btn image-btn-primary">Download Image</a>
            @endif
        </div>
    </div>

    <div class="image-card image-stage">
        <div class="image-frame">
            @if($imagePath)
                <img src="{{ asset('storage/'.$imagePath) }}" alt="{{ $title }}">
            @else
                <div class="image-empty">
                    <div class="icon">📦</div>
                    <div>No product image uploaded for this lead.</div>
                </div>
            @endif
        </div>
    </div>

    <div class="image-meta">
        <div class="image-meta-item"><span>Lead Number</span><strong>{{ $lead->lead_number }}</strong></div>
        <div class="image-meta-item"><span>Product</span><strong>{{ $lead->product_name ?: '-' }}</strong></div>
        <div class="image-meta-item"><span>Client</span><strong>{{ $lead->client?->company_name ?? $lead->client_company_name ?? '-' }}</strong></div>
        <div class="image-meta-item"><span>Status</span><strong>{{ $lead->statusLabel() }}</strong></div>
    </div>
</div>
@endsection
