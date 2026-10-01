@extends('layouts.app')

@section('page-title', 'PI / Tax Invoices')

@section('content')
<div class="si-page" style="padding:0;">
    <div class="si-hero">
        <div>
            <p class="si-eyebrow">Sales Billing</p>
            <h1>Invoice Management</h1>
            <p>Create invoices with multiple linked products, clients, projects and client portal visibility.</p>
        </div>
        <div class="si-actions">
            <a href="{{ route('sales-invoices.create', ['type' => 'proforma']) }}" class="si-btn si-btn-primary">New PI</a>
            <a href="{{ route('sales-invoices.create', ['type' => 'tax']) }}" class="si-btn si-btn-light">New Tax Invoice</a>
        </div>
    </div>

    <div class="si-stats">
        <div class="si-stat"><span>Proforma</span><strong>{{ $stats['proforma'] }}</strong></div>
        <div class="si-stat blue"><span>Tax</span><strong>{{ $stats['tax'] }}</strong></div>
        <div class="si-stat purple"><span>Total</span><strong>{{ \App\Helpers\CommonHelper::indianCurrency($stats['sent']) }}</strong></div>
        <div class="si-stat green"><span>Paid</span><strong>{{ \App\Helpers\CommonHelper::indianCurrency($stats['paid']) }}</strong></div>
        <div class="si-stat orange"><span>Outstanding</span><strong>{{ \App\Helpers\CommonHelper::indianCurrency($stats['outstanding']) }}</strong></div>
    </div>

    <div class="si-card si-filter-card">
        <form method="GET" action="{{ route('sales-invoices.index') }}" class="si-filter-form">
            <div class="si-field search"><label>Search</label><input name="search" value="{{ $search }}" placeholder="Search invoice, client, GSTIN, PO..."></div>
            <div class="si-field"><label>Type</label><select name="type"><option value="all">All Types</option>@foreach($typeOptions as $key => $label)<option value="{{ $key }}" {{ $type === $key ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select></div>
            <div class="si-field"><label>Status</label><select name="status"><option value="all">All Status</option>@foreach($statusOptions as $key => $label)<option value="{{ $key }}" {{ $status === $key ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select></div>
            <div class="si-field"><label>Client</label><select name="client_id"><option value="all">All Clients</option>@foreach($clients as $client)<option value="{{ $client->id }}" {{ (string)$clientId === (string)$client->id ? 'selected' : '' }}>{{ $client->company_name }}</option>@endforeach</select></div>
            <div class="si-field"><label>Project</label><select name="project_id"><option value="all">All Projects</option>@foreach($projects as $project)<option value="{{ $project->id }}" {{ (string)$projectId === (string)$project->id ? 'selected' : '' }}>{{ $project->project_number }} - {{ $project->name }}</option>@endforeach</select></div>
            <div class="si-filter-actions"><button class="si-btn si-btn-primary" type="submit">Filter</button><a class="si-btn si-btn-soft" href="{{ route('sales-invoices.index') }}">Reset</a></div>
        </form>
    </div>

    <div class="si-card">
        <div class="master-table-wrap">
            <table class="master-table">
                <thead><tr><th>Invoice</th><th>Client</th><th>Project</th><th>Items</th><th>Total</th><th>Balance</th><th>Portal</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse($invoices as $invoice)
                        @php
                            $paidAmount = $invoice->payments->sum('credit_amount') - $invoice->payments->sum('debit_amount');
                            $balanceAmount = (float) $invoice->total_amount - $paidAmount;
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('sales-invoices.show', $invoice) }}" title="Open" style="text-decoration:none;">
                                    <strong class="master-id">{{ $invoice->invoice_number }}</strong><br><span style="color:grey;font-size:11px;">{{ $invoice->typeLabel() }}</span><br>{{ optional($invoice->invoice_date)->format('d M Y') ?: '-' }}
                                </a>
                            </td>
                            <td><strong>{{ Str::limit($invoice->client_company_name, 22, '...') ?: '-' }}</strong><br><span>{{ $invoice->client_gstin ?: 'GSTIN -' }}</span></td>
                            <td>{{ $invoice->project ? $invoice->project->project_number : '-' }}<br><span>{{ Str::limit($invoice->project?->name ?? '', 22, '...') }}</span></td>
                            <td>{{ $invoice->items->count() }}</td>
                            <td>{{ \App\Helpers\CommonHelper::indianCurrency($invoice->total_amount) }}</td>
                            <td>{{ \App\Helpers\CommonHelper::indianCurrency($balanceAmount) }}</td>
                            <td><span class="si-badge {{ $invoice->show_client_portal ? 'public' : 'private' }}">{{ $invoice->show_client_portal ? 'Visible' : 'Hidden' }}</span></td>
                            <td><span class="si-badge status-{{ $invoice->status }}">{{ $invoice->statusLabel() }}</span></td>
                            <td><div class="si-row-actions"><a class="si-icon" href="{{ route('sales-invoices.show', $invoice) }}" title="Open">👁</a><a class="si-icon" href="{{ route('sales-invoices.print', $invoice) }}" target="_blank" title="Print">🖨</a><a class="si-icon" href="{{ route('sales-invoices.edit', $invoice) }}" title="Edit">✎</a><form method="POST" action="{{ route('sales-invoices.destroy', $invoice) }}" onsubmit="return confirm('Delete invoice?')">@csrf @method('DELETE')<button class="si-icon danger" type="submit">🗑</button></form></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="10"><div class="si-empty">No invoices found.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="si-pagination">{{ $invoices->links() }}</div>
    </div>
</div>

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/sales-invoices.css') }}">
@endpush
@endsection
