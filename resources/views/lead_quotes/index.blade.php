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
    <div class="cq-card">
        <form method="GET" action="{{ route('lead-quotes.index') }}">
            <div class="cq-toolbar core-filter-toolbar">
                <div class="master-search">
                    <span aria-hidden="true">⌕</span>
                    <input class="master-input" name="search" value="{{ $search }}"
                        placeholder="Search quote, customer, lead..." aria-label="Search customer quotes">
                </div>
                <x-filter-trigger drawer="leadQuoteFiltersDrawer"
                    :count="(filled($search) ? 1 : 0) + ($status !== 'all' ? 1 : 0) + ($leadId !== 'all' ? 1 : 0) + ($currency !== 'all' ? 1 : 0)" />
            </div>
            <x-drawer id="leadQuoteFiltersDrawer" title="Filter customer quotes" eyebrow="Quote filters"
                subtitle="Narrow customer quotes by status, lead, or currency." size="medium">
                <section class="core-drawer-section">
                    <h3 class="core-drawer-section-title">Quote details</h3>
                    <div class="core-drawer-fields">
                        <div class="master-field">
                            <label class="master-label" for="leadQuoteFilterStatus">Status</label>
                            <select class="master-select" id="leadQuoteFilterStatus" name="status">
                                <option value="all">All statuses</option>
                                @foreach($statusOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="leadQuoteFilterLead">Lead</label>
                            <select class="master-select" id="leadQuoteFilterLead" name="lead_id">
                                <option value="all">All leads</option>
                                @foreach($leads as $lead)
                                    <option value="{{ $lead->id }}" @selected((string) $leadId === (string) $lead->id)>
                                        {{ $lead->lead_number }} - {{ $lead->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="leadQuoteFilterCurrency">Currency</label>
                            <select class="master-select" id="leadQuoteFilterCurrency" name="currency">
                                <option value="all">All currencies</option>
                                @foreach($currencyOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($currency === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </section>
                <x-slot:footer>
                    <a class="master-btn master-btn-soft" href="{{ route('lead-quotes.index') }}">Reset</a>
                    <button class="master-btn master-btn-primary" type="submit">Apply filters</button>
                </x-slot:footer>
            </x-drawer>
        </form>
    </div>
    <div class="cq-card"><div class="cq-table-wrap ui-mobile-cards"><table class="cq-table" data-table-settings data-table-key="lead-quotes"><thead><tr><th>Quote</th><th class="ui-mobile-secondary">Lead</th><th>Customer</th><th class="ui-mobile-secondary">Date</th><th>Valid Until</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead><tbody>@forelse($quotes as $quote)<tr><td data-label="Quote"><span class="cq-id">{{ $quote->quote_number }}</span><span class="cq-sub">{{ $quote->title }}</span></td><td class="ui-mobile-secondary">{{ $quote->lead?->lead_number ?: 'Standalone' }}<span class="cq-sub">{{ $quote->lead?->title }}</span></td><td data-label="Customer">{{ $quote->client?->company_name ?? $quote->customer_company_name ?? '-' }}<span class="cq-sub">{{ $quote->customer_contact_name ?: '-' }}</span></td><td data-label="Date" class="ui-mobile-secondary">{{ $quote->quote_date ? $quote->quote_date->format('d M Y') : '-' }}</td><td data-label="Valid until">{{ $quote->valid_until ? $quote->valid_until->format('d M Y') : '-' }}</td><td data-label="Amount">{{ \App\Helpers\CommonHelper::amount($quote->total_amount, $quote->currency) }}<span class="cq-sub">Sub: {{ \App\Helpers\CommonHelper::amount($quote->subtotal, $quote->currency) }}</span></td><td data-label="Status"><span class="cq-badge status-{{ $quote->status }}">{{ $quote->statusLabel() }}</span></td><td data-label="Action"><div class="cq-row-actions"><a class="master-icon-btn green" href="{{ route('lead-quotes.show',$quote) }}">👁</a><a class="master-icon-btn" href="{{ route('lead-quotes.edit',$quote) }}">✎</a><form method="POST" action="{{ route('lead-quotes.destroy',$quote) }}" data-confirm="Delete this quote?">@csrf @method('DELETE')<button class="master-icon-btn danger">🗑</button></form></div></td></tr>@empty<tr><td colspan="8" style="padding:70px;text-align:center;color:#687386;font-weight:700">No customer quotes found.</td></tr>@endforelse</tbody></table></div><div class="cq-pagination">{{ $quotes->links() }}</div></div>
</div>
@endsection
