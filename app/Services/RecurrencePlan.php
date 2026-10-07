<?php

namespace App\Services;

use App\Models\CashflowRecurrenceOccurrence;
use App\Models\CashflowRecurrenceRule;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The plan: the only writer of an occurrence row.
 *
 * A rule says "every month on the 5th"; the plan is the list of dates it has
 * actually promised, one row each, and every one of them is written here. Two
 * things make this class the interesting half of the module:
 *
 * **The plan is a window, not the whole promise.** "Every day until 2028" is a
 * thousand rows nobody will read, so the plan holds
 * `RecurrenceVocabulary::PLAN_WINDOW` undecided dates and is topped up as the
 * office works through them. A daily rule and a yearly rule therefore cost the
 * same, and the tail of a very long rule is not a table that grows for ever.
 *
 * **Re-planning is a reconciliation, never a rebuild.** The rows that are still
 * undecided keep their dates, their ids and their notifications; the ones the
 * new plan no longer contains are **withdrawn**, not deleted; and the dates the
 * new plan adds are appended. The date maths is `RecurrenceSchedule`, which the
 * draft's preview also reads — so what the office was shown before approving is
 * exactly what gets written.
 *
 * The three decisions a person can make (approve, skip, withdraw) are here too,
 * because they are all facts about an occurrence row; the *rule's* own state is
 * never touched from this class — that belongs to `RecurrenceIntake`, which
 * calls in here for the plan half of a state change **and** for the other half
 * of an edit (`reconcile()`, the recipe changing under a live plan).
 */
class RecurrencePlan
{
    /** The statuses that count as a promise: everything except a withdrawal. */
    private const COMMITTED = [
        RecurrenceVocabulary::OCCURRENCE_PENDING,
        RecurrenceVocabulary::OCCURRENCE_APPROVED,
        RecurrenceVocabulary::OCCURRENCE_SKIPPED,
    ];

    public function __construct(
        private RecurrenceSchedule $schedule,
        private RecurrencePosting $posting,
        private OfficeBriefing $briefing,
    ) {
    }

    /* ------------------------------------------------------------------ reading */

    /**
     * The dates a rule *would* hold if it were approved now — what the draft
     * the office is reading promises, computed by the same calculator that will
     * write the real thing.
     *
     * @return array<int, CarbonInterface> keyed by sequence, 1 first
     */
    public function preview(CashflowRecurrenceRule $rule, int $limit = RecurrenceVocabulary::PREVIEW_LIMIT): array
    {
        return $this->schedule->dates(
            $rule->frequency,
            $rule->starts_on,
            $rule->ends_on,
            $rule->occurrence_limit,
            $limit,
        );
    }

    /**
     * Has this rule asked its last question — and had it answered?
     *
     * "Finished" is two facts together: nothing is still waiting for approval,
     * *and* the window has nothing left to promise (the count is used up, or
     * the next date would fall past the end date). A rule that has simply
     * worked through its window up to today is very much alive.
     */
    public function isFinished(CashflowRecurrenceRule $rule, ?CarbonInterface $today = null): bool
    {
        if ($rule->occurrences()->where('status', RecurrenceVocabulary::OCCURRENCE_PENDING)->exists()) {
            return false;
        }

        $committed = $rule->occurrences()->whereIn('status', self::COMMITTED)->count();

        if ($rule->occurrence_limit !== null && $committed >= $rule->occurrence_limit) {
            return true;
        }

        if ($rule->ends_on === null) {
            return false;
        }

        $next = $this->nextDate($rule, $today ?: Carbon::today());

        return $next !== null && $next->toDateString() > $rule->ends_on->toDateString();
    }

    /* ------------------------------------------------------------------- writing */

    /**
     * Top the window up: write the next dates the rule owes, up to the window
     * and up to whichever of `ends_on` / `occurrence_limit` closes it.
     *
     * Only an active rule has a plan. A draft has none (it has a *preview*), and
     * a paused or ended rule's tail has already been withdrawn — so there is no
     * path from here to a payment on a rule nobody approved.
     *
     * @return int how many dates were written
     */
    public function plan(CashflowRecurrenceRule $rule, ?CarbonInterface $today = null): int
    {
        if (! $rule->isActive() || ! $rule->starts_on) {
            return 0;
        }

        $today = $today ?: Carbon::today();

        $open = $rule->occurrences()->where('status', RecurrenceVocabulary::OCCURRENCE_PENDING)->count();
        $room = RecurrenceVocabulary::PLAN_WINDOW - $open;

        if ($room <= 0) {
            return 0;
        }

        $committed = $rule->occurrences()->whereIn('status', self::COMMITTED)->count();

        if ($rule->occurrence_limit !== null) {
            $room = min($room, max(0, $rule->occurrence_limit - $committed));
        }

        if ($room <= 0) {
            return 0;
        }

        $cursor = $this->nextDate($rule, $today);

        if ($cursor === null) {
            return 0;
        }

        /* Sequences count every row the rule has ever written, withdrawals
           included: the number is the rule's own history, not a coupon. */
        $sequence = (int) $rule->occurrences()->max('sequence');

        $anchor = (int) $rule->starts_on->day;
        $written = 0;

        while ($written < $room) {
            if ($rule->ends_on && $cursor->toDateString() > $rule->ends_on->toDateString()) {
                break;
            }

            $rule->occurrences()->create([
                'sequence' => ++$sequence,
                'effective_date' => $cursor->toDateString(),
                'status' => RecurrenceVocabulary::OCCURRENCE_PENDING,
            ]);

            $written++;
            $cursor = $this->schedule->step($cursor, $rule->frequency, $anchor);
        }

        return $written;
    }

