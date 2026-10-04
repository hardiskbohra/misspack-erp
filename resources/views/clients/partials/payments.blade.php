@if($paymentEntriesAvailable)
    <div class="client-detail-tools client-financial-entries">
        <section class="cpa-card client-entry-table-card" aria-labelledby="client-payment-entries-heading">
            <div class="cpa-section-head">
                <div><p class="cpa-eyebrow">Cashflow ledger</p><h2 id="client-payment-entries-heading">Payment entries</h2></div>
                <span class="cpa-badge">{{ number_format($paymentEntries->total()) }} {{ \Illuminate\Support\Str::plural('entry', $paymentEntries->total()) }}</span>
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
                            <tr><td colspan="9"><div class="cpa-empty">No payment entries found for this client.</div></td></tr>
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
