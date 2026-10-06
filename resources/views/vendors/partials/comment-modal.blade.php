<div class="master-modal" id="addCommentModal" aria-hidden="true"
    @if ($errors->any() && old('_vendor_comment_form') === 'add') data-auto-open="true" @endif>
    <div class="master-modal-card vendor-comment-modal-card" role="dialog" aria-modal="true"
        aria-labelledby="addCommentTitle" aria-describedby="addCommentDescription" tabindex="-1">
        <form method="POST" action="{{ route('vendors.comments.store', $vendor) }}" class="vendor-comment-form">
            @csrf
            <input type="hidden" name="_vendor_comment_form" value="add">
            <div class="master-modal-header">
                <div class="master-modal-heading">
                    <span class="master-modal-icon"><i class="fa-regular fa-message" aria-hidden="true"></i></span>
                    <div>
                        <h2 class="master-modal-title" id="addCommentTitle">Add a team note</h2>
                        <p class="master-modal-subtitle" id="addCommentDescription">Keep an internal follow-up or important vendor context with this record.</p>
                    </div>
                </div>
                <button type="button" class="master-modal-close" id="closeAddCommentModal" data-close-modal aria-label="Close add team note dialog">&times;</button>
            </div>
            <div class="master-modal-body">
                @if ($errors->any() && old('_vendor_comment_form') === 'add')
                    <div class="vendor-form-errors" role="alert"><strong>Review the note before saving.</strong></div>
                @endif
                <div class="master-modal-grid">
                    <div class="master-field full">
                        <label class="master-label" for="vendorCommentBody">Note <span class="master-required" aria-hidden="true">*</span></label>
                        <textarea class="master-textarea" id="vendorCommentBody" name="body" rows="5" required
                            placeholder="Add an internal follow-up, issue, reminder or payment note">{{ old('_vendor_comment_form') === 'add' ? old('body') : '' }}</textarea>
                        @if ($errors->has('body'))<span class="master-error">{{ $errors->first('body') }}</span>@endif
                    </div>
                    <div class="master-field full">
                        <label class="master-check">
                            <input type="checkbox" name="is_pinned" value="1" @checked(old('_vendor_comment_form') === 'add' && old('is_pinned'))>
                            Pin this note to the top of the vendor record
                        </label>
                    </div>
                </div>
            </div>
            <div class="master-modal-footer">
                <button type="button" class="master-btn master-btn-light" id="cancelAddCommentModal" data-close-modal>Cancel</button>
                <button class="master-btn master-btn-primary" type="submit"><i class="fa-solid fa-check" aria-hidden="true"></i> Save note</button>
            </div>
        </form>
    </div>
</div>
