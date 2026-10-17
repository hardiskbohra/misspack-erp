@extends('layouts.app')
@section('title', 'Packaging Lists')
@section('page-title', 'Packaging Lists')
@section('page-actions')
<a class="master-btn master-btn-primary" href="{{ route('packaging-lists.create') }}"><i class="fa-solid fa-plus"></i> New Packaging List</a>
@endsection
@push('styles')<link rel="stylesheet" href="{{ $assetVer('assets/css/packaging-lists.css') }}">@endpush
@section('content')
@php
    $filtersActive = trim((string) $search) !== '' || $status !== 'all' || $mode !== 'all';
    $chipUrl = fn (string $key) => route('packaging-lists.index', collect(request()->except([$key, 'page']))->reject(fn ($value) => $value === null || $value === '' || $value === 'all')->all());
@endphp
<div class="packaging-list-page master-list">
    <div class="master-stats desktop-only">
        <div class="master-stat master-stat--flat blue"><span class="icon"><i class="fa-solid fa-clipboard-list"></i></span><div><p class="master-stat-title">All Lists</p><p class="master-stat-value">{{ number_format($stats['total']) }}</p></div></div>
        <div class="master-stat master-stat--flat orange"><span class="icon"><i class="fa-regular fa-pen-to-square"></i></span><div><p class="master-stat-title">Draft</p><p class="master-stat-value">{{ number_format($stats['draft']) }}</p></div></div>
        <div class="master-stat master-stat--flat green"><span class="icon"><i class="fa-solid fa-circle-check"></i></span><div><p class="master-stat-title">Ready / Dispatched</p><p class="master-stat-value">{{ number_format($stats['confirmed']) }}</p></div></div>
        <div class="master-stat master-stat--flat purple"><span class="icon"><i class="fa-solid fa-boxes-stacked"></i></span><div><p class="master-stat-title">Total Cartons</p><p class="master-stat-value">{{ number_format($stats['cartons']) }}</p></div></div>
    </div>

    <section class="master-card master-card--flat">
        <div class="master-list-bar">
            <div class="master-list-chips">
                <a class="master-list-chip {{ $status === 'all' ? 'is-active' : '' }}" href="{{ route('packaging-lists.index', request()->except(['status','page'])) }}">All</a>
                @foreach($statusOptions as $key => $label)
                    <a class="master-list-chip {{ $status === $key ? 'is-active' : '' }}" href="{{ route('packaging-lists.index', array_merge(request()->except('page'), ['status'=>$key])) }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>

        <form method="GET" action="{{ route('packaging-lists.index') }}">
            <div class="master-filter-row core-filter-toolbar">
                <div class="master-search"><span aria-hidden="true">⌕</span><input class="master-input" name="search" value="{{ $search }}" placeholder="Search PL number, invoice or party..." aria-label="Search packaging lists"></div>
                <x-filter-trigger drawer="packagingListFilters" :count="($status !== 'all' ? 1 : 0) + ($mode !== 'all' ? 1 : 0)" />
            </div>
            <x-drawer id="packagingListFilters" title="Filter packaging lists" eyebrow="Packaging list filters" subtitle="Narrow the list by workflow status or transport mode." size="medium">
                <section class="core-drawer-section"><h3 class="core-drawer-section-title">Document details</h3><div class="core-drawer-fields">
                    <div class="master-field"><label class="master-label" for="plFilterStatus">Status</label><select class="master-select" id="plFilterStatus" name="status"><option value="all">All statuses</option>@foreach($statusOptions as $key=>$label)<option value="{{ $key }}" @selected($status===$key)>{{ $label }}</option>@endforeach</select></div>
                    <div class="master-field"><label class="master-label" for="plFilterMode">Transport mode</label><select class="master-select" id="plFilterMode" name="mode"><option value="all">All modes</option>@foreach($modeOptions as $key=>$label)<option value="{{ $key }}" @selected($mode===$key)>{{ $label }}</option>@endforeach</select></div>
                </div></section>
                <x-slot:footer>@if($filtersActive)<a class="master-btn master-btn-soft" href="{{ route('packaging-lists.index') }}">Reset</a>@endif<button class="master-btn master-btn-primary" type="submit">Apply filters</button></x-slot:footer>
            </x-drawer>
        </form>

        @if($filtersActive)
            <div class="master-list-applied"><span class="master-list-applied-title">Filtered by</span>
                @if(trim((string)$search)!=='')<span class="master-list-applied-chip"><span class="master-list-applied-key">Search</span><span class="master-list-applied-value">{{ $search }}</span><a class="master-list-applied-x" href="{{ $chipUrl('search') }}" aria-label="Remove search filter">&times;</a></span>@endif
                @if($status!=='all')<span class="master-list-applied-chip"><span class="master-list-applied-key">Status</span><span class="master-list-applied-value">{{ $statusOptions[$status] ?? $status }}</span><a class="master-list-applied-x" href="{{ $chipUrl('status') }}" aria-label="Remove status filter">&times;</a></span>@endif
                @if($mode!=='all')<span class="master-list-applied-chip"><span class="master-list-applied-key">Mode</span><span class="master-list-applied-value">{{ $modeOptions[$mode] ?? $mode }}</span><a class="master-list-applied-x" href="{{ $chipUrl('mode') }}" aria-label="Remove mode filter">&times;</a></span>@endif
                <a class="master-list-applied-clear" href="{{ route('packaging-lists.index') }}">Clear all filters</a>
            </div>
        @endif

        <div class="master-table-wrap">
            <table class="master-table packaging-list-table">
                <thead><tr><th scope="col">Packaging List</th><th scope="col">From / Ship To</th><th scope="col">Shipment / Project</th><th scope="col" class="is-num">Cartons</th><th scope="col" class="is-num">Gross Weight</th><th scope="col" class="is-num">CBM</th><th scope="col">Status</th><th scope="col" class="is-action"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>@forelse($lists as $list)
                    <tr class="is-clickable" data-href="{{ route('packaging-lists.show',$list) }}">
                        <td data-label="Packaging List"><a href="{{ route('packaging-lists.show',$list) }}">{{ $list->packing_number }}</a><span class="master-sub">{{ $list->packing_date?->format('d M Y') }} · {{ $list->invoice_number ?: 'No invoice' }}</span></td>
                        <td data-label="From / Ship To"><strong>{{ $list->from_name }}</strong><span class="master-sub">to {{ $list->ship_to_name }}</span></td>
                        <td data-label="Shipment / Project"><strong>{{ $list->shipment?->shipment_number ?: 'Not linked' }}</strong><span class="master-sub">{{ $list->project?->project_number ?: $list->modeLabel() }}</span></td>
                        <td data-label="Cartons" class="is-num">{{ number_format($list->total_cartons) }}</td><td data-label="Gross Weight" class="is-num">{{ number_format((float)$list->total_gross_weight,3) }} kg</td><td data-label="CBM" class="is-num">{{ number_format((float)$list->total_cbm,4) }}</td>
                        <td data-label="Status"><span class="master-badge pl-status-{{ $list->status }}">{{ $list->statusLabel() }}</span></td>
                        <td data-label="Actions" class="is-action"><a class="master-icon-btn" href="{{ route('packaging-lists.edit',$list) }}" aria-label="Edit {{ $list->packing_number }}" title="Edit"><i class="fa-solid fa-pen"></i></a></td>
                    </tr>
                @empty<tr><td colspan="8"><div class="master-empty-state"><i class="fa-solid fa-boxes-stacked"></i><p>{{ $filtersActive ? 'No packaging lists match these filters.' : 'No packaging lists have been created yet.' }}</p><a class="master-btn {{ $filtersActive ? 'master-btn-soft' : 'master-btn-primary' }}" href="{{ $filtersActive ? route('packaging-lists.index') : route('packaging-lists.create') }}">{{ $filtersActive ? 'Clear filters' : 'Create first packaging list' }}</a></div></td></tr>@endforelse</tbody>
            </table>
        </div>
        <x-pagination :items="$lists" />
    </section>
</div>
@endsection
