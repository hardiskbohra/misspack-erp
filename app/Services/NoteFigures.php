<?php

namespace App\Services;

use App\Helpers\DateRanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * The four numbers above the notes page, and the counts on its chips.
 *
 * All of it comes out of **one grouped query** per reading, not one query per
 * number. The reason is not only the cost: four separate counts are four
 * chances to count a different set — "Needs attention" once read 9 while its
 * own queue listed 11, because one of them had a join the other forgot. A
 * single row of sums cannot disagree with itself.
 *
 * The figures describe the notes *the filters leave* — that is what makes them
 * a caption for the page rather than a second, unrelated scoreboard. The chip
 * counts deliberately break that rule for exactly one filter: see
 * `NoteFilters::withoutState()`.
 */
class NoteFigures
{
    /**
     * What the current filters leave: total, pinned, on the desk, filed, and
     * touched this week.
     *
     * @return array{total: int, pinned: int, desk: int, filed: int, week: int}
     */
    public function summary(Builder $query): array
    {
        $row = $this->row($query);

        return [
            'total' => (int) $row->total,
            'pinned' => (int) $row->pinned,
            'desk' => (int) $row->desk,
            'filed' => (int) $row->filed,
            'week' => (int) $row->week,
        ];
    }

    /**
     * What each state chip would give you, keyed by the chip's value.
     *
     * Read with `NoteFilters::withoutState()`, so each count answers the
     * question the chip actually asks: "how many would I see if I clicked this".
     *
     * @return array<string, int>
     */
    public function stateCounts(Builder $query): array
    {
        $row = $this->row($query);

        return [
            'desk' => (int) $row->desk,
            'pinned' => (int) $row->pinned,
            'filed' => (int) $row->filed,
            'everything' => (int) $row->total,
        ];
    }

    /**
     * The one row of sums both readings are cut from.
     *
     * Cloned, and de-ordered: a figure is a fact about a set of rows, and the
     * order the reader happens to be reading them in is not part of it.
     */
    private function row(Builder $query): object
    {
        return (clone $query)
            ->reorder()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when is_pinned = 1 and archived_at is null then 1 else 0 end) as pinned')
            ->selectRaw('sum(case when archived_at is null then 1 else 0 end) as desk')
            ->selectRaw('sum(case when archived_at is not null then 1 else 0 end) as filed')
            ->selectRaw('sum(case when updated_at >= ? then 1 else 0 end) as week', [$this->weekStart()])
            ->first();
    }

    /** This week started on Monday, in the office's timezone, not the server's. */
    private function weekStart(): Carbon
    {
        return DateRanges::today()->copy()->startOfWeek();
    }
}
