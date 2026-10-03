@if($paymentEntriesAvailable)
    <div class="client-detail-tools client-financial-entries">
        <section class="master-card master-card--flat client-detail-card client-detail-card--wide client-entry-filters" aria-labelledby="client-payment-filters-heading">
            <div class="client-entry-toolbar-head">
                <div><p class="cpa-eyebrow">Cashflow ledger</p><h2 class="client-detail-title" id="client-payment-filters-heading">Payment entries</h2></div>
                <span class="cpa-badge">{{ number_format($paymentEntries->total()) }} entries</span>
            </div>
            <form method="GET" action="{{ route('clients.show', $client) }}" class="client-entry-filter-form">
                <input type="hidden" name="tab" value="payments">
                <div class="master-field client-entry-search-field">
                    <label class="master-label" for="clientPaymentSearch">Search</label>
                    <input class="master-input" id="clientPaymentSearch" name="payment_search" value="{{ $paymentSearch }}" placeholder="Particulars or reference">
                </div>
                <div class="master-field">
                    <label class="master-label" for="clientPaymentDirection">Entry type</label>
                    <select class="master-select" id="clientPaymentDirection" name="payment_direction">
                        <option value="all">All types</option>
                        @foreach($paymentDirectionOptions as $key => $label)
                            <option value="{{ $key }}" @selected($paymentDirection === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="master-field">
                    <label class="master-label" for="clientPaymentStatus">Status</label>
                    <select class="master-select" id="clientPaymentStatus" name="payment_status">
                        <option value="all">All statuses</option>
                        @foreach($paymentStatusOptions as $key => $label)
                            <option value="{{ $key }}" @selected($paymentStatus === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="master-field">
                    <label class="master-label" for="clientPaymentMode">Payment mode</label>
                    <select class="master-select" id="clientPaymentMode" name="payment_mode">
                        <option value="all">All modes</option>
                        @foreach($paymentModeOptions as $key => $label)
                            <option value="{{ $key }}" @selected($paymentMode === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="master-field">
                    <label class="master-label" for="clientPaymentCurrency">Currency</label>
                    <select class="master-select" id="clientPaymentCurrency" name="payment_currency">
                        <option value="all">All currencies</option>
                        @foreach($paymentCurrencyOptions as $code)
                            <option value="{{ $code }}" @selected($paymentCurrency === $code)>{{ \App\Helpers\CommonHelper::currencyLabel($code) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="master-field">
                    <label class="master-label" for="clientPaymentFrom">From</label>
                    <input class="master-input" id="clientPaymentFrom" type="date" name="payment_date_from" value="{{ $paymentDateFrom }}">
                </div>
                <div class="master-field">
                    <label class="master-label" for="clientPaymentTo">To</label>
                    <input class="master-input" id="clientPaymentTo" type="date" name="payment_date_to" value="{{ $paymentDateTo }}">
                </div>
                <div class="client-entry-filter-actions">
                    <a class="master-btn master-btn-soft" href="{{ route('clients.show', ['client' => $client, 'tab' => 'payments']) }}">Reset</a>
                    <button class="master-btn master-btn-primary" type="submit">Apply filters</button>
                </div>
            </form>
        </section>

        @if($paymentTotalsByCurrency->isNotEmpty())
            <section class="client-payment-summaries" aria-label="Payment totals by currency">
                @foreach($paymentTotalsByCurrency as $summary)
                    @php($summaryCurrency = $summary->currency ?: 'INR')
                    <article class="cpa-card client-payment-summary">
                        <div class="client-payment-summary-head"><span>{{ \App\Helpers\CommonHelper::currencyLabel($summaryCurrency) }}</span><span class="cpa-badge">{{ number_format($summary->entry_count) }} entries</span></div>
                        <div class="client-payment-summary-grid">
                            <div><span>Receipts</span><strong>{{ \App\Helpers\CommonHelper::amount($summary->credits, $summaryCurrency) }}</strong></div>
                            <div><span>Payments / refunds</span><strong>{{ \App\Helpers\CommonHelper::amount($summary->debits, $summaryCurrency) }}</strong></div>
                            <div><span>Net movement</span><strong>{{ \App\Helpers\CommonHelper::amount((float) $summary->credits - (float) $summary->debits, $summaryCurrency) }}</strong></div>
                        </div>
                    </article>
                @endforeach
                <p class="client-payment-currency-note">Totals are grouped by currency and are not converted or combined.</p>
            </section>
        @endif

        <section class="cpa-card client-entry-table-card" aria-labelledby="client-payment-entries-heading">
            <div class="cpa-section-head">
                <div><p class="cpa-eyebrow">Receipts and adjustments</p><h2 id="client-payment-entries-heading">Payments</h2></div>
            </div>
            <div class="master-table-wrap client-entry-table-wrap">
                <table class="master-table client-payment-table">
                    <thead><tr><th>Date</th><th>Particulars</th><th>Reference</th><th>Type</th><th>Account / category</th><th>Mode</th><th>Status</th><th class="text-right">Amount</th><th></th></tr></thead>
                    <tbody>
                        @forelse($paymentEntries as $entry)
                            <tr>
                                <td>{{ $entry->entry_date?->format('d M Y') ?: '—' }}</td>
                                <td><strong>{{ $entry->particular }}</strong>@if($entry->related_party_type === 'client' || $entry->client_id)<span class="client-entry-subline">Client payment</span>@endif</td>
                                <td>{{ $entry->invoice_bill_number ?: ($entry->bank_reference_number ?: '—') }}</td>
                                <td><span class="master-badge {{ $entry->transaction_type === 'credit' ? 'status-approved' : 'status-under-review' }}">{{ $paymentDirectionOptions[$entry->transaction_type] ?? ucfirst($entry->transaction_type) }}</span></td>
                                <td>{{ $entry->account?->account_name ?: '—' }}@if($entry->category)<span class="client-entry-subline">{{ $entry->category->name }}</span>@endif</td>
                                <td>{{ $paymentModeOptions[$entry->payment_mode] ?? ucfirst(str_replace('_', ' ', $entry->payment_mode ?? '—')) }}</td>
                                <td><span class="master-badge status-{{ str_replace('_', '-', $entry->accounting_status) }}">{{ $entry->statusLabel() }}</span></td>
                                <td class="text-right"><strong>{{ \App\Helpers\CommonHelper::amount($entry->amountMoved(), $entry->currency) }}</strong></td>
                                <td><a class="master-btn master-btn-soft master-btn-sm" href="{{ route('cashflows.show', $entry) }}">Manage</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="9"><div class="cpa-empty">No payment entries match these filters.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($paymentEntries->hasPages())
                <div class="client-entry-pagination">{{ $paymentEntries->links() }}</div>
            @endif
        </section>
    </div>
@else
    <div class="master-card master-card--flat client-detail-card client-detail-card--wide">
        <div class="master-empty-state"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><p>Cashflow entries are not available in this installation.</p></div>
    </div>
@endif
