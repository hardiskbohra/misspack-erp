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
    $statementAsOf = $statement['period']['to'] ?? $statement['generated_at'];
@endphp

<div class="vendor-detail-stack vendor-statement-workspace">
    <section class="master-card master-card--flat vendor-detail-card vendor-statement-filter-card" aria-labelledby="vendor-statement-filters-heading">
        <div class="vendor-statement-toolbar-head">
            <div>
                <p class="vendor-tab-eyebrow">Live vendor ledger</p>
                <h2 class="master-section-title" id="vendor-statement-filters-heading">Statement entries</h2>
            </div>
            <div class="vendor-statement-toolbar-actions">
                <span class="master-chip">{{ number_format($totals['count']) }} {{ \Illuminate\Support\Str::plural('entry', $totals['count']) }}</span>
                @if (\Illuminate\Support\Facades\Route::has('cashflows.statements.show'))
                    <a class="master-btn master-btn-soft" href="{{ route('cashflows.statements.show', array_merge(['partyType' => 'vendor', 'party' => $vendor->id], array_filter(['period' => $periodKey, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'currency' => $statement['currency']]))) }}">
                        <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> Full statement tools
                    </a>
                @endif
            </div>
        </div>

        <form method="GET" action="{{ route('vendors.show', $vendor) }}">
            <input type="hidden" name="tab" value="statement">
            <div class="core-filter-toolbar">
                <x-filter-trigger drawer="vendorStatementFiltersDrawer" label="Statement filters"
                    :count="(($periodKey !== 'this_month' || filled($dateFrom) || filled($dateTo)) ? 1 : 0) + ($statement['currency'] !== ($vendor->preferred_currency ?: 'INR') ? 1 : 0)" />
            </div>
            <x-drawer id="vendorStatementFiltersDrawer" title="Statement filters" eyebrow="Vendor ledger"
                subtitle="Choose the reporting period, date range, and currency." size="medium">
                <section class="core-drawer-section">
                    <h3 class="core-drawer-section-title">Reporting period</h3>
                    <div class="core-drawer-fields">
                        <div class="master-field">
                            <label class="master-label" for="vendorStatementPeriod">Period</label>
                            <select class="master-select" id="vendorStatementPeriod" name="period">
                                @foreach ($periodOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($periodKey === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="vendorStatementFrom">From</label>
                            <input class="master-input" id="vendorStatementFrom" type="date" name="date_from" value="{{ $dateFrom }}" onchange="document.getElementById('vendorStatementPeriod').value='custom'">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="vendorStatementTo">To</label>
                            <input class="master-input" id="vendorStatementTo" type="date" name="date_to" value="{{ $dateTo }}" onchange="document.getElementById('vendorStatementPeriod').value='custom'">
                        </div>
                        @if (count($currencyOptions) > 1)
                            <div class="master-field">
                                <label class="master-label" for="vendorStatementCurrency">Currency</label>
                                <select class="master-select" id="vendorStatementCurrency" name="currency">
                                    @foreach ($currencyOptions as $code)
                                        <option value="{{ $code }}" @selected($statement['currency'] === $code)>{{ \App\Helpers\CommonHelper::currencyLabel($code) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @else
                            <input type="hidden" name="currency" value="{{ $statement['currency'] }}">
                        @endif
                    </div>
                </section>
                <x-slot:footer>
                    <button class="master-btn master-btn-soft" type="submit" formmethod="GET"
                        formaction="{{ route('cashflows.statements.pdf', ['partyType' => 'vendor', 'party' => $vendor->id]) }}"
                        formtarget="_blank" name="ageing" value="0"
                        aria-label="Download vendor statement PDF for the selected period">
                        <i class="fa-solid fa-file-arrow-down" aria-hidden="true"></i> Download PDF
                    </button>
                    <button class="master-btn master-btn-primary" type="submit">Apply filters</button>
                </x-slot:footer>
            </x-drawer>
        </form>
        <p class="vendor-statement-source">{{ rtrim($statement['source_note'], '. ') }} · Balance as on {{ $statementAsOf->format('d M Y') }}</p>
    </section>

    <div class="vendor-statement-metrics" aria-label="Vendor statement balances">
        <section class="master-stat master-stat--flat blue vendor-statement-metric">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-wallet"></i></span>
            <div><p class="master-stat-title">Opening balance</p><p class="master-stat-value">{{ $money($statement['opening']) }}</p></div>
        </section>
        <section class="master-stat master-stat--flat orange vendor-statement-metric">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-arrow-up"></i></span>
            <div><p class="master-stat-title">Payments · debits</p><p class="master-stat-value">{{ $money($totals['debit']) }}</p></div>
        </section>
        <section class="master-stat master-stat--flat teal vendor-statement-metric">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-arrow-down"></i></span>
            <div><p class="master-stat-title">Bills · credits</p><p class="master-stat-value">{{ $money($totals['credit']) }}</p></div>
        </section>
        <section class="master-stat master-stat--flat purple vendor-statement-metric is-balance">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-scale-balanced"></i></span>
            <div>
                <p class="master-stat-title">Closing balance</p>
                <p class="master-stat-value">{{ $money($totals['closing']) }}</p>
                <p class="master-sub vendor-statement-balance-label">{{ $totals['closing'] >= 0 ? 'Payable to vendor' : 'Vendor credit / advance' }}</p>
            </div>
        </section>
    </div>

    @if ($otherCurrencies)
        <div class="vendor-statement-other-currencies"><strong>Other currency activity</strong><span>{{ $otherCurrencies }}</span></div>
    @endif

    <section class="master-card master-card--flat vendor-detail-card vendor-statement-entries" aria-labelledby="vendor-statement-entries-heading">
        <div class="master-section-head">
            <div>
                <h2 class="master-section-title" id="vendor-statement-entries-heading">Transactions</h2>
                <p class="master-sub">{{ \App\Helpers\CommonHelper::currencyLabel($statement['currency']) }} balance · positive closing balance is payable to this vendor.</p>
            </div>
        </div>
        <div class="master-table-wrap vendor-statement-table-wrap">
            <table class="master-table vendor-detail-table vendor-statement-table">
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Particulars</th>
                        <th scope="col">Reference</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-right">Debit · payment</th>
                        <th scope="col" class="text-right">Credit · bill</th>
                        <th scope="col" class="text-right">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="vendor-statement-opening-row">
                        <td>{{ $statement['period']['from']?->copy()->subDay()->format('d M Y') ?: '—' }}</td>
                        <td colspan="3"><strong>Opening balance</strong></td>
                        <td class="text-right">—</td>
                        <td class="text-right">—</td>
                        <td class="text-right"><strong>{{ $money($statement['opening']) }}</strong></td>
                    </tr>
                    @forelse ($statement['rows'] as $row)
                        <tr>
                            <td>{{ $row['date']?->format('d M Y') ?: '—' }}</td>
                            <td>{{ $row['particular'] }}</td>
                            <td>{{ $row['reference'] ?: '—' }}</td>
                            <td>
                                @if ($row['status'])
                                    <span class="master-badge status-{{ \Illuminate\Support\Str::slug($row['status']) }}">{{ $row['status'] }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-right">{{ $row['debit'] > 0 ? $money($row['debit']) : '—' }}</td>
                            <td class="text-right">{{ $row['credit'] > 0 ? $money($row['credit']) : '—' }}</td>
                            <td class="text-right"><strong>{{ $money($row['balance']) }}</strong></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="master-empty-state"><i class="fa-solid fa-scale-balanced" aria-hidden="true"></i><p>No entries fall within the selected filters.</p></div></td></tr>
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
