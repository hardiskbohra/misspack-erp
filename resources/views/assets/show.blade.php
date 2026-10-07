@extends('layouts.app')

@section('title', $asset->asset_code.' · '.$asset->name)
@section('page-title', 'Fixed asset')

@section('page-actions')
    <a class="master-btn master-btn-ghost" href="{{ route('assets.index') }}">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> All assets
    </a>
    <a class="master-btn master-btn-soft" href="{{ route('assets.depreciation', ['year' => $currentYear]) }}">
        <i class="fa-solid fa-calculator" aria-hidden="true"></i> The year's schedule
    </a>
    @unless ($asset->isDisposed())
        <button type="button" class="master-btn master-btn-primary" data-open-asset-modal="allocate">
            <i class="fa-solid fa-hand-holding-hand" aria-hidden="true"></i> Hand it over
        </button>
    @endunless
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/assets.css') }}">
@endpush
@php
    $money = fn ($amount, $currency = 'INR') => \App\Helpers\CommonHelper::amount($amount, $currency);
    $recordUrl = fn (string $key) => route('assets.show', ['asset' => $asset, 'tab' => $key]);
    $share = fn ($part) => $asset->capitalisedCost() > 0
        ? \App\Services\AssetVocabulary::percentLabel($part / $asset->capitalisedCost() * 100, 1).'%'
        : '—';
@endphp

