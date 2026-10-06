<section class="master-tab-panel" id="project-panel-attachments" role="tabpanel" aria-labelledby="project-tab-attachments">
    <div class="project-blocks">
        <section class="master-card master-card--flat project-detail-card">
            <div class="master-section-head">
                <div>
                    <h2 class="master-section-title">Documents</h2>
                    <p class="master-sub">{{ $project->attachments->count() }}
                        {{ \Illuminate\Support\Str::plural('file', $project->attachments->count()) }} — drawings, invoices,
                    packing lists and photographs</p>
                </div>
                <div class="master-section-meta">
                    <button type="button" class="master-btn master-btn-primary" id="openAddAttachmentModal">
                        <i class="fas fa-plus" aria-hidden="true"></i> Add documents</button>
                </div>
            </div>
            @if ($project->attachments->isEmpty())
                <div class="master-empty-state">
                    <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
                    <p>No document on this project yet. Upload the vendor invoice, the packing list or the artwork and the
                    portal can show the client what it is allowed to see.</p>
                    <button type="button" class="master-btn master-btn-soft master-btn-sm" data-modal-open="addAttachmentModal">
                        <i class="fas fa-plus" aria-hidden="true"></i> Add documents</button>
                </div>
            @else
                <div class="project-doc-grid">
                    @foreach ($project->attachments as $attachment)
                        <article class="project-doc">
                            @if ($attachment->isImage())
                                <img class="project-doc-thumb" src="{{ $attachment->fileUrl() }}"
                                    alt="{{ $attachment->title ?: $attachment->original_name }}">
                            @else
                                <span class="project-doc-icon" aria-hidden="true"><i class="fa-solid fa-file-lines"></i></span>
                            @endif
                            <div class="project-doc-body">
                                <strong>{{ $attachment->title ?: $attachment->original_name }}</strong>
                                <span class="project-fact-note">
                                    {{ $attachment->categoryLabel() }} · {{ strtoupper($attachment->extension) }} ·
                                    {{ $attachment->is_public ? 'Public' : 'Internal' }}
                                </span>
                                @if ($attachment->product)
                                    <span class="project-fact-note">Product: {{ $attachment->product->product_name }}</span>
                                @endif
                                <div class="project-doc-actions">
                                    <a class="master-btn master-btn-light master-btn-sm" href="{{ $attachment->fileUrl() }}"
                                        target="_blank" rel="noopener"><i class="fa-regular fa-eye" aria-hidden="true"></i> Open</a>
                                    <form method="POST" action="{{ route('projects.attachments.destroy', $attachment) }}"
                                        data-confirm="Delete this document?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="master-btn master-btn-danger-ghost master-btn-sm">
                                            <i class="fas fa-trash" aria-hidden="true"></i> Delete</button>
                                    </form>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</section>


<!--Add Attachment-->
<div class="master-modal" id="addAttachmentModal" aria-hidden="true">
    <div class="master-modal-card">
        <form method="POST" enctype="multipart/form-data" action="{{ route('projects.attachments.store', $project) }}">
            @csrf
            <div class="master-modal-header">
                <div class="master-modal-heading">
                    <div>
                        <h3 class="master-modal-title">Add Attachment</h3>
                    </div>
                </div>
                <button type="button" class="master-modal-close" id="closeAddAttachmentModal" data-close-modal>×</button>
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
                    <div class="master-field">
                        <label class="master-label">Category</label>
                        <select class="master-select" name="category">
                            @foreach ($attachmentCategoryOptions as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field">
                        <label class="master-label">Title</label>
                        <input class="master-input" type="text" name="title"
                            placeholder="Vendor invoice / packing list / etc."></div>
                    <div class="master-field">
                        <label class="master-label">Files</label>
                        <input class="master-input" type="file" name="attachments[]" multiple
                            required
                            accept=".jpg,.jpeg,.png,.webp,.gif,.heic,.heif,.pdf,.doc,.docx,.xls,.xlsx,.csv,.ppt,.pptx,.txt,.zip">
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
                    <div class="master-field full">
                        <label class="master-label">Notes</label>
                        <textarea class="master-textarea" name="notes" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="master-modal-footer">
                <button type="button" class="master-btn master-btn-light" id="cancelAddAttachmentModal" data-close-modal>Cancel</button>
                <button class="master-btn master-btn-primary" type="submit"> Upload</button>
            </div>
        </form>
    </div>
</div>
