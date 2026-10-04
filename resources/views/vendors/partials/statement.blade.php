<section class="master-card master-card--flat vendor-detail-card vendor-block-card" id="vendor-block-statement" aria-labelledby="vendor-block-statement-title">
    <div class="vendor-panel-head">
        <div>
            <h2 class="vendor-detail-title" id="vendor-block-statement-title">Statement of account</h2>
            <p class="vendor-detail-help">The vendor-currency ledger in date order, with the balance after each row. Each currency is its own account.</p>
        </div>
        <div class="vendor-panel-meta">
            <span class="vendor-pill">{{ $summary['statement_count'] }} manual {{ \Illuminate\Support\Str::plural('entry', $summary['statement_count']) }}</span>
            {{-- The manual ledger is the vendor-currency view; this is the same
                 account as a statement the vendor can be sent. --}}
            <a class="master-btn master-btn-soft master-btn-sm"
                href="{{ route('cashflows.statements.show', ['partyType' => 'vendor', 'party' => $vendor->id]) }}">
                <i class="fa-solid fa-file-invoice" aria-hidden="true"></i> Full statement of account
            </a>
        </div>
    </div>

    {{-- One strip for the currencies this vendor trades in: billed, paid and
         the balance that follows, with the rupee side underneath. The four
         stacked figures per card cost a row of height each and said no more. --}}
    <div class="vendor-currency-strip" role="list" aria-label="Ledger by currency">
        @forelse($currencySummary as $currency => $row)
            <div class="vendor-currency-cell" role="listitem">
                <span class="vendor-currency-label">{{ \App\Helpers\CommonHelper::currencyLabel($currency) }}</span>
                <div class="vendor-currency-line">
                    <span>Billed <strong>{{ $money($row['credit'], $currency) }}</strong></span>
                    <span>Paid <strong class="vendor-amount-credit">{{ $money($row['debit'], $currency) }}</strong></span>
                    <span>Balance <strong @class(['vendor-amount-debit' => $row['balance'] > 0])>{{ $money($row['balance'], $currency) }}</strong></span>
                </div>
                <span class="master-sub">In rupees: billed {{ $money($row['inr_credit']) }} · paid {{ $money($row['inr_debit']) }}</span>
            </div>
        @empty
            <div class="vendor-currency-cell" role="listitem">
                <span class="vendor-currency-label">No ledger</span>
                <p class="vendor-empty-text">No vendor-currency rows yet, so there is no statement to build.</p>
            </div>
        @endforelse
    </div>

    <div class="master-card master-card--flat vendor-table-card vendor-table-bleed">
        <div class="master-table-wrap ui-mobile-cards">
            <table class="master-table vendor-table vendor-money-table vendor-money-table--statement">
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Entry</th>
                        <th scope="col">Particular</th>
                        <th scope="col" class="is-num">Amount</th>
                        <th scope="col" class="is-num">Balance</th>
                        <th scope="col" class="ui-mobile-secondary">Account / proof</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($vendorPaymentEntries->sortBy('transaction_date') as $paymentEntry)
                        <tr>
                            <td data-label="Date">{{ $paymentEntry->transaction_date?->format('d M Y') ?: '—' }}</td>
                            <td data-label="Entry">
                                <strong>{{ $paymentEntry->invoice_number ?: 'No invoice' }}</strong>
                                <span class="master-sub">{{ $paymentEntry->categoryLabel() }} · {{ $paymentEntry->statusLabel() }}</span>
                            </td>
                            <td data-label="Particular">
                                <strong>{{ $paymentEntry->particular }}</strong>
                                @if ($paymentEntry->remarks)<span class="master-sub">{{ $paymentEntry->remarks }}</span>@endif
                            </td>
                            <td data-label="Amount" class="is-num {{ $paymentEntry->transaction_type === 'credit' ? 'vendor-amount-debit' : 'vendor-amount-credit' }}">
                                {{ $money($paymentEntry->foreign_amount, $paymentEntry->foreign_currency ?: 'RMB') }}
                                <span class="master-sub">
                                    {{ $money($paymentEntry->amount_in_inr) }}@if ($paymentEntry->exchange_rate) @ {{ number_format((float) $paymentEntry->exchange_rate, 2) }}@endif
                                </span>
                            </td>
                            <td data-label="Balance" class="is-num">
                                <strong>{{ $money($paymentEntry->running_balance, $paymentEntry->foreign_currency ?: 'RMB') }}</strong>
                            </td>
                            <td data-label="Account / proof" class="ui-mobile-secondary">
                                {{ $paymentEntry->paidAccount?->account_name ?: '—' }}
                                <span class="master-sub">
                                    {{ $paymentEntry->bank_reference_number ?: 'No bank reference' }}@foreach($paymentEntry->attachments as $attachment) · <a class="vendor-file-link" href="{{ $attachment->fileUrl() }}" target="_blank" rel="noopener">{{ $attachment->extension ?: 'file' }}</a>@endforeach
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="master-list-empty">
                                    <span class="master-list-empty-icon" aria-hidden="true"><i class="fa-solid fa-file-invoice"></i></span>
                                    <h3 class="master-list-empty-title">No statement rows yet</h3>
                                    <p class="master-list-empty-text">Add entries from the Payments tab and the statement builds itself.</p>
                                    <div class="master-list-empty-actions">
                                        <a class="master-btn master-btn-soft" href="#vendor-block-ledger">Add a ledger entry</a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
