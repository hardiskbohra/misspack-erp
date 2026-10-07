<?php

namespace App\Services;

use App\Models\CashflowRecurrenceOccurrence;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * The numbers above the recurring-rules page, and the counts on its chips.
 *
 * Two readings, each **one grouped query** rather than a query per number:
 *
 *   - `summary()` — the rules the filters leave, split by the state the chips
 *     offer, plus the plan-side numbers the day is actually worked from: what is
 *     due for approval now, what is late, and what the next thirty days hold;
 *   - `stateCounts()` — the same row, read against a builder with the state chip
 *     lifted, so each chip prints the number you would get by clicking it.
 *
 * The plan-side numbers are deliberately cut from the **rules the filters
 * leave** (`whereIn` a subquery of that same builder) rather than from the whole
 * table: a figure is a caption for the page under it, and a caption that counts
 * something the page is not showing is a second, unrelated scoreboard. That is
 * also why an overdue row is measured against the office's today and not the
 * server's.
 */
class RecurrenceFigures
{
    /**
     * @return array{total: int, approval: int, drafts: int, running: int, paused: int, ended: int,
     *               due: int, overdue: int, soon: int, planned: int}
     */
    public function summary(Builder $rules, ?CarbonInterface $today = null): array
    {
        $today = $today ?: now();
        $row = $this->row($rules);

        $plan = $this->planRow($rules, $today);

        return [
            'total' => (int) $row->total,
            'approval' => (int) $row->approval,
            'drafts' => (int) $row->drafts,
            'running' => (int) $row->running,
            'paused' => (int) $row->paused,
            'ended' => (int) $row->ended,
            'due' => (int) $plan->due,
            'overdue' => (int) $plan->overdue,
            'soon' => (int) $plan->soon,
            'planned' => (int) $plan->planned,
        ];
    }

    /**
     * What each state chip would give you, keyed by the chip's value.
     *
     * Read with `RecurrenceFilters::withoutState()` so the answer is the
     * question the chip asks. The mapping is the same row read six ways — the
     * check asserts these keys against the vocabulary's own state list, because
     * a chip whose key is missing here would print a silent zero.
     *
     * @return array<string, int>
     */
    public function stateCounts(Builder $rules): array
    {
        $row = $this->row($rules);

        return [
            'everything' => (int) $row->total,
            'approval' => (int) $row->approval,
            'drafts' => (int) $row->drafts,
            'running' => (int) $row->running,
            'paused' => (int) $row->paused,
            'ended' => (int) $row->ended,
        ];
    }

    /** The one row of sums the rule-side readings are cut from. */
    private function row(Builder $rules): object
    {
        return (clone $rules)
            ->reorder()
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when status = 'draft' and requested_at is not null then 1 else 0 end) as approval")
            ->selectRaw("sum(case when status = 'draft' and requested_at is null then 1 else 0 end) as drafts")
            ->selectRaw("sum(case when status = 'active' then 1 else 0 end) as running")
            ->selectRaw("sum(case when status = 'paused' then 1 else 0 end) as paused")
            ->selectRaw("sum(case when status = 'ended' then 1 else 0 end) as ended")
            ->first();
    }

    /**
     * The plan-side numbers for the same rules: what is asking now, what is
     * late, what the next thirty days hold, and how big the plan is.
     *
     * One query, with the rule set carried in as a subquery — the occurrences of
     * the rules the page is showing, not of every rule in the office.
     */
    private function planRow(Builder $rules, CarbonInterface $today): object
    {
        $ids = (clone $rules)->reorder()->select('id');

        return CashflowRecurrenceOccurrence::query()
            ->whereIn('cashflow_recurrence_rule_id', $ids)
            ->selectRaw("sum(case when status = 'pending' and effective_date <= ? then 1 else 0 end) as due", [$today->toDateString()])
            ->selectRaw("sum(case when status = 'pending' and effective_date < ? then 1 else 0 end) as overdue", [$today->toDateString()])
            ->selectRaw("sum(case when status = 'pending' and effective_date <= ? then 1 else 0 end) as soon", [$today->copy()->addDays(30)->toDateString()])
            ->selectRaw("sum(case when status = 'pending' then 1 else 0 end) as planned")
            ->first();
    }
}
