<section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-documents-heading">
    <div class="master-section-head">
        <div>
            <h2 class="master-section-title" id="vendor-documents-heading">Vendor documents</h2>
            <p class="master-sub">Agreements, compliance files, catalogues and supporting documents for this supplier.</p>
        </div>
        <div class="vendor-document-toolbar">
            <span class="master-chip">{{ number_format($summary['attachments_count']) }} {{ \Illuminate\Support\Str::plural('file', $summary['attachments_count']) }}</span>
            @if ($attachmentsAvailable && \Illuminate\Support\Facades\Route::has('vendors.attachments.store'))
                <button type="button" class="master-btn master-btn-primary" id="openAddAttachmentModal">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Add documents
                </button>
            @endif
        </div>
    </div>

    @if ($attachmentsAvailable)
        @if ($vendor->attachments->isNotEmpty())
            <div class="master-table-wrap ui-mobile-cards">
                <table class="master-table vendor-detail-table vendor-attachment-table">
                    <thead>
                        <tr>
                            <th scope="col">Document</th>
                            <th scope="col">Category</th>
                            <th scope="col">Notes</th>
                            <th scope="col">Uploaded by</th>
                            <th scope="col">Uploaded</th>
                            <th scope="col">File size</th>
                            <th scope="col"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($vendor->attachments as $attachment)
                            @php
                                $fileBytes = (int) ($attachment->file_size ?? 0);
                                $fileSize = $fileBytes >= 1048576
                                    ? number_format($fileBytes / 1048576, 1).' MB'
                                    : ($fileBytes > 0 ? number_format(max(1, $fileBytes / 1024), 0).' KB' : '—');
                            @endphp
                            <tr>
                                <td data-label="Document">
                                    <a class="vendor-document-link" href="{{ $attachment->fileUrl() }}" target="_blank" rel="noopener">
                                        <span class="vendor-document-icon" aria-hidden="true"><i class="{{ $attachment->isImage() ? 'fa-regular fa-image' : 'fa-regular fa-file-lines' }}"></i></span>
                                        <span><strong>{{ $attachment->title ?: $attachment->original_name }}</strong><span class="vendor-table-meta">{{ strtoupper($attachment->extension ?: 'FILE') }} · {{ $attachment->original_name }}</span></span>
                                        <span class="visually-hidden">(opens in a new tab)</span>
                                    </a>
                                </td>
                                <td data-label="Category"><span class="master-chip">{{ $attachment->categoryLabel() }}</span></td>
                                <td data-label="Notes">{{ $attachment->notes ?: '—' }}</td>
                                <td data-label="Uploaded by">{{ $attachment->uploader?->name ?: 'Team member' }}</td>
                                <td data-label="Uploaded">{{ $attachment->created_at?->format('d M Y') ?: '—' }}</td>
                                <td data-label="File size">{{ $fileSize }}</td>
                                <td data-label="Actions">
                                    @if (\Illuminate\Support\Facades\Route::has('vendors.attachments.destroy'))
                                        <form method="POST" action="{{ route('vendors.attachments.destroy', $attachment) }}"
                                            data-confirm="Delete {{ $attachment->title ?: $attachment->original_name }} from this vendor record?">
                                            @csrf
                                            @method('DELETE')
                                            <button class="master-icon-btn danger" type="submit" aria-label="Delete document: {{ $attachment->title ?: $attachment->original_name }}" title="Delete document">
                                                <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="master-empty-state"><i class="fa-regular fa-folder-open" aria-hidden="true"></i><p>No documents have been added to this vendor record yet.</p></div>
        @endif
        @if (\Illuminate\Support\Facades\Route::has('vendors.attachments.store'))
            @include('vendors.partials.attachment-modal')
        @endif
    @else
        <div class="master-empty-state"><i class="fa-solid fa-database" aria-hidden="true"></i><p>Vendor document storage is not installed for this workspace.</p></div>
    @endif
</section>
