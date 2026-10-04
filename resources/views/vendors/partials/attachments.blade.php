<section class="master-card master-card--flat vendor-detail-card vendor-block-card" id="vendor-block-attachments" aria-labelledby="vendor-block-attachments-title">
    <div class="vendor-panel-head">
        <div>
            <h2 class="vendor-detail-title" id="vendor-block-attachments-title">Documents</h2>
            <p class="vendor-detail-help">Certificates, bank proofs, price lists and signed paperwork for this supplier.</p>
        </div>
        <div class="vendor-panel-meta">
            <span class="vendor-pill">{{ $attachmentsAvailable ? $vendor->attachments->count() : 0 }} {{ \Illuminate\Support\Str::plural('file', $attachmentsAvailable ? $vendor->attachments->count() : 0) }}</span>
            @if ($attachmentsAvailable)
                <button type="button" class="master-btn master-btn-primary master-btn-sm" id="openAddAttachmentModal">
                    <i class="fas fa-plus" aria-hidden="true"></i> Add documents
                </button>
            @endif
        </div>
    </div>

    @if ($attachmentsAvailable)
        <div class="vendor-attachment-grid">
            @forelse($vendor->attachments as $attachment)
                <article class="master-card master-card--flat vendor-attachment">
                    @if ($attachment->isImage())
                        <a class="vendor-attachment-media" href="{{ $attachment->fileUrl() }}" target="_blank" rel="noopener">
                            <img src="{{ $attachment->fileUrl() }}" alt="{{ $attachment->title ?: $attachment->original_name }}" loading="lazy">
                        </a>
                    @else
                        <span class="vendor-attachment-media is-file" aria-hidden="true">
                            <i class="fa-solid fa-file-lines"></i>
                            <span>{{ strtoupper($attachment->extension ?: 'file') }}</span>
                        </span>
                    @endif
                    <div class="vendor-attachment-copy">
                        <strong>{{ $attachment->title ?: $attachment->original_name }}</strong>
                        <span class="master-sub">{{ $attachment->categoryLabel() }} · {{ strtoupper($attachment->extension ?: 'file') }}</span>
                        <span class="master-sub">{{ $attachment->created_at?->format('d M Y') ?: '' }}@if ($attachment->uploader) · {{ $attachment->uploader->name }}@endif</span>
                        @if ($attachment->notes)
                            <span class="vendor-attachment-note">{{ $attachment->notes }}</span>
                        @endif
                        <div class="vendor-attachment-actions">
                            <a class="master-btn master-btn-soft master-btn-sm" href="{{ $attachment->fileUrl() }}" target="_blank" rel="noopener">
                                <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> Open
                            </a>
                            @if(\Illuminate\Support\Facades\Route::has('vendors.attachments.destroy'))
                                <form method="POST" action="{{ route('vendors.attachments.destroy', $attachment) }}"
                                    data-confirm="Delete this vendor document?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="master-btn master-btn-light master-btn-sm vendor-row-delete" type="submit">
                                        <i class="far fa-trash-alt" aria-hidden="true"></i> Delete
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="master-card master-card--flat vendor-attachment-empty">
                    <div class="master-list-empty">
                        <span class="master-list-empty-icon" aria-hidden="true"><i class="fa-regular fa-folder-open"></i></span>
                        <h3 class="master-list-empty-title">No documents filed yet</h3>
                        <p class="master-list-empty-text">Upload the vendor's certificates, bank proof or signed price list so the next person does not have to ask.</p>
                        <div class="master-list-empty-actions">
                            <button type="button" class="master-btn master-btn-primary" id="openAddAttachmentModalEmpty">
                                <i class="fas fa-plus" aria-hidden="true"></i> Add first document
                            </button>
                        </div>
                    </div>
                </div>
            @endforelse
        </div>
    @else
        <div class="master-card master-card--flat">
            <div class="master-list-empty">
                <span class="master-list-empty-icon" aria-hidden="true"><i class="fa-regular fa-folder-open"></i></span>
                <h3 class="master-list-empty-title">Document storage is not available</h3>
                <p class="master-list-empty-text">The vendor documents table is missing on this database, so uploads are disabled.</p>
            </div>
        </div>
    @endif
</section>
