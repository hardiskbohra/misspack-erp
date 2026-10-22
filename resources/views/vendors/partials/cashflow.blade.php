@php
    /**
     * Cashflow rows the vendor ledger cannot speak for.
     *
     * A rupee payment to a linked vendor is posted to the ledger above as soon
     * as it is saved, so it is not repeated here. What is left is money the
     * ledger has no row for — a payment to a vendor matched by name, a
     * foreign-currency row, or money received back — and that is exactly what
     * must not be invisible on this page.
     */
    $cashflowRows = $statementEntries ?? collect();
@endphp

@if ($cashflowRows->isNotEmpty())
    <section class="master-card master-card--flat vendor-detail-card vendor-block-card" id="vendor-block-cashflow" aria-labelledby="vendor-block-cashflow-title">
        <div class="vendor-panel-head">
            <div>
                <h2 class="vendor-detail-title" id="vendor-block-cashflow-title">Other cashflow activity</h2>
                <p class="vendor-detail-help">Rupee rows from the cashflow ledger that are not in the vendor-currency ledger above. A payment recorded against this vendor with a linked vendor is posted to the ledger automatically.</p>
            </div>
            <div class="vendor-panel-meta">
                <span class="vendor-pill">{{ $cashflowRows->count() }} {{ \Illuminate\Support\Str::plural('row', $cashflowRows->count()) }}</span>
                @if (\Illuminate\Support\Facades\Route::has('cashflows.index'))
                    <a class="master-btn master-btn-soft master-btn-sm" href="{{ route('cashflows.index', ['search' => $vendor->vendor_name]) }}">
                        <i class="fa-solid fa-scale-balanced" aria-hidden="true"></i> Open cashflow
                    </a>
                @endif
            </div>
        </div>

        <div class="master-card master-card--flat vendor-table-card vendor-table-bleed">
            <div class="master-table-wrap ui-mobile-cards">
                <table class="master-table vendor-table vendor-money-table">
                    <thead>
                        <tr>
                            <th scope="col">Date</th>
                            <th scope="col">Particular</th>
                            <th scope="col">Reference</th>
                            <th scope="col" class="is-num">Amount</th>
                            <th scope="col" class="ui-mobile-secondary">Account</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cashflowRows as $cashflowRow)
                            @php($isPaid = $cashflowRow->transaction_type === 'debit')
                            <tr>
                                <td data-label="Date">{{ $cashflowRow->entry_date?->format('d M Y') ?: '—' }}</td>
                                <td data-label="Particular">
                                    <strong>{{ $cashflowRow->particular ?: 'Cashflow entry' }}</strong>
                                    <span class="master-sub">{{ $isPaid ? 'Paid out' : 'Received' }} · cashflow #{{ $cashflowRow->id }}</span>
                                </td>
                                <td data-label="Reference">
                                    {{ $cashflowRow->invoice_bill_number ?: '—' }}
                                    @if ($cashflowRow->bank_reference_number)
                                        <span class="master-sub">{{ $cashflowRow->bank_reference_number }}</span>
                                    @endif
                                </td>
                                <td data-label="Amount" class="is-num {{ $isPaid ? 'vendor-amount-debit' : 'vendor-amount-credit' }}">
                                    {{ \App\Helpers\CommonHelper::amount($cashflowRow->amountMoved(), $cashflowRow->currency ?: 'INR') }}
                                    <span class="master-sub">{{ $isPaid ? 'Paid' : 'Received' }}</span>
                                </td>
                                <td data-label="Account" class="ui-mobile-secondary">
                                    {{ $cashflowRow->account?->account_name ?: '—' }}
                                    <span class="master-sub">{{ $cashflowRow->payment_mode ? str_replace('_', ' ', $cashflowRow->payment_mode) : '—' }}</span>
                                </td>
                                <td data-label="Action" class="vendor-table-actions-cell">
                                    @if (\Illuminate\Support\Facades\Route::has('cashflows.show'))
                                        <a class="master-icon-btn" href="{{ route('cashflows.show', $cashflowRow) }}"
                                            aria-label="Open cashflow entry {{ $cashflowRow->id }}" title="Open cashflow entry">
                                            <i class="far fa-eye" aria-hidden="true"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endif
