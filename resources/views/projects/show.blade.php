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
                <a href="{{ route('projects.index') }}" class="pd-btn pd-btn-light"><i class="fa-solid fa-arrow-left"></i>
                    Back</a>
                <a href="{{ route('projects.edit', $project) }}" class="pd-btn pd-btn-light"><i class="fa-solid fa-pen"></i>
                    Edit</a>
                @if ($project->show_client_portal)
                    <a href="{{ $portalUrl }}" target="_blank" class="pd-btn pd-btn-primary"><i
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
                            <input type="text" value="{{ $portalUrl }}" readonly id="portalLinkInput">
                            <button type="button" class="pd-btn pd-btn-soft pd-btn-sm" id="copyPortalLink"><i
                                    class="fa-solid fa-copy"></i> Copy</button>
                        </div>
                    @endif
                </div>
            </div>
            <form method="POST" action="{{ route('projects.status.update', $project) }}" class="master-status-form">
                @csrf
                @method('PATCH')
                <div class="master-field">
                    <select name="status">
                        @foreach ($statusOptions as $key => $label)
                            <option value="{{ $key }}" {{ $project->status === $key ? 'selected' : '' }}>
                                {{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="master-field">
                    <select name="stage">
                        @foreach ($stageOptions as $key => $label)
                            <option value="{{ $key }}" {{ $project->stage === $key ? 'selected' : '' }}>
                                {{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="master-field">
                    <select name="health">
                        @foreach ($healthOptions as $key => $label)
                            <option value="{{ $key }}" {{ $project->health === $key ? 'selected' : '' }}>
                                {{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="master-field">
                    <input type="number" name="progress_percent" min="0" max="100"
                        value="{{ $project->progress_percent }}">
                </div>
                <div class="pd-actions">
                    <button class="pd-btn pd-btn-primary" type="submit">Update</button>
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
                                        <label>Mapped Product</label>
                                        <select name="product_id" required>
                                            <option value="">Manual product</option>
                                            @foreach($products as $product)
                                                <option value="{{ $product->id }}">{{ $product->product_number }} - {{ $product->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="master-field" style="display:none;">
                                        <label>Product Name</label>
                                        <input type="text" name="product_name" placeholder="Required if product not selected">
                                    </div>
                                    <div class="master-field small">
                                        <label>Qty</label>
                                        <input type="number" step="0.001" min="0.001" name="quantity" value="1" required>
                                    </div>
                                    <div class="master-field small" style="display:none;">
                                        <label>Unit</label>
                                        <input type="text" name="unit" value="pcs">
                                    </div>
                                    <div class="master-field small">
                                        <label>Unit Price</label>
                                        <input type="number" step="0.01" min="0" name="unit_price" value="0">
                                    </div>
                                    <div class="master-field">
                                        <label>Status</label>
                                        <select name="status">
                                            @foreach($productStatusOptions as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="master-field">
                                        <label>Vendor</label>
                                        <select name="vendor_id">
                                            <option value="">Unassigned</option>
                                            @foreach ($vendors as $vendor)
                                                <option value="{{ $vendor->id }}">{{ $vendor->contact_person_name }} ({{ $vendor->vendor_name }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="master-field">
                                        <label>Vendor Invoice Number</label>
                                        <input type="text" name="vendor_invoice_number">
                                    </div>
                                    <div class="master-field">
                                        <label>Expected Ready</label>
                                        <input type="date" name="expected_ready_date">
                                    </div>
                                    <div class="master-field pd-span-2">
                                        <label>Notes</label>
                                        <textarea rows="5" name="notes" placeholder="Artwork, production, packaging notes"></textarea>
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
                                        <label>Mapped Product</label> 
                                        <select name="product_id"> 
                                            <option value="">Manual product</option> 
                                            @foreach ($products as $product) 
                                                <option value="{{ $product->id }}"> {{ $product->name }}{{ $product->sku ? ' - ' . $product->sku : '' }}</option> 
                                            @endforeach 
                                        </select> 
                                    </div> 
                                    <div class="master-field small">
                                        <label>Qty</label>
                                        <input type="number" step="0.001" min="0.001" name="quantity" value="1" required>
                                    </div> 
                                    <div class="master-field small">
                                        <label>Unit Price</label>
                                        <input type="number" step="0.01" min="0" name="unit_price" value="0">
                                    </div> 
                                    <div class="master-field">
                                        <label>Expected Ready</label>
                                        <input type="date" name="expected_ready_date">
                                    </div>
                                    <!--<div class="master-field">-->
                                    <!--    <label>Assignee</label>-->
                                    <!--    <select name="assigned_to">-->
                                    <!--        <option value="">Unassigned</option>-->
                                    <!--        @foreach ($users as $user)-->
                                    <!--            <option value="{{ $user->id }}">{{ $user->name }}</option>-->
                                    <!--        @endforeach-->
                                    <!--    </select>-->
                                    <!--</div>-->
                                    <div class="master-field">
                                        <label>Vendor</label>
                                        <select name="vendor_id">
                                            <option value="">Unassigned</option>
                                            @foreach ($vendors as $vendor)
                                                <option value="{{ $vendor->id }}">{{ $vendor->contact_person_name }} ({{ $vendor->vendor_name }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="master-field">
                                        <label>Vendor Invoice Number</label>
                                        <input type="text" name="vendor_invoice_number">
                                    </div>
                                    <div class="master-field">
                                        <label>Status</label>
                                        <select name="status"> 
                                            @foreach ($productStatusOptions as $key => $label) 
                                                <option value="{{ $key }}">{{ $label }}</option> 
                                            @endforeach 
                                        </select> 
                                    </div> 
                                    <div class="master-field pd-span-2">
                                        <label>Notes</label>
                                        <textarea rows="5" name="notes" placeholder="Artwork, production, packaging notes"></textarea>
                                    </div>
                                    <!--<div class="master-field">-->
                                    <!--    <label>Stage</label>-->
                                    <!--    <select name="stage"> -->
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
                                    <div class="master-field"><label>Product (optional)</label><select name="project_product_id">
                                            <option value="">Project level</option>
                                            @foreach ($project->products as $projectProduct)
                                                <option value="{{ $projectProduct->id }}">{{ $projectProduct->product_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="master-field"><label>Title</label><input type="text" name="title"
                                            placeholder="e.g. Artwork approved" required></div>
                                    <div class="master-field"><label>Status</label><select name="status">
                                            @foreach ($trackingStatusOptions as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="master-field small"><label>Progress %</label><input type="number"
                                            name="progress_percent" min="0" max="100"
                                            value="{{ $project->progress_percent }}"></div>
                                    <div class="master-field"><label>Activity By</label><input type="text" name="location"
                                            placeholder="MP / Client / Vendor etc."></div>
                                    <div class="master-field"><label>Date & Time</label><input type="datetime-local"
                                            name="occurred_at" value="{{ now()->format('Y-m-d\TH:i') }}"></div>
                                    <label class="pd-check"><input type="checkbox" name="is_public" value="1" checked>
                                        Public for client</label>
                                    <div class="master-field pd-span-2"><label>Notes</label>
                                        <textarea name="notes" rows="2" placeholder="Detailed tracking message"></textarea>
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
                                        <label>Product (optional)</label>
                                        <select name="project_product_id">
                                            <option value="">Project level</option>
                                            @foreach ($project->products as $projectProduct)
                                                <option value="{{ $projectProduct->id }}">{{ $projectProduct->product_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="master-field">
                                        <label>Title</label>
                                        <input type="text" name="title" placeholder="e.g. Artwork approved" required>
                                    </div>
                                    <div class="master-field">
                                        <label>Status</label>
                                        <select name="status">
                                            @foreach ($trackingStatusOptions as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="master-field small">
                                        <label>Progress %</label>
                                        <input type="number" name="progress_percent" min="0" max="100" value="{{ $project->progress_percent }}">
                                    </div>
                                    <div class="master-field">
                                        <label>Location</label>
                                        <input type="text" name="location" placeholder="Factory / Ahmedabad / etc.">
                                    </div>
                                    <div class="master-field">
                                        <label>Date & Time</label>
                                        <input type="datetime-local"
                                            name="occurred_at" value="{{ now()->format('Y-m-d\TH:i') }}">
                                    </div>
                                    <label class="master-check">
                                        <input class="master-check" type="checkbox" name="is_public" value="1" checked>
                                        Public for client
                                    </label>
                                    <div class="master-field pd-span-2">
                                        <label>Notes</label>
                                        <textarea name="notes" rows="2" placeholder="Detailed tracking message"></textarea>
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
                                        <label>Product (optional)</label>
                                        <select name="project_product_id">
                                            <option value="">Project level</option>
                                            @foreach ($project->products as $projectProduct)
                                                <option value="{{ $projectProduct->id }}">{{ $projectProduct->product_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="master-field full">
                                        <label>Comment</label>
                                        <textarea name="body" rows="3" required placeholder="Add internal/client-visible comment..."></textarea>
                                    </div>
                                    <div class="master-field">
                                        <div class="master-toggle-group" style="display:block">
                                            <label>Public for Client</label><br>
                                        
                                            <label class="master-switch">
                                                <input type="checkbox" name="is_public" value="1">
                                                <span class="master-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="master-field">
                                        <div class="master-toggle-group" style="display:block">
                                            <label>Pin Comment</label><br>
                                        
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
                                        <label>Product (optional)</label>
                                        <select name="project_product_id">
                                            <option value="">Project level</option>
                                            @foreach ($project->products as $projectProduct)
                                                <option value="{{ $projectProduct->id }}">{{ $projectProduct->product_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="master-field full">
                                        <label>Comment</label>
                                        <textarea name="body" rows="3" required placeholder="Add internal/client-visible comment..."></textarea>
                                    </div>
                                    <div class="master-field">
                                        <div class="master-toggle-group" style="display:block">
                                            <label>Public for Client</label><br>
                                        
                                            <label class="master-switch">
                                                <input type="checkbox" name="is_public" value="1">
                                                <span class="master-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="master-field">
                                        <div class="master-toggle-group" style="display:block">
                                            <label>Pin Comment</label><br>
                                        
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
                                        <label>Product (optional)</label>
                                        <select name="project_product_id">
                                            <option value="">Project level</option>
                                            @foreach ($project->products as $projectProduct)
                                                <option value="{{ $projectProduct->id }}">{{ $projectProduct->product_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="master-field">
                                        <label>Category</label>
                                        <select name="category">
                                            @foreach ($attachmentCategoryOptions as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="master-field">
                                        <label>Title</label>
                                        <input type="text" name="title"
                                            placeholder="Vendor invoice / packing list / etc."></div>
                                    <div class="master-field">
                                        <label>Files</label>
                                        <input type="file" name="attachments[]" multiple
                                            required
                                            accept=".jpg,.jpeg,.png,.webp,.gif,.heic,.heif,.pdf,.doc,.docx,.xls,.xlsx,.csv,.ppt,.pptx,.txt,.zip">
                                    </div>
                                    <div class="master-field">
                                        <div class="master-toggle-group" style="display:block">
                                            <label>Public for Client</label><br>
                                        
                                            <label class="master-switch">
                                                <input type="checkbox" name="is_public" value="1">
                                                <span class="master-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="master-field pd-span-2">
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
                                        <label>Type</label>
                                        <select name="transaction_type">
                                            @foreach ($paymentTypeOptions as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="master-field">
                                        <label>Date</label>
                                        <input type="date" name="payment_date"
                                            value="{{ now()->toDateString() }}" required></div>
                                    <div class="master-field">
                                        <label>Amount</label>
                                        <input type="number" step="0.01"
                                            min="0.01" name="amount" required></div>
                                    <div class="master-field">
                                        <label>Currency</label>
                                        <select name="currency">
                                            @foreach ($currencyOptions as $key => $label)
                                                <option value="{{ $key }}"
                                                    {{ $project->currency === $key ? 'selected' : '' }}>{{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="master-field">
                                        <label>Mode</label>
                                        <select name="payment_mode">
                                            <option value="">Select mode</option>
                                            @foreach ($paymentModeOptions as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="master-field">
                                        <label>Reference No.</label>
                                        <input type="text"
                                            name="reference_number"></div>
                                    <div class="master-field">
                                        <label>Category</label>
                                        <input type="text" name="category"
                                            placeholder="Advance / Vendor / Freight"></div>
                                    <div class="master-field">
                                        <div class="master-toggle-group" style="display:block">
                                            <label>Public for Client</label><br>
                                        
                                            <label class="master-switch">
                                                <input type="checkbox" name="is_public" value="1">
                                                <span class="master-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="master-field pd-span-2">
                                        <label>Notes</label>
                                        <textarea name="notes" rows="2"></textarea>
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
                                        <label>Type</label>
                                        <select name="transaction_type">
                                            @foreach ($paymentTypeOptions as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="master-field">
                                        <label>Date</label>
                                        <input type="date" name="payment_date" required></div>
                                    <div class="master-field">
                                        <label>Amount</label>
                                        <input type="number" step="0.01"
                                            min="0.01" name="amount" required></div>
                                    <div class="master-field">
                                        <label>Currency</label>
                                        <select name="currency">
                                            @foreach ($currencyOptions as $key => $label)
                                                <option value="{{ $key }}"
                                                    {{ $project->currency === $key ? 'selected' : '' }}>{{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="master-field">
                                        <label>Mode</label>
                                        <select name="payment_mode">
                                            <option value="">Select mode</option>
                                            @foreach ($paymentModeOptions as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="master-field">
                                        <label>Reference No.</label>
                                        <input type="text"
                                            name="reference_number"></div>
                                    <div class="master-field">
                                        <label>Category</label>
                                        <input type="text" name="category"
                                            placeholder="Advance / Vendor / Freight"></div>
                                    <div class="master-field">
                                        <div class="master-toggle-group" style="display:block">
                                            <label>Public for Client</label><br>
                                        
                                            <label class="master-switch">
                                                <input type="checkbox" name="is_public" value="1">
                                                <span class="master-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="master-field pd-span-2">
                                        <label>Notes</label>
                                        <textarea name="notes" rows="2"></textarea>
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

    <style>
        .pd-page {
            display: flex;
            flex-direction: column;
            gap: 18px
        }

        .pd-hero {
            background: linear-gradient(135deg, #4f83f1, #7b61ff);
            border-radius: 24px;
            padding: 24px;
            color: #fff;
            display: flex;
            justify-content: space-between;
            gap: 18px;
            box-shadow: 0 18px 45px rgba(79, 131, 241, .22)
        }

        .pd-eyebrow {
            margin: 0 0 6px;
            text-transform: uppercase;
            letter-spacing: .12em;
            font-size: 11px;
            font-weight: 600;
            opacity: .78
        }

        .pd-hero h1 {
            margin: 0;
            font-size: 30px;
            font-weight: 600
        }

        .pd-hero-meta {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 10px;
            opacity: .9
        }

        .pd-hero-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap
        }

        .pd-btn {
            border: 0;
            border-radius: 14px;
            padding: 10px 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            transition: .2s
        }

        .pd-btn:hover {
            transform: translateY(-1px);
            text-decoration: none
        }

        .pd-btn-primary {
            background: #ef4770;
            color: #fff;
            box-shadow: 0 10px 24px rgba(239, 71, 112, .24)
        }

        .pd-btn-light {
            background: #eef3ff;
            color: #4f83f1
            border: 1px solid rgba(255, 255, 255, .3)
        }

        .pd-btn-soft {
            background: #eef3ff;
            color: #4f83f1
        }

        .pd-btn-sm {
            padding: 8px 10px;
            font-size: 12px
        }

        .pd-status-card,
        .pd-card,
        .pd-metric,
        .pd-tabs-shell {
            background: #fff;
            border: 1px solid #edf0f7;
            border-radius: 22px;
            box-shadow: 0 12px 32px rgba(22, 34, 51, .06)
        }

        .master-status-form{
            display:grid;
            grid-template-columns:repeat(2,minmax(220px,1fr));
            gap:16px 20px;
            align-items:end;
            border-left:1px solid #DDD;
            padding:10px;
        }

        .pd-status-card {
            padding: 16px;
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 14px;
            align-items: center
        }

        .pd-status-left {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 16px;
            align-items: center
        }

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

        .pd-chip-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap
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

        .pd-mini-progress {
            height: 7px
        }

        .pd-copy-row {
            display: flex;
            gap: 8px;
            margin-top: 12px
        }

        .pd-copy-row input {
            flex: 1;
            border: 1px solid #dfe5f2;
            border-radius: 12px;
            padding: 9px;
            color: #667085;
            background: #f8fafc
        }

        .pd-status-form {
            display: grid;
            grid-template-columns: repeat(4, 120px) auto;
            gap: 8px
        }

        .pd-status-form select,
        .pd-status-form input,
        .master-field input,
        .master-field select,
        .master-field textarea {
            border: 1px solid #dfe5f2;
            border-radius: 13px;
            padding: 10px 11px;
            background: #fff;
            color: #172033;
            outline: none;
            width: 100%
        }

        .pd-status-form select:focus,
        .pd-status-form input:focus,
        .master-field input:focus,
        .master-field select:focus,
        .master-field textarea:focus {
            border-color: #4f83f1;
            box-shadow: 0 0 0 4px rgba(79, 131, 241, .1)
        }

        .pd-metrics {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 14px
        }

        .pd-metric {
            padding: 16px
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
            overflow: hidden
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

        @media(max-width:1399px) {
            .pd-status-card {
                grid-template-columns: 1fr
            }

            .pd-status-form {
                grid-template-columns: repeat(4, 1fr) auto
            }

            .pd-product-form {
                grid-template-columns: repeat(3, minmax(0, 1fr))
            }

            .pd-tracking-form,
            .pd-attachment-form,
            .pd-payment-form {
                grid-template-columns: repeat(3, minmax(0, 1fr))
            }
        }

        @media(max-width:991px) {
            .pd-metrics {
                grid-template-columns: repeat(2, minmax(0, 1fr))
            }

            .pd-panel-grid {
                grid-template-columns: 1fr
            }

            .pd-status-form,
            .pd-product-form,
            .pd-tracking-form,
            .pd-attachment-form,
            .pd-payment-form,
            .pd-inline-form {
                grid-template-columns: 1fr 1fr
            }

            .pd-attachment-grid {
                grid-template-columns: 1fr
            }

            .pd-span-2 {
                grid-column: 1/-1
            }
        }

        @media(max-width:767px) {

            .pd-hero,
            .pd-status-left,
            .pd-product-top,
            .pd-payment,
            .pd-section-head,
            .pd-comment-options {
                flex-direction: column;
                display: flex
            }

            .pd-hero-actions,
            .pd-hero-actions .pd-btn {
                width: 100%
            }

            .pd-status-form,
            .pd-metrics,
            .pd-product-form,
            .pd-tracking-form,
            .pd-attachment-form,
            .pd-payment-form,
            .pd-inline-form,
            .pd-info-list {
                grid-template-columns: 1fr
            }

            .pd-copy-row {
                flex-direction: column
            }

            .pd-card,
            .pd-tabs-content {
                padding: 14px
            }

            .pd-attachment {
                grid-template-columns: 52px 1fr
            }

            .pd-attachment img,
            .pd-file-icon {
                width: 52px;
                height: 52px
            }

            .pd-progress-ring {
                width: 82px;
                height: 82px
            }

            .pd-tab-btn {
                padding: 9px 11px
            }

            .pd-tabs-nav {
                padding: 10px
            }

            .pd-summary-item {
                grid-template-columns: 38px 1fr
            }

            .pd-summary-item i {
                width: 38px;
                height: 38px
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

            const editProductModal = document.getElementById('editProductModal');
            const editProductForm = document.getElementById('editProductForm');
        
            document.querySelectorAll('.editProductBtn').forEach(btn => {
        
                btn.addEventListener('click', function () {
        
                    const product = JSON.parse(this.dataset.product);
        
                    editProductForm.action = `/project-products/${product.id}`;
                    
                    setValue(editProductForm,'product_id', product.product_id);
                    setValue(editProductForm,'quantity', product.quantity);
                    setValue(editProductForm,'unit_price', product.unit_price);
                    setValue(editProductForm,'status', product.status);
                    setValue(editProductForm,'stage', product.stage);
                    setValue(editProductForm,'assigned_to', product.assigned_to);
                    setValue(editProductForm,'vendor_id', product.vendor_id);
                    setValue(editProductForm,'vendor_invoice_number', product.vendor_invoice_number);
                    setValue(editProductForm,'expected_ready_date', product.expected_ready_date);
                    setValue(editProductForm,'actual_ready_date', product.actual_ready_date);
                    setValue(editProductForm,'notes', product.notes);
                    setValue(editProductForm,'currency', product.currency);
                    setValue(editProductForm,'sort_order', product.sort_order);
                    
                    openModal(editProductModal);
                });
        
            });

            const editTrackingModal = document.getElementById('editTrackingModal');
            const editTrackingForm = document.getElementById('editTrackingForm');
            
            document.querySelectorAll('.editTrackingBtn').forEach(btn => {

                btn.addEventListener('click', function () {
        
                    const tracking = JSON.parse(this.dataset.tracking);
        
                    editTrackingForm.action = `/project-tracking/${tracking.id}`;
        
                    setValue(editTrackingForm, 'project_product_id', tracking.project_product_id);
                    setValue(editTrackingForm, 'title', tracking.title);
                    setValue(editTrackingForm, 'status', tracking.status);
                    setValue(editTrackingForm, 'progress_percent', tracking.progress_percent);
                    setValue(editTrackingForm, 'location', tracking.location);
                    setValue(editTrackingForm, 'notes', tracking.notes);
                    setValue(editTrackingForm, 'is_public', tracking.is_public);
        
                    if (tracking.occurred_at) {
                        const date = tracking.occurred_at.substring(0, 16);
                        setValue(editTrackingForm, 'occurred_at', date);
                    }
        
                    openModal(editTrackingModal);
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

            const editPaymentModal = document.getElementById('editPaymentModal');
            const editPaymentForm = document.getElementById('editPaymentForm');
            
            document.querySelectorAll('.editPaymentBtn').forEach(btn => {

                btn.addEventListener('click', function () {
        
                    const payment = JSON.parse(this.dataset.payment);
        
                    editPaymentForm.action = `/project-payments/${payment.id}`;
        
                    setValue(editPaymentForm, 'transaction_type', payment.transaction_type);
                    
                    let paymentDate = payment.payment_date;

                    if (paymentDate) {
                        paymentDate = paymentDate.substring(0, 10);
                    }
                    setValue(editPaymentForm, 'payment_date', paymentDate);
                    setValue(editPaymentForm, 'amount', payment.amount);
                    setValue(editPaymentForm, 'currency', payment.currency);
                    setValue(editPaymentForm, 'payment_mode', payment.payment_mode);
                    setValue(editPaymentForm, 'reference_number', payment.reference_number);
                    setValue(editPaymentForm, 'category', payment.category);
                    setValue(editPaymentForm, 'notes', payment.notes);
                    setValue(editPaymentForm, 'is_public', payment.is_public);
        
                    openModal(editPaymentModal);
                });
            });
            
            const addProductModal = document.getElementById('addProductModal');
            document.getElementById('openAddProductModal')?.addEventListener('click', () => openModal(addProductModal));
            
            const addTrackingModal = document.getElementById('addTrackingModal');
            document.getElementById('openAddTrackingModal')?.addEventListener('click', () => openModal(addTrackingModal));
            
            const addCommentModal = document.getElementById('addCommentModal');
            document.getElementById('openAddCommentModal')?.addEventListener('click', () => openModal(addCommentModal));
            
            const addAttachmentModal = document.getElementById('addAttachmentModal');
            document.getElementById('openAddAttachmentModal')?.addEventListener('click', () => openModal(addAttachmentModal));
            
            const addPaymentModal = document.getElementById('addPaymentModal');
            document.getElementById('openAddPaymentModal')?.addEventListener('click', () => openModal(addPaymentModal));
        
        });
    </script>
@endsection
