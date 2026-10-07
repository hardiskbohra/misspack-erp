<?php

namespace App\Http\Controllers;

use App\Models\CashflowEntry;
use App\Models\CashflowRecurrenceOccurrence;
use App\Models\CashflowRecurrenceRule;
use App\Services\CashflowPickers;
use App\Services\RecurrenceFigures;
use App\Services\RecurrenceFilters;
use App\Services\RecurrenceIntake;
use App\Services\RecurrencePlan;
use App\Services\RecurrenceVocabulary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * Recurring cashflow — the standing payments, and the office's yes.
 *
 * The module has one idea and every screen is a view of it: **a rule is not a
 * payment**. A rule is written as a draft, argued about, approved once, and from
 * then on it asks — one date at a time — for the money to actually move. The
 * ask happens on the occurrence's effective date and nowhere earlier, because a
 * payment approved a month before it is due is a payment nobody is thinking
 * about; the notification goes out on the day, and the answer belongs to the
 * same moment.
 *
 * The controller is deliberately thin. Every write goes through
 * `RecurrenceIntake` (the rule's state) or `RecurrencePlan` (the dates and the
 * decisions), which is where the rules live: what may be edited when, why a skip
 * is allowed in advance and an approval is not, and what pausing does to a plan.
 * Here there is validation, a guard that produces a sentence instead of a stack
 * trace, and a redirect.
 */
class CashflowRecurrenceController extends Controller
{
    public function __construct(
        private RecurrenceFilters $filters,
        private RecurrenceFigures $figures,
        private RecurrenceIntake $intake,
        private RecurrencePlan $plan,
        private CashflowPickers $pickers,
    ) {
    }

    /* ------------------------------------------------------------- the working page */

    public function index(Request $request): View
    {
        /* The day's round runs here as well as from the command, because a
           schedule is the one thing an office cannot rely on having: the same
           idempotent sweep (`notified_at` is its marker) means the page shows
           the truth whether cron ran or not. It happens before the reads, so the
           page is describing the state after the sweep, not before it. */
        $this->intake->release();

        $filters = $this->filters->fromRequest($request);

        /* **One query.** The figures, the chip counts and the rules table are
           this builder or a clone of it; the to-do list below is the same
           *filter object* applied to the rule each ask belongs to. One
           definition of the filters, four readouts, and a builder that is
           never a page — the page is taken by the filters themselves. */
        $query = $this->filters->apply(
            CashflowRecurrenceRule::query()->with(['account', 'client', 'vendor', 'employee', 'officeService']),
            $filters
        );

        $figures = $this->figures->summary($query);

        /* The chip counts are the one reading that needs its own builder: they
           are counted with the state chip lifted, or each chip would report on
           the state it is standing in rather than the state it offers. */
        $stateCounts = $this->figures->stateCounts(
            $this->filters->apply(CashflowRecurrenceRule::query(), $this->filters->withoutState($filters))
        );

        $rules = $this->filters->page($query, $filters);

        /* The day's asks. These are the same rules the filters leave — a to-do
           list that ignored the chips would be a second page inside this one —
           read from their plans rather than from the rules table, with the
           filters applied to the rule each ask belongs to.
           Not as `rule_id in (select … from the rules query)`: the rules query
           is this page's builder, `paginate()` writes its LIMIT on the builder
           it is called on, and MySQL refuses a LIMIT inside an IN subquery
           (error 1235) — which the SQLite the tests run on allows and the
           office's MySQL does not, so that shape is a 500 no test can see. A
           read that cannot borrow a builder cannot inherit its LIMIT. */
        $due = CashflowRecurrenceOccurrence::query()
            ->dueBy(now())
            ->whereHas('rule', fn (Builder $rule) => $this->filters->apply($rule, $filters))
            ->with(['rule.account', 'rule.client', 'rule.vendor', 'rule.employee', 'rule.officeService', 'decider'])
            ->planOrder()
            ->limit(RecurrenceVocabulary::DUE_LIMIT)
            ->get();

        $applied = $this->filters->applied($filters);

        return view('cashflows.recurring.index', array_merge($this->sharedData(), [
            'rules' => $rules,
            'due' => $due,
            'figures' => $figures,
            'stateCounts' => $stateCounts,
            'stateOptions' => RecurrenceVocabulary::STATE_LABELS,
            'applied' => $applied,
            'filtered' => $applied !== [],
            'dueLimit' => RecurrenceVocabulary::DUE_LIMIT,
            ...$filters,
        ]));
    }

    /* ------------------------------------------------------------------- the rule */

