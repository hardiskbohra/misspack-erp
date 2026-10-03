@extends('layouts.app')

@section('page-title', 'Statements of account')

@section('page-actions')
    <a class="master-btn master-btn-ghost desktop-only" href="{{ route('cashflows.documents') }}">Document archive</a>
    <a class="master-btn master-btn-soft" href="{{ route('cashflows.index') }}">Back to Ledger</a>
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/cashflows.css') }}">
    {{-- the shared list chrome, after the module sheet like every other list --}}
    <link rel="stylesheet" href="{{ $assetVer('assets/css/master-list.css') }}">
@endpush
@php
    /* Every filter is one URL away from the others, so a chip keeps what it does
       not own: the party type, the period and the currency travel together. */
    $chipUrl = function (array $overrides) {
        $keep = array_merge(request()->except(['page']), $overrides);

        return route('cashflows.statements', array_filter(
            $keep,
            fn ($value, $key) => ! in_array($key, ['share'], true)
                && $value !== null && $value !== '' && $value !== 'all',
            ARRAY_FILTER_USE_BOTH
        ));
    };

    $typeChips = ['all' => 'All parties', 'client' => 'Clients', 'vendor' => 'Vendors'];
    $filtered = trim((string) $q) !== ''
        || ($partyType !== 'all')
        || $currency !== 'INR'
        || $periodKey !== 'this_month';
    $isVendor = $partyType === 'vendor';
    $debitWord = $isVendor ? 'Billed' : 'Invoiced';
    $creditWord = $isVendor ? 'Paid' : 'Received';
@endphp

