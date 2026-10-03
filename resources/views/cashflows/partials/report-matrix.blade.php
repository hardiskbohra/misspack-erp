@php
    /* The matrix, drawn once for the screen and once for the printer. The only
       difference is that a printed sheet cannot be clicked, so the drill links
       are dropped — the figures, the columns and the totals are the same block,
       which is why the paper in the accountant's file and the page on the
       screen can be read against each other. */
    $printMode = $printMode ?? false;
    /* The totals row wears the surface's own class on screen (master-list.css
       owns the shape) and nothing on paper: the partial is included by both,
       and naming the chrome here would be a second owner for it. */
    $footClass = $footClass ?? '';

    /* Money keeps the app's Indian format (₹1,50,000), in the one currency the
       window holds. A report that mixes currencies shows the figures with no
       sign at all — a ₹ in front of a dollar figure states the wrong amount, and
       the page says why. Which of the two it is, is decided once, on the
       controller, so the headline, the matrix and the bars cannot disagree. */
    $moneyCurrency = (string) ($report['money_currency'] ?? '');
    $money = fn ($value) => $moneyCurrency === ''
        ? \App\Helpers\CommonHelper::indianCurrency($value, '')
        : \App\Helpers\CommonHelper::amount($value, $moneyCurrency);

    $measureLabel = $measures[$report['measure']] ?? '';
    $dimensionLabel = $dimensions[$report['dimension']]['label'] ?? '';

    $delta = function (?float $value) {
        if ($value === null) {
            return null;
        }

        return ($value > 0 ? '+' : '') . number_format($value, 1) . '%';
    };
@endphp

<div class="master-table-wrap cf-report-wrap">
    <table class="master-table cf-report-table">
        <thead>
            <tr>
                <th scope="col" class="cf-report-name-col">{{ $dimensionLabel }}</th>
                @foreach ($report['periods'] as $period)
                    <th scope="col" class="is-num cf-report-period">
                        @if ($printMode)
                            {{ $period['label'] }}
                        @else
                            <a href="{{ $report['totals']['cells'][$loop->index]['url'] ?? $report['url'] }}"
                                title="Open every entry in {{ $period['label'] }} ({{ $period['start'] }} to {{ $period['end'] }})">
                                {{ $period['label'] }}
                            </a>
                        @endif
                    </th>
                @endforeach
                <th scope="col" class="is-num cf-report-total-col">Total</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($report['rows'] as $row)
                @php($rowValue = $money($row['measure_value']))
                <tr>
                    <td class="cf-report-name-col">
                        @if ($printMode)
                            <span class="cf-report-name">{{ $row['label'] }}</span>
                        @else
                            <a class="cf-report-name" href="{{ $row['url'] }}"
                                title="Open these {{ $row['count'] }} entries in the ledger">{{ $row['label'] }}</a>
                        @endif
                        <span class="master-sub">
                            {{ \Illuminate\Support\Str::plural('entry', $row['count']) }}, {{ $row['count'] }}
                            @if ($report['measure'] === 'net' && ! $report['empty'])
                                · +{{ $money($row['credit']) }} / −{{ $money($row['debit']) }}
                            @endif
                        </span>
                    </td>

                    @foreach ($report['periods'] as $index => $period)
                        @php($cell = $row['cells'][$index] ?? null)
                        <td class="is-num cf-report-cell">
                            @if ($cell && ! $cell['empty'])
                                @php($value = $money($cell['measure_value']))
                                @if ($printMode)
                                    <span class="cf-report-value is-{{ $cell['direction'] }}">{{ $value }}</span>
                                @else
                                    <a class="cf-report-value is-{{ $cell['direction'] }}" href="{{ $cell['url'] }}"
                                        title="Open the {{ \Illuminate\Support\Str::plural('entry', $cell['count']) }} behind this figure — {{ $cell['count'] }} in {{ $period['label'] }}, {{ $period['start'] }} to {{ $period['end'] }}">
                                        {{ $value }}
                                    </a>
                                @endif

                                @if ($report['comparison'] !== 'none' && $cell['delta'] !== null)
                                    <span class="cf-report-delta is-{{ $cell['delta'] > 0 ? 'up' : 'down' }}"
                                        title="{{ $period['label'] }} against {{ $period['compare_label'] ?: 'the comparison period' }}: {{ $money($cell['compare_value']) }}">
                                        @if ($cell['delta'] > 0)↑@else↓@endif {{ $delta($cell['delta']) }}
                                    </span>
                                @endif
                            @else
                                <span class="cf-report-dash" title="Nothing in {{ $period['label'] }}">·</span>
                            @endif
                        </td>
                    @endforeach

                    <td class="is-num cf-report-total-col">
                        @if ($printMode)
                            <span class="cf-report-value is-{{ $row['direction'] }}">{{ $rowValue }}</span>
                        @else
                            <a class="cf-report-value is-{{ $row['direction'] }}" href="{{ $row['url'] }}"
                                title="Open every entry for {{ $row['label'] }} in this range">{{ $rowValue }}</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($report['periods']) + 2 }}" class="cf-report-blank">
                        Nothing to group — no entry in this range matches these filters.
                    </td>
                </tr>
            @endforelse
        </tbody>

        <tfoot>
            <tr class="{{ $footClass }}">
                <td>
                    Total
                    <span class="master-sub">
                        {{ \Illuminate\Support\Str::plural('entry', $report['totals']['count']) }},
                        {{ $report['totals']['count'] }}
                    </span>
                </td>
                @foreach ($report['periods'] as $index => $period)
                    @php($cell = $report['totals']['cells'][$index] ?? null)
                    <td class="is-num cf-report-cell">
                        @if ($cell && ! $cell['empty'])
                            @if ($printMode)
                                <strong class="cf-report-value is-{{ $cell['direction'] }}">{{ $money($cell['measure_value']) }}</strong>
                            @else
                                <a class="cf-report-value is-{{ $cell['direction'] }}" href="{{ $cell['url'] }}"
                                    title="Open every entry in {{ $period['label'] }}">{{ $money($cell['measure_value']) }}</a>
                            @endif
                        @else
                            <span class="cf-report-dash" aria-hidden="true">·</span>
                        @endif
                    </td>
                @endforeach
                <td class="is-num cf-report-total-col">
                    @if ($printMode)
                        <strong class="cf-report-value is-{{ $report['totals']['direction'] }}">{{ $money($report['totals']['measure_value']) }}</strong>
                    @else
                        <a class="cf-report-value is-{{ $report['totals']['direction'] }}" href="{{ $report['url'] }}"
                            title="Open every entry in this range">{{ $money($report['totals']['measure_value']) }}</a>
                    @endif
                </td>
            </tr>
        </tfoot>
    </table>
</div>
