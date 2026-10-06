{{--
    The overview: what this project is, where it stands, what it is worth, and
    what is on the record behind each tab. Every card links to the tab that
    owns the detail, so the page has one door per question.
--}}
<section class="master-tab-panel" id="project-panel-overview" role="tabpanel" aria-labelledby="project-tab-overview">
    <div class="project-blocks">
        <div class="project-detail-grid">
            <section class="master-card master-card--flat" aria-labelledby="project-snapshot-heading">
                <div class="master-section-head">
                    <div>
                        <h2 class="master-section-title" id="project-snapshot-heading">Snapshot</h2>
                        <p class="master-sub">{{ $project->project_number }}</p>
                    </div>
                </div>
                <div class="master-facts">
                    <div class="master-info"><span>Client</span><strong>{{ $project->clientName() }}</strong></div>
                    <div class="master-info"><span>Owner</span>
                        <strong @class(['master-empty-value' => ! $project->assignedUser])>{{ $project->assignedUser?->name ?: 'Unassigned' }}</strong>
                    </div>
                    <div class="master-info"><span>Start date</span>
                        <strong @class(['master-empty-value' => ! $project->start_date])>{{ $project->start_date?->format('d M Y') ?: 'Not set' }}</strong>
                    </div>
                    <div class="master-info"><span>Target date</span>
                        <strong @class(['project-money-late' => $isLate, 'master-empty-value' => ! $project->target_date])>{{ $project->target_date?->format('d M Y') ?: 'Not set' }}</strong>
                    </div>
                    <div class="master-info"><span>Completed</span>
                        <strong @class(['master-empty-value' => ! $project->completed_at])>{{ $project->completed_at?->format('d M Y') ?: 'Still running' }}</strong>
                    </div>
                    <div class="master-info"><span>Accepted quote</span>
                        <strong @class(['master-empty-value' => ! $project->customerQuote])>{{ $project->customerQuote?->quote_number ?: 'No quote mapped' }}</strong>
                    </div>
                    <div class="master-info"><span>Currency</span><strong>{{ $project->currency ?: 'INR' }}</strong></div>
                    <div class="master-info"><span>Created</span>
                        <strong>{{ $project->created_at?->format('d M Y') ?: '—' }}
                            <span class="project-fact-note">{{ $project->creator?->name ? 'by '.$project->creator->name : 'by the office' }}</span></strong>
                    </div>
                </div>
            </section>

            <section class="master-card master-card--flat" aria-labelledby="project-progress-heading">
                <div class="master-section-head">
                    <div>
                        <h2 class="master-section-title" id="project-progress-heading">Progress</h2>
                        <p class="master-sub">{{ $project->stageLabel() }}</p>
                    </div>
                    <div class="master-section-meta">
                        <a class="master-btn master-btn-soft master-btn-sm" href="{{ $recordUrl('milestones') }}">
                            <i class="fa-solid fa-flag-checkered" aria-hidden="true"></i> Milestones
                        </a>
                    </div>
                </div>
                <div class="project-progress-block">
                    <div class="project-progress-row">
                        <span class="project-record-progress-track" role="img" aria-label="{{ $progress }} percent complete">
                            <span style="width: {{ $progress }}%"></span>
                        </span>
                        <strong>{{ $progress }}%</strong>
                    </div>
                    <p class="project-fact-note">
                        {{ $milestonesDone }} of {{ $project->milestones->count() }} milestones done
                        @if ($milestonesOpen > 0) · {{ $milestonesOpen }} still open @endif
                    </p>
                </div>
                <div class="master-facts">
                    <div class="master-info"><span>Products</span><strong>{{ number_format($project->products->count()) }}</strong></div>
                    <div class="master-info"><span>Milestones</span><strong>{{ number_format($project->milestones->count()) }}</strong></div>
                    <div class="master-info"><span>Shipments</span><strong>{{ number_format($project->shipments->count()) }}</strong></div>
                    <div class="master-info"><span>Activity updates</span><strong>{{ number_format($project->trackingUpdates->count()) }}</strong></div>
                </div>
            </section>

            <section class="master-card master-card--flat" aria-labelledby="project-money-heading">
                <div class="master-section-head">
                    <div>
                        <h2 class="master-section-title" id="project-money-heading">Money at a glance</h2>
                        <p class="master-sub">Payments and the ledger rows linked to this project</p>
                    </div>
                    <div class="master-section-meta">
                        <a class="master-btn master-btn-soft master-btn-sm" href="{{ $recordUrl('payments') }}">
                            <i class="fa-solid fa-indian-rupee-sign" aria-hidden="true"></i> Open payments
                        </a>
                    </div>
                </div>
                <div class="master-facts">
                    <div class="master-info"><span>Estimated value</span><strong>{{ $money($project->estimated_value) }}</strong></div>
                    <div class="master-info"><span>Received</span><strong class="project-money-in">{{ $money($totals['inward']) }}</strong></div>
                    <div class="master-info"><span>Outstanding</span>
                        <strong class="{{ $totals['outstanding'] > 0 ? 'project-money-out' : 'master-empty-value' }}">{{ $totals['outstanding'] > 0 ? $money($totals['outstanding']) : 'Nothing pending' }}</strong>
                    </div>
                    <div class="master-info"><span>Expenses</span><strong class="project-money-out">{{ $money($totals['outward']) }}</strong></div>
                    <div class="master-info"><span>{{ $totals['net'] >= 0 ? 'Profit' : 'Loss' }}</span>
                        <strong class="{{ $totals['net'] >= 0 ? 'project-money-in' : 'project-money-out' }}">{{ $money(abs($totals['net'])) }}</strong>
                    </div>
                    <div class="master-info"><span>Payment entries</span><strong>{{ number_format($project->payments->count()) }}</strong></div>
                    <div class="master-info"><span>Ledger entries</span><strong>{{ number_format($project->relationLoaded('cashflowEntries') ? $project->cashflowEntries->count() : 0) }}</strong></div>
                    <div class="master-info"><span>Received against</span><strong>{{ $receivedPercent }}% of the estimate</strong></div>
                </div>
            </section>

            <section class="master-card master-card--flat" aria-labelledby="project-portal-heading">
                <div class="master-section-head">
                    <div>
                        <h2 class="master-section-title" id="project-portal-heading">Client portal</h2>
                        <p class="master-sub">The read-only page the client opens with a token link</p>
                    </div>
                    <div class="master-section-meta">
                        @if ($project->show_client_portal)
                            <span class="master-badge status-completed">Enabled</span>
                        @else
                            <span class="master-badge status-draft">Hidden</span>
                        @endif
                    </div>
                </div>
                @if ($project->show_client_portal)
                    <label class="master-field">
                        <span class="master-label">Share link</span>
                        <input class="master-input" type="text" value="{{ $portalUrl }}" readonly id="portalLinkInput"
                            aria-label="Client portal link">
                    </label>
                    <div class="project-portal-actions">
                        <button type="button" class="master-btn master-btn-soft master-btn-sm" id="copyPortalLink">
                            <i class="fa-solid fa-copy" aria-hidden="true"></i> Copy link
                        </button>
                        <a class="master-btn master-btn-light master-btn-sm" href="{{ $portalUrl }}" target="_blank" rel="noopener">
                            <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> Open portal
                        </a>
                    </div>
                @else
                    <div class="master-empty-state">
                        <i class="fa-solid fa-eye-slash" aria-hidden="true"></i>
                        <p>The portal is hidden for this project. Turn it on from the project form and the client can follow
                        progress with this token link.</p>
                        <a class="master-btn master-btn-soft master-btn-sm" href="{{ route('projects.edit', $project) }}">
                            <i class="fa-solid fa-pen" aria-hidden="true"></i> Edit project
                        </a>
                    </div>
                @endif
            </section>
        </div>

        @if ($project->scope_summary || $project->deliverables || $project->client_notes || $project->internal_notes)
            <section class="master-card master-card--flat" aria-labelledby="project-scope-heading">
                <div class="master-section-head">
                    <div>
                        <h2 class="master-section-title" id="project-scope-heading">Scope and notes</h2>
                        <p class="master-sub">What was agreed, what is delivered, and what stays in the office</p>
                    </div>
                </div>
                <div class="master-facts">
                    @if ($project->scope_summary)
                        <div class="master-info is-wide"><span>Scope summary</span><strong class="project-note-text">{{ $project->scope_summary }}</strong></div>
                    @endif
                    @if ($project->deliverables)
                        <div class="master-info is-wide"><span>Deliverables</span><strong class="project-note-text">{{ $project->deliverables }}</strong></div>
                    @endif
                    @if ($project->client_notes)
                        <div class="master-info is-wide"><span>Client notes</span><strong class="project-note-text">{{ $project->client_notes }}</strong></div>
                    @endif
                    @if ($project->internal_notes)
                        <div class="master-info is-wide"><span>Internal notes</span>
                            <strong class="project-note-text">{{ $project->internal_notes }}</strong>
                            <span class="project-fact-note"><i class="fa-solid fa-lock" aria-hidden="true"></i> Never shown on the
                            client portal</span>
                        </div>
                    @endif
                </div>
            </section>
        @endif

        <section class="master-card master-card--flat" aria-labelledby="project-record-heading">
            <div class="master-section-head">
                <div>
                    <h2 class="master-section-title" id="project-record-heading">What is on the record</h2>
                    <p class="master-sub">One tab per question — each carries its own count</p>
                </div>
            </div>
            <nav class="project-jump" aria-label="Project sections">
                @foreach ($tabs as $key => $label)
                    @continue ($key === 'overview')
                    <a href="{{ $recordUrl($key) }}">
                        <span>{{ $label }}</span>
                        <span class="project-jump-count">{{ number_format($tabCounts[$key] ?? 0) }}</span>
                    </a>
                @endforeach
            </nav>
        </section>

        <section class="master-card master-card--flat" aria-labelledby="project-activity-heading">
            <div class="master-section-head">
                <div>
                    <h2 class="master-section-title" id="project-activity-heading">Recent activity</h2>
                    <p class="master-sub">The latest updates on this project</p>
                </div>
                <div class="master-section-meta">
                    <a class="master-btn master-btn-soft master-btn-sm" href="{{ $recordUrl('tracking') }}">
                        <i class="fa-solid fa-location-dot" aria-hidden="true"></i> All activity
                    </a>
                </div>
            </div>
            @forelse ($project->trackingUpdates->sortByDesc('occurred_at')->take(6) as $tracking)
                <div class="project-timeline-row">
                    <div>
                        <strong>{{ $tracking->title }}</strong>
                        <span class="project-fact-note">
                            {{ $tracking->occurred_at?->format('d M Y') ?: 'No date' }}
                            @if ($tracking->product) · {{ $tracking->product->product_name }} @endif
                            @if ($tracking->location) · {{ $tracking->location }} @endif
                        </span>
                    </div>
                    <span class="master-badge status-{{ str_replace('_', '-', (string) $tracking->status) }}">{{ $tracking->statusLabel() }}</span>
                </div>
            @empty
                <div class="master-empty-state">
                    <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                    <p>No activity updates yet. Add the first one from the activities tab and the client portal timeline
                    starts telling the story.</p>
                    <a class="master-btn master-btn-soft master-btn-sm" href="{{ $recordUrl('tracking') }}">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i> Add activity
                    </a>
                </div>
            @endforelse
        </section>
    </div>
</section>
