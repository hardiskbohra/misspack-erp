@extends('client_portal.layouts.app')

@section('title', 'Invoices')
@section('page-title', 'Invoices')

@section('content')
<div class="cp-page-head"><div><p class="cp-eyebrow">Billing</p><h1>Invoices</h1><p>View invoices shared by MissPack.</p></div></div>
<div class="cp-card" style="padding:18px;margin-bottom:18px;"><form method="GET" class="cp-form-grid"><div class="cp-field"><label>Status</label><select name="status"><option value="all">All Status</option>@foreach($statusOptions as $key=>$label)<option value="{{ $key }}" {{ $status===$key?'selected':'' }}>{{ $label }}</option>@endforeach</select></div><div style="display:flex;align-items:end;gap:10px;"><button class="cp-btn cp-btn-primary">Filter</button><a class="cp-btn cp-btn-light" href="{{ route('client-portal.invoices.index') }}">Reset</a></div></form></div>
<div class="cp-card"><div class="cp-table-wrap"><table class="cp-table"><thead><tr><th>Invoice</th><th>Date</th><th>Due</th><th>Total</th><th>Outstanding</th><th>Status</th><th>Action</th></tr></thead><tbody>@forelse($invoices as $invoice)<tr><td><strong>{{ $invoice->invoice_number }}</strong><span class="cp-muted" style="display:block;">{{ $invoice->title ?: '-' }}</span></td><td>{{ optional($invoice->invoice_date)->format('d M Y') ?: '-' }}</td><td>{{ optional($invoice->due_date)->format('d M Y') ?: '-' }}</td><td>{{ $invoice->currency }} {{ number_format((float)$invoice->total_amount, 2) }}</td><td>{{ $invoice->currency }} {{ number_format($invoice->outstandingAmount(), 2) }}</td><td><span class="cp-badge status-{{ $invoice->status }}">{{ $invoice->statusLabel() }}</span></td><td><a class="cp-btn cp-btn-soft cp-btn-sm" href="{{ route('client-portal.invoices.show', $invoice) }}">Open</a></td></tr>@empty<tr><td colspan="7"><div class="cp-empty">No invoices found.</div></td></tr>@endforelse</tbody></table></div><div class="cp-pagination">{{ $invoices->links() }}</div></div>
@endsection
