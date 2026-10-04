<div class="master-modal" id="addAttachmentModal" aria-hidden="true"
    @if ($errors->any() && old('_vendor_attachment_form') === 'add') data-auto-open="true" @endif>
    <div class="master-modal-card vendor-attachment-modal-card" role="dialog" aria-modal="true"
        aria-labelledby="addAttachmentTitle" aria-describedby="addAttachmentDescription" tabindex="-1">
        <form method="POST" enctype="multipart/form-data" action="{{ route('vendors.attachments.store', $vendor) }}" class="vendor-attachment-form">
            @csrf
            <input type="hidden" name="_vendor_attachment_form" value="add">
            <div class="master-modal-header">
                <div class="master-modal-heading">
                    <span class="master-modal-icon"><i class="fa-solid fa-paperclip" aria-hidden="true"></i></span>
                    <div>
                        <h2 class="master-modal-title" id="addAttachmentTitle">Add vendor documents</h2>
                        <p class="master-modal-subtitle" id="addAttachmentDescription">Store supplier agreements, compliance records, catalogues and proof of payment.</p>
                    </div>
                </div>
                <button type="button" class="master-modal-close" id="closeAddAttachmentModal" data-close-modal aria-label="Close add documents dialog">&times;</button>
            </div>
            <div class="master-modal-body">
                @if ($errors->any() && old('_vendor_attachment_form') === 'add')
                    <div class="vendor-form-errors" role="alert"><strong>Review the highlighted document details.</strong></div>
                @endif
                <div class="master-modal-grid">
                    <div class="master-field">
                        <label class="master-label" for="vendorAttachmentCategory">Category <span class="master-required" aria-hidden="true">*</span></label>
                        <select class="master-select" id="vendorAttachmentCategory" name="category" required>
                            @foreach ($attachmentOptions as $key => $label)
                                <option value="{{ $key }}" @selected(old('_vendor_attachment_form') === 'add' ? old('category', 'other') === $key : $key === 'other')>{{ $label }}</option>
                            @endforeach
                        </select>
                        @if ($errors->has('category'))<span class="master-error">{{ $errors->first('category') }}</span>@endif
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="vendorAttachmentTitle">Title</label>
                        <input class="master-input" id="vendorAttachmentTitle" type="text" name="title" maxlength="255"
                            value="{{ old('_vendor_attachment_form') === 'add' ? old('title') : '' }}" placeholder="Optional title for these files">
                        @if ($errors->has('title'))<span class="master-error">{{ $errors->first('title') }}</span>@endif
                    </div>
                    <div class="master-field full">
                        <label class="master-label" for="vendorAttachmentFiles">Files <span class="master-required" aria-hidden="true">*</span></label>
                        <input class="master-input" id="vendorAttachmentFiles" type="file" name="attachments[]" multiple required
                            accept=".jpg,.jpeg,.png,.webp,.gif,.heic,.heif,.pdf,.doc,.docx,.xls,.xlsx,.csv,.ppt,.pptx,.txt,.zip">
                        <small class="master-help">Select one or more files. Maximum 20 MB per file.</small>
                        @if ($errors->has('attachments'))<span class="master-error">{{ $errors->first('attachments') }}</span>@endif
                        @if ($errors->has('attachments.*'))<span class="master-error">{{ $errors->first('attachments.*') }}</span>@endif
                    </div>
                    <div class="master-field full">
                        <label class="master-label" for="vendorAttachmentNotes">Notes</label>
                        <textarea class="master-textarea" id="vendorAttachmentNotes" name="notes" rows="3" placeholder="Optional context for the team">{{ old('_vendor_attachment_form') === 'add' ? old('notes') : '' }}</textarea>
                        @if ($errors->has('notes'))<span class="master-error">{{ $errors->first('notes') }}</span>@endif
                    </div>
                </div>
            </div>
            <div class="master-modal-footer">
                <button type="button" class="master-btn master-btn-light" id="cancelAddAttachmentModal" data-close-modal>Cancel</button>
                <button class="master-btn master-btn-primary" type="submit"><i class="fa-solid fa-upload" aria-hidden="true"></i> Upload documents</button>
            </div>
        </form>
    </div>
</div>
