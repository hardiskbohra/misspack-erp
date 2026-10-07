@extends('client_portal.layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Overview')

@section('content')
<div class="cp-welcome-row">
    <div>
        <p class="cp-eyebrow">{{ now()->format('l, d M') }}</p>
        <h1>Welcome back, {{ $portalUser->displayName() }}</h1>
        <p>Here is the latest activity for {{ $client->company_name }}.</p>
    </div>
    <div class="cp-welcome-actions">
        <a class="master-btn master-btn-soft" href="{{ route('client-portal.attachments.index') }}"><i class="fa-solid fa-cloud-arrow-up"></i> Upload a file</a>
        <a class="master-btn master-btn-primary" href="{{ route('client-portal.support.index') }}"><i class="fa-regular fa-message"></i> Ask for help</a>
    </div>
</div>

<div class="cp-dashboard-stat-grid">
    <a class="cp-dashboard-stat stat-blue" href="{{ route('client-portal.projects.index') }}">
        <span class="cp-dashboard-stat-icon"><i class="fa-solid fa-briefcase"></i></span>
        <span class="cp-dashboard-stat-copy"><small>Published projects</small><strong>{{ $stats['projects'] }}</strong></span>
        <i class="fa-solid fa-arrow-right cp-stat-arrow"></i>
    </a>
    <a class="cp-dashboard-stat stat-violet" href="{{ route('client-portal.shipments.index') }}">
        <span class="cp-dashboard-stat-icon"><i class="fa-solid fa-truck-fast"></i></span>
        <span class="cp-dashboard-stat-copy"><small>Trackable shipments</small><strong>{{ $stats['shipments'] }}</strong></span>
        <i class="fa-solid fa-arrow-right cp-stat-arrow"></i>
    </a>
    <a class="cp-dashboard-stat stat-teal" href="{{ route('client-portal.invoices.index') }}">
        <span class="cp-dashboard-stat-icon"><i class="fa-solid fa-file-invoice-dollar"></i></span>
        <span class="cp-dashboard-stat-copy">
            <small>{{ $outstandingByCurrency->count() > 1 ? 'Open invoice balances' : 'Balance due' }}</small>
            <strong>
                @if($outstandingByCurrency->count() === 1)
                    {{ \App\Helpers\CommonHelper::amount($outstandingByCurrency->first()['amount'], $outstandingByCurrency->first()['currency']) }}
                @elseif($outstandingByCurrency->count() > 1)
                    {{ $outstandingByCurrency->sum('count') }} invoices
                @else
                    All clear
                @endif
            </strong>
        </span>
        <i class="fa-solid fa-arrow-right cp-stat-arrow"></i>
    </a>
    <a class="cp-dashboard-stat stat-orange" href="{{ route('client-portal.notifications.index') }}">
        <span class="cp-dashboard-stat-icon"><i class="fa-regular fa-bell"></i></span>
        <span class="cp-dashboard-stat-copy"><small>Updates to review</small><strong>{{ $stats['unread_notifications'] }}</strong></span>
        <i class="fa-solid fa-arrow-right cp-stat-arrow"></i>
    </a>
</div>

