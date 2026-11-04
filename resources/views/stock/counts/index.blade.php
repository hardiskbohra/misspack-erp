@extends('layouts.app')
@section('title', 'Physical stock counts')
@section('page-title', 'Physical stock counts')
@push('styles')<link rel="stylesheet" href="{{ $assetVer('assets/css/stock.css') }}">@endpush
@section('content')
<div class="stock master-list">
    <header class="master-card master-header stock-header"><div><p class="stock-eyebrow">Stock reconciliation · single location</p><h1>Physical counts</h1><p class="master-sub">Review and reconcile actual quantities against the manual stock ledger.</p></div>@if(auth()->user()->hasPermission('operations.manage'))<a class="master-btn master-btn-primary" href="{{ route('stock.counts.create') }}"><i class="fa-solid fa-plus" aria-hidden="true"></i> Start physical count</a>@endif</header>
    @include('stock.partials.navigation')
    <div class="stock-alert"><strong>Independent review is required.</strong> The person who records a count cannot approve it. Stock changes after the snapshot require a recount.</div>
    @include('stock.partials.errors')
    <section class="master-card stock-section"><div class="stock-section-heading"><div><h2 class="master-section-title">Count history</h2><p class="master-sub">Drafts, submissions and decisions remain in the audit trail.</p></div></div>
        <nav class="master-list-chips" aria-label="Filter counts by status">@foreach(['all' => 'All', 'draft' => 'Draft', 'submitted' => 'Awaiting review', 'posted' => 'Posted', 'cancelled' => 'Cancelled'] as $key => $label)<a class="master-list-chip {{ $status === $key ? 'is-active' : '' }}" href="{{ route('stock.counts.index', $key === 'all' ? [] : ['status' => $key]) }}" @if($status === $key) aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>
        <div class="master-table-wrap"><table class="master-table ui-mobile-cards stock-table"><thead><tr><th scope="col">Count</th><th scope="col">Counter</th><th scope="col">Items</th><th scope="col">Status</th><th scope="col">Created</th><th scope="col"><span class="sr-only">Action</span></th></tr></thead><tbody>
            @forelse($counts as $count)<tr><td data-label="Count"><a href="{{ route('stock.counts.show', $count) }}"><strong>{{ $count->number() }}</strong></a><span class="master-sub">{{ $count->title }}</span></td><td data-label="Counter">{{ $count->counter_name }}</td><td data-label="Items">{{ $count->lines_count }}</td><td data-label="Status"><span class="stock-status stock-status--{{ $count->status }}">{{ $count->status === 'submitted' ? 'Awaiting review' : $count->label() }}</span></td><td data-label="Created">{{ $count->created_at->format('d M Y, H:i') }} {{ config('app.timezone') }}</td><td data-label="Action"><a class="master-btn master-btn-soft" href="{{ route('stock.counts.show', $count) }}">View count</a></td></tr>
            @empty<tr><td colspan="6"><div class="master-empty-state"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i><p>{{ $status === 'all' ? 'No physical counts yet.' : 'No counts in this status.' }}</p>@if($status === 'all' && auth()->user()->hasPermission('operations.manage'))<a class="master-btn master-btn-primary" href="{{ route('stock.counts.create') }}">Start a count</a>@endif</div></td></tr>@endforelse
        </tbody></table></div><x-pagination :items="$counts" />
    </section>
</div>
@endsection
