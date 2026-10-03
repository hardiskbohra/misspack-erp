@extends('client_portal.layouts.app')

@section('title', 'Documents')
@section('page-title', 'Documents')

@section('content')
<div class="cp-page-head">
    <div><p class="cp-eyebrow">Secure document room</p><h1>Documents</h1><p>Upload files for your MissPack team. Downloads are private and checked against your client account.</p></div>
    <span class="cp-status-pill cp-status-resolved"><i class="fa-solid fa-lock"></i>&nbsp; Private storage</span>
</div>

<div class="cp-support-grid cp-document-upload-grid">
    <section class="cp-card cp-support-compose">
        <div class="cp-section-heading"><div><p class="cp-eyebrow">Share a file</p><h2>Upload documents</h2></div><span class="cp-support-icon"><i class="fa-solid fa-cloud-arrow-up"></i></span></div>
        <form method="POST" action="{{ route('client-portal.attachments.store') }}" enctype="multipart/form-data" class="cp-form-grid">
            @csrf
            <div class="master-field"><label class="master-label" for="document-category">Category</label><select class="master-select" id="document-category" name="category" required>@foreach($categories as $key => $label)<option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>@endforeach</select></div>
            <div class="master-field"><label class="master-label" for="document-title">Title <span class="cp-muted">(optional)</span></label><input class="master-input" id="document-title" name="title" value="{{ old('title') }}" maxlength="255" placeholder="Name these files"></div>
            <div class="master-field"><label class="master-label" for="document-files">Files</label><input class="master-input" id="document-files" type="file" name="attachments[]" multiple required accept=".jpg,.jpeg,.png,.webp,.gif,.heic,.heif,.pdf,.doc,.docx,.xls,.xlsx,.csv,.ppt,.pptx,.txt,.zip"><small class="cp-field-help">Up to 10 files, 20 MB each. Images, PDF, Office, CSV, TXT and ZIP.</small></div>
            <div class="master-field"><label class="master-label" for="document-notes">Note for MissPack <span class="cp-muted">(optional)</span></label><textarea class="master-textarea" id="document-notes" name="notes" rows="3" maxlength="4000" placeholder="Add context about these files.">{{ old('notes') }}</textarea></div>
            <div class="cp-support-form-foot"><span class="cp-muted"><i class="fa-solid fa-shield-halved"></i> Files are not exposed as public links.</span><button class="master-btn master-btn-primary" type="submit">Upload securely</button></div>
        </form>
    </section>

    <section class="cp-card cp-support-list">
        <div class="cp-section-heading"><div><p class="cp-eyebrow">Your shared files</p><h2>Document library</h2></div><span class="cp-support-count">{{ $documents->total() }}</span></div>
        <form method="GET" class="cp-support-filters cp-document-filter">
            <select class="master-select" name="category" aria-label="Filter documents by category"><option value="all">All categories</option>@foreach($categories as $key => $label)<option value="{{ $key }}" @selected($category === $key)>{{ $label }}</option>@endforeach</select>
            <button class="master-btn master-btn-soft master-btn-sm" type="submit">Filter</button>
            <a class="master-btn master-btn-light master-btn-sm" href="{{ route('client-portal.attachments.index') }}">Reset</a>
        </form>
        <div class="cp-document-grid">
            @forelse($documents as $document)
                <article class="cp-document-card">
                    @if($document->isImage())
                        <a class="cp-document-preview" href="{{ route('client-portal.attachments.file', $document) }}" target="_blank" rel="noopener"><img src="{{ route('client-portal.attachments.file', $document) }}" alt="{{ $document->title ?: $document->original_name }}"></a>
                    @else
                        <span class="cp-file-icon"><i class="fa-solid fa-file-lines"></i></span>
                    @endif
                    <div class="cp-document-copy">
                        <strong>{{ $document->title ?: $document->original_name }}</strong>
                        <small>{{ $document->categoryLabel() }} · {{ strtoupper($document->extension) }} · {{ $document->created_at->format('d M Y') }}</small>
                    </div>
                    <div class="cp-document-actions">
                        <a class="master-btn master-btn-soft master-btn-sm" href="{{ route('client-portal.attachments.file', $document) }}?download=1"><i class="fa-solid fa-download"></i> Download</a>
                        @if((int) $document->client_portal_user_id === (int) $clientPortalUser->id)
                            <form method="POST" action="{{ route('client-portal.attachments.destroy', $document) }}" data-confirm="Delete this document from the portal?">@csrf @method('DELETE')<button class="master-btn master-btn-light master-btn-sm" type="submit">Delete</button></form>
                        @endif
                    </div>
                </article>
            @empty
                <div class="cp-empty cp-empty-spacious"><i class="fa-regular fa-folder-open"></i><strong>No documents found</strong><span>Uploaded files and documents shared by MissPack will appear here.</span></div>
            @endforelse
        </div>
        <div class="cp-pagination">{{ $documents->links() }}</div>
    </section>
</div>
@endsection
