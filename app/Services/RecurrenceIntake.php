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
 * The only thing that changes a recurring rule's state.
 *
 * Seven doors — write it, change it, ask for approval, approve it, send it back,
 * pause / resume / end it, throw it away — and every one of them is here. The
 * controller validates and hands over facts; it never sets `status`, never
 * touches `approved_by` and never decides whether a rule may be edited. That matters
 * more in this module than in most, because the state machine is the promise:
 * a rule that reached `active` without a decision, or a draft that quietly
 * started posting, are failures nobody would notice until the money moved.
 *
 * The transitions, and the reasons they are these and not others:
 *
 *   - **any rule is editable, and an edit re-plans only what nobody has
 *     answered.** The office asked for this, and it is the right ask: a wrong
 *     amount used to mean ending a live salary rule and writing the next one,
 *     which loses the run that is still running. So an edit follows the recipe —
 *     `RecurrencePlan::reconcile()` keeps every undecided date that is still on
 *     the ladder, withdraws the ones that are not, and never touches a decision.
 *     The record says when the recipe moved under an answer (`revised_at`),
 *     because the office agreed to a rule that read a certain way;
 *   - **approving is provisional.** It says "yes, this pattern of payment is
 *     approved"; it does not pay anything. The payments ask again, one date at a
 *     time, in `RecurrencePlan`;
 *   - **sending an ask back returns the rule to the draft pile** and keeps the
 *     reason, so the person who wrote it reads what to fix instead of guessing;
 *   - **pausing withdraws the tail of the plan** and resuming re-plans from
 *     today, because a rule that was asleep for three months owes nobody three
 *     months of back-dated asks;
 *   - **ending is final** and withdraws the same tail.
 *
 * Two transitions are compositions rather than column writes: they change the
 * rule *and* the plan, and both halves go through their one writer (this class
 * for the rule, `RecurrencePlan` for the occurrences).
 */
class RecurrenceIntake
{
    public function __construct(
        private RecurrencePlan $plan,
        private OfficeBriefing $briefing,
    ) {
    }

    /* --------------------------------------------------------------- the author */

    /** A new rule, born a draft — and a draft does nothing at all. */
    public function create(User $author, array $facts): CashflowRecurrenceRule
    {
        $rule = new CashflowRecurrenceRule();

        $rule->status = RecurrenceVocabulary::STATUS_DRAFT;
        $rule->created_by = $author->id;

        $this->fill($rule, $facts);
        $rule->save();

        return $rule;
    }

    /**
     * Change a rule — a draft, a rule waiting on the office, a running one, a
     * paused one, or one that has ended.
     *
     * Two halves, and the order matters. The rule is filled and saved first, so
     * the plan reconciles against what the record now says rather than against
     * what the form meant; then, if it is running, `RecurrencePlan::reconcile()`
     * re-reads the dates nobody has decided from the new recipe — the dates that
     * are still promised keep their rows and their notifications, the rest are
     * withdrawn, and every approval and skip stays exactly where it was.
     *
     * A rule that has already been answered and then changes gets one more
     * fact written down: `revised_at`. It is not a warning and it does not stop
     * anything — it is the honest half of allowing the edit at all. The office
     * said yes to a rule that read a certain way, and the next date asks with a
     * figure nobody has seen unless the record can say the rule has moved.
     *
     * Nothing is reconciled for a draft (it has a preview, not a plan), for a
     * paused rule (its tail was withdrawn on pause — resuming plans the new
     * recipe from today), or for an ended one (its plan is closed; the edit
     * corrects the record). `reconcile()` is also cheap when the schedule did
     * not change: the dates are already rungs, so it writes nothing.
     */
    public function update(CashflowRecurrenceRule $rule, array $facts): CashflowRecurrenceRule
    {
        $this->fill($rule, $facts);

        $changed = $rule->isDirty();

        if ($changed && $rule->decided_at !== null) {
            $rule->revised_at = now();
        }

        if ($changed) {
            $rule->save();
        }

        if ($changed && $rule->isActive()) {
            $this->plan->reconcile($rule);
        }

        return $rule;
    }

