@php
    $periodOptions = $periodOptions ?? ($dateRangeLabels + ['all' => 'All time', 'custom' => 'Custom dates']);
    $statementQuery = array_filter([
        'partyType' => 'client',
        'party' => $client->id,
        'period' => $periodKey,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'currency' => $currency,
        'ageing' => $ageing ? '1' : '0',
    ], fn ($value) => $value !== null && $value !== '');
@endphp

<div class="client-detail-tools">
    <section class="master-card master-card--flat client-detail-card client-detail-card--wide client-statement-filters no-print" aria-labelledby="client-statement-filters-heading">
        <div class="client-statement-toolbar-head">
            <div>
                <p class="cpa-eyebrow">Live ledger</p>
                <h2 class="client-detail-title" id="client-statement-filters-heading">Client statement</h2>
            </div>
            <div class="client-statement-actions">
                <a class="master-btn master-btn-soft master-btn-sm" href="{{ route('cashflows.statements.pdf', $statementQuery) }}"><i class="fa-solid fa-file-arrow-down" aria-hidden="true"></i> PDF / print</a>
                <a class="master-btn master-btn-primary master-btn-sm" href="{{ route('cashflows.statements.show', $statementQuery) }}"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> Full statement tools</a>
            </div>
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
            @endif
            <div class="client-statement-ageing">
                <input type="hidden" name="ageing" value="0">
                <label class="master-check"><input type="checkbox" name="ageing" value="1" @checked($ageing)> Include receivables ageing</label>
            </div>
            <div class="client-statement-filter-submit"><button class="master-btn master-btn-primary" type="submit">Update statement</button></div>
        </form>
        <p class="client-statement-source">{{ $statement['source_note'] }}@if($statement['period']['from'] || $statement['period']['to']) · Balance as on {{ ($statement['period']['to'] ?? $statement['generated_at'])->format('d M Y') }}@endif</p>
    </section>

    <section class="master-card master-card--flat client-detail-card client-detail-card--wide client-statement-sheet" aria-label="Statement of account for {{ $client->company_name }}">
        <div class="client-statement-scroll">
            @include('cashflows.partials.statement', ['statement' => $statement, 'context' => 'app'])
        </div>
    </section>
</div>
