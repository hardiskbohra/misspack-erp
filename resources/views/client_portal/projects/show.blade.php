@extends('client_portal.layouts.app')

@section('title', $project->name)
@section('page-title', 'Project Detail')

@section('content')

<style>
    
    .pd-progress-ring {
        width: 96px;
        height: 96px;
        border-radius: 50%;
        background: conic-gradient(#4f83f1 {{ $project->progress_percent }}%, #eef2f7 0);
        display: grid;
        place-items: center;
        position: relative
    }

    .pd-progress-ring:before {
        content: "";
        position: absolute;
        inset: 10px;
        background: #fff;
        border-radius: 50%
    }

    .pd-progress-ring strong,
    .pd-progress-ring span {
        position: relative
    }

    .pd-progress-ring strong {
        font-size: 22px;
        color: #172033
    }

    .pd-progress-ring span {
        font-size: 11px;
        color: #7b8495;
        margin-top: 34px;
        position: absolute
    }

    .pd-status-left {
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 16px;
        align-items: center
    }
    
    .pd-chip {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        border-radius: 999px;
        padding: 7px 10px;
        background: #f1f4f9;
        color: #5f6b7a;
        font-size: 12px;
        font-weight: 600
    }

    .pd-status-in_progress,
    .pd-status-pps_development,
    .pd-status-in_production
    {
        background: #8eb3ed;
        color: #FFF
    }

    .pd-status-completed,
    .pd-health-green,
    .pd-status-ready,
    .pd-status-shipped,
    .pd-status-delivered,
    .pd-public {
        background: #c1f2bb !important;
        color: green !important
    }

    .pd-status-on_hold,
    .pd-status-waiting_client,
    .pd-status-waiting_vendor,
    .pd-status-po_pending,
    .pd-status-artwork_pending,
    .pd-status-artwork_review,
    .pd-status-pps_review,
    .pd-status-qc_pending,
    .pd-health-amber {
        background: #ebe983;
        color: red
    }

    .pd-status-cancelled,
    .pd-health-red {
        background: #e86d61;
        color: #FFF
    }

    .pd-pin {
        background: #fff7e6;
        color: #b54708
    }

    .pd-progress,
    .pd-mini-progress {
        height: 10px;
        background: #eef2f7;
        border-radius: 999px;
        overflow: hidden;
        margin-top: 12px
    }

    .pd-progress span,
    .pd-mini-progress span {
        display: block;
        height: 100%;
        background: linear-gradient(90deg, #4f83f1, #12b76a)
    }
    
    .pd-metrics {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 14px
    }
    
    .pd-metric {
        padding: 16px;
        background: #fff;
        border: 1px solid #edf0f7;
        border-radius: 22px;
        box-shadow: 0 12px 32px rgba(22, 34, 51, .06)
    }

    .pd-metric span {
        font-size: 12px;
        color: #7b8495;
        font-weight: 600;
        text-transform: uppercase
    }

    .pd-metric strong {
        display: block;
        margin-top: 8px;
        color: #172033;
        font-size: 20px
    }

    .pd-green {
        color: #039855 !important
    }

    .pd-red {
        color: #d92d20 !important
    }
    
    .pd-tabs-shell {
        overflow: hidden;
        background: #fff;
        border: 1px solid #edf0f7;
        border-radius: 22px;
        box-shadow: 0 12px 32px rgba(22, 34, 51, .06)
    }

    .pd-tabs-nav {
        display: flex;
        gap: 8px;
        align-items: center;
        overflow-x: auto;
        padding: 12px;
        background: #f8faff;
        border-bottom: 1px solid #edf0f7;
        scrollbar-width: thin
    }

    .pd-tab-btn {
        border: 1px solid #dfe5f2;
        background: #fff;
        color: #5f6b7a;
        border-radius: 14px;
        padding: 10px 13px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        white-space: nowrap;
        cursor: pointer;
        transition: .2s
    }

    .pd-tab-btn:hover {
        border-color: #4f83f1;
        color: #4f83f1
    }

    .pd-tab-btn.active {
        background: #4f83f1;
        border-color: #4f83f1;
        color: #fff;
        box-shadow: 0 10px 22px rgba(79, 131, 241, .22)
    }

    .pd-tab-btn span {
        min-width: 22px;
        height: 22px;
        border-radius: 999px;
        background: #eef3ff;
        color: #4f83f1;
        display: grid;
        place-items: center;
        padding: 0 6px;
        font-size: 11px
    }

    .pd-tab-btn.active span {
        background: rgba(255, 255, 255, .22);
        color: #fff
    }

    .pd-tabs-content {
        padding: 16px;
        background: #fff
    }

    .pd-tab-panel {
        display: none
    }

    .pd-tab-panel.active {
        display: block
    }

    .pd-panel-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.25fr) minmax(320px, .75fr);
        gap: 16px
    }

    .pd-card {
        padding: 18px;
        box-shadow: none
    }

    .pd-section-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 10px;
        margin-bottom: 14px
    }

    .pd-section-head h2 {
        margin: 0;
        color: #172033;
        font-weight: 600;
        font-size: 20px
    }

    .pd-count {
        background: #eef3ff;
        color: #4f83f1;
        border-radius: 999px;
        padding: 7px 10px;
        font-size: 12px;
        font-weight: 600
    }

    .pd-info-list {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px
    }

    .pd-info-list div {
        background: #f8faff;
        border: 1px solid #edf2ff;
        border-radius: 14px;
        padding: 11px
    }

    .pd-info-list span,
    .pd-text-block span {
        display: block;
        color: #7b8495;
        font-size: 12px;
        font-weight: 500;
        text-transform: uppercase
    }

    .pd-info-list strong {
        display: block;
        margin-top: 4px;
        font-size: 14px;
        font-weight: 600;
        color: #172033
    }

    .pd-text-block {
        margin-top: 12px;
        border-top: 1px solid #edf0f7;
        padding-top: 12px
    }

    .pd-text-block p {
        margin: 6px 0 0;
        color: #344054;
        font-size: 14px;
        white-space: pre-wrap
    }

    .pd-private {
        background: #fff8ec;
        border: 1px solid #fedf89;
        border-radius: 14px;
        padding: 12px
    }

    .pd-summary-list {
        display: grid;
        gap: 10px
    }

    .pd-summary-item {
        border: 1px solid #edf0f7;
        background: #fff;
        border-radius: 16px;
        padding: 12px;
        display: grid;
        grid-template-columns: 42px 1fr;
        gap: 10px;
        text-align: left;
        cursor: pointer
    }

    .pd-summary-item i {
        width: 42px;
        height: 42px;
        border-radius: 14px;
        background: #eef3ff;
        color: #4f83f1;
        display: grid;
        place-items: center
    }

    .pd-summary-item strong {
        display: block;
        color: #172033
    }

    .pd-summary-item small {
        display: block;
        color: #7b8495;
        margin-top: 3px
    }

    .pd-latest-box {
        margin-top: 14px;
        border-top: 1px solid #edf0f7;
        padding-top: 14px
    }

    .pd-latest-box>strong {
        display: block;
        margin-bottom: 8px
    }

    .pd-latest-row {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        padding: 9px 0;
        border-bottom: 1px solid #edf0f7
    }

    .pd-latest-row span {
        font-weight: 500;
        color: #344054
    }

    .pd-latest-row small {
        color: #7b8495
    }

    .pd-inline-form {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
        background: #f8faff;
        border: 1px solid #edf2ff;
        border-radius: 18px;
        padding: 14px;
        margin-bottom: 14px
    }

    .pd-product-form {
        grid-template-columns: 1.2fr .55fr .55fr .9fr .9fr .55fr auto
    }

    .pd-tracking-form {
        grid-template-columns: 1fr 1fr .85fr .65fr 1fr 1fr auto
    }

    .pd-attachment-form {
        grid-template-columns: 1fr 1fr 1fr 1fr auto 1fr auto
    }

    .pd-payment-form {
        grid-template-columns: repeat(5, minmax(0, 1fr))
    }

    .pd-nested-form {
        margin-top: 12px;
        background: #fff;
        border: 1px solid black;
        box-shadow: 0 18px 45px rgba(79, 131, 241, .22);
    }

    .master-field {
        display: flex;
        flex-direction: column;
        gap: 6px
    }

    .master-field label {
        font-size: 12px;
        color: #5f6b7a;
        font-weight: 600
    }

    .master-field.small {
        min-width: 80px
    }

    .pd-span-2 {
        grid-column: span 2
    }

    .pd-check {
        display: flex;
        gap: 8px;
        align-items: center;
        font-weight: 600;
        color: #5f6b7a;
        font-size: 13px
    }

    .pd-check input {
        accent-color: #4f83f1
    }

    .pd-product-list,
    .pd-comments-list,
    .pd-payment-list,
    .pd-log-list {
        display: flex;
        flex-direction: column;
        gap: 10px
    }

    .pd-attachment-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px
    }

    .pd-product-card,
    .pd-comment,
    .pd-attachment,
    .pd-payment,
    .pd-log {
        border: 1px solid #edf0f7;
        background: #fff;
        border-radius: 18px;
        padding: 14px
    }

    .pd-product-top,
    .pd-timeline-head,
    .pd-comment-head,
    .pd-payment {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        align-items: flex-start
    }
    
    .pd-product-top {
        display: grid;
        grid-template-columns: 0.2fr 1.5fr 0.6fr;
    }

    .pd-product-top h3 {
        margin: 0 0 5px;
        color: #172033;
        font-size: 17px
    }

    .pd-muted {
        color: #7b8495;
        font-size: 13px
    }

    .pd-product-meta {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        color: #667085;
        font-size: 12px;
        margin-top: 10px
    }

    .pd-note {
        background: #fff8ec;
        border: 1px solid #fedf89;
        border-radius: 14px;
        padding: 10px;
        font-size:12px;
        margin-top:10px;
        margin-bottom:10px;
        color: #7a4b08
    }

    .pd-details {
        margin-top: 10px
    }

    .pd-details summary {
        cursor: pointer;
        font-weight: 600;
        color: #4f83f1
    }

    .pd-delete-form {
        margin-top: 10px
    }

    .pd-link-danger {
        border: 0;
        background: none;
        color: #d92d20;
        cursor: pointer;
        font-weight: 600;
        padding: 0
    }

    .pd-link-success {
        border: 0;
        background: none;
        color: green;
        cursor: pointer;
        font-weight: 600;
        padding: 0
    }

    .pd-empty {
        padding: 18px;
        border: 1px dashed #d8deea;
        border-radius: 16px;
        text-align: center;
        color: #7b8495;
        background: #fbfcff
    }

    .pd-timeline {
        position: relative;
        display: flex;
        flex-direction: column;
        gap: 14px
    }

    .pd-timeline-item {
        display: grid;
        grid-template-columns: 22px 1fr;
        gap: 10px
    }

    .pd-dot {
        width: 14px;
        height: 14px;
        border-radius: 50%;
        background: #4f83f1;
        margin-top: 5px;
        box-shadow: 0 0 0 5px #eaf2ff
    }

    .pd-timeline-content {
        border: 1px solid #edf0f7;
        border-radius: 16px;
        padding: 12px
    }

    .pd-timeline-content p {
        margin: 8px 0 0;
        color: #344054
    }

    .pd-row-actions {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
        margin-top: 8px
    }

    .pd-row-actions a {
        font-weight: 600;
        color: #4f83f1;
        text-decoration: none
    }

    .pd-comment-form {
        display: flex;
        flex-direction: column;
        gap: 10px;
        background: #f8faff;
        border: 1px solid #edf2ff;
        border-radius: 18px;
        padding: 14px;
        margin-bottom: 14px
    }

    .pd-comment-options {
        display: flex;
        gap: 12px;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: wrap
    }

    .pd-comment p {
        margin: 8px 0;
        color: #344054
    }

    .pd-pinned {
        border-color: #fedf89;
        background: #fffdf7
    }

    .pd-attachment {
        display: grid;
        grid-template-columns: 64px 1fr;
        gap: 12px
    }

    .pd-attachment img,
    .pd-file-icon {
        width: 64px;
        height: 64px;
        border-radius: 14px;
        object-fit: cover;
        background: #eef3ff;
        color: #4f83f1;
        display: grid;
        place-items: center;
        font-size: 22px
    }

    .pd-attachment strong,
    .pd-attachment span {
        display: block
    }

    .pd-attachment span {
        color: #7b8495;
        font-size: 12px;
        margin-top: 3px
    }

    .pd-payment {
        align-items: center
    }

    .pd-payment span {
        display: block;
        color: #7b8495;
        font-size: 12px;
        margin-top: 3px
    }

    .pd-payment-amount {
        font-weight: 600
    }

    .pd-payment.inward .pd-payment-amount {
        color: #039855
    }

    .pd-payment.outward .pd-payment-amount {
        color: #d92d20
    }

    .pd-cashflow-box {
        margin-top: 12px;
        background: #f8faff;
        border: 1px solid #edf2ff;
        border-radius: 16px;
        padding: 12px
    }

    .pd-cashflow-box>strong {
        display: block;
        margin-bottom: 8px;
        color: #172033
    }

    .pd-cashflow-row {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        border-top: 1px solid #edf0f7;
        padding: 8px 0;
        color: #667085;
        font-size: 13px
    }

    .pd-cashflow-row:first-of-type {
        border-top: 0
    }

    .pd-cashflow-row b {
        color: #172033
    }

    .pd-log strong,
    .pd-log span {
        display: block
    }

    .pd-log span {
        color: #7b8495;
        font-size: 12px;
        margin-top: 3px
    }

    .pd-log p {
        margin: 6px 0 0;
        color: #344054;
        font-size: 13px
    }
    
    .master-modal {
        display: none;
    }
    
    .master-modal.show {
        display: flex;
    }
