@extends('client_portal.layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')

<style>
    .projects-progress-wrap{
        width:360px;
        flex-shrink:0;
        align-self:center;
    }
    
    .projects-progress-text{
        display:flex;
        justify-content:space-between;
        align-items:center;
        margin-bottom:10px;
    }
    
    .projects-progress{
        height:12px;
        background:#e5e7eb;
        border-radius:999px;
        overflow:hidden;
    }
    
    .projects-progress span{
        display:block;
        height:100%;
        border-radius:999px;
        background:linear-gradient(90deg,#4f7cff,#18b66b);
    }

    .projects-number {
        font-size: 12px;
        color: #4f83f1;
        font-weight: 600
    }
</style>

<div class="cp-page-head" style="margin-bottom:25px;">
    <div>
        <p class="cp-eyebrow">Welcome, {{ $portalUser->displayName() }}</p>
        <h1>{{ $client->company_name }}</h1>
        
    </div>
    <!--<a href="{{ route('client-portal.attachments.index') }}" class="cp-btn cp-btn-primary">Upload Document</a>-->
</div>

<div class="master-stats">
    <div class="master-stat blue"><span class="icon"><i class="fa-solid fa-briefcase"></i></span>
        <div>
            <p class="master-stat-title">Projects</p>
            <p class="master-stat-value">{{ $stats['projects'] }}</p>
        </div>
    </div>
    <div class="master-stat purple"><span class="icon"><i class="fas fa-truck"></i></span>
        <div>
            <p class="master-stat-title">Shipments</p>
            <p class="master-stat-value">{{ $stats['shipments'] }}</p>
        </div>
    </div>
    <div class="master-stat teal"><span class="icon"><i class="fa-solid fa-file-invoice-dollar"></i></span>
        <div>
            <p class="master-stat-title">Invoices</p>
            <p class="master-stat-value">{{ $stats['invoices'] }}</p>
        </div>
    </div>
    <div class="master-stat blue"><span class="icon"><i class="fas fa-bell"></i></span>
        <div>
            <p class="master-stat-title">Unread Notifications</p>
            <p class="master-stat-value">{{ $stats['unread_notifications'] }}</p>
        </div>
    </div>
    <div class="master-stat orange"><span class="icon"><i class="fas fa-user-gear"></i></span>
        <div>
            <p class="master-stat-title">KYC Status</p>
            <p class="master-stat-value">{{ method_exists($client, 'statusLabel') ? $client->statusLabel() : ucfirst($client->status) }}</p>
        </div>
    </div>
</div>

<div class="cp-grid-2">
    <div class="cp-card" style="padding:20px;">
        <div class="cp-page-head" style="margin-bottom:10px;">
            <div>
                <p class="cp-eyebrow">Latest</p>
                <h1 style="font-size:20px;">Projects</h1>
            </div>
            <a class="cp-btn cp-btn-soft cp-btn-sm" href="{{ route('client-portal.projects.index') }}">View All</a>
        </div>
        @forelse($projects as $project)
            <div class="cp-file" style="margin-bottom:10px;">
                <div class="cp-file-icon">▣</div>
                <div>
                    <a href="{{ route('client-portal.projects.show', $project->id) }}" style="margin-top:8px;text-decoration:none;">
                        <div class="cp-muted">{{ $project->project_number }}</div>
                        <h3>{{ $project->name }}</h3>
                        <div class="projects-progress-wrap" style="margin-top:10px;">
                            <div class="projects-progress-text">
                                <span>{{ $project->stageLabel() }}</span>
                                <strong>{{ $project->progress_percent }}%</strong>
                            </div>
                    
                            <div class="projects-progress">
                                <span style="width: {{ $project->progress_percent }}%"></span>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        @empty
            <div class="cp-empty">No public projects yet.</div>
        @endforelse
    </div>
    
    <div class="cp-card" style="padding:20px;">
        <div class="cp-page-head" style="margin-bottom:10px;">
            <div>
                <p class="cp-eyebrow">Latest</p>
                <h1 style="font-size:20px;">Shipments</h1>
            </div>
            <a class="cp-btn cp-btn-soft cp-btn-sm" href="{{ route('client-portal.shipments.index') }}">View All</a>
        </div>
        @forelse($shipments as $shipment)
        
            <div class="cp-comment">
                <a href="{{ route('client-portal.shipments.show', $shipment->id) }}" style="margin-top:8px;text-decoration:none;">
                    <div class="cp-comment-head">
                        <strong>{{ $shipment->shipment_number }} · {{ $shipment->logistic_partner ?: '-' }} · {{ $shipment->tracking_number ?: 'No tracking' }}</strong>
                        <span>{{ $shipment->statusLabel() }}</span>
                    </div>
                    <h4>{{ $shipment->identity_name }}</h4>
                </a>
                <strong style="font-size:11px;padding-top:5px;">{{ $shipment->pickup_date ? $shipment->pickup_date->format('d M') : '-' }}</strong>
            </div>
        @empty
            <div class="cp-empty">No public shipments yet.</div>
        @endforelse
    </div>
</div>

<div class="cp-grid-2" style="margin-top:18px;">

    <div class="cp-card" style="padding:20px;">
        <div class="cp-page-head" style="margin-bottom:10px;">
            <div>
                <p class="cp-eyebrow">Latest</p>
                <h1 style="font-size:20px;">Notifications</h1>
            </div>
            <a class="cp-btn cp-btn-soft cp-btn-sm" href="{{ route('client-portal.notifications.index') }}">View All</a>
        </div>
        @forelse($notifications as $notification)
            <div class="cp-comment" style="background:{{ $notification->is_read ? '#fff' : '#f8fbff' }};">
                <div class="cp-comment-head">
                    <strong>{{ $notification->title }}</strong>
                    <span>{{ $notification->created_at->format('d M Y') }}</span>
                </div>
                <p>{{ $notification->message }}</p>
                @if($notification->action_url)
                    <a class="cp-btn cp-btn-soft cp-btn-sm" href="{{ $notification->action_url }}" style="margin-top:8px;">Open</a>
                @endif
            </div>
        @empty
            <div class="cp-empty">No notifications yet.</div>
        @endforelse
    </div>

    <!--<div class="cp-card" style="padding:20px;">-->
    <!--    <div class="cp-page-head" style="margin-bottom:10px;">-->
    <!--        <div>-->
    <!--            <p class="cp-eyebrow">Latest</p>-->
    <!--            <h1 style="font-size:20px;">Invoices</h1>-->
    <!--        </div>-->
    <!--        <a class="cp-btn cp-btn-soft cp-btn-sm" href="{{ route('client-portal.invoices.index') }}">View All</a>-->
    <!--    </div>-->
    <!--    @forelse($invoices as $invoice)-->
    <!--        <div class="cp-file" style="margin-bottom:10px;">-->
    <!--            <div class="cp-file-icon">▤</div>-->
    <!--            <div>-->
    <!--                <strong>{{ $invoice->invoice_number }}</strong>-->
    <!--                <div class="cp-muted">{{ $invoice->currency }} {{ number_format((float) $invoice->total_amount, 2) }} · {{ $invoice->statusLabel() }}</div>-->
    <!--                <a class="cp-btn cp-btn-soft cp-btn-sm" href="{{ route('client-portal.invoices.show', $invoice) }}" style="margin-top:8px;">Open</a>-->
    <!--            </div>-->
    <!--        </div>-->
    <!--    @empty-->
    <!--        <div class="cp-empty">No public invoices yet.</div>-->
    <!--    @endforelse-->
    <!--</div>-->
</div>
@endsection
