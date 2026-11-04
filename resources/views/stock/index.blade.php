@extends('layouts.app')
@section('title', 'Stock reconciliation')
@section('page-title', 'Stock reconciliation')
@push('styles')<link rel="stylesheet" href="{{ $assetVer('assets/css/stock.css') }}">@endpush
@section('content')
<div class="stock master-list">
    <header class="master-card master-header stock-header">
        <div><p class="stock-eyebrow">Operations · single location</p><h1>Stock reconciliation</h1><p class="master-sub">Track company-owned stock by product, variant and batch. All movements are entered manually.</p></div>
        @if(auth()->user()->hasPermission('operations.manage'))
            <div class="stock-actions"><a class="master-btn master-btn-soft" href="{{ route('stock.counts.create') }}">Start physical count</a><a class="master-btn master-btn-primary" href="{{ route('stock.items.create') }}"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add stock item</a></div>
        @endif
    </header>
    @include('stock.partials.navigation')
    <div class="stock-stats">
        <div class="master-card stock-stat"><span>Tracked items / batches</span><strong>{{ number_format($stats['items']) }}</strong></div>
        <div class="master-card stock-stat"><span>With stock on hand</span><strong>{{ number_format($stats['in_stock']) }}</strong></div>
        <div class="master-card stock-stat"><span>Expired with stock</span><strong>{{ number_format($stats['expired']) }}</strong></div>
        <div class="master-card stock-stat"><span>Counts awaiting review</span><strong>{{ number_format($stats['pending']) }}</strong></div>
    </div>
    <p class="stock-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Purchases, shipments and the product “ready stock” flag do not automatically change these balances. Quantities with different units are never combined. Expiry dates follow {{ config('app.timezone') }}.</p>
    @include('stock.partials.errors')
    <section class="master-card master-card--flat stock-section">
        <div class="stock-section-heading"><div><h2 class="master-section-title">Stock register</h2><p class="master-sub">One row per product, variant and batch.</p></div></div>
        <form method="GET" action="{{ route('stock.index') }}" class="stock-filter">
            <div class="master-field"><label class="master-label" for="stockSearch">Search</label><input class="master-input" id="stockSearch" name="search" value="{{ $search }}" placeholder="Product, SKU, variant or batch"></div>
            <div class="master-field"><label class="master-label" for="stockFilter">Stock status</label><select class="master-select" id="stockFilter" name="filter">
                @foreach(['all' => 'All items', 'in_stock' => 'In stock', 'zero' => 'Zero balance', 'expired' => 'Expired with stock', 'expiring' => 'Expires within 30 days'] as $key => $label)<option value="{{ $key }}" @selected($filter === $key)>{{ $label }}</option>@endforeach
            </select></div>
            <button class="master-btn master-btn-primary" type="submit">Apply</button>
            @if($search !== '' || $filter !== 'all')<a class="master-btn master-btn-soft" href="{{ route('stock.index') }}">Clear</a>@endif
        </form>
        <div class="master-table-wrap"><table class="master-table ui-mobile-cards stock-table">
            <thead><tr><th scope="col">Product</th><th scope="col">Variant</th><th scope="col">Batch / expiry</th><th scope="col">On hand</th><th scope="col"><span class="sr-only">Action</span></th></tr></thead>
            <tbody>@forelse($items as $item)
                <tr><td data-label="Product"><a href="{{ route('stock.items.show', $item) }}"><strong>{{ $item->product->name }}</strong></a><span class="master-sub">{{ $item->number() }} · {{ $item->product->product_number }}@if($item->product->sku) · {{ $item->product->sku }}@endif</span></td>
                    <td data-label="Variant">{{ $item->variant ?: 'Not specified' }}</td>
                    <td data-label="Batch / expiry">{{ $item->batch_code ?: 'No batch specified' }}<span class="master-sub">@if($item->expires_on) Expires {{ $item->expires_on->format('d M Y') }} @if($item->expiryState()) · <strong class="stock-expiry">{{ $item->expiryState() === 'expired' ? 'Expired' : 'Expiring soon' }}</strong>@endif @else No expiry recorded @endif</span></td>
                    <td data-label="On hand" class="stock-number"><strong>{{ $item->quantity_on_hand }}</strong> {{ $item->unit }}</td>
                    <td data-label="Action"><a class="master-btn master-btn-soft" href="{{ route('stock.items.show', $item) }}">View ledger</a></td></tr>
            @empty<tr><td colspan="5"><div class="master-empty-state"><i class="fa-solid fa-box-open" aria-hidden="true"></i><p>{{ $search !== '' || $filter !== 'all' ? 'No stock items match these filters.' : 'No stock items have been recorded yet.' }}</p>@if(auth()->user()->hasPermission('operations.manage') && $search === '' && $filter === 'all')<a class="master-btn master-btn-primary" href="{{ route('stock.items.create') }}">Add the first item</a>@endif</div></td></tr>@endforelse</tbody>
        </table></div>
        <x-pagination :items="$items" />
    </section>
</div>
@endsection
