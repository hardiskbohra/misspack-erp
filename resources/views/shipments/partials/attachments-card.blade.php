<section class="master-card master-card--flat master-section ship-attachments" id="shipmentAttachments">
    <div class="master-section-head">
        <h3 class="master-section-title">Shipment Attachments</h3>
        @if ($shipment->attachments->isNotEmpty())
            <span class="master-chip">{{ $shipment->attachments->count() }}</span>
        @endif
    </div>

    @if (auth()->user()?->hasPermission('operations.manage'))
        <form method="POST" action="{{ route('shipments.attachments.store', $shipment) }}"
            enctype="multipart/form-data" class="ship-doc-upload">
            @csrf
            <div class="ship-form-field">
                <label class="ship-form-label" for="shipmentAttachmentFiles">Upload attachments</label>
                <input class="master-input" id="shipmentAttachmentFiles" type="file"
                    name="attachment_photos[]" multiple required
                    accept=".jpg,.jpeg,.png,.webp,.gif,.heic,.heif,.xls,.xlsx,.pdf,.doc"
                    aria-describedby="shipmentAttachmentHelp">
                <p class="master-help" id="shipmentAttachmentHelp">Select photos or supporting files, up to 8 MB each. JPG, JPEG, PNG, WEBP, GIF, HEIC, HEIF, XLS, XLSX, PDF and DOC are supported. Keep operational dispatch documents in the project's Workflow Control Centre.</p>
                @error('attachment_photos')<p class="master-error" role="alert">{{ $message }}</p>@enderror
                @error('attachment_photos.*')<p class="master-error" role="alert">{{ $message }}</p>@enderror
            </div>
            <div class="ship-doc-upload-actions">
                <label class="master-check"><input type="checkbox" name="is_public" value="1" @checked(old('is_public'))> Visible to client / public tracking</label>
                <button class="master-btn master-btn-primary master-btn-sm" type="submit"><i class="fa-solid fa-upload" aria-hidden="true"></i> Upload attachments</button>
            </div>
        </form>
    @endif

    @if ($shipment->attachments->isEmpty())
        <p class="master-sub">No shipment attachments yet.</p>
    @else
        <div class="photo-grid">
            @foreach ($shipment->attachments as $attachment)
                <a class="photo-card" href="{{ asset('storage/'.$attachment->file_path) }}" target="_blank" rel="noopener">
                    @if (str_starts_with((string) $attachment->mime_type, 'image/'))
                        <img src="{{ asset('storage/'.$attachment->file_path) }}" alt="{{ $attachment->title ?: $attachment->original_name }}">
                    @else
                        <div class="file-card"><i class="file-icon fa-solid fa-file"></i></div>
                    @endif

                    <div class="photo-card-body">
                        <div class="photo-name">{{ $attachment->original_name }}</div>
                        <div class="photo-meta">
                            <span class="master-chip">{{ $attachment->is_public ? 'Public' : 'Internal' }}</span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</section>
