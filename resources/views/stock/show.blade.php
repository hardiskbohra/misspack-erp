@extends('layouts.app')
@section('title', $item->number().' · stock')
@section('page-title', 'Stock item')
@push('styles')<link rel="stylesheet" href="{{ $assetVer('assets/css/stock.css') }}">@endpush
@section('content')
@php
    $canManage = auth()->user()->hasPermission('operations.manage');
    $decisionForm = old('form_context');
    $oldRecord = old('record_revision');
    $oldLedger = old('ledger_version');
    $decisionStale = in_array($decisionForm, ['archive', 'restore'], true) && (!is_scalar($oldRecord) || !is_scalar($oldLedger) || (string) $oldRecord !== (string) $item->record_revision || (string) $oldLedger !== (string) $item->ledger_version);
    $movementStale = $decisionForm === 'movement' && (!is_scalar($oldRecord) || (string) $oldRecord !== (string) $item->record_revision);
    $oldText = fn ($key) => is_scalar(old($key)) ? (string) old($key) : '';
    $decisionReason = $oldText('reason');
@endphp
<div class="stock">
    <header class="master-card master-header stock-header"><div><p class="stock-eyebrow">{{ $item->number() }} · {{ $item->product->product_number }}</p><h1>{{ $item->product->name }}</h1><p class="master-sub">{{ $item->variant ?: 'Variant not specified' }} · {{ $item->batch_code ?: 'No batch specified' }}</p>@if($item->archived_at)<span class="stock-status stock-status--archived">Archived</span>@endif</div><div class="stock-actions"><a class="master-btn master-btn-soft" href="{{ route('stock.index', ['status' => $item->archived_at ? 'archived' : 'active']) }}">Back to stock</a>@if($canManage && !$item->archived_at)<a class="master-btn master-btn-primary" href="{{ route('stock.items.edit', $item) }}">Edit item</a><a class="master-btn master-btn-soft" href="#stockArchive">Delete</a>@elseif($canManage)<a class="master-btn master-btn-primary" href="#stockRestore">Restore</a>@endif</div></header>
    @include('stock.partials.navigation')
    @include('stock.partials.errors')
    @if($decisionStale)<div class="stock-alert stock-alert--warning" role="status"><strong>Review the latest item and balance.</strong> Your earlier decision was not applied because the record changed. Any nonzero-balance acknowledgement must be confirmed again.@if($decisionReason !== '')<p>Unapplied reason: {{ $decisionReason }}</p>@endif</div>@endif
    @if($movementStale)<details class="stock-alert stock-alert--warning"><summary>View your unsaved movement from the older form</summary><p>The item's details, status or correction requests changed. This movement was not recorded; review the current item before entering it again.</p><ul><li>Type: {{ $oldText('type') }}</li><li>Quantity: {{ $oldText('quantity') }}</li><li>Reference: {{ $oldText('reference') }}</li><li>Reason: {{ $oldText('reason') }}</li></ul></details>@endif
    @if($decisionForm === 'edit' && $item->archived_at)<details class="stock-alert stock-alert--warning"><summary>View your unsaved edit from the older form</summary><p>The item was archived before your edit was saved. Restore it first if the correction is still needed; these entries were not applied.</p><ul><li>Product ID: {{ $oldText('product_id') }}</li><li>Variant: {{ $oldText('variant') }}</li><li>Batch: {{ $oldText('batch_code') }}</li><li>Expiry: {{ $oldText('expires_on') }}</li><li>Unit: {{ $oldText('unit') }}</li><li>Requested quantity: {{ $oldText('requested_quantity') }}</li><li>Reason: {{ $oldText('reason') }}</li></ul></details>@endif
    @if($item->archived_at)<div class="stock-alert stock-alert--warning" role="status"><strong>This item is archived.</strong> Its balance and history are retained, but it is excluded from the active register and cannot receive movements or enter a new count. Archived {{ $item->archived_at->format('d M Y, H:i') }} {{ config('app.timezone') }}@if($item->archiver) by {{ $item->archiver->name }}@endif.</div>@endif
    <div class="stock-facts">
        <div class="master-card stock-stat"><span>On hand{{ $item->archived_at ? ' · archived' : '' }}</span><strong class="stock-number">{{ $item->quantity_on_hand }} <small>{{ $item->unit }}</small></strong></div>
        <div class="master-card stock-stat"><span>Batch / lot</span><strong>{{ $item->batch_code ?: 'Not specified' }}</strong></div>
        <div class="master-card stock-stat"><span>Expiry</span><strong>{{ $item->expires_on?->format('d M Y') ?: 'Not recorded' }}</strong>@if($item->expiryState())<span class="stock-expiry">{{ $item->expiryState() === 'expired' ? 'Expired' : 'Expiring within 30 days' }}</span>@endif</div>
        <div class="master-card stock-stat"><span>Ledger revision</span><strong>{{ $item->ledger_version }}</strong></div>
    </div>
    @if($openLines->isNotEmpty())
        <div class="stock-alert stock-alert--warning"><strong>Count in progress.</strong> A new movement or identity edit makes its snapshot stale and requires a recount. Finish or cancel all open counts before archiving this item.
            @foreach($openLines as $line)<a href="{{ route('stock.counts.show', $line->count) }}">{{ $line->count->number() }}</a>{{ $loop->last ? '' : ',' }}@endforeach
        </div>
    @endif
    @if($canManage && !$item->archived_at)
        <section class="master-card stock-section"><div class="stock-section-heading"><div><h2 class="master-section-title">Record a movement</h2><p class="master-sub">Receipts increase stock; issues decrease it. Correct errors with a new movement, not by editing history.</p></div></div>
            <form method="POST" action="{{ route('stock.movements.store', $item) }}">@csrf<input type="hidden" name="form_context" value="movement"><input type="hidden" name="record_revision" value="{{ $item->record_revision }}"><input type="hidden" name="request_key" value="{{ $movementStale ? $requestKey : ($oldText('request_key') ?: $requestKey) }}">
                <div class="master-form-grid stock-form-grid">
                    <div class="master-field"><label class="master-label" for="movementType">Movement <span aria-hidden="true">*</span></label><select class="master-select" id="movementType" name="type" required><option value="receipt" @selected(!$movementStale && old('type') === 'receipt')>Receipt · add stock</option><option value="issue" @selected(!$movementStale && old('type') === 'issue')>Issue · remove stock</option></select></div>
                    <div class="master-field"><label class="master-label" for="movementQuantity">Quantity ({{ $item->unit }}) <span aria-hidden="true">*</span></label><input class="master-input" id="movementQuantity" name="quantity" type="text" inputmode="decimal" pattern="[0-9]+([.][0-9]{1,3})?" maxlength="16" value="{{ $movementStale ? '' : $oldText('quantity') }}" required></div>
                    <div class="master-field"><label class="master-label" for="movementRef">Reference</label><input class="master-input" id="movementRef" name="reference" maxlength="255" value="{{ $movementStale ? '' : $oldText('reference') }}" placeholder="Invoice, shipment or other reference"></div>
                    <div class="master-field stock-field-wide"><label class="master-label" for="movementReason">Reason <span aria-hidden="true">*</span></label><textarea class="master-input" id="movementReason" name="reason" rows="2" maxlength="1000" required>{{ !$movementStale && (!$decisionForm || $decisionForm === 'movement') ? $oldText('reason') : '' }}</textarea></div>
                </div><div class="stock-actions"><button type="submit" class="master-btn master-btn-primary">Record movement</button></div>
            </form>
        </section>
    @endif
    @if($canManage && !$item->archived_at)
        <details id="stockArchive" class="master-card stock-section stock-disclosure" @if($decisionForm === 'archive') open @endif><summary>Delete / archive this item</summary>
            <p class="master-sub">Archiving removes the item from the active register, not from history. Its balance and ledger remain intact and it can be restored. Archived identities cannot be reused.</p>
            @if($openLines->isNotEmpty())<div class="stock-alert stock-alert--warning">Finish or cancel the open counts listed above before archiving.@if($decisionForm === 'archive' && $decisionReason !== '')<p>Unapplied reason: {{ $decisionReason }}</p>@endif</div>
            @else
                @if($item->quantity_milli > 0)<div class="stock-alert stock-alert--warning"><strong>This item still has {{ $item->quantity_on_hand }} {{ $item->unit }} on hand.</strong> Archiving will hide this balance from the active register, not remove the stock.</div>@endif
                <form method="POST" action="{{ route('stock.items.destroy', $item) }}" class="stock-review-form stock-decision-form" data-confirm="Archive this stock item? Its balance and history will be retained, and it can be restored." data-confirm-text="Archive item">@csrf @method('DELETE')
                    <input type="hidden" name="form_context" value="archive"><input type="hidden" name="record_revision" value="{{ $item->record_revision }}"><input type="hidden" name="ledger_version" value="{{ $item->ledger_version }}">
                    <label class="master-label" for="archiveReason">Reason for archiving <span aria-hidden="true">*</span></label><textarea class="master-input" id="archiveReason" name="reason" maxlength="1000" rows="3" required>{{ $decisionForm === 'archive' ? $decisionReason : '' }}</textarea>
                    @if($item->quantity_milli > 0)<label class="stock-checkbox"><input type="checkbox" name="acknowledge_nonzero" value="1" required @checked($decisionForm === 'archive' && !$decisionStale && old('acknowledge_nonzero'))><span>I understand {{ $item->quantity_on_hand }} {{ $item->unit }} will remain in the archive, outside the active register.</span></label>@endif
                    <button type="submit" class="master-btn master-btn-danger">Archive item</button>
                </form>
            @endif
        </details>
    @elseif($canManage && $item->archived_at)
        <details id="stockRestore" class="master-card stock-section stock-disclosure" @if($decisionForm === 'restore') open @endif><summary>Restore this item</summary><p class="master-sub">Return this item and its existing {{ $item->quantity_on_hand }} {{ $item->unit }} balance to the active register. History remains unchanged.</p>
            <form method="POST" action="{{ route('stock.items.restore', $item) }}" class="stock-review-form stock-decision-form" data-confirm="Restore this item and its existing balance to the active register?" data-confirm-text="Restore item" data-confirm-danger="false">@csrf
                <input type="hidden" name="form_context" value="restore"><input type="hidden" name="record_revision" value="{{ $item->record_revision }}"><input type="hidden" name="ledger_version" value="{{ $item->ledger_version }}">
                <label class="master-label" for="restoreReason">Reason for restoration <span aria-hidden="true">*</span></label><textarea class="master-input" id="restoreReason" name="reason" maxlength="1000" rows="3" required>{{ $decisionForm === 'restore' ? $decisionReason : '' }}</textarea><button type="submit" class="master-btn master-btn-primary">Restore item</button>
            </form>
        </details>
    @endif
    <section class="master-card stock-section"><div class="stock-section-heading"><div><h2 class="master-section-title">Movement history</h2><p class="master-sub">Newest first. Stock history cannot be edited or deleted. Older movements use the current identity until the first correction, when it is captured for history; earlier unrecorded changes cannot be reconstructed.</p></div></div>
        <div class="master-table-wrap"><table class="master-table ui-mobile-cards stock-table"><thead><tr><th scope="col">Date / actor</th><th scope="col">Movement / recorded item</th><th scope="col">Change</th><th scope="col">Balance after</th><th scope="col">Reason / reference</th></tr></thead><tbody>
            @forelse($movements as $movement)
                @php
                    $identity = $movement->item_snapshot ?? $item->snapshot();
                @endphp
                <tr><td data-label="Date / actor">{{ $movement->created_at->format('d M Y, H:i') }} {{ config('app.timezone') }}<span class="master-sub">{{ $movement->actor_name }}</span></td><td data-label="Movement / recorded item">{{ $movement->label() }}<span class="master-sub">Revision {{ $movement->ledger_version }} · {{ $identity['product_name'] }} (#{{ $identity['product_id'] }}) · {{ $identity['variant'] ?: 'No variant' }} · {{ $identity['batch_code'] ?: 'No batch' }} · {{ $identity['expires_on'] ? 'Expiry '.$identity['expires_on'] : 'No expiry' }}@if(!$movement->item_snapshot) · Current identity (older record)@elseif(($identity['snapshot_basis'] ?? '') === 'legacy_pre_edit') · Captured before first correction (older record)@endif</span></td><td data-label="Change" class="stock-number">{{ $movement->quantity_delta }} {{ $identity['unit'] }}</td><td data-label="Balance after" class="stock-number">{{ $movement->balance_after }} {{ $identity['unit'] }}</td><td data-label="Reason / reference"><span class="stock-wrap">{{ $movement->reason }}</span>@if($movement->countLine?->count)<span class="master-sub"><a href="{{ route('stock.counts.show', $movement->countLine->count) }}">{{ $movement->countLine->count->number() }}</a></span>@elseif($movement->reference)<span class="master-sub">{{ $movement->reference }}</span>@endif</td></tr>
            @empty<tr><td colspan="5"><div class="master-empty-state"><p>No movements recorded.</p></div></td></tr>@endforelse
        </tbody></table></div><x-pagination :items="$movements" />
    </section>
    <section class="master-card stock-section"><h2 class="master-section-title">Count history</h2><p class="master-sub">All counts that included this item, even when the physical quantity matched the book and no adjustment was needed.</p>
        <div class="master-table-wrap"><table class="master-table ui-mobile-cards stock-count-table"><thead><tr><th scope="col">Count</th><th scope="col">Status</th><th scope="col">Book</th><th scope="col">Physical</th><th scope="col">Variance</th></tr></thead><tbody>
            @forelse($countHistory as $line)<tr><td data-label="Count"><a href="{{ route('stock.counts.show', $line->count) }}"><strong>{{ $line->count->number() }}</strong></a><span class="master-sub">{{ $line->count->title }} · {{ $line->count->created_at->format('d M Y') }}</span></td><td data-label="Status">{{ $line->count->status === 'submitted' ? 'Awaiting review' : $line->count->label() }}</td><td data-label="Book" class="stock-number">{{ $line->book_qty }} {{ $line->item_snapshot['unit'] }}</td><td data-label="Physical" class="stock-number">{{ $line->counted_qty === null ? 'Not counted' : $line->counted_qty.' '.$line->item_snapshot['unit'] }}</td><td data-label="Variance" class="stock-number">{{ $line->variance() === null ? '—' : $line->variance().' '.$line->item_snapshot['unit'] }}</td></tr>
            @empty<tr><td colspan="5"><div class="master-empty-state"><p>No physical counts include this item.</p></div></td></tr>@endforelse
        </tbody></table></div><x-pagination :items="$countHistory" />
    </section>
    <section class="master-card stock-section"><h2 class="master-section-title">Item history</h2><p class="master-sub">Details corrections, balance requests, archives and restorations are retained with their reasons. Balance requests post only if independently approved.</p>
        <div class="stock-events">@forelse($events as $event)
            @php
                $before = $event->before_snapshot; $after = $event->after_snapshot;
                $eventRows = [
                    'Product' => [($before['product_name'] ?? '—').' · #'.($before['product_id'] ?? '—'), ($after['product_name'] ?? '—').' · #'.($after['product_id'] ?? '—')],
                    'Variant' => [$before['variant'] ?: 'Not specified', $after['variant'] ?: 'Not specified'],
                    'Batch / lot' => [$before['batch_code'] ?: 'Not specified', $after['batch_code'] ?: 'Not specified'],
                    'Expiry' => [$before['expires_on'] ?: 'Not recorded', $after['expires_on'] ?: 'Not recorded'],
                    'Unit' => [$before['unit'], $after['unit']],
                    'Balance at decision' => [$before['quantity_on_hand'].' '.$before['unit'], $after['quantity_on_hand'].' '.$after['unit']],
                    'Status' => [$before['archived'] ? 'Archived' : 'Active', $after['archived'] ? 'Archived' : 'Active'],
                ];
                if (isset($after['requested_quantity'])) $eventRows['Requested balance'] = ['—', $after['requested_quantity'].' '.$after['unit'].' · awaiting review when requested'];
            @endphp
            <article class="stock-event"><div><strong>{{ match ($event->action) { 'updated' => 'Item corrected', 'archived' => 'Item archived', 'restored' => 'Item restored', default => ucfirst($event->action) } }}</strong> · {{ $event->actor_name }} · Revision {{ $event->record_revision }}<span class="master-sub">{{ $event->created_at->format('d M Y, H:i') }} {{ config('app.timezone') }}</span></div><p class="stock-wrap">{{ $event->reason }}</p>@if($event->stockCount)<p><a href="{{ route('stock.counts.show', $event->stockCount) }}">{{ $event->stockCount->number() }} · view correction review</a> ({{ $event->stockCount->status === 'submitted' ? 'Awaiting review' : $event->stockCount->label() }})</p>@endif
                <details><summary>View before and after</summary><div class="master-table-wrap"><table class="master-table ui-mobile-cards"><thead><tr><th scope="col">Field</th><th scope="col">Before</th><th scope="col">After</th></tr></thead><tbody>@foreach($eventRows as $label => $values)<tr><td data-label="Field">{{ $label }}</td><td data-label="Before">{{ $values[0] }}</td><td data-label="After">{{ $values[1] }}</td></tr>@endforeach</tbody></table></div></details>
            </article>
        @empty<p class="master-sub">No item corrections or archive decisions recorded.</p>@endforelse</div><x-pagination :items="$events" />
    </section>
</div>
@endsection
@push('scripts')
<script>
    function openStockDecision() {
        var id = window.location.hash.slice(1);
        if (id !== 'stockArchive' && id !== 'stockRestore') return;
        var panel = document.getElementById(id);
        if (panel) {
            panel.open = true;
            var summary = panel.querySelector('summary');
            if (summary) summary.focus();
        }
    }
    window.addEventListener('hashchange', openStockDecision);
    openStockDecision();
</script>
@endpush