</style>

    @php
        $totals = $project->paymentTotals();
        $statusClass = 'projects-chip-status-' . $project->status;
        $healthClass = 'projects-health-' . $project->health;
    @endphp
    
<div class="master-card master-header" style="margin-bottom:15px;border:1px solid white;background: linear-gradient(135deg, #34d399, #6ee7b7);box-shadow: 0 18px 45px rgba(79, 131, 241, .22);">
    <div class="pd-status-left">
        <div class="pd-progress-ring">
            <strong>{{ $project->progress_percent }}%</strong>
            <span>Progress</span>
        </div>
        <div>
            <p class="cp-eyebrow" style="color:white">{{ $project->project_number }}</p>
            <h1>{{ $project->name }}</h1>
            <p><span style="color:white"><i class="fa-solid fa-calendar-days"></i> &nbsp; Start:
                    {{ optional($project->start_date)->format('d M Y') ?: 'Not set' }}</span> &nbsp; &nbsp;
                <!--<span><i class="fa-solid fa-calendar-days"></i> &nbsp; Target:-->
                <!--    {{ optional($project->target_date)->format('d M Y') ?: 'Not set' }}</span>-->
            </p>
        </div>
    </div>
    <div>
        <a href="{{ route('client-portal.projects.index') }}" class="master-btn master-btn-light" style="padding:8px 15px;">Back</a>&nbsp;
        <button type="button" class="master-btn master-btn-primary" id="openAddCommentModal2"><i class="fas fa-plus"></i> Add Comment</button>
        <button type="button" class="master-btn master-btn-primary" id="openAddAttachmentModal2"><i class="fas fa-plus"></i> Add Attachment</button>
    </div>
</div>

<div class="pd-metrics" style="margin-bottom:15px;">
    <div class="pd-metric">
        <span>Status</span>
        <strong>{{ $project->statusLabel() }}</strong>
    </div>
    <div class="pd-metric">
        <span>Stage</span>
        <strong>{{ $project->stageLabel() }}</strong>
    </div>
    <div class="pd-metric"><span>Estimated
            Value</span><strong>{{ $project->currency == 'INR' ? '₹' : $project->currency }}{{ number_format((float) $project->estimated_value, 2) }}</strong>
    </div>
    <div class="pd-metric"><span>Payments</span><strong
            class="pd-green">{{ $project->currency == 'INR' ? '₹' : $project->currency }}{{ number_format($totals['inward'], 2) }}</strong>
    </div>
    <div class="pd-metric">
        <span>Balance</span><strong>{{ $project->currency == 'INR' ? '₹' : $project->currency }}{{ number_format($totals['outstanding'], 2) }}</strong>
    </div>
</div>

