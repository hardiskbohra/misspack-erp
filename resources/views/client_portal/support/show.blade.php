@extends('client_portal.layouts.app')

@section('title', $conversation->subject)
@section('page-title', 'Support request')

@section('content')
<div class="cp-page-head">
    <div>
        <a class="cp-back-link" href="{{ route('client-portal.support.index') }}"><i class="fa-solid fa-arrow-left"></i> Support inbox</a>
        <p class="cp-eyebrow">{{ $conversation->categoryLabel() }} · {{ $conversation->priorityLabel() }} priority</p>
        <h1>{{ $conversation->subject }}</h1>
        <p>Opened {{ $conversation->created_at->format('d M Y, h:i A') }}</p>
    </div>
    <div class="cp-support-detail-actions">
        <span class="cp-status-pill cp-status-{{ $conversation->status }}">{{ $conversation->statusLabel() }}</span>
        @if($conversation->status !== 'resolved')
            <form method="POST" action="{{ route('client-portal.support.close', $conversation->id) }}" data-confirm="Mark this request as resolved?">@csrf @method('PATCH')<button class="master-btn master-btn-soft" type="submit">Mark resolved</button></form>
        @else
            <form method="POST" action="{{ route('client-portal.support.reopen', $conversation->id) }}">@csrf @method('PATCH')<button class="master-btn master-btn-soft" type="submit">Reopen request</button></form>
        @endif
    </div>
</div>

<div class="cp-support-conversation-layout">
    <section class="cp-card cp-support-thread-detail">
        <div class="cp-thread-intro">
            <div class="cp-support-icon"><i class="fa-regular fa-comments"></i></div>
            <div><strong>Conversation</strong><span>Messages are shared with your client portal team.</span></div>
        </div>
        <div class="cp-message-list">
            @forelse($conversation->messages as $message)
                <article class="cp-message {{ $message->sender_type === 'client' ? 'cp-message-client' : 'cp-message-staff' }}">
                    <div class="cp-message-avatar">{{ strtoupper(substr($message->senderName(), 0, 1)) }}</div>
                    <div class="cp-message-body">
                        <div class="cp-message-meta"><strong>{{ $message->sender_type === 'staff' ? 'MissPack Support' : $message->senderName() }}</strong><time>{{ $message->created_at->format('d M Y, h:i A') }}</time></div>
                        <p>{{ $message->body }}</p>
                    </div>
                </article>
            @empty
                <div class="cp-empty">No messages yet.</div>
            @endforelse
        </div>

        @if($conversation->status !== 'resolved')
            <form method="POST" action="{{ route('client-portal.support.messages.store', $conversation->id) }}" class="cp-support-reply-form">
                @csrf
                <label class="master-label" for="support-reply">Add a message</label>
                <textarea class="master-textarea" id="support-reply" name="message" rows="4" maxlength="5000" required placeholder="Write a reply or add more context…"></textarea>
                <div class="cp-support-form-foot"><span class="cp-muted">Your message is private to this support thread.</span><button class="master-btn master-btn-primary" type="submit">Send message</button></div>
            </form>
        @else
            <div class="cp-support-resolved-note"><i class="fa-solid fa-circle-check"></i> This request is resolved. Reopen it if you need more help.</div>
        @endif
    </section>

    <aside class="cp-card cp-support-about">
        <p class="cp-eyebrow">Request details</p>
        <h2>At a glance</h2>
        <dl>
            <div><dt>Status</dt><dd>{{ $conversation->statusLabel() }}</dd></div>
            <div><dt>Topic</dt><dd>{{ $conversation->categoryLabel() }}</dd></div>
            <div><dt>Priority</dt><dd>{{ $conversation->priorityLabel() }}</dd></div>
            <div><dt>Started by</dt><dd>{{ $conversation->portalUser?->displayName() ?: $portalUser->displayName() }}</dd></div>
            <div><dt>Last updated</dt><dd>{{ optional($conversation->last_message_at)->format('d M Y, h:i A') ?: '—' }}</dd></div>
        </dl>
        <div class="cp-support-private-note"><i class="fa-solid fa-shield-halved"></i><span>Only signed-in members of your client account and MissPack support can view this thread.</span></div>
    </aside>
</div>
@endsection
