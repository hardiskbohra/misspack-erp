@extends('layouts.app')

@section('title', $vendor->vendor_name)
@section('page-title', 'Vendor profile')

@section('content')
@php
    $statusClass = str_replace('_', '-', $vendor->status);
    $typeClass = str_replace('_', '-', $vendor->vendor_type);
    $initials = collect(explode(' ', trim($vendor->vendor_name)))
        ->filter()
        ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
        ->take(2)
        ->implode('');
    $formatAddress = fn (...$parts) => collect($parts)->filter(fn ($part) => filled($part))->implode(', ');
    $vendorAddress = $formatAddress($vendor->address, $vendor->city, $vendor->state, $vendor->country, $vendor->pincode);
    $tabUrl = fn (string $key) => route('vendors.show', ['vendor' => $vendor, 'tab' => $key]);
    $money = fn ($amount, $currency = 'INR') => \App\Helpers\CommonHelper::amount($amount, $currency);
@endphp

<div class="vendor vendor-show" data-vendor-id="{{ $vendor->id }}" data-vendor-tab="{{ $tab }}">
    <header class="master-card master-header vendor-record-header">
        <div class="vendor-record-identity">
            @if ($vendor->image_path)
                <img class="vendor-record-avatar" src="{{ asset('storage/'.$vendor->image_path) }}" alt="">
            @else
                <span class="vendor-record-avatar vendor-record-avatar--initials" aria-hidden="true">{{ $initials }}</span>
            @endif
            <div class="vendor-record-copy">
                <h1>{{ $vendor->vendor_name }}</h1>
                <div class="vendor-record-meta">
                    <span>{{ $vendor->vendor_number ?: 'Vendor record' }}</span>
                    @if ($vendor->brand_name)
                        <span aria-hidden="true">·</span><span>{{ $vendor->brand_name }}</span>
                    @endif
                    @if ($vendor->country)
                        <span aria-hidden="true">·</span><span>{{ $vendor->country }}</span>
                    @endif
                    <span class="master-badge vendor-status vendor-status-{{ $statusClass }}">{{ $vendor->statusLabel() }}</span>
                    <span class="master-chip vendor-type vendor-type-{{ $typeClass }}"><i class="fa-solid fa-industry" aria-hidden="true"></i> {{ $vendor->typeLabel() }}</span>
                    <span class="master-chip"><i class="fa-solid fa-coins" aria-hidden="true"></i> {{ $vendor->preferred_currency ?: 'INR' }}</span>
                </div>
            </div>
        </div>
        <nav class="vendor-record-actions" aria-label="Vendor actions">
            <a href="{{ route('vendors.index') }}" class="master-btn master-btn-light"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Vendors</a>
            <a href="{{ route('vendors.edit', $vendor) }}" class="master-btn master-btn-primary"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit vendor</a>
            <a href="{{ $tabUrl('statement') }}" class="master-btn master-btn-soft"><i class="fa-solid fa-file-invoice" aria-hidden="true"></i> Statement</a>
        </nav>
    </header>

    <div class="master-tabs-card vendor-detail-tabs-card">
        <nav class="master-tabs" role="tablist" aria-label="Vendor profile sections">
            @foreach ($tabs as $key => $label)
                @php
                    $tabCount = match ($key) {
                        'quotes' => $summary['vendor_quotes_count'],
                        'projects' => $summary['project_products_count'],
                        'products' => $summary['products_count'],
                        'payments' => $summary['statement_count'],
                        'shipments' => $summary['shipments_count'],
                        'attachments' => $summary['attachments_count'],
                        'comments' => $summary['comments_count'],
                        default => null,
                    };
                @endphp
                <a class="master-tab {{ $tab === $key ? 'is-active' : '' }}"
                    id="vendor-tab-{{ $key }}" role="tab"
                    aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                    aria-controls="vendor-panel-{{ $key }}"
                    @if ($tab === $key) aria-current="page" @endif
                    href="{{ $tabUrl($key) }}">
                    {{ $label }}
                    @if ($tabCount !== null)
                        <span class="master-tab-count">{{ number_format($tabCount) }}</span>
                    @endif
                </a>
            @endforeach
        </nav>

        <div class="master-tabs-panels vendor-detail-panels">
            @if ($tab === 'overview')
                <section class="master-tab-panel" id="vendor-panel-overview" role="tabpanel" aria-labelledby="vendor-tab-overview">
                    @include('vendors.partials.overview')
                </section>
            @elseif ($tab === 'profile')
                <section class="master-tab-panel" id="vendor-panel-profile" role="tabpanel" aria-labelledby="vendor-tab-profile">
                    @include('vendors.partials.profile')
                </section>
            @elseif ($tab === 'contacts')
                <section class="master-tab-panel" id="vendor-panel-contacts" role="tabpanel" aria-labelledby="vendor-tab-contacts">
                    @include('vendors.partials.contacts')
                </section>
            @elseif ($tab === 'commercial')
                <section class="master-tab-panel" id="vendor-panel-commercial" role="tabpanel" aria-labelledby="vendor-tab-commercial">
                    @include('vendors.partials.commercial')
                </section>
            @elseif ($tab === 'quotes')
                <section class="master-tab-panel" id="vendor-panel-quotes" role="tabpanel" aria-labelledby="vendor-tab-quotes">
                    @include('vendors.partials.quotes')
                </section>
            @elseif ($tab === 'projects')
                <section class="master-tab-panel" id="vendor-panel-projects" role="tabpanel" aria-labelledby="vendor-tab-projects">
                    @include('vendors.partials.projects')
                </section>
            @elseif ($tab === 'products')
                <section class="master-tab-panel" id="vendor-panel-products" role="tabpanel" aria-labelledby="vendor-tab-products">
                    @include('vendors.partials.products')
                </section>
            @elseif ($tab === 'payments')
                <section class="master-tab-panel" id="vendor-panel-payments" role="tabpanel" aria-labelledby="vendor-tab-payments">
                    @include('vendors.partials.payments')
                </section>
            @elseif ($tab === 'statement')
                <section class="master-tab-panel" id="vendor-panel-statement" role="tabpanel" aria-labelledby="vendor-tab-statement">
                    @include('vendors.partials.statement')
                </section>
            @elseif ($tab === 'shipments')
                <section class="master-tab-panel" id="vendor-panel-shipments" role="tabpanel" aria-labelledby="vendor-tab-shipments">
                    @include('vendors.partials.shipments')
                </section>
            @elseif ($tab === 'attachments')
                <section class="master-tab-panel" id="vendor-panel-attachments" role="tabpanel" aria-labelledby="vendor-tab-attachments">
                    @include('vendors.partials.attachments')
                </section>
            @elseif ($tab === 'comments')
                <section class="master-tab-panel" id="vendor-panel-comments" role="tabpanel" aria-labelledby="vendor-tab-comments">
                    @include('vendors.partials.comments')
                </section>
            @endif
        </div>
    </div>
</div>

@push('scripts')
    <script src="{{ asset('assets/js/vendors.js') }}"></script>
@endpush
@endsection
