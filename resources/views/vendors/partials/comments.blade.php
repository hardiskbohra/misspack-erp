<section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-notes-heading">
    <div class="master-section-head">
        <div>
            <h2 class="master-section-title" id="vendor-notes-heading">Team notes</h2>
            <p class="master-sub">Internal follow-ups and context shared with the purchasing team.</p>
        </div>
        <div class="vendor-comment-toolbar">
            <span class="master-chip">{{ number_format($summary['comments_count']) }} {{ \Illuminate\Support\Str::plural('note', $summary['comments_count']) }}</span>
            @if ($commentsAvailable && \Illuminate\Support\Facades\Route::has('vendors.comments.store'))
                <button type="button" class="master-btn master-btn-primary" id="openAddCommentModal">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Add note
                </button>
            @endif
        </div>
    </div>

    @if ($commentsAvailable)
        @if ($vendor->comments->isNotEmpty())
            <div class="vendor-comment-list">
                @foreach ($vendor->comments as $comment)
                    <article class="vendor-comment-card {{ $comment->is_pinned ? 'is-pinned' : '' }}">
                        <div class="vendor-comment-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($comment->creator?->name ?: 'T', 0, 1)) }}</div>
                        <div class="vendor-comment-content">
                            <div class="vendor-comment-head">
                                <div><strong>{{ $comment->creator?->name ?: 'Team member' }}</strong><span>{{ $comment->created_at?->format('d M Y · h:i A') ?: 'Date not available' }}</span></div>
                                @if ($comment->is_pinned)<span class="master-badge vendor-pinned-badge"><i class="fa-solid fa-thumbtack" aria-hidden="true"></i> Pinned</span>@endif
                            </div>
                            <p class="vendor-comment-body">{{ $comment->body }}</p>
                            @if (\Illuminate\Support\Facades\Route::has('vendors.comments.destroy'))
                                <form method="POST" action="{{ route('vendors.comments.destroy', $comment) }}"
                                    data-confirm="Delete this internal note?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="master-btn master-btn-light vendor-comment-delete" type="submit">
                                        <i class="fa-regular fa-trash-can" aria-hidden="true"></i> Delete note
                                    </button>
                                </form>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="master-empty-state"><i class="fa-regular fa-note-sticky" aria-hidden="true"></i><p>No team notes have been added yet.</p></div>
        @endif
        @if (\Illuminate\Support\Facades\Route::has('vendors.comments.store'))
            @include('vendors.partials.comment-modal')
        @endif
    @else
        <div class="master-empty-state"><i class="fa-solid fa-database" aria-hidden="true"></i><p>Internal vendor notes are not installed for this workspace.</p></div>
    @endif
</section>