    /* ------------------------------------------------------------ the decisions */

    /**
     * Ask for approval: the draft goes to the office's queue.
     *
     * The ask carries the state the rule is in — an ask re-sent after a
     * correction clears the old decision, so the queue never shows a rule as
     * "sent back" and "waiting" at the same time.
     */
    public function requestApproval(User $user, CashflowRecurrenceRule $rule): CashflowRecurrenceRule
    {
        $rule->requested_at = now();
        $rule->requested_by = $user->id;
        $rule->decided_at = null;
        $rule->decided_by = null;
        $rule->decision_note = null;
        /* The revision clears with the answer it was about: this ask is a new
           question, and the old answer — and the fact that the rule had moved
           under it — no longer describes what is on the table. */
        $rule->revised_at = null;
        $rule->save();

        return $rule;
    }

    /**
     * Approve: the rule starts running and its plan is written down.
     *
     * The plan is built here — in the same call, through its own writer — so
     * there is no moment in which a rule is `active` and has no dates, and no
     * second door that could start a rule without planning it.
     */
    public function approve(User $user, CashflowRecurrenceRule $rule, ?string $note = null): CashflowRecurrenceRule
    {
        /* The guard under the controller's: a controller is one caller away from
           being replaced, and "active without a decision" is the failure this
           module exists to make impossible. */
        if (! $rule->isDraft() || $rule->requested_at === null) {
            throw new RuntimeException('Only a rule that has been sent for approval can be approved.');
        }

        $rule->status = RecurrenceVocabulary::STATUS_ACTIVE;
        $rule->decided_at = now();
        $rule->decided_by = $user->id;
        $rule->decision_note = $this->note($note);
        $rule->paused_at = null;
        $rule->save();

        $this->plan->plan($rule);

        return $rule;
    }

    /** Send it back to its author with a reason, and back into the draft pile. */
    public function sendBack(User $user, CashflowRecurrenceRule $rule, ?string $note = null): CashflowRecurrenceRule
    {
        $rule->status = RecurrenceVocabulary::STATUS_DRAFT;
        $rule->requested_at = null;
        $rule->requested_by = null;
        $rule->decided_at = now();
        $rule->decided_by = $user->id;
        $rule->decision_note = $this->note($note);
        $rule->save();

        return $rule;
    }

    /** Stop asking. The plan's undecided tail is withdrawn; history stays. */
    public function pause(CashflowRecurrenceRule $rule): CashflowRecurrenceRule
    {
        $rule->status = RecurrenceVocabulary::STATUS_PAUSED;
        $rule->paused_at = now();
        $rule->save();

        $this->plan->withdraw($rule);

        return $rule;
    }

    /** Start asking again — from today, on the rule's own anchor day. */
    public function resume(CashflowRecurrenceRule $rule): CashflowRecurrenceRule
    {
        $rule->status = RecurrenceVocabulary::STATUS_ACTIVE;
        $rule->paused_at = null;
        $rule->save();

        $this->plan->plan($rule);

        return $rule;
    }

    /** Over for good: by hand, or because the window ran out. */
    public function end(CashflowRecurrenceRule $rule, ?string $note = null): CashflowRecurrenceRule
    {
        $rule->status = RecurrenceVocabulary::STATUS_ENDED;
        $rule->ended_at = now();

        if ($note !== null) {
            $rule->decision_note = $this->note($note);
        }

        $rule->save();

        $this->plan->withdraw($rule);

        return $rule;
    }

