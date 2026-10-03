@extends('client_portal.layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Workspace overview')

@section('content')
<div class="cp-dashboard-hero">
    <div class="cp-dashboard-hero-copy">
        <span class="cp-hero-kicker"><i class="fa-solid fa-sparkles"></i> Your MissPack workspace</span>
        <h1>Welcome back, {{ $portalUser->displayName() }}.</h1>
        <p>{{ $client->company_name }} · Your projects, deliveries and billing updates, all together.</p>
        <div class="cp-hero-actions">
            <a class="cp-hero-primary" href="{{ route('client-portal.projects.index') }}">Explore projects <i class="fa-solid fa-arrow-right"></i></a>
            <a class="cp-hero-secondary" href="{{ route('client-portal.support.index') }}"><i class="fa-regular fa-message"></i> Contact support</a>
        </div>
    </div>
    <div class="cp-dashboard-hero-art" aria-hidden="true">
        <div class="cp-hero-orbit cp-orbit-one"></div><div class="cp-hero-orbit cp-orbit-two"></div>
        <div class="cp-hero-art-core"><i class="fa-solid fa-cubes-stacked"></i></div>
        <span class="cp-hero-float cp-float-a"><i class="fa-solid fa-box"></i></span>
        <span class="cp-hero-float cp-float-b"><i class="fa-solid fa-truck-fast"></i></span>
        <span class="cp-hero-float cp-float-c"><i class="fa-solid fa-file-invoice-dollar"></i></span>
    </div>
</div>

<div class="cp-dashboard-stat-grid">
    <a class="cp-dashboard-stat stat-blue" href="{{ route('client-portal.projects.index') }}"><span class="cp-dashboard-stat-icon"><i class="fa-solid fa-briefcase"></i></span><span class="cp-dashboard-stat-copy"><small>Published projects</small><strong>{{ $stats['projects'] }}</strong></span><i class="fa-solid fa-arrow-up-right-from-square cp-stat-arrow"></i></a>
    <a class="cp-dashboard-stat stat-violet" href="{{ route('client-portal.shipments.index') }}"><span class="cp-dashboard-stat-icon"><i class="fa-solid fa-truck-fast"></i></span><span class="cp-dashboard-stat-copy"><small>Published shipments</small><strong>{{ $stats['shipments'] }}</strong></span><i class="fa-solid fa-arrow-up-right-from-square cp-stat-arrow"></i></a>
    <a class="cp-dashboard-stat stat-teal" href="{{ route('client-portal.invoices.index') }}"><span class="cp-dashboard-stat-icon"><i class="fa-solid fa-file-invoice-dollar"></i></span><span class="cp-dashboard-stat-copy"><small>Available invoices</small><strong>{{ $stats['invoices'] }}</strong></span><i class="fa-solid fa-arrow-up-right-from-square cp-stat-arrow"></i></a>
    <a class="cp-dashboard-stat stat-orange" href="{{ route('client-portal.payments.index') }}"><span class="cp-dashboard-stat-icon"><i class="fa-solid fa-indian-rupee-sign"></i></span><span class="cp-dashboard-stat-copy"><small>Shared receipts</small><strong>{{ $stats['payments'] }}</strong></span><i class="fa-solid fa-arrow-up-right-from-square cp-stat-arrow"></i></a>
    <a class="cp-dashboard-stat stat-pink" href="{{ route('client-portal.notifications.index') }}"><span class="cp-dashboard-stat-icon"><i class="fa-solid fa-bell"></i></span><span class="cp-dashboard-stat-copy"><small>Unread updates</small><strong>{{ $stats['unread_notifications'] }}</strong></span><i class="fa-solid fa-arrow-up-right-from-square cp-stat-arrow"></i></a>
</div>

