@extends('layouts.app')

@section('page-title', 'Customer Quote Management')

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/lead-quotes-index.css') }}">
@endpush
<div class="cq-page">
    <div class="cq-card cq-header">
        <h1>Customer Quote Management</h1>
        <div class="cq-actions">
            <a href="{{ route('leads.index') }}" class="master-btn master-btn-light">Leads</a>
            <a href="{{ route('lead-quotes.create') }}" class="master-btn master-btn-primary">+ Create Quote</a>
            @if($quote->status === 'accepted')
                <a href="{{ route('projects.create', ['customer_quote_id' => $quote->id]) }}" class="master-btn master-btn-primary">
                    <i class="fa-solid fa-briefcase"></i> Create Project
                </a>
            @endif
        </div>
    </div>
    @if(session('success'))<div class="cq-alert cq-alert-success">{{ session('success') }}</div>@endif
    <div class="cq-card"><form method="GET" action="{{ route('lead-quotes.index') }}"><div class="cq-toolbar"><div class="master-search-form"><span>⌕</span><input class="master-input" name="search" value="{{ $search }}" placeholder="Search quote, customer, lead..."></div></div><div class="cq-filter-row"><select class="master-select" name="status"><option value="all">All Status</option>@foreach($statusOptions as $key=>$label)<option value="{{ $key }}" {{ $status===$key ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select><select class="master-select" name="lead_id"><option value="all">All Leads</option>@foreach($leads as $lead)<option value="{{ $lead->id }}" {{ (string)$leadId===(string)$lead->id ? 'selected' : '' }}>{{ $lead->lead_number }} - {{ $lead->title }}</option>@endforeach</select><select class="master-select" name="currency"><option value="all">All Currency</option>@foreach($currencyOptions as $key=>$label)<option value="{{ $key }}" {{ $currency===$key ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select><button class="master-btn master-btn-primary" type="submit">Filter</button><a class="master-btn master-btn-light" href="{{ route('lead-quotes.index') }}">Reset</a></div></form></div>
    <div class="cq-card"><div class="cq-table-wrap"><table class="cq-table"><thead><tr><th>Quote</th><th>Lead</th><th>Customer</th><th>Date</th><th>Valid Until</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead><tbody>@forelse($quotes as $quote)<tr><td><span class="cq-id">{{ $quote->quote_number }}</span><span class="cq-sub">{{ $quote->title }}</span></td><td>{{ $quote->lead?->lead_number ?: 'Standalone' }}<span class="cq-sub">{{ $quote->lead?->title }}</span></td><td>{{ $quote->client?->company_name ?? $quote->customer_company_name ?? '-' }}<span class="cq-sub">{{ $quote->customer_contact_name ?: '-' }}</span></td><td>{{ $quote->quote_date ? $quote->quote_date->format('d M Y') : '-' }}</td><td>{{ $quote->valid_until ? $quote->valid_until->format('d M Y') : '-' }}</td><td>{{ \App\Helpers\CommonHelper::amount($quote->total_amount, $quote->currency) }}<span class="cq-sub">Sub: {{ \App\Helpers\CommonHelper::amount($quote->subtotal, $quote->currency) }}</span></td><td><span class="cq-badge status-{{ $quote->status }}">{{ $quote->statusLabel() }}</span></td><td><div class="cq-row-actions"><a class="master-icon-btn green" href="{{ route('lead-quotes.show',$quote) }}">👁</a><a class="master-icon-btn" href="{{ route('lead-quotes.edit',$quote) }}">✎</a><form method="POST" action="{{ route('lead-quotes.destroy',$quote) }}" data-confirm="Delete this quote?">@csrf @method('DELETE')<button class="master-icon-btn danger">🗑</button></form></div></td></tr>@empty<tr><td colspan="8" style="padding:70px;text-align:center;color:#687386;font-weight:900">No customer quotes found.</td></tr>@endforelse</tbody></table></div><div class="cq-pagination">{{ $quotes->links() }}</div></div>
</div>
@endsection
