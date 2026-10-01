<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $project->name }} | Project Progress</title>
    <link rel="stylesheet" href="{{ asset('assets/css/projects-public.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/select2-theme.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/master-alert.css') }}">
</head>
<body>
<div class="portal-shell">
    <div class="portal-top">
        <div class="portal-logo">
            <img src="{{ asset('images/misspack-logo.png') }}" alt="MissPack">
            <div><strong>MissPack</strong><span>Packed Perfect</span></div>
        </div>
        <span class="portal-badge">Client Project Portal</span>
    </div>

    @if(session('success'))<div class="portal-alert success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="portal-alert error">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="portal-alert error"><strong>Please fix:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="portal-hero">
        <div>
            <p class="eyebrow">{{ $project->project_number }}</p>
            <h1>{{ $project->name }}</h1>
            <p>{{ $project->client_notes ?: 'Track project progress, product status, public attachments, payments and comments from this secure page.' }}</p>
        </div>
        <div class="progress-box">
            <span>Overall Progress</span><br>
            <strong>{{ $project->progress_percent }}%</strong>
            <div class="progress"><span style="width: {{ $project->progress_percent }}%"></span></div>
            <p style="margin:10px 0 0;">{{ $project->stageLabel() }}</p>
        </div>
    </section>

    <div class="metric-grid">
        <div class="metric"><span>Status</span><strong>{{ $project->statusLabel() }}</strong></div>
        <div class="metric"><span>Stage</span><strong>{{ $project->stageLabel() }}</strong></div>
        <div class="metric"><span>Start Date</span><strong>{{ optional($project->start_date)->format('d M Y') ?: '-' }}</strong></div>
        <div class="metric"><span>Target Date</span><strong>{{ optional($project->target_date)->format('d M Y') ?: '-' }}</strong></div>
    </div>

    <div class="main-grid">
        <main>
            <section class="card">
                <div class="section-head"><div><p class="eyebrow">Details</p><h2>Project Overview</h2></div><span class="chip green">{{ $project->healthLabel() }}</span></div>
                <div class="info-grid">
                    <div class="info"><span>Client</span><strong>{{ $project->client ? $project->client->company_name : 'Client' }}</strong></div>
                    <div class="info"><span>Products</span><strong>{{ $project->products->count() }}</strong></div>
                    <div class="info"><span>Estimated Value</span><strong>{{ $project->currency }} {{ number_format((float) $project->estimated_value, 2) }}</strong></div>
                    <div class="info"><span>Priority</span><strong>{{ $project->priorityLabel() }}</strong></div>
                </div>
                @if($project->scope_summary)<div class="text-block"><span>Scope</span><p>{{ $project->scope_summary }}</p></div>@endif
                @if($project->deliverables)<div class="text-block"><span>Deliverables</span><p>{{ $project->deliverables }}</p></div>@endif
            </section>

            <section class="card">
                <div class="section-head"><div><p class="eyebrow">Products</p><h2>Products in this Project</h2></div><span class="chip">{{ $project->products->count() }} items</span></div>
                <div class="product-grid">
                    @forelse($project->products as $projectProduct)
                        <div class="product-card">
                            <div class="product-top"><div><h3>{{ $projectProduct->product_name }}</h3><div class="muted">Qty {{ number_format((float) $projectProduct->quantity, 3) }} {{ $projectProduct->unit }}</div></div><div><span class="chip">{{ $projectProduct->statusLabel() }}</span></div></div>
                            <div class="muted" style="margin-top:8px;">Stage: {{ $projectProduct->stageLabel() }} @if($projectProduct->expected_ready_date) · Expected Ready: {{ $projectProduct->expected_ready_date->format('d M Y') }} @endif</div>
                            @if($projectProduct->notes)<p style="white-space:pre-wrap;color:#344054;">{{ $projectProduct->notes }}</p>@endif
                        </div>
                    @empty
                        <div class="empty">Products will appear here once added by the team.</div>
                    @endforelse
                </div>
            </section>

            <section class="card">
                <div class="section-head"><div><p class="eyebrow">Milestones</p><h2>Public Product Timelines</h2></div><span class="chip">{{ $project->publicMilestones->count() }} milestones</span></div>
                <div class="product-grid">
                    @forelse($project->publicMilestones->groupBy('project_product_id') as $productId => $milestones)
                        @php($firstMilestone = $milestones->first())
                        <div class="product-card">
                            <div class="product-top"><div><h3>{{ $firstMilestone && $firstMilestone->product ? $firstMilestone->product->product_name : 'Project Level' }}</h3><div class="muted">{{ $milestones->where('status', 'completed')->count() }} of {{ $milestones->count() }} completed</div></div><span class="chip green">{{ (int) round($milestones->avg('progress_percent')) }}%</span></div>
                            @foreach($milestones as $milestone)
                                <div class="timeline-content" style="margin-top:10px;">
                                    <div class="timeline-head"><strong>{{ $milestone->title }}</strong><span>{{ $milestone->statusLabel() }}</span></div>
                                    <div class="mini-progress"><span style="width: {{ $milestone->progress_percent }}%"></span></div>
                                    <div class="muted">Planned: {{ optional($milestone->planned_start_date)->format('d M') ?: '-' }} → {{ optional($milestone->planned_end_date)->format('d M') ?: '-' }}</div>
                                    @if($milestone->client_note)<p style="white-space:pre-wrap;color:#344054;">{{ $milestone->client_note }}</p>@endif
                                </div>
                            @endforeach
                        </div>
                    @empty
                        <div class="empty">No public milestones yet.</div>
                    @endforelse
                </div>
            </section>

            <section class="card">
                <div class="section-head"><div><p class="eyebrow">Progress</p><h2>Public Tracking Timeline</h2></div><span class="chip">{{ $project->publicTrackingUpdates->count() }} updates</span></div>
                <div class="timeline">
                    @forelse($project->publicTrackingUpdates as $tracking)
                        <div class="timeline-item">
                            <div class="dot"></div>
                            <div class="timeline-content">
                                <div class="timeline-head"><strong>{{ $tracking->title }}</strong><span>{{ optional($tracking->occurred_at)->format('d M Y, h:i A') }}</span></div>
                                <div class="muted">{{ $tracking->statusLabel() }} @if($tracking->product) · {{ $tracking->product->product_name }} @endif @if($tracking->location) · {{ $tracking->location }} @endif</div>
                                @if($tracking->progress_percent !== null)<div class="mini-progress"><span style="width: {{ $tracking->progress_percent }}%"></span></div>@endif
                                @if($tracking->notes)<p style="white-space:pre-wrap;color:#344054;">{{ $tracking->notes }}</p>@endif
                            </div>
                        </div>
                    @empty
                        <div class="empty">No public tracking updates yet.</div>
                    @endforelse
                </div>
            </section>
        </main>

        <aside>
            <section class="card">
                <div class="section-head"><div><p class="eyebrow">Files</p><h2>Public Attachments</h2></div></div>
                <div class="file-list">
                    @forelse($project->publicAttachments as $attachment)
                        <div class="file">
                            @if($attachment->isImage())<img src="{{ $attachment->fileUrl() }}" alt="{{ $attachment->title }}">@else<div class="file-icon">📄</div>@endif
                            <div><strong>{{ $attachment->title ?: $attachment->original_name }}</strong><span>{{ $attachment->categoryLabel() }} @if($attachment->product) · {{ $attachment->product->product_name }} @endif</span><a href="{{ $attachment->fileUrl() }}" target="_blank">Open file</a></div>
                        </div>
                    @empty
                        <div class="empty">No public files yet.</div>
                    @endforelse
                </div>
            </section>

            <section class="card">
                <div class="section-head"><div><p class="eyebrow">Payments</p><h2>Public Payments</h2></div></div>
                <div class="payment-list">
                    @forelse($project->publicPayments as $payment)
                        <div class="payment"><div><span>{{ $payment->typeLabel() }}</span><strong>{{ optional($payment->payment_date)->format('d M Y') }}</strong></div><strong class="{{ $payment->transaction_type }}">{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</strong></div>
                    @empty
                        <div class="empty">No public payment entries.</div>
                    @endforelse
                </div>
            </section>

            <section class="card">
                <div class="section-head"><div><p class="eyebrow">Comments</p><h2>Discussion</h2></div></div>
                <form method="POST" action="{{ route('projects.public.comments.store', $project->public_token) }}" class="portal-form">
                    @csrf
                    <div class="master-field"><label class="master-label">Your Name</label><input class="master-input" type="text" name="client_name" required value="{{ old('client_name') }}"></div>
                    <div class="master-field"><label class="master-label">Email (optional)</label><input class="master-input" type="email" name="client_email" value="{{ old('client_email') }}"></div>
                    <div class="master-field"><label class="master-label">Related Product (optional)</label><select class="master-select" name="project_product_id"><option value="">Project level</option>@foreach($project->products as $projectProduct)<option value="{{ $projectProduct->id }}">{{ $projectProduct->product_name }}</option>@endforeach</select></div>
                    <div class="master-field"><label class="master-label">Comment</label><textarea class="master-textarea" name="body" rows="4" required>{{ old('body') }}</textarea></div>
                    <button class="master-btn-primary" type="submit">Submit Comment</button>
                </form>
                <div class="comment-list" style="margin-top:14px;">
                    @forelse($project->publicComments as $comment)
                        <div class="comment"><div class="comment-head"><strong>{{ $comment->authorName() }}</strong><span>{{ $comment->created_at->format('d M Y, h:i A') }}</span></div>@if($comment->product)<div class="muted">For: {{ $comment->product->product_name }}</div>@endif<p>{{ $comment->body }}</p></div>
                    @empty
                        <div class="empty">No public comments yet.</div>
                    @endforelse
                </div>
            </section>

            <section class="card">
                <div class="section-head"><div><p class="eyebrow">Upload</p><h2>Send Document</h2></div></div>
                <form method="POST" action="{{ route('projects.public.attachments.store', $project->public_token) }}" enctype="multipart/form-data" class="portal-form">
                    @csrf
                    <div class="master-field"><label class="master-label">Your Name</label><input class="master-input" type="text" name="client_name" required></div>
                    <div class="master-field"><label class="master-label">Related Product</label><select class="master-select" name="project_product_id"><option value="">Project level</option>@foreach($project->products as $projectProduct)<option value="{{ $projectProduct->id }}">{{ $projectProduct->product_name }}</option>@endforeach</select></div>
                    <div class="master-field"><label class="master-label">Title</label><input class="master-input" type="text" name="title" placeholder="Artwork approval / document / photo"></div>
                    <div class="master-field"><label class="master-label">Files</label><input class="master-input" type="file" name="attachments[]" multiple required accept=".jpg,.jpeg,.png,.webp,.gif,.heic,.heif,.pdf,.doc,.docx,.xls,.xlsx,.csv,.ppt,.pptx,.txt,.zip"></div>
                    <div class="master-field"><label class="master-label">Notes</label><textarea class="master-textarea" name="notes" rows="3"></textarea></div>
                    <button class="master-btn-primary" type="submit">Upload Document</button>
                </form>
            </section>
        </aside>
    </div>

    <div class="footer">This secure project portal is shared by MissPack for project progress visibility.</div>
</div>
    <script src="{{ asset('assets/vendor/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/select2/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/js/master-alert.js') }}"></script>
    <script src="{{ asset('assets/js/master-selects.js') }}"></script>
</body>
</html>
