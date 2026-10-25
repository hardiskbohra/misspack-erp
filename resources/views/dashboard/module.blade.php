@extends('layouts.app')

@section('page-title', $dashboard['label'].' Dashboard')

@section('page-actions-class', 'topbar-page-actions--compact')

@section('page-actions')
    <form method="GET" action="{{ route('dashboards.show', $module) }}" id="moduleDashboardPeriodForm">
        <div class="core-filter-toolbar">
            <x-filter-trigger drawer="moduleDashboardPeriodDrawer" label="Reporting period" :icon-only="true" />
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
                            <option value="custom" @selected($period['type'] === 'custom')>Custom date range</option>
                        </select>
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="moduleCurrency">Reporting currency</label>
                        <select class="master-select" id="moduleCurrency" name="currency">
                            @foreach(['INR', 'USD', 'RMB'] as $code)<option value="{{ $code }}" @selected($currency === $code)>{{ $code }}</option>@endforeach
                        </select>
                        @error('currency')<span class="master-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="master-field module-period-control module-period-month module-period-quarter module-period-half module-period-year">
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
                    <div class="master-field module-period-control module-period-custom">
                        <label class="master-label" for="modulePeriodStartDate">From date</label>
                        <input class="master-input" type="date" id="modulePeriodStartDate" name="start_date"
                            value="{{ $period['start']->toDateString() }}"
                            @disabled($period['type'] !== 'custom') @required($period['type'] === 'custom')>
                    </div>
                    <div class="master-field module-period-control module-period-custom">
                        <label class="master-label" for="modulePeriodEndDate">To date</label>
                        <input class="master-input" type="date" id="modulePeriodEndDate" name="end_date" min="{{ $period['start']->toDateString() }}"
                            value="{{ $period['end']->toDateString() }}"
                            @disabled($period['type'] !== 'custom') @required($period['type'] === 'custom')>
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
<link rel="stylesheet" href="{{ $assetVer('assets/css/module-dashboard.css') }}">
@endpush

@section('content')
@php
    $formatMetric = fn ($value, $format) => $format === 'money'
        ? \App\Helpers\CommonHelper::amount((float) $value, $currency)
        : number_format((float) $value, 0);
