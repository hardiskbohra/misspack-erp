@extends('layouts.app')

@section('page-title', $dashboard['label'].' Dashboard')

@section('page-actions')
    <form method="GET" action="{{ route('dashboards.show', $module) }}" id="moduleDashboardPeriodForm">
        <div class="core-filter-toolbar">
            <x-filter-trigger drawer="moduleDashboardPeriodDrawer" label="Reporting period" />
            <span class="module-period-summary">{{ $period['label'] }} <small>{{ $period['start']->format('d M Y') }} – {{ $period['end']->format('d M Y') }}</small></span>
        </div>
        <x-drawer id="moduleDashboardPeriodDrawer" title="Choose reporting period" eyebrow="Module dashboard"
            subtitle="Use the same period for totals, trends and the like-for-like comparison." size="medium">
            <section class="core-drawer-section">
                <h3 class="core-drawer-section-title">Reporting window</h3>
                <div class="core-drawer-fields">
                    <div class="master-field">
                        <label class="master-label" for="modulePeriodType">View</label>
                        <select class="master-select" name="period_type" id="modulePeriodType">
                            <option value="month" @selected($period['type'] === 'month')>Month</option>
                            <option value="quarter" @selected($period['type'] === 'quarter')>Quarter</option>
                            <option value="half" @selected($period['type'] === 'half')>Half year</option>
                            <option value="year" @selected($period['type'] === 'year')>Year</option>
                        </select>
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="modulePeriodYear">Year</label>
                        <select class="master-select" name="year" id="modulePeriodYear">
                            @foreach($availableYears as $year)
                                <option value="{{ $year }}" @selected((int) $period['year'] === $year)>{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field module-period-control module-period-month">
                        <label class="master-label" for="modulePeriodMonth">Month</label>
                        <select class="master-select" name="month" id="modulePeriodMonth">
                            @foreach(range(1, 12) as $month)
                                <option value="{{ $month }}" @selected((int) $period['month'] === $month)>{{ \Carbon\Carbon::create(null, $month)->format('F') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field module-period-control module-period-quarter">
                        <label class="master-label" for="modulePeriodQuarter">Quarter</label>
                        <select class="master-select" name="quarter" id="modulePeriodQuarter">
                            @foreach(range(1, 4) as $quarter)
                                <option value="{{ $quarter }}" @selected((int) $period['quarter'] === $quarter)>Q{{ $quarter }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field module-period-control module-period-half">
                        <label class="master-label" for="modulePeriodHalf">Half year</label>
                        <select class="master-select" name="half" id="modulePeriodHalf">
                            <option value="1" @selected((int) $period['half'] === 1)>H1 · Jan–Jun</option>
                            <option value="2" @selected((int) $period['half'] === 2)>H2 · Jul–Dec</option>
                        </select>
                    </div>
                </div>
            </section>
            <x-slot:footer>
                <button type="button" class="master-btn" data-drawer-close>Cancel</button>
                <button type="submit" class="master-btn primary">Apply period</button>
            </x-slot:footer>
        </x-drawer>
    </form>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/module-dashboard.css') }}">
@endpush

@section('content')
@php
    $formatMetric = fn ($value, $format) => $format === 'money'
        ? \App\Helpers\CommonHelper::amount((float) $value, 'INR')
        : number_format((float) $value, 0);
    $trendMaximum = max(1, collect($dashboard['trend'])->max(fn ($point) => max((float) $point['value'], (float) ($point['secondary'] ?? 0))));
@endphp
<div class="module-dashboard">
    <nav class="module-dashboard-nav" aria-label="Module dashboards">
        <a href="{{ route('dashboard') }}" class="module-dashboard-nav-link"><i class="fa-solid fa-gauge-high"></i><span>Overview</span></a>
        @foreach($modules as $key => $definition)
            <a href="{{ route('dashboards.show', ['module' => $key] + request()->only(['period_type', 'year', 'month', 'quarter', 'half'])) }}"
                class="module-dashboard-nav-link {{ $module === $key ? 'active' : '' }}" @if($module === $key) aria-current="page" @endif>
                <i class="{{ $definition['icon'] }}"></i><span>{{ $definition['label'] }}</span>
            </a>
        @endforeach
    </nav>

    @if(count($dashboard['alerts']))
        <section class="module-alert-banner" role="alert" aria-labelledby="moduleAlertTitle">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <div><span>Immediate attention</span><strong id="moduleAlertTitle">{{ count($dashboard['alerts']) }} {{ count($dashboard['alerts']) === 1 ? 'issue needs' : 'issues need' }} review</strong></div>
            <a href="#moduleAttention">Review alerts</a>
        </section>
    @endif

    <section class="module-stat-grid" aria-label="{{ $dashboard['label'] }} statistics">
        @foreach($dashboard['metrics'] as $metric)
            <a class="module-stat-card tone-{{ $metric['tone'] }}" href="{{ $metric['url'] }}">
                <div class="module-stat-heading"><span>{{ $metric['label'] }}</span><i class="{{ $dashboard['icon'] }}" aria-hidden="true"></i></div>
                <strong>{{ $formatMetric($metric['current'], $metric['format']) }}</strong>
                @if($metric['comparison'])
                    <div class="module-stat-comparison {{ $metric['change'] < 0 ? 'negative' : ($metric['change'] > 0 ? 'positive' : 'neutral') }}">
                        <i class="fa-solid fa-{{ $metric['change'] < 0 ? 'arrow-trend-down' : ($metric['change'] > 0 ? 'arrow-trend-up' : 'minus') }}" aria-hidden="true"></i>
                        {{ abs($metric['change']) }}% vs {{ $period['previousLabel'] }}
                    </div>
                @else
                    <div class="module-stat-comparison neutral"><i class="fa-regular fa-clock" aria-hidden="true"></i> Current open position</div>
                @endif
                <small>{{ $metric['hint'] }}</small>
            </a>
        @endforeach
    </section>

    <div class="module-dashboard-grid">
        <section class="module-dashboard-card module-trend-card" aria-labelledby="moduleTrendTitle">
            <header class="module-card-heading">
                <div><span>Selected period</span><h2 id="moduleTrendTitle">{{ $dashboard['trend_label'] }}</h2></div>
                <span class="module-card-chip">{{ $period['label'] }}</span>
            </header>
            <div class="module-trend-legend">
                <span><i class="primary"></i>{{ $module === 'finance' ? 'Inward' : $dashboard['trend_label'] }}</span>
                @if($module === 'finance')<span><i class="secondary"></i>Outward</span>@endif
            </div>
            <div class="module-trend-bars">
                @foreach($dashboard['trend'] as $point)
                    <div class="module-trend-item">
                        <div class="module-trend-values">
                            <progress max="{{ $trendMaximum }}" value="{{ $point['value'] }}" aria-label="{{ $point['label'] }} {{ $module === 'finance' ? 'inward' : 'value' }}"></progress>
                            @if(array_key_exists('secondary', $point))
                                <progress class="secondary" max="{{ $trendMaximum }}" value="{{ $point['secondary'] }}" aria-label="{{ $point['label'] }} outward"></progress>
                            @endif
                        </div>
                        <span>{{ $point['label'] }}</span>
                        <strong>{{ $formatMetric($point['value'], $dashboard['trend_format']) }}@if(array_key_exists('secondary', $point)) <small>/ {{ $formatMetric($point['secondary'], $dashboard['trend_format']) }}</small>@endif</strong>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="module-dashboard-card" aria-labelledby="moduleDistributionTitle">
            <header class="module-card-heading"><div><span>Live workload</span><h2 id="moduleDistributionTitle">Status distribution</h2></div></header>
            <div class="module-distributions">
                @foreach($dashboard['breakdowns'] as $breakdown)
                    @php
                        $distributionMax = max(1, collect($breakdown['items'])->max('value') ?: 1);
                    @endphp
                    <div class="module-distribution-group">
                        <h3>{{ $breakdown['title'] }}</h3>
                        @forelse($breakdown['items'] as $item)
                            <div class="module-distribution-row">
                                <span>{{ $item['label'] ?: 'Not set' }}</span><strong>{{ $item['value'] }}</strong>
                                <progress max="{{ $distributionMax }}" value="{{ $item['value'] }}" aria-label="{{ $item['label'] }} {{ $item['value'] }}"></progress>
                            </div>
                        @empty
                            <p class="module-empty-copy">No status data is available yet.</p>
                        @endforelse
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    <section class="module-dashboard-card module-attention-card" id="moduleAttention" aria-labelledby="moduleAttentionTitle">
        <header class="module-card-heading">
            <div><span>Action queue</span><h2 id="moduleAttentionTitle">Important attention</h2></div>
            <span class="module-card-chip {{ count($dashboard['alerts']) ? 'danger' : '' }}">{{ count($dashboard['alerts']) }} open</span>
        </header>
        @forelse($dashboard['alerts'] as $alert)
            <a class="module-attention-row" href="{{ $alert['url'] }}">
                <span class="module-attention-icon {{ $alert['level'] }}"><i class="fa-solid fa-exclamation" aria-hidden="true"></i></span>
                <span><strong>{{ $alert['title'] }}</strong><small>{{ $alert['message'] }}</small></span>
                <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
            </a>
        @empty
            <div class="module-empty-state"><i class="fa-regular fa-circle-check" aria-hidden="true"></i><strong>No urgent exceptions</strong><span>There are no critical items in this module queue right now.</span></div>
        @endforelse
    </section>
</div>
@endsection

@push('scripts')
<script src="{{ $assetVer('assets/js/module-dashboard.js') }}"></script>
@endpush
