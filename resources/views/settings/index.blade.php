@extends('layouts.app')

@section('title', 'Settings')
@section('page-title', 'Settings')

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/settings.css') }}">
@endpush

{{-- The hub: what the office can change, area by area, before it opens one.

     The rail is here too, so the hub is one more screen of the module rather
     than a page that behaves differently from the ones it links to — and the
     card of each area says what is inside it and how much of it there is, which
     is the one thing a menu cannot. --}}
<div class="set master-list">

    @include('settings.partials.nav', ['current' => null])

    <div class="set-area">

        <div class="master-card master-card--flat set-head">
            <span class="set-head-mark" aria-hidden="true"><i class="fas fa-sliders"></i></span>
            <div>
                <h1 class="set-head-title">Settings</h1>
                <p class="master-sub">
                    Every module's rules and master data, in one place. The rail on the left is the same on
                    every screen here, so you can move from one area to the next without coming back to this page —
                    and the search box on top finds a setting by name, wherever it lives.
                </p>
            </div>
        </div>

        <div class="set-grid">
            @foreach ($areas as $area)
                @php($areaCounts = array_filter($counts[$area['key']] ?? [], fn ($value) => $value !== null))

                <section class="master-card master-card--flat set-card" aria-labelledby="setCard{{ ucfirst($area['key']) }}">
                    <div class="set-card-top">
                        <span class="set-card-mark" aria-hidden="true"><i class="{{ $area['icon'] }}"></i></span>
                        <div class="set-card-text">
                            <h2 class="set-card-title" id="setCard{{ ucfirst($area['key']) }}">
                                <a href="{{ route($area['route']) }}">{{ $area['label'] }}</a>
                            </h2>
                            <p class="set-card-blurb">{{ $area['blurb'] }}</p>
                        </div>
                    </div>

                    <ul class="set-card-holds">
                        @foreach ($area['holds'] as $hold)
                            <li>{{ $hold }}</li>
                        @endforeach
                    </ul>

                    <div class="set-card-foot">
                        <p class="set-counts">
                            @foreach ($areaCounts as $label => $value)
                                <span class="set-count">{{ number_format($value) }} {{ $label }}</span>
                            @endforeach
                        </p>

                        <a class="master-btn master-btn-light master-btn-sm" href="{{ route($area['route']) }}">
                            Open {{ $area['label'] }} <i class="fas fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    </div>

                    @if ($area['links'])
                        <p class="set-card-links">
                            @foreach ($area['links'] as $link)
                                <a href="{{ route($link['route'], $link['params']) }}">{{ $link['label'] }}</a>
                            @endforeach
                        </p>
                    @endif
                </section>
            @endforeach
        </div>

        <p class="master-help set-foot-note">
            A module's own records are not settings: products, clients, office services, users and notes keep the
            pages they have. This is only where the rules and the lists behind them are changed.
        </p>

    </div>
</div>

@push('scripts')
    <script src="{{ $assetVer('assets/js/settings.js') }}" defer></script>
@endpush
@endsection
