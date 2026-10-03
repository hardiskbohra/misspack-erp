@extends('client_portal.layouts.app')

@section('title', 'Invoices')
@section('page-title', 'Invoices')

@section('content')
<div class="cp-page-head">
    <div>
        <p class="cp-eyebrow">Billing workspace</p>
        <h1>Invoices</h1>
        <p>Your MissPack invoices, payment status and downloadable records in one place.</p>
    </div>
    <a class="master-btn master-btn-soft" href="{{ route('client-portal.statement.index') }}"><i class="fa-solid fa-file-invoice"></i> Statement of account</a>
</div>

<section class="cp-card cp-invoice-section">
    <div class="cp-section-heading">
        <div><p class="cp-eyebrow">ERP billing</p><h2>Sales invoices</h2><p class="cp-muted">Invoices published from MissPack’s billing system.</p></div>
        <span class="cp-support-count">{{ $salesInvoices->total() }}</span>
    </div>
    <div class="cp-table-wrap">
        <table class="cp-table">
            <thead><tr><th>Invoice</th><th>Invoice date</th><th>Due date</th><th>Total</th><th>Received</th><th>Balance</th><th>State</th><th></th></tr></thead>
            <tbody>
                @forelse($salesInvoices as $invoice)
                    <tr>
                        <td><a class="cp-record-link" href="{{ route('client-portal.invoices.sales.show', $invoice) }}"><strong>{{ $invoice->invoice_number }}</strong></a><span class="cp-table-sub">{{ $invoice->typeLabel() }}</span></td>
                        <td>{{ optional($invoice->invoice_date)->format('d M Y') ?: '—' }}</td>
                        <td>{{ optional($invoice->due_date)->format('d M Y') ?: '—' }}</td>
                        <td>{{ \App\Helpers\CommonHelper::amount($invoice->total_amount, $invoice->currency) }}</td>
                        <td>{{ \App\Helpers\CommonHelper::amount($invoice->clientPortalReceivedAmount(), $invoice->currency) }}</td>
                        <td><strong>{{ \App\Helpers\CommonHelper::amount($invoice->clientPortalBalanceDue(), $invoice->currency) }}</strong></td>
                        <td><span class="cp-status-pill cp-invoice-state-{{ $invoice->clientPortalStateKey() }}">{{ $invoice->clientPortalStateLabel() }}</span></td>
                        <td><a class="master-btn master-btn-soft master-btn-sm" href="{{ route('client-portal.invoices.sales.show', $invoice) }}">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8"><div class="cp-empty">No sales invoices have been published to your workspace yet.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="cp-pagination">{{ $salesInvoices->links() }}</div>
</section>

@if($legacyInvoices->total())
    <section class="cp-card cp-invoice-section cp-legacy-invoices">
        <div class="cp-section-heading">
            <div><p class="cp-eyebrow">Previous records</p><h2>Legacy portal invoices</h2><p class="cp-muted">Older invoices are preserved here for continuity. New billing appears above.</p></div>
            <span class="cp-support-count">{{ $legacyInvoices->total() }}</span>
        </div>
        <div class="cp-table-wrap">
            <table class="cp-table">
                <thead><tr><th>Invoice</th><th>Date</th><th>Due date</th><th>Total</th><th>Outstanding</th><th>State</th><th></th></tr></thead>
                <tbody>
                    @foreach($legacyInvoices as $invoice)
                        <tr>
                            <td><a class="cp-record-link" href="{{ route('client-portal.invoices.show', $invoice) }}"><strong>{{ $invoice->invoice_number }}</strong></a><span class="cp-table-sub">{{ $invoice->title ?: 'Portal invoice' }}</span></td>
                            <td>{{ optional($invoice->invoice_date)->format('d M Y') ?: '—' }}</td>
                            <td>{{ optional($invoice->due_date)->format('d M Y') ?: '—' }}</td>
                            <td>{{ \App\Helpers\CommonHelper::amount($invoice->total_amount, $invoice->currency) }}</td>
                            <td><strong>{{ \App\Helpers\CommonHelper::amount($invoice->outstandingAmount(), $invoice->currency) }}</strong></td>
                            <td><span class="cp-status-pill cp-invoice-state-{{ $invoice->status }}">{{ $invoice->statusLabel() }}</span></td>
                            <td><a class="master-btn master-btn-soft master-btn-sm" href="{{ route('client-portal.invoices.show', $invoice) }}">View</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="cp-pagination">{{ $legacyInvoices->links() }}</div>
    </section>
@endif
@endsection
