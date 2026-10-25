@extends('client_portal.layouts.app')

@section('title', 'Notifications')
@section('page-title', 'Notifications')

@section('content')
<div class="cp-page-head">
    <div><p class="cp-eyebrow">Workspace activity</p><h1>Notifications</h1><p>Project, delivery, billing and account updates shared by the MissPack team.</p></div>
    @if($notifications->where('is_read', false)->isNotEmpty())
        <form method="POST" action="{{ route('client-portal.notifications.readAll') }}">@csrf @method('PATCH')<button class="master-btn master-btn-soft" type="submit"><i class="fa-solid fa-check-double"></i> Mark all read</button></form>
    @endif
</div>

<section class="cp-card cp-notification-centre">
    <div class="cp-section-heading cp-section-heading-padded">
        <div><p class="cp-eyebrow">Inbox</p><h2>All updates</h2></div>
        <span class="cp-support-count">{{ $notifications->total() }}</span>
    </div>
    <div class="cp-notification-feed">
        @forelse($notifications as $notification)
            <article class="cp-notification-row {{ $notification->is_read ? '' : 'is-unread' }}">
                <span class="cp-notification-row-icon"><i class="fa-regular fa-bell"></i></span>
                <div class="cp-notification-row-copy">
                    <div class="cp-notification-row-head"><strong>{{ $notification->title }}</strong><time>{{ $notification->created_at->diffForHumans() }}</time></div>
                    <p>{{ $notification->message ?: 'A new update is available in your workspace.' }}</p>
                    <div class="cp-row-actions">
                        @if ($notification->action_url)<a class="master-btn master-btn-soft master-btn-sm" href="{{ $notification->action_url }}">Open update <i class="fa-solid fa-arrow-right"></i></a>@endif
                        @if (!$notification->is_read)
                            <form method="POST" action="{{ route('client-portal.notifications.read', $notification) }}">@csrf @method('PATCH')<button class="master-btn master-btn-light master-btn-sm" type="submit">Mark read</button></form>
                        @endif
                    </div>
                </div>
                @if(!$notification->is_read)<span class="cp-unread-indicator" aria-label="Unread"></span>@endif
            </article>
        @empty
            <div class="cp-empty cp-empty-spacious"><i class="fa-regular fa-bell-slash"></i><strong>You are all caught up</strong><span>New portal updates will appear here.</span></div>
        @endforelse
    </div>
    <div class="cp-pagination">{{ $notifications->links() }}</div>
</section>
@endsection
