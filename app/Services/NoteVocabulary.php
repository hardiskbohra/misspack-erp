<?php

namespace App\Services;

/**
 * Every word the notes module writes with, in one place.
 *
 * A note's colour is written in three places at once — the value in
 * `notes.colour`, the label on the select and the class the note wears on the
 * board (`.nt-note--<key>`, in `public/assets/css/notes.css`). If those three
 * are three lists, the day someone adds "orange" the select offers a colour
 * that renders as no colour at all, and nothing fails loudly: the note is
 * simply blank where the others are tinted. So the keys are here once, and
 * `tools/checks/notes-check.cjs` reads this constant and asserts the sheet has
 * a rule for every one of them.
 *
 * It also owns the two limits the screen prints: how much of a body the table
 * quotes before it points at the note, and how many notes the board draws. The
 * board limit is a promise the page has to keep out loud — "showing the first
 * 24 of 90" — which is the only honest way to cap something a reader cannot
 * count for themselves.
 */
class NoteVocabulary
{
    /**
     * The colours a note may wear: key => label.
     *
     * Key order is the order of the chips and the select. The keys are also the
     * CSS class suffixes, so they stay lowercase and single words.
     */
    public const COLOURS = [
        'yellow' => 'Yellow',
        'blue' => 'Blue',
        'green' => 'Green',
        'pink' => 'Pink',
        'purple' => 'Purple',
        'grey' => 'Grey',
    ];

    /** The colour a note wears when nobody chose one — still a vocabulary word. */
    public const DEFAULT_COLOUR = 'yellow';

    /** The board is a desk, not a filing cabinet: it draws the first N and says so. */
    public const BOARD_LIMIT = 24;

    /** How much of a body the table quotes before it points at the note. */
    public const EXCERPT_LIMIT = 140;

    /** The title's ceiling, read by the form, the validation and the column. */
    public const TITLE_LIMIT = 160;

    /** The body's ceiling — a sticky note, not a document store. */
    public const BODY_LIMIT = 20000;

    /**
     * The colours, label first: `['yellow' => 'Yellow', …]`.
     *
     * @return array<string, string>
     */
    public static function colours(): array
    {
        return self::COLOURS;
    }

    /** Is this a colour this app knows? A query-string value is not trusted. */
    public static function hasColour(?string $colour): bool
    {
        return $colour !== null && array_key_exists($colour, self::COLOURS);
    }

    /** The label for a key, and the default's label for anything else. */
    public static function label(?string $colour): string
    {
        return self::COLOURS[$colour] ?? self::COLOURS[self::DEFAULT_COLOUR];
    }

    /**
     * The colour to actually write: the one asked for, or the default.
     *
     * The intake calls this rather than the controller, so an unknown key can
     * never reach the column — validation catches it at the door, and this is
     * the floor under that.
     */
    public static function normalise(?string $colour): string
    {
        $colour = is_string($colour) ? strtolower(trim($colour)) : '';

        return self::hasColour($colour) ? $colour : self::DEFAULT_COLOUR;
    }
}
