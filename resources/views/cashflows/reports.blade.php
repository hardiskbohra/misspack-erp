@extends('layouts.app')

@section('page-title', 'Cashflow Reports')

{{-- A report is read to be acted on: exported, printed, or opened in the ledger
     it was built from. The page's primary action is the export, because that is
     what leaves the app. --}}
@section('page-actions')
    <a class="master-btn master-btn-ghost" href="{{ route('cashflows.index') }}">Ledger</a>
    <a class="master-btn master-btn-ghost desktop-only" href="{{ route('cashflows.documents') }}">Documents</a>
    <a class="master-btn master-btn-ghost desktop-only" href="{{ route('cashflows.statements') }}">Statements</a>
    <a class="master-btn master-btn-soft" href="{{ route('cashflows.reports.pdf', $reportQuery) }}">Print / PDF</a>
    <a class="master-btn master-btn-primary" href="{{ route('cashflows.reports.export', $reportQuery) }}">Export CSV</a>
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/cashflows.css') }}">
    {{-- the shared list chrome — the chip bar, the saved views, the applied
         strip and the totals row are the same on every surface in the module --}}
    <link rel="stylesheet" href="{{ $assetVer('assets/css/master-list.css') }}">
@endpush

@php
    /* What the page is showing, in one line — the sentence an accountant would
       say out loud to describe the sheet in front of them. */
    $scope = ($dimensions[$dimension]['label'] ?? '') . ' × ' . strtolower($units[$unit] ?? '')
        . ' · ' . $measures[$measure];

    /* Chips keep every choice on the page except the one they own: picking a
       period must not silently drop the grouping, and picking a grouping must
       not reset the dates. */
    $baseQuery = collect($reportQuery)->except(['date_from', 'date_to'])->all();

    $with = fn (array $overrides) => route('cashflows.reports', array_merge($baseQuery, $overrides));

    $filterCount = collect($appliedFilters)
        ->reject(fn ($chip) => in_array($chip['query'], ['date_from', 'date_to'], true))
        ->count();

    $multiCurrency = count($report['currencies']) > 1;

    /* One money formatter for the whole sheet, so the headline card and the
       bars below it agree with the matrix (and with each other). */
    $moneyCurrency = (string) ($report['money_currency'] ?? '');
    $money = fn ($value) => $moneyCurrency === ''
        ? \App\Helpers\CommonHelper::indianCurrency($value, '')
        : \App\Helpers\CommonHelper::amount($value, $moneyCurrency);
@endphp

