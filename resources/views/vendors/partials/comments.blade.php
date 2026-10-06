<section class="master-tab-panel" id="vendor-panel-comments" role="tabpanel" aria-labelledby="vendor-tab-comments">
    <div class="vendor-panel-head">
        <div>
            <h2 class="vendor-detail-title">Internal comments</h2>
            <p class="vendor-detail-help">Notes for your team about this supplier — payment follow-ups, quality issues and reminders.</p>
        </div>
        <div class="vendor-panel-meta">
            <span class="vendor-pill">{{ $commentsAvailable && $vendor->relationLoaded('comments') ? $vendor->comments->count() : 0 }} {{ \Illuminate\Support\Str::plural('comment', $commentsAvailable && $vendor->relationLoaded('comments') ? $vendor->comments->count() : 0) }}</span>
            @if ($commentsAvailable)
                <button type="button" class="master-btn master-btn-primary master-btn-sm" id="openAddCommentModal">
                    <i class="fas fa-plus" aria-hidden="true"></i> Add comment
                </button>
            @endif
        </div>
    </div>

    @if ($commentsAvailable)
        <div class="vendor-comment-grid">
            @forelse($vendor->comments as $comment)
                <article class="master-card master-card--flat vendor-comment {{ $comment->is_pinned ? 'is-pinned' : '' }}">
                    <header class="vendor-comment-head">
                        <span class="vendor-comment-author">
                            <span class="vendor-comment-avatar" aria-hidden="true">{{ strtoupper(mb_substr($comment->creator?->name ?: 'Team', 0, 1)) }}</span>
                            <span>
                                <strong>{{ $comment->creator?->name ?: 'Internal team' }}</strong>
                                <span class="master-sub">{{ $comment->created_at?->format('d M Y, h:i A') }}</span>
                            </span>
                        </span>
                        <span class="vendor-panel-meta">
                            @if ($comment->is_pinned)
                                <span class="vendor-pill is-pinned"><i class="fa-solid fa-thumbtack" aria-hidden="true"></i> Pinned</span>
                            @endif
                            @if (\Illuminate\Support\Facades\Route::has('vendors.comments.destroy'))
                                <form method="POST" action="{{ route('vendors.comments.destroy', $comment) }}"
                                    data-confirm="Delete this comment?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="master-icon-btn danger" aria-label="Delete comment">
                                        <i class="far fa-trash-alt" aria-hidden="true"></i>
                                    </button>
                                </form>
                            @endif
                        </span>
                    </header>
                    <p class="vendor-comment-body">{{ $comment->body }}</p>
                </article>
            @empty
                <div class="master-card master-card--flat vendor-attachment-empty">
                    <div class="master-list-empty">
                        <span class="master-list-empty-icon" aria-hidden="true"><i class="fa-regular fa-comment"></i></span>
                        <h3 class="master-list-empty-title">No comments yet</h3>
                        <p class="master-list-empty-text">Leave a note so the next person knows what was agreed or what is still open.</p>
                        <div class="master-list-empty-actions">
                            <button type="button" class="master-btn master-btn-primary" id="openAddCommentModalEmpty">
                                <i class="fas fa-plus" aria-hidden="true"></i> Add first comment
                            </button>
                        </div>
                    </div>
                </div>
            @endforelse
        </div>
    @else
        <div class="master-card master-card--flat">
            <div class="master-list-empty">
                <span class="master-list-empty-icon" aria-hidden="true"><i class="fa-regular fa-comment"></i></span>
                <h3 class="master-list-empty-title">Comments are not available</h3>
                <p class="master-list-empty-text">The vendor comments table is missing on this database, so notes are disabled.</p>
            </div>
        </div>
    @endif
</section>