    /** A rule's own page: its plan, its decisions and its facts. */
    public function show(Request $request, CashflowRecurrenceRule $recurrence): View
    {
        $recurrence->load(['account', 'category', 'client', 'vendor', 'employee', 'officeService', 'creator', 'requester', 'decider']);

        $occurrences = $recurrence->occurrences()
            ->with(['entry', 'decider'])
            ->planOrder()
            ->paginate(24)
            ->withQueryString();

        return view('cashflows.recurring.show', array_merge($this->sharedData(), [
            'rule' => $recurrence,
            'occurrences' => $occurrences,
            'released' => $recurrence->releasedCount(),
            'planned' => $recurrence->plannedCount(),
            'nextDate' => $recurrence->nextDate(),
            /* What approval would write down. A draft has no plan yet, so the
               page shows the dates it would hold — computed by the same
               calculator that will write them. */
            'preview' => $recurrence->isDraft() ? $this->plan->preview($recurrence) : [],
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $rule = $this->intake->create($request->user(), $this->facts($request));

        return redirect()
            ->route('cashflows.recurring.show', $rule)
            ->with('success', 'Draft saved. It posts nothing at all until the office approves it.');
    }

    /**
     * Change a rule — any rule, in any state.
     *
     * The plan half of this (what happens to the dates nobody has answered) is
     * the intake's, and so is the rule that a decision is never touched. The
     * controller's job is the sentence: an edit to a running rule is a different
     * act from an edit to a draft, and the office should read which one it just
     * did.
     */
    public function update(Request $request, CashflowRecurrenceRule $recurrence): RedirectResponse
    {
        $wasRunning = $recurrence->isActive();

        $this->intake->update($recurrence, $this->facts($request));

        return redirect()
            ->route('cashflows.recurring.show', $recurrence)
            ->with('success', $wasRunning
                ? 'Rule changed — the dates nobody had answered were re-planned around it, and what was approved or skipped stayed as it was.'
                : 'Rule changed.');
    }

    /**
     * Ask for approval — the one thing a draft can do besides being edited.
     *
     * A rule is approved **from the ask**, never from nowhere: the queue on the
     * index is rules in this state, and "who approved this" always has an answer
     * that starts with somebody asking.
     */
    public function requestApproval(Request $request, CashflowRecurrenceRule $recurrence): RedirectResponse
    {
        if (! $recurrence->isDraft()) {
            return back()->with('error', 'Only a draft asks for approval.');
        }

        if ($recurrence->isWaitingApproval()) {
            return back()->with('error', 'This rule is already waiting for an answer.');
        }

        $this->intake->requestApproval($request->user(), $recurrence);

        return back()->with('success', 'Sent for approval — it is in the office queue now.');
    }

    public function approve(Request $request, CashflowRecurrenceRule $recurrence): RedirectResponse
    {
        if (! $recurrence->isWaitingApproval()) {
            return back()->with('error', 'Approve a rule that has been sent for approval.');
        }

        $this->intake->approve($request->user(), $recurrence, $this->note($request));

        return back()->with('success', 'Approved — the plan is written and every date will ask on its day.');
    }

    public function sendBack(Request $request, CashflowRecurrenceRule $recurrence): RedirectResponse
    {
        if (! $recurrence->isWaitingApproval()) {
            return back()->with('error', 'Only a rule waiting for an answer can be sent back.');
        }

        $note = $request->validate([
            'decision_note' => ['required', 'string', 'max:'.RecurrenceVocabulary::DECISION_NOTE_LIMIT],
        ])['decision_note'];

        $this->intake->sendBack($request->user(), $recurrence, $note);

        return back()->with('success', 'Sent back — the author will read your reason.');
    }

    public function pause(CashflowRecurrenceRule $recurrence): RedirectResponse
    {
        if (! $recurrence->isActive()) {
            return back()->with('error', 'Only a running rule is paused.');
        }

        $this->intake->pause($recurrence);

        return back()->with('success', 'Paused — the undecided dates were withdrawn and the history stayed.');
    }

    public function resume(CashflowRecurrenceRule $recurrence): RedirectResponse
    {
        if (! $recurrence->isPaused()) {
            return back()->with('error', 'Only a paused rule is resumed.');
        }

        $this->intake->resume($recurrence);

        return back()->with('success', 'Running again — the plan starts from today, on this rule’s own day.');
    }

    public function end(Request $request, CashflowRecurrenceRule $recurrence): RedirectResponse
    {
        if ($recurrence->isEnded()) {
            return back()->with('error', 'This rule has already ended.');
        }

        $this->intake->end($recurrence, $this->note($request));

        return back()->with('success', 'Ended. What it asked and what was decided stays on the record.');
    }

    /**
     * Delete a rule — any rule. The ledger is not part of this.
     *
     * The confirmation the office reads before it commits is the model's own
     * sentence (`deleteWarning()`), so the list's row menu and this page warn the
     * same way. What the controller adds afterwards is the count that actually
     * happened: how many entries stay in the ledger because they are money.
     */
    public function destroy(CashflowRecurrenceRule $recurrence): RedirectResponse
    {
        $posted = $recurrence->releasedCount();

        $this->intake->delete($recurrence);

        return redirect()
            ->route('cashflows.recurring.index')
            ->with('success', $posted > 0
                ? 'Rule deleted — its dates, decisions and asks went with it. The entries it posted stay in the ledger: that money moved.'
                : 'Rule deleted. Nothing was ever posted from it.');
    }

    /* -------------------------------------------------------------- one date */

    /**
     * Yes — this date's money moves, and the ledger entry posts in the same
     * breath (see `RecurrencePlan::approve()`).
     */
    public function approveOccurrence(Request $request, CashflowRecurrenceRule $recurrence, string $occurrence): RedirectResponse
    {
        $row = $this->occurrence($recurrence, $occurrence);

        try {
            $this->plan->approve($request->user(), $row, $this->note($request));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Approved — the entry is in the ledger, waiting for the bank to agree.');
    }

    /** No — this date is not paid; the rule keeps running and keeps its day. */
    public function skipOccurrence(Request $request, CashflowRecurrenceRule $recurrence, string $occurrence): RedirectResponse
    {
        $row = $this->occurrence($recurrence, $occurrence);

        try {
            $this->plan->skip($request->user(), $row, $this->note($request));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Skipped for this date only — the next one stands.');
    }

    /* ------------------------------------------------------------- the private */

    /**
     * The only way to reach one date: an id, looked up **inside the rule**.
     *
     * The route hands this a string, never a bound model, so an occurrence id
     * from another rule is a 404 rather than a decision made on somebody else's
     * plan. (A payment is not private the way a note is — the office may see
     * every rule — but a URL that says "occurrence 41" must not decide the
     * occurrence 41 of a rule the reader was not looking at.)
     */
    private function occurrence(CashflowRecurrenceRule $rule, string $id): CashflowRecurrenceOccurrence
    {
        return $rule->occurrences()->findOrFail($id);
    }

    /** The facts a form carries, checked — and nothing else reaches a rule. */
    private function facts(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:'.RecurrenceVocabulary::TITLE_LIMIT],
            'particular' => ['required', 'string', 'max:'.RecurrenceVocabulary::PARTICULAR_LIMIT],
            'transaction_type' => ['required', Rule::in(array_keys(CashflowEntry::transactionTypeOptions()))],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', Rule::in(array_keys(CashflowEntry::currencyOptions()))],
            'account_id' => ['required', 'exists:cashflow_accounts,id'],
            'category_id' => ['nullable', 'exists:cashflow_categories,id'],
            'payment_mode' => ['nullable', Rule::in(array_keys(CashflowEntry::paymentModeOptions()))],
            'expense_head' => ['nullable', 'string', 'max:'.RecurrenceVocabulary::EXPENSE_HEAD_LIMIT],
            'related_party_type' => ['required', Rule::in(array_keys(CashflowEntry::relatedPartyOptions()))],
            'related_party_name' => ['nullable', 'string', 'max:'.RecurrenceVocabulary::NAME_LIMIT],
            'client_id' => ['nullable', 'integer'],
            'vendor_id' => ['nullable', 'integer'],
            'employee_id' => ['nullable', 'integer', 'exists:users,id'],
            'office_service_id' => ['nullable', 'integer', 'exists:office_services,id'],
            'notes' => ['nullable', 'string', 'max:'.RecurrenceVocabulary::NOTES_LIMIT],
            'frequency' => ['required', Rule::in(array_keys(RecurrenceVocabulary::FREQUENCIES))],
            'starts_on' => ['required', 'date'],
            /* The window closes one way or the other, and the end date cannot
               come before the first one — the schedule would simply be empty. */
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'occurrence_limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);
    }

    /** An optional reason, trimmed; empty is no reason rather than an empty one. */
    private function note(Request $request): ?string
    {
        $note = trim((string) $request->input('decision_note', ''));

        return $note === '' ? null : mb_substr($note, 0, RecurrenceVocabulary::DECISION_NOTE_LIMIT);
    }

    /**
     * The lists the rule form is filled from — the module's own pickers, so a
     * rule offers exactly the accounts, categories and people an entry does.
     *
     * @return array<string, mixed>
     */
    private function sharedData(): array
    {
        return array_merge($this->pickers->shared(), [
            'frequencyOptions' => RecurrenceVocabulary::FREQUENCIES,
            'windowOptions' => RecurrenceFilters::WINDOW_LABELS,
            'sortOptions' => RecurrenceFilters::SORT_LABELS,
        ]);
    }
}
