@php
    /**
     * What is owed on this vendor, aged.
     *
     * The ledger says what was billed and what was paid; this says what is
     * still open, when it falls due and how late it already is. Rupees
     * throughout, because a payment is made in rupees whatever the bill was
     * written in — the foreign figure stays on the bill row beside it.
     *
     * One card, three bands: the head, the five ages as one strip, and the open
     * bills. The card the chase list used to occupy is gone — the table below
     * is the same rows, oldest first, with the days late on them.
     */
    $buckets = $payables['buckets'];
    $openRows = $payables['rows'];
    /* The table is the chase list: the 25 that need attention first, in
       chasing order. A wall of rows would push the money tab's own ledger
       off the screen, and the statement already lists every bill. */
    $visibleRows = array_slice($openRows, 0, 25);
    $hiddenRows = count($openRows) - count($visibleRows);
    $hasOverdue = $payables['overdue'] > 0;
    $lateBuckets = ['overdue_1_30', 'overdue_31_60', 'overdue_60_plus'];
@endphp
<section class="master-card master-card--flat vendor-detail-card vendor-block-card" id="vendor-block-payables" aria-labelledby="vendor-block-payables-heading">
    <div class="vendor-panel-head">
        <div>
            <h2 class="vendor-detail-title" id="vendor-block-payables-heading">Payables</h2>
            <p class="vendor-detail-help">Bills and expenses raised by this vendor, less what has been paid. Oldest money is settled first.</p>
        </div>
        <div class="vendor-panel-meta">
            @if ($hasOverdue)
                <span class="vendor-pill is-alert">{{ \App\Helpers\CommonHelper::indianCurrency($payables['overdue']) }} overdue</span>
            @else
                <span class="vendor-pill">Nothing late</span>
            @endif
            @if ($payables['next_due'])
                <span class="vendor-meta-chip">Next due {{ $payables['next_due']->format('d M Y') }}</span>
            @endif
        </div>
    </div>

    {{-- The five ages in one strip: a cell each, hairline divided. Anything
         already late carries its badge, because a number in the right cell
         should not have to be read twice to see that it is late. --}}
    <div class="vendor-ageing" role="list" aria-label="Ageing of what is owed">
        @foreach ($buckets as $key => $bucket)
            <div class="vendor-ageing-cell {{ $bucket['count'] === 0 ? 'is-zero' : '' }}" role="listitem">
                <span class="vendor-ageing-label">{{ $bucket['label'] }}</span>
                <strong class="vendor-ageing-amount is-num">{{ \App\Helpers\CommonHelper::indianCurrency($bucket['amount']) }}</strong>
                @if (in_array($key, $lateBuckets, true) && $bucket['count'] > 0)
                    <span class="vendor-pill is-alert">{{ $bucket['count'] }} {{ \Illuminate\Support\Str::plural('bill', $bucket['count']) }}</span>
                @else
                    <span class="vendor-ageing-count">{{ $bucket['count'] }} {{ \Illuminate\Support\Str::plural('bill', $bucket['count']) }}</span>
                @endif
            </div>
        @endforeach
    </div>

    <div class="vendor-subhead">
        <h3 class="vendor-subhead-title">Open bills</h3>
        <span class="vendor-subhead-note">
            {{ \App\Helpers\CommonHelper::indianCurrency($payables['outstanding']) }} outstanding
            @if (count($openRows))
                · {{ count($openRows) }} {{ \Illuminate\Support\Str::plural('bill', count($openRows)) }}
            @endif
        </span>
    </div>

    @if ($visibleRows)
        <div class="master-table-wrap ui-mobile-cards vendor-table-bleed">
            <table class="master-table vendor-table vendor-money-table vendor-money-table--payables">
                <thead>
                    <tr>
                        <th scope="col">Bill</th>
                        <th scope="col">Due</th>
                        <th scope="col" class="is-num">Open in currency</th>
                        <th scope="col" class="is-num">Open in rupees</th>
                        <th scope="col" class="vendor-table-actions-cell">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($visibleRows as $bill)
                        @php($billPayload = [
                            'id' => $bill['id'],
                            'particular' => $bill['particular'],
                            'invoice' => $bill['invoice'],
                            'currency' => $bill['currency'],
                            'amount' => $bill['foreign_left'],
                        ])
                        <tr>
                            <td data-label="Bill">
                                <strong>{{ $bill['particular'] ?: 'Ledger entry' }}</strong>
                                <span class="master-sub">
                                    {{ $bill['invoice'] ?: 'No invoice number' }} · {{ $bill['date']?->format('d M Y') ?: '—' }}@if ($bill['is_expense']) · Expense @endif
                                </span>
                            </td>
                            <td data-label="Due">
                                @if ($bill['due'])
                                    {{ $bill['due']->format('d M Y') }}
                                    @if ($bill['is_overdue'])
                                        <span class="vendor-late-chip">{{ abs((int) $bill['days_left']) }} {{ \Illuminate\Support\Str::plural('day', abs((int) $bill['days_left'])) }} late</span>
                                    @elseif ($bill['is_due_soon'])
                                        <span class="master-sub">Due within 7 days</span>
                                    @else
                                        <span class="master-sub">{{ abs((int) $bill['days_left']) }} {{ \Illuminate\Support\Str::plural('day', abs((int) $bill['days_left'])) }} left</span>
                                    @endif
                                @else
                                    <span class="master-empty-value">No due date</span>
                                    <span class="master-sub">Set payment terms to fill this in</span>
                                @endif
                            </td>
                            <td data-label="Open in currency" class="is-num">
                                {{ \App\Helpers\CommonHelper::amount($bill['foreign_left'], $bill['currency']) }}
                            </td>
                            <td data-label="Open in rupees" class="is-num">
                                <strong>{{ \App\Helpers\CommonHelper::indianCurrency($bill['rupee_left']) }}</strong>
                            </td>
                            <td data-label="Action" class="vendor-table-actions-cell">
                                <button type="button" class="master-btn master-btn-soft master-btn-sm payBillBtn"
                                    data-bill='@json($billPayload)'>
                                    <i class="fa-solid fa-arrow-up-right-dots" aria-hidden="true"></i> Record payment
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="vendor-empty-text">Nothing open. Every bill from this vendor has been settled.</p>
    @endif

    @if ($hiddenRows > 0)
        <p class="vendor-panel-note">
            {{ $hiddenRows }} more {{ \Illuminate\Support\Str::plural('bill', $hiddenRows) }} beyond the 25 that need attention first —
            <a href="{{ route('cashflows.statements.show', ['partyType' => 'vendor', 'party' => $vendor->id]) }}">the statement lists them all</a>.
        </p>
    @endif

    <div class="vendor-detail-actions">
        <a class="master-btn master-btn-soft master-btn-sm"
            href="{{ route('cashflows.statements.show', ['partyType' => 'vendor', 'party' => $vendor->id]) }}">
            <i class="fa-solid fa-file-invoice" aria-hidden="true"></i> Vendor statement
        </a>
        <a class="master-btn master-btn-light master-btn-sm"
            href="{{ route('vendors.payables.export', ['vendor' => $vendor->id]) }}"
            title="This vendor's dated bills, as a spreadsheet">
            <i class="fa-solid fa-file-csv" aria-hidden="true"></i> Export payables
        </a>
    </div>
</section>
