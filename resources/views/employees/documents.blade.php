@extends('layouts.app')

@section('title', 'My Documents')
@section('page-title', 'My Documents')

@section('page-actions')
    <a class="master-btn master-btn-ghost" href="{{ route('my.profile') }}">My profile</a>
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/employees.css') }}">
@endpush

<div class="emp employee-documents master-list">
    <div class="master-card master-card--flat">
        <div class="master-list-bar">
            <div>
                <p class="emp-checklist-title">Your file</p>
                <p class="master-sub" style="margin:2px 0 0;">
                    {{ $checklist['present'] }} of {{ $checklist['required'] }} required papers on file.
                    Upload what is missing; the office marks each one as checked once they have seen it.
                </p>
            </div>
            <span class="emp-pill {{ $checklist['complete'] ? 'is-ok' : 'is-warn' }}">
                {{ $checklist['complete'] ? 'Complete' : 'Incomplete' }}
            </span>
        </div>

        <div class="master-filter-row">
            <form method="POST" action="{{ route('my.documents.store') }}" enctype="multipart/form-data" class="emp-upload-form">
                @csrf
                <div class="emp-upload-fields">
                    <div class="master-field">
                        <label class="master-label" for="docType">What is it?</label>
                        <select class="master-select" id="docType" name="document_type" required>
                            @foreach ($uploadTypes as $key => $label)
                                <option value="{{ $key }}" @selected(old('document_type') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('document_type')<p class="master-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="docTitle">Title (optional)</label>
                        <input class="master-input" id="docTitle" name="title" value="{{ old('title') }}"
                            placeholder="e.g. Aadhaar front and back">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="docNumber">Number (optional)</label>
                        <input class="master-input" id="docNumber" name="document_number" value="{{ old('document_number') }}"
                            placeholder="Document number">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="docExpiry">Expires (optional)</label>
                        <input class="master-input" id="docExpiry" type="date" name="expires_on" value="{{ old('expires_on') }}">
                    </div>
                    <div class="master-field full">
                        <label class="master-label" for="docFiles">Files</label>
                        <input class="master-input emp-file-input" id="docFiles" type="file" name="documents[]" multiple required>
                        <p class="master-sub" style="margin:6px 0 0;">
                            Images, PDF, Word, Excel or ZIP — up to 20 MB each, 10 at a time.
                        </p>
                        @error('documents')<p class="master-error">{{ $message }}</p>@enderror
                        @error('documents.*')<p class="master-error">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="master-actions">
                    <button class="master-btn master-btn-primary" type="submit">Upload</button>
                </div>
            </form>
        </div>
    </div>

    <div class="master-card master-table-card master-card--flat">
        @include('employees.partials.document-checklist', ['checklist' => $checklist, 'mode' => 'self', 'user' => $me])
    </div>
</div>
@endsection
