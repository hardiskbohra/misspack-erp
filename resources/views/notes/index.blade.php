@extends('layouts.app')

@section('page-title', 'Notes')

@section('page-actions')
    {{-- The one action on this page opens the composer. It is a button and not a
         link because it opens a dialog — and it is here, in the topbar, so a note
         is two clicks from anywhere rather than a trip to the top of the page. --}}
    <button type="button" class="master-btn master-btn-primary" data-open-note-modal>
        <i class="fa-solid fa-plus" aria-hidden="true"></i> New note
    </button>
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/notes.css') }}">
@endpush
@php
    /* Every chip is one URL away from the others, so a chip keeps what it does
       not own: the search, the period and the order travel with it. */
    $chipUrl = function (array $overrides) {
        $keep = array_merge(request()->except(['page']), $overrides);

        return route('notes.index', array_filter(
            $keep,
            fn ($value) => $value !== null && $value !== '' && $value !== 'all',
            ARRAY_FILTER_USE_BOTH
        ));
    };

    $firstNote = $notes->firstItem() ?? 0;
    $lastNote = $notes->lastItem() ?? 0;
    $boardIsFull = ! $board->isEmpty() && $figures['total'] > $board->count();
@endphp

<div class="nt nt-index master-list">

    {{-- The figures. Four numbers cut from one query — what these filters leave,
         how many of them are pinned, how many are still on the desk, and how
         many moved this week. --}}
    <div class="master-stats desktop-only" aria-label="Your notes">
        <div class="master-stat master-stat--flat blue">
            <span class="icon" aria-hidden="true"><i class="fa-regular fa-note-sticky"></i></span>
            <div>
                <p class="master-stat-title">Matching</p>
                <p class="master-stat-value">{{ number_format($figures['total']) }}</p>
                <p class="master-sub">{{ $filtered ? 'left by these filters' : 'on the desk and filed away' }}</p>
            </div>
        </div>

        <div class="master-stat master-stat--flat orange">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-thumbtack"></i></span>
            <div>
                <p class="master-stat-title">Pinned</p>
                <p class="master-stat-value">{{ number_format($figures['pinned']) }}</p>
                <p class="master-sub">stuck to the front of the board</p>
            </div>
        </div>

        <div class="master-stat master-stat--flat teal">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-pen-to-square"></i></span>
            <div>
                <p class="master-stat-title">On the desk</p>
                <p class="master-stat-value">{{ number_format($figures['desk']) }}</p>
                <p class="master-sub">{{ number_format($figures['filed']) }} filed away</p>
            </div>
        </div>

        <div class="master-stat master-stat--flat purple">
            <span class="icon" aria-hidden="true"><i class="fa-regular fa-clock"></i></span>
            <div>
                <p class="master-stat-title">Edited this week</p>
                <p class="master-stat-value">{{ number_format($figures['week']) }}</p>
                <p class="master-sub">touched since Monday</p>
            </div>
        </div>
    </div>

    {{-- ─────────────────────────────────────────────── search and filter --}}
    <section class="master-card master-card--flat" aria-label="Search and filter notes">
        <div class="master-list-bar">
            <nav class="master-list-chips" aria-label="Filter notes by state">
                @foreach ($stateOptions as $key => $label)
                    <a class="master-list-chip {{ $state === $key ? 'is-active' : '' }}"
                        href="{{ $chipUrl(['state' => $key]) }}">
                        {{ $label }}
                        <span class="master-list-chip-count">{{ number_format($stateCounts[$key] ?? 0) }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="master-list-chips" aria-label="Filter notes by colour">
                <a class="master-list-chip {{ $colour === 'all' ? 'is-active' : '' }}"
                    href="{{ $chipUrl(['colour' => 'all']) }}">Every colour</a>
                @foreach ($colourOptions as $key => $label)
                    <a class="master-list-chip {{ $colour === $key ? 'is-active' : '' }}"
                        href="{{ $chipUrl(['colour' => $key]) }}">
                        <span class="nt-dot nt-dot--{{ $key }}" aria-hidden="true"></span> {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>

        <form method="GET" action="{{ route('notes.index') }}">
            {{-- The chips are links, so they are not part of this form. Carrying
                 them as hidden fields is what keeps a search from quietly
                 dropping the state and the colour the reader had chosen. --}}
            @if ($state !== 'desk')
                <input type="hidden" name="state" value="{{ $state }}">
            @endif
            @if ($colour !== 'all')
                <input type="hidden" name="colour" value="{{ $colour }}">
            @endif

            <div class="master-filter-row core-filter-toolbar">
                <label class="master-search">
                    <span aria-hidden="true"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input class="master-input" type="search" name="q" value="{{ $q }}"
                        placeholder="Search a title or a line of a note" aria-label="Search notes">
                </label>

                <x-filter-trigger drawer="notesFiltersDrawer" label="Filters" :count="count($applied)" />
            </div>

            <x-drawer id="notesFiltersDrawer" title="Filter notes" eyebrow="Note filters"
                subtitle="The chips carry the state and the colour; this carries the period and the order." size="medium">
                <section class="core-drawer-section">
                    <h3 class="core-drawer-section-title">Last touched</h3>
                    <div class="core-drawer-fields">
                        <label class="master-field">
                            <span class="master-label">Edited</span>
                            <select class="master-select" name="period">
                                @foreach ($periodOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($period === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                </section>

                <section class="core-drawer-section">
                    <h3 class="core-drawer-section-title">Order</h3>
                    <div class="core-drawer-fields">
                        <label class="master-field">
                            <span class="master-label">Read the list</span>
                            <select class="master-select" name="sort">
                                @foreach ($sortOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($sort === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <span class="master-help">The board keeps its own order: pinned first, then last edited.</span>
                        </label>
                    </div>
                </section>

                <x-slot:footer>
                    @if ($filtered)
                        <a class="master-btn master-btn-soft" href="{{ route('notes.index') }}">Reset</a>
                    @endif
                    <button class="master-btn master-btn-primary" type="submit">
                        <i class="fa-solid fa-filter" aria-hidden="true"></i> Apply filters
                    </button>
                </x-slot:footer>
            </x-drawer>
        </form>

        @if ($filtered)
            <div class="master-list-applied">
                <span class="master-list-applied-title">Filtered by</span>
                @foreach ($applied as $chip)
                    <span class="master-list-applied-chip">
                        <span class="master-list-applied-key">{{ $chip['label'] }}</span>
                        <span class="master-list-applied-value">{{ $chip['value'] }}</span>
                        <a class="master-list-applied-x" href="{{ $chipUrl(array_fill_keys($chip['query'], null)) }}"
                            aria-label="Remove the {{ strtolower($chip['label']) }} filter">&times;</a>
                    </span>
                @endforeach
                <a class="master-list-applied-clear" href="{{ route('notes.index') }}">Clear all</a>
            </div>
        @endif
    </section>

    {{-- ───────────────────────────────────────────────────────────── the board --}}
    <section class="master-card master-card--flat nt-card" aria-labelledby="ntBoardTitle">
        <div class="nt-card-head">
            <div>
                <p class="master-eyebrow">The desk</p>
                <h2 class="master-section-title" id="ntBoardTitle">Pinned first</h2>
            </div>
            <p class="master-list-hint">
                @if ($board->isEmpty())
                    Nothing to show
                @elseif ($boardIsFull)
                    Showing the first {{ number_format($boardLimit) }} — the list below holds all
                    {{ number_format($figures['total']) }}
                @else
                    {{ number_format($board->count()) }} note{{ $board->count() === 1 ? '' : 's' }}, pinned first and then
                    last edited
                @endif
            </p>
        </div>

        @if ($board->isEmpty())
            <div class="master-list-empty">
                <span class="master-list-empty-icon" aria-hidden="true">🗒️</span>
                <h3 class="master-list-empty-title">
                    {{ $filtered ? 'No notes match this view' : 'Your desk is clear' }}
                </h3>
                <p class="master-list-empty-text">
                    @if ($filtered)
                        Nothing left after the chips and filters above. Widen them, or start a fresh note.
                    @else
                        Everything you would have written on a sticky note lives here instead — private to this
                        login, searchable, and it never falls behind the keyboard.
                    @endif
                </p>
                <div class="master-list-empty-actions">
                    @if ($filtered)
                        <a class="master-btn master-btn-soft" href="{{ route('notes.index') }}">Clear the filters</a>
                    @endif
                    <button type="button" class="master-btn master-btn-primary" data-open-note-modal>
                        <i class="fa-solid fa-plus" aria-hidden="true"></i> Write a note
                    </button>
                </div>
            </div>
        @else
            <div class="nt-board">
                @foreach ($board as $note)
                    @include('notes.partials.note-card', ['note' => $note])
                @endforeach
            </div>
        @endif
    </section>

    {{-- ─────────────────────────────────────────────────────── the same notes --}}
    @if ($notes->total() > 0)
        <section class="master-card master-table-card master-card--flat" aria-label="The same notes as a list">
            <div class="master-list-toolbar">
                <p class="master-list-hint"
                    title="The same notes the board draws, with the colour, the state and the actions on each one.">
                    Showing {{ $firstNote }}–{{ $lastNote }} of {{ number_format($notes->total()) }}
                </p>

                <div class="master-list-toolbar-actions">
                    <button type="button" class="master-btn master-btn-light master-btn-sm" data-open-note-modal>
                        <i class="fa-solid fa-plus" aria-hidden="true"></i> New note
                    </button>
                </div>
            </div>

            {{-- The list is the working half of the page, so it is built to fit
                 as many notes on one screen as the words allow: four columns
                 and no more — the colour rides on the dot beside the title, the
                 pinned mark rides beside it — one line of the note under the
                 title, and every action inside a row menu instead of three
                 buttons per row. --}}
            <div class="master-table-wrap ui-mobile-cards">
                <table class="master-table nt-table">
                    <thead>
                        <tr>
                            <th scope="col">Note</th>
                            <th scope="col" class="nt-col-state">State</th>
                            <th scope="col" class="desktop-only">Edited</th>
                            <th scope="col" class="nt-col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($notes as $note)
                            <tr class="nt-row is-clickable" data-href="{{ route('notes.edit', $note) }}">
                                <td data-label="Note">
                                    <span class="nt-dot nt-dot--{{ $note->colourKey() }}" role="img"
                                        aria-label="{{ $note->colourLabel() }} note"></span>

                                    @if ($note->is_pinned)
                                        <span class="nt-pin-mark" role="img" aria-label="Pinned"
                                            title="Pinned to the front">
                                            <i class="fa-solid fa-thumbtack" aria-hidden="true"></i>
                                        </span>
                                    @endif

                                    <a class="nt-note-link" href="{{ route('notes.edit', $note) }}"
                                        title="{{ $note->title }}">{{ $note->title }}</a>

                                    @if ($note->excerpt() !== '')
                                        <span class="nt-cell-sub" title="{{ $note->excerpt(600) }}">{{ $note->excerpt() }}</span>
                                    @endif
                                </td>

                                <td class="nt-col-state" data-label="State">
                                    @if ($note->isArchived())
                                        <span class="nt-badge">Filed away</span>
                                    @else
                                        <span class="nt-badge nt-badge--desk">On the desk</span>
                                    @endif
                                </td>

                                <td class="desktop-only nt-when" data-label="Edited">
                                    <span title="{{ $note->updated_at?->format('d M Y, H:i') }}">{{ $note->editedShortLabel() }}</span>
                                </td>

                                <td class="nt-col-actions" data-label="">
                                    <div class="master-dropdown">
                                        <button type="button" class="master-dropdown-toggle"
                                            aria-label="Actions for {{ $note->title }}" aria-haspopup="true"
                                            aria-expanded="false">
                                            <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                        </button>
                                        <div class="master-dropdown-menu">
                                            <a href="{{ route('notes.edit', $note) }}">
                                                <i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i>
                                                Open the note
                                            </a>

                                            <form method="POST" action="{{ route('notes.pin', $note) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="pinned" value="{{ $note->is_pinned ? 0 : 1 }}">
                                                <button type="submit">
                                                    <i class="fa-solid fa-thumbtack" aria-hidden="true"></i>
                                                    {{ $note->is_pinned ? 'Let it fall back into the pile' : 'Pin to the front' }}
                                                </button>
                                            </form>

                                            <form method="POST" action="{{ route('notes.archive', $note) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit">
                                                    <i class="fa-regular fa-box-archive" aria-hidden="true"></i>
                                                    {{ $note->isArchived() ? 'Put it back on the desk' : 'File it away' }}
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-pagination :items="$notes" />
        </section>
    @endif
</div>

{{-- ────────────────────────────────────────────────────────── a new note --}}
{{-- The composer is a dialog, not a card at the top of the page: writing a note
     is a thing you stop and do, and the page it leaves behind is the desk you
     read. `_dialog` is how this one comes back — a validation failure redirects
     here with the typing kept, the marker below names the dialog, and
     `notes.js` reopens it. Nobody retypes a note. --}}
<span hidden data-open-dialog="{{ $errors->any() ? old('_dialog') : '' }}"></span>

<div class="master-modal" id="noteCreateModal" aria-hidden="true">
    <div class="master-modal-card" role="dialog" aria-modal="true" aria-labelledby="noteCreateTitle">
        <form method="POST" action="{{ route('notes.store') }}">
            @csrf
            <input type="hidden" name="_dialog" value="noteCreateModal">

            <div class="master-modal-header">
                <div class="master-modal-heading">
                    <span class="master-modal-icon" aria-hidden="true"><i class="fa-regular fa-note-sticky"></i></span>
                    <div>
                        <h3 class="master-modal-title" id="noteCreateTitle">A new note</h3>
                        <p class="master-modal-subtitle">Private to this login — nobody else reads it, not even the office.</p>
                    </div>
                </div>
                <button type="button" class="master-modal-close" data-close-modal="noteCreateModal"
                    aria-label="Close">&times;</button>
            </div>

            <div class="master-modal-body">
                @if ($errors->any())
                    <div class="master-info-box is-danger" role="alert">
                        <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                        {{ $errors->first() }}
                    </div>
                @endif

                <div class="master-modal-grid">
                    <div class="master-field full">
                        <label class="master-label" for="noteTitle">Title
                            <span class="master-required" aria-hidden="true">*</span></label>
                        <input class="master-input" id="noteTitle" type="text" name="title"
                            value="{{ old('title') }}" maxlength="{{ \App\Services\NoteVocabulary::TITLE_LIMIT }}"
                            placeholder="What is this about?" autocomplete="off" required>
                        @error('title')<p class="master-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="master-field">
                        <label class="master-label" for="noteColour">Colour</label>
                        <select class="master-select" id="noteColour" name="colour">
                            @foreach ($colourOptions as $key => $label)
                                <option value="{{ $key }}"
                                    @selected(old('colour', \App\Services\NoteVocabulary::DEFAULT_COLOUR) === $key)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="master-field nt-modal-pin">
                        <label class="master-label" for="notePinned">Where it goes</label>
                        <label class="master-check">
                            <input type="checkbox" id="notePinned" name="is_pinned" value="1" @checked(old('is_pinned'))>
                            <span>Pin it to the front of the board</span>
                        </label>
                    </div>

                    <div class="master-field full">
                        <label class="master-label" for="noteBody">The note</label>
                        <textarea class="master-textarea" id="noteBody" name="body" rows="6"
                            placeholder="Write it as it comes — a list keeps its line breaks.">{{ old('body') }}</textarea>
                        <p class="master-help">Line breaks are kept: what you type is what the board shows.</p>
                    </div>
                </div>
            </div>

            <div class="master-modal-footer">
                <button type="button" class="master-btn master-btn-light" data-close-modal="noteCreateModal">Cancel</button>
                <button class="master-btn master-btn-primary" type="submit">
                    <i class="fa-solid fa-thumbtack" aria-hidden="true"></i> Stick it on the desk
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
    <script src="{{ $assetVer('assets/js/notes.js') }}" defer></script>
@endpush
@endsection
