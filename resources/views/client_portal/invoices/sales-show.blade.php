@extends('client_portal.layouts.app')

@section('title', $invoice->invoice_number)
@section('page-title', 'Sales invoice')

@section('content')
<div class="cp-page-head">
    <div>
        <a class="cp-back-link" href="{{ route('client-portal.invoices.index') }}"><i class="fa-solid fa-arrow-left"></i> All invoices</a>
        <p class="cp-eyebrow">ERP sales invoice · {{ $invoice->typeLabel() }}</p>
        <h1>{{ $invoice->invoice_number }}</h1>
        <p>{{ $invoice->clientPortalStateLabel() }}@if($invoice->due_date) · Due {{ $invoice->due_date->format('d M Y') }}@endif</p>
    </div>
    <div class="cp-support-detail-actions">
        <a class="master-btn master-btn-primary" href="{{ route('client-portal.invoices.sales.print', $invoice) }}" target="_blank" rel="noopener"><i class="fa-solid fa-print"></i> Print invoice</a>
        <a class="master-btn master-btn-light" href="{{ route('client-portal.invoices.index') }}">Back</a>
    </div>
</div>

<div class="cp-grid-4 cp-invoice-metrics">
    <div class="cp-card cp-stat"><span>Invoice total</span><strong>{{ \App\Helpers\CommonHelper::amount($invoice->total_amount, $invoice->currency) }}</strong></div>
    <div class="cp-card cp-stat"><span>Received</span><strong>{{ \App\Helpers\CommonHelper::amount($invoice->clientPortalReceivedAmount(), $invoice->currency) }}</strong></div>
    <div class="cp-card cp-stat"><span>Balance due</span><strong>{{ \App\Helpers\CommonHelper::amount($invoice->clientPortalBalanceDue(), $invoice->currency) }}</strong></div>
    <div class="cp-card cp-stat"><span>Payment state</span><strong>{{ $invoice->clientPortalStateLabel() }}</strong></div>
</div>

<section class="cp-card cp-invoice-section cp-sales-lines">
    <div class="cp-section-heading"><div><p class="cp-eyebrow">Itemised billing</p><h2>Invoice lines</h2></div><span class="cp-muted">{{ $invoice->items->count() }} {{ \Illuminate\Support\Str::plural('item', $invoice->items->count()) }}</span></div>
    <div class="cp-table-wrap">
        <table class="cp-table">
            <thead><tr><th>Item</th><th>Quantity</th><th>Unit price</th><th class="cp-number">Line total</th></tr></thead>
            <tbody>
                @forelse($invoice->items as $item)
                    <tr>
                        <td><strong>{{ $item->product_name }}</strong>@if($item->description)<span class="cp-table-sub">{{ $item->description }}</span>@endif</td>
                        <td>{{ $item->quantity }} {{ $item->unit }}</td>
                        <td>{{ \App\Helpers\CommonHelper::amount($item->unit_price, $invoice->currency) }}</td>
                        <td class="cp-number"><strong>{{ \App\Helpers\CommonHelper::amount($item->line_total, $invoice->currency) }}</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="4"><div class="cp-empty">No line items were attached to this invoice.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="cp-invoice-total-row"><span>Subtotal {{ $invoice->currency }}</span><strong>{{ \App\Helpers\CommonHelper::amount($invoice->subtotal, $invoice->currency) }}</strong></div>
    <div class="cp-invoice-total-row"><span>Tax and other charges</span><strong>{{ \App\Helpers\CommonHelper::amount($invoice->total_amount - $invoice->subtotal + $invoice->round_off, $invoice->currency) }}</strong></div>
    <div class="cp-invoice-total-row is-grand"><span>Total due</span><strong>{{ \App\Helpers\CommonHelper::amount($invoice->total_amount, $invoice->currency) }}</strong></div>
</section>

<div class="cp-grid-2 cp-invoice-detail-grid">
    <section class="cp-card cp-invoice-detail-card">
        <p class="cp-eyebrow">Invoice context</p><h2>Dates & notes</h2>
        <dl class="cp-detail-list">
            <div><dt>Invoice date</dt><dd>{{ optional($invoice->invoice_date)->format('d M Y') ?: '—' }}</dd></div>
            <div><dt>Due date</dt><dd>{{ optional($invoice->due_date)->format('d M Y') ?: '—' }}</dd></div>
            @if($invoice->po_number)<div><dt>Purchase order</dt><dd>{{ $invoice->po_number }}</dd></div>@endif
        </dl>
        @if($invoice->notes)<div class="cp-invoice-notes"><strong>Note from MissPack</strong><p>{{ $invoice->notes }}</p></div>@endif
        @if($invoice->publicAttachments->isNotEmpty())
            <div class="cp-invoice-attachments"><strong>Shared attachments</strong>@foreach($invoice->publicAttachments as $attachment)<a href="{{ route('client-portal.invoices.sales.attachments.file', [$invoice->id, $attachment->id]) }}" target="_blank" rel="noopener"><i class="fa-solid fa-paperclip"></i> {{ $attachment->title ?: $attachment->original_name }}</a>@endforeach</div>
        @endif
    </section>

    <section class="cp-card cp-invoice-detail-card">
        <p class="cp-eyebrow">Discussion</p><h2>Invoice comments</h2>
        <form method="POST" action="{{ route('client-portal.invoices.sales.comments.store', $invoice) }}" class="cp-inline-form">
            @csrf
            <div class="master-field"><label class="master-label" for="sales-invoice-comment">Ask about this invoice</label><textarea class="master-textarea" id="sales-invoice-comment" name="body" rows="3" maxlength="4000" required></textarea></div>
            <button class="master-btn master-btn-primary" type="submit">Send comment</button>
        </form>
        <div class="cp-comment-list">
            @forelse($comments as $comment)
                <article class="cp-comment"><div class="cp-comment-head"><strong>{{ $comment->authorName() }}</strong><time>{{ $comment->created_at->format('d M Y, h:i A') }}</time></div><p>{{ $comment->body }}</p></article>
            @empty
                <div class="cp-empty">No comments on this invoice yet.</div>
            @endforelse
        </div>
    </section>
</div>
@endsection
