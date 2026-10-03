<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Cashflow Report</title>
    <link rel="stylesheet" href="{{ asset('assets/css/cashflows-pdf.css') }}">
</head>

@php
    /* The sheet the accountant files. It prints the same matrix the screen
       shows — same grouping, same periods, same totals — because a printed
       total that cannot be matched to the report it came from is a total
       somebody has to re-add by hand. */
    $currencies = $report['currencies'];
    $singleCurrency = count($currencies) === 1 ? $currencies[0] : null;
    $money = fn ($value) => $singleCurrency
        ? \App\Helpers\CommonHelper::amount($value, $singleCurrency)
        : \App\Helpers\CommonHelper::indianCurrency($value, '');

    $scope = ($dimensions[$dimension]['label'] ?? '') . ' × ' . strtolower($units[$unit] ?? '')
        . ' · ' . $measures[$measure];
@endphp

<body @if(!empty($pdfFallbackMessage)) onload="setTimeout(function(){ window.print(); }, 500)" @endif>
    @if(!empty($pdfFallbackMessage))
        <div class="notice no-print">{{ $pdfFallbackMessage }}</div>
    @endif

    <div class="header">
        <div>
            <div class="brand">MissPack Cashflow Report</div>
            <div class="subtitle">
                {{ $scope }} · {{ $dateFrom->format('d M Y') }} to {{ $dateTo->format('d M Y') }}
                @if ($comparison !== 'none')
                    · compared with {{ strtolower($comparisons[$comparison]) }}
                @endif
            </div>
        </div>
        <div><span class="badge">{{ strtoupper($units[$unit] ?? '') }}</span></div>
    </div>

    <div class="grid">
        <div class="stat"><span>Money in</span><strong>{{ $money($report['totals']['credit']) }}</strong></div>
        <div class="stat"><span>Money out</span><strong>{{ $money($report['totals']['debit']) }}</strong></div>
        <div class="stat"><span>Net</span><strong>{{ $money($report['totals']['net']) }}</strong></div>
        <div class="stat"><span>Entries</span><strong>{{ $report['totals']['count'] }}</strong></div>
    </div>

    @if (count($currencies) > 1)
        <div class="notice">
            This range holds {{ implode(' and ', $currencies) }}; the figures carry no currency sign.
        </div>
    @endif

    <div class="section">
        <h3>{{ $dimensions[$dimension]['label'] ?? '' }} by {{ strtolower($units[$unit] ?? '') }}</h3>
        <table>
            <thead>
                <tr>
                    <th>{{ $dimensions[$dimension]['label'] ?? '' }}</th>
                    @foreach ($report['periods'] as $period)
                        <th>{{ $period['label'] }}</th>
                    @endforeach
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($report['rows'] as $row)
                    <tr>
                        <td>{{ $row['label'] }}
                            <div class="small">{{ \Illuminate\Support\Str::plural('entry', $row['count']) }}, {{ $row['count'] }}</div>
                        </td>
                        @foreach ($report['periods'] as $index => $period)
                            @php($cell = $row['cells'][$index] ?? null)
                            <td class="{{ $cell && ! $cell['empty'] ? ($cell['direction'] === 'out' ? 'debit' : 'credit') : '' }}">
                                {{ $cell && ! $cell['empty'] ? $money($cell['measure_value']) : '—' }}
                            </td>
                        @endforeach
                        <td class="{{ $row['direction'] === 'out' ? 'debit' : 'credit' }}"><strong>{{ $money($row['measure_value']) }}</strong></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($report['periods']) + 2 }}">No entry in this range matches these filters.</td>
                    </tr>
                @endforelse
            </tbody>
            @if ($report['rows'] !== [])
                <tfoot>
                    <tr>
                        <td><strong>Total</strong>
                            <div class="small">{{ \Illuminate\Support\Str::plural('entry', $report['totals']['count']) }}, {{ $report['totals']['count'] }}</div>
                        </td>
                        @foreach ($report['periods'] as $index => $period)
                            @php($cell = $report['totals']['cells'][$index] ?? null)
                            <td><strong>{{ $cell && ! $cell['empty'] ? $money($cell['measure_value']) : '—' }}</strong></td>
                        @endforeach
                        <td><strong>{{ $money($report['totals']['measure_value']) }}</strong></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    <div class="section">
        <h3>Filters</h3>
        <table>
            <tbody>
                <tr>
                    <td>Grouped by</td>
                    <td>{{ $dimensions[$dimension]['label'] ?? '' }} ({{ $dimensions[$dimension]['hint'] ?? '' }})</td>
                </tr>
                <tr>
                    <td>Figure</td>
                    <td>{{ $measures[$measure] ?? '' }} · {{ $units[$unit] ?? '' }} columns · compared with {{ strtolower($comparisons[$comparison] ?? '') }}</td>
                </tr>
                <tr>
                    <td>Rows included</td>
                    <td>
                        @forelse ($report['filters'] as $key => $value)
                            {{ $key }}={{ $value }}@if (! $loop->last), @endif
                        @empty
                            every entry in the range
                        @endforelse
                    </td>
                </tr>
                <tr>
                    <td>Generated</td>
                    <td>{{ \App\Helpers\DateRanges::today()->format('d M Y') }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="notice no-print">
        Every figure opens in the ledger at
        {{ route('cashflows.reports') }} — the paper is a copy, the app is the record.
    </div>
</body>

</html>
