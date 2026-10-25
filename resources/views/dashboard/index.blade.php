@extends('layouts.app')

@section('page-title', 'ERP Overview Dashboard')

@section('page-actions')
    <form method="GET" action="{{ route('dashboard') }}" id="dashPeriodForm">
        <div class="core-filter-toolbar">
            <x-filter-trigger drawer="dashboardPeriodDrawer" label="Reporting period" />
            <div class="master-period-badge">{{ $period['label'] }}<span>{{ $start->format('d M Y') }} - {{ $end->format('d M Y') }}</span></div>
        </div>
        <x-drawer id="dashboardPeriodDrawer" title="Choose reporting period" eyebrow="Dashboard range"
            subtitle="Change the reporting window used by the dashboard charts and totals." size="medium">
            <section class="core-drawer-section">
                <h3 class="core-drawer-section-title">Period</h3>
                <div class="core-drawer-fields">
                    <div class="master-field">
                        <label class="master-label" for="periodType">View</label>
                        <select class="master-select" name="period_type" id="periodType">
                            <option value="range" @selected($period['type'] === 'range')>Rolling days</option>
                            <option value="month" @selected($period['type'] === 'month')>Month</option>
                            <option value="quarter" @selected($period['type'] === 'quarter')>Quarter</option>
                            <option value="half" @selected($period['type'] === 'half')>Half year</option>
                            <option value="year" @selected($period['type'] === 'year')>Single year</option>
                            <option value="multi_year" @selected($period['type'] === 'multi_year')>Multi-year</option>
                            <option value="custom" @selected($period['type'] === 'custom')>Custom date range</option>
                        </select>
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="overviewCurrency">Reporting currency</label>
                        <select class="master-select" id="overviewCurrency" name="currency">
                            @foreach(['INR', 'USD', 'RMB'] as $code)<option value="{{ $code }}" @selected($currency === $code)>{{ $code }}</option>@endforeach
                        </select>
                        @error('currency')<span class="master-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="master-field period-control period-range">
                        <label class="master-label" for="periodRange">Range</label>
                        <select class="master-select" id="periodRange" name="range">
                            <option value="7" @selected((int) ($period['range'] ?? 30) === 7)>7 days</option>
                            <option value="30" @selected((int) ($period['range'] ?? 30) === 30)>30 days</option>
                            <option value="90" @selected((int) ($period['range'] ?? 30) === 90)>90 days</option>
                            <option value="180" @selected((int) ($period['range'] ?? 30) === 180)>180 days</option>
                            <option value="365" @selected((int) ($period['range'] ?? 30) === 365)>365 days</option>
                        </select>
                    </div>
                    <div class="master-field period-control period-year period-month period-quarter period-half">
                        <label class="master-label" for="periodYear">Year</label>
                        <select class="master-select" id="periodYear" name="year">
                            @foreach($availableYears as $year)
                                <option value="{{ $year }}" @selected((int) ($period['year'] ?? now()->year) === (int) $year)>{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field period-control period-month">
                        <label class="master-label" for="periodMonth">Month</label>
                        <select class="master-select" id="periodMonth" name="month">
                            @foreach(range(1, 12) as $month)
                                <option value="{{ $month }}" @selected((int) ($period['month'] ?? now()->month) === $month)>{{ \Carbon\Carbon::create(null, $month)->format('F') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field period-control period-quarter">
                        <label class="master-label" for="periodQuarter">Quarter</label>
                        <select class="master-select" id="periodQuarter" name="quarter">
                            <option value="1" @selected((int) ($period['quarter'] ?? 1) === 1)>Q1</option>
                            <option value="2" @selected((int) ($period['quarter'] ?? 1) === 2)>Q2</option>
                            <option value="3" @selected((int) ($period['quarter'] ?? 1) === 3)>Q3</option>
                            <option value="4" @selected((int) ($period['quarter'] ?? 1) === 4)>Q4</option>
                        </select>
                    </div>
                    <div class="master-field period-control period-half">
                        <label class="master-label" for="periodHalf">Half</label>
                        <select class="master-select" id="periodHalf" name="half">
                            <option value="1" @selected((int) ($period['half'] ?? 1) === 1)>H1</option>
                            <option value="2" @selected((int) ($period['half'] ?? 1) === 2)>H2</option>
                        </select>
                    </div>
                    <div class="master-field period-control period-multi_year">
                        <label class="master-label" for="periodFromYear">From</label>
                        <select class="master-select" id="periodFromYear" name="from_year">
                            @foreach($availableYears as $year)
                                <option value="{{ $year }}" @selected((int) ($period['from_year'] ?? now()->year - 2) === (int) $year)>{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field period-control period-multi_year">
                        <label class="master-label" for="periodToYear">To</label>
                        <select class="master-select" id="periodToYear" name="to_year">
                            @foreach($availableYears as $year)
                                <option value="{{ $year }}" @selected((int) ($period['to_year'] ?? now()->year) === (int) $year)>{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field period-control period-custom">
                        <label class="master-label" for="periodStartDate">From date</label>
                        <input class="master-input" type="date" id="periodStartDate" name="start_date"
                            value="{{ $period['start']->toDateString() }}"
                            @disabled($period['type'] !== 'custom') @required($period['type'] === 'custom')>
                    </div>
                    <div class="master-field period-control period-custom">
                        <label class="master-label" for="periodEndDate">To date</label>
                        <input class="master-input" type="date" id="periodEndDate" name="end_date" min="{{ $period['start']->toDateString() }}"
                            value="{{ $period['end']->toDateString() }}"
                            @disabled($period['type'] !== 'custom') @required($period['type'] === 'custom')>
                    </div>
                </div>
            </section>
            <x-slot:footer>
                <button class="master-btn master-btn-primary" type="submit">
                    <i class="fa-solid fa-filter" aria-hidden="true"></i> Apply period
                </button>
            </x-slot:footer>
        </x-drawer>
    </form>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ $assetVer('assets/css/module-dashboard.css') }}">
<link rel="stylesheet" href="{{ $assetVer('assets/css/dashboard.css') }}">
@endpush

@section('content')
<div class="module-dashboard" id="dashboardRoot" data-chart-data="{}">
    <header class="module-executive-heading">
        <div><span class="master-eyebrow">One place per module</span><h2>Executive overview</h2>
        <p>Review the headline position here. Open a module for its complete trends, breakdowns, analytical tables and attention queue.</p></div>
        <a class="master-btn" href="{{ route('dashboards.index', $moduleQuery) }}">Module dashboards</a>
    </header>
    <p class="module-scope-note">{{ $period['label'] }} · Money in {{ $currency }} only; no currencies combined or FX assumed. Live positions are identified separately from period activity.</p>
    <section class="module-directory-grid" aria-label="Module summaries">
        @forelse($summaries as $key => $summary)
            <article class="module-dashboard-card module-overview-card">
                <header class="module-card-heading"><div><span>{{ $summary['description'] }}</span><h2><i class="{{ $summary['icon'] }}" aria-hidden="true"></i> {{ $summary['label'] }}</h2></div></header>
                <dl class="module-summary-values">
                    @foreach($summary['metrics'] as $metric)
                        <div><dt>{{ $metric['label'] }}</dt><dd>{{ $metric['format'] === 'money' ? \App\Helpers\CommonHelper::amount((float) $metric['current'], $currency) : number_format((float) $metric['current']) }}</dd>
                        <small>{{ $metric['comparison'] ? 'Selected period' : 'Live position' }}</small></div>
                    @endforeach
                </dl>
                <a class="master-btn" data-overview-module="{{ $key }}" href="{{ $summary['url'] }}">Open {{ $summary['label'] }} analytics <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                <small>{{ $summary['alerts'] }} priority alerts shown in the module queue</small>
            </article>
        @empty
            <p class="module-empty-copy">No module analytics are enabled for your access profile. Ask an administrator for the relevant module view permission.</p>
        @endforelse
    </section>
</div>
@endsection

@push('scripts')
<script src="{{ $assetVer('assets/js/dashboard.js') }}"></script>
@endpush
