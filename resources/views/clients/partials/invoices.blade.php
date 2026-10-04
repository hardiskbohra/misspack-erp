@if($invoiceEntriesAvailable)
    <div class="client-detail-tools client-financial-entries">
        <section class="cpa-card client-entry-table-card" aria-labelledby="client-invoice-entries-heading">
            <div class="cpa-section-head">
                <div><p class="cpa-eyebrow">ERP billing</p><h2 id="client-invoice-entries-heading">Invoice entries</h2></div>
                <div class="client-entry-head-actions">
                    <span class="cpa-badge">{{ number_format($invoiceEntries->total()) }} {{ \Illuminate\Support\Str::plural('invoice', $invoiceEntries->total()) }}</span>
                    <a class="master-btn master-btn-primary" href="{{ route('sales-invoices.create', ['client_id' => $client->id]) }}"><i class="fa-solid fa-plus" aria-hidden="true"></i> Create invoice</a>
                </div>
            </div>
            <div class="master-table-wrap client-entry-table-wrap">
                <table class="master-table client-invoice-table">
                    <thead><tr><th>Invoice</th><th>Type</th><th>Invoice date</th><th>Due date</th><th class="text-right">Total</th><th class="text-right">Received</th><th class="text-right">Balance</th><th>State</th><th>Portal</th><th></th></tr></thead>
                    <tbody>
                        @forelse($invoiceEntries as $invoice)
                            @php($invoiceState = $invoice->stateKey())
                            <tr>
                                <td><strong>{{ $invoice->invoice_number }}</strong>@if($invoice->po_number)<span class="client-entry-subline">PO {{ $invoice->po_number }}</span>@endif</td>
                                <td>{{ $invoice->typeLabel() }}</td>
                                <td>{{ $invoice->invoice_date?->format('d M Y') ?: '—' }}</td>
                                <td>{{ $invoice->due_date?->format('d M Y') ?: '—' }}</td>
                                <td class="text-right">{{ \App\Helpers\CommonHelper::amount($invoice->total_amount, $invoice->currency) }}</td>
                                <td class="text-right">{{ \App\Helpers\CommonHelper::amount($invoice->receivedAmount(), $invoice->currency) }}</td>
                                <td class="text-right"><strong>{{ \App\Helpers\CommonHelper::amount($invoice->balanceDue(), $invoice->currency) }}</strong></td>
                                <td><span class="master-badge status-{{ str_replace('_', '-', $invoiceState) }}">{{ $invoice->stateLabel() }}</span></td>
                                <td>{{ $invoice->show_client_portal ? 'Visible' : 'Hidden' }}</td>
                                <td><a class="master-btn master-btn-soft master-btn-sm" href="{{ route('sales-invoices.show', $invoice) }}">Manage</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="10"><div class="cpa-empty">No invoice entries found for this client.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($invoiceEntries->hasPages())
                <div class="client-entry-pagination">{{ $invoiceEntries->links() }}</div>
            @endif
        </section>
    </div>
@else
    <div class="master-card master-card--flat client-detail-card client-detail-card--wide">
        <div class="master-empty-state"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><p>Sales invoice entries are not available in this installation.</p></div>
    </div>
@endif
