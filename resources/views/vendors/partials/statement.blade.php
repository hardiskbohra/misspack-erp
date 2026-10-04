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

    <div class="vendor-currency-summary">
        @forelse($currencySummary as $currency => $row)
            <div class="master-card master-card--flat vendor-currency-card">
                <span class="vendor-currency-card-label">{{ \App\Helpers\CommonHelper::currencyLabel($currency) }}</span>
                <div class="vendor-currency-card-figures">
                    <div><span>Bills</span><strong>{{ $money($row['credit'], $currency) }}</strong></div>
                    <div><span>Paid</span><strong class="vendor-amount-credit">{{ $money($row['debit'], $currency) }}</strong></div>
                    <div><span>Balance</span><strong @class(['vendor-amount-negative' => $row['balance'] > 0])>{{ $money($row['balance'], $currency) }}</strong></div>
                    <div><span>Expenses</span><strong>{{ $money($row['expense'], $currency) }}</strong></div>
                </div>
                <span class="master-sub">In rupees: billed {{ $money($row['inr_credit']) }} · paid {{ $money($row['inr_debit']) }}</span>
            </div>
        @empty
            <div class="master-card master-card--flat vendor-currency-card">
                <span class="vendor-currency-card-label">No ledger</span>
                <p class="vendor-empty-text">No vendor-currency rows yet, so there is no statement to build.</p>
            </div>
        @endforelse
    </div>

    <div class="master-card master-card--flat vendor-table-card">
        <div class="master-table-wrap ui-mobile-cards">
            <table class="master-table vendor-table">
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Invoice</th>
                        <th scope="col">Particular</th>
                        <th scope="col" class="ui-mobile-secondary">Category</th>
                        <th scope="col" class="is-num">Credit / bill</th>
                        <th scope="col" class="is-num">Debit / paid</th>
                        <th scope="col" class="is-num">Balance</th>
                        <th scope="col" class="is-num ui-mobile-secondary">Rupee value</th>
                        <th scope="col" class="ui-mobile-secondary">Account / proof</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($vendorPaymentEntries->sortBy('transaction_date') as $paymentEntry)
                        <tr>
                            <td data-label="Date">{{ $paymentEntry->transaction_date?->format('d M Y') ?: '—' }}</td>
                            <td data-label="Invoice">
                                {{ $paymentEntry->invoice_number ?: '—' }}
                                <span class="master-sub">{{ $paymentEntry->bank_reference_number ?: '' }}</span>
                            </td>
                            <td data-label="Particular">
                                <strong>{{ $paymentEntry->particular }}</strong>
                                @if ($paymentEntry->remarks)<span class="master-sub">{{ $paymentEntry->remarks }}</span>@endif
                            </td>
                            <td data-label="Category" class="ui-mobile-secondary">
                                {{ $paymentEntry->categoryLabel() }}
                                <span class="master-sub">{{ $paymentEntry->statusLabel() }}</span>
                            </td>
                            <td data-label="Credit / bill" class="is-num vendor-amount-debit">
                                {{ $paymentEntry->transaction_type === 'credit' ? $money($paymentEntry->foreign_amount, $paymentEntry->foreign_currency ?: 'RMB') : '—' }}
                            </td>
                            <td data-label="Debit / paid" class="is-num vendor-amount-credit">
                                {{ $paymentEntry->transaction_type === 'debit' ? $money($paymentEntry->foreign_amount, $paymentEntry->foreign_currency ?: 'RMB') : '—' }}
                            </td>
                            <td data-label="Balance" class="is-num">
                                <strong>{{ $money($paymentEntry->running_balance, $paymentEntry->foreign_currency ?: 'RMB') }}</strong>
                            </td>
                            <td data-label="Rupee value" class="is-num ui-mobile-secondary">
                                {{ $money($paymentEntry->amount_in_inr) }}
                                <span class="master-sub">Rate {{ $paymentEntry->exchange_rate ? number_format((float) $paymentEntry->exchange_rate, 2) : '—' }}</span>
                            </td>
                            <td data-label="Account / proof" class="ui-mobile-secondary">
                                {{ $paymentEntry->paidAccount?->account_name ?: '—' }}
                                <span class="master-sub">
                                    @forelse($paymentEntry->attachments as $attachment)
                                        <a class="vendor-file-link" href="{{ $attachment->fileUrl() }}" target="_blank" rel="noopener">{{ $attachment->extension ?: 'file' }}</a>
                                    @empty
                                        No proof attached
                                    @endforelse
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <div class="master-list-empty">
                                    <span class="master-list-empty-icon" aria-hidden="true"><i class="fa-solid fa-file-invoice"></i></span>
                                    <h3 class="master-list-empty-title">No statement rows yet</h3>
                                    <p class="master-list-empty-text">Add entries from the Payments tab and the statement builds itself.</p>
                                    <div class="master-list-empty-actions">
                                        <a class="master-btn master-btn-soft" href="{{ $recordUrl('payments') }}">Go to payments</a>
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
