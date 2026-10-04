@extends('client_portal.layouts.app')

@section('title', 'Invoices')
@section('page-title', 'Invoices')

@section('content')
<div class="cp-page-head">
    <div>
        <p class="cp-eyebrow">Billing workspace</p>
        <h1>Invoices</h1>
        <p>Your MissPack sales invoices, payment status and downloadable records in one place.</p>
    </div>
    <a class="master-btn master-btn-soft" href="{{ route('client-portal.statement.index') }}"><i class="fa-solid fa-file-invoice"></i> Statement of account</a>
</div>

<section class="cp-card cp-invoice-section">
    <div class="cp-section-heading">
        <div><p class="cp-eyebrow">ERP billing</p><h2>Sales invoices</h2><p class="cp-muted">Invoices published from MissPack’s billing system.</p></div>
        <span class="cp-support-count">{{ $salesInvoices->total() }}</span>
    </div>
    <div class="cp-table-wrap ui-mobile-cards">
        <table class="cp-table" data-table-settings data-table-key="portal-invoices">
            <thead><tr><th>Invoice</th><th class="ui-mobile-secondary">Invoice date</th><th>Due date</th><th>Total</th><th class="ui-mobile-secondary">Received</th><th>Balance</th><th>State</th><th></th></tr></thead>
            <tbody>
                @forelse($salesInvoices as $invoice)
                    <tr>
                        <td data-label="Invoice"><a class="cp-record-link" href="{{ route('client-portal.invoices.sales.show', $invoice) }}"><strong>{{ $invoice->invoice_number }}</strong></a><span class="cp-table-sub">{{ $invoice->typeLabel() }}</span></td>
                        <td data-label="Invoice date" class="ui-mobile-secondary">{{ optional($invoice->invoice_date)->format('d M Y') ?: '—' }}</td>
                        <td data-label="Due date">{{ optional($invoice->due_date)->format('d M Y') ?: '—' }}</td>
                        <td data-label="Total">{{ \App\Helpers\CommonHelper::amount($invoice->total_amount, $invoice->currency) }}</td>
                        <td data-label="Received" class="ui-mobile-secondary">{{ \App\Helpers\CommonHelper::amount($invoice->clientPortalReceivedAmount(), $invoice->currency) }}</td>
                        <td data-label="Balance"><strong>{{ \App\Helpers\CommonHelper::amount($invoice->clientPortalBalanceDue(), $invoice->currency) }}</strong></td>
                        <td data-label="State"><span class="cp-status-pill cp-invoice-state-{{ $invoice->clientPortalStateKey() }}">{{ $invoice->clientPortalStateLabel() }}</span></td>
                        <td data-label="Action"><a class="master-btn master-btn-soft master-btn-sm" href="{{ route('client-portal.invoices.sales.show', $invoice) }}">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8"><div class="cp-empty">No sales invoices have been published to your workspace yet.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="cp-pagination">{{ $salesInvoices->links() }}</div>
</section>
@endsection