<div class="pd-tabs-shell">
    <div class="pd-tabs-nav" role="tablist" aria-label="Project sections">
        <button type="button" class="pd-tab-btn active" data-tab="overview" role="tab" aria-selected="true">Overview</button>
        <button type="button" class="pd-tab-btn" data-tab="products" role="tab" aria-selected="false">Products
            <span>{{ $project->products->count() }}</span></button>
        <button type="button" class="pd-tab-btn" data-tab="milestones" role="tab" aria-selected="false">
            Milestones <span>{{ $project->milestones->count() }}</span></button>
        <button type="button" class="pd-tab-btn" data-tab="comments" role="tab" aria-selected="false">
            Comments <span>{{ $project->comments->count() }}</span></button>
        <button type="button" class="pd-tab-btn" data-tab="attachments" role="tab" aria-selected="false">Attachments
            <span>{{ $project->publicAttachments->count() }}</span></button>
        <button type="button" class="pd-tab-btn" data-tab="payments" role="tab" aria-selected="false">Payments
            <span>{{ $project->payments->count() + $project->cashflowEntries->whereNotNull('client_id')->count() }}</span></button>
        <button type="button" class="pd-tab-btn" data-tab="shipments" role="tab" aria-selected="false">Shiments
            <span>{{ $project->shipments->count() }}</span></button>
        <button type="button" class="pd-tab-btn" data-tab="tracking" role="tab" aria-selected="false">Activities
            <span>{{ $project->publicTrackingUpdates->count() }}</span></button>
    </div>

    <div class="pd-tabs-content">
        <section class="pd-tab-panel active" id="pd-tab-overview" data-tab-panel="overview" role="tabpanel">
            <div class="pd-panel-grid">
                <div class="pd-card pd-overview-card">
                    <div class="pd-section-head">
                        <div>
                            <p class="pd-eyebrow">Snapshot</p>
                            <h2>Project Overview</h2>
                        </div>
                        <span class="pd-count">{{ $project->project_number }}</span>
                    </div>
                    <div class="pd-info-list">
                        <div>
                            <span>Client</span><strong>{{ $project->client ? $project->client->company_name : 'Client #' . $project->client_id }}</strong>
                        </div>
                        <div><span>Assigned
                                To</span><strong>Neha Bohra</strong>
                        </div>
                        <div><span>Start
                                Date</span><strong>{{ optional($project->start_date)->format('d M Y') ?: '-' }}</strong>
                        </div>
                        <div><span>Client
                                Portal</span><strong>{{ $project->show_client_portal ? 'Enabled' : 'Hidden' }}</strong>
                        </div>
                        <div><span>Products</span><strong>{{ $project->products->count() }}</strong></div>
                        <div><span>Currency</span><strong>{{ $project->currency }}</strong></div>
                    </div>
                    @if ($project->scope_summary)
                        <div class="pd-text-block"><span>Scope Summary</span>
                            <p>{{ $project->scope_summary }}</p>
                        </div>
                    @endif
                    @if ($project->deliverables)
                        <div class="pd-text-block"><span>Deliverables</span>
                            <p>{{ $project->deliverables }}</p>
                        </div>
                    @endif
                    @if ($project->client_notes)
                        <div class="pd-text-block"><span>Client Notes</span>
                            <p>{{ $project->client_notes }}</p>
                        </div>
                    @endif
                    @if ($project->internal_notes)
                        <div class="pd-text-block pd-private"><span>Internal Notes</span>
                            <p>{{ $project->internal_notes }}</p>
                        </div>
                    @endif
                </div>

                <div class="pd-card">
                    <div class="pd-section-head">
                        <div>
                            <p class="pd-eyebrow">Quick Summary</p>
                            <h2>Latest Activity</h2>
                        </div>
                    </div>
                    <div class="pd-summary-list">
                        <button type="button" class="pd-summary-item" data-tab-jump="products" style="background:#eaf2ff">
                            <i class="fa-solid fa-boxes-stacked"></i>
                            <span><strong>{{ $project->products->count() }} Products</strong><small>View
                                    product-wise execution and invoices</small></span>
                        </button>
                        <button type="button" class="pd-summary-item" data-tab-jump="tracking" style="background:#e8fff3">
                            <i class="fa-solid fa-location-dot"></i>
                            <span><strong>{{ $project->publicTrackingUpdates->count() }} Activity
                                    Updates</strong><small>Latest public/internal project progress</small></span>
                        </button>
                        <button type="button" class="pd-summary-item" data-tab-jump="attachments" style="background:#fff7e6">
                            <i class="fa-solid fa-paperclip"></i>
                            <span><strong>{{ $project->publicAttachments->count() }} Attachments</strong><small>Vendor
                                    invoice, packing list, artwork, photos</small></span>
                        </button>
                        <button type="button" class="pd-summary-item" data-tab-jump="payments" style="background:#fff1f3">
                            <i class="fa-solid fa-indian-rupee-sign"></i>
                            <span><strong>{{ $project->cashflowEntries->whereNotNull('client_id')->where('related_party_type', 'client')->count() }} Payment
                                    Entries</strong><small>Inward/outward payment tracking</small></span>
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!--Product-->
        <section class="pd-tab-panel" id="pd-tab-products" data-tab-panel="products" role="tabpanel">
            <div class="pd-section-head" style="padding:5px;">
                <div>
                    <p class="pd-eyebrow">Products</p>
                    <h2>Project Products</h2>
                </div>
                <div>
                    <span class="pd-count">{{ $project->products->count() }} items</span>
                </div>
            </div>
                                
            <div class="master-table-wrap master-card" style="padding:0px;box-shadow:none;">
                <table class="master-table">
                    <thead>
                        <tr>
                            <th width="70">Image</th>
                            <th>Product</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Amount (Excl. GST)</th>
                            <th>Specifications</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($project->products as $projectProduct)
                            @php
                                $media = optional($projectProduct->product)->primaryMedia();
                            @endphp
                            <tr>
                                <td>
                                    @if($media)
                                        <img class="master-avatar"
                                             src="{{ asset('storage/'.$media->file_path) }}">
                                    @else
                                        <div class="master-avatar">📦</div>
                                    @endif
                                </td>
                                <td>
                                    <strong>{{ $projectProduct->product_name }}</strong>
                                    <div class="master-sub" style="margin-bottom:5px;">{{ $projectProduct->product->product_number }}</div>
                                    <span class="pd-chip pd-status-{{ $projectProduct->status }}" style="font-size:10px;padding:5px 10px;">{{ $projectProduct->statusLabel() }}</span>
                                </td>
                                <td><strong>{{ number_format($projectProduct->quantity) }} {{ $projectProduct->unit }}</strong></td>
                                <td><strong>{{ $projectProduct->currency == 'INR' ? '₹' : $projectProduct->currency }}{{ $projectProduct->unit_price }}</strong></td>
                                <td><strong>{{ $projectProduct->currency == 'INR' ? '₹' : $projectProduct->currency }}{{ number_format($projectProduct->total_amount,2) }}</strong></td>
                                <td><strong style="font-size:10px;font-weight:500;">{!! $projectProduct?->notes ? nl2br(e($projectProduct->notes)) : '-' !!}</strong></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11">
                                    <div class="master-empty">
                                        No products added yet.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
        
        @php
            $milestones = $project->relationLoaded('milestones') ? $project->milestones : collect();
            $milestoneTotal = $milestones->count();
            $milestoneCompleted = $milestones->where('status', 'completed')->count();
            $milestonePublic = $milestones->where('is_public', true)->count();
            $milestoneOverdue = $milestones
                ->filter(function ($milestone) {
                    return method_exists($milestone, 'isOverdue') && $milestone->isOverdue();
                })
                ->count();
            $milestoneProgress = $milestoneTotal ? (int) round($milestones->avg('progress_percent')) : 0;
            $productsWithMilestones = $project->products;
            $projectLevelMilestones = $milestones->whereNull('project_product_id')->values();
        @endphp
        
        <section class="pd-tab-panel" id="pd-tab-milestones" data-tab-panel="milestones" role="tabpanel">
            <div class="pmile-page">
                <div class="pmile-stats">
                    <div><span>Total Milestones</span><strong>{{ $milestoneTotal }}</strong></div>
                    <div><span>Completed</span><strong class="green">{{ $milestoneCompleted }}</strong></div>
                    <div><span>Public To Client</span><strong class="blue">{{ $milestonePublic }}</strong></div>
                    <div><span>Overdue / Attention</span><strong class="red">{{ $milestoneOverdue }}</strong></div>
                    <div><span>Average Progress</span><strong>{{ $milestoneProgress }}%</strong></div>
                </div>
        
                <div class="pmile-actions-card">
                    <div class="pd-section-head">
                        <div>
                            <p class="pd-eyebrow">Product Timeline</p>
                            <h2>Project Milestone Timelines</h2>
                        </div>
                    </div>
                </div>
        
                <div class="pmile-product-list">
                    @if ($projectLevelMilestones->count())
                        @include('projects.partials.milestone-product-block', [
                            'title' => 'Project Level Milestones',
                            'productMilestones' => $projectLevelMilestones,
                            'projectProduct' => null,
                        ])
                    @endif
        
                    @forelse($productsWithMilestones as $projectProduct)

                        @php
                            $productMilestones = $milestones
                                ->where('project_product_id', $projectProduct->id)
                                ->values();
                    
                            $milestoneCount = $productMilestones->count();
                    
                            $completedCount = $productMilestones
                                ->where('status', 'completed')
                                ->count();
                    
                            $progress = $milestoneCount > 0
                                ? (int) round($productMilestones->avg('progress_percent'))
                                : 0;
                        @endphp
                        <div class="pmile-product-block">
                            <div class="pmile-product-head">
                                <div>
                                    <h3 style="color:blue;">{{ $projectProduct->product_name }}</h3>
                                    <small>{{ $milestoneCount }} milestones · {{ $completedCount }} completed</small>
                                </div>
                                <div class="pmile-product-progress">
                                    <div class="pd-progress-text"><span>Overall Progress</span><strong> - {{ $progress }}%</strong></div>
                                    <div class="pd-progress"><span style="width: {{ $progress }}%"></span></div>
                                </div>
                            </div>
                        
                            @if ($milestoneCount)
                                {{-- PRODUCT MILESTONE STEP INDICATOR --}}
                                <div class="pmile-step-wrapper">
                        
                                    <div class="pmile-step-scroll">
                        
                                        <div class="pmile-step-line">
                        
                                            @foreach ($productMilestones as $index => $milestone)
                                                @php
                                                    $isCompleted =
                                                        $milestone->progress_percent >= 100 || strtolower($milestone->status) === 'completed';
                        
                                                    $isCurrent =
                                                        !$isCompleted &&
                                                        (strtolower($milestone->status) === 'in_progress' ||
                                                            strtolower($milestone->status) === 'in progress' ||
                                                            $milestone->progress_percent > 0);
                        
                                                    $isOverdue = $milestone->isOverdue();
                        
                                                    $stepClass = $isCompleted ? 'completed' : ($isCurrent ? 'current' : 'upcoming');
                        
                                                    $nodeLabel = $milestone->status === 'completed' ? '✓' : $loop->iteration;
                                                    $connectorClass =
                                                        $milestone->status === 'completed'
                                                            ? 'done'
                                                            : ($milestone->status === 'in_progress'
                                                                ? 'active'
                                                                : '');
                                                    $milestonePayload = [
                                                        'id' => $milestone->id,
                                                        'project_product_id' => $milestone->project_product_id,
                                                        'milestone_key' => $milestone->milestone_key,
                                                        'title' => $milestone->title,
                                                        'description' => $milestone->description,
                                                        'status' => $milestone->status,
                                                        'progress_percent' => $milestone->progress_percent,
                                                        'planned_start_date' => optional($milestone->planned_start_date)->format('Y-m-d'),
                                                        'planned_end_date' => optional($milestone->planned_end_date)->format('Y-m-d'),
                                                        'actual_start_date' => optional($milestone->actual_start_date)->format('Y-m-d'),
                                                        'actual_end_date' => optional($milestone->actual_end_date)->format('Y-m-d'),
                                                        'owner_id' => $milestone->owner_id,
                                                        'is_public' => (bool) $milestone->is_public,
                                                        'is_required' => (bool) $milestone->is_required,
                                                        'sort_order' => $milestone->sort_order,
                                                        'notes' => $milestone->notes,
                                                        'internal_notes' => $milestone->internal_notes,
                                                        'client_note' => $milestone->client_note,
                                                        'blocked_reason' => $milestone->blocked_reason,
                                                    ];
                                                @endphp
                        
                                                <div class="pmile-step {{ $stepClass }} {{ $isOverdue ? 'overdue' : '' }}">
                        
                                                    {{-- STEP CIRCLE --}}
                                                    <div class="pmile-step-circle">
                        
                                                        @if ($isCompleted)
                                                            <i class="fas fa-check"></i>
                                                        @else
                                                            <span>{{ $index + 1 }}</span>
                                                        @endif
                        
                                                    </div>
                        
                                                    {{-- STEP CONTENT --}}
                                                    <div class="pmile-step-content">
                        
                                                        <div class="pmile-step-title-row">
                                                            <h4>{{ $milestone->title }}</h4>
                                                        </div>
                        
                                                        {{-- STATUS --}}
                                                        <div class="pmile-step-status">
                        
                                                            <span class="pmile-step-status-badge">
                                                                {{ $milestone->statusLabel() }}
                                                            </span>
                        
                                                            @if ($isOverdue)
                                                                <span class="pmile-step-overdue">
                                                                    Overdue
                                                                </span>
                                                            @endif
                        
                                                        </div>
                        
                                                        {{-- DATE --}}
                                                        <div class="pmile-step-date">
                        
                                                            @if ($milestone->planned_start_date)
                                                                {{ optional($milestone->planned_start_date)->format('d M') }}
                        
                                                                @if ($milestone->planned_end_date)
                                                                    → {{ optional($milestone->planned_end_date)->format('d M') }}
                                                                @endif
                                                            @else
                                                                -
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="pmile-empty">No milestones for this product yet.</div>
                            @endif
                        </div>

                    @empty
                        @if (!$projectLevelMilestones->count())
                            <div class="pd-empty">Add products first, then generate product-wise milestone timelines.</div>
                        @endif
                    @endforelse
                </div>
            </div>
        
        </section>


        <!--Tracking-->
        <section class="pd-tab-panel" id="pd-tab-tracking" data-tab-panel="tracking" role="tabpanel">
            <div class="pd-section-head" style="padding:5px;">
                <div>
                    <p class="pd-eyebrow">Logs</p>
                    <h2>Activity</h2>
                </div>
                <div>
                    <span class="pd-count">{{ $project->publicTrackingUpdates->count() }} items</span> &nbsp;
                </div>
            </div>
            
            <div class="master-table-wrap master-card" style="padding:0px;box-shadow:none;">
                <table class="master-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Title</th>
                            <th>Product</th>
                            <th>Status</th>
                        </tr>
                    </thead>
            
                    <tbody>
                        @forelse($project->publicTrackingUpdates as $tracking)
                            <tr>
                                <td>
                                    {{ optional($tracking->occurred_at)->format('d M Y') }}
                                    <div class="master-sub">
                                        {{ optional($tracking->occurred_at)->format('h:i A') }}
                                    </div>
                                </td>
                                <td><strong>{{ $tracking->title }}</strong></td>
                                <td><strong>{{ optional($tracking->product)->product_name ?? 'Entire Project' }}</strong></td>
                                <td><span class="pd-chip pd-status-{{ $tracking->status }}">{{ $tracking->statusLabel() }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9">
                                    <div class="master-empty">
                                        No tracking updates available.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <!--Comment-->
        <section class="pd-tab-panel" id="pd-tab-comments" data-tab-panel="comments" role="tabpanel">
            <div class="pd-section-head" style="padding:5px;">
                <div>
                    <p class="pd-eyebrow">Conversation</p>
                    <h2>Comments</h2>
                </div>
                <div>
                    <span class="pd-count">{{ $project->publicComments->count() }} comments</span> &nbsp;
                    <button type="button" class="master-btn master-btn-primary" id="openAddCommentModal"><i class="fas fa-plus"></i> Add Comment</button>
                </div>
            </div>
            <div class="pd-comments-list">
                @forelse($project->publicComments as $comment)
                    <div class="pd-comment {{ $comment->is_pinned ? 'pd-pinned' : '' }}" style="line-height:1;">
                        <div class="pd-comment-head">
                            <strong>{{ $comment->authorName() }}</strong>
                            <div>
                                <span class="pd-chip {{ $comment->is_public ? 'pd-public' : '' }}">{{ $comment->is_public ? 'Public' : 'Internal' }}</span>
                                @if ($comment->is_pinned)
                                    <span class="pd-chip pd-pin">Pinned</span>
                                @endif
                            </div>
                        </div>
                        <div class="pd-muted">
                        @if ($comment->product)
                            For: {{ $comment->product->product_name }} | 
                        @endif
                            {{ $comment->created_at->format('d M Y, h:i A') }}
                        </div>
                        <p class="master-sub" style="padding:15px;font-size:14px;color:#000;font-weight:400;">{{ $comment->body }}</p>
                        <div class="pd-row-actions">
                            <button type="button" class="master-btn master-btn-soft editCommentBtn" data-comment='@json($comment)'>Edit</button>
                            <form method="POST" action="{{ route('projects.comments.destroy', $comment) }}"
                                onsubmit="return confirm('Delete this comment?');">
                                @csrf @method('DELETE')
                                    <button type="submit" class="pd-link-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="pd-empty">No comments yet.</div>
                @endforelse
            </div>
        </section>
        
        <!--Add Comment-->
        <div class="master-modal" id="addCommentModal" aria-hidden="true">
            <div class="master-modal-card" style="max-width:900px;">
                <form method="POST" action="{{ route('client-portal.projects.comments.store', $project->id) }}">
                    @csrf
                    <div class="master-modal-header">
                        <div class="master-modal-heading">
                            <div>
                                <h3 class="master-modal-title">Add Comment</h3>
                            </div>
                        </div>
                        <button type="button" class="master-modal-close" id="closeAddCommentModal" data-close-modal>×</button>
                    </div>
                    <div class="master-modal-body">
                        <div>
                            <div class="cp-field" style="margin-bottom:15px;">
                                <label>Product (optional)</label>
                                <select name="project_product_id">
                                    <option value="">Project level</option>
                                    @foreach ($project->products as $projectProduct)
                                        <option value="{{ $projectProduct->id }}">{{ $projectProduct->product_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="cp-field full">
                                <label>Comment</label>
                                <textarea name="body" rows="3" required placeholder="Add internal/client-visible comment..."></textarea>
                                <input type="checkbox" name="is_public" value="1" hidden>
                            </div>
                        </div>
                    </div>
                    <div class="master-modal-footer">
                        <button type="button" class="master-btn master-btn-light" id="cancelAddCommentModal" data-close-modal>Cancel</button>
                        <button class="master-btn master-btn-primary" type="submit"> Add Comment</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!--Update Comment-->
        <div class="master-modal" id="editCommentModal">
            <div class="master-modal-card" style="max-width:900px;">
                <form id="editCommentForm" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="master-modal-header">
                        <div class="master-modal-heading">
                            <div>
                                <h3 class="master-modal-title">Update Comment</h3>
                            </div>
                        </div>
                        <button type="button" class="master-modal-close" id="closeEditCommentModal" data-close-modal>×</button>
                    </div>
                    <div class="master-modal-body">
                        <div>
                            <div class="cp-field" style="margin-bottom:15px;">
                                <label>Product (optional)</label>
                                <select name="project_product_id">
                                    <option value="">Project level</option>
                                    @foreach ($project->products as $projectProduct)
                                        <option value="{{ $projectProduct->id }}">{{ $projectProduct->product_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="cp-field full">
                                <label>Comment</label>
                                <textarea name="body" rows="3" required placeholder="Add internal/client-visible comment..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="master-modal-footer">
                        <button type="button" class="master-btn master-btn-light" id="cancelEditCommentModal" data-close-modal>Cancel</button>
                        <button class="master-btn master-btn-primary" type="submit"> Save Comment</button>
                    </div>
                </form>
            </div>
        </div>

        <!--Attachment-->
        <section class="pd-tab-panel" id="pd-tab-attachments" data-tab-panel="attachments" role="tabpanel">
            <div>
                <div class="pd-section-head" style="padding:5px;">
                    <div>
                        <p class="pd-eyebrow">Files</p>
                        <h2>Attachments</h2>
                    </div>
                    <div>
                        <span class="pd-count">{{ $project->publicAttachments->count() }} Files</span> &nbsp;
                        <button type="button" class="master-btn master-btn-primary" id="openAddAttachmentModal"><i class="fas fa-plus"></i> Add Attachment</button>
                    </div>
                </div>
                
                <div class="pd-attachment-grid pd-card" style="padding:0px;">
                    @forelse($project->publicAttachments as $attachment)
                        <div class="pd-attachment">
                            @if ($attachment->isImage())
                                <img src="{{ $attachment->fileUrl() }}" alt="{{ $attachment->title }}">
                            @else
                                <div class="pd-file-icon"><i class="fa-solid fa-file-lines"></i></div>
                            @endif
                            <div>
                                <strong>{{ $attachment->title ?: $attachment->original_name }}</strong>
                                <span>{{ $attachment->categoryLabel() }} ·
                                    {{ strtoupper($attachment->extension) }}</span>
                                @if ($attachment->product)
                                    <span>Product: {{ $attachment->product->product_name }}</span>
                                @endif
                                <div class="pd-row-actions">
                                    <a class="master-btn master-btn-light" href="{{ $attachment->fileUrl() }}"
                                        target="_blank">Open</a>
                                    <form method="POST"
                                        action="{{ route('projects.attachments.destroy', $attachment) }}"
                                        onsubmit="return confirm('Delete attachment?');">@csrf
                                        @method('DELETE')<button type="submit"
                                            class="pd-link-danger">Delete</button></form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="pd-empty">No attachments uploaded yet.</div>
                    @endforelse
                </div>
            </div>
        </section>
                
        <!--Add Attachment-->
        <div class="master-modal" id="addAttachmentModal" aria-hidden="true">
            <div class="master-modal-card" style="max-width:900px;">
                <form method="POST" enctype="multipart/form-data" action="{{ route('client-portal.projects.documents.store', $project->id) }}">
                    @csrf
                    <div class="master-modal-header">
                        <div class="master-modal-heading">
                            <div>
                                <h3 class="master-modal-title">Add Attachment</h3>
                            </div>
                        </div>
                        <button type="button" class="master-modal-close" id="closeAddAttachmentModal" data-close-modal>×</button>
                    </div>
                    <div class="master-modal-body">
                        <div class="master-modal-grid">
                            <div class="cp-field">
                                <label>Product (optional)</label>
                                <select name="project_product_id">
                                    <option value="">Project level</option>
                                    @foreach ($project->products as $projectProduct)
                                        <option value="{{ $projectProduct->id }}">{{ $projectProduct->product_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="cp-field">
                                <label>Category</label>
                                <select name="category">
                                    <option value="client_document">Client Document</option>
                                    <option value="artwork">Artwork</option>
                                    <option value="payment_proof">Payment Proof</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="cp-field">
                                <label>Title</label>
                                <input type="text" name="title"
                                    placeholder="Document name"></div>
                            <div class="cp-field">
                                <label>Files</label>
                                <input type="file" name="attachments[]" multiple
                                    required
                                    accept=".jpg,.jpeg,.png,.webp,.gif,.heic,.heif,.pdf,.doc,.docx,.xls,.xlsx,.csv,.ppt,.pptx,.txt,.zip">
                                <input type="checkbox" name="is_public" value="1" hidden>
                            </div>
                            <div class="cp-field pd-span-2">
                                <label>Notes</label>
                                <textarea name="notes" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="master-modal-footer">
                        <button type="button" class="master-btn master-btn-light" id="cancelAddAttachmentModal" data-close-modal>Cancel</button>
                        <button class="master-btn master-btn-primary" type="submit"> Upload</button>
                    </div>
                </form>
            </div>
        </div>

        <!--Payments-->
        <section class="pd-tab-panel" id="pd-tab-payments" data-tab-panel="payments" role="tabpanel">
            <div>
                <div class="pd-section-head" style="padding:5px;">
                    <div>
                        <p class="pd-eyebrow">Finance</p>
                        <h2>Payment</h2>
                    </div>
                    <div>
                        <span class="pd-count">{{ $project->cashflowEntries->whereNotNull('client_id')->where('related_party_type', 'client')->count() }} Entries</span> &nbsp;
                    </div>
                </div>
                <div class="master-table-wrap master-card" style="padding:0px;box-shadow:none;">
                    <table class="master-table">
                        <thead>
                            <tr>
                                <th width="160">Date</th>
                                <th>Type</th>
                                <th>Reference No.</th>
                                <th width="160">Amount</th>
                                <th width="140">Mode</th>
                            </tr>
                        </thead>
                
                        <tbody>
                            @foreach ($project->cashflowEntries->whereNotNull('client_id')->where('related_party_type', 'client')->take(8) as $entry)
                                <tr>
                                    <td>
                                        <strong>
                                            {{ optional($entry->entry_date)->format('d M Y') }}
                                        </strong>
                                        <div class="master-sub">
                                            {{ $entry->transaction_type === 'credit' ? 'Paid' : 'Expense' }}
                                        </div>
                                    </td>
                                    <td><strong>{{ $entry->particular }}</strong></td>
                                    <td><strong>{{ $entry->bank_reference_number }}</strong></td>
                                    <td>
                                        @if ($entry->transaction_type === 'credit')
                                            <span style="color:red">
                                                {{ $entry->currency === 'INR' ? '₹' : $entry->currency }}
                                                {{ number_format((float) $entry->credit_amount, 2) }}
                                            </span>
                                        @else
                                            <span style="color:green">
                                                {{ $entry->currency === 'INR' ? '₹' : $entry->currency }}
                                                {{ number_format((float) $entry->debit_amount, 2) }}
                                            </span>
                                        @endif
                                    </td>
                                    <td><strong>{{ $entry->payment_mode ? strtoupper($entry->payment_mode) : '-' }}</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        
        <!--Shipments-->
        <section class="pd-tab-panel" id="pd-tab-shipments" data-tab-panel="shipments" role="tabpanel">
            <div>
                <div class="pd-section-head" style="padding:5px;">
                    <div>
                        <p class="pd-eyebrow">Logistics</p>
                        <h2>Shipment</h2>
                    </div>
                    <div>
                        <span class="pd-count">{{ $project->shipments->count() }} Shipments</span> &nbsp;
                    </div>
                </div>
                <div class="master-table-wrap master-card" style="padding:0px;box-shadow:none;">
                    <table class="master-table">
                        <thead>
                            <tr>
                                <th width="160">Date</th>
                                <th>Shipment</th>
                                <th>Route</th>
                                <th width="160">Logistics</th>
                                <th width="160">Charges</th>
                                <th width="140">Status</th>
                            </tr>
                        </thead>
                
                        <tbody>
                                @forelse($project->shipments as $shipment)
                                @php($statusClass = str_replace('_', '-', $shipment->status))
                                <tr style="line-height:1.5">
                                    <td style="font-weight:500">
                                        {{ $shipment->pickup_date ? $shipment->pickup_date->format('d M') : '-' }}
                                    </td>
                                    <td>
                                        <span class="master-sub">{{ $shipment->shipment_number }}</span>
                                        <span class="master-id">{{ $shipment->identity_name }}</span>
                                    </td>
                                    <td class="master-route">
                                        <strong style="font-weight:500">{{ $shipment->from_name ?: 'Origin' }} → {{ $shipment->to_name ?: 'Destination' }}</strong>
                                        <span class="master-sub desktop-only">{{ $shipment->from_city ?: '-' }} to {{ $shipment->to_city ?: '-' }}</span>
                                    </td>
                                    <td style="font-weight:500">
                                        {{ $shipment->logistic_partner ?: '-' }}
                                        <span class="master-sub">{{ $shipment->tracking_number ?: 'No tracking' }}</span>
                                    </td>
                                    <td style="font-weight:500">
                                        {{ $shipment->currency ?: '' }} {{ $shipment->shipment_cost ?: '0' }}
                                        <span class="master-sub">{{ $shipment->cost_borne_by ?: '' }}</span>
                                    </td>
                                    <td style="font-weight:500"><span class="master-badge status-{{ $statusClass }}">{{ $shipment->statusLabel() }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">
                                        <div class="master-empty">
                                            No shipments available.
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>

<style>
    .pmile-page {
        display: flex;
        flex-direction: column;
        gap: 16px
    }

    .pmile-stats {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 12px
    }

    .pmile-stats div {
        background: #f8faff;
        border: 1px solid #edf2ff;
        border-radius: 16px;
        padding: 13px
    }

    .pmile-stats span {
        display: block;
        color: #7b8495;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase
    }

    .pmile-stats strong {
        display: block;
        margin-top: 6px;
        font-size: 22px;
        color: #172033
    }

    .pmile-stats .green {
        color: #039855
    }

    .pmile-stats .blue {
        color: #2563eb
    }

    .pmile-stats .red {
        color: #d92d20
    }

    .pmile-actions-card {
        padding: 18px
    }

    .pmile-head-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap
    }

    .pmile-create {
        background: #f8faff;
        border: 1px solid #edf2ff;
        border-radius: 18px;
        padding: 14px
    }

    .pmile-create summary {
        cursor: pointer;
        color: #4f83f1;
        font-weight: 600;
        margin-bottom: 12px
    }

    .pmile-form {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
        align-items: end
    }

    .pmile-product-list {
        display: flex;
        flex-direction: column;
        gap: 16px
    }

    .pmile-product-block {
        background: #fff;
        border: 1px solid #edf0f7;
        border-radius: 22px;
        padding: 16px
    }

    .pmile-product-head {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: center;
        border-bottom: 1px solid #edf0f7;
        padding-bottom: 14px;
        margin-bottom: 14px
    }

    .pmile-product-head h3 {
        margin: 0;
        color: #172033
    }

    .pmile-product-head small {
        color: #7b8495;
        font-weight: 500
    }

    .pmile-product-progress {
        min-width: 220px
    }

    .pmile-stepper-wrap {
        width: 100%;
        overflow-x: auto;
        overflow-y: hidden;
        padding: 8px 2px 16px;
        scrollbar-width: thin
    }

    .pmile-stepper {
        display: flex;
        align-items: flex-start;
        gap: 0;
        min-width: max-content;
        position: relative;
        padding: 8px 0 4px
    }

    .pmile-step {
        position: relative;
        flex: 0 0 285px;
        min-width: 285px;
        padding: 0 12px
    }

    .pmile-step-connector {
        position: absolute;
        top: 22px;
        left: 50%;
        width: 100%;
        height: 4px;
        background: #e8eef8;
        z-index: 0
    }

    .pmile-step-connector.done {
        background: #12b76a
    }

    .pmile-step-connector.active {
        background: linear-gradient(90deg, #4f83f1, #e8eef8)
    }

    .pmile-step-node {
        position: relative;
        z-index: 2;
        width: 46px;
        height: 46px;
        border-radius: 50%;
        background: #f3f6fb;
        border: 4px solid #fff;
        box-shadow: 0 0 0 2px #dfe7f3, 0 8px 18px rgba(25, 42, 70, .12);
        display: grid;
        place-items: center;
        color: #667085;
        font-weight: 600;
        margin: 0 auto 12px
    }

    .pmile-step.status-completed .pmile-step-node {
        background: #12b76a;
        color: #fff;
        box-shadow: 0 0 0 2px #abefc6, 0 8px 18px rgba(18, 183, 106, .22)
    }

    .pmile-step.status-in-progress .pmile-step-node {
        background: #4f83f1;
        color: #fff;
        box-shadow: 0 0 0 2px #bfd7ff, 0 8px 18px rgba(79, 131, 241, .24)
    }

    .pmile-step.status-waiting .pmile-step-node {
        background: #f59e0b;
        color: #fff;
        box-shadow: 0 0 0 2px #fedf89, 0 8px 18px rgba(245, 158, 11, .22)
    }

    .pmile-step.status-blocked .pmile-step-node,
    .pmile-step.overdue .pmile-step-node {
        background: #ef4770;
        color: #fff;
        box-shadow: 0 0 0 2px #fecdca, 0 8px 18px rgba(239, 71, 112, .22)
    }

    .pmile-step-card {
        background: #fff;
        border: 1px solid #edf0f7;
        border-radius: 18px;
        padding: 14px;
        box-shadow: 0 8px 22px rgba(25, 42, 70, .06);
        min-height: 238px
    }

    .pmile-step.overdue .pmile-step-card {
        border-color: #fecdca;
        background: #fffafa
    }

    .pmile-step-card-head {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        align-items: flex-start;
        margin-bottom: 10px
    }

    .pmile-step-card .master-icon-btn {
        width: 34px;
        height: 34px;
        border: 0;
        border-radius: 11px;
        background: #eef3ff;
        color: #4f83f1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        flex: 0 0 34px
    }

    .pmile-step-card .master-icon-btn:hover {
        background: #e0ebff
    }

    .pmile-step-title-wrap {
        min-width: 0
    }

    .pmile-step-card h4 {
        margin: 0;
        color: #172033;
        font-size: 15px;
        line-height: 1.35;
        overflow-wrap: anywhere
    }

    .pmile-step-subtitle {
        display: block;
        color: #7b8495;
        font-size: 11px;
        font-weight: 600;
        margin-top: 3px
    }

    .pmile-step-badges {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        margin-bottom: 10px
    }

    .pmile-status,
    .pmile-public,
    .pmile-required,
    .pmile-overdue {
        border-radius: 999px;
        padding: 5px 8px;
        font-size: 10.5px;
        font-weight: 600
    }

    .pmile-status {
        background: #eef3ff;
        color: #4f83f1
    }

    .pmile-public {
        background: #e8fff3;
        color: #039855
    }

    .pmile-private {
        background: #f3f4f6;
        color: #667085
    }

    .pmile-required {
        background: #fff7e6;
        color: #b54708
    }

    .pmile-overdue {
        background: #fff1f3;
        color: #d92d20
    }

    .pmile-step-progress-row {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 8px;
        align-items: center;
        margin-bottom: 10px
    }

    .pmile-step-progress-row strong {
        font-size: 12px;
        color: #172033
    }

    .pmile-progress {
        height: 8px;
        background: #eef2f7;
        border-radius: 999px;
        overflow: hidden
    }

    .pmile-progress span {
        display: block;
        height: 100%;
        background: linear-gradient(90deg, #4f83f1, #12b76a)
    }

    .pmile-step.status-completed .pmile-progress span {
        background: #12b76a
    }

    .pmile-step.status-blocked .pmile-progress span {
        background: #ef4770
    }

    .pmile-step-dates {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        margin: 10px 0
    }

    .pmile-step-dates div {
        background: #f8faff;
        border: 1px solid #edf2ff;
        border-radius: 12px;
        padding: 8px
    }

    .pmile-step-dates span {
        display: block;
        color: #7b8495;
        font-size: 10.5px;
        font-weight: 600;
        text-transform: uppercase
    }

    .pmile-step-dates strong {
        display: block;
        margin-top: 3px;
        font-size: 12px;
        color: #172033
    }

    .pmile-step-owner {
        font-size: 12px;
        color: #667085;
        font-weight: 600
    }

    .pmile-notes {
        color: #344054;
        font-size: 12px;
        white-space: pre-wrap;
        margin: 8px 0 0
    }

    .pmile-notes.danger {
        color: #d92d20
    }

    .pmile-edit {
        margin-top: 10px
    }

    .pmile-edit summary {
        cursor: pointer;
        color: #4f83f1;
        font-weight: 600
    }

    .pmile-edit-form {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 9px;
        margin-top: 10px;
        background: #f8faff;
        border: 1px solid #edf2ff;
        border-radius: 14px;
        padding: 12px
    }

    .pmile-edit-form .pd-field.full {
        grid-column: 1/-1
    }

    .pmile-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center
    }

    .pmile-delete-form {
        margin: 0
    }

    .pmile-empty {
        padding: 18px;
        text-align: center;
        border: 1px dashed #d8deea;
        border-radius: 16px;
        color: #7b8495;
        background: #fbfcff;
        font-weight: 600
    }

    .pmile-modal {
        position: fixed;
        inset: 0;
        z-index: 10050;
        display: none
    }

    .pmile-modal.is-open {
        display: block
    }

    .pmile-modal-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, .58);
        backdrop-filter: blur(6px)
    }

    .pmile-modal-card {
        position: relative;
        width: min(780px, calc(100% - 24px));
        max-height: 92vh;
        margin: 4vh auto;
        background: #fff;
        border: 1px solid #edf0f7;
        border-radius: 24px;
        box-shadow: 0 24px 80px rgba(15, 23, 42, .28);
        overflow: hidden;
        display: flex;
        flex-direction: column
    }

    .pmile-modal-head {
        padding: 18px 20px;
        border-bottom: 1px solid #edf0f7;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px
    }

    .pmile-modal-head h3 {
        margin: 0;
        color: #172033;
        font-size: 20px
    }

    .pmile-modal-close {
        border: 0;
        background: #f3f6fb;
        color: #667085;
        width: 38px;
        height: 38px;
        border-radius: 13px;
        cursor: pointer
    }

    .pmile-modal-body {
        padding: 18px 20px;
        overflow: auto
    }

    .pmile-modal-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px
    }

    .pmile-modal-grid .pd-field.full {
        grid-column: 1/-1
    }

    .pmile-modal-footer {
        padding: 16px 20px;
        border-top: 1px solid #edf0f7;
        display: flex;
        justify-content: flex-end;
        gap: 10px
    }

    .pmile-modal-delete-form {
        padding: 0 20px 18px;
        display: flex;
        justify-content: flex-start
    }

    @media(max-width:1399px) {
        .pmile-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr))
        }

        .pmile-form {
            grid-template-columns: repeat(2, minmax(0, 1fr))
        }
    }

    @media(max-width:767px) {

        .pmile-stats,
        .pmile-form,
        .pmile-edit-form,
        .pmile-modal-grid {
            grid-template-columns: 1fr
        }

        .pmile-product-head {
            flex-direction: column;
            align-items: flex-start
        }

        .pmile-product-progress {
            min-width: 0;
            width: 100%
        }

        .pmile-step {
            flex-basis: 255px;
            min-width: 255px;
            padding: 0 9px
        }

        .pmile-step-card {
            min-height: 220px
        }

        .pmile-step-dates {
            grid-template-columns: 1fr
        }

        .pmile-modal-card {
            width: 100%;
            max-height: calc(100vh - 20px);
            margin: 10px auto;
            border-radius: 20px
        }

        .pmile-modal-footer {
            flex-direction: column-reverse
        }

        .pmile-modal-footer .pd-btn {
            width: 100%
        }

        .pmile-modal-delete-form {
            justify-content: center
        }
    }


    /* =========================================================
   PRODUCT MILESTONE - STEP INDICATOR
   ========================================================= */

    .pmile-step-wrapper {
        width: 100%;
        background: #fff;
        /*border-radius: 14px;*/
        padding: 20px;
        /*border: 1px solid #e8edf5;*/
        box-sizing: border-box;
    }

    .pmile-step-scroll {
        width: 100%;
        overflow-x: auto;
        overflow-y: hidden;
        padding: 10px 5px 20px;
    }

    /* Timeline container */

    .pmile-step-line {
        display: flex;
        align-items: flex-start;
        position: relative;
        min-width: max-content;
        padding: 0 25px;
    }

    /* Horizontal connecting line */

    .pmile-step-line::before {
        content: "";
        position: absolute;
        left: 65px;
        right: 65px;
        top: 23px;
        height: 2px;
        background: #dce3ec;
        z-index: 0;
    }


    /* =========================================================
   INDIVIDUAL STEP
   ========================================================= */

    .pmile-step {
        width: 100px;
        min-width: 100px;
        position: relative;
        text-align: center;
        z-index: 1;
    }


    /* Space between steps */

    .pmile-step+.pmile-step {
        margin-left: 25px;
    }


    /* =========================================================
   STEP CIRCLE
   ========================================================= */

    .pmile-step-circle {
        width: 46px;
        height: 46px;
        border-radius: 50%;
        background: #fff;
        border: 2px solid #cbd4df;

        margin: 0 auto 12px;

        display: flex;
        align-items: center;
        justify-content: center;

        font-size: 14px;
        font-weight: 600;

        position: relative;
        z-index: 2;

        transition: all .2s ease;
    }


    /* Upcoming */

    .pmile-step.upcoming .pmile-step-circle {
        color: #8b96a7;
        border-color: #cbd4df;
        background: #fff;
    }


    /* Completed */

    .pmile-step.completed .pmile-step-circle {
        color: #fff;
        background: #12b76a;
        border-color: #12b76a;
    }


    /* Current */

    .pmile-step.current .pmile-step-circle {
        color: #fff;
        background: #2563eb;
        border-color: #2563eb;

        box-shadow:
            0 0 0 5px rgba(37, 99, 235, .10);
    }


    /* Current animated ring */

    .pmile-step.current .pmile-step-circle::before {
        content: "";
        position: absolute;
        inset: -6px;

        border-radius: 50%;
        border: 2px solid rgba(37, 99, 235, .35);
    }


    /* =========================================================
   CONNECTING LINE COLORS
   ========================================================= */

    /* Completed connection */

    .pmile-step.completed::after {
        content: "";
        position: absolute;

        top: 22px;
        left: calc(50% + 23px);
        width: calc(100% + 25px);
        height: 3px;

        background: #12b76a;

        z-index: 0;
    }


    /* Current connection should remain grey */

    .pmile-step.current::after {
        content: "";
        position: absolute;

        top: 22px;
        left: calc(50% + 23px);
        width: calc(100% + 25px);
        height: 2px;

        background: #dce3ec;

        z-index: 0;
    }


    /* Last item should not create a line */

    .pmile-step:last-child::after {
        display: none;
    }


    /* =========================================================
   TITLE
   ========================================================= */

    .pmile-step-content {
        position: relative;
    }

    .pmile-step-title-row {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-height: 42px;
    }

    .pmile-step-title-row h4 {
        margin: 0;
        color: #172b4d;
        font-size: 14px;
        font-weight: 600;
        line-height: 1.35;
    }


    /* Edit button */

    .pmile-step-edit {
        width: 26px !important;
        height: 26px !important;

        padding: 0 !important;

        flex-shrink: 0;
    }


    /* =========================================================
   STATUS
   ========================================================= */

    .pmile-step-status {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 5px;

        margin-top: 8px;
        min-height: 25px;
    }

    .pmile-step-status-badge {
        display: inline-flex;
        align-items: center;

        padding: 4px 10px;

        border-radius: 20px;

        font-size: 11px;
        font-weight: 600;

        white-space: nowrap;
    }


    /* Completed */

    .pmile-step.completed .pmile-step-status-badge {
        background: #e9f9f1;
        color: #079455;
    }


    /* Current */

    .pmile-step.current .pmile-step-status-badge {
        background: #eaf2ff;
        color: #2563eb;
    }


    /* Upcoming */

    .pmile-step.upcoming .pmile-step-status-badge {
        background: #f1f3f5;
        color: #667085;
    }


    /* Overdue */

    .pmile-step-overdue {
        font-size: 10px;
        padding: 4px 7px;
        border-radius: 12px;

        background: #fff1f3;
        color: #d92d20;
    }


    /* =========================================================
   DATE
   ========================================================= */

    .pmile-step-date {
        margin-top: 8px;

        font-size: 12px;
        color: #667085;

        white-space: nowrap;
    }


    /* =========================================================
   CURRENT STEP DETAIL CARD
   ========================================================= */

    .pmile-current-card {
        margin-top: 18px;

        width: 100%;

        display: grid;

        grid-template-columns:
            1.5fr 1fr 1fr 1.5fr;

        gap: 25px;

        align-items: center;

        padding: 25px 30px;

        background: #f8fbff;

        border: 1px solid #cfe0ff;

        border-radius: 14px;

        box-sizing: border-box;
    }


    /* Current step */

    .pmile-current-main {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .pmile-current-icon {
        width: 54px;
        height: 54px;

        flex-shrink: 0;

        border-radius: 50%;

        background: #2563eb;

        color: #fff;

        display: flex;
        align-items: center;
        justify-content: center;

        font-size: 20px;

        box-shadow: 0 0 0 7px rgba(37, 99, 235, .08);
    }

    .pmile-current-label {
        color: #2563eb;

        font-size: 13px;
        font-weight: 600;

        margin-bottom: 3px;
    }

    .pmile-current-main h3 {
        margin: 0 0 7px;

        color: #172b4d;

        font-size: 18px;
        font-weight: 500;
    }

    .pmile-current-status {
        display: inline-flex;

        padding: 5px 11px;

        background: #eaf2ff;
        color: #2563eb;

        border-radius: 20px;

        font-size: 11px;
        font-weight: 600;
    }


    /* Columns */

    .pmile-current-column {
        padding-left: 25px;

        border-left: 1px solid #e4eaf2;
    }

    .pmile-current-column label,
    .pmile-current-progress label {
        display: block;

        color: #2563eb;

        font-size: 13px;
        font-weight: 600;

        margin-bottom: 8px;
    }

    .pmile-current-column strong {
        color: #344054;

        font-size: 14px;
        font-weight: 600;
    }


    /* =========================================================
   CURRENT PROGRESS
   ========================================================= */

    .pmile-current-progress {
        padding-left: 25px;

        border-left: 1px solid #e4eaf2;
    }

    .pmile-progress-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .pmile-progress-header strong {
        color: #2563eb;
        font-size: 14px;
    }

    .pmile-progress-bar {
        width: 100%;
        height: 9px;

        background: #e8edf4;

        border-radius: 10px;

        overflow: hidden;

        margin-top: 4px;
    }

    .pmile-progress-bar span {
        display: block;

        height: 100%;

        background: #2563eb;

        border-radius: inherit;

        transition: width .3s ease;
    }

    .pmile-on-track {
        margin-top: 9px;

        color: #12b76a;

        font-size: 12px;
        font-weight: 600;
    }

    .pmile-on-track i {
        margin-right: 4px;
    }

    .pmile-on-track-danger {
        color: #d92d20;
    }


    /* =========================================================
   SCROLLBAR
   ========================================================= */

    .pmile-step-scroll::-webkit-scrollbar {
        height: 6px;
    }

    .pmile-step-scroll::-webkit-scrollbar-track {
        background: #f1f3f5;
        border-radius: 10px;
    }

    .pmile-step-scroll::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }


    /* =========================================================
   RESPONSIVE
   ========================================================= */

    @media (max-width: 1000px) {

        .pmile-current-card {
            grid-template-columns: 1fr 1fr;
        }

        .pmile-current-column,
        .pmile-current-progress {
            border-left: none;
            padding-left: 0;
        }

    }


    @media (max-width: 650px) {

        .pmile-step-wrapper {
            padding: 20px 10px;
        }

        .pmile-step-line {
            padding: 0 20px;
        }

        .pmile-step {
            width: 145px;
            min-width: 145px;
        }

        .pmile-step+.pmile-step {
            margin-left: 15px;
        }

        .pmile-current-card {
            grid-template-columns: 1fr;

            padding: 20px;
        }

        .pmile-current-column,
        .pmile-current-progress {
            border-left: none;
            border-top: 1px solid #e4eaf2;

            padding-left: 0;
            padding-top: 15px;
        }

    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var copyBtn = document.getElementById('copyPortalLink');
        if (copyBtn) {
            copyBtn.addEventListener('click', function() {
                var input = document.getElementById('portalLinkInput');
                input.select();
                input.setSelectionRange(0, 99999);
                document.execCommand('copy');
                copyBtn.innerHTML = '<i class="fa-solid fa-check"></i> Copied';
                setTimeout(function() {
                    copyBtn.innerHTML = '<i class="fa-solid fa-copy"></i> Copy';
                }, 1800);
            });
        }

        var tabButtons = document.querySelectorAll('.pd-tab-btn');
        var tabPanels = document.querySelectorAll('.pd-tab-panel');
        var storageKey = 'project_show_active_tab_{{ $project->id }}';

        function openProjectTab(tabName, updateHash) {
            var found = false;
            tabButtons.forEach(function(btn) {
                var isActive = btn.getAttribute('data-tab') === tabName;
                btn.classList.toggle('active', isActive);
                btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
                if (isActive) found = true;
            });
            if (!found) {
                tabName = 'overview';
                tabButtons.forEach(function(btn) {
                    var isActive = btn.getAttribute('data-tab') === tabName;
                    btn.classList.toggle('active', isActive);
                    btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });
            }
            tabPanels.forEach(function(panel) {
                panel.classList.toggle('active', panel.getAttribute('data-tab-panel') === tabName);
            });
            try {
                localStorage.setItem(storageKey, tabName);
            } catch (e) {}
            if (updateHash) {
                history.replaceState(null, '', '#' + tabName);
            }
        }

        tabButtons.forEach(function(btn) {
            btn.addEventListener('click', function() {
                openProjectTab(btn.getAttribute('data-tab'), true);
            });
        });

        document.querySelectorAll('[data-tab-jump]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                openProjectTab(btn.getAttribute('data-tab-jump'), true);
                window.scrollTo({
                    top: document.querySelector('.pd-tabs-shell').offsetTop - 90,
                    behavior: 'smooth'
                });
            });
        });

        var initialTab = window.location.hash ? window.location.hash.replace('#', '') : '';
        if (!initialTab) {
            try {
                initialTab = localStorage.getItem(storageKey) || 'overview';
            } catch (e) {
                initialTab = 'overview';
            }
        }
        openProjectTab(initialTab, false);
    });
    
    document.addEventListener('DOMContentLoaded', function () {
        
        function openModal(modal){
            modal.classList.add('show');
            document.body.classList.add('master-modal-open');
        }
        
        function closeModal(modal){
            modal.classList.remove('show');
            document.body.classList.remove('master-modal-open');
        }
        
        function setValue(form, name, value) {
            const field = form.elements[name];
            if (!field) return;
        
            if (field.type === 'checkbox') {
                field.checked = !!value;
            } else {
                field.value = value ?? '';
            }
        }
    
        document.addEventListener('keydown',function(e){
            if(e.key !== 'Escape') return;
            document.querySelectorAll('.master-modal.show').forEach(modal=>{
                closeModal(modal);
            });
        });
        
        document.querySelectorAll('.master-modal').forEach(modal=>{
            modal.addEventListener('click',function(e){
                if(e.target===modal){
                    closeModal(modal);
                }
            });
        });
            
        document.querySelectorAll('[data-close-modal]').forEach(btn=>{
            btn.addEventListener('click',()=>{
                closeModal(btn.closest('.master-modal'));
            });
        });

        const editCommentModal = document.getElementById('editCommentModal');
        const editCommentForm = document.getElementById('editCommentForm');
        
        document.querySelectorAll('.editCommentBtn').forEach(btn => {

            btn.addEventListener('click', function () {
    
                const comment = JSON.parse(this.dataset.comment);
    
                editCommentForm.action = `/project-comments/${comment.id}`;
    
                setValue(editCommentForm, 'project_product_id', comment.project_product_id);
                setValue(editCommentForm, 'body', comment.body);
                setValue(editCommentForm, 'is_pinned', comment.is_pinned);
                setValue(editCommentForm, 'is_public', comment.is_public);
    
                openModal(editCommentModal);
            });
        });
        
        const addCommentModal = document.getElementById('addCommentModal');
        document.getElementById('openAddCommentModal')?.addEventListener('click', () => openModal(addCommentModal));
        document.getElementById('openAddCommentModal2')?.addEventListener('click', () => openModal(addCommentModal));
        
        const addAttachmentModal = document.getElementById('addAttachmentModal');
        document.getElementById('openAddAttachmentModal')?.addEventListener('click', () => openModal(addAttachmentModal));
        document.getElementById('openAddAttachmentModal2')?.addEventListener('click', () => openModal(addAttachmentModal));
    
    });
    
</script>
@endsection
