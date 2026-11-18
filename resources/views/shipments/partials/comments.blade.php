<section class="master-card master-card--flat master-section ship-comments" aria-labelledby="shipmentCommentsTitle">
    <div class="master-section-head">
        <div>
            <h2 class="master-section-title" id="shipmentCommentsTitle">Internal comments</h2>
            <p class="master-sub">Internal only — comments and their attachments are not shown on client portal or public tracking.</p>
        </div>
        @if ($commentsAvailable)
            <span class="master-chip">{{ $tabCounts['comments'] }} {{ \Illuminate\Support\Str::plural('comment', $tabCounts['comments']) }}</span>
        @endif
    </div>

    @if (! $commentsAvailable)
        <div class="master-alert warning" role="status">Comments are unavailable until the latest database migration is applied.</div>
    @else
        @if (auth()->user()?->hasPermission('operations.manage'))
            <form method="POST" action="{{ route('shipments.comments.store', $shipment) }}" enctype="multipart/form-data" class="ship-comment-form">
                @csrf
                <input type="hidden" name="shipment_comment_form" value="1">
                @if ($errors->any() && old('shipment_comment_form'))
                    <div class="master-alert warning" role="alert">
                        <strong>Comment could not be saved</strong>
                        <ul>@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
                        <span>Please choose your files again after correcting the form.</span>
                    </div>
                @endif
                <div class="ship-form-field">
                    <label class="master-label" for="shipmentCommentBody">Add a comment <span class="master-required" aria-hidden="true">*</span></label>
                    <textarea class="master-textarea" id="shipmentCommentBody" name="body" rows="4" maxlength="4000" required placeholder="Share an internal update or follow-up">{{ old('shipment_comment_form') ? old('body') : '' }}</textarea>
                </div>
                <div class="ship-form-field">
                    <label class="master-label" for="shipmentCommentFiles">Attachments (optional)</label>
                    <input class="master-input" id="shipmentCommentFiles" type="file" name="attachments[]" multiple
                        accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt" aria-describedby="shipmentCommentFileHelp">
                    <p class="master-help" id="shipmentCommentFileHelp">Up to 5 files, 10 MB each. PDF, JPG, PNG, WEBP, GIF, DOC, DOCX, XLS, XLSX, CSV and TXT.</p>
                </div>
                <div class="ship-comment-actions">
                    <span class="master-sub"><i class="fa-solid fa-lock" aria-hidden="true"></i> Internal only</span>
                    <button type="submit" class="master-btn master-btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add comment</button>
                </div>
            </form>
        @endif

        <div class="ship-comment-list" aria-label="Shipment comments">
            @forelse ($shipment->comments as $comment)
                <article class="ship-comment" data-shipment-comment="{{ $comment->id }}">
                    <header>
                        <div class="ship-comment-meta">
                            <strong>{{ $comment->creator?->name ?: 'Former team member' }}</strong>
                            <span><time datetime="{{ $comment->created_at->toIso8601String() }}">{{ $comment->created_at->format('d M Y, h:i A') }}</time> · Internal only</span>
                        </div>
                        @if (auth()->user()?->hasPermission('operations.manage'))
                            <form method="POST" action="{{ route('shipments.comments.destroy', [$shipment, $comment]) }}" data-confirm="Delete this comment and its attachments? This cannot be undone." data-confirm-text="Delete comment">
                                @csrf @method('DELETE')
                                <button class="master-btn master-btn-danger-ghost master-btn-sm" type="submit" aria-label="Delete comment by {{ $comment->creator?->name ?: 'former team member' }}">
                                    <i class="fa-regular fa-trash-can" aria-hidden="true"></i> Delete
                                </button>
                            </form>
                        @endif
                    </header>
                    <p class="ship-comment-body">{{ $comment->body }}</p>
                    @if ($comment->attachments->isNotEmpty())
                        <ul class="ship-comment-files" aria-label="Comment attachments">
                            @foreach ($comment->attachments as $file)
                                <li><a href="{{ route('shipments.comments.attachments.file', [$shipment, $comment, $file]) }}"><i class="fa-solid fa-paperclip" aria-hidden="true"></i><span>{{ $file->original_name }}</span></a><small>{{ number_format($file->file_size / 1024, 1) }} KB</small></li>
                            @endforeach
                        </ul>
                    @endif
                </article>
            @empty
                <div class="master-empty-state"><i class="fa-regular fa-comment" aria-hidden="true"></i><p>No comments yet. Internal updates can be kept here with their supporting files.</p></div>
            @endforelse
        </div>
    @endif
</section>
