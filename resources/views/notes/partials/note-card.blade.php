{{--
    One sticky on the board — included once per note by notes/index.blade.php.

    The whole card is one link to the note's own page, which is why there is no
    button inside it: a pin or a delete here would be a control inside a link,
    and the board is the place you *read* your desk. The working controls — pin,
    file away, open — are in the table below, where a row can hold a form
    without arguing with a link.

    The body is printed with its line breaks (`nl2br` after `e()`), the way the
    invoice record prints a line's description: a note written as a list is a
    list, and folding it into one paragraph loses the only structure it had.
--}}
<a class="nt-note nt-note--{{ $note->colourKey() }}{{ $note->isArchived() ? ' is-filed' : '' }}"
    href="{{ route('notes.edit', $note) }}">
    <span class="nt-note-top">
        <span class="nt-dot nt-dot--{{ $note->colourKey() }}" aria-hidden="true"></span>
        <span class="nt-note-colour">{{ $note->colourLabel() }}</span>

        @if ($note->isArchived())
            <span class="nt-badge">Filed away</span>
        @endif

        @if ($note->is_pinned)
            <span class="nt-note-pinned" title="Pinned to the front">
                <i class="fa-solid fa-thumbtack" aria-hidden="true"></i> Pinned
            </span>
        @endif
    </span>

    <span class="nt-note-title">{{ $note->title }}</span>

    @if (trim((string) $note->body) !== '')
        <span class="nt-note-body">{!! nl2br(e($note->body)) !!}</span>
    @endif

    <span class="nt-note-foot">Edited {{ $note->editedLabel() }}</span>
</a>
