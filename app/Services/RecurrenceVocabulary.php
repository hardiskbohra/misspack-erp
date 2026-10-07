<?php

namespace App\Services;

use Carbon\CarbonInterface;

/**
 * Every word the recurring-cashflow module writes with, in one place.
 *
 * The frequencies are the interesting half. A rule's rhythm is written in three
 * places at once — the value in `cashflow_recurrence_rules.frequency`, the label
 * on the select and in the table, and the **step in the schedule maths**. Three
 * lists would mean the day somebody adds "fortnightly" the form offers a rhythm
 * that the planner silently reads as the default, and nothing fails loudly: the
 * plan simply holds the wrong dates. So the keys are here once, and
 * `tools/checks/recurring-check.cjs` reads this constant and asserts that the
 * schedule has a case for every one of them.
 *
 * The statuses are split the way the module is: a rule's four states, and an
 * occurrence's four. They are different words about different things — a rule is
 * `active`, the payment it asks about is `pending` — so they are two constants
 * and never one shared list.
 */
class RecurrenceVocabulary
{
    /* ------------------------------------------------------------------ the rule */

    /** Key => the label the office reads. Key order is the order of the select. */
    public const FREQUENCIES = [
        'daily' => 'Every day',
        'weekly' => 'Every week',
        'monthly' => 'Every month',
        'quarterly' => 'Every quarter',
        'half_yearly' => 'Every six months',
        'yearly' => 'Every year',
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_ENDED = 'ended';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_ACTIVE => 'Running',
        self::STATUS_PAUSED => 'Paused',
        self::STATUS_ENDED => 'Ended',
    ];

    /**
     * The states a reader filters by — the status column plus the ask, because
     * "a draft nobody has picked up" and "a draft waiting on an answer" are two
     * different piles, and the second is the one the office works from.
     */
    public const STATE_LABELS = [
        'everything' => 'Everything',
        'approval' => 'Waiting for approval',
        'running' => 'Running',
        'drafts' => 'Drafts',
        'paused' => 'Paused',
        'ended' => 'Ended',
    ];

    /**
     * The badge tone per state — the **shared** badge's own suffixes, so a screen
     * writes `core-badge core-badge-<tone>` and never invents a badge of its own
     * (`public/assets/css/core.css`, the house's badge). The check reads these
     * keys against that sheet, because a state whose tone has no class is a
     * state that renders as no state at all.
     */
    public const STATE_TONES = [
        'approval' => 'warning',
        'running' => 'success',
        'drafts' => 'neutral',
        'paused' => 'info',
        'ended' => 'neutral',
    ];

    /* ------------------------------------------------------------ the occurrence */

    public const OCCURRENCE_PENDING = 'pending';
    public const OCCURRENCE_APPROVED = 'approved';
    public const OCCURRENCE_SKIPPED = 'skipped';
    public const OCCURRENCE_CANCELLED = 'cancelled';

    /** What the plan still holds: a promise about the future, not a decision. */
    public const OCCURRENCE_OPEN = [self::OCCURRENCE_PENDING];

    public const OCCURRENCE_LABELS = [
        self::OCCURRENCE_PENDING => 'Waiting for approval',
        self::OCCURRENCE_APPROVED => 'Posted',
        self::OCCURRENCE_SKIPPED => 'Skipped',
        self::OCCURRENCE_CANCELLED => 'Withdrawn',
    ];

    /* The same shared suffixes: a date waiting for its turn is not a warning
       until it is due (the view says so), so `pending` is the one tone a screen
       overrides. */
    public const OCCURRENCE_TONES = [
        self::OCCURRENCE_PENDING => 'warning',
        self::OCCURRENCE_APPROVED => 'success',
        self::OCCURRENCE_SKIPPED => 'neutral',
        self::OCCURRENCE_CANCELLED => 'neutral',
    ];

    /* ------------------------------------------------------------------ the limits */

    /**
     * How many undecided dates the plan holds at once.
     *
     * A plan is a **window, not the whole promise**: "every day until December
     * 2028" is a thousand rows nobody reads, and a table that grows by a
     * thousand rows to hold a sentence is a table that will one day be a
     * problem. The planner keeps this many dates in front of the office and
     * tops the window up as the dates are worked through, so a daily rule and a
     * yearly rule read the same and cost the same.
     */
    public const PLAN_WINDOW = 24;

    /** How many dates a draft's preview shows before it says "and so on". */
    public const PREVIEW_LIMIT = 8;

    /**
     * How many approvals the module's front page lists inline before it points
     * at the rules. The figure above them is the real count — this is only how
     * much of the queue is drawn on one screen.
     */
    public const DUE_LIMIT = 12;

    public const TITLE_LIMIT = 160;
    public const PARTICULAR_LIMIT = 160;
    public const NAME_LIMIT = 160;
    public const EXPENSE_HEAD_LIMIT = 120;
    public const DECISION_NOTE_LIMIT = 255;
    public const NOTES_LIMIT = 4000;

    /* ---------------------------------------------------------------- the reading */

    /** Is this a rhythm this app knows? A query-string value is not trusted. */
    public static function hasFrequency(?string $frequency): bool
    {
        return $frequency !== null && array_key_exists($frequency, self::FREQUENCIES);
    }

    public static function hasState(?string $state): bool
    {
        return $state !== null && array_key_exists($state, self::STATE_LABELS);
    }

    public static function frequencyLabel(?string $frequency): string
    {
        return self::FREQUENCIES[$frequency] ?? self::FREQUENCIES['monthly'];
    }

    /**
     * The rhythm as a sentence, anchored on the day the window starts.
     *
     * "Every month on the 5th" and "Every quarter on the 1st" are what an office
     * actually says; "monthly" alone leaves the reader to open the rule to find
     * out which day. A weekly rule names the weekday instead, because that is
     * how a weekly payment is described out loud.
     */
    public static function cadence(string $frequency, CarbonInterface $start): string
    {
        $day = (int) $start->day;

        return match ($frequency) {
            'daily' => 'Every day',
            'weekly' => 'Every '.$start->format('l'),
            'monthly' => 'Every month on the '.self::ordinal($day),
            'quarterly' => 'Every quarter on the '.self::ordinal($day),
            'half_yearly' => 'Every six months on the '.self::ordinal($day),
            'yearly' => 'Every year on '.$start->format('j M'),
            default => self::frequencyLabel($frequency),
        };
    }

    /** 1st, 2nd, 3rd, 4th … 11th, 21st, 31st. */
    public static function ordinal(int $day): string
    {
        if ($day % 100 >= 11 && $day % 100 <= 13) {
            return $day.'th';
        }

        return $day.self::suffix($day % 10);
    }

    private static function suffix(int $last): string
    {
        return match ($last) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }
}
