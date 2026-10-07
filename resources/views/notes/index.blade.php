@extends('layouts.app')

@section('page-title', 'Notes')

@section('page-actions')
    <a class="master-btn master-btn-primary" href="#noteComposer">
        <i class="fa-solid fa-plus" aria-hidden="true"></i> New note
    </a>
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

    {{-- ────────────────────────────────────────────────────────── a new note --}}
    <section class="master-card master-card--flat nt-card" id="noteComposer" aria-labelledby="ntComposerTitle">
        <div class="nt-card-head">
            <div>
                <p class="master-eyebrow">Quick capture</p>
                <h2 class="master-section-title" id="ntComposerTitle">A new note</h2>
            </div>
            <p class="master-help">Private to this login — nobody else, not even the office, reads your notes.</p>
        </div>

        <form method="POST" action="{{ route('notes.store') }}" class="nt-compose-form">
            @csrf

            <div class="nt-compose-grid">
                <label class="master-field nt-compose-title">
                    <span class="master-label">Title</span>
                    <input class="master-input @error('title') is-invalid @enderror" type="text" name="title"
                        value="{{ old('title') }}" maxlength="{{ \App\Services\NoteVocabulary::TITLE_LIMIT }}"
                        placeholder="What is this about?" autocomplete="off" required>
                    @error('title')
                        <span class="master-field-error">{{ $message }}</span>
                    @enderror
                </label>

                <label class="master-field nt-compose-colour">
                    <span class="master-label">Colour</span>
                    <select class="master-select" name="colour">
                        @foreach ($colourOptions as $key => $label)
                            <option value="{{ $key }}" @selected(old('colour', \App\Services\NoteVocabulary::DEFAULT_COLOUR) === $key)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="master-field nt-compose-body">
                    <span class="master-label">The note</span>
                    <textarea class="master-textarea" name="body" rows="3"
                        placeholder="Write it as it comes — a list keeps its line breaks.">{{ old('body') }}</textarea>
                </label>

                <div class="nt-compose-foot">
                    <label class="master-check nt-compose-pin">
                        <input type="checkbox" name="is_pinned" value="1" @checked(old('is_pinned'))>
                        <span>Pin it to the front</span>
                    </label>

                    <button class="master-btn master-btn-primary" type="submit">
                        <i class="fa-solid fa-thumbtack" aria-hidden="true"></i> Stick it on the desk
                    </button>
                </div>
            </div>
        </form>
    </section>

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
                        Nothing left after the chips and filters above. Widen them, or start a fresh note below.
                    @else
                        Everything you would have written on a sticky note lives here instead — private to this
                        login, searchable, and it never falls behind the keyboard.
                    @endif
                </p>
                <div class="master-list-empty-actions">
                    @if ($filtered)
                        <a class="master-btn master-btn-soft" href="{{ route('notes.index') }}">Clear the filters</a>
                    @endif
                    <a class="master-btn master-btn-primary" href="#noteComposer">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i> Write a note
                    </a>
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
                    <a class="master-btn master-btn-light master-btn-sm" href="#noteComposer">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i> New note
                    </a>
                </div>
            </div>

            <div class="master-table-wrap ui-mobile-cards">
                <table class="master-table">
                    <thead>
                        <tr>
                            <th scope="col">Note</th>
                            <th scope="col" class="desktop-only">Colour</th>
                            <th scope="col">State</th>
                            <th scope="col" class="desktop-only">Edited</th>
                            <th scope="col" class="nt-col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($notes as $note)
                            <tr>
                                <td data-label="Note">
                                    <a href="{{ route('notes.edit', $note) }}"><strong>{{ $note->title }}</strong></a>
                                    @if ($note->excerpt() !== '')
                                        <span class="nt-cell-sub">{{ $note->excerpt() }}</span>
                                    @endif
                                </td>
                                <td class="desktop-only" data-label="Colour">
                                    <span class="nt-dot nt-dot--{{ $note->colourKey() }}" aria-hidden="true"></span>
                                    {{ $note->colourLabel() }}
                                </td>
                                <td data-label="State">
                                    @if ($note->isArchived())
                                        <span class="nt-badge">Filed away</span>
                                    @else
                                        <span class="nt-badge nt-badge--desk">On the desk</span>
                                    @endif
                                    @if ($note->is_pinned)
                                        <span class="nt-badge nt-badge--pinned">Pinned</span>
                                    @endif
                                </td>
                                <td class="desktop-only" data-label="Edited">{{ $note->editedLabel() }}</td>
                                <td class="nt-col-actions" data-label="">
                                    <div class="nt-row-actions">
                                        <a class="master-btn master-btn-soft master-btn-sm"
                                            href="{{ route('notes.edit', $note) }}">Open</a>

                                        <form method="POST" action="{{ route('notes.pin', $note) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="pinned" value="{{ $note->is_pinned ? 0 : 1 }}">
                                            <button class="master-btn master-btn-soft master-btn-sm" type="submit"
                                                title="{{ $note->is_pinned ? 'Let it fall back into the pile' : 'Stick it to the front' }}">
                                                <i class="fa-solid fa-thumbtack" aria-hidden="true"></i>
                                                {{ $note->is_pinned ? 'Unpin' : 'Pin' }}
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('notes.archive', $note) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button class="master-btn master-btn-soft master-btn-sm" type="submit"
                                                title="{{ $note->isArchived() ? 'Put it back on the desk' : 'File it away — it stays here, under “Filed away”' }}">
                                                <i class="fa-regular fa-box-archive" aria-hidden="true"></i>
                                                {{ $note->isArchived() ? 'Restore' : 'File away' }}
                                            </button>
                                        </form>
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
@endsection