@endphp
<div class="module-dashboard">
    <section class="core-card module-reporting-summary" id="moduleReportingFilters" aria-label="Selected reporting filters">
        <div><span>Reporting period</span><strong>{{ $period['label'] }}</strong>
            @unless($period['type'] === 'custom')<small>{{ $period['start']->format('d M Y') }} – {{ $period['end']->format('d M Y') }}</small>@endunless</div>
        <span class="module-card-chip">{{ $currency }} only · no FX conversion</span>
    </section>
    <nav class="core-card module-dashboard-nav" aria-label="Module dashboards">
        @foreach($modules as $key => $definition)
            <a href="{{ route('dashboards.show', ['module' => $key] + request()->only(['period_type', 'year', 'month', 'quarter', 'half', 'start_date', 'end_date', 'currency'])) }}"
                class="module-dashboard-nav-link {{ $module === $key ? 'active' : '' }}" @if($module === $key) aria-current="page" @endif>
                <i class="{{ $definition['icon'] }}"></i><span>{{ $definition['label'] }}</span>
            </a>
        @endforeach
    </nav>

    <div class="module-section-links" aria-label="Dashboard sections">
        <a href="#moduleSummary">Summary &amp; trends</a><a href="#moduleAnalysis">Detailed analysis</a><a href="#moduleAttention">Attention queue</a>
    </div>
    <p class="module-scope-note">Live positions reflect current records as of {{ now()->format('d M Y') }}, not a historical closing balance.</p>

    @if(count($dashboard['alerts']))
        <section class="module-alert-banner" role="alert" aria-labelledby="moduleAlertTitle">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <div><span>Immediate attention</span><strong id="moduleAlertTitle">{{ count($dashboard['alerts']) }} {{ count($dashboard['alerts']) === 1 ? 'issue needs' : 'issues need' }} review</strong></div>
            <a href="#moduleAttention">Review alerts</a>
        </section>
    @endif

    <section id="moduleSummary" class="module-stat-grid" aria-label="{{ $dashboard['label'] }} statistics">
        @foreach($dashboard['metrics'] as $metric)
            <a class="core-card module-stat-card tone-{{ $metric['tone'] }}" href="{{ $metric['url'] }}">
                <div class="module-stat-heading"><span>{{ $metric['label'] }}</span><i class="{{ $dashboard['icon'] }}" aria-hidden="true"></i></div>
                <strong>{{ $formatMetric($metric['current'], $metric['format']) }}</strong>
                @if($metric['comparison'])
                    <div class="module-stat-comparison {{ $metric['change'] < 0 ? 'negative' : ($metric['change'] > 0 ? 'positive' : 'neutral') }}">
                        <i class="fa-solid fa-{{ $metric['change'] < 0 ? 'arrow-trend-down' : ($metric['change'] > 0 ? 'arrow-trend-up' : 'minus') }}" aria-hidden="true"></i>
                        @if($metric['new_activity'] ?? false) New activity · prior period zero @else {{ abs($metric['change']) }}% vs {{ $period['previousLabel'] }} @endif
                    </div>
                @else
                    <div class="module-stat-comparison neutral"><i class="fa-regular fa-clock" aria-hidden="true"></i> Current open position</div>
                @endif
                <small>{{ $metric['hint'] }}</small>
            </a>
        @endforeach
    </section>

    <div class="module-dashboard-grid">
        @include('dashboard.partials.module-trend', ['chartId' => 'moduleTrendTitle', 'chart' => [
            'title' => $dashboard['trend_label'], 'format' => $dashboard['trend_format'], 'series' => $dashboard['trend'],
            'primary_label' => $module === 'finance' ? 'Inward' : $dashboard['trend_label'], 'secondary_label' => $module === 'finance' ? 'Outward' : null]])

        <section class="core-card module-dashboard-card" aria-labelledby="moduleDistributionTitle">
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

    @foreach($dashboard['additional_trends'] as $chart)
        @include('dashboard.partials.module-trend', ['chartId' => 'moduleAdditionalTrend'.$loop->index, 'chart' => $chart])
    @endforeach

    <section id="moduleAnalysis" aria-labelledby="moduleAnalysisTitle">
        <header class="module-card-heading"><div><span>One consolidated workspace</span><h2 id="moduleAnalysisTitle">Detailed analysis</h2></div></header>
        <div class="module-analysis-grid">
            @foreach($dashboard['panels'] as $panel)
                <article class="core-card module-dashboard-card module-analysis-panel">
                    <header class="module-card-heading"><div><span>{{ $panel['scope'] === 'period' ? 'Selected period' : 'Live position' }}</span><h3>{{ $panel['title'] }}</h3></div></header>
                    <p class="module-panel-basis">{{ str_replace('_', ' ', $panel['basis']) }}</p>
                    <div class="module-analysis-table-wrap" tabindex="0" role="region" aria-label="{{ $panel['title'] }} table">
                        <table class="module-analysis-table">
                            <thead><tr>@foreach($panel['columns'] as $column)<th scope="col">{{ $column }}</th>@endforeach</tr></thead>
                            <tbody>
                                @forelse($panel['rows'] as $row)
                                    <tr>@foreach($panel['columns'] as $key => $column)
                                        @php($cellFormat = $panel['formats'][$key] ?? 'text')
                                        <td @if($cellFormat !== 'text') class="num" @endif>
                                            @if($cellFormat === 'money'){{ $formatMetric($row[$key], 'money') }}
                                            @elseif($cellFormat === 'quantity'){{ number_format((float) $row[$key], 3) }}
                                            @elseif($cellFormat === 'percent'){{ number_format((float) $row[$key], 1) }}%
                                            @elseif($cellFormat === 'number'){{ number_format((float) $row[$key]) }}
                                            @else{{ $row[$key] }}@endif
                                        </td>
                                    @endforeach</tr>
                                @empty
                                    <tr><td colspan="{{ count($panel['columns']) }}" class="module-empty-copy">No data{{ $panel['available'] ? ' for this scope.' : ' available: this source is not installed or its required fields are missing.' }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($panel['url'])<a class="module-record-link" href="{{ $panel['url'] }}">Open source records <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>@endif
                </article>
            @endforeach
        </div>
    </section>

    <section class="core-card module-dashboard-card module-attention-card" id="moduleAttention" aria-labelledby="moduleAttentionTitle">
        <header class="module-card-heading">
            <div><span>Action queue</span><h2 id="moduleAttentionTitle">Important attention</h2><p class="module-panel-basis">Up to 8 priority items; source record lists remain available for the full workload.</p></div>
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
