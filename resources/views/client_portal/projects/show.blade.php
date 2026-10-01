@extends('client_portal.layouts.app')

@section('title', $project->name)
@section('page-title', 'Project Detail')

@section('content')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/client-portal-project-show.css') }}">
    <div id="projectShowRoot" data-project-id="{{ $project->id }}" hidden></div>
@endpush

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
                                data-confirm="Delete this comment?"">
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
                            <div class="master-field" style="margin-bottom:15px;">
                                <label class="master-label">Product (optional)</label>
                                <select class="master-select" name="project_product_id">
                                    <option value="">Project level</option>
                                    @foreach ($project->products as $projectProduct)
                                        <option value="{{ $projectProduct->id }}">{{ $projectProduct->product_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="master-field full">
                                <label class="master-label">Comment</label>
                                <textarea class="master-textarea" name="body" rows="3" required placeholder="Add internal/client-visible comment..."></textarea>
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
                            <div class="master-field" style="margin-bottom:15px;">
                                <label class="master-label">Product (optional)</label>
                                <select class="master-select" name="project_product_id">
                                    <option value="">Project level</option>
                                    @foreach ($project->products as $projectProduct)
                                        <option value="{{ $projectProduct->id }}">{{ $projectProduct->product_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="master-field full">
                                <label class="master-label">Comment</label>
                                <textarea class="master-textarea" name="body" rows="3" required placeholder="Add internal/client-visible comment..."></textarea>
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
                                        data-confirm="Delete attachment?"">@csrf
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
                            <div class="master-field">
                                <label class="master-label">Product (optional)</label>
                                <select class="master-select" name="project_product_id">
                                    <option value="">Project level</option>
                                    @foreach ($project->products as $projectProduct)
                                        <option value="{{ $projectProduct->id }}">{{ $projectProduct->product_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="master-field">
                                <label class="master-label">Category</label>
                                <select class="master-select" name="category">
                                    <option value="client_document">Client Document</option>
                                    <option value="artwork">Artwork</option>
                                    <option value="payment_proof">Payment Proof</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="master-field">
                                <label class="master-label">Title</label>
                                <input class="master-input" type="text" name="title"
                                    placeholder="Document name"></div>
                            <div class="master-field">
                                <label class="master-label">Files</label>
                                <input class="master-input" type="file" name="attachments[]" multiple
                                    required
                                    accept=".jpg,.jpeg,.png,.webp,.gif,.heic,.heif,.pdf,.doc,.docx,.xls,.xlsx,.csv,.ppt,.pptx,.txt,.zip">
                                <input type="checkbox" name="is_public" value="1" hidden>
                            </div>
                            <div class="master-field pd-span-2">
                                <label class="master-label">Notes</label>
                                <textarea class="master-textarea" name="notes" rows="2"></textarea>
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


@push('scripts')
    <script src="{{ asset('assets/js/client-portal-project-show.js') }}"></script>
@endpush
@endsection