<div class="cf cashflow-reports master-list">

    {{-- ── the surface's controls: period chips, saved views, the builder ── --}}
    <div class="master-card master-card--flat">
        <div class="master-list-bar">
            <div class="master-list-chips">
                @foreach ($dateRanges as $rangeKey => $range)
                    <a class="master-list-chip {{ $activeRange === $rangeKey ? 'is-active' : '' }}"
                        href="{{ $with(['date_from' => $range['from'], 'date_to' => $range['to']]) }}">
                        {{ $dateRangeLabels[$rangeKey] }}
                    </a>
                @endforeach
            </div>

            <div class="master-list-saved">
                @foreach ($savedViews as $view)
                    <span class="master-list-saved-chip">
                        <a href="{{ route('cashflows.reports', ['saved_view' => $view->id]) }}"
                            title="{{ $view->is_shared ? 'Shared view' : 'Your view' }}">{{ $view->name }}</a>
                        @if ((int) $view->user_id === (int) auth()->id())
                            <form method="POST" action="{{ route('cashflows.saved-views.destroy', $view) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" title="Remove saved view" aria-label="Remove the saved view {{ $view->name }}">&times;</button>
                            </form>
                        @endif
                    </span>
                @endforeach
                <button type="button" class="master-btn master-btn-soft master-btn-sm" id="toggleSaveView">☆ Save this report</button>
                <form method="POST" action="{{ route('cashflows.saved-views.store', $reportQuery) }}" class="master-list-save-view" id="saveViewForm" hidden>
                    @csrf
                    <input type="hidden" name="module" value="cashflow-reports">
                    <input class="master-input" name="name" placeholder="Report name" maxlength="60"
                        aria-label="Saved report name" required>
                    <label class="master-check"><input type="checkbox" name="is_shared" value="1"> Share</label>
                    <button class="master-btn master-btn-primary master-btn-sm">Save</button>
                </form>
            </div>
        </div>

        <form method="GET" action="{{ route('cashflows.reports') }}">
            <div class="master-filter-row cf-report-builder">
                <div class="cf-report-field">
                    <label class="master-label" for="reportDimension">Group by</label>
                    <select class="master-select" id="reportDimension" name="dimension">
                        @foreach ($dimensions as $key => $definition)
                            <option value="{{ $key }}" @selected($dimension === $key)>{{ $definition['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="cf-report-field">
                    <label class="master-label" for="reportUnit">Period</label>
                    <select class="master-select" id="reportUnit" name="period_unit">
                        @foreach ($units as $key => $label)
                            <option value="{{ $key }}" @selected($unit === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="cf-report-field">
                    <label class="master-label" for="reportMeasure">Figure</label>
                    <select class="master-select" id="reportMeasure" name="measure">
                        @foreach ($measures as $key => $label)
                            <option value="{{ $key }}" @selected($measure === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="cf-report-field">
                    <label class="master-label" for="reportComparison">Compare with</label>
                    <select class="master-select" id="reportComparison" name="comparison">
                        @foreach ($comparisons as $key => $label)
                            <option value="{{ $key }}" @selected($comparison === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="cf-report-field">
                    <label class="master-label" for="reportFrom">From</label>
                    <input class="master-input" id="reportFrom" type="date" name="date_from" value="{{ $report['from'] }}">
                </div>

                <div class="cf-report-field">
                    <label class="master-label" for="reportTo">To</label>
                    <input class="master-input" id="reportTo" type="date" name="date_to" value="{{ $report['to'] }}">
                </div>

                <div class="cf-report-actions">
                    <button class="master-btn master-btn-primary" type="submit">Run report</button>
                    <a class="master-btn master-btn-soft" href="{{ route('cashflows.reports') }}">Reset</a>
                </div>

                {{-- The dimensions that narrow the rows, not the shape of the
                     report. Behind a disclosure so the six controls above stay
                     one line, and counted so a filter nobody can see never
                     quietly changes a total. --}}
                <details class="cf-report-more" @if ($filterCount > 0) open @endif>
                    <summary>
                        <span class="master-btn master-btn-ghost master-btn-sm">Filters</span>
                        @if ($filterCount > 0)
                            <span class="cf-report-more-count">{{ $filterCount }}</span>
                        @endif
                    </summary>
                    <div class="cf-report-more-grid">
                        @foreach ([
                            'account_id' => ['Account', $accounts->pluck('account_name', 'id')->all()],
                            'account_type' => ['Account type', $accountTypeOptions],
                            'category_id' => ['Category', $categories->pluck('name', 'id')->all()],
                            'accounting_status' => ['Accounting status', $accountingStatusOptions],
                            'transaction_type' => ['Money in / out', $transactionTypeOptions],
                            'payment_mode' => ['Payment mode', $paymentModeOptions],
                            'currency' => ['Currency', $currencyOptions],
                            'documents' => ['Documents', ['missing' => 'Missing only', 'attached' => 'Filed only']],
                        ] as $name => [$label, $options])
                            @php($selected = (string) request()->query($name, 'all'))
                            <div class="cf-report-field">
                                <label class="master-label" for="reportFilter-{{ str_replace('_', '-', $name) }}">{{ $label }}</label>
                                <select class="master-select" id="reportFilter-{{ str_replace('_', '-', $name) }}" name="{{ $name }}">
                                    <option value="all">All {{ strtolower($label) }}</option>
                                    @foreach ($options as $key => $optionLabel)
                                        <option value="{{ $key }}" @selected($selected === (string) $key)>{{ $optionLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach

                        @if ($clients->isNotEmpty())
                            <div class="cf-report-field">
                                <label class="master-label" for="reportFilter-client">Client</label>
                                <select class="master-select" id="reportFilter-client" name="client_id">
                                    <option value="all">All clients</option>
                                    @foreach ($clients as $client)
                                        <option value="{{ $client->id }}" @selected((string) request()->query('client_id') === (string) $client->id)>{{ $client->company_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        @if ($vendors->isNotEmpty())
                            <div class="cf-report-field">
                                <label class="master-label" for="reportFilter-vendor">Vendor</label>
                                <select class="master-select" id="reportFilter-vendor" name="vendor_id">
                                    <option value="all">All vendors</option>
                                    @foreach ($vendors as $vendor)
                                        <option value="{{ $vendor->id }}" @selected((string) request()->query('vendor_id') === (string) $vendor->id)>{{ $vendor->vendor_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        @if ($employees->isNotEmpty())
                            <div class="cf-report-field">
                                <label class="master-label" for="reportFilter-employee">Employee</label>
                                <select class="master-select" id="reportFilter-employee" name="employee_id">
                                    <option value="all">All employees</option>
                                    @foreach ($employees as $employee)
                                        <option value="{{ $employee->id }}" @selected((string) request()->query('employee_id') === (string) $employee->id)>{{ $employee->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div class="cf-report-field">
                            <label class="master-label" for="reportFilter-expense">Expense head</label>
                            <input class="master-input" id="reportFilter-expense" name="expense_head"
                                value="{{ request()->query('expense_head') }}" placeholder="Any expense head">
                        </div>

                        <div class="cf-report-field">
                            <label class="master-label" for="reportFilter-particular">Search</label>
                            <input class="master-input" id="reportFilter-particular" name="search"
                                value="{{ request()->query('search') }}" placeholder="Particular, invoice, reference">
                        </div>
                    </div>
                </details>
            </div>

            {{-- What is filtering the report, one removable chip each: a filter
                 that arrives by link (or by saved view) is visible here even
                 when its control is closed. --}}
            @if ($filterCount > 0)
                <div class="master-list-applied">
                    <span class="master-list-applied-title">Filtered by</span>
                    @foreach ($appliedFilters as $chip)
                        @continue(in_array($chip['query'], ['date_from', 'date_to'], true))
                        <span class="master-list-applied-chip">
                            <span class="master-list-applied-key">{{ $chip['label'] }}</span>
                            <span class="master-list-applied-value">{{ $appliedFilterLabels[$chip['key']] ?? $chip['value'] }}</span>
                            <a class="master-list-applied-x"
                                href="{{ route('cashflows.reports', array_merge($reportQuery, [$chip['query'] => 'all', 'dimension' => $dimension])) }}"
                                aria-label="Remove the {{ strtolower($chip['label']) }} filter"
                                title="Remove the {{ strtolower($chip['label']) }} filter">&times;</a>
                        </span>
                    @endforeach
                    <a class="master-list-applied-clear" href="{{ route('cashflows.reports') }}">Clear all filters</a>
                </div>
            @endif
        </form>
    </div>

    {{-- ── the window, in figures ── --}}
    <div class="master-stats cf-report-headline">
        <div class="master-stat master-stat--flat green">
            <span class="icon">↓</span>
            <div>
                <p class="master-stat-title">Money in</p>
                <p class="master-stat-value">{{ $money($report['totals']['credit']) }}</p>
                <p class="master-sub">{{ $scope }} · {{ $dateFrom->format('d M Y') }} → {{ $dateTo->format('d M Y') }}</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat orange">
            <span class="icon">↑</span>
            <div>
                <p class="master-stat-title">Money out</p>
                <p class="master-stat-value">{{ $money($report['totals']['debit']) }}</p>
                <p class="master-sub">{{ \Illuminate\Support\Str::plural('entry', $report['totals']['count']) }}, {{ $report['totals']['count'] }} in this range</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat {{ $report['totals']['direction'] === 'out' ? 'purple' : 'blue' }}">
            <span class="icon">=</span>
            <div>
                <p class="master-stat-title">Net {{ $report['totals']['direction'] === 'out' ? 'out' : 'in' }}</p>
                <p class="master-stat-value">{{ $money($report['totals']['net']) }}</p>
                <p class="master-sub">
                    @if ($comparison !== 'none' && $report['totals']['delta'] !== null)
                        {{ $report['totals']['delta'] > 0 ? '+' : '' }}{{ number_format($report['totals']['delta'], 1) }}% against
                        {{ strtolower($comparisons[$comparison]) }}
                    @else
                        {{ $measures[$measure] }} per {{ strtolower($units[$unit]) }}
                    @endif
                </p>
            </div>
        </div>
    </div>

    @if ($report['truncated'])
        <p class="cf-report-note">
            <strong>Long range.</strong> Only the first {{ count($report['periods']) }} {{ strtolower($units[$unit]) }}
            columns are drawn — narrow the dates to see the rest, or switch the period to a longer one.
        </p>
    @endif

    @if ($multiCurrency)
        {{-- A total that adds rupees to dollars is a number, not an answer. --}}
        <p class="cf-report-note">
            <strong>Mixed currencies.</strong> This range holds
            {{ implode(' and ', $report['currencies']) }}, so the figures carry no currency sign —
            add a currency filter to compare like with like.
        </p>
    @endif

    @if ($report['empty'])
        <div class="master-card master-card--flat master-list-empty">
            <span class="master-list-empty-icon" aria-hidden="true">∑</span>
            <p class="master-list-empty-title">Nothing to group in this range</p>
            <p class="master-list-empty-text">
                No entry between {{ $dateFrom->format('d M Y') }} and {{ $dateTo->format('d M Y') }}
                matches these filters. Widen the dates, or clear a filter.
            </p>
            <div class="master-list-empty-actions">
                <a class="master-btn master-btn-soft" href="{{ route('cashflows.reports') }}">Start over</a>
                <a class="master-btn master-btn-ghost" href="{{ route('cashflows.index', array_merge($report['filters'], ['date_from' => $report['from'], 'date_to' => $report['to']])) }}">
                    Open the ledger for these dates
                </a>
            </div>
        </div>
    @else
        <div class="master-card master-table-card master-card--flat">
            <div class="master-list-toolbar">
                <p class="master-list-hint"
                    title="Every figure opens the entries behind it in the ledger, filtered to the same rows this report counted.">
                    {{ $scope }} <strong>{{ $dateFrom->format('d M Y') }} → {{ $dateTo->format('d M Y') }}</strong>
                    @if ($comparison !== 'none')
                        · compared with {{ strtolower($comparisons[$comparison]) }}
                    @endif
                </p>
            </div>

            @include('cashflows.partials.report-matrix', ['printMode' => false, 'footClass' => 'master-list-total'])
        </div>

        {{-- The same figures down the periods, so the shape of the range is
             visible before the numbers are read. --}}
        <div class="master-card master-card--flat cf-report-trend">
            @php($peak = max(1, ...array_map(fn ($cell) => max((float) $cell['credit'], (float) $cell['debit']), $report['totals']['cells'])))
            @foreach ($report['periods'] as $index => $period)
                @php($cell = $report['totals']['cells'][$index])
                <a class="cf-report-bar" href="{{ $cell['url'] }}"
                    title="{{ $period['label'] }}: in {{ $money($cell['credit']) }}, out {{ $money($cell['debit']) }}">
                    <span class="cf-report-bar-bars">
                        <i class="cf-report-bar-in" style="height: {{ max(2, round($cell['credit'] / $peak * 100)) }}%"></i>
                        <i class="cf-report-bar-out" style="height: {{ max(2, round($cell['debit'] / $peak * 100)) }}%"></i>
                    </span>
                    <span class="cf-report-bar-label">{{ $period['short'] }}</span>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection

@push('scripts')
    <script src="{{ $assetVer('assets/js/cashflows.js') }}"></script>
@endpush
