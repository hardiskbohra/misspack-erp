@extends('layouts.app')

@section('title', 'Portal Support Request')

@section('content')
<div class="cp-office-support">
    <div class="master-header">
        <div>
            <p class="master-eyebrow">{{ $client->company_name }} · {{ $conversation->categoryLabel() }}</p>
            <h1>{{ $conversation->subject }}</h1>
            <p>Opened by {{ $conversation->portalUser?->displayName() ?: 'Client team' }} · {{ $conversation->created_at->format('d M Y, h:i A') }}</p>
        </div>
        <div class="master-actions">
            <a class="master-btn master-btn-light" href="{{ route('clients.portal.support.index', $client) }}"><i class="fa-solid fa-arrow-left"></i> Inbox</a>
            <form method="POST" action="{{ route('clients.portal.support.status', [$client, $conversation->id]) }}" class="cp-office-status-form">
                @csrf @method('PATCH')
                <select class="master-select" name="status" aria-label="Update request status">
                    @foreach($statusOptions as $key => $label)
                        <option value="{{ $key }}" @selected($conversation->status === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="master-btn master-btn-primary" type="submit">Update</button>
            </form>
        </div>
    </div>

    <div class="cp-office-support-detail">
        <section class="master-card cp-office-thread">
            <div class="cp-office-thread-head">
                <div><span class="cp-office-status cp-office-status-{{ $conversation->status }}">{{ $conversation->statusLabel() }}</span><span class="cp-office-priority">{{ $conversation->priorityLabel() }} priority</span></div>
                <small>{{ $conversation->messages->count() }} {{ \Illuminate\Support\Str::plural('message', $conversation->messages->count()) }}</small>
            </div>
            @foreach($conversation->messages as $message)
                <article class="cp-office-message {{ $message->sender_type === 'staff' ? 'is-staff' : 'is-client' }}">
                    <div class="cp-office-message-avatar">{{ strtoupper(substr($message->senderName(), 0, 1)) }}</div>
                    <div class="cp-office-message-content">
                        <div class="cp-office-message-meta"><strong>{{ $message->senderName() }}</strong><time>{{ $message->created_at->format('d M Y, h:i A') }}</time></div>
                        <p>{{ $message->body }}</p>
                    </div>
                </article>
            @endforeach
            <form method="POST" action="{{ route('clients.portal.support.reply', [$client, $conversation->id]) }}" class="cp-office-reply-form">
                @csrf
                <label class="master-label" for="staff-reply">Reply as {{ auth()->user()->name ?? 'MissPack Team' }}</label>
                <textarea class="master-textarea" id="staff-reply" name="message" rows="5" maxlength="5000" required placeholder="Write a helpful reply…">{{ old('message') }}</textarea>
                <div class="cp-office-reply-foot"><span>Sending a reply notifies active portal users for this client.</span><button class="master-btn master-btn-primary" type="submit">Send reply</button></div>
            </form>
        </section>

        <aside class="master-card cp-office-thread-aside">
            <p class="master-eyebrow">Client request</p>
            <h2>Details</h2>
            <dl>
                <div><dt>Client</dt><dd>{{ $client->company_name }}</dd></div>
                <div><dt>Portal user</dt><dd>{{ $conversation->portalUser?->displayName() ?: 'Client team' }}</dd></div>
                <div><dt>Topic</dt><dd>{{ $conversation->categoryLabel() }}</dd></div>
                <div><dt>Priority</dt><dd>{{ $conversation->priorityLabel() }}</dd></div>
                <div><dt>Created</dt><dd>{{ $conversation->created_at->format('d M Y') }}</dd></div>
                <div><dt>Last update</dt><dd>{{ optional($conversation->last_message_at)->format('d M Y, h:i A') ?: '—' }}</dd></div>
            </dl>
            <a class="master-btn master-btn-light" href="{{ route('clients.portal.show', $client) }}">Manage portal access</a>
        </aside>
    </div>
</div>

@push('styles')
<link rel="stylesheet" href="{{ $assetVer('assets/css/client-portal-workspace.css') }}">
@endpush
@endsection
