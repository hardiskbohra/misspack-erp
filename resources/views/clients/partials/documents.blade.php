@if($documentsAvailable)
    <div class="client-detail-tools">
        <div class="cpa-grid">
            <section class="cpa-card" aria-labelledby="client-attach-document-heading">
                <div class="cpa-section-head"><div><p class="cpa-eyebrow">Secure file room</p><h2 id="client-attach-document-heading">Attach a document</h2></div></div>
                <p class="client-detail-help">Files are stored privately and served through an authorization-checked download. Choose whether the client can see the attachment.</p>
                <form method="POST" action="{{ route('clients.portal.documents.store', $client) }}" enctype="multipart/form-data" class="cpa-form-grid">
                    @csrf
                    <div class="master-field">
                        <label class="master-label" for="clientDocumentTitle">Title</label>
                        <input class="master-input" id="clientDocumentTitle" name="title" maxlength="255" value="{{ old('title') }}" placeholder="Uses the file name if blank">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="clientDocumentCategory">Category *</label>
                        <select class="master-select" id="clientDocumentCategory" name="category" required>
                            @foreach(\App\Models\ClientPortalDocument::categoryOptions() as $key => $label)
                                <option value="{{ $key }}" @selected(old('category', 'general') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field cpa-field-full">
                        <label class="master-label" for="clientDocumentFile">File * <span class="cpa-muted">(max 20 MB)</span></label>
                        <input class="master-input" id="clientDocumentFile" type="file" name="file" required accept=".jpg,.jpeg,.png,.webp,.gif,.heic,.heif,.pdf,.doc,.docx,.xls,.xlsx,.csv,.ppt,.pptx,.txt,.zip">
                    </div>
                    <div class="master-field cpa-field-full">
                        <label class="master-label" for="clientDocumentNotes">Internal note</label>
                        <textarea class="master-textarea" id="clientDocumentNotes" name="notes" maxlength="4000" rows="3" placeholder="Optional staff note; not shown to the client.">{{ old('notes') }}</textarea>
                    </div>
                    <div class="cpa-checks">
                        <input type="hidden" name="is_public_to_client" value="0">
                        <label class="master-check"><input type="checkbox" name="is_public_to_client" value="1" @checked(old('is_public_to_client', true))> Share in the client portal</label>
                    </div>
                    <div class="cpa-submit"><button class="master-btn master-btn-primary" type="submit"><i class="fa-solid fa-paperclip" aria-hidden="true"></i> Attach document</button></div>
                </form>
            </section>

            <section class="cpa-card" aria-labelledby="client-documents-list-heading">
                <div class="cpa-section-head">
                    <div><p class="cpa-eyebrow">Attachments</p><h2 id="client-documents-list-heading">Client documents</h2></div>
                    <span class="cpa-badge">{{ $documents->count() }}</span>
                </div>
                <div class="cpa-table-wrap">
                    <table class="cpa-table client-document-table">
                        <thead><tr><th>File</th><th>Category</th><th>Added by</th><th>Portal</th><th>Uploaded</th><th></th></tr></thead>
                        <tbody>
                            @forelse($documents as $document)
                                <tr>
                                    <td><strong>{{ $document->title ?: $document->original_name ?: basename($document->file_path) }}</strong><span>{{ strtoupper($document->extension ?: 'FILE') }}@if($document->file_size) · {{ number_format($document->file_size / 1024, 1) }} KB @endif</span></td>
                                    <td>{{ $document->categoryLabel() }}</td>
                                    <td>{{ $document->portalUser?->displayName() ?: 'MissPack team' }}</td>
                                    <td><span class="client-document-visibility {{ $document->is_public_to_client ? 'is-public' : 'is-private' }}">{{ $document->is_public_to_client ? 'Shared' : 'Private' }}</span></td>
                                    <td>{{ $document->created_at?->format('d M Y') ?: '—' }}</td>
                                    <td><a class="master-btn master-btn-soft master-btn-sm" href="{{ route('clients.portal.documents.file', [$client, $document]) }}"><i class="fa-solid fa-download" aria-hidden="true"></i> Download</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><div class="cpa-empty">No client portal documents are attached to this record yet.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($documents->count() === 100)
                    <p class="client-document-limit-note">Showing the 100 most recent attachments.</p>
                @endif
            </section>
        </div>
    </div>
@else
    <div class="master-card master-card--flat client-detail-card client-detail-card--wide">
        <div class="master-empty-state"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><p>Client portal documents are not available in this installation.</p></div>
    </div>
@endif
