<section class="master-tab-panel" id="project-panel-logs" role="tabpanel" aria-labelledby="project-tab-logs">
    <div class="project-blocks">
        <section class="master-card master-card--flat project-detail-card">
            <div class="master-section-head">
                <div>
                    <h2 class="master-section-title">Activity logs</h2>
                    <p class="master-sub">{{ $project->logs->count() }}
                    {{ \Illuminate\Support\Str::plural('entry', $project->logs->count()) }} — who changed what, and when</p>
                </div>
            </div>
            @forelse($project->logs as $log)
                <article class="project-log">
                    <div class="project-log-head">
                        <strong>{{ $log->title }}</strong>
                        <div class="project-log-badges">
                            <span class="master-badge {{ $log->is_public ? 'status-completed' : 'status-draft' }}">
                            {{ $log->is_public ? 'Public' : 'Internal' }}</span>
                            <span class="master-badge status-draft">{{ $log->event_type }}</span>
                        </div>
                    </div>
                    <p class="project-comment-meta">
                        {{ $log->actor_name ?: 'System' }} · {{ $log->created_at->format('d M Y, h:i A') }}
                        @if ($log->product) · {{ $log->product->product_name }} @endif
                    </p>
                    @if ($log->description)
                        <p class="project-comment-body">{{ $log->description }}</p>
                    @endif
                </article>
            @empty
                <div class="master-empty-state">
                    <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                    <p>No log entries yet. Every status change, update and deletion on this project writes one, so the record
                    can answer for itself.</p>
                </div>
            @endforelse
        </section>
    </div>
</section>