<div class="cp-dashboard-columns">
    <section class="cp-card cp-dashboard-panel cp-dashboard-projects">
        <div class="cp-section-heading"><div><p class="cp-eyebrow">Delivery</p><h2>Active projects</h2></div><a class="cp-text-link" href="{{ route('client-portal.projects.index') }}">View all <i class="fa-solid fa-arrow-right"></i></a></div>
        <div class="cp-dashboard-record-list">
            @forelse($projects as $project)
                <a class="cp-dashboard-record" href="{{ route('client-portal.projects.show', $project) }}">
                    <span class="cp-record-symbol"><i class="fa-solid fa-cubes-stacked"></i></span>
                    <span class="cp-dashboard-record-copy"><strong>{{ $project->name }}</strong><small>{{ $project->project_number }} · {{ $project->stageLabel() }}</small><span class="cp-progress-track"><span style="width:{{ (int) $project->progress_percent }}%"></span></span></span>
                    <span class="cp-record-percent">{{ (int) $project->progress_percent }}%</span>
                </a>
            @empty
                <div class="cp-empty cp-empty-spacious">No projects have been published to your workspace yet.</div>
            @endforelse
        </div>
    </section>

    <section class="cp-card cp-dashboard-panel">
        <div class="cp-section-heading"><div><p class="cp-eyebrow">Logistics</p><h2>Recent shipments</h2></div><a class="cp-text-link" href="{{ route('client-portal.shipments.index') }}">View all <i class="fa-solid fa-arrow-right"></i></a></div>
        <div class="cp-dashboard-record-list">
            @forelse($shipments as $shipment)
                <a class="cp-dashboard-record" href="{{ route('client-portal.shipments.show', $shipment) }}">
                    <span class="cp-record-symbol record-coral"><i class="fa-solid fa-box"></i></span>
                    <span class="cp-dashboard-record-copy"><strong>{{ $shipment->shipment_number }}</strong><small>{{ $shipment->from_city ?: 'Origin' }} <i class="fa-solid fa-arrow-right-long"></i> {{ $shipment->to_city ?: 'Destination' }}</small></span>
                    <span class="cp-record-status">{{ $shipment->statusLabel() }}</span>
                </a>
            @empty
                <div class="cp-empty cp-empty-spacious">No shipments have been shared yet.</div>
            @endforelse
        </div>
    </section>

    <section class="cp-card cp-dashboard-panel cp-dashboard-invoices">
        <div class="cp-section-heading"><div><p class="cp-eyebrow">Billing</p><h2>Latest invoices</h2></div><a class="cp-text-link" href="{{ route('client-portal.invoices.index') }}">Billing centre <i class="fa-solid fa-arrow-right"></i></a></div>
        <div class="cp-dashboard-record-list">
            @forelse($salesInvoices as $invoice)
                <a class="cp-dashboard-record" href="{{ route('client-portal.invoices.sales.show', $invoice) }}">
                    <span class="cp-record-symbol record-mint"><i class="fa-solid fa-file-invoice"></i></span>
                    <span class="cp-dashboard-record-copy"><strong>{{ $invoice->invoice_number }}</strong><small>{{ optional($invoice->invoice_date)->format('d M Y') ?: 'Date not set' }} · {{ $invoice->clientPortalStateLabel() }}</small></span>
                    <strong class="cp-record-amount">{{ \App\Helpers\CommonHelper::amount($invoice->clientPortalBalanceDue(), $invoice->currency) }}</strong>
                </a>
            @empty
                @if($legacyInvoices->isEmpty())
                    <div class="cp-empty cp-empty-spacious">No invoices have been published yet.</div>
                @endif
            @endforelse
            @foreach($legacyInvoices as $invoice)
                <a class="cp-dashboard-record" href="{{ route('client-portal.invoices.show', $invoice) }}">
                    <span class="cp-record-symbol record-mint"><i class="fa-solid fa-file-invoice"></i></span>
                    <span class="cp-dashboard-record-copy"><strong>{{ $invoice->invoice_number }} <small class="cp-legacy-tag">Previous</small></strong><small>{{ optional($invoice->invoice_date)->format('d M Y') ?: 'Date not set' }} · {{ $invoice->statusLabel() }}</small></span>
                    <strong class="cp-record-amount">{{ \App\Helpers\CommonHelper::amount($invoice->outstandingAmount(), $invoice->currency) }}</strong>
                </a>
            @endforeach
        </div>
    </section>

    <section class="cp-card cp-dashboard-panel cp-dashboard-support">
        <div class="cp-section-heading"><div><p class="cp-eyebrow">We're here to help</p><h2>Support inbox</h2></div><a class="cp-text-link" href="{{ route('client-portal.support.index') }}">Open inbox <i class="fa-solid fa-arrow-right"></i></a></div>
        <div class="cp-support-summary">
            <div><strong>{{ $stats['support_open'] }}</strong><span>requests in progress</span></div>
            <div><strong>{{ $stats['support_unread'] }}</strong><span>new replies</span></div>
            <a class="master-btn master-btn-primary" href="{{ route('client-portal.support.index') }}"><i class="fa-regular fa-paper-plane"></i> Start a request</a>
        </div>
        @forelse($supportConversations as $conversation)
            <a class="cp-mini-support-thread" href="{{ route('client-portal.support.show', $conversation->id) }}"><span><strong>{{ $conversation->subject }}</strong><small>{{ optional($conversation->last_message_at)->diffForHumans() ?: 'Recently opened' }}</small></span><span class="cp-status-pill cp-status-{{ $conversation->status }}">{{ $conversation->statusLabel() }}</span></a>
        @empty
            <div class="cp-dashboard-hint">Start a private conversation with the MissPack team whenever you need help.</div>
        @endforelse
    </section>

    <section class="cp-card cp-dashboard-panel cp-dashboard-notifications">
        <div class="cp-section-heading"><div><p class="cp-eyebrow">What's new</p><h2>Recent updates</h2></div><a class="cp-text-link" href="{{ route('client-portal.notifications.index') }}">All updates <i class="fa-solid fa-arrow-right"></i></a></div>
        <div class="cp-notification-list">
            @forelse($notifications as $notification)
                <div class="cp-dashboard-notification {{ $notification->is_read ? '' : 'is-unread' }}"><span class="cp-notification-mark"><i class="fa-solid fa-bell"></i></span><div><strong>{{ $notification->title }}</strong><p>{{ \Illuminate\Support\Str::limit($notification->message ?: 'There is a new update in your workspace.', 120) }}</p><time>{{ $notification->created_at->diffForHumans() }}</time></div>@if($notification->action_url)<a class="cp-notification-open" href="{{ $notification->action_url }}" aria-label="Open update"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>@endif</div>
            @empty
                <div class="cp-empty cp-empty-spacious">You are all caught up. New updates will appear here.</div>
            @endforelse
        </div>
    </section>
</div>
@endsection
