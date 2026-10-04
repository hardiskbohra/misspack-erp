@php
    $periodOptions = $periodOptions ?? ($dateRangeLabels + ['all' => 'All time', 'custom' => 'Custom dates']);
    $totals = $statement['totals'];
    $money = fn ($value) => \App\Helpers\CommonHelper::amount((float) $value, $statement['currency']);
    $otherCurrencies = collect($statement['other_currencies'] ?? [])->map(function ($line) {
        $parts = [];
        if ($line['debit'] > 0) {
            $parts[] = \App\Helpers\CommonHelper::amount($line['debit'], $line['currency']).' debit';
        }
        if ($line['credit'] > 0) {
            $parts[] = \App\Helpers\CommonHelper::amount($line['credit'], $line['currency']).' credit';
        }

        return $line['currency'].' · '.number_format($line['rows']).' '.\Illuminate\Support\Str::plural('entry', $line['rows']).($parts ? ' ('.implode(', ', $parts).')' : '');
    })->implode(' · ');
@endphp

<div class="client-detail-tools client-statement-workspace">
    <section class="master-card master-card--flat client-detail-card client-detail-card--wide client-statement-filters" aria-labelledby="client-statement-filters-heading">
        <div class="client-statement-toolbar-head">
            <div>
                <p class="cpa-eyebrow">Live client ledger</p>
                <h2 class="client-detail-title" id="client-statement-filters-heading">Statement entries</h2>
            </div>
            <span class="cpa-badge">{{ number_format($totals['count']) }} {{ \Illuminate\Support\Str::plural('entry', $totals['count']) }}</span>
        </div>
        <form method="GET" action="{{ route('clients.show', $client) }}" class="client-statement-filter-form">
            <input type="hidden" name="tab" value="statement">
            <div class="master-field">
                <label class="master-label" for="clientStatementPeriod">Period</label>
                <select class="master-select" id="clientStatementPeriod" name="period">
                    @foreach($periodOptions as $key => $label)
                        <option value="{{ $key }}" @selected($periodKey === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="master-field">
                <label class="master-label" for="clientStatementFrom">From</label>
                <input class="master-input" id="clientStatementFrom" type="date" name="date_from" value="{{ $dateFrom }}" onchange="document.getElementById('clientStatementPeriod').value='custom'">
            </div>
            <div class="master-field">
                <label class="master-label" for="clientStatementTo">To</label>
                <input class="master-input" id="clientStatementTo" type="date" name="date_to" value="{{ $dateTo }}" onchange="document.getElementById('clientStatementPeriod').value='custom'">
            </div>
            @if(count($currencyOptions) > 1)
                <div class="master-field">
                    <label class="master-label" for="clientStatementCurrency">Currency</label>
                    <select class="master-select" id="clientStatementCurrency" name="currency">
                        @foreach($currencyOptions as $code)
                            <option value="{{ $code }}" @selected($statement['currency'] === $code)>{{ \App\Helpers\CommonHelper::currencyLabel($code) }}</option>
                        @endforeach
                    </select>
                </div>
            @else
                <input type="hidden" name="currency" value="{{ $statement['currency'] }}">
            @endif
            <div class="client-statement-filter-submit">
                <button class="master-btn master-btn-soft client-statement-pdf-link" type="submit" formmethod="GET" formaction="{{ route('cashflows.statements.pdf', ['partyType' => 'client', 'party' => $client->id]) }}" formtarget="_blank" name="ageing" value="0" aria-label="Download statement PDF for the selected period">
                    <i class="fa-solid fa-file-arrow-down" aria-hidden="true"></i> Download PDF
                </button>
                <button class="master-btn master-btn-primary" type="submit">Apply filters</button>
            </div>
        </form>
        <p class="client-statement-source">{{ rtrim($statement['source_note'], '. ') }} · Balance as on {{ ($statement['period']['to'] ?? $statement['generated_at'])->format('d M Y') }}</p>
    </section>

    <div class="client-statement-metrics" aria-label="Statement totals">
        <section class="client-statement-metric"><span>Opening balance</span><strong>{{ $money($statement['opening']) }}</strong></section>
        <section class="client-statement-metric"><span>Total debits</span><strong>{{ $money($totals['debit']) }}</strong></section>
        <section class="client-statement-metric"><span>Total credits</span><strong>{{ $money($totals['credit']) }}</strong></section>
        <section class="client-statement-metric is-balance"><span>Closing balance</span><strong>{{ $money($totals['closing']) }}</strong><small>{{ $totals['closing'] >= 0 ? 'Receivable' : 'Client credit' }}</small></section>
    </div>

    @if($otherCurrencies)
        <div class="client-statement-other-currencies"><strong>Other currency activity</strong><span>{{ $otherCurrencies }}</span></div>
    @endif

    <section class="master-card master-card--flat client-detail-card client-detail-card--wide client-statement-entries" aria-labelledby="client-statement-entries-heading">
        <div class="cpa-section-head">
            <h2 id="client-statement-entries-heading">Transactions</h2>
        </div>
        <div class="master-table-wrap client-statement-table-wrap">
            <table class="master-table client-statement-table">
                <thead>
                    <tr><th>Date</th><th>Particulars</th><th>Reference</th><th>Status</th><th class="text-right">Debit</th><th class="text-right">Credit</th><th class="text-right">Balance</th></tr>
                </thead>
                <tbody>
                    <tr class="client-statement-opening-row">
                        <td>{{ $statement['period']['from']?->copy()->subDay()->format('d M Y') ?: '—' }}</td>
                        <td colspan="3"><strong>Opening balance</strong></td>
                        <td class="text-right">—</td>
                        <td class="text-right">—</td>
                        <td class="text-right"><strong>{{ $money($statement['opening']) }}</strong></td>
                    </tr>
                    @forelse($statement['rows'] as $row)
                        <tr>
                            <td>{{ $row['date']?->format('d M Y') ?: '—' }}</td>
                            <td>{{ $row['particular'] }}</td>
                            <td>{{ $row['reference'] ?: '—' }}</td>
                            <td>@if($row['status'])<span class="master-badge status-{{ \Illuminate\Support\Str::slug($row['status']) }}">{{ $row['status'] }}</span>@else—@endif</td>
                            <td class="text-right">{{ $row['debit'] > 0 ? $money($row['debit']) : '—' }}</td>
                            <td class="text-right">{{ $row['credit'] > 0 ? $money($row['credit']) : '—' }}</td>
                            <td class="text-right"><strong>{{ $money($row['balance']) }}</strong></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="cpa-empty">No entries fall within the selected filters.</div></td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4"><strong>Period totals · closing balance</strong></td>
                        <td class="text-right"><strong>{{ $money($totals['debit']) }}</strong></td>
                        <td class="text-right"><strong>{{ $money($totals['credit']) }}</strong></td>
                        <td class="text-right"><strong>{{ $money($totals['closing']) }}</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>
</div>
