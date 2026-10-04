@extends('client_portal.layouts.app')

@section('title', 'Quotations')
@section('page-title', 'Quotations')

@section('content')
<div class="cp-page-head"><div><p class="cp-eyebrow">Sales</p><h1>Quotations</h1><p>View quotations shared by MissPack.</p></div></div>
<div class="cp-card" style="padding:18px;margin-bottom:18px;">
    <form method="GET" action="{{ route('client-portal.quotes.index') }}">
        <div class="core-filter-toolbar">
            <div class="master-field">
                <label class="master-label" for="portalQuoteSearch">Search</label>
                <input class="master-input" id="portalQuoteSearch" name="search" value="{{ $search }}" placeholder="Search quote number or title...">
            </div>
            <x-filter-trigger drawer="portalQuoteFiltersDrawer"
                :count="(filled($search) ? 1 : 0) + ($status !== 'all' ? 1 : 0)" />
        </div>
        <x-drawer id="portalQuoteFiltersDrawer" title="Filter quotations" eyebrow="Quote filters"
            subtitle="Narrow shared quotations by status." size="medium">
            <section class="core-drawer-section">
                <h3 class="core-drawer-section-title">Quotation status</h3>
                <div class="core-drawer-fields">
                    <div class="master-field">
                        <label class="master-label" for="portalQuoteFilterStatus">Status</label>
                        <select class="master-select" id="portalQuoteFilterStatus" name="status">
                            <option value="all">All statuses</option>
                            @foreach($statusOptions as $key => $label)
                                <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </section>
            <x-slot:footer>
                <a class="master-btn master-btn-soft" href="{{ route('client-portal.quotes.index') }}">Reset</a>
                <button class="master-btn master-btn-primary" type="submit">Apply filters</button>
            </x-slot:footer>
        </x-drawer>
    </form>
</div>
<div class="cp-card"><div class="cp-table-wrap ui-mobile-cards"><table class="cp-table" data-table-settings data-table-key="portal-quotes"><thead><tr><th>Quote</th><th>Date</th><th class="ui-mobile-secondary">Items</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead><tbody>@forelse($quotes as $quote)<tr><td data-label="Quote"><strong>{{ $quote->quote_number }}</strong><span class="cp-muted" style="display:block;">{{ $quote->title }}</span></td><td data-label="Date">{{ optional($quote->quote_date)->format('d M Y') ?: '-' }}<span class="cp-muted" style="display:block;">Expiry: {{ optional($quote->expiry_date)->format('d M Y') ?: '-' }}</span></td><td data-label="Items" class="ui-mobile-secondary">{{ $quote->items->count() }}</td><td data-label="Amount">{{ \App\Helpers\CommonHelper::amount($quote->total_amount, $quote->currency) }}</td><td data-label="Status"><span class="cp-badge status-{{ $quote->status }}">{{ $quote->statusLabel() }}</span></td><td data-label="Action"><a class="master-btn master-btn-soft master-btn-sm" href="{{ route('client-portal.quotes.show', $quote->id) }}">Open</a></td></tr>@empty<tr><td colspan="6"><div class="cp-empty">No public quotations found.</div></td></tr>@endforelse</tbody></table></div>@if(method_exists($quotes,'links'))<div class="cp-pagination">{{ $quotes->links() }}</div>@endif</div>
@endsection
