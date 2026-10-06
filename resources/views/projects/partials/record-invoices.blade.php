<section class="master-tab-panel" id="project-panel-invoices" role="tabpanel" aria-labelledby="project-tab-invoices">
    <div class="project-blocks">
        {{-- The invoices the client owes. Raised in the invoices module, tagged
             to this project — the tag is the whole link, so this tab reads the
             same rows that listing does and never keeps a list of its own. --}}
        <section class="master-card master-card--flat project-detail-card">
            <div class="master-section-head">
                <div>
                    <h2 class="master-section-title">Invoices</h2>
                    <p class="master-sub">{{ $project->taxInvoices->count() }}
                        {{ \Illuminate\Support\Str::plural('tax invoice', $project->taxInvoices->count()) }} raised on
                        this project</p>
                </div>
                <div class="master-section-meta">
                    <a class="master-btn master-btn-soft master-btn-sm"
                        href="{{ route('sales-invoices.index', ['invoice_type' => 'tax', 'project_id' => $project->id]) }}">
                        <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> Open in invoices</a>
                </div>
            </div>
            @if ($project->taxInvoices->isEmpty())
                <div class="master-empty-state">
                    <i class="fa-regular fa-file-lines" aria-hidden="true"></i>
                    <p>No tax invoice on this project yet. Raise one in the invoices module and it appears here on its
                        own — nothing has to be added twice.</p>
                    <a class="master-btn master-btn-soft master-btn-sm"
                        href="{{ route('sales-invoices.create', ['project_id' => $project->id]) }}">
                        <i class="fas fa-plus" aria-hidden="true"></i> New invoice</a>
                </div>
            @else
                <div class="master-table-wrap">
                    <table class="master-table">
                        <thead>
                            <tr>
                                <th scope="col">Invoice</th>
                                <th scope="col">Date</th>
                                <th scope="col" class="is-num">Amount</th>
                                <th scope="col" class="is-num">Received</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="project-col-actions">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($project->taxInvoices as $invoice)
                                <tr>
                                    <td data-label="Invoice">
                                        <strong>{{ $invoice->invoice_number }}</strong>
                                        <span class="project-fact-note">{{ $invoice->client_company_name ?: $project->clientName() }}</span>
                                    </td>
                                    <td data-label="Date">{{ optional($invoice->invoice_date)->format('d M Y') ?: '—' }}</td>
                                    <td data-label="Amount" class="is-num">
                                        {{ \App\Helpers\CommonHelper::amount($invoice->total_amount, $invoice->currency) }}
                                    </td>
                                    <td data-label="Received" class="is-num">
                                        <strong class="{{ $invoice->receivedAmount() > 0 ? 'project-money-in' : '' }}">
                                            {{ \App\Helpers\CommonHelper::amount($invoice->receivedAmount(), $invoice->currency) }}
                                        </strong>
                                    </td>
                                    <td data-label="Status">
                                        <span class="master-badge status-{{ $invoice->stateKey() }}">{{ $invoice->stateLabel() }}</span>
                                    </td>
                                    <td data-label="Action" class="project-col-actions">
                                        <a class="master-btn master-btn-light master-btn-sm" href="{{ route('sales-invoices.show', $invoice) }}">
                                            <i class="fa-regular fa-eye" aria-hidden="true"></i> Open</a>
                                        <a class="master-btn master-btn-light master-btn-sm" href="{{ route('sales-invoices.print', $invoice) }}"
                                            target="_blank" rel="noopener">
                                            <i class="fa-solid fa-print" aria-hidden="true"></i> Print</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- What asked for the money before an invoice could. A converted
             proforma is history, not a mistake — it keeps its row and says so. --}}
        <section class="master-card master-card--flat project-detail-card">
            <div class="master-section-head">
                <div>
                    <h2 class="master-section-title">Proforma invoices</h2>
                    <p class="master-sub">{{ $project->proformaInvoices->count() }}
                        {{ \Illuminate\Support\Str::plural('proforma', $project->proformaInvoices->count()) }} raised on
                        this project</p>
                </div>
                <div class="master-section-meta">
                    <a class="master-btn master-btn-soft master-btn-sm"
                        href="{{ route('sales-invoices.index', ['invoice_type' => 'proforma', 'project_id' => $project->id]) }}">
                        <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> Open in invoices</a>
                </div>
            </div>
            @if ($project->proformaInvoices->isEmpty())
                <div class="master-empty-state">
                    <i class="fa-regular fa-file-lines" aria-hidden="true"></i>
                    <p>No proforma on this project. Raise one when the money has to be asked for before the invoice
                        exists.</p>
                    <a class="master-btn master-btn-soft master-btn-sm"
                        href="{{ route('sales-invoices.create', ['type' => 'proforma', 'project_id' => $project->id]) }}">
                        <i class="fas fa-plus" aria-hidden="true"></i> New proforma</a>
                </div>
            @else
                <div class="master-table-wrap">
                    <table class="master-table">
                        <thead>
                            <tr>
                                <th scope="col">Proforma</th>
                                <th scope="col">Date</th>
                                <th scope="col" class="is-num">Amount</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="project-col-actions">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($project->proformaInvoices as $invoice)
                                <tr>
                                    <td data-label="Proforma">
                                        <strong>{{ $invoice->invoice_number }}</strong>
                                        <span class="project-fact-note">
                                            {{ $invoice->isSuperseded() ? 'Carried by '.$invoice->convertedInvoice?->invoice_number : ($invoice->client_company_name ?: $project->clientName()) }}
                                        </span>
                                    </td>
                                    <td data-label="Date">{{ optional($invoice->invoice_date)->format('d M Y') ?: '—' }}</td>
                                    <td data-label="Amount" class="is-num">
                                        {{ \App\Helpers\CommonHelper::amount($invoice->total_amount, $invoice->currency) }}
                                    </td>
                                    <td data-label="Status">
                                        <span class="master-badge status-{{ $invoice->stateKey() }}">{{ $invoice->stateLabel() }}</span>
                                    </td>
                                    <td data-label="Action" class="project-col-actions">
                                        <a class="master-btn master-btn-light master-btn-sm" href="{{ route('sales-invoices.show', $invoice) }}">
                                            <i class="fa-regular fa-eye" aria-hidden="true"></i> Open</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- What we ordered for the job. --}}
        <section class="master-card master-card--flat project-detail-card">
            <div class="master-section-head">
                <div>
                    <h2 class="master-section-title">Purchase orders</h2>
                    <p class="master-sub">{{ $project->purchaseOrders->count() }}
                        {{ \Illuminate\Support\Str::plural('order', $project->purchaseOrders->count()) }} placed for
                        this project</p>
                </div>
                <div class="master-section-meta">
                    <a class="master-btn master-btn-soft master-btn-sm"
                        href="{{ route('purchase-invoices.index', ['invoice_type' => 'order', 'project' => $project->id]) }}">
                        <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> Open in purchases</a>
                </div>
            </div>
            @if ($project->purchaseOrders->isEmpty())
                <div class="master-empty-state">
                    <i class="fa-regular fa-clipboard" aria-hidden="true"></i>
                    <p>No purchase order for this project yet. Place one in the purchase module and it is listed here,
                        tagged to the project it was raised for.</p>
                </div>
            @else
                <div class="master-table-wrap">
                    <table class="master-table">
                        <thead>
                            <tr>
                                <th scope="col">Order</th>
                                <th scope="col">Vendor</th>
                                <th scope="col">Date</th>
                                <th scope="col" class="is-num">Amount</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="project-col-actions">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($project->purchaseOrders as $invoice)
                                <tr>
                                    <td data-label="Order">
                                        <strong>{{ $invoice->invoice_number }}</strong>
                                        <span class="project-fact-note">{{ $invoice->typeLabel() }}</span>
                                    </td>
                                    <td data-label="Vendor">{{ $invoice->vendor_company_name ?: $invoice->vendor?->contact_person_name ?: '—' }}</td>
                                    <td data-label="Date">{{ optional($invoice->invoice_date)->format('d M Y') ?: '—' }}</td>
                                    <td data-label="Amount" class="is-num">
                                        {{ \App\Helpers\CommonHelper::amount($invoice->total_amount, $invoice->currency) }}
                                    </td>
                                    <td data-label="Status">
                                        <span class="master-badge status-{{ $invoice->status }}">{{ $invoice->statusLabel() }}</span>
                                    </td>
                                    <td data-label="Action" class="project-col-actions">
                                        <a class="master-btn master-btn-light master-btn-sm" href="{{ route('purchase-invoices.show', $invoice) }}">
                                            <i class="fa-regular fa-eye" aria-hidden="true"></i> Open</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- What the vendors billed us for it. --}}
        <section class="master-card master-card--flat project-detail-card">
            <div class="master-section-head">
                <div>
                    <h2 class="master-section-title">Bills</h2>
                    <p class="master-sub">{{ $project->bills->count() }}
                        {{ \Illuminate\Support\Str::plural('bill', $project->bills->count()) }} recorded against this
                        project</p>
                </div>
                <div class="master-section-meta">
                    <a class="master-btn master-btn-soft master-btn-sm"
                        href="{{ route('purchase-invoices.index', ['invoice_type' => 'bill', 'project' => $project->id]) }}">
                        <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> Open in purchases</a>
                </div>
            </div>
            @if ($project->bills->isEmpty())
                <div class="master-empty-state">
                    <i class="fa-regular fa-file-invoice" aria-hidden="true"></i>
                    <p>No vendor bill on this project yet. Record one in the purchase module — a bill keeps its own
                        payments in the ledger, and this tab only reads what carries the project's tag.</p>
                </div>
            @else
                <div class="master-table-wrap">
                    <table class="master-table">
                        <thead>
                            <tr>
                                <th scope="col">Bill</th>
                                <th scope="col">Vendor</th>
                                <th scope="col">Date</th>
                                <th scope="col" class="is-num">Amount</th>
                                <th scope="col" class="is-num">Paid</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="project-col-actions">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($project->bills as $invoice)
                                <tr>
                                    <td data-label="Bill">
                                        <strong>{{ $invoice->invoice_number }}</strong>
                                        <span class="project-fact-note">{{ $invoice->typeLabel() }}</span>
                                    </td>
                                    <td data-label="Vendor">{{ $invoice->vendor_company_name ?: $invoice->vendor?->contact_person_name ?: '—' }}</td>
                                    <td data-label="Date">{{ optional($invoice->invoice_date)->format('d M Y') ?: '—' }}</td>
                                    <td data-label="Amount" class="is-num">
                                        {{ \App\Helpers\CommonHelper::amount($invoice->total_amount, $invoice->currency) }}
                                    </td>
                                    <td data-label="Paid" class="is-num">
                                        <strong class="{{ $invoice->paidAmount() > 0 ? 'project-money-out' : '' }}">
                                            {{ \App\Helpers\CommonHelper::amount($invoice->paidAmount(), $invoice->currency) }}
                                        </strong>
                                    </td>
                                    <td data-label="Status">
                                        <span class="master-badge status-{{ $invoice->status }}">{{ $invoice->statusLabel() }}</span>
                                    </td>
                                    <td data-label="Action" class="project-col-actions">
                                        <a class="master-btn master-btn-light master-btn-sm" href="{{ route('purchase-invoices.show', $invoice) }}">
                                            <i class="fa-regular fa-eye" aria-hidden="true"></i> Open</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</section>
