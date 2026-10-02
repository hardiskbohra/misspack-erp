{{--
    The bills behind one entry.

    The upload sits inside the card rather than behind a button: a bill is
    filed while it is in hand, and month-end should have nothing left to
    chase. An entry with no document is what the ledger's "Missing documents"
    chip counts, so this card is where that number goes down.
--}}
<section class="master-card master-section cf-docs" id="documents">
    <div class="master-section-head">
        <div>
            <h3 class="master-section-title">Documents</h3>
            <p class="master-sub">
                @if ($entry->attachments->isEmpty())
                    Nothing on file yet — attach the bill, the bank slip or the receipt here.
                @else
                    {{ $entry->attachments->count() }} {{ \Illuminate\Support\Str::plural('file', $entry->attachments->count()) }} on file
                    @if ($entry->attachments->max('created_at'))
                        · last filed {{ $entry->attachments->max('created_at')->format('d M Y') }}
                    @endif
                @endif
            </p>
        </div>
        <span class="cf-doc-state {{ $entry->attachments->isEmpty() ? 'is-missing' : 'is-filed' }}">
            {{ $entry->attachments->isEmpty() ? 'Missing' : 'Filed' }}
        </span>
    </div>

    @if ($entry->attachments->isNotEmpty())
        <ul class="cf-doc-list">
            @foreach ($entry->attachments as $document)
                <li class="cf-doc">
                    <span class="cf-doc-icon" aria-hidden="true"><i class="fa-solid {{ $document->icon() }}"></i></span>
                    <div class="cf-doc-body">
                        <a class="cf-doc-name" href="{{ $document->url() }}" target="_blank"
                            rel="noopener">{{ $document->name() }}</a>
                        <span class="master-sub">
                            {{ $document->documentTypeLabel() }}
                            · {{ $document->sizeLabel() }}
                            @if ($document->created_at)
                                · {{ $document->created_at->format('d M Y') }}
                            @endif
                            @if ($document->uploader?->name)
                                · {{ $document->uploader->name }}
                            @endif
                        </span>
                    </div>
                    <div class="cf-doc-actions">
                        <a class="master-btn master-btn-soft master-btn-sm" href="{{ $document->url() }}"
                            target="_blank" rel="noopener">Open</a>
                        <form method="POST" action="{{ route('cashflows.attachments.destroy', $document) }}"
                            data-confirm="Remove this document?">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="master-btn master-btn-light master-btn-sm"
                                aria-label="Remove {{ $document->name() }}">Remove</button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    <form class="cf-doc-upload" method="POST" action="{{ route('cashflows.attachments.store', $entry) }}"
        enctype="multipart/form-data">
        @csrf
        <div class="cf-doc-upload-fields">
            <div class="master-field">
                <label class="master-label" for="documentType">Document type</label>
                <select class="master-select" id="documentType" name="document_type" required>
                    @foreach ($documentTypeOptions as $key => $label)
                        <option value="{{ $key }}" @selected($key === 'bill')>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="master-field">
                <label class="master-label" for="documentTitle">Title <span class="master-sub">(optional)</span></label>
                <input class="master-input" id="documentTitle" name="title" maxlength="255"
                    placeholder="e.g. Bill 2418 — Shree Traders">
            </div>
            <div class="master-field full">
                <label class="master-label" for="documentFiles">Files</label>
                <input class="master-input" id="documentFiles" type="file" name="attachments[]" multiple required
                    accept=".jpg,.jpeg,.png,.webp,.gif,.heic,.heif,.pdf,.doc,.docx,.xls,.xlsx,.csv,.ppt,.pptx,.txt,.zip">
                <p class="master-sub">PDF, image, Word, Excel, CSV or ZIP · up to 20 MB each.</p>
            </div>
        </div>
        <div class="cf-doc-upload-actions">
            <button class="master-btn master-btn-primary" type="submit">Attach documents</button>
        </div>
    </form>

    {{-- The other direction: a bill that was filed before its entry existed.
         Matching keeps the file where it is and points it at this entry, which
         is also how the month-end chase gets closed. --}}
    @if (($unlinkedDocuments ?? collect())->isNotEmpty())
        <form class="cf-doc-match" method="POST" action="{{ route('cashflows.attachments.link', $entry) }}">
            @csrf
            <div class="master-field full">
                <label class="master-label" for="matchDocument">Match a document that is filed without an entry</label>
                <select class="master-select" id="matchDocument" name="attachment_id" required>
                    <option value="">Choose a document…</option>
                    @foreach ($unlinkedDocuments as $candidate)
                        <option value="{{ $candidate->id }}">{{ $candidate->optionLabel() }}</option>
                    @endforeach
                </select>
                <p class="master-sub">
                    Bills filed on the <a href="{{ route('cashflows.documents', ['state' => 'unlinked']) }}">document
                    archive</a> until an entry claims them — matching one links it to this entry.</p>
            </div>
            <div class="cf-doc-upload-actions">
                <button type="submit" class="master-btn master-btn-soft">Match to this entry</button>
            </div>
        </form>
    @endif
</section>