    /**
     * Withdraw every undecided date: what pausing, ending or a re-plan that
     * moved a date does to the promise.
     *
     * The rows are cancelled rather than deleted because they are history — the
     * office *did* tell the accounts desk that a payment was coming on the 5th,
     * and withdrawing it is a fact worth keeping. Nothing decided is ever
     * touched: an approval and a skip are answers, and answers do not un-happen.
     */
    public function withdraw(CashflowRecurrenceRule $rule): int
    {
        return $rule->occurrences()
            ->where('status', RecurrenceVocabulary::OCCURRENCE_PENDING)
            ->update([
                'status' => RecurrenceVocabulary::OCCURRENCE_CANCELLED,
                'updated_at' => now(),
            ]);
    }

    /**
     * Bring the plan in line with the recipe after an edit — the reconciliation
     * the class docblock promises, run for the one reason that is not a state
     * change.
     *
     * The rule is the source of truth for its own schedule, so when the schedule
     * changes the dates it has **not** decided have to be re-read from it: a
     * monthly rule changed to quarterly must not go on asking for the dates it
     * promised an hour ago. Three rules make that safe:
     *
     *   - **a decision is never touched.** An approval posted money and a skip is
     *     an answer; neither moves because the recipe did, and neither is even
     *     read as something to withdraw. They do keep their rung of the ladder —
     *     the walk below consumes the rungs they occupy, so the dates that follow
     *     them still follow *them*;
     *   - **an undecided date stays on if it is still a rung.** Matching is by
     *     date, so a rule whose amount, wording or party changed keeps every
     *     date — and its notification, which is why this is a reconciliation and
     *     not a rebuild. Only a change to the rhythm, the anchor day, the window
     *     or the count moves dates;
     *   - **the rest are withdrawn, never deleted.** `cancel()` writes the same
     *     `cancelled` a pause writes, so "we had promised the 5th and withdrew
     *     it" stays readable.
     *
     * Rungs nobody holds are gaps rather than errors: a pause and a resume leave
     * the ladder with a hole in it, and the sweep picks the next rung on or after
     * today. The walk is measured against the recipe's own start — the one thing
     * that does not move when the office edits nothing — and it assumes the one
     * thing it cannot check from inside the loop: the rows arrive in
     * `planOrder()`, date order with the sequence as the tie-break.
     *
     * @return int how many dates the top-up wrote — 0 when the window was full
     */
    public function reconcile(CashflowRecurrenceRule $rule, ?CarbonInterface $today = null): int
    {
        if (! $rule->isActive() || ! $rule->starts_on) {
            return 0;
        }

        $anchor = (int) $rule->starts_on->day;
        $cursor = $rule->starts_on->copy()->startOfDay();
        $end = $rule->ends_on;
        $committed = 0;

        /* One read of the rule's own ladder: every row that still counts as a
           promise, in the order the promise was made. */
        foreach ($rule->occurrences()->whereIn('status', self::COMMITTED)->planOrder()->get() as $row) {
            $date = $row->effective_date?->copy()->startOfDay();

            if ($date === null) {
                continue;
            }

            while ($cursor->toDateString() < $date->toDateString()) {
                $cursor = $this->schedule->step($cursor, $rule->frequency, $anchor);
            }

            $onLadder = $cursor->toDateString() === $date->toDateString();
            $pastEnd = $end !== null && $date->toDateString() > $end->toDateString();
            $overCount = $rule->occurrence_limit !== null && $committed >= $rule->occurrence_limit;

            if ($row->status === RecurrenceVocabulary::OCCURRENCE_PENDING
                && (! $onLadder || $pastEnd || $overCount)) {
                $this->cancel($row);

                continue;
            }

            if ($onLadder && ! $pastEnd) {
                $cursor = $this->schedule->step($cursor, $rule->frequency, $anchor);
            }

            $committed++;
        }

        /* The window is topped back up on the new ladder — and if every date was
           already a rung, this writes nothing at all. */
        return $this->plan($rule, $today);
    }

    /**
     * Withdraw one undecided date. The single-row arm of `withdraw()`, for the
     * one caller that reconciles a plan row by row.
     *
     * A decision can never arrive here: the caller filters to pending rows, and
     * the status is the guard under that filter.
     */
    private function cancel(CashflowRecurrenceOccurrence $occurrence): void
    {
        if (! $occurrence->isPending()) {
            return;
        }

        $occurrence->status = RecurrenceVocabulary::OCCURRENCE_CANCELLED;
        $occurrence->save();
    }

