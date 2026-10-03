@extends('client_portal.layouts.app')

@section('title', $invoice->invoice_number)
@section('page-title', 'Invoice details')

@section('content')
<div class="cp-page-head">
    <div>
        <a class="cp-back-link" href="{{ route('client-portal.invoices.index') }}"><i class="fa-solid fa-arrow-left"></i> Invoices</a>
        <p class="cp-eyebrow">Legacy portal invoice</p>
        <h1>{{ $invoice->invoice_number }}</h1>
        <p>{{ $invoice->title ?: 'Invoice record' }} · {{ $invoice->statusLabel() }}</p>
    </div>
    <div class="cp-support-detail-actions">
        @if($invoice->file_path)
            <a href="{{ route('client-portal.invoices.file', $invoice) }}" class="master-btn master-btn-primary"><i class="fa-solid fa-download"></i> Download file</a>
        @endif
        <a href="{{ route('client-portal.invoices.index') }}" class="master-btn master-btn-light">Back to invoices</a>
    </div>
</div>

<div class="cp-grid-4 cp-invoice-metrics">
    <div class="cp-card cp-stat"><span>Total</span><strong>{{ \App\Helpers\CommonHelper::amount($invoice->total_amount, $invoice->currency) }}</strong></div>
    <div class="cp-card cp-stat"><span>Paid</span><strong>{{ \App\Helpers\CommonHelper::amount($invoice->paid_amount, $invoice->currency) }}</strong></div>
    <div class="cp-card cp-stat"><span>Outstanding</span><strong>{{ \App\Helpers\CommonHelper::amount($invoice->outstandingAmount(), $invoice->currency) }}</strong></div>
    <div class="cp-card cp-stat"><span>State</span><strong>{{ $invoice->statusLabel() }}</strong></div>
</div>

<div class="cp-grid-2 cp-invoice-detail-grid">
    <section class="cp-card cp-invoice-detail-card">
        <p class="cp-eyebrow">Record details</p><h2>Invoice information</h2>
        <dl class="cp-detail-list">
            <div><dt>Invoice date</dt><dd>{{ optional($invoice->invoice_date)->format('d M Y') ?: '—' }}</dd></div>
            <div><dt>Due date</dt><dd>{{ optional($invoice->due_date)->format('d M Y') ?: '—' }}</dd></div>
            <div><dt>Subtotal</dt><dd>{{ \App\Helpers\CommonHelper::amount($invoice->subtotal, $invoice->currency) }}</dd></div>
            <div><dt>Tax</dt><dd>{{ \App\Helpers\CommonHelper::amount($invoice->tax_amount, $invoice->currency) }}</dd></div>
        </dl>
        @if($invoice->notes)<div class="cp-invoice-notes"><strong>Note</strong><p>{{ $invoice->notes }}</p></div>@endif
    </section>

    <section class="cp-card cp-invoice-detail-card">
        <p class="cp-eyebrow">Discussion</p><h2>Invoice comments</h2>
        <form method="POST" action="{{ route('client-portal.invoices.comments.store', $invoice) }}" class="cp-inline-form">
            @csrf
            <div class="master-field"><label class="master-label" for="invoice-comment">Add a question or note</label><textarea class="master-textarea" id="invoice-comment" name="body" rows="3" maxlength="4000" required></textarea></div>
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
