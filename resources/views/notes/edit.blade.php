@extends('layouts.app')

@section('title', 'Note')
@section('page-title', 'Note')

@section('page-actions')
    <a class="master-btn master-btn-ghost" href="{{ route('notes.index') }}">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to notes
    </a>
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/notes.css') }}">
@endpush

<div class="nt nt-edit master">
    <div class="master-grid nt-edit-grid">

        {{-- The words. One form, the same fields the composer takes, plus the
             colour — so a note is written quickly and tidied properly. --}}
        <form method="POST" action="{{ route('notes.update', $note) }}"
            class="master-card master-form-card nt-card" aria-labelledby="ntEditTitle">
            @csrf
            @method('PUT')

            <div class="nt-card-head">
                <div>
                    <p class="master-eyebrow">{{ $note->colourLabel() }} note</p>
                    <h2 class="master-section-title" id="ntEditTitle">The note</h2>
                </div>
                <p class="master-help">Private to this login — nobody else reads it, not even the office.</p>
            </div>

            @if ($errors->any())
                <div class="master-error-summary" role="alert">
                    <strong>Please review {{ $errors->count() }} {{ \Illuminate\Support\Str::plural('field', $errors->count()) }}:</strong>
                    <ul>
                        @foreach ($errors->messages() as $field => $messages)
                            <li>{{ $messages[0] }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="master-form-grid">
                <label class="master-field full">
                    <span class="master-label">Title</span>
                    <input class="master-input @error('title') is-invalid @enderror" type="text" name="title"
                        value="{{ old('title', $note->title) }}" maxlength="{{ \App\Services\NoteVocabulary::TITLE_LIMIT }}"
                        required autocomplete="off">
                    @error('title')
                        <span class="master-field-error">{{ $message }}</span>
                    @enderror
                </label>

                <label class="master-field">
                    <span class="master-label">Colour</span>
                    <select class="master-select" name="colour">
                        @foreach ($colourOptions as $key => $label)
                            <option value="{{ $key }}" @selected(old('colour', $note->colourKey()) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="master-check nt-edit-pin">
                    <input type="checkbox" name="is_pinned" value="1"
                        @checked(old('is_pinned', $note->is_pinned))>
                    <span>Pin it to the front of the board</span>
                </label>

                <label class="master-field full">
                    <span class="master-label">The note</span>
                    <textarea class="master-textarea" name="body" rows="12"
                        placeholder="Write it as it comes — a list keeps its line breaks.">{{ old('body', $note->body) }}</textarea>
                    <span class="master-help">Line breaks are kept: what you type is what the board shows.</span>
                </label>
            </div>

            <div class="master-actions">
                <a class="master-btn master-btn-light" href="{{ route('notes.index') }}">Cancel</a>
                <button class="master-btn master-btn-primary" type="submit">
                    <i class="fa-regular fa-floppy-disk" aria-hidden="true"></i> Save the note
                </button>
            </div>
        </form>

        {{-- What the note is, and the three things that can happen to it. The
             destructive one is here and not on the board on purpose: a delete
             button beside every sticky is a delete button one mis-click away
             from a note you were only reading. --}}
        <aside class="master-card master-card--flat nt-card nt-edit-side" aria-labelledby="ntNoteFactsTitle">
            <p class="master-eyebrow">This note</p>
            <h2 class="master-section-title" id="ntNoteFactsTitle">{{ $note->title }}</h2>

            <div class="master-facts">
                <x-fact label="Colour">
                    <strong>
                        <span class="nt-dot nt-dot--{{ $note->colourKey() }}" aria-hidden="true"></span>
                        {{ $note->colourLabel() }}
                    </strong>
                </x-fact>

                <x-fact label="State">
                    @if ($note->isArchived())
                        <strong class="nt-badge">Filed away</strong>
                    @elseif ($note->is_pinned)
                        <strong class="nt-badge nt-badge--pinned">Pinned on the desk</strong>
                    @else
                        <strong class="nt-badge nt-badge--desk">On the desk</strong>
                    @endif
                </x-fact>

                <x-fact label="Written" :value="$note->created_at?->format('d M Y, H:i')" />
                <x-fact label="Last edited" :value="$note->updated_at?->format('d M Y, H:i')" />
            </div>

            <div class="nt-edit-actions">
                <form method="POST" action="{{ route('notes.pin', $note) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="pinned" value="{{ $note->is_pinned ? 0 : 1 }}">
                    <button class="master-btn master-btn-soft" type="submit">
                        <i class="fa-solid fa-thumbtack" aria-hidden="true"></i>
                        {{ $note->is_pinned ? 'Let it fall back into the pile' : 'Pin it to the front' }}
                    </button>
                </form>

                <form method="POST" action="{{ route('notes.archive', $note) }}">
                    @csrf
                    @method('PATCH')
                    <button class="master-btn master-btn-soft" type="submit">
                        <i class="fa-regular fa-box-archive" aria-hidden="true"></i>
                        {{ $note->isArchived() ? 'Put it back on the desk' : 'File it away' }}
                    </button>
                </form>

                <form method="POST" action="{{ route('notes.destroy', $note) }}"
                    data-confirm="Delete this note? The words go with it, and there is nothing to restore.">
                    @csrf
                    @method('DELETE')
                    <button class="master-btn master-btn-danger" type="submit">
                        <i class="fa-regular fa-trash-can" aria-hidden="true"></i> Delete this note
                    </button>
                </form>
            </div>
        </aside>
    </div>
</div>
@endsection
