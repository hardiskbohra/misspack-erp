@extends('layouts.app')
@section('title', $count->number().' · stock count')
@section('page-title', 'Physical stock count')
@push('styles')<link rel="stylesheet" href="{{ $assetVer('assets/css/stock.css') }}">@endpush
@section('content')
<div class="stock">
    <header class="master-card master-header stock-header"><div><p class="stock-eyebrow">{{ $count->number() }} · {{ $count->status === 'submitted' ? 'Awaiting review' : $count->label() }}</p><h1>{{ $count->title }}</h1><p class="master-sub">Counter: {{ $count->counter_name }} · Created {{ $count->created_at->format('d M Y, H:i') }} {{ config('app.timezone') }}</p></div><a class="master-btn master-btn-soft" href="{{ route('stock.counts.index') }}">Back to counts</a></header>
    @include('stock.partials.navigation')
    @include('stock.partials.errors')
    @php($staleDraft = is_array(old('lines')) && old('revision') !== null && (string) old('revision') !== (string) $count->revision)
    @if($staleDraft && $isCounter)
        <details class="stock-alert stock-alert--warning"><summary>View your unsaved entries from the older form</summary><p>The count changed elsewhere. The inputs below now show the latest saved values; these unsaved entries were not applied.</p>
            <ul>@foreach($count->lines as $line) @php($oldLine = old('lines.'.$line->id)) @if(is_array($oldLine))<li>{{ $line->item_snapshot['item_number'] }}: {{ $oldLine['counted_qty'] ?? 'not counted' }} · {{ $oldLine['reason'] ?? 'no reason' }}</li>@endif @endforeach</ul>
        </details>
    @endif
    <div class="stock-facts">
        <div class="master-card stock-stat"><span>Selected items</span><strong>{{ $count->lines->count() }}</strong></div>
        <div class="master-card stock-stat"><span>Counted (saved)</span><strong>{{ $count->lines->whereNotNull('counted_qty')->count() }}</strong></div>
        <div class="master-card stock-stat"><span>Differences (saved)</span><strong>{{ $count->lines->filter(fn ($line) => $line->variance() !== null && $line->variance() !== '0.000')->count() }}</strong></div>
        <div class="master-card stock-stat"><span>Status</span><strong>{{ $count->status === 'submitted' ? 'Awaiting review' : $count->label() }}</strong></div>
    </div>
    @if($count->notes)<div class="stock-alert"><strong>Count instructions:</strong> <span class="stock-wrap">{{ $count->notes }}</span></div>@endif
    @if(!$count->created_by && in_array($count->status, ['draft', 'submitted'], true))<div class="stock-alert stock-alert--warning" role="status">The original counter is no longer available. This count cannot be edited or independently approved; cancel it and start a new count.</div>@endif
    @if($count->lines->contains(fn ($line) => $line->isStale()) && in_array($count->status, ['draft', 'submitted'], true))<div class="stock-alert stock-alert--warning" role="status"><strong>Recount required.</strong> At least one selected item's balance or identity changed since its snapshot. A submitted count must be returned before the counter refreshes and recounts it.</div>@endif
    <section class="master-card stock-section"><div class="stock-section-heading"><div><h2 class="master-section-title">Counted quantities</h2><p class="master-sub">Blank means not counted; enter zero explicitly. Explain every difference. Book values are the snapshot, not a live balance.</p></div></div>
        @if($count->status === 'draft' && $isCounter)<form method="POST" action="{{ route('stock.counts.update', $count) }}">@csrf<input type="hidden" name="revision" value="{{ $count->revision }}">@endif
        <div class="master-table-wrap"><table class="master-table ui-mobile-cards stock-count-table"><thead><tr><th scope="col">Product / variant</th><th scope="col">Batch / expiry</th><th scope="col">Book quantity</th><th scope="col">Physical quantity</th><th scope="col">Variance</th><th scope="col">Difference reason</th></tr></thead><tbody>
            @foreach($count->lines as $line)
                @php($snapshot = $line->item_snapshot)
                <tr><td data-label="Product / variant"><a href="{{ route('stock.items.show', $line->stock_item_id) }}"><strong>{{ $snapshot['product_name'] }}</strong></a><span class="master-sub">{{ $snapshot['item_number'] }} · {{ $snapshot['product_number'] }} · {{ $snapshot['variant'] ?: 'No variant' }}</span>@if($line->isStale() && in_array($count->status, ['draft', 'submitted'], true))<span class="stock-expiry">Stock changed since snapshot</span>@if($line->item)<span class="master-sub">Current: {{ $line->item->product->name }} · {{ $line->item->variant ?: 'No variant' }} · {{ $line->item->batch_code ?: 'No batch' }} · {{ $line->item->quantity_on_hand }} {{ $line->item->unit }}@if($line->item->archived_at) · Archived @endif</span>@endif @endif</td>
                    <td data-label="Batch / expiry">{{ $snapshot['batch_code'] ?: 'Not specified' }}<span class="master-sub">{{ $snapshot['expires_on'] ?: 'No expiry' }}</span></td>
                    <td data-label="Book quantity" class="stock-number">{{ $line->book_qty }} {{ $snapshot['unit'] }}<span class="master-sub">Revision {{ $line->ledger_version }}</span></td>
                    <td data-label="Physical quantity">@if($count->status === 'draft' && $isCounter)<label class="sr-only" for="countQty{{ $line->id }}">Physical quantity for {{ $snapshot['item_number'] }}</label><input class="master-input stock-count-input" type="text" inputmode="decimal" pattern="[0-9]+([.][0-9]{1,3})?" maxlength="16" id="countQty{{ $line->id }}" name="lines[{{ $line->id }}][counted_qty]" value="{{ $staleDraft ? $line->counted_qty : old('lines.'.$line->id.'.counted_qty', $line->counted_qty) }}" placeholder="Not counted"> <span>{{ $snapshot['unit'] }}</span>@else<span class="stock-number">{{ $line->counted_qty === null ? 'Not counted' : $line->counted_qty.' '.$snapshot['unit'] }}</span>@endif</td>
                    <td data-label="Variance" class="stock-number">{{ $line->variance() === null ? '—' : $line->variance().' '.$snapshot['unit'] }}</td>
                    <td data-label="Difference reason">@if($count->status === 'draft' && $isCounter)<label class="sr-only" for="countReason{{ $line->id }}">Difference reason for {{ $snapshot['item_number'] }}</label><textarea class="master-input stock-count-reason" rows="2" maxlength="1000" id="countReason{{ $line->id }}" name="lines[{{ $line->id }}][reason]" placeholder="Required if there is a difference">{{ $staleDraft ? $line->reason : old('lines.'.$line->id.'.reason', $line->reason) }}</textarea>@else<span class="stock-wrap">{{ $line->reason ?: '—' }}</span>@endif</td></tr>
            @endforeach
        </tbody></table></div>
        @if($count->status === 'draft' && $isCounter)
            <p class="master-help">Displayed variance updates when you save the draft. Both buttons save the quantities currently entered.</p>
            <div class="stock-actions"><button type="submit" class="master-btn master-btn-soft">Save draft</button><button type="submit" class="master-btn master-btn-primary" formaction="{{ route('stock.counts.submit', $count) }}">Submit for independent review</button></div></form>
        @endif
    </section>
    @if($count->status === 'draft' && $isCounter)
        <section class="master-card stock-section"><h2 class="master-section-title">Refresh for a recount</h2><p class="master-sub">Refresh all item identities and book balances to their current values and clear all physical quantities and reasons. Previously submitted values remain in the history below.</p>
            <form method="POST" action="{{ route('stock.counts.refresh', $count) }}" data-confirm="Refresh book balances and clear all physical quantities and reasons? You must recount every selected item." data-confirm-text="Refresh for recount">@csrf<input type="hidden" name="revision" value="{{ $count->revision }}"><button type="submit" class="master-btn master-btn-soft">Refresh book balances</button></form>
        </section>
    @endif
    @if($count->status === 'submitted' && $canReview)
        <section class="master-card stock-section"><div class="stock-section-heading"><div><h2 class="master-section-title">Independent review</h2><p class="master-sub">Approval posts every difference atomically. Confirm the evidence before proceeding; a changed stock revision blocks posting.</p></div></div>
            <div class="stock-review-grid">
                <form method="POST" action="{{ route('stock.counts.approve', $count) }}" class="stock-review-form" data-confirm="Approve this count and post its differences to stock?" data-confirm-text="Approve and post" data-confirm-danger="false">@csrf<input type="hidden" name="revision" value="{{ $count->revision }}"><label class="master-label" for="approveReason">Approval reason <span aria-hidden="true">*</span></label><textarea class="master-input" id="approveReason" name="reason" maxlength="1000" rows="3" required></textarea><button type="submit" class="master-btn master-btn-primary">Approve and post</button></form>
                <form method="POST" action="{{ route('stock.counts.reject', $count) }}" class="stock-review-form">@csrf<input type="hidden" name="revision" value="{{ $count->revision }}"><label class="master-label" for="rejectReason">Reason to return for correction <span aria-hidden="true">*</span></label><textarea class="master-input" id="rejectReason" name="reason" maxlength="1000" rows="3" required></textarea><button type="submit" class="master-btn master-btn-soft">Return to counter</button></form>
            </div>
        </section>
    @elseif($count->status === 'submitted' && $isCounter)
        <div class="stock-alert">This count is awaiting a different operations manager's review. You cannot approve or reject your own count.</div>
    @endif
    @if($canManage && in_array($count->status, ['draft', 'submitted'], true))
        <details class="master-card stock-section stock-disclosure"><summary>Cancel this count</summary><p class="master-sub">Cancellation keeps the count and its history. It does not change stock.</p><form method="POST" action="{{ route('stock.counts.cancel', $count) }}" class="stock-review-form" data-confirm="Cancel this count without posting any stock adjustment?" data-confirm-text="Cancel count">@csrf<input type="hidden" name="revision" value="{{ $count->revision }}"><label class="master-label" for="cancelReason">Cancellation reason <span aria-hidden="true">*</span></label><textarea class="master-input" id="cancelReason" name="reason" maxlength="1000" rows="2" required></textarea><button type="submit" class="master-btn master-btn-soft">Cancel count</button></form></details>
    @endif
    <section class="master-card stock-section"><h2 class="master-section-title">Count history</h2><p class="master-sub">Submission snapshots and decisions are retained, even if the counter later refreshes for a recount.</p>
        <div class="stock-events">@foreach($count->events as $event)<article class="stock-event"><div><strong>{{ ucfirst($event->action) }}</strong> · {{ $event->actor_name }} · Revision {{ $event->count_revision }} <span class="master-sub">{{ $event->created_at->format('d M Y, H:i') }} {{ config('app.timezone') }}</span></div>@if($event->reason)<p class="stock-wrap">{{ $event->reason }}</p>@endif
            @if($event->snapshot)<details><summary>View recorded quantities</summary><div class="master-table-wrap"><table class="master-table ui-mobile-cards"><thead><tr><th scope="col">Item</th><th scope="col">Book</th><th scope="col">Physical</th><th scope="col">Variance</th><th scope="col">Reason</th></tr></thead><tbody>@foreach($event->snapshot as $record)<tr><td data-label="Item">{{ $record['item']['item_number'] }} · {{ $record['item']['product_name'] }}<span class="master-sub">{{ $record['item']['variant'] ?: 'No variant' }} · {{ $record['item']['batch_code'] ?: 'No batch' }}</span></td><td data-label="Book">{{ $record['book_qty'] }} {{ $record['item']['unit'] }}</td><td data-label="Physical">{{ $record['counted_qty'] ?? 'Not counted' }}</td><td data-label="Variance">{{ $record['variance'] ?? '—' }}</td><td data-label="Reason" class="stock-wrap">{{ $record['reason'] ?: '—' }}</td></tr>@endforeach</tbody></table></div></details>@endif
        </article>@endforeach</div>
    </section>
</div>
@endsection
