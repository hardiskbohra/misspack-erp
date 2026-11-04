@extends('layouts.app')
@section('title', 'Start a physical count')
@section('page-title', 'Start a physical count')
@push('styles')<link rel="stylesheet" href="{{ $assetVer('assets/css/stock.css') }}">@endpush
@section('content')
<div class="stock">
    <header class="master-card master-header stock-header"><div><p class="stock-eyebrow">Physical counts</p><h1>Start a physical count</h1><p class="master-sub">Choose the batches to count. Their current book quantities are snapshotted when the count starts.</p></div><a class="master-btn master-btn-soft" href="{{ route('stock.counts.index') }}">Back to counts</a></header>
    @include('stock.partials.navigation')
    @include('stock.partials.errors')
    <div class="stock-alert stock-alert--warning"><strong>Count against a stable snapshot.</strong> If an item's balance or identity changes, it must be recounted against a refreshed snapshot before approval. Archived items are not available for new counts.</div>
    <form method="POST" action="{{ route('stock.counts.store') }}" class="master-card stock-section">@csrf
        <h2 class="master-section-title">Count details</h2>
        <div class="master-form-grid stock-form-grid">
            <div class="master-field stock-field-wide"><label class="master-label" for="countTitle">Count name <span aria-hidden="true">*</span></label><input class="master-input" id="countTitle" name="title" maxlength="160" value="{{ old('title') }}" placeholder="e.g. November bottle count" required></div>
            <div class="master-field stock-field-wide"><label class="master-label" for="countItems">Items to count <span aria-hidden="true">*</span></label><select class="master-select" id="countItems" name="item_ids[]" multiple required aria-describedby="countItemsHelp">
                @foreach($items as $item)<option value="{{ $item->id }}" @selected(in_array((string) $item->id, array_map('strval', old('item_ids', [])), true))>{{ $item->number() }} · {{ $item->product->name }} · {{ $item->variant ?: 'No variant' }} · {{ $item->batch_code ?: 'No batch' }} · {{ $item->quantity_on_hand }} {{ $item->unit }}</option>@endforeach
            </select><p class="master-help" id="countItemsHelp">Select 1–100 items, including zero-balance items when they need to be counted. Only these batches are included.</p></div>
            <div class="master-field stock-field-wide"><label class="master-label" for="countNotes">Instructions / notes</label><textarea class="master-input" id="countNotes" name="notes" maxlength="2000" rows="3">{{ old('notes') }}</textarea></div>
        </div>
        @if($items->isEmpty())<div class="stock-alert">No stock items are available. <a href="{{ route('stock.items.create') }}">Add an item and opening balance first.</a></div>@endif
        <div class="stock-actions"><a class="master-btn master-btn-soft" href="{{ route('stock.counts.index') }}">Cancel</a><button class="master-btn master-btn-primary" type="submit" @disabled($items->isEmpty())>Create count and snapshot</button></div>
    </form>
</div>
@endsection
