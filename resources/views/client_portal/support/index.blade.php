@extends('client_portal.layouts.app')

@section('title', 'Support')
@section('page-title', 'Support desk')

@section('content')
<div class="cp-page-head">
    <div>
        <p class="cp-eyebrow">Your MissPack team</p>
        <h1>Support desk</h1>
        <p>Keep project, shipment, billing and account questions together in one private thread.</p>
    </div>
    <div class="cp-support-head-stat"><strong>{{ $conversations->total() }}</strong><span>requests</span></div>
</div>

<div class="cp-support-grid">
    <section class="cp-card cp-support-compose">
        <div class="cp-section-heading">
            <div><p class="cp-eyebrow">Start a conversation</p><h2>How can we help?</h2></div>
            <span class="cp-support-icon"><i class="fa-regular fa-paper-plane"></i></span>
        </div>
        <form method="POST" action="{{ route('client-portal.support.store') }}" class="cp-form-grid">
            @csrf
            <div class="master-field">
                <label class="master-label" for="support-subject">Subject</label>
                <input class="master-input" id="support-subject" name="subject" value="{{ old('subject') }}" maxlength="180" required placeholder="A short summary">
            </div>
            <div class="cp-form-row">
                <div class="master-field">
                    <label class="master-label" for="support-category">Topic</label>
                    <select class="master-select" id="support-category" name="category" required>
                        @foreach($categoryOptions as $key => $label)
                            <option value="{{ $key }}" @selected(old('category', 'general') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="master-field">
                    <label class="master-label" for="support-priority">Priority</label>
                    <select class="master-select" id="support-priority" name="priority" required>
                        @foreach($priorityOptions as $key => $label)
                            <option value="{{ $key }}" @selected(old('priority', 'normal') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="master-field">
                <label class="master-label" for="support-message">Message</label>
                <textarea class="master-textarea" id="support-message" name="message" rows="5" maxlength="5000" required placeholder="Add the details that will help us respond.">{{ old('message') }}</textarea>
            </div>
            <div class="cp-support-form-foot">
                <span class="cp-muted"><i class="fa-solid fa-lock"></i> Visible only to your client account and MissPack support.</span>
                <button class="master-btn master-btn-primary" type="submit">Send request <i class="fa-solid fa-arrow-right"></i></button>
            </div>
        </form>
    </section>

    <section class="cp-card cp-support-list">
        <div class="cp-section-heading">
            <div><p class="cp-eyebrow">Shared inbox</p><h2>Your requests</h2></div>
            <span class="cp-support-count">{{ $conversations->total() }}</span>
        </div>
        <form method="GET" class="cp-support-filters">
            <select class="master-select" name="status" aria-label="Filter support by status">
                <option value="all">All statuses</option>
                @foreach($statusOptions as $key => $label)
                    <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <select class="master-select" name="category" aria-label="Filter support by topic">
                <option value="all">All topics</option>
                @foreach($categoryOptions as $key => $label)
                    <option value="{{ $key }}" @selected($category === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="master-btn master-btn-soft master-btn-sm" type="submit">Filter</button>
        </form>

        <div class="cp-support-thread-list">
            @forelse($conversations as $conversation)
                <a class="cp-support-thread" href="{{ route('client-portal.support.show', $conversation->id) }}">
                    <div class="cp-support-thread-mark"><i class="fa-regular fa-comments"></i></div>
                    <div class="cp-support-thread-copy">
                        <div class="cp-support-thread-title"><strong>{{ $conversation->subject }}</strong>@if($conversation->unread_reply_count)<span class="cp-unread-dot" title="New reply"></span>@endif</div>
                        <div class="cp-support-thread-meta"><span>{{ $conversation->categoryLabel() }}</span><span>·</span><span>{{ $conversation->messages_count }} {{ \Illuminate\Support\Str::plural('message', $conversation->messages_count) }}</span></div>
                    </div>
                    <div class="cp-support-thread-right">
                        <span class="cp-status-pill cp-status-{{ $conversation->status }}">{{ $conversation->statusLabel() }}</span>
                        <small>{{ optional($conversation->last_message_at)->diffForHumans() ?: $conversation->created_at->diffForHumans() }}</small>
                    </div>
                </a>
            @empty
                <div class="cp-empty cp-empty-spacious"><i class="fa-regular fa-message"></i><strong>Your support inbox is ready</strong><span>Send your first request using the form. Replies will appear here.</span></div>
            @endforelse
        </div>
        <div class="cp-pagination">{{ $conversations->links() }}</div>
    </section>
</div>
@endsection
