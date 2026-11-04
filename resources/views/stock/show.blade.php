@extends('layouts.app')
@section('title', $item->number().' · stock')
@section('page-title', 'Stock item')
@push('styles')<link rel="stylesheet" href="{{ $assetVer('assets/css/stock.css') }}">@endpush
@section('content')
<div class="stock">
    <header class="master-card master-header stock-header"><div><p class="stock-eyebrow">{{ $item->number() }} · {{ $item->product->product_number }}</p><h1>{{ $item->product->name }}</h1><p class="master-sub">{{ $item->variant ?: 'Variant not specified' }} · {{ $item->batch_code ?: 'No batch specified' }}</p></div><div class="stock-actions"><a class="master-btn master-btn-soft" href="{{ route('stock.index') }}">Back to stock</a></div></header>
    @include('stock.partials.navigation')
    @include('stock.partials.errors')
    <div class="stock-facts">
        <div class="master-card stock-stat"><span>On hand</span><strong class="stock-number">{{ $item->quantity_on_hand }} <small>{{ $item->unit }}</small></strong></div>
        <div class="master-card stock-stat"><span>Batch / lot</span><strong>{{ $item->batch_code ?: 'Not specified' }}</strong></div>
        <div class="master-card stock-stat"><span>Expiry</span><strong>{{ $item->expires_on?->format('d M Y') ?: 'Not recorded' }}</strong>@if($item->expiryState())<span class="stock-expiry">{{ $item->expiryState() === 'expired' ? 'Expired' : 'Expiring within 30 days' }}</span>@endif</div>
        <div class="master-card stock-stat"><span>Ledger revision</span><strong>{{ $item->ledger_version }}</strong></div>
    </div>
    @if($openLines->isNotEmpty())
        <div class="stock-alert stock-alert--warning"><strong>Count in progress.</strong> A new movement will make the current snapshot stale and require a recount.
            @foreach($openLines as $line)<a href="{{ route('stock.counts.show', $line->count) }}">{{ $line->count->number() }}</a>{{ $loop->last ? '' : ',' }}@endforeach
        </div>
    @endif
    @if(auth()->user()->hasPermission('operations.manage'))
        <section class="master-card stock-section"><div class="stock-section-heading"><div><h2 class="master-section-title">Record a movement</h2><p class="master-sub">Receipts increase stock; issues decrease it. Correct errors with a new movement, not by editing history.</p></div></div>
            <form method="POST" action="{{ route('stock.movements.store', $item) }}">@csrf<input type="hidden" name="request_key" value="{{ old('request_key', $requestKey) }}">
                <div class="master-form-grid stock-form-grid">
                    <div class="master-field"><label class="master-label" for="movementType">Movement <span aria-hidden="true">*</span></label><select class="master-select" id="movementType" name="type" required><option value="receipt" @selected(old('type') === 'receipt')>Receipt · add stock</option><option value="issue" @selected(old('type') === 'issue')>Issue · remove stock</option></select></div>
                    <div class="master-field"><label class="master-label" for="movementQuantity">Quantity ({{ $item->unit }}) <span aria-hidden="true">*</span></label><input class="master-input" id="movementQuantity" name="quantity" type="text" inputmode="decimal" pattern="[0-9]+([.][0-9]{1,3})?" maxlength="16" value="{{ old('quantity') }}" required></div>
                    <div class="master-field"><label class="master-label" for="movementRef">Reference</label><input class="master-input" id="movementRef" name="reference" maxlength="255" value="{{ old('reference') }}" placeholder="Invoice, shipment or other reference"></div>
                    <div class="master-field stock-field-wide"><label class="master-label" for="movementReason">Reason <span aria-hidden="true">*</span></label><textarea class="master-input" id="movementReason" name="reason" rows="2" maxlength="1000" required>{{ old('reason') }}</textarea></div>
                </div><div class="stock-actions"><button type="submit" class="master-btn master-btn-primary">Record movement</button></div>
            </form>
        </section>
    @endif
    <section class="master-card stock-section"><div class="stock-section-heading"><div><h2 class="master-section-title">Movement history</h2><p class="master-sub">Newest first. Stock history cannot be edited or deleted.</p></div></div>
        <div class="master-table-wrap"><table class="master-table ui-mobile-cards stock-table"><thead><tr><th scope="col">Date / actor</th><th scope="col">Movement</th><th scope="col">Change</th><th scope="col">Balance after</th><th scope="col">Reason / reference</th></tr></thead><tbody>
            @forelse($movements as $movement)<tr><td data-label="Date / actor">{{ $movement->created_at->format('d M Y, H:i') }} {{ config('app.timezone') }}<span class="master-sub">{{ $movement->actor_name }}</span></td><td data-label="Movement">{{ $movement->label() }}<span class="master-sub">Revision {{ $movement->ledger_version }}</span></td><td data-label="Change" class="stock-number">{{ $movement->quantity_delta }} {{ $item->unit }}</td><td data-label="Balance after" class="stock-number">{{ $movement->balance_after }} {{ $item->unit }}</td><td data-label="Reason / reference"><span class="stock-wrap">{{ $movement->reason }}</span>@if($movement->countLine?->count)<span class="master-sub"><a href="{{ route('stock.counts.show', $movement->countLine->count) }}">{{ $movement->countLine->count->number() }}</a></span>@elseif($movement->reference)<span class="master-sub">{{ $movement->reference }}</span>@endif</td></tr>
            @empty<tr><td colspan="5"><div class="master-empty-state"><p>No movements recorded.</p></div></td></tr>@endforelse
        </tbody></table></div><x-pagination :items="$movements" />
    </section>
</div>
@endsection
