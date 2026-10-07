<?php

namespace App\Models;

use App\Services\NoteVocabulary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One note, private to the login that wrote it.
 *
 * The model's job here is the *reader*, and it has exactly one door: a note is
 * only ever reached through `scopeOwnedBy()`. A desk is not a company
 * document — the office does not get to read an employee's stickies, and an
 * employee obviously does not get to read the office's — so "whose note is it"
 * is the first clause of every query in the module, written once, here.
 *
 * Nothing in this class writes a note: `App\Services\NoteIntake` is the only
 * writer (see its docblock). Scopes read; they do not mutate.
 */
class Note extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'body',
        'colour',
        'is_pinned',
        'archived_at',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'archived_at' => 'datetime',
    ];

    /** The person the note is private to. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /* ----------------------------------------------------------------- reads */

    /**
     * The privacy rule, as a query.
     *
     * A note with no owner is nothing to nobody. `null` — a session that has
     * gone, a console command with no actor, a hand-typed query string — must
     * match **no** rows rather than all of them, which is what a bare
     * `where('user_id', null)` would quietly become. `1 = 0` is the honest
     * answer to "whose notes are these?": none you may see.
     */
    public function scopeOwnedBy(Builder $query, ?int $userId): Builder
    {
        return $userId
            ? $query->where('user_id', $userId)
            : $query->whereRaw('1 = 0');
    }

    /** One box, two places a word can be: the title and the body. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $nested) use ($term) {
            $nested->where('title', 'like', "%{$term}%")
                ->orWhere('body', 'like', "%{$term}%");
        });
    }

    /** Still on the desk. */
    public function scopeOnTheDesk(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    /** Filed away, and still readable — filing is not deleting. */
    public function scopeFiledAway(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    /** Stuck where you can see it. */
    public function scopePinned(Builder $query): Builder
    {
        return $query->where('is_pinned', true);
    }

    /** A colour only narrows when it is a colour this app knows. */
    public function scopeInColour(Builder $query, ?string $colour): Builder
    {
        return NoteVocabulary::hasColour($colour)
            ? $query->where('colour', $colour)
            : $query;
    }

    /** "Edited since" is about the words, so it reads `updated_at`. */
    public function scopeEditedSince(Builder $query, ?Carbon $moment): Builder
    {
        return $moment ? $query->where('updated_at', '>=', $moment) : $query;
    }

    /* ---------------------------------------------------------------- orders */

    /**
     * The board's order: pinned first, then the last edit.
     *
     * The board keeps this order whatever the table is sorted by — "pinned
     * first" is what a board *is* — which is why this is a scope and not one of
     * `NoteFilters`' sort choices.
     */
    public function scopeDeskOrder(Builder $query): Builder
    {
        return $query->orderByDesc('is_pinned')
            ->orderByDesc('updated_at')
            ->orderByDesc('id');
    }

    /** The table's default: the note you touched last is the one you want. */
    public function scopeRecentFirst(Builder $query): Builder
    {
        return $query->orderByDesc('updated_at')->orderByDesc('id');
    }

    /** Newest written first. */
    public function scopeNewestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    /** A–Z, for when you half-remember the first word. */
    public function scopeTitleOrder(Builder $query): Builder
    {
        return $query->orderBy('title')->orderBy('id');
    }

    /* --------------------------------------------------------------- reading */

    /** Filed away rather than on the desk. */
    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /** The colour to paint this note, always one the vocabulary knows. */
    public function colourKey(): string
    {
        return NoteVocabulary::normalise($this->colour);
    }

    public function colourLabel(): string
    {
        return NoteVocabulary::label($this->colour);
    }

    /**
     * The body as one line, for a table cell.
     *
     * The board prints the body with its line breaks — a note written as a list
     * is a list — but a table row is one line by construction, so this folds
     * the whitespace first and then cuts. Two readouts of one note, each honest
     * about what it is; the note itself is one click away.
     */
    public function excerpt(int $limit = NoteVocabulary::EXCERPT_LIMIT): string
    {
        $body = trim(preg_replace('/\s+/u', ' ', (string) $this->body) ?? '');

        return $body === '' ? '' : Str::limit($body, $limit);
    }

    /** When it was last touched, in the words a desk uses. */
    public function editedLabel(): string
    {
        return $this->updated_at ? $this->updated_at->diffForHumans() : '—';
    }
}
