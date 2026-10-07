<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * The arithmetic of a recurring rule — and the only place it is written down.
 *
 * Three things read this class: the **planner** (which writes the dates onto the
 * plan), the **draft's preview** (which shows the dates the plan would hold, so
 * the office can check a rule before approving it), and the **test**
 * (`tests/Unit/RecurrenceScheduleTest.php`). They agree by construction, because
 * there is one step function and everything else is that step repeated. If the
 * preview were computed in the browser — the obvious place to put "show me the
 * next few dates as I type" — the office would be shown one calendar and get
 * another, and nothing on the server would ever fail.
 *
 * The one rule of the arithmetic that matters, and the reason this is a service
 * rather than a line in the intake:
 *
 *   **A month has no 31st, and a rule anchored on the 31st still pays the next
 *   month.** A rule that starts on 31 January pays 28 February (29 in a leap
 *   year), then **31 March, 30 April, 31 May** — the anchor day is carried by
 *   the mathematics below and re-applied to each target month, never read back
 *   from the clamped date. Stepping "one month" from the clamped 28 February is
 *   the classic way a rent rule quietly slides to the 28th of every month for
 *   ever; this class cannot make that mistake because it never does the second
 *   step from the clamped date — it computes every date from the anchor.
 *
 * Everything is a date, not a datetime: a payment is due on a day.
 */
class RecurrenceSchedule
{
    /**
     * The first `$cap` dates of a window, oldest first.
     *
     * `$until` and `$limit` are the window's two closing rules and either may be
     * null; `$cap` is the planner's window (see
     * `RecurrenceVocabulary::PLAN_WINDOW`), never a business rule — it is what
     * keeps a daily rule from being a table of a thousand rows.
     *
     * @return array<int, CarbonInterface> keyed by sequence, so 1 is the first
     */
    public function dates(
        string $frequency,
        CarbonInterface $start,
        ?CarbonInterface $until = null,
        ?int $limit = null,
        int $cap = RecurrenceVocabulary::PLAN_WINDOW,
    ): array {
        $dates = [];

        if ($cap < 1) {
            return $dates;
        }

        $anchor = (int) $start->day;
        $date = $start->copy()->startOfDay();
        $sequence = 1;

        while (count($dates) < $cap) {
            if ($limit !== null && $sequence > $limit) {
                break;
            }

            if ($until !== null && $date->toDateString() > $until->toDateString()) {
                break;
            }

            $dates[$sequence] = $date;
            $sequence++;
            $date = $this->step($date, $frequency, $anchor);
        }

        return $dates;
    }

    /**
     * The date of one sequence number — how the planner continues a plan whose
     * window has moved (the office answered the first dates; the plan needs the
     * twenty-fifth one).
     *
     * Iterating rather than multiplying is deliberate: every rhythm here is
     * "the same day of the next period", and a formula that jumped straight to
     * sequence N would have to re-derive the month-length clamping in a second
     * place — which is the drift this class exists to prevent.
     */
    public function dateForSequence(string $frequency, CarbonInterface $start, int $sequence): CarbonInterface
    {
        if ($sequence < 1) {
            throw new InvalidArgumentException('An occurrence is counted from 1.');
        }

        $anchor = (int) $start->day;
        $date = $start->copy()->startOfDay();

        for ($step = 1; $step < $sequence; $step++) {
            $date = $this->step($date, $frequency, $anchor);
        }

        return $date;
    }

    /**
     * The date after this one, on the same anchor day.
     *
     * Daily and weekly simply add their unit. The five month-based rhythms all
     * take the same road: move to the **first** of the target month, then set the
     * day to the anchor if that month is long enough and to the month's last day
     * if it is not. Starting from the first is what makes "one month after 31
     * January" the 28th of February rather than the 3rd of March (Carbon's
     * `addMonth()` on the 31st would overflow into the next month).
     */
    public function step(CarbonInterface $date, string $frequency, int $anchorDay): CarbonInterface
    {
        $anchorDay = max(1, min(31, $anchorDay));

        return match ($frequency) {
            'daily' => $date->copy()->addDay()->startOfDay(),
            'weekly' => $date->copy()->addWeek()->startOfDay(),
            'monthly' => $this->anchored($date, 1, $anchorDay),
            'quarterly' => $this->anchored($date, 3, $anchorDay),
            'half_yearly' => $this->anchored($date, 6, $anchorDay),
            'yearly' => $this->anchored($date, 12, $anchorDay),
            default => throw new InvalidArgumentException('Unknown frequency: '.$frequency),
        };
    }

    /**
     * The first date on or after `$after` that a window would land on — how the
     * planner finds where to start when a rule is paused for two months and
     * resumed, or when a draft is approved long after it was written.
     *
     * The dates it lands on are the window's own dates: resuming on the 3rd of a
     * month whose payment day is the 5th pays on the 5th and never on the 3rd,
     * because a resumed rule keeps its anchor like every other.
     */
    public function firstOnOrAfter(string $frequency, CarbonInterface $start, CarbonInterface $after): CarbonInterface
    {
        $anchor = (int) $start->day;
        $date = $start->copy()->startOfDay();

        /* A rule started years ago and never worked through would iterate a long
           way; the guard is the window's own reality — a daily rule is the only
           one that can burn through this many steps, and it is capped at the
           number of days between the start and the resume date. */
        $steps = 0;
        $ceiling = max(1, $start->diffInDays($after) + 2);

        while ($date->toDateString() < $after->toDateString()) {
            $date = $this->step($date, $frequency, $anchor);

            if (++$steps > $ceiling) {
                break;
            }
        }

        return $date;
    }

    /**
     * How many dates the whole window holds, as far as the office stated it:
     * `$limit` when a count was given, otherwise counted out to the end date.
     *
     * A window with neither a count nor an end date has no number: "until we say
     * stop" is a real answer, and this returns null for it rather than inventing
     * a figure the page would then print as a promise. Screens that must show
     * something show the plan's own count instead.
     */
    public function totalDates(string $frequency, CarbonInterface $start, ?CarbonInterface $until, ?int $limit, int $ceiling = 1000): ?int
    {
        if ($limit !== null) {
            return $limit;
        }

        if ($until === null) {
            return null;
        }

        $count = 0;
        $anchor = (int) $start->day;
        $date = $start->copy()->startOfDay();

        while ($date->toDateString() <= $until->toDateString() && $count < $ceiling) {
            $count++;
            $date = $this->step($date, $frequency, $anchor);
        }

        return $count;
    }

    /** Today, in the office's own timezone — the one clock the module reads. */
    public function today(): CarbonInterface
    {
        return Carbon::today();
    }

    /** Move to the first of the target month and anchor the day inside it. */
    private function anchored(CarbonInterface $date, int $months, int $anchorDay): CarbonInterface
    {
        $target = $date->copy()->startOfMonth()->addMonths($months);

        return $target->setDay(min($anchorDay, $target->daysInMonth))->startOfDay();
    }
}
