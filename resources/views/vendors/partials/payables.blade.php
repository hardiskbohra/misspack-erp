@php
    /**
     * What is owed on this vendor, aged.
     *
     * The ledger says what was billed and what was paid; this says what is
     * still open, when it falls due and how late it already is. Rupees
     * throughout, because a payment is made in rupees whatever the bill was
     * written in — the foreign figure stays on the bill row beside it.
     */
    $buckets = $payables['buckets'];
    $openRows = $payables['rows'];
    $hasOverdue = $payables['overdue'] > 0;
@endphp
<section class="master-card master-card--flat vendor-detail-card vendor-block-card" id="vendor-block-payables" aria-labelledby="vendor-block-payables-heading">
    <div class="vendor-panel-head vendor-panel-head--spaced">
        <div>
            <h2 class="vendor-detail-title" id="vendor-block-payables-heading">Payables</h2>
            <p class="vendor-detail-help">Bills and expenses raised by this vendor, less what has been paid. Oldest money is settled first.</p>
        </div>
        <div class="vendor-panel-meta">
            <span class="vendor-pill {{ $hasOverdue ? 'is-alert' : '' }}">
                {{ \App\Helpers\CommonHelper::indianCurrency($payables['overdue']) }} overdue
            </span>
            @if ($payables['next_due'])
                <span class="vendor-meta-chip">Next due {{ $payables['next_due']->format('d M Y') }}</span>
            @endif
        </div>
    </div>

    <div class="vendor-ageing">
        @foreach ($buckets as $key => $bucket)
            <div class="master-card master-card--flat vendor-ageing-card {{ $key === 'overdue_60_plus' && $bucket['count'] ? 'is-alert' : '' }} {{ $bucket['count'] === 0 ? 'is-empty' : '' }}">
                <span class="vendor-ageing-label">{{ $bucket['label'] }}</span>
                <strong class="vendor-ageing-amount is-num">{{ \App\Helpers\CommonHelper::indianCurrency($bucket['amount']) }}</strong>
                <span class="vendor-ageing-count">{{ $bucket['count'] }} {{ \Illuminate\Support\Str::plural('bill', $bucket['count']) }}</span>
            </div>
        @endforeach
    </div>

    <div class="vendor-detail-grid">
        <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-open-bills-heading">
            <div class="vendor-card-head">
                <h3 class="vendor-detail-title" id="vendor-open-bills-heading">Open bills</h3>
                <span class="vendor-card-link">{{ \App\Helpers\CommonHelper::indianCurrency($payables['outstanding']) }} outstanding</span>
            </div>

            @if ($openRows)
                <div class="master-table-wrap ui-mobile-cards">
                    <table class="master-table vendor-bill-table">
                        <thead>
                            <tr>
                                <th scope="col">Bill</th>
                                <th scope="col">Due</th>
                                <th scope="col" class="is-num">Open in currency</th>
                                <th scope="col" class="is-num">Open in rupees</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($openRows as $bill)
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
                                            {{ $bill['invoice'] ?: 'No invoice number' }} ·
                                            {{ $bill['date']?->format('d M Y') ?: '—' }}
                                            @if ($bill['is_expense']) · Expense @endif
                                        </span>
                                    </td>
                                    <td data-label="Due">
                                        @if ($bill['due'])
                                            {{ $bill['due']->format('d M Y') }}
                                            <span class="master-sub">
                                                @if ($bill['is_overdue'])
                                                    {{ abs((int) $bill['days_left']) }} {{ \Illuminate\Support\Str::plural('day', abs((int) $bill['days_left'])) }} late
                                                @elseif ($bill['is_due_soon'])
                                                    Due within 7 days
                                                @else
                                                    {{ abs((int) $bill['days_left']) }} {{ \Illuminate\Support\Str::plural('day', abs((int) $bill['days_left'])) }} left
                                                @endif
                                            </span>
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
                                    <td data-label="Action">
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
        </section>

        <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-overdue-heading">
            <div class="vendor-card-head">
                <h3 class="vendor-detail-title" id="vendor-overdue-heading">Chase list</h3>
                @if ($hasOverdue)
                    <span class="vendor-card-link">{{ $payables['overdue_count'] }} overdue</span>
                @endif
            </div>

            @if ($hasOverdue)
                <div class="vendor-mini-list">
                    @foreach ($payables['overdue_rows'] as $bill)
                        <div class="vendor-mini-row">
                            <div>
                                <strong>{{ $bill['particular'] ?: 'Ledger entry' }}</strong>
                                <small>
                                    {{ $bill['invoice'] ?: 'No invoice number' }} ·
                                    {{ abs((int) $bill['days_left']) }} {{ \Illuminate\Support\Str::plural('day', abs((int) $bill['days_left'])) }} late
                                </small>
                            </div>
                            <b class="is-num">{{ \App\Helpers\CommonHelper::indianCurrency($bill['rupee_left']) }}</b>
                        </div>
                    @endforeach
                </div>
                <p class="vendor-detail-help">
                    Oldest first. Send the statement and the office can settle the whole account in one payment.
                </p>
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
            @else
                <p class="vendor-empty-text">Nothing is late. Bills that pass their due date appear here with how long they have been waiting.</p>
            @endif
        </section>
    </div>
</section>
