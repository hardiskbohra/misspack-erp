@if($invoiceEntriesAvailable)
    <div class="client-detail-tools client-financial-entries">
        <section class="master-card master-card--flat client-detail-card client-detail-card--wide client-entry-filters" aria-labelledby="client-invoice-filters-heading">
            <div class="client-entry-toolbar-head">
                <div><p class="cpa-eyebrow">ERP billing</p><h2 class="client-detail-title" id="client-invoice-filters-heading">Invoice entries</h2></div>
                <a class="master-btn master-btn-primary" href="{{ route('sales-invoices.create', ['client_id' => $client->id]) }}"><i class="fa-solid fa-plus" aria-hidden="true"></i> Create invoice</a>
            </div>
            <form method="GET" action="{{ route('clients.show', $client) }}" class="client-entry-filter-form">
                <input type="hidden" name="tab" value="invoices">
                <div class="master-field client-entry-search-field">
                    <label class="master-label" for="clientInvoiceSearch">Search</label>
                    <input class="master-input" id="clientInvoiceSearch" name="invoice_search" value="{{ $invoiceSearch }}" placeholder="Invoice number, PO or notes">
                </div>
                <div class="master-field">
                    <label class="master-label" for="clientInvoiceType">Type</label>
                    <select class="master-select" id="clientInvoiceType" name="invoice_type">
                        <option value="all">All types</option>
                        @foreach($invoiceTypeOptions as $key => $label)
                            <option value="{{ $key }}" @selected($invoiceType === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="master-field">
                    <label class="master-label" for="clientInvoiceStatus">Status</label>
                    <select class="master-select" id="clientInvoiceStatus" name="invoice_status">
                        <option value="all">All statuses</option>
                        @foreach($invoiceStatusOptions as $key => $label)
                            <option value="{{ $key }}" @selected($invoiceStatus === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="master-field">
                    <label class="master-label" for="clientInvoiceCurrency">Currency</label>
                    <select class="master-select" id="clientInvoiceCurrency" name="invoice_currency">
                        <option value="all">All currencies</option>
                        @foreach($invoiceCurrencyOptions as $code)
                            <option value="{{ $code }}" @selected($invoiceCurrency === $code)>{{ \App\Helpers\CommonHelper::currencyLabel($code) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="master-field">
                    <label class="master-label" for="clientInvoiceFrom">From</label>
                    <input class="master-input" id="clientInvoiceFrom" type="date" name="invoice_date_from" value="{{ $invoiceDateFrom }}">
                </div>
                <div class="master-field">
                    <label class="master-label" for="clientInvoiceTo">To</label>
                    <input class="master-input" id="clientInvoiceTo" type="date" name="invoice_date_to" value="{{ $invoiceDateTo }}">
                </div>
                <div class="client-entry-filter-actions">
                    <a class="master-btn master-btn-soft" href="{{ route('clients.show', ['client' => $client, 'tab' => 'invoices']) }}">Reset</a>
                    <button class="master-btn master-btn-primary" type="submit">Apply filters</button>
                </div>
            </form>
        </section>

        <section class="cpa-card client-entry-table-card" aria-labelledby="client-invoice-entries-heading">
            <div class="cpa-section-head">
                <div><p class="cpa-eyebrow">Sales ledger</p><h2 id="client-invoice-entries-heading">Invoices</h2></div>
                <span class="cpa-badge">{{ number_format($invoiceEntries->total()) }}</span>
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
                            <tr><td colspan="10"><div class="cpa-empty">No invoice entries match these filters.</div></td></tr>
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
