@extends('layouts.app')
@section('title', 'Add stock item')
@section('page-title', 'Add stock item')
@push('styles')<link rel="stylesheet" href="{{ $assetVer('assets/css/stock.css') }}">@endpush
@section('content')
<div class="stock">
    <header class="master-card master-header stock-header"><div><p class="stock-eyebrow">Stock register</p><h1>Add stock item</h1><p class="master-sub">Define one product, variant and batch in the single stock location.</p></div><a class="master-btn master-btn-soft" href="{{ route('stock.index') }}">Back to stock</a></header>
    @include('stock.partials.navigation')
    @include('stock.partials.errors')
    <form method="POST" action="{{ route('stock.items.store') }}" class="master-card master-form-card stock-section">@csrf
        <h2 class="master-section-title">Item identity</h2>
        <p class="master-sub">Choose the exact variant and batch. Identity, unit and expiry are kept as history after creation.</p>
        <div class="master-form-grid stock-form-grid">
            <div class="master-field stock-field-wide"><label class="master-label" for="stockProduct">Product <span aria-hidden="true">*</span></label><select class="master-select" name="product_id" id="stockProduct" required><option value="">Select a product</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected((string) old('product_id') === (string) $product->id)>{{ $product->name }} · {{ $product->product_number }}@if($product->sku) · {{ $product->sku }}@endif @if($product->status !== 'active') · {{ ucfirst($product->status) }}@endif</option>@endforeach</select>@if($products->isEmpty())<p class="master-help">Create a product in the catalogue before adding stock.</p>@endif</div>
            <div class="master-field"><label class="master-label" for="stockVariant">Variant</label><input class="master-input" id="stockVariant" name="variant" maxlength="200" value="{{ old('variant') }}" placeholder="e.g. Amber, 100 ml, matte"><p class="master-help">Include the differences that make this stock distinct.</p></div>
            <div class="master-field"><label class="master-label" for="stockUnit">Stock unit <span aria-hidden="true">*</span></label><input class="master-input" id="stockUnit" name="unit" maxlength="30" value="{{ old('unit', 'pcs') }}" required><p class="master-help">Enter one unit; automatic unit conversion is not supported.</p></div>
            <div class="master-field"><label class="master-label" for="stockBatch">Batch / lot</label><input class="master-input" id="stockBatch" name="batch_code" maxlength="120" value="{{ old('batch_code') }}" placeholder="Leave blank only if no batch applies"></div>
            <div class="master-field"><label class="master-label" for="stockExpiry">Expiry date</label><input class="master-input" type="date" id="stockExpiry" name="expires_on" value="{{ old('expires_on') }}"><p class="master-help">Optional; requires a named batch. Expired stock is flagged, not blocked.</p></div>
        </div>
        <h2 class="master-section-title stock-form-heading">Opening balance</h2>
        <div class="stock-alert"><strong>Enter a verified opening count.</strong> This is posted immediately. Enter 0 only if the balance is known to be zero; it cannot be edited later.</div>
        <div class="master-form-grid stock-form-grid">
            <div class="master-field"><label class="master-label" for="stockOpening">Opening quantity <span aria-hidden="true">*</span></label><input class="master-input" id="stockOpening" name="opening_quantity" type="text" inputmode="decimal" pattern="[0-9]+([.][0-9]{1,3})?" maxlength="16" value="{{ old('opening_quantity') }}" required><p class="master-help">Up to three decimal places.</p></div>
            <div class="master-field"><label class="master-label" for="stockReference">Reference</label><input class="master-input" id="stockReference" name="reference" maxlength="255" value="{{ old('reference') }}" placeholder="e.g. opening count sheet"></div>
            <div class="master-field stock-field-wide"><label class="master-label" for="stockReason">Opening reason / source <span aria-hidden="true">*</span></label><textarea class="master-input" id="stockReason" name="reason" maxlength="1000" rows="3" required>{{ old('reason') }}</textarea></div>
        </div>
        <div class="stock-actions"><a class="master-btn master-btn-soft" href="{{ route('stock.index') }}">Cancel</a><button class="master-btn master-btn-primary" type="submit" @disabled($products->isEmpty())>Record opening stock</button></div>
    </form>
</div>
@endsection
