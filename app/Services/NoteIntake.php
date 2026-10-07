<?php

namespace App\Services;

use App\Models\Note;
use App\Models\User;

/**
 * The only writer of a note.
 *
 * Five things happen to a note — it is written, its words are changed, it is
 * pinned, it is filed away and it is deleted — and every one of them goes
 * through this class. The controller never assigns a field, never calls
 * `save()`, and never decides what a colour is; it validates and hands the
 * facts over. That matters more here than in most modules, because a note is
 * *private*: the owner column is written in exactly one place (`create()`), a
 * note can never change hands (`update()` has no owner in its field list), and
 * there is one place to read to know that.
 *
 * Two smaller rules are enforced here rather than hoped for:
 *
 *   - **a colour the vocabulary does not know is the default colour**, not an
 *     unknown key in a column that a stylesheet then cannot paint;
 *   - **an empty body is no body**, not a body of spaces — otherwise "has this
 *     note got anything in it?" is answered differently by the card, the table
 *     and the search.
 */
class NoteIntake
{
    /**
     * A new note, owned by the person who is signed in.
     *
     * `$owner` is a `User` and not an id on purpose: the caller has to have the
     * person, so "whose note is this" is never a number picked out of a URL.
     */
    public function create(User $owner, array $facts): Note
    {
        $note = new Note();

        $note->user_id = $owner->id;

        $this->fill($note, $facts);
        $note->save();

        return $note;
    }

    /** Change the words — and only the words. A note does not change hands. */
    public function update(Note $note, array $facts): Note
    {
        $this->fill($note, $facts);
        $note->save();

        return $note;
    }

    /** Stick it to the front of the desk, or let it fall back into the pile. */
    public function pin(Note $note, bool $pinned): Note
    {
        $note->is_pinned = $pinned;
        $note->save();

        return $note;
    }

    /**
     * Off the desk, into the drawer — and still readable there.
     *
     * Filing is one fact (`archived_at`), so the two directions are the same
     * column: `now()` files, `null` restores. Nothing is copied and nothing is
     * soft-deleted, which is why "restore" is exact rather than approximate.
     */
    public function fileAway(Note $note): Note
    {
        $note->archived_at = now();
        $note->save();

        return $note;
    }

    public function restore(Note $note): Note
    {
        $note->archived_at = null;
        $note->save();

        return $note;
    }

    /** Gone. The row and its words go together — there is nothing to keep. */
    public function delete(Note $note): void
    {
        $note->delete();
    }

    /**
     * The one place a note's fields are set from facts.
     *
     * `array_key_exists` rather than `??`: a field the form sent as empty is a
     * field the person cleared, and "cleared" and "not mentioned" are different
     * things — the first must empty the column, the second must leave it alone.
     */
    private function fill(Note $note, array $facts): void
    {
        if (array_key_exists('title', $facts)) {
            $note->title = trim((string) $facts['title']);
        }

        if (array_key_exists('body', $facts)) {
            $note->body = $this->body($facts['body']);
        }

        if (array_key_exists('colour', $facts)) {
            $note->colour = NoteVocabulary::normalise(
                is_string($facts['colour']) ? $facts['colour'] : null
            );
        }

        if (array_key_exists('is_pinned', $facts)) {
            $note->is_pinned = (bool) $facts['is_pinned'];
        }
    }

    /** An empty body is no body — not a body of spaces. */
    private function body($value): ?string
    {
        $body = trim((string) $value);

        return $body === '' ? null : $body;
    }
}