<div class="fixed-assets master-list" data-asset-id="{{ $asset->id }}">

    {{-- ────────────────────────────────────────── the asset and its figures --}}
    <header class="master-card master-header ast-record-header">
        <div class="ast-record-identity">
            <span class="ast-record-mark" aria-hidden="true"><i class="fa-solid fa-industry"></i></span>
            <div>
                <p class="master-eyebrow">{{ $asset->asset_code }}</p>
                <h1 class="ast-record-title">{{ $asset->name }}</h1>
                <p class="master-sub">
                    {{ $asset->category?->name ?: 'Unclassified' }}
                    @if (filled($asset->make) || filled($asset->model))
                        · {{ trim($asset->make.' '.$asset->model) }}
                    @endif
                    @if (filled($asset->serial_no))
                        · <span class="ast-mono">{{ $asset->serial_no }}</span>
                    @endif
                </p>
                <div class="ast-record-badges">
                    <span class="core-badge core-badge-{{ $asset->stateTone() }}">{{ $asset->stateLabel() }}</span>
                    <span class="core-badge core-badge-{{ $asset->conditionTone() }}">{{ $asset->conditionLabel() }}</span>
                    @if ($asset->isDisposed())
                        <span class="core-badge core-badge-neutral">Off the books</span>
                    @elseif ($asset->warrantyExpiring())
                        <span class="core-badge core-badge-warning">Warranty out soon</span>
                    @elseif ($asset->verificationOverdue())
                        <span class="core-badge core-badge-danger">Not verified this year</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="ast-record-facts">
            <div>
                <span class="ast-fact-label">Purchased</span>
                <span class="ast-fact-value">{{ $asset->purchase_date?->format('d M Y') ?: '—' }}</span>
                <span class="ast-fact-sub">{{ $asset->ageLabel() ?: '' }}</span>
            </div>
            <div>
                <span class="ast-fact-label">Held by</span>
                <span class="ast-fact-value">{{ $asset->holderLabel() }}</span>
                <span class="ast-fact-sub">{{ $asset->placeLabel() }}</span>
            </div>
            <div>
                <span class="ast-fact-label">Depreciates by</span>
                <span class="ast-fact-value">{{ $asset->methodLabel() }}</span>
                <span class="ast-fact-sub">{{ $asset->recipeLabel() }}</span>
            </div>
        </div>
    </header>

    {{-- The four figures an asset's page is opened for. Every one of them is
         computed from the recipe — there is no stored book value to drift. --}}
    <div class="master-stats desktop-only">
        <div class="master-stat master-stat--flat green tooltip-container">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-indian-rupee-sign"></i></span>
            <div>
                <p class="master-stat-title">Capitalised cost</p>
                <p class="master-stat-value">{{ $money($asset->capitalisedCost()) }}</p>
                <p class="master-sub">
                    {{ $asset->claimsInputCredit()
                        ? 'cost alone — GST claimed as credit ('.$money($asset->gst_amount).')'
                        : 'cost + GST — capitalised' }}
                </p>
                <span class="tooltip-text">The basis the depreciation is charged on. The GST treatment on the asset decides which figure that is.</span>
            </div>
        </div>

        <div class="master-stat master-stat--flat teal tooltip-container">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-chart-line"></i></span>
            <div>
                <p class="master-stat-title">Net book value today</p>
                <p class="master-stat-value">{{ $money($asset->netBookValue()) }}</p>
                <p class="master-sub">{{ $share($asset->netBookValue()) }} of capitalised cost</p>
                <span class="tooltip-text">Cost less everything written off up to today. On a disposed asset it has stopped where the sale stopped it.</span>
            </div>
        </div>

        <div class="master-stat master-stat--flat orange tooltip-container">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-arrow-trend-down"></i></span>
            <div>
                <p class="master-stat-title">Written off to date</p>
                <p class="master-stat-value">{{ $money($asset->accumulatedDepreciation()) }}</p>
                <p class="master-sub">{{ $share($asset->accumulatedDepreciation()) }} of capitalised cost</p>
                <span class="tooltip-text">The total depreciation charged since purchase, financial year by financial year — the same rows the Depreciation tab prints.</span>
            </div>
        </div>

        <div class="master-stat master-stat--flat purple tooltip-container">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-calendar-day"></i></span>
            <div>
                <p class="master-stat-title">Charge this year</p>
                <p class="master-stat-value">{{ $money($chargeThisYear) }}</p>
                <p class="master-sub">financial year {{ $currentYear }}</p>
                <span class="tooltip-text">This asset's share of the year's depreciation, pro-rated to the days it was on the books during the year.</span>
            </div>
        </div>
    </div>

    {{-- ─────────────────────────────── the record, one panel per URL --}}
    <div class="master-tabs-card">
        <nav class="master-tabs" role="tablist" aria-label="Asset sections">
            @foreach ($tabs as $key => $label)
                <a class="master-tab {{ $tab === $key ? 'is-active' : '' }}" role="tab"
                    id="asset-tab-{{ $key }}" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                    href="{{ $recordUrl($key) }}">
                    {{ $label }}
                    @if (($tabCounts[$key] ?? 0) > 0)
                        <span class="master-tab-count">{{ number_format($tabCounts[$key]) }}</span>
                    @endif
                </a>
            @endforeach
        </nav>

        <div class="master-tabs-panels">
            @if ($tab === 'allocation')
                @include('assets.partials.tab-allocation')
            @elseif ($tab === 'maintenance')
                @include('assets.partials.tab-maintenance')
            @elseif ($tab === 'depreciation')
                @include('assets.partials.tab-depreciation')
            @else
                @include('assets.partials.tab-overview')
            @endif
        </div>
    </div>
</div>

{{-- ───────────────────────────────────────────── the doors, one dialog each --}}
<span hidden data-open-dialog="{{ $errors->any() ? old('_dialog') : '' }}"></span>

@include('assets.partials.modal-edit')
@include('assets.partials.modal-allocate', ['asset' => $asset])
@if ($asset->openAllocation)
    @include('assets.partials.modal-return', ['asset' => $asset])
@endif
@include('assets.partials.modal-maintenance', ['asset' => $asset])
@include('assets.partials.modal-verify', ['asset' => $asset])
@unless ($asset->isDisposed())
    @include('assets.partials.modal-dispose', ['asset' => $asset])
@endunless
@endsection

@push('scripts')
    <script src="{{ $assetVer('assets/js/assets.js') }}" defer></script>
@endpush
