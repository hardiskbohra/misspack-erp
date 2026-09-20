<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $project->name }} | Project Progress</title>
    <style>
        :root{--primary:#4f83f1;--accent:#ef4770;--ink:#172033;--muted:#667085;--line:#e7ecf5;--soft:#f7f9fc;--green:#039855;--red:#d92d20;--amber:#b54708}*{box-sizing:border-box}body{margin:0;background:#f5f7fb;color:var(--ink);font-family:"Inter","Segoe UI",Roboto,Arial,sans-serif}.portal-shell{max-width:1180px;margin:0 auto;padding:18px}.portal-top{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:16px}.portal-logo{display:flex;align-items:center;gap:10px}.portal-logo img{height:44px;width:auto;object-fit:contain}.portal-logo strong{display:block;font-size:18px}.portal-logo span{display:block;color:var(--muted);font-size:12px}.portal-badge{background:#e8fff3;color:var(--green);border:1px solid #abefc6;border-radius:999px;padding:9px 12px;font-weight:900;font-size:12px}.portal-hero{background:linear-gradient(135deg,#4f83f1,#7b61ff);color:#fff;border-radius:28px;padding:26px;box-shadow:0 18px 45px rgba(79,131,241,.22);display:grid;grid-template-columns:1fr auto;gap:18px}.eyebrow{margin:0 0 8px;text-transform:uppercase;letter-spacing:.12em;font-size:11px;font-weight:900;opacity:.82}.portal-hero h1{margin:0;font-size:32px;line-height:1.15}.portal-hero p{margin:10px 0 0;opacity:.9;max-width:760px}.progress-box{background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.28);border-radius:22px;padding:18px;width:240px}.progress-box strong{font-size:38px}.progress{height:11px;background:rgba(255,255,255,.25);border-radius:999px;overflow:hidden;margin-top:12px}.progress span{display:block;height:100%;background:#fff;border-radius:999px}.portal-alert{border-radius:16px;padding:12px 14px;margin:14px 0;font-weight:800}.portal-alert.success{background:#e8fff3;color:#027a48;border:1px solid #abefc6}.portal-alert.error{background:#fff1f3;color:#b42318;border:1px solid #fecdca}.metric-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:16px 0}.metric{background:#fff;border:1px solid var(--line);border-radius:20px;padding:16px;box-shadow:0 10px 28px rgba(22,34,51,.05)}.metric span{display:block;color:var(--muted);font-size:12px;font-weight:900;text-transform:uppercase}.metric strong{display:block;margin-top:7px;font-size:18px}.main-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(330px,.65fr);gap:16px}.card{background:#fff;border:1px solid var(--line);border-radius:22px;padding:18px;box-shadow:0 10px 28px rgba(22,34,51,.05);margin-bottom:16px}.section-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:14px}.section-head h2{margin:0;font-size:20px}.chip{display:inline-flex;align-items:center;border-radius:999px;background:#eef3ff;color:var(--primary);padding:7px 10px;font-size:12px;font-weight:900}.chip.green{background:#e8fff3;color:var(--green)}.chip.amber{background:#fff7e6;color:var(--amber)}.info-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.info{background:var(--soft);border:1px solid var(--line);border-radius:16px;padding:12px}.info span{display:block;color:var(--muted);font-size:12px;font-weight:900}.info strong{display:block;margin-top:5px}.text-block{border-top:1px solid var(--line);margin-top:14px;padding-top:14px}.text-block span{color:var(--muted);font-size:12px;font-weight:900;text-transform:uppercase}.text-block p{white-space:pre-wrap;color:#344054;margin:7px 0 0}.product-grid{display:grid;gap:12px}.product-card{border:1px solid var(--line);border-radius:18px;padding:14px;background:#fff}.product-top{display:flex;justify-content:space-between;gap:12px}.product-card h3{margin:0 0 6px;font-size:17px}.muted{color:var(--muted);font-size:13px}.timeline{display:flex;flex-direction:column;gap:14px}.timeline-item{display:grid;grid-template-columns:22px 1fr;gap:10px}.dot{width:14px;height:14px;border-radius:50%;background:var(--primary);margin-top:5px;box-shadow:0 0 0 5px #eaf2ff}.timeline-content{border:1px solid var(--line);border-radius:16px;padding:12px}.timeline-head{display:flex;justify-content:space-between;gap:10px}.timeline-head strong{font-size:15px}.timeline-head span{color:var(--muted);font-size:12px}.mini-progress{height:7px;background:#eef2f7;border-radius:999px;overflow:hidden;margin-top:9px}.mini-progress span{display:block;height:100%;background:linear-gradient(90deg,var(--primary),#12b76a)}.file-list,.comment-list,.payment-list{display:flex;flex-direction:column;gap:10px}.file{display:grid;grid-template-columns:58px 1fr;gap:10px;border:1px solid var(--line);border-radius:16px;padding:10px}.file img,.file-icon{width:58px;height:58px;border-radius:14px;object-fit:cover;background:#eef3ff;color:var(--primary);display:grid;place-items:center;font-size:24px}.file strong,.file span{display:block}.file span{color:var(--muted);font-size:12px;margin-top:3px}.file a{color:var(--primary);font-weight:900;text-decoration:none;margin-top:5px;display:inline-flex}.comment,.payment{border:1px solid var(--line);border-radius:16px;padding:12px}.comment-head,.payment{display:flex;justify-content:space-between;gap:10px}.comment-head strong{font-size:14px}.comment-head span{color:var(--muted);font-size:12px}.comment p{margin:8px 0 0;color:#344054;white-space:pre-wrap}.payment span{display:block;color:var(--muted);font-size:12px}.payment strong.inward{color:var(--green)}.payment strong.outward{color:var(--red)}.portal-form{display:flex;flex-direction:column;gap:10px}.field{display:flex;flex-direction:column;gap:6px}.field label{font-size:12px;font-weight:900;color:#5d6b82}.field input,.field select,.field textarea{border:1px solid #dfe5f2;border-radius:14px;padding:11px;background:#fff;outline:none;width:100%}.field input:focus,.field select:focus,.field textarea:focus{border-color:var(--primary);box-shadow:0 0 0 4px rgba(79,131,241,.1)}.btn{border:0;border-radius:14px;background:var(--accent);color:#fff;font-weight:900;padding:11px 15px;cursor:pointer}.empty{padding:16px;text-align:center;color:var(--muted);border:1px dashed #d8deea;border-radius:16px;background:#fbfcff}.footer{text-align:center;color:var(--muted);font-size:12px;margin:18px 0 6px}@media(max-width:991px){.portal-hero,.main-grid{grid-template-columns:1fr}.progress-box{width:100%}.metric-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:640px){.portal-shell{padding:12px}.portal-top,.product-top,.timeline-head,.comment-head,.payment{flex-direction:column}.metric-grid,.info-grid{grid-template-columns:1fr}.portal-hero{padding:20px;border-radius:22px}.portal-hero h1{font-size:25px}.card{padding:14px}.file{grid-template-columns:48px 1fr}.file img,.file-icon{width:48px;height:48px}}
    </style>
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
                    <div class="field"><label>Your Name</label><input type="text" name="client_name" required value="{{ old('client_name') }}"></div>
                    <div class="field"><label>Email (optional)</label><input type="email" name="client_email" value="{{ old('client_email') }}"></div>
                    <div class="field"><label>Related Product (optional)</label><select name="project_product_id"><option value="">Project level</option>@foreach($project->products as $projectProduct)<option value="{{ $projectProduct->id }}">{{ $projectProduct->product_name }}</option>@endforeach</select></div>
                    <div class="field"><label>Comment</label><textarea name="body" rows="4" required>{{ old('body') }}</textarea></div>
                    <button class="btn" type="submit">Submit Comment</button>
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
                    <div class="field"><label>Your Name</label><input type="text" name="client_name" required></div>
                    <div class="field"><label>Related Product</label><select name="project_product_id"><option value="">Project level</option>@foreach($project->products as $projectProduct)<option value="{{ $projectProduct->id }}">{{ $projectProduct->product_name }}</option>@endforeach</select></div>
                    <div class="field"><label>Title</label><input type="text" name="title" placeholder="Artwork approval / document / photo"></div>
                    <div class="field"><label>Files</label><input type="file" name="attachments[]" multiple required accept=".jpg,.jpeg,.png,.webp,.gif,.heic,.heif,.pdf,.doc,.docx,.xls,.xlsx,.csv,.ppt,.pptx,.txt,.zip"></div>
                    <div class="field"><label>Notes</label><textarea name="notes" rows="3"></textarea></div>
                    <button class="btn" type="submit">Upload Document</button>
                </form>
            </section>
        </aside>
    </div>

    <div class="footer">This secure project portal is shared by MissPack for project progress visibility.</div>
</div>
</body>
</html>
