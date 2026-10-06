<section class="master-tab-panel" id="project-panel-comments" role="tabpanel" aria-labelledby="project-tab-comments">
    <div class="project-blocks">
        <section class="master-card master-card--flat project-detail-card">
            <div class="master-section-head">
                <div>
                    <h2 class="master-section-title">Comments</h2>
                    <p class="master-sub">{{ $project->comments->count() }}
                        {{ \Illuminate\Support\Str::plural('comment', $project->comments->count()) }} — internal notes and
                    what the client sees</p>
                </div>
                <div class="master-section-meta">
                    <button type="button" class="master-btn master-btn-primary" id="openAddCommentModal">
                        <i class="fas fa-plus" aria-hidden="true"></i> Add comment</button>
                </div>
            </div>
            <div class="project-thread">
                @forelse($project->comments as $comment)
                    <article class="project-comment {{ $comment->is_pinned ? 'is-pinned' : '' }}">
                        <div class="project-comment-head">
                            <strong>{{ $comment->authorName() }}</strong>
                            <div class="project-comment-badges">
                                @if ($comment->is_pinned)
                                    <span class="master-badge status-planned"><i class="fa-solid fa-thumbtack" aria-hidden="true"></i> Pinned</span>
                                @endif
                                <span class="master-badge {{ $comment->is_public ? 'status-completed' : 'status-draft' }}">
                                {{ $comment->is_public ? 'Public' : 'Internal' }}</span>
                            </div>
                        </div>
                        <p class="project-comment-meta">
                            {{ $comment->created_at->format('d M Y, h:i A') }}
                            @if ($comment->product) · {{ $comment->product->product_name }} @endif
                        </p>
                        <p class="project-comment-body">{{ $comment->body }}</p>
                        <div class="project-comment-actions">
                            <button type="button" class="master-btn master-btn-soft master-btn-sm editCommentBtn"
                                data-comment='@json($comment)'><i class="fas fa-pen" aria-hidden="true"></i> Edit</button>
                            <form method="POST" action="{{ route('projects.comments.destroy', $comment) }}"
                                data-confirm="Delete this comment?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="master-btn master-btn-danger-ghost master-btn-sm">
                                    <i class="fas fa-trash" aria-hidden="true"></i> Delete</button>
                            </form>
                        </div>
                    </article>
                @empty
                    <div class="master-empty-state">
                        <i class="fa-regular fa-comment-dots" aria-hidden="true"></i>
                        <p>No comments yet. Write down what the office agreed on the phone — the internal ones stay in the
                        office, the public ones reach the portal.</p>
                        <button type="button" class="master-btn master-btn-soft master-btn-sm"
                            data-modal-open="addCommentModal"><i class="fas fa-plus" aria-hidden="true"></i> Add comment</button>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</section>


<!--Add Comment-->
<div class="master-modal" id="addCommentModal" aria-hidden="true">
    <div class="master-modal-card">
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
                        <div class="master-toggle-group" >
                            <label class="master-label">Public for Client</label><br>

                            <label class="master-switch">
                                <input type="checkbox" name="is_public" value="1">
                                <span class="master-slider"></span>
                            </label>
                        </div>
                    </div>
                    <div class="master-field">
                        <div class="master-toggle-group" >
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
    <div class="master-modal-card">
        <form id="editCommentForm" method="POST"
            data-update-url="{{ route('projects.comments.update', ['projectComment' => '__ID__']) }}">
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
                        <div class="master-toggle-group" >
                            <label class="master-label">Public for Client</label><br>

                            <label class="master-switch">
                                <input type="checkbox" name="is_public" value="1">
                                <span class="master-slider"></span>
                            </label>
                        </div>
                    </div>
                    <div class="master-field">
                        <div class="master-toggle-group" >
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
