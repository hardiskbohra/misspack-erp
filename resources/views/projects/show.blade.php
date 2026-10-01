@extends('layouts.app')

@section('page-title', $project->name)

@section('content')
    @php
        $totals = $project->paymentTotals();
        $portalUrl = route('projects.public.show', $project->public_token);
    @endphp
    <div class="pd-page pd-tabs-page">
        <div class="pd-hero">
            <div>
                <p class="pd-eyebrow">{{ $project->project_number }}</p>
                <h1>{{ $project->name }}</h1>
                <div class="pd-hero-meta">
                    <span><i class="fa-solid fa-building"></i>
                        {{ $project->client ? $project->client->company_name : 'Client #' . $project->client_id }}</span>
                    <span><i class="fa-solid fa-user-check"></i>
                        {{ $project->assignedUser ? $project->assignedUser->name : 'Unassigned' }}</span>
                    <span><i class="fa-solid fa-calendar-days"></i> Start:
                        {{ optional($project->start_date)->format('d M Y') ?: 'Not set' }}</span>
                    <span><i class="fa-solid fa-calendar-days"></i> Target:
                        {{ optional($project->target_date)->format('d M Y') ?: 'Not set' }}</span>
                </div>
            </div>
            <div class="pd-hero-actions">
                <a href="{{ route('projects.index') }}" class="master-btn master-btn-light"><i class="fa-solid fa-arrow-left"></i>
                    Back</a>
                <a href="{{ route('projects.edit', $project) }}" class="master-btn master-btn-light"><i class="fa-solid fa-pen"></i>
                    Edit</a>
                @if ($project->show_client_portal)
                    <a href="{{ $portalUrl }}" target="_blank" class="master-btn master-btn-primary"><i
                            class="fa-solid fa-eye"></i> Client Portal</a>
                @endif
            </div>
        </div>

        <div class="pd-status-card">
            <div class="pd-status-left">
                <div class="pd-progress-ring">
                    <strong>{{ $project->progress_percent }}%</strong>
                    <span>Progress</span>
                </div>
                <div>
                    <div class="pd-chip-row">
                        <span class="pd-chip pd-status-{{ $project->status }}">{{ $project->statusLabel() }}</span>
                        <span class="pd-chip pd-health-{{ $project->health }}">{{ $project->healthLabel() }}</span>
                        <span class="pd-chip">{{ $project->stageLabel() }}</span>
                        <span class="pd-chip">{{ $project->priorityLabel() }}</span>
                    </div>
                    <div class="pd-progress"><span style="width: {{ $project->progress_percent }}%"></span></div>
                    @if ($project->show_client_portal)
                        <div class="pd-copy-row">
                            <input class="master-input" type="text" value="{{ $portalUrl }}" readonly id="portalLinkInput">
                            <button type="button" class="master-btn master-btn-soft master-btn-sm" id="copyPortalLink"><i
                                    class="fa-solid fa-copy"></i> Copy</button>
                        </div>
                    @endif
                </div>
            </div>
            <form method="POST" action="{{ route('projects.status.update', $project) }}" class="master-status-form">
                @csrf
                @method('PATCH')
                <div class="master-field">
                    <select class="master-select" name="status">
                        @foreach ($statusOptions as $key => $label)
                            <option value="{{ $key }}" {{ $project->status === $key ? 'selected' : '' }}>
                                {{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="master-field">
                    <select class="master-select" name="stage">
                        @foreach ($stageOptions as $key => $label)
                            <option value="{{ $key }}" {{ $project->stage === $key ? 'selected' : '' }}>
                                {{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="master-field">
                    <select class="master-select" name="health">
                        @foreach ($healthOptions as $key => $label)
                            <option value="{{ $key }}" {{ $project->health === $key ? 'selected' : '' }}>
                                {{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="master-field">
                    <input class="master-input" type="number" name="progress_percent" min="0" max="100"
                        value="{{ $project->progress_percent }}">
                </div>
                <div class="pd-actions">
                    <button class="master-btn master-btn-primary" type="submit">Update</button>
                </div>
            </form>
        </div>

        <div class="pd-metrics">
            <div class="pd-metric"><span>Estimated
                    Value</span><strong>{{ $project->currency == 'INR' ? '₹' : $project->currency }}{{ number_format((float) $project->estimated_value, 2) }}</strong>
            </div>
            <div class="pd-metric"><span>Payment Inward</span><strong
                    class="pd-green">{{ $project->currency == 'INR' ? '₹' : $project->currency }}{{ number_format($totals['inward'], 2) }}</strong>
            </div>
            <div class="pd-metric"><span>Expenses / Outward</span><strong
                    class="pd-red">{{ $project->currency == 'INR' ? '₹' : $project->currency }}{{ number_format($totals['outward'], 2) }}</strong>
            </div>
            <div class="pd-metric">
                <span>Outstanding</span><strong>{{ $project->currency == 'INR' ? '₹' : $project->currency }}{{ number_format($totals['outstanding'], 2) }}</strong>
            </div>
            <div class="pd-metric">
                <span>Profit/Loss</span><strong class="{{ ($totals['inward'] - $totals['outward'] > 0 ? 'pd-green' : 'pd-red') }}">{{ $project->currency == 'INR' ? '₹' : $project->currency }}{{ number_format(($totals['inward'] - $totals['outward']), 2) }}</strong>
            </div>
        </div>

        <div class="pd-tabs-shell" data-project-id="{{ $project->id }}">
            <div class="pd-tabs-nav" role="tablist" aria-label="Project sections">
                <button type="button" class="pd-tab-btn active" data-tab="overview" role="tab" aria-selected="true">Overview</button>
                <button type="button" class="pd-tab-btn" data-tab="products" role="tab" aria-selected="false">Products
                    <span>{{ $project->products->count() }}</span></button>
                <button type="button" class="pd-tab-btn" data-tab="milestones" role="tab" aria-selected="false">
                    Milestones <span>{{ $project->milestones->count() }}</span></button>
                <button type="button" class="pd-tab-btn" data-tab="comments" role="tab" aria-selected="false">
                    Comments <span>{{ $project->comments->count() }}</span></button>
                <button type="button" class="pd-tab-btn" data-tab="attachments" role="tab" aria-selected="false">Attachments
                    <span>{{ $project->attachments->count() }}</span></button>
                <button type="button" class="pd-tab-btn" data-tab="payments" role="tab" aria-selected="false">Payments
                    <span>{{ $project->payments->count() + $project->cashflowEntries->count() }}</span></button>
                <button type="button" class="pd-tab-btn" data-tab="shipments" role="tab" aria-selected="false">Shiments
                    <span>{{ $project->shipments->count() }}</span></button>
                <button type="button" class="pd-tab-btn" data-tab="tracking" role="tab" aria-selected="false">Activities
                    <span>{{ $project->trackingUpdates->count() }}</span></button>
                <button type="button" class="pd-tab-btn" data-tab="logs" role="tab" aria-selected="false">Logs
                    <span>{{ $project->logs->count() }}</span></button>
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
                                        To</span><strong>{{ $project->assignedUser ? $project->assignedUser->name : 'Unassigned' }}</strong>
                                </div>
                                <div><span>Start
                                        Date</span><strong>{{ optional($project->start_date)->format('d M Y') ?: '-' }}</strong>
                                </div>
                                <div><span>Target
                                        Date</span><strong>{{ optional($project->target_date)->format('d M Y') ?: '-' }}</strong>
                                </div>
                                <div>
                                    <span>Quote</span><strong>{{ $project->relationLoaded('customerQuote') && $project->customerQuote ? $project->customerQuote->quote_number : '-' }}</strong>
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
                                <button type="button" class="pd-summary-item" data-tab-jump="milestones">
                                    <i class="fa-solid fa-flag-checkered"></i>
                                    <span><strong>{{ $project->milestones->count() }} Milestones</strong><small>Product-wise timeline and public/internal stages</small></span>
                                </button>
                                <button type="button" class="pd-summary-item" data-tab-jump="attachments" style="background:#fff7e6">
                                    <i class="fa-solid fa-paperclip"></i>
                                    <span><strong>{{ $project->attachments->count() }} Attachments</strong><small>Vendor
                                            invoice, packing list, artwork, photos</small></span>
                                </button>
                                <button type="button" class="pd-summary-item" data-tab-jump="payments" style="background:#fff1f3">
                                    <i class="fa-solid fa-indian-rupee-sign"></i>
                                    <span><strong>{{ $project->payments->count() + $project->cashflowEntries->count() }} Payment
                                            Entries</strong><small>Inward/outward payment tracking</small></span>
                                </button>
                                <button type="button" class="pd-summary-item" data-tab-jump="tracking" style="background:#e8fff3">
                                    <i class="fa-solid fa-location-dot"></i>
                                    <span><strong>{{ $project->trackingUpdates->count() }} Activity
                                            Updates</strong><small>Latest public/internal project activities</small></span>
                                </button>
                            </div>
                            <div class="pd-latest-box">
                                <strong>Latest Activity</strong>
                                @forelse($project->trackingUpdates->take(4) as $tracking)
                                    <div class="pd-latest-row">
                                        <span>{{ $tracking->title }}</span>
                                        <small>{{ optional($tracking->occurred_at)->format('d M Y') ?: '-' }}</small>
                                    </div>
                                @empty
                                    <div class="pd-empty">No activity updates yet.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </section>

                <!--Product-->
                <section class="pd-tab-panel" id="pd-tab-products" data-tab-panel="products" role="tabpanel">
                    <div class="pd-section-head" style="padding:15px;">
                        <div>
                            <p class="pd-eyebrow">Products</p>
                            <h2>Project Products</h2>
                        </div>
                        <div>
                            <span class="pd-count">{{ $project->products->count() }} items</span> &nbsp;
                            <button type="button" class="master-btn master-btn-primary addProductBtn" id="openAddProductModal"><i class="fas fa-plus"></i> Add Product</button>
                        </div>
                    </div>
                                        
                    <div class="master-table-wrap pd-card">
                        <table class="master-table">
                            <thead>
                                <tr>
                                    <th width="70">Image</th>
                                    <th>Product</th>
                                    <th>Qty</th>
                                    <th>Amount</th>
                                    <th>Specifications</th>
                                    <th>Vendor</th>
                                    <th width="120">Actions</th>
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
                                            @php
                                                $milestone = $projectProduct->currentMilestone();
                                            @endphp
                                            
                                            <span class="pd-chip pd-status-{{ $milestone?->status }}"
                                                  style="font-size:11px;padding:5px 10px;">
                                                {{ $milestone?->title ?? 'No Milestone' }}
                                            </span>
                                        </td>
                                        <td><strong>{{ number_format($projectProduct->quantity) }} {{ $projectProduct->unit }}</strong></td>
                                        <td><strong>{{ $projectProduct->currency == 'INR' ? '₹' : $projectProduct->currency }}{{ number_format($projectProduct->total_amount,2) }}</strong></td>
                                        <td><strong style="font-size:11px">{!! $projectProduct?->notes ? nl2br(e($projectProduct->notes)) : '-' !!}</strong></td>
                                        <td><strong>{{ optional($projectProduct->vendor)->contact_person_name ?: '-' }}</strong><div class="master-sub">{{ $projectProduct->vendor_invoice_number ?: 'No Vendor Invoice' }}</div></td>
                                        <td>
                                            <div class="master-row-actions">
                                                <button type="button" class="master-icon-btn editProductBtn" data-product='@json($projectProduct)'><i class="fas fa-pen"></i></button>
                                                <form method="POST"
                                                    action="{{ route('projects.products.destroy',$projectProduct) }}" onsubmit="return confirm('Remove product?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="master-icon-btn danger"><i class="fas fa-trash"></i></button>
                                                </form>
                                            </div>
                                        </td>
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
                
                <!--Add Product-->
                <div class="master-modal" id="addProductModal" aria-hidden="true">
                    <div class="master-modal-card" style="max-width:900px;">
                        <form method="POST" action="{{ route('projects.products.store', $project) }}">
                            @csrf
                            <div class="master-modal-header">
                                <div class="master-modal-heading">
                                    <div>
                                        <h3 class="master-modal-title">Add Product</h3>
                                    </div>
                                </div>
                                <button type="button" class="master-modal-close" data-close-modal id="closeAddProductModal">×</button>
                            </div>
                            <div class="master-modal-body">
                                <div class="master-modal-grid">
                                    <div class="master-field">
                                        <label class="master-label">Mapped Product</label>
                                        <select class="master-select" name="product_id" required>
                                            <option value="">Manual product</option>
                                            @foreach($products as $product)
                                                <option value="{{ $product->id }}">{{ $product->product_number }} - {{ $product->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="master-field" style="display:none;">
                                        <label class="master-label">Product Name</label>
                                        <input class="master-input" type="text" name="product_name" placeholder="Required if product not selected">
                                    </div>
                                    <div class="master-field small">
                                        <label class="master-label">Qty</label>
                                        <input class="master-input" type="number" step="0.001" min="0.001" name="quantity" value="1" required>
                                    </div>
                                    <div class="master-field small" style="display:none;">
                                        <label class="master-label">Unit</label>
                                        <input class="master-input" type="text" name="unit" value="pcs">
                                    </div>
                                    <div class="master-field small">
                                        <label class="master-label">Unit Price</label>
                                        <input class="master-input" type="number" step="0.01" min="0" name="unit_price" value="0">
                                    </div>
                                    <div class="master-field">
                                        <label class="master-label">Status</label>
                                        <select class="master-select" name="status">
                                            @foreach($productStatusOptions as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="master-field">
                                        <label class="master-label">Vendor</label>
                                        <select class="master-select" name="vendor_id">
                                            <option value="">Unassigned</option>
                                            @foreach ($vendors as $vendor)
                                                <option value="{{ $vendor->id }}">{{ $vendor->contact_person_name }} ({{ $vendor->vendor_name }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="master-field">
                                        <label class="master-label">Vendor Invoice Number</label>
                                        <input class="master-input" type="text" name="vendor_invoice_number">
                                    </div>
                                    <div class="master-field">
                                        <label class="master-label">Expected Ready</label>
                                        <input class="master-input" type="date" name="expected_ready_date">
                                    </div>
                                    <div class="master-field pd-span-2">
                                        <label class="master-label">Notes</label>
                                        <textarea class="master-textarea" rows="5" name="notes" placeholder="Artwork, production, packaging notes"></textarea>
                                    </div>
                                    <input type="hidden" name="currency" value="{{ $project->currency }}">
                                </div>
                            </div>
                            <div class="master-modal-footer">
                                <button type="button" class="master-btn master-btn-light" id="cancelAddProductModal" data-close-modal>Cancel</button>
                                <button class="master-btn master-btn-primary" type="submit"> Add Product</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!--Edit Product-->
                <div class="master-modal" id="editProductModal">
                    <div class="master-modal-card" style="max-width:900px;">
                        <form id="editProductForm" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="master-modal-header">
                                <div><h3 class="master-modal-title">Edit Product</h3></div>
                                <button type="button" class="master-modal-close" id="closeEditProductModal" data-close-modal>×</button>
                            </div>
                            <div class="master-modal-body">
                                <div class="master-modal-grid">
                        
                                    <div class="master-field"> 
                                        <label class="master-label">Mapped Product</label> 
                                        <select class="master-select" name="product_id"> 
                                            <option value="">Manual product</option> 
                                            @foreach ($products as $product) 
                                                <option value="{{ $product->id }}"> {{ $product->name }}{{ $product->sku ? ' - ' . $product->sku : '' }}</option> 
                                            @endforeach 
                                        </select> 
                                    </div> 
                                    <div class="master-field small">
                                        <label class="master-label">Qty</label>
                                        <input class="master-input" type="number" step="0.001" min="0.001" name="quantity" value="1" required>
                                    </div> 
                                    <div class="master-field small">
                                        <label class="master-label">Unit Price</label>
                                        <input class="master-input" type="number" step="0.01" min="0" name="unit_price" value="0">
                                    </div> 
                                    <div class="master-field">
                                        <label class="master-label">Expected Ready</label>
                                        <input class="master-input" type="date" name="expected_ready_date">
                                    </div>
                                    <!--<div class="master-field">-->
                                    <!--    <label class="master-label">Assignee</label>-->
                                    <!--    <select class="master-select" name="assigned_to">-->
                                    <!--        <option value="">Unassigned</option>-->
                                    <!--        @foreach ($users as $user)-->
                                    <!--            <option value="{{ $user->id }}">{{ $user->name }}</option>-->
                                    <!--        @endforeach-->
                                    <!--    </select>-->
                                    <!--</div>-->
                                    <div class="master-field">
                                        <label class="master-label">Vendor</label>
                                        <select class="master-select" name="vendor_id">
                                            <option value="">Unassigned</option>
                                            @foreach ($vendors as $vendor)
                                                <option value="{{ $vendor->id }}">{{ $vendor->contact_person_name }} ({{ $vendor->vendor_name }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="master-field">
                                        <label class="master-label">Vendor Invoice Number</label>
                                        <input class="master-input" type="text" name="vendor_invoice_number">
                                    </div>
                                    <div class="master-field">
                                        <label class="master-label">Status</label>
                                        <select class="master-select" name="status"> 
                                            @foreach ($productStatusOptions as $key => $label) 
                                                <option value="{{ $key }}">{{ $label }}</option> 
                                            @endforeach 
                                        </select> 
                                    </div> 
                                    <div class="master-field pd-span-2">
                                        <label class="master-label">Notes</label>
                                        <textarea class="master-textarea" rows="5" name="notes" placeholder="Artwork, production, packaging notes"></textarea>
                                    </div>
                                    <!--<div class="master-field">-->
                                    <!--    <label class="master-label">Stage</label>-->
                                    <!--    <select class="master-select" name="stage"> -->
                                    <!--        @foreach ($productStageOptions as $key => $label) -->
                                    <!--            <option value="{{ $key }}">{{ $label }}</option> -->
                                    <!--        @endforeach -->
                                    <!--    </select> -->
                                    <!--</div>-->
                                </div>
                            </div>
                            <div class="master-modal-footer">
                                <button type="button" class="master-btn master-btn-light" id="cancelEditProductModal" data-close-modal>Cancel</button>
                                <button class="master-btn master-btn-primary" type="submit"> Save Product</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!--Milestones-->
                @include('projects.partials.milestones-tab')

                <!--Activity-->
                <section class="pd-tab-panel" id="pd-tab-tracking" data-tab-panel="tracking" role="tabpanel">
                    <div class="pd-section-head" style="padding:15px;">
                        <div>
                            <p class="pd-eyebrow">Logs</p>
                            <h2>Activity Timelines</h2>
                        </div>
                        <div>
                            <span class="pd-count">{{ $project->trackingUpdates->count() }} activities</span> &nbsp;
                            <button type="button" class="master-btn master-btn-primary addTrackingBtn" id="openAddTrackingModal"><i class="fas fa-plus"></i> Add Activity</button>
                        </div>
                    </div>
                    
                    <div class="master-table-wrap pd-card">
                        <table class="master-table">
                            <thead>
                                <tr>
                                    <th width="160">Date</th>
                                    <th>Title</th>
                                    <th width="160">Product</th>
                                    <th width="140">Status</th>
                                    <th width="150">Activity By</th>
                                    <th width="90">Visibility</th>
                                    <th width="90">Actions</th>
                                </tr>
                            </thead>
                    
                            <tbody>
                                @forelse($project->trackingUpdates as $tracking)
                                    <tr>
                                        <td>
                                            {{ optional($tracking->occurred_at)->format('d M Y') }}
                                            <div class="master-sub">
                                                {{ optional($tracking->occurred_at)->format('h:i A') }}
                                            </div>
                                        </td>
                                        <td><strong>{{ $tracking->title }}</strong></td>
                                        <td><strong>{{ optional($tracking->product)->product_name ?? '-' }}</strong></td>
                                        <td><span class="pd-chip pd-status-{{ $tracking->status }}">{{ $tracking->statusLabel() }}</span></td>
                                        <td><strong>{{ $tracking->location ?: '-' }}</strong></td>
                                        <td><span class="pd-chip {{ $tracking->is_public ? 'pd-public' : '' }}">{{ $tracking->is_public ? 'Public' : 'Internal' }}</span></td>
                                        <td>
                                            <div class="master-row-actions">
                                                <button type="button" class="master-icon-btn editTrackingBtn" data-tracking='@json($tracking)'><i class="fas fa-pen"></i></button>
                                                <form method="POST"
                                                    action="{{ route('projects.tracking.destroy',$tracking) }}" onsubmit="return confirm('Remove tracking?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="master-icon-btn danger"><i class="fas fa-trash"></i></button>
                                                </form>
                                            </div>
                                        </td>
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
                
                <!--Add Tracking-->
                <div class="master-modal" id="addTrackingModal" aria-hidden="true">
                    <div class="master-modal-card" style="max-width:900px;">
                        <form method="POST" action="{{ route('projects.tracking.store', $project) }}">
                            @csrf
                            <div class="master-modal-header">
                                <div class="master-modal-heading">
                                    <div>
                                        <h3 class="master-modal-title">Add Activity</h3>
                                    </div>
                                </div>
                                <button type="button" class="master-modal-close" id="closeAddTrackingModal" data-close-modal>×</button>
                            </div>
                            <div class="master-modal-body">
                                <div class="master-modal-grid">
                                    <div class="master-field"><label class="master-label">Product (optional)</label><select class="master-select" name="project_product_id">
                                            <option value="">Project level</option>
                                            @foreach ($project->products as $projectProduct)
                                                <option value="{{ $projectProduct->id }}">{{ $projectProduct->product_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="master-field"><label class="master-label">Title</label><input class="master-input" type="text" name="title"
                                            placeholder="e.g. Artwork approved" required></div>
                                    <div class="master-field"><label class="master-label">Status</label><select class="master-select" name="status">
                                            @foreach ($trackingStatusOptions as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="master-field small"><label class="master-label">Progress %</label><input class="master-input" type="number"
                                            name="progress_percent" min="0" max="100"
                                            value="{{ $project->progress_percent }}"></div>
                                    <div class="master-field"><label class="master-label">Activity By</label><input class="master-input" type="text" name="location"
                                            placeholder="MP / Client / Vendor etc."></div>
                                    <div class="master-field"><label class="master-label">Date & Time</label><input class="master-input" type="datetime-local"
                                            name="occurred_at" value="{{ now()->format('Y-m-d\TH:i') }}"></div>
                                    <label class="master-check"><input type="checkbox" name="is_public" value="1" checked>
                                        Public for client</label>
                                    <div class="master-field pd-span-2"><label class="master-label">Notes</label>
                                        <textarea class="master-textarea" name="notes" rows="2" placeholder="Detailed tracking message"></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="master-modal-footer">
                                <button type="button" class="master-btn master-btn-light" id="cancelAddTrackingModal" data-close-modal>Cancel</button>
                                <button class="master-btn master-btn-primary" type="submit"> Add Product</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!--Edit Tracking-->
                <div class="master-modal" id="editTrackingModal">
                    <div class="master-modal-card" style="max-width:900px;">
                        <form id="editTrackingForm" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="master-modal-header">
                                <div>
                                    <h3 class="master-modal-title">Edit Tracking</h3>
                                </div>
            
                                <button type="button" class="master-modal-close" id="closeEditTrackingModal" data-close-modal>×</button>
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
                                        <label class="master-label">Title</label>
                                        <input class="master-input" type="text" name="title" placeholder="e.g. Artwork approved" required>
                                    </div>
                                    <div class="master-field">
                                        <label class="master-label">Status</label>
                                        <select class="master-select" name="status">
                                            @foreach ($trackingStatusOptions as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="master-field small">
                                        <label class="master-label">Progress %</label>
                                        <input class="master-input" type="number" name="progress_percent" min="0" max="100" value="{{ $project->progress_percent }}">
                                    </div>
                                    <div class="master-field">
                                        <label class="master-label">Location</label>
                                        <input class="master-input" type="text" name="location" placeholder="Factory / Ahmedabad / etc.">
                                    </div>
                                    <div class="master-field">
                                        <label class="master-label">Date & Time</label>
                                        <input class="master-input" type="datetime-local"
                                            name="occurred_at" value="{{ now()->format('Y-m-d\TH:i') }}">
                                    </div>
                                    <label class="master-check">
                                        <input class="master-check" type="checkbox" name="is_public" value="1" checked>
                                        Public for client
                                    </label>
                                    <div class="master-field pd-span-2">
                                        <label class="master-label">Notes</label>
                                        <textarea class="master-textarea" name="notes" rows="2" placeholder="Detailed tracking message"></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="master-modal-footer">
                                <button type="button" class="master-btn master-btn-light" id="cancelEditTrackingModal" data-close-modal>Cancel</button>
                                <button class="master-btn master-btn-primary" type="submit"> Save Tracking</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!--Comment-->
                <section class="pd-tab-panel" id="pd-tab-comments" data-tab-panel="comments" role="tabpanel">
                    <div class="pd-section-head" style="padding:15px;">
                        <div>
                            <p class="pd-eyebrow">Conversation</p>
                            <h2>Comments</h2>
                        </div>
                        <div>
                            <span class="pd-count">{{ $project->comments->count() }} comments</span> &nbsp;
                            <button type="button" class="master-btn master-btn-primary" id="openAddCommentModal"><i class="fas fa-plus"></i> Add Comment</button>
                        </div>
                    </div>
                    <div class="pd-comments-list">
                        @forelse($project->comments as $comment)
                            <div class="pd-comment {{ $comment->is_pinned ? 'pd-pinned' : '' }}">
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
                        <form method="POST" action="{{ route('projects.comments.store', $project) }}">
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
                                    <div class="master-field full">
                                        <label class="master-label">Comment</label>
                                        <textarea class="master-textarea" name="body" rows="3" required placeholder="Add internal/client-visible comment..."></textarea>
                                    </div>
                                    <div class="master-field">
                                        <div class="master-toggle-group" style="display:block">
                                            <label class="master-label">Public for Client</label><br>
                                        
                                            <label class="master-switch">
                                                <input type="checkbox" name="is_public" value="1">
                                                <span class="master-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="master-field">
                                        <div class="master-toggle-group" style="display:block">
                                            <label class="master-label">Pin Comment</label><br>
                                        
                                            <label class="master-switch">
                                                <input type="checkbox" name="is_pinned" value="1">
                                                <span class="master-slider"></span>
                                            </label>
                                        </div>
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
                                    <div class="master-field full">
                                        <label class="master-label">Comment</label>
                                        <textarea class="master-textarea" name="body" rows="3" required placeholder="Add internal/client-visible comment..."></textarea>
                                    </div>
                                    <div class="master-field">
                                        <div class="master-toggle-group" style="display:block">
                                            <label class="master-label">Public for Client</label><br>
                                        
                                            <label class="master-switch">
                                                <input type="checkbox" name="is_public" value="1">
                                                <span class="master-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="master-field">
                                        <div class="master-toggle-group" style="display:block">
                                            <label class="master-label">Pin Comment</label><br>
                                        
                                            <label class="master-switch">
                                                <input type="checkbox" name="is_pinned" value="1">
                                                <span class="master-slider"></span>
                                            </label>
                                        </div>
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
                        <div class="pd-section-head" style="padding:15px;">
                            <div>
                                <p class="pd-eyebrow">Files</p>
                                <h2>Attachments</h2>
                            </div>
                            <div>
                                <span class="pd-count">{{ $project->attachments->count() }} Files</span> &nbsp;
                                <button type="button" class="master-btn master-btn-primary" id="openAddAttachmentModal"><i class="fas fa-plus"></i> Add Attachment</button>
                            </div>
                        </div>
                        
                        <div class="pd-attachment-grid pd-card">
                            @forelse($project->attachments as $attachment)
                                <div class="pd-attachment">
                                    @if ($attachment->isImage())
                                        <img src="{{ $attachment->fileUrl() }}" alt="{{ $attachment->title }}">
                                    @else
                                        <div class="pd-file-icon"><i class="fa-solid fa-file-lines"></i></div>
                                    @endif
                                    <div>
                                        <strong>{{ $attachment->title ?: $attachment->original_name }}</strong>
                                        <span>{{ $attachment->categoryLabel() }} ·
                                            {{ strtoupper($attachment->extension) }} ·
                                            {{ $attachment->is_public ? 'Public' : 'Internal' }}</span>
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
                        <form method="POST" enctype="multipart/form-data" action="{{ route('projects.attachments.store', $project) }}">
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
                                            @foreach ($attachmentCategoryOptions as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="master-field">
                                        <label class="master-label">Title</label>
                                        <input class="master-input" type="text" name="title"
                                            placeholder="Vendor invoice / packing list / etc."></div>
                                    <div class="master-field">
                                        <label class="master-label">Files</label>
                                        <input class="master-input" type="file" name="attachments[]" multiple
                                            required
                                            accept=".jpg,.jpeg,.png,.webp,.gif,.heic,.heif,.pdf,.doc,.docx,.xls,.xlsx,.csv,.ppt,.pptx,.txt,.zip">
                                    </div>
                                    <div class="master-field">
                                        <div class="master-toggle-group" style="display:block">
                                            <label class="master-label">Public for Client</label><br>
                                        
                                            <label class="master-switch">
                                                <input type="checkbox" name="is_public" value="1">
                                                <span class="master-slider"></span>
                                            </label>
                                        </div>
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
                        <div class="pd-section-head" style="padding:15px;">
                            <div>
                                <p class="pd-eyebrow">Finance</p>
                                <h2>Payment</h2>
                            </div>
                            <div>
                                <span class="pd-count">{{ $project->payments->count() }} Entries</span> &nbsp;
                                <button type="button" class="master-btn master-btn-primary" id="openAddPaymentModal"><i class="fas fa-plus"></i> Add Payment</button>
                            </div>
                        </div>
                        <div class="master-table-wrap pd-card">
                            <table class="master-table">
                                <thead>
                                    <tr>
                                        <th width="160">Date</th>
                                        <th>Type</th>
                                        <th>Reference No.</th>
                                        <th width="160">Amount</th>
                                        <th width="140">Mode</th>
                                        <th width="100">Visibility</th>
                                        <th width="90">Actions</th>
                                    </tr>
                                </thead>
                        
                                <tbody>
                                    @foreach ($project->cashflowEntries->take(8) as $entry)
                                        <tr>
                                            <td>
                                                <strong>
                                                    {{ optional($entry->entry_date)->format('d M Y') }}
                                                </strong>
                                                <div class="master-sub">
                                                    {{ $entry->transaction_type === 'credit' ? 'Inward' : 'Expense' }}
                                                </div>
                                            </td>
                                            <td><strong>{{ $entry->particular }}</strong></td>
                                            <td><strong>{{ $entry->bank_reference_number }}</strong></td>
                                            <td>
                                                @if ($entry->transaction_type === 'credit')
                                                    <span style="color:green">
                                                        {{ $entry->currency === 'INR' ? '₹' : $entry->currency }}
                                                        {{ number_format((float) $entry->credit_amount, 2) }}
                                                    </span>
                                                @else
                                                    <span style="color:red">
                                                        {{ $entry->currency === 'INR' ? '₹' : $entry->currency }}
                                                        {{ number_format((float) $entry->debit_amount, 2) }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td>    <strong>{{ $entry->payment_mode ? \Illuminate\Support\Str::title($entry->payment_mode) : '-' }}</strong></td>
                                            <td><span class="pd-chip pd-{{ $entry->client_id == null ? '-' : 'public' }}">{{ $entry->client_id == null ? 'Internal' : 'Public' }}</span></td>
                                            <td>
                                                <div class="master-sub">
                                                    Cashflow Mapping
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                    @forelse($project->payments as $payment)
                                        <tr>
                                            <td>
                                                <strong>{{ optional($payment->payment_date)->format('d M Y') }}</strong>
                                                <div class="master-sub">
                                                    {{ $payment->transaction_type === 'inward' ? 'Inward' : 'Expense' }}
                                                </div>
                                            </td>
                                            <td><strong>{{ $payment->category }}</strong></td>
                                            <td><strong>{{ $payment->reference_number }}</strong></td>
                                            <td><span style="{{ $payment->transaction_type === 'inward' ? 'color:green' : 'color:red' }}">{{ $payment->currency == 'INR' ? '₹' : $payment->currency }}
                                                {{ number_format((float) $payment->amount, 2) }}</span></td>
                                            <td>    <strong>{{ $payment->payment_mode ? \Illuminate\Support\Str::title($payment->payment_mode) : '-' }}</strong></td>
                                            <td><span class="pd-chip {{ $payment->is_public ? 'pd-public' : '' }}">
                                                {{ $payment->is_public ? 'Public' : 'Internal' }}</span></td>
                                            <td>
                                                <div class="master-row-actions">
                                                    <button type="button" class="master-icon-btn editPaymentBtn" data-payment='@json($payment)'><i class="fas fa-pen"></i></button>
                                                    <form method="POST"
                                                        action="{{ route('projects.payments.destroy',$payment) }}" onsubmit="return confirm('Remove payment entry?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="master-icon-btn danger"><i class="fas fa-trash"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7">
                                                <div class="master-empty">
                                                    No payment entries available.
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
                
                <!--Add Payment-->
                <div class="master-modal" id="addPaymentModal" aria-hidden="true">
                    <div class="master-modal-card" style="max-width:900px;">
                        <form method="POST" action="{{ route('projects.payments.store', $project) }}">
                            @csrf
                            <div class="master-modal-header">
                                <div class="master-modal-heading">
                                    <div>
                                        <h3 class="master-modal-title">Add Payment</h3>
                                    </div>
                                </div>
                                <button type="button" class="master-modal-close" id="closeAddPaymentModal" data-close-modal>×</button>
                            </div>
                            <div class="master-modal-body">
                                <div class="master-modal-grid">
                                    <div class="master-field">
                                        <label class="master-label">Type</label>
                                        <select class="master-select" name="transaction_type">
                                            @foreach ($paymentTypeOptions as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="master-field">
                                        <label class="master-label">Date</label>
                                        <input class="master-input" type="date" name="payment_date"
                                            value="{{ now()->toDateString() }}" required></div>
                                    <div class="master-field">
                                        <label class="master-label">Amount</label>
                                        <input class="master-input" type="number" step="0.01"
                                            min="0.01" name="amount" required></div>
                                    <div class="master-field">
                                        <label class="master-label">Currency</label>
                                        <select class="master-select" name="currency">
                                            @foreach ($currencyOptions as $key => $label)
                                                <option value="{{ $key }}"
                                                    {{ $project->currency === $key ? 'selected' : '' }}>{{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="master-field">
                                        <label class="master-label">Mode</label>
                                        <select class="master-select" name="payment_mode">
                                            <option value="">Select mode</option>
                                            @foreach ($paymentModeOptions as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="master-field">
                                        <label class="master-label">Reference No.</label>
                                        <input class="master-input" type="text"
                                            name="reference_number"></div>
                                    <div class="master-field">
                                        <label class="master-label">Category</label>
                                        <input class="master-input" type="text" name="category"
                                            placeholder="Advance / Vendor / Freight"></div>
                                    <div class="master-field">
                                        <div class="master-toggle-group" style="display:block">
                                            <label class="master-label">Public for Client</label><br>
                                        
                                            <label class="master-switch">
                                                <input type="checkbox" name="is_public" value="1">
                                                <span class="master-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="master-field pd-span-2">
                                        <label class="master-label">Notes</label>
                                        <textarea class="master-textarea" name="notes" rows="2"></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="master-modal-footer">
                                <button type="button" class="master-btn master-btn-light" id="cancelAddPaymentModal" data-close-modal>Cancel</button>
                                <button class="master-btn master-btn-primary" type="submit"> Add Payment</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!--Update Payment-->
                <div class="master-modal" id="editPaymentModal">
                    <div class="master-modal-card" style="max-width:900px;">
                        <form id="editPaymentForm" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="master-modal-header">
                                <div class="master-modal-heading">
                                    <div>
                                        <h3 class="master-modal-title">Update Payment</h3>
                                    </div>
                                </div>
                                <button type="button" class="master-modal-close" id="closeEditPaymentModal" data-close-modal>×</button>
                            </div>
                            <div class="master-modal-body">
                                <div class="master-modal-grid">
                                    <div class="master-field">
                                        <label class="master-label">Type</label>
                                        <select class="master-select" name="transaction_type">
                                            @foreach ($paymentTypeOptions as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="master-field">
                                        <label class="master-label">Date</label>
                                        <input class="master-input" type="date" name="payment_date" required></div>
                                    <div class="master-field">
                                        <label class="master-label">Amount</label>
                                        <input class="master-input" type="number" step="0.01"
                                            min="0.01" name="amount" required></div>
                                    <div class="master-field">
                                        <label class="master-label">Currency</label>
                                        <select class="master-select" name="currency">
                                            @foreach ($currencyOptions as $key => $label)
                                                <option value="{{ $key }}"
                                                    {{ $project->currency === $key ? 'selected' : '' }}>{{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="master-field">
                                        <label class="master-label">Mode</label>
                                        <select class="master-select" name="payment_mode">
                                            <option value="">Select mode</option>
                                            @foreach ($paymentModeOptions as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="master-field">
                                        <label class="master-label">Reference No.</label>
                                        <input class="master-input" type="text"
                                            name="reference_number"></div>
                                    <div class="master-field">
                                        <label class="master-label">Category</label>
                                        <input class="master-input" type="text" name="category"
                                            placeholder="Advance / Vendor / Freight"></div>
                                    <div class="master-field">
                                        <div class="master-toggle-group" style="display:block">
                                            <label class="master-label">Public for Client</label><br>
                                        
                                            <label class="master-switch">
                                                <input type="checkbox" name="is_public" value="1">
                                                <span class="master-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="master-field pd-span-2">
                                        <label class="master-label">Notes</label>
                                        <textarea class="master-textarea" name="notes" rows="2"></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="master-modal-footer">
                                <button type="button" class="master-btn master-btn-light" id="cancelEditPaymentModal" data-close-modal>Cancel</button>
                                <button class="master-btn master-btn-primary" type="submit"> Save Payment</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <section class="pd-tab-panel" id="pd-tab-shipments" data-tab-panel="shipments" role="tabpanel">
                    <div>
                        <div class="pd-section-head" style="padding:15px;">
                            <div>
                                <p class="pd-eyebrow">Logistics</p>
                                <h2>Shipment</h2>
                            </div>
                            <div>
                                <span class="pd-count">{{ $project->shipments->count() }} Shipments</span> &nbsp;
                                <button type="button" class="master-btn master-btn-primary" id="openAddShipmentModal"><i class="fas fa-plus"></i> Add Shipment</button>
                            </div>
                        </div>
                        <div class="master-table-wrap pd-card">
                            <table class="master-table">
                                <thead>
                                    <tr>
                                        <th width="160">Date</th>
                                        <th>Shipment</th>
                                        <th>Route</th>
                                        <th width="160">Logistics</th>
                                        <th width="160">Charges</th>
                                        <th width="140">Status</th>
                                        <th width="90">Actions</th>
                                    </tr>
                                </thead>
                        
                                <tbody>
                                        @forelse($project->shipments as $shipment)
                                        @php($statusClass = str_replace('_', '-', $shipment->status))
                                        <tr style="line-height:1.5">
                                            <td style="font-weight:500">
                                                {{ $shipment->pickup_date ? $shipment->pickup_date->format('d M') : '-' }}
                                                <span class="master-sub">Drop: {{ $shipment->drop_date ? $shipment->drop_date->format('d M') : '-' }}</span>
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
                                            <td style="font-weight:500">
                                                <div class="master-row-actions">
                                                    <a href="{{ route('shipments.show', $shipment) }}" class="master-icon-btn green" title="View">👁</a>
                                                    <a href="{{ route('shipments.edit', $shipment) }}" class="master-icon-btn" title="Edit">✎</a>
                                                    <button type="button" class="master-icon-btn" title="Copy public link" onclick="copyShipmentLink('{{ route('shipments.publicTrack', $shipment->public_token) }}')">🔗</button>
                                                </div>
                                            </td>
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

                <section class="pd-tab-panel" id="pd-tab-logs" data-tab-panel="logs" role="tabpanel">
                    <div>
                        <div class="pd-section-head" style="padding:15px;">
                            <div>
                                <p class="pd-eyebrow">Audit</p>
                                <h2>Activity Logs</h2>
                            </div>
                            <div>
                                <span class="pd-count">{{ $project->logs->count() }} Logs</span>
                            </div>
                        </div>
                        <div class="pd-log-list pd-card">
                            @forelse($project->logs as $log)
                                <div class="pd-log">
                                    <strong>{{ $log->title }}</strong>
                                    <span>{{ $log->actor_name ?: 'System' }} ·
                                        {{ $log->created_at->format('d M Y, h:i A') }} @if ($log->product)
                                            · {{ $log->product->product_name }}
                                        @endif
                                    </span>
                                    @if ($log->description)
                                        <p>{{ $log->description }}</p>
                                    @endif
                                    <div class="pd-row-actions"><span
                                            class="pd-chip {{ $log->is_public ? 'pd-public' : '' }}">{{ $log->is_public ? 'Public Log' : 'Internal Log' }}</span><span
                                            class="pd-chip">{{ $log->event_type }}</span></div>
                                </div>
                            @empty
                                <div class="pd-empty">No logs yet.</div>
                            @endforelse
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>


@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/projects.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/js/projects.js') }}"></script>
@endpush
@endsection