<div class="cf cashflow-statements master-list">

    <div class="master-stats desktop-only">
        <div class="master-stat master-stat--flat blue tooltip-container">
            <span class="icon" aria-hidden="true">🧾</span>
            <div>
                <p class="master-stat-title">Statements to send</p>
                <p class="master-stat-value">{{ number_format($totals['parties']) }}</p>
                <p class="master-sub">parties with movement or a balance</p>
                <span class="tooltip-text">One line per party and currency: a vendor billed in RMB and paid in rupees holds two balances, and each is its own statement.</span>
            </div>
        </div>
        <div class="master-stat master-stat--flat {{ $totals['closing'] > 0 ? 'orange' : 'green' }} tooltip-container">
            <span class="icon" aria-hidden="true">⏳</span>
            <div>
                <p class="master-stat-title">{{ $isVendor ? 'Payable' : ($partyType === 'client' ? 'Receivable' : 'Still open') }}</p>
                <p class="master-stat-value">{{ \App\Helpers\CommonHelper::amount($totals['closing'], $currency) }}</p>
                <p class="master-sub">{{ $totals['balanced'] }} {{ \Illuminate\Support\Str::plural('party', $totals['balanced']) }} with a balance</p>
                <span class="tooltip-text">Carried in plus this period's movement, in
                    {{ \App\Helpers\CommonHelper::currencyLabel($currency) }}. Parties in another currency are on their own
                    line and are not added in here.</span>
            </div>
        </div>
        <div class="master-stat master-stat--flat purple tooltip-container">
            <span class="icon" aria-hidden="true">🔗</span>
            <div>
                <p class="master-stat-title">Live links</p>
                <p class="master-stat-value">{{ number_format($liveCount) }}</p>
                <p class="master-sub">{{ number_format($sentThisMonth) }} sent this month</p>
                <span class="tooltip-text">Links that have not expired and were not revoked. Every open is counted, so "did they look at it" has an answer.</span>
            </div>
        </div>
        <div class="master-stat master-stat--flat teal tooltip-container">
            <span class="icon" aria-hidden="true">₹</span>
            <div>
                <p class="master-stat-title">Movement this period</p>
                <p class="master-stat-value">{{ \App\Helpers\CommonHelper::amount($totals['debit'] + $totals['credit'], $currency) }}</p>
                <p class="master-sub">{{ $debitWord }} + {{ strtolower($creditWord) }}</p>
                <span class="tooltip-text">Everything the filtered parties moved in this period — the statements themselves carry the running balance.</span>
            </div>
        </div>
    </div>

    <div class="master-card master-card--flat">
        <div class="master-list-bar">
            <div class="master-list-chips">
                @foreach ($typeChips as $key => $label)
                    <a class="master-list-chip {{ $partyType === $key ? 'is-active' : '' }}"
                        href="{{ $chipUrl(['party_type' => $key]) }}">{{ $label }}</a>
                @endforeach
            </div>

            <div class="master-list-chips">
                @foreach ($dateRanges as $rangeKey => $range)
                    <a class="master-list-chip {{ $activeRange === $rangeKey ? 'is-active' : '' }}"
                        href="{{ $chipUrl(['period' => $rangeKey, 'date_from' => $range['from'], 'date_to' => $range['to']]) }}">
                        {{ $dateRangeLabels[$rangeKey] }}
                    </a>
                @endforeach
                <a class="master-list-chip {{ $activeRange === 'all' ? 'is-active' : '' }}"
                    href="{{ $chipUrl(['period' => 'all', 'date_from' => null, 'date_to' => null]) }}"
                    title="Every party with a balance, whenever it was booked">All time</a>
            </div>
        </div>

        <form method="GET" action="{{ route('cashflows.statements') }}">
            <div class="master-filter-row">
                {{-- The type is the chips' dimension and nobody else's: one
                     control per question, carried here so Apply keeps the chip
                     that is lit. --}}
                <input type="hidden" name="party_type" value="{{ $partyType }}">

                <div class="master-search">
                    <span aria-hidden="true">⌕</span>
                    <input class="master-input" type="text" name="q" value="{{ $q }}"
                        placeholder="Search party name..." aria-label="Search party">
                </div>
                <select class="master-select" name="currency" aria-label="Statement currency">
                    @foreach ($currencyOptions as $code)
                        <option value="{{ $code }}" @selected($currency === $code)>Statements in
                            {{ \App\Helpers\CommonHelper::currencyLabel($code) }}</option>
                    @endforeach
                </select>
                <input class="master-input desktop-only" type="date" name="date_from" value="{{ $dateFrom }}"
                    aria-label="From" title="From">
                <input class="master-input desktop-only" type="date" name="date_to" value="{{ $dateTo }}"
                    aria-label="To" title="To">

                <div class="master-list-filter-group">
                    @if ($filtered)
                        <a class="master-btn master-btn-soft" href="{{ route('cashflows.statements', ['period' => 'this_month']) }}">Reset</a>
                    @endif
                    <button class="master-btn master-btn-primary" type="submit">Apply filters</button>
                </div>
            </div>

            @if ($filtered)
                <div class="master-list-applied">
                    <span class="master-list-applied-title">Filtered by</span>

                    @if ($partyType !== 'all')
                        <span class="master-list-applied-chip">
                            <span class="master-list-applied-key">Statement</span>
                            <span class="master-list-applied-value">{{ $typeChips[$partyType] }}</span>
                            <a class="master-list-applied-x" href="{{ $chipUrl(['party_type' => 'all']) }}"
                                aria-label="Remove the party type filter" title="Remove the party type filter">&times;</a>
                        </span>
                    @endif

                    @if (trim((string) $q) !== '')
                        <span class="master-list-applied-chip">
                            <span class="master-list-applied-key">Search</span>
                            <span class="master-list-applied-value">{{ $q }}</span>
                            <a class="master-list-applied-x" href="{{ $chipUrl(['q' => null]) }}"
                                aria-label="Clear the search" title="Clear the search">&times;</a>
                        </span>
                    @endif

                    @if ($currency !== 'INR')
                        <span class="master-list-applied-chip">
                            <span class="master-list-applied-key">Currency</span>
                            <span class="master-list-applied-value">{{ \App\Helpers\CommonHelper::currencyLabel($currency) }}</span>
                            <a class="master-list-applied-x" href="{{ $chipUrl(['currency' => 'INR']) }}"
                                aria-label="Back to rupee statements" title="Back to rupee statements">&times;</a>
                        </span>
                    @endif

                    @if ($activeRange !== 'this_month')
                        <span class="master-list-applied-chip">
                            <span class="master-list-applied-key">Period</span>
                            <span class="master-list-applied-value">
                                {{ $activeRange === 'all' ? 'All time' : ($dateRangeLabels[$activeRange] ?? $dateFrom.' → '.$dateTo) }}
                            </span>
                            <a class="master-list-applied-x"
                                href="{{ $chipUrl(['period' => 'this_month', 'date_from' => null, 'date_to' => null]) }}"
                                aria-label="Back to this month" title="Back to this month">&times;</a>
                        </span>
                    @endif
                </div>
            @endif
        </form>

        <div class="master-table-wrap">
            <table class="master-table">
                <thead>
                    <tr>
                        <th scope="col">Party</th>
                        <th scope="col">Currency</th>
                        <th scope="col" class="is-num">Opening</th>
                        <th scope="col" class="is-num">Debit <em>({{ strtolower($debitWord) }})</em></th>
                        <th scope="col" class="is-num">Credit <em>({{ strtolower($creditWord) }})</em></th>
                        <th scope="col" class="is-num">{{ $isVendor ? 'Payable' : 'Balance' }}</th>
                        <th scope="col" class="is-num">Rows</th>
                        <th scope="col">Last activity</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td data-label="Party">
                                <strong>{{ $row['name'] }}</strong>
                                <span class="master-sub">{{ $row['party_type_label'] }}</span>
                            </td>
                            <td data-label="Currency"><span
                                    class="stmt-chip stmt-chip--row">{{ \App\Helpers\CommonHelper::currencyLabel($row['currency']) }}</span></td>
                            <td class="is-num" data-label="Opening">
                                {{ \App\Helpers\CommonHelper::amount($row['opening'], $row['currency']) }}</td>
                            <td class="is-num" data-label="Debit">
                                {{ \App\Helpers\CommonHelper::amount($row['debit'], $row['currency']) }}</td>
                            <td class="is-num" data-label="Credit">
                                {{ \App\Helpers\CommonHelper::amount($row['credit'], $row['currency']) }}</td>
                            <td class="is-num" data-label="{{ $isVendor ? 'Payable' : 'Balance' }}">
                                <strong>{{ \App\Helpers\CommonHelper::amount($row['closing'], $row['currency']) }}</strong>
                            </td>
                            <td class="is-num" data-label="Rows">{{ number_format($row['count']) }}</td>
                            <td data-label="Last activity">
                                {{ $row['last_date'] ? \Illuminate\Support\Carbon::parse($row['last_date'])->format('d M Y') : '—' }}
                            </td>
                            <td data-label="Action">
                                {{-- The period travels as the preset it is, not
                                     only as two dates: "All time" has no dates to
                                     carry, and a drill-down that silently fell back
                                     to this month would print a different statement
                                     from the line that was clicked. --}}
                                <a class="master-btn master-btn-soft master-btn-sm"
                                    href="{{ route('cashflows.statements.show', array_filter(['partyType' => $row['party_type'], 'party' => $row['party_id'], 'period' => $periodKey, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'currency' => $row['currency']])) }}">
                                    Statement
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <div class="master-list-empty">
                                    <span class="master-list-empty-icon" aria-hidden="true">🧾</span>
                                    <p class="master-list-empty-title">
                                        {{ $filtered ? 'No party matches these filters' : 'Nothing to statement this month' }}
                                    </p>
                                    <p class="master-list-empty-text">
                                        {{ $filtered
                                            ? 'Widen the period, or clear the search — a party with a balance carried in still belongs here.'
                                            : 'Statements are built from the ledger: sales invoices and receipts for a client, bills and payments for a vendor. Book something and it appears here.' }}
                                    </p>
                                    <div class="master-list-empty-actions">
                                        @if ($filtered)
                                            <a class="master-btn master-btn-soft" href="{{ route('cashflows.statements') }}">Clear filters</a>
                                        @endif
                                        <a class="master-btn master-btn-primary" href="{{ route('cashflows.index') }}">Open the ledger</a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($rows !== [])
                    <tfoot>
                        <tr class="master-list-total">
                            <td colspan="5">
                                <strong>Total — {{ $totals['parties'] }}
                                    {{ \Illuminate\Support\Str::plural('statement', $totals['parties']) }} to send</strong>
                                <span class="master-sub">Opening + this period's movement, in
                                    {{ \App\Helpers\CommonHelper::currencyLabel($currency) }}</span>
                            </td>
                            <td class="is-num">
                                <strong>{{ \App\Helpers\CommonHelper::amount($totals['closing'], $currency) }}</strong>
                                <span class="master-sub">{{ $isVendor ? 'Payable' : 'Balance' }}</span>
                            </td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <div class="master-card master-card--flat">
        <div class="master-list-bar">
            <div class="stmt-log-head">
                <p class="stmt-log-title">Sent statements</p>
                <p class="master-sub stmt-log-note">What left the building, when, and whether it was opened.</p>
            </div>
        </div>

        <div class="master-table-wrap">
            <table class="master-table">
                <thead>
                    <tr>
                        <th scope="col">Party</th>
                        <th scope="col">Statement</th>
                        <th scope="col">Currency</th>
                        <th scope="col">Sent</th>
                        <th scope="col">Expires</th>
                        <th scope="col">Opens</th>
                        <th scope="col">State</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($shares as $share)
                        <tr>
                            <td data-label="Party">
                                <strong>{{ $share->party_name }}</strong>
                                <span class="master-sub">{{ $share->partyTypeLabel() }}</span>
                            </td>
                            <td data-label="Statement">
                                {{ $share->title() }}
                                <span class="master-sub">{{ $share->periodLabel() }}</span>
                            </td>
                            <td data-label="Currency">{{ \App\Helpers\CommonHelper::currencyLabel($share->party_currency) }}</td>
                            <td data-label="Sent">
                                {{ $share->created_at?->format('d M Y') }}
                                <span class="master-sub">{{ $share->channelLabel() }}</span>
                            </td>
                            <td data-label="Expires">{{ $share->expiresLabel() }}</td>
                            <td data-label="Opens">
                                {{ number_format($share->views) }}
                                @if ($share->last_viewed_at)
                                    <span class="master-sub">last {{ $share->last_viewed_at->format('d M Y') }}</span>
                                @endif
                            </td>
                            <td data-label="State">
                                <span class="cf-doc-state {{ $share->state() === 'live' ? 'is-linked' : 'is-unlinked' }}">
                                    {{ $share->stateLabel() }}
                                </span>
                            </td>
                            <td data-label="Action">
                                <div class="stmt-log-actions">
                                    <a class="master-btn master-btn-soft master-btn-sm" href="{{ $share->url() }}"
                                        target="_blank" rel="noopener">Open</a>
                                    @if ($share->state() === 'live')
                                        <form method="POST" action="{{ route('cashflows.statements.shares.revoke', $share) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button class="master-btn master-btn-light master-btn-sm" type="submit"
                                                title="Close the link without losing the record">Revoke</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('cashflows.statements.shares.destroy', $share) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="master-btn master-btn-light master-btn-sm" type="submit"
                                                title="Remove the log entry">Remove</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="master-list-empty">
                                    <span class="master-list-empty-icon" aria-hidden="true">🔗</span>
                                    <p class="master-list-empty-title">No statement has been sent yet</p>
                                    <p class="master-list-empty-text">
                                        Open a party's statement above, then press <em>Share link</em>. The link carries the
                                        period and the currency it was built for, expires on its own, and every open is
                                        counted here.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
    <script src="{{ $assetVer('assets/js/cashflows.js') }}"></script>
@endpush
@endsection