    /* ---------------------------------------------------------------- decisions */

    /**
     * Yes: this date's money moves. The ledger entry is posted in the same
     * transaction as the decision, so there is never an approval without its
     * entry or an entry without its approval.
     *
     * **A payment is approved on or after its effective date.** Before that day
     * the occurrence is still a plan, not an ask: the office is notified when
     * the money is due, and the answer belongs to that moment. Skipping, by
     * contrast, is allowed at any time — see `skip()`.
     */
    public function approve(User $decidedBy, CashflowRecurrenceOccurrence $occurrence, ?string $note = null, ?CarbonInterface $today = null): CashflowRecurrenceOccurrence
    {
        if (! $occurrence->isPending()) {
            throw new RuntimeException('That date has already been decided.');
        }

        if (! $occurrence->isDue($today ?: Carbon::today())) {
            throw new RuntimeException('This payment opens for approval on its effective date.');
        }

        DB::transaction(function () use ($decidedBy, $occurrence, $note) {
            $entry = $this->posting->post($occurrence, $decidedBy);

            $occurrence->status = RecurrenceVocabulary::OCCURRENCE_APPROVED;
            $occurrence->cashflow_entry_id = $entry?->id;
            $occurrence->decided_at = now();
            $occurrence->decided_by = $decidedBy->id;
            $occurrence->decision_note = $this->note($note);
            $occurrence->save();
        });

        if ($occurrence->rule) {
            $this->plan($occurrence->rule);
        }

        return $occurrence;
    }

    /**
     * No: this date is not paid, and the rule carries on with the next one.
     *
     * A skip is a plan decision, not a payment, so it may be made **any time** —
     * including in advance, when the office already knows that this month's
     * salary went out by cheque. The rule keeps its anchor: skipping the 5th of
     * November does not move December's payment to the 6th.
     */
    public function skip(User $decidedBy, CashflowRecurrenceOccurrence $occurrence, ?string $note = null): CashflowRecurrenceOccurrence
    {
        if (! $occurrence->isPending()) {
            throw new RuntimeException('That date has already been decided.');
        }

        $occurrence->status = RecurrenceVocabulary::OCCURRENCE_SKIPPED;
        $occurrence->decided_at = now();
        $occurrence->decided_by = $decidedBy->id;
        $occurrence->decision_note = $this->note($note);
        $occurrence->save();

        if ($occurrence->rule) {
            $this->plan($occurrence->rule);
        }

        return $occurrence;
    }

    /**
     * Tell the office about the dates that are asking right now — the ask, once
     * per date.
     *
     * `notified_at` is the fact this method owns: the plan knows that a date
     * opened for approval and that the office was told. The alert itself is
     * `OfficeBriefing`'s, which is the one thing in this application that raises
     * one. The stamp is written **only when the alert exists** — if the office
     * has switched the source off, nothing was said, and pretending otherwise
     * would swallow the ask for ever; if it is switched back on, tomorrow's
     * sweep finds the date still unnotified and speaks. The alert itself is
     * fingerprinted on the occurrence, so re-running the sweep is free.
     *
     * @return Collection<int, CashflowRecurrenceOccurrence>
     */
    public function notifyDue(CashflowRecurrenceRule $rule, ?CarbonInterface $today = null): Collection
    {
        $today = $today ?: Carbon::today();

        $due = $rule->occurrences()
            ->dueBy($today)
            ->unnotified()
            ->planOrder()
            ->get();

        $told = collect();

        foreach ($due as $occurrence) {
            if (! $this->briefing->recurringNeedsApproval($occurrence)) {
                continue;
            }

            $occurrence->notified_at = now();
            $occurrence->save();

            $told->push($occurrence);
        }

        return $told;
    }

    /* ------------------------------------------------------------------ helpers */

    /**
     * The next date the ladder would write — from where the promise stopped, or
     * from today when the promise is in the past.
     *
     * The second half is the rule about waking up: a rule paused for three
     * months and resumed owes nobody three months of back-dated asks, so its
     * ladder restarts on the rule's own anchor day on or after today. A rule
     * that has never run and was approved late starts the same way — the
     * effective date is where the plan *begins*, not a debt of missed dates.
     */
    private function nextDate(CashflowRecurrenceRule $rule, CarbonInterface $today): ?CarbonInterface
    {
        $anchor = (int) $rule->starts_on->day;
        $last = $rule->occurrences()->whereIn('status', self::COMMITTED)->max('effective_date');

        $cursor = $last
            ? $this->schedule->step(Carbon::parse($last), $rule->frequency, $anchor)
            : $rule->starts_on->copy()->startOfDay();

        if ($cursor->toDateString() < $today->toDateString()) {
            $cursor = $this->schedule->firstOnOrAfter($rule->frequency, $rule->starts_on, $today);
        }

        return $cursor;
    }

    private function note($note): ?string
    {
        $text = trim((string) $note);

        return $text === '' ? null : $text;
    }
}
