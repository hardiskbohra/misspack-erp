@extends('client_portal.layouts.app')

@section('title', 'Notifications')
@section('page-title', 'Notifications')

@section('content')
    <div class="master-header" style="padding:5px;line-height:1;">
        <div>
            <h1>Notifications</h1>
            <p style="font-size:16px;font-weight:500;">All client portal updates from MissPack.</p>
        </div>
        <div class="master-actions">
            <form method="POST" action="{{ route('client-portal.notifications.readAll') }}">@csrf @method('PATCH')<button
                class="cp-btn cp-btn-primary">Mark All Read</button></form>
        </div>
    </div>
    <div class="cp-card" style="padding:20px;">
        @forelse($notifications as $notification)
            <div class="cp-comment" style="background:{{ $notification->is_read ? '#fff' : '#f8fbff' }};">
                <div class="cp-comment-head">
                    <strong>{{ $notification->title }}</strong><span>{{ $notification->created_at->format('d M Y, h:i A') }}</span>
                </div>
                <p>{{ $notification->message }}</p>
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;">
                    @if ($notification->action_url)
                        <a class="cp-btn cp-btn-soft cp-btn-sm" href="{{ $notification->action_url }}">Open</a>
                    @endif
                    @if (!$notification->is_read)
                        <form method="POST" action="{{ route('client-portal.notifications.read', $notification) }}">
                            @csrf @method('PATCH')<button class="cp-btn cp-btn-light cp-btn-sm">Mark Read</button>
                        </form>
                    @endif
                    <span class="cp-badge status-{{ $notification->is_read ? 'active' : 'pending' }}">
                        {{ $notification->is_read ? 'Read' : 'Unread' }}</span>
                </div>
            </div>@empty<div class="cp-empty">No notifications yet.</div>
            @endforelse
            <div class="cp-pagination">{{ $notifications->links() }}</div>
        </div>
    @endsection
