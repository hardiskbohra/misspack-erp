@extends('layouts.app')

@section('title', 'Depreciation · '.$year['label'])
@section('page-title', 'Depreciation schedule')

@section('page-actions')
    <a class="master-btn master-btn-ghost" href="{{ route('assets.index') }}">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> The register
    </a>
    <a class="master-btn master-btn-soft" href="{{ route('assets.depreciation.export', ['year' => $year['key']]) }}">
        <i class="fa-solid fa-file-csv" aria-hidden="true"></i> Export this year
    </a>
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/assets.css') }}">
@endpush
@php
    $money = fn ($amount, $currency = 'INR') => \App\Helpers\CommonHelper::amount($amount, $currency);
    /* The report is company-wide, so the one link it offers back is to the whole
       register — a filtered register would quietly change the year underneath the
       reader. */
    $grossBookValue = $totals['closing'];
@endphp

<div class="fixed-assets ast-report master-list">

    <header class="master-card master-header ast-report-header">
        <div>
            <p class="master-eyebrow">The company's year</p>
            <h1 class="master-section-title">{{ $year['label'] }} depreciation schedule</h1>
            <p class="master-sub">
                {{ $year['from']->format('d M Y') }} to {{ $year['to']->format('d M Y') }} ·
                {{ number_format($rowCount) }} {{ \Illuminate\Support\Str::plural('asset', $rowCount) }} the year touched
            </p>
        </div>

        {{-- The year is the one thing this report is asked for, so the picker is a
             small form rather than a filter drawer: it changes the whole page. --}}
        <form class="ast-year-picker" method="GET" action="{{ route('assets.depreciation') }}">
            <label class="master-field">
                <span class="master-label">Financial year</span>
                <select class="master-select" name="year" onchange="this.form.submit()">
                    @foreach ($yearOptions as $key => $label)
                        <option value="{{ $key }}" @selected($year['key'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <noscript>
                <button class="master-btn master-btn-primary" type="submit">Show the year</button>
            </noscript>
        </form>
    </header>

    <div class="master-info-box">
        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
        <span>
            This schedule is <strong>the whole company</strong>, whatever the register was filtered to — a year's
            depreciation belongs to the year and to the company, and reading it through a location filter would
            answer a question nobody asked. The rows are the assets this year touched: held, bought or sold during
            it, one row per asset under its class, and a fully depreciated asset that was not sold this year
            belongs to an earlier year and is not one of them. Every figure is computed from the assets' own
            purchase dates, useful lives and methods — nothing is stored, so a correction on an asset is a
            correction here the moment it is saved.
        </span>
    </div>

    {{-- ───────────────────────────────────────────────────── the year in money --}}
    <div class="master-stats desktop-only">
        <div class="master-stat master-stat--flat blue tooltip-container">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-scale-unbalanced"></i></span>
            <div>
                <p class="master-stat-title">Opening book value</p>
                <p class="master-stat-value">{{ $money($totals['opening']) }}</p>
                <p class="master-sub">on {{ $year['from']->copy()->subDay()->format('d M Y') }}</p>
                <span class="tooltip-text">What the year touched was carried at the day before the year began — the previous year's closing figure, unchanged.</span>
            </div>
        </div>

        <div class="master-stat master-stat--flat teal tooltip-container">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-cart-plus"></i></span>
            <div>
                <p class="master-stat-title">Additions</p>
                <p class="master-stat-value">{{ $money($totals['additions']) }}</p>
                <p class="master-sub">assets bought during the year, at cost</p>
                <span class="tooltip-text">Capitalised value of everything purchased inside the year. The rest of the year's assets were already on the books on 1 April.</span>
            </div>
        </div>

        <div class="master-stat master-stat--flat orange tooltip-container">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-arrow-trend-down"></i></span>
            <div>
                <p class="master-stat-title">Depreciation for the year</p>
                <p class="master-stat-value">{{ $money($totals['charge']) }}</p>
                <p class="master-sub">charged asset by asset, pro-rated by days</p>
                <span class="tooltip-text">The figure that goes to the profit and loss account. Each asset is charged on its own life and method, pro-rated for the days it was held during the year.</span>
            </div>
        </div>

        <div class="master-stat master-stat--flat {{ $totals['disposal_gain'] >= 0 ? 'green' : 'red' }} tooltip-container">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-box-archive"></i></span>
            <div>
                <p class="master-stat-title">{{ $totals['disposal_gain'] >= 0 ? 'Profit on disposals' : 'Loss on disposals' }}</p>
                <p class="master-stat-value">{{ $money(abs($totals['disposal_gain'])) }}</p>
                <p class="master-sub">
                    {{ $money($totals['disposal_value']) }} realised against
                    {{ $money($totals['disposal_book']) }} of book value
                </p>
                <span class="tooltip-text">What assets sold during the year fetched, measured against what they were carried at on the day they left.</span>
            </div>
        </div>

        <div class="master-stat master-stat--flat purple tooltip-container">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-chart-line"></i></span>
            <div>
                <p class="master-stat-title">Closing book value</p>
                <p class="master-stat-value">{{ $money($grossBookValue) }}</p>
                <p class="master-sub">on {{ $year['to']->format('d M Y') }}</p>
                <span class="tooltip-text">Opening, plus additions, less the year's charge, less anything disposed of at its book value. This is the figure the balance sheet's fixed assets line carries.</span>
            </div>
        </div>
    </div>

    {{-- ──────────────────────────────────────────────── the schedule, by class --}}
    @if ($groups === [])
        <section class="master-card master-card--flat ast-table-card">
            <div class="master-list-empty">
                <span class="master-list-empty-icon" aria-hidden="true">📉</span>
                <h3 class="master-list-empty-title">Nothing was depreciated in {{ $year['label'] }}</h3>
                <p class="master-list-empty-text">
                    No asset had a charge for this year and nothing was bought or sold inside it. Assets that finished
                    depreciating before the year began — and those bought after it ended — belong to other years.
                </p>
                <div class="master-list-empty-actions">
                    <a class="master-btn master-btn-soft" href="{{ route('assets.depreciation') }}">Back to this year</a>
                    <a class="master-btn master-btn-primary" href="{{ route('assets.index') }}">Open the register</a>
                </div>
            </div>
        </section>
    @else
        @foreach ($groups as $group)
            <section class="master-card master-card--flat ast-table-card" aria-label="{{ $group['category'] }} depreciation">
                <div class="ast-group-head">
                    <div>
                        <p class="master-eyebrow">Class</p>
                        <h2 class="master-section-title">{{ $group['category'] }}</h2>
                        {{-- The recipe, said once for the class instead of three words
                             repeated on every row: the charge below is this life and
                             this method, pro-rated by the days each asset was held. A
                             row that carries its own recipe says so under its name. --}}
                        @if ($group['recipe'])
                            <p class="master-sub">{{ $group['recipe'] }}</p>
                        @endif
                    </div>
                    <div class="ast-group-totals">
                        <span>
                            <span class="ast-fact-label">Charge for the year</span>
                            <span class="ast-fact-value ast-money">{{ $money($group['subtotal']['charge']) }}</span>
                        </span>
                        <span>
                            <span class="ast-fact-label">Closing value</span>
                            <span class="ast-fact-value ast-money">{{ $money($group['subtotal']['closing']) }}</span>
                        </span>
                    </div>
                </div>

                <div class="master-table-wrap ui-mobile-cards">
                    <table class="master-table ast-table ast-schedule-table">
                        <thead>
                            <tr>
                                <th scope="col">Asset</th>
                                <th scope="col" class="ast-col-money">Opening</th>
                                <th scope="col" class="ast-col-money">Additions</th>
                                <th scope="col" class="ast-col-money">Charge for the year</th>
                                <th scope="col" class="ast-col-money">Closing</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($group['rows'] as $row)
                                <tr class="{{ $row['disposed_on'] ? 'ast-row-disposed' : '' }}">
                                    <td data-label="Asset">
                                        <a class="ast-asset-link" href="{{ route('assets.show', ['asset' => $row['id'], 'tab' => 'depreciation']) }}">
                                            {{ $row['name'] }}
                                        </a>
                                        <span class="ast-cell-sub">
                                            <span class="ast-code">{{ $row['code'] }}</span>
                                            @unless ($row['inherits_recipe'])
                                                · its own recipe: {{ $row['recipe'] }}
                                            @endunless
                                        </span>
                                        {{-- The disposal is an annotation on the asset, not a
                                             column: it is empty in every year but the one an
                                             asset left in, and when it is not empty it is the
                                             whole story of that row. --}}
                                        @if ($row['disposed_on'])
                                            <span class="ast-cell-sub">
                                                sold {{ $row['disposed_on']->format('d M Y') }}
                                                for {{ $money($row['disposal_value']) }} —
                                                {{ $row['disposal_gain'] >= 0 ? 'profit' : 'loss' }}
                                                {{ $money(abs($row['disposal_gain'])) }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="ast-col-money" data-label="Opening"><span class="ast-money">{{ $money($row['opening']) }}</span></td>
                                    <td class="ast-col-money" data-label="Additions">
                                        @if ($row['additions'] > 0)
                                            <span class="ast-money">{{ $money($row['additions']) }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="ast-col-money" data-label="Charge for the year">
                                        <span class="ast-money">{{ $money($row['charge']) }}</span>
                                    </td>
                                    <td class="ast-col-money" data-label="Closing"><span class="ast-money">{{ $money($row['closing']) }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="ast-total-row">
                                <th scope="row">
                                    {{ $group['category'] }} —
                                    {{ number_format($group['subtotal']['assets']) }}
                                    {{ \Illuminate\Support\Str::plural('asset', $group['subtotal']['assets']) }}
                                    @if ($group['subtotal']['disposal_value'] > 0 || $group['subtotal']['disposal_book'] > 0)
                                        {{-- The class sold something this year: the money is
                                             the subtotal's own annotation, so the row can still
                                             be read without the "Disposed" column. --}}
                                        <span class="ast-cell-sub">
                                            {{ $money($group['subtotal']['disposal_value']) }} realised on
                                            {{ $money($group['subtotal']['disposal_book']) }} of book value
                                        </span>
                                    @endif
                                </th>
                                <td class="ast-col-money"><span class="ast-money">{{ $money($group['subtotal']['opening']) }}</span></td>
                                <td class="ast-col-money"><span class="ast-money">{{ $money($group['subtotal']['additions']) }}</span></td>
                                <td class="ast-col-money"><span class="ast-money">{{ $money($group['subtotal']['charge']) }}</span></td>
                                <td class="ast-col-money"><span class="ast-money">{{ $money($group['subtotal']['closing']) }}</span></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>
        @endforeach

        {{-- The company's roll-up is the five cards above, not a table here. It was
             a card with one row printing the same opening, additions, charge,
             closing and realised money the cards already carry — the same five
             figures twice on one page, and the second copy was the one nobody
             read. A class's subtotal lives under its own rows; the sum of those
             subtotals is the strip at the top of this page, and the file that
             leaves the building carries every class and every row. --}}

    @endif
</div>
@endsection
