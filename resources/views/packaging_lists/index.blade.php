@extends('layouts.app')
@section('title', 'Packaging Lists')
@section('page-title', 'Packaging Lists')
@push('styles')<link rel="stylesheet" href="{{ $assetVer('assets/css/packaging-lists.css') }}">@endpush
@section('content')
<div class="packaging-list-page master-list">
    <header class="master-card master-list-bar">
        <div><p class="master-eyebrow">Shipping documents</p><h1>Packaging lists</h1><p class="master-sub">Carton-wise quantities, dimensions, weights and CBM ready for dispatch paperwork.</p></div>
        <a class="master-btn master-btn-primary" href="{{ route('packaging-lists.create') }}"><i class="fa-solid fa-plus"></i> New packaging list</a>
    </header>

    <div class="master-stats">
        <div class="master-stat master-stat--flat blue"><span class="icon"><i class="fa-solid fa-clipboard-list"></i></span><div><p class="master-stat-title">All lists</p><p class="master-stat-value">{{ number_format($stats['total']) }}</p></div></div>
        <div class="master-stat master-stat--flat orange"><span class="icon"><i class="fa-regular fa-pen-to-square"></i></span><div><p class="master-stat-title">Draft</p><p class="master-stat-value">{{ number_format($stats['draft']) }}</p></div></div>
        <div class="master-stat master-stat--flat green"><span class="icon"><i class="fa-solid fa-circle-check"></i></span><div><p class="master-stat-title">Ready / dispatched</p><p class="master-stat-value">{{ number_format($stats['confirmed']) }}</p></div></div>
        <div class="master-stat master-stat--flat purple"><span class="icon"><i class="fa-solid fa-boxes-stacked"></i></span><div><p class="master-stat-title">Total cartons</p><p class="master-stat-value">{{ number_format($stats['cartons']) }}</p></div></div>
    </div>

    <section class="master-card">
        <form method="GET" class="master-filter-row">
            <div class="master-search"><i class="fa-solid fa-search"></i><input class="master-input" name="search" value="{{ $search }}" placeholder="PL number, invoice, sender or receiver" aria-label="Search packaging lists"></div>
            <select class="master-select" name="status" aria-label="Status"><option value="all">All statuses</option>@foreach($statusOptions as $key => $label)<option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>@endforeach</select>
            <select class="master-select" name="mode" aria-label="Transport mode"><option value="all">All modes</option>@foreach($modeOptions as $key => $label)<option value="{{ $key }}" @selected($mode === $key)>{{ $label }}</option>@endforeach</select>
            <button class="master-btn master-btn-primary master-btn-sm">Apply</button>
            @if($search || $status !== 'all' || $mode !== 'all')<a class="master-btn master-btn-light master-btn-sm" href="{{ route('packaging-lists.index') }}">Reset</a>@endif
        </form>
        <div class="master-table-wrap">
            <table class="master-table packaging-list-table">
                <thead><tr><th scope="col">Packaging list</th><th scope="col">Parties</th><th scope="col">Shipment / project</th><th scope="col" class="is-num">Cartons</th><th scope="col" class="is-num">Gross weight</th><th scope="col" class="is-num">CBM</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead>
                <tbody>
                @forelse($lists as $list)
                    <tr class="is-clickable" data-href="{{ route('packaging-lists.show', $list) }}">
                        <td data-label="Packaging list"><a href="{{ route('packaging-lists.show', $list) }}"><strong>{{ $list->packing_number }}</strong></a><span class="master-sub">{{ $list->packing_date?->format('d M Y') }} · {{ $list->invoice_number ?: 'No invoice' }}</span></td>
                        <td data-label="Parties"><strong>{{ $list->from_name }}</strong><span class="master-sub">to {{ $list->ship_to_name }}</span></td>
                        <td data-label="Shipment / project"><strong>{{ $list->shipment?->shipment_number ?: '—' }}</strong><span class="master-sub">{{ $list->project?->project_number ?: $list->modeLabel() }}</span></td>
                        <td data-label="Cartons" class="is-num">{{ number_format($list->total_cartons) }}</td>
                        <td data-label="Gross weight" class="is-num">{{ number_format((float)$list->total_gross_weight, 3) }} kg</td>
                        <td data-label="CBM" class="is-num">{{ number_format((float)$list->total_cbm, 4) }}</td>
                        <td data-label="Status"><span class="master-badge pl-status-{{ $list->status }}">{{ $list->statusLabel() }}</span></td>
                        <td data-label="Action" class="is-action"><a class="master-icon-btn" href="{{ route('packaging-lists.edit', $list) }}" aria-label="Edit {{ $list->packing_number }}"><i class="fa-solid fa-pen"></i></a></td>
                    </tr>
                @empty
                    <tr><td colspan="8"><div class="master-empty-state"><i class="fa-solid fa-boxes-stacked"></i><p>No packaging lists found.</p><a class="master-btn master-btn-primary" href="{{ route('packaging-lists.create') }}">Create first packaging list</a></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <x-pagination :items="$lists" />
    </section>
</div>
@endsection