    /**
     * Throw a rule away — any rule, in any state.
     *
     * This used to be a draft's privilege, on the argument that the occurrences
     * are the story of standing payments and a record does not lose a payment
     * because a rule was tidied up. The office asked for the delete and the
     * argument is answerable, because **the ledger is not in this method**:
     *
     *   - the entries the rule posted are money that moved. They keep their
     *     amount, their date, their account and their narration — the entry says
     *     which rule wrote it, in words, and its balance is untouched. What goes
     *     is the *plan*, which is the thing a person is deliberately deleting;
     *   - the occurrences go by cascade, decisions included. Their answers
     *     survive where it matters: the entry carries who approved it
     *     (`created_by`) and the occurrence's number in its narration, and the
     *     rule's decisions are what the reader asked to be rid of;
     *   - **the asks go too.** The office was told these dates needed an answer,
     *     and an alert whose button opens a rule that no longer exists is worse
     *     than no alert: it is a bell that cannot be answered. `OfficeBriefing`
     *     owns alerts, so it takes them back.
     *
     * In a transaction, because a half-deleted rule — its asks gone, its dates
     * standing — would go on asking for money nobody can approve.
     */
    public function delete(CashflowRecurrenceRule $rule): void
    {
        DB::transaction(function () use ($rule) {
            $this->briefing->withdrawRecurringRule($rule);

            $rule->occurrences()->delete();
            $rule->delete();
        });
    }

    /**
     * The daily sweep: every running rule is topped up, the dates that are
     * asking today are announced, and a rule whose last date has been decided
     * ends itself.
     *
     * It is idempotent, which is what lets two doors call it — the scheduled
     * command in the morning, and the module's own page (the same way the office
     * briefing runs itself when nobody's cron has) — without the office getting
     * two emails about one salary. `notified_at` carries that promise.
     *
     * @return Collection<int, CashflowRecurrenceOccurrence>
     */
    public function release(?CarbonInterface $today = null): Collection
    {
        $today = $today ?: Carbon::today();
        $notified = collect();

        CashflowRecurrenceRule::query()
            ->where('status', RecurrenceVocabulary::STATUS_ACTIVE)
            ->orderBy('id')
            ->chunkById(50, function ($rules) use ($today, &$notified) {
                foreach ($rules as $rule) {
                    $this->plan->plan($rule, $today);
                    $notified = $notified->merge($this->plan->notifyDue($rule, $today));

                    if ($this->plan->isFinished($rule, $today)) {
                        $this->end($rule, 'The last date on this rule’s plan has been decided.');
                    }
                }
            });

        return $notified;
    }

    /* ------------------------------------------------------------------ filling */

    /**
     * The one place a rule's fields are set from facts.
     *
     * `array_key_exists` rather than `??`: a field the form sent as empty is a
     * field the person cleared, and "cleared" and "not mentioned" are different
     * things — the first empties the column, the second leaves it alone.
     */
    private function fill(CashflowRecurrenceRule $rule, array $facts): void
    {
        foreach ([
            'title' => fn ($value) => trim((string) $value),
            'particular' => fn ($value) => trim((string) $value),
            'transaction_type' => fn ($value) => $value === 'credit' ? 'credit' : 'debit',
            'amount' => fn ($value) => round((float) $value, 2),
            'currency' => fn ($value) => strtoupper((string) $value) ?: 'INR',
            'account_id' => fn ($value) => $value ?: null,
            'category_id' => fn ($value) => $value ?: null,
            'payment_mode' => fn ($value) => $value ?: null,
            'expense_head' => fn ($value) => $this->text($value),
            'related_party_type' => fn ($value) => $value ?: 'other',
            'related_party_name' => fn ($value) => $this->text($value),
            'client_id' => fn ($value) => $value ?: null,
            'vendor_id' => fn ($value) => $value ?: null,
            'employee_id' => fn ($value) => $value ?: null,
            'office_service_id' => fn ($value) => $value ?: null,
            'notes' => fn ($value) => $this->text($value),
            'frequency' => fn ($value) => RecurrenceVocabulary::hasFrequency($value) ? $value : 'monthly',
            'starts_on' => fn ($value) => $value,
            'ends_on' => fn ($value) => $value ?: null,
            'occurrence_limit' => fn ($value) => $value ? max(1, (int) $value) : null,
        ] as $field => $cast) {
            if (array_key_exists($field, $facts)) {
                $rule->{$field} = $cast($facts[$field]);
            }
        }
    }

    /** An empty string is no value, not a value made of spaces. */
    private function text($value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function note($note): ?string
    {
        return $this->text($note);
    }
}
