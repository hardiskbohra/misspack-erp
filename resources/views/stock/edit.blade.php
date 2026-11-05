@extends('layouts.app')
@section('title', 'Edit '.$item->number().' · stock')
@section('page-title', 'Edit stock item')
@push('styles')<link rel="stylesheet" href="{{ $assetVer('assets/css/stock.css') }}">@endpush
@section('content')
@php
    $oldRecord = old('record_revision');
    $oldLedger = old('ledger_version');
    $oldInput = session()->getOldInput();
    $staleForm = array_key_exists('requested_quantity', $oldInput) && (!is_scalar($oldRecord) || !is_scalar($oldLedger) || (string) $oldRecord !== (string) $item->record_revision || (string) $oldLedger !== (string) $item->ledger_version);
    $oldText = fn ($key) => is_scalar(old($key)) ? (string) old($key) : '';
    $formValue = function ($key, $saved) use ($staleForm, $oldInput) {
        if ($staleForm || !array_key_exists($key, $oldInput)) return $saved;
        $value = $oldInput[$key];
        return is_scalar($value) || $value === null ? $value : $saved;
    };
@endphp
<div class="stock">
    <header class="master-card master-header stock-header"><div><p class="stock-eyebrow">{{ $item->number() }} · stock register</p><h1>Edit stock item</h1><p class="master-sub">Correct this item's details or request a balance correction.</p></div><a class="master-btn master-btn-soft" href="{{ route('stock.items.show', $item) }}">Back to item</a></header>
    @include('stock.partials.navigation')
    @include('stock.partials.errors')
    @if($staleForm)
        <details class="stock-alert stock-alert--warning"><summary>View your unsaved entries from the older form</summary><p>The item or its ledger changed elsewhere. The form now shows the latest saved values; these older entries were not applied.</p>
            <ul><li>Product ID: {{ $oldText('product_id') }}</li><li>Variant: {{ $oldText('variant') }}</li><li>Batch: {{ $oldText('batch_code') }}</li><li>Expiry: {{ $oldText('expires_on') }}</li><li>Unit: {{ $oldText('unit') }}</li><li>Requested quantity: {{ $oldText('requested_quantity') }}</li><li>Reason: {{ $oldText('reason') }}</li></ul>
        </details>
    @endif
    <div class="stock-alert stock-alert--warning"><strong>Correct the existing record, not a physical transfer or unit conversion.</strong> Changing product, variant, batch, expiry or unit does not convert the quantity. Previous movement identities and count snapshots remain in the history. Open counts must be refreshed and recounted after an identity change.</div>
    <form method="POST" action="{{ route('stock.items.update', $item) }}" class="master-card master-form-card stock-section">@csrf @method('PUT')
        <input type="hidden" name="form_context" value="edit"><input type="hidden" name="record_revision" value="{{ $item->record_revision }}"><input type="hidden" name="ledger_version" value="{{ $item->ledger_version }}">
        <h2 class="master-section-title">Item details</h2>
        <div class="master-form-grid stock-form-grid">
            <div class="master-field stock-field-wide"><label class="master-label" for="stockProduct">Product <span aria-hidden="true">*</span></label><select class="master-select" name="product_id" id="stockProduct" required><option value="">Select a product</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected((string) $formValue('product_id', $item->product_id) === (string) $product->id)>{{ $product->name }} · {{ $product->product_number }}@if($product->sku) · {{ $product->sku }}@endif @if($product->status !== 'active') · {{ ucfirst($product->status) }}@endif</option>@endforeach</select></div>
            <div class="master-field"><label class="master-label" for="stockVariant">Variant</label><input class="master-input" id="stockVariant" name="variant" maxlength="200" value="{{ $formValue('variant', $item->variant) }}"></div>
            <div class="master-field"><label class="master-label" for="stockUnit">Stock unit <span aria-hidden="true">*</span></label><input class="master-input" id="stockUnit" name="unit" maxlength="30" value="{{ $formValue('unit', $item->unit) }}" required></div>
            <div class="master-field"><label class="master-label" for="stockBatch">Batch / lot</label><input class="master-input" id="stockBatch" name="batch_code" maxlength="120" value="{{ $formValue('batch_code', $item->batch_code) }}"></div>
            <div class="master-field"><label class="master-label" for="stockExpiry">Expiry date</label><input class="master-input" type="date" id="stockExpiry" name="expires_on" value="{{ $formValue('expires_on', $item->expires_on?->format('Y-m-d')) }}"><p class="master-help">Optional; requires a named batch.</p></div>
        </div>
        <h2 class="master-section-title stock-form-heading">Quantity correction</h2>
        <div class="stock-alert"><strong>Current balance: {{ $item->quantity_on_hand }} {{ $item->unit }}.</strong> Leave the quantity unchanged to edit details only. A different quantity creates a physical count for another operations manager to review; it does not change the balance until approved.</div>
        <div class="master-form-grid stock-form-grid">
            <div class="master-field"><label class="master-label" for="stockRequestedQuantity">Requested quantity <span aria-hidden="true">*</span></label><input class="master-input" id="stockRequestedQuantity" name="requested_quantity" type="text" inputmode="decimal" pattern="[0-9]+([.][0-9]{1,3})?" maxlength="16" value="{{ $formValue('requested_quantity', $item->quantity_on_hand) }}" required><p class="master-help">Enter the verified physical quantity, to three decimal places.</p></div>
            <div class="master-field stock-field-wide"><label class="master-label" for="stockEditReason">Reason for change <span aria-hidden="true">*</span></label><textarea class="master-input" id="stockEditReason" name="reason" maxlength="1000" rows="3" required>{{ $formValue('reason', '') }}</textarea><p class="master-help">The reason and before/after details are retained in item history.</p></div>
        </div>
        <div class="stock-actions"><a class="master-btn master-btn-soft" href="{{ route('stock.items.show', $item) }}">Cancel</a><button class="master-btn master-btn-primary" type="submit">Save changes</button></div>
    </form>
</div>
@endsection