<div class="cp-dashboard-layout">
    <div class="cp-dashboard-main">
        <section class="cp-card cp-dashboard-panel">
            <div class="cp-section-heading">
                <div><p class="cp-eyebrow">Work in progress</p><h2>Projects</h2></div>
                <a class="cp-text-link" href="{{ route('client-portal.projects.index') }}">View all <i class="fa-solid fa-arrow-right"></i></a>
            </div>
            <div class="cp-dashboard-record-list">
                @forelse($projects as $project)
                    <a class="cp-dashboard-record" href="{{ route('client-portal.projects.show', $project) }}">
                        <span class="cp-record-symbol"><i class="fa-solid fa-cubes-stacked"></i></span>
                        <span class="cp-dashboard-record-copy">
                            <strong>{{ $project->name }}</strong>
                            <small>{{ $project->project_number }} · {{ $project->stageLabel() }}</small>
                            <span class="cp-progress-track" aria-label="{{ (int) $project->progress_percent }} percent complete"><span style="width:{{ (int) $project->progress_percent }}%"></span></span>
                        </span>
                        <span class="cp-record-percent">{{ (int) $project->progress_percent }}%</span>
                    </a>
                @empty
                    <div class="cp-empty cp-empty-spacious"><i class="fa-regular fa-folder-open"></i><strong>No published projects yet</strong><span>Projects shared by MissPack will appear here.</span></div>
                @endforelse
            </div>
        </section>

        <section class="cp-card cp-dashboard-panel">
            <div class="cp-section-heading">
                <div><p class="cp-eyebrow">Billing</p><h2>Recent invoices</h2></div>
                <a class="cp-text-link" href="{{ route('client-portal.invoices.index') }}">Billing centre <i class="fa-solid fa-arrow-right"></i></a>
            </div>
            <div class="cp-dashboard-record-list">
                @forelse($salesInvoices as $invoice)
                    <a class="cp-dashboard-record" href="{{ route('client-portal.invoices.sales.show', $invoice) }}">
                        <span class="cp-record-symbol record-mint"><i class="fa-solid fa-file-invoice"></i></span>
                        <span class="cp-dashboard-record-copy"><strong>{{ $invoice->invoice_number }}</strong><small>{{ optional($invoice->due_date)->format('d M Y') ? 'Due '.optional($invoice->due_date)->format('d M Y') : 'No due date' }} · {{ $invoice->clientPortalStateLabel() }}</small></span>
                        <strong class="cp-record-amount">{{ \App\Helpers\CommonHelper::amount($invoice->clientPortalBalanceDue(), $invoice->currency) }}</strong>
                    </a>
                @empty
                    <div class="cp-empty cp-empty-spacious"><i class="fa-regular fa-file-lines"></i><strong>No invoices published</strong><span>Shared billing documents will appear here.</span></div>
                @endforelse
            </div>
        </section>

        <section class="cp-card cp-dashboard-panel">
            <div class="cp-section-heading">
                <div><p class="cp-eyebrow">Logistics</p><h2>Recent shipments</h2></div>
                <a class="cp-text-link" href="{{ route('client-portal.shipments.index') }}">Track all <i class="fa-solid fa-arrow-right"></i></a>
            </div>
            <div class="cp-dashboard-record-list">
                @forelse($shipments as $shipment)
                    <a class="cp-dashboard-record" href="{{ route('client-portal.shipments.show', $shipment) }}">
                        <span class="cp-record-symbol record-coral"><i class="fa-solid fa-box"></i></span>
                        <span class="cp-dashboard-record-copy"><strong>{{ $shipment->shipment_number }}</strong><small>{{ $shipment->from_city ?: 'Origin' }} <i class="fa-solid fa-arrow-right-long"></i> {{ $shipment->to_city ?: 'Destination' }}</small></span>
                        <span class="cp-record-status">{{ $shipment->statusLabel() }}</span>
                    </a>
                @empty
                    <div class="cp-empty cp-empty-spacious"><i class="fa-solid fa-truck-fast"></i><strong>No shipments shared</strong><span>Trackable deliveries will appear here.</span></div>
                @endforelse
            </div>
        </section>
    </div>

    <aside class="cp-dashboard-rail" aria-label="Workspace summary">
        <section class="cp-card cp-dashboard-panel">
            <div class="cp-section-heading"><div><p class="cp-eyebrow">Priority</p><h2>Needs your attention</h2></div></div>
            @if($attentionItems->isNotEmpty())
                <div class="cp-action-list">
                    @foreach($attentionItems as $item)
                        <a class="cp-action-item" href="{{ $item['url'] }}">
                            <span class="cp-action-icon"><i class="{{ $item['icon'] }}"></i></span>
                            <span><strong>{{ $item['label'] }}</strong><small>{{ $item['detail'] }}</small></span>
                            <span class="cp-action-count">{{ $item['count'] }}</span>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="cp-all-clear"><i class="fa-solid fa-circle-check"></i><strong>You are all caught up</strong><div class="cp-muted">There are no urgent items right now.</div></div>
            @endif
        </section>

        <section class="cp-card cp-dashboard-panel">
            <div class="cp-section-heading"><div><p class="cp-eyebrow">Shortcuts</p><h2>Quick access</h2></div></div>
            <div class="cp-quick-links">
                <a class="cp-quick-link" href="{{ route('client-portal.statement.index') }}"><i class="fa-solid fa-scale-balanced"></i><span>Statement</span></a>
                <a class="cp-quick-link" href="{{ route('client-portal.attachments.index') }}"><i class="fa-solid fa-folder-open"></i><span>Documents</span></a>
                <a class="cp-quick-link" href="{{ route('client-portal.products.index') }}"><i class="fa-solid fa-box-open"></i><span>Catalogue</span></a>
                <a class="cp-quick-link" href="{{ route('client-portal.kyc.show') }}"><i class="fa-solid fa-address-card"></i><span>KYC</span></a>
            </div>
        </section>

        <section class="cp-card cp-dashboard-panel">
            <div class="cp-section-heading"><div><p class="cp-eyebrow">Support</p><h2>Your conversations</h2></div><a class="cp-text-link" href="{{ route('client-portal.support.index') }}">Inbox</a></div>
            <div class="cp-support-summary">
                <div><strong>{{ $stats['support_open'] }}</strong><span>in progress</span></div>
                <div><strong>{{ $stats['support_unread'] }}</strong><span>new replies</span></div>
            </div>
            @forelse($supportConversations->take(2) as $conversation)
                <a class="cp-mini-support-thread" href="{{ route('client-portal.support.show', $conversation->id) }}"><span><strong>{{ $conversation->subject }}</strong><small>{{ optional($conversation->last_message_at)->diffForHumans() ?: 'Recently opened' }}</small></span><span class="cp-status-pill cp-status-{{ $conversation->status }}">{{ $conversation->statusLabel() }}</span></a>
            @empty
                <div class="cp-dashboard-hint">Need help? Start a private conversation with our team.</div>
            @endforelse
        </section>

        <section class="cp-card cp-dashboard-panel">
            <div class="cp-section-heading"><div><p class="cp-eyebrow">Activity</p><h2>Latest updates</h2></div><a class="cp-text-link" href="{{ route('client-portal.notifications.index') }}">All</a></div>
            <div class="cp-notification-list">
                @forelse($notifications->take(3) as $notification)
                    <div class="cp-dashboard-notification {{ $notification->is_read ? '' : 'is-unread' }}"><span class="cp-notification-mark"><i class="fa-solid fa-bell"></i></span><div><strong>{{ $notification->title }}</strong><p>{{ \Illuminate\Support\Str::limit($notification->message ?: 'There is a new update.', 80) }}</p><time>{{ $notification->created_at->diffForHumans() }}</time></div></div>
                @empty
                    <div class="cp-dashboard-hint">New account activity will appear here.</div>
                @endforelse
            </div>
        </section>
    </aside>
</div>
@endsection
