<?php

namespace App\Services;

use App\Helpers\DateRanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Everything the notes page can be narrowed by, in one place.
 *
 * The board, the table, the four figures above them and the counts printed on
 * the chips all have to mean the same thing by "pinned" and by "this week". A
 * filter is exactly where that agreement breaks, so the keys, the words and the
 * defaults live here, the SQL is asked of the model's own scopes rather than
 * re-written per screen, and there is **one** `apply()` — the board and the
 * table are two readouts of the same query, not two queries that look alike.
 *
 * Two criteria live on the chip bar (the state of the note, its colour) and two
 * in the drawer (the period, the order). Nothing is asked twice: a criterion
 * with a chip of its own is not printed again in the strip, because a reader
 * who can see the answer should not have to clear it from two places.
 */
class NoteFilters
{
    /** The filter keys, and what an unset one means. */
    public const DEFAULTS = [
        'q' => null,
        'state' => 'desk',      // desk | pinned | filed | everything
        'colour' => 'all',      // a NoteVocabulary key, or all
        'period' => 'any',      // any | today | week | month
        'sort' => 'edited',     // edited | created | title
    ];

    /** The chip row: the note's own state, in the order the screen reads it. */
    public const STATE_LABELS = [
        'desk' => 'On the desk',
        'pinned' => 'Pinned',
        'filed' => 'Filed away',
        'everything' => 'Everything',
    ];

    /** The drawer: when the note was last touched. */
    public const PERIOD_LABELS = [
        'any' => 'Any time',
        'today' => 'Today',
        'week' => 'This week',
        'month' => 'This month',
    ];

    /** The drawer: which order the table is read in. */
    public const SORT_LABELS = [
        'edited' => 'Recently edited',
        'created' => 'Newest written',
        'title' => 'Title A–Z',
    ];

    /**
     * Each order choice is one of the model's order scopes.
     *
     * The map is the contract: a key here without a `scope<Name>()` on `Note`
     * would be a select that changes nothing, and the check reads both ends.
     */
    public const SORT_SCOPES = [
        'edited' => 'recentFirst',
        'created' => 'newestFirst',
        'title' => 'titleOrder',
    ];

    /** The words the "Filtered by" strip wears. */
    public const LABELS = [
        'period' => 'Edited',
        'sort' => 'Order',
    ];

    /**
     * The filters a request carries, every value checked against what the
     * screen offers: a hand-typed `?state=garbage` is the default, not a query
     * that quietly returns nothing.
     *
     * @return array<string, mixed>
     */
    public function fromRequest(Request $request): array
    {
        $state = (string) $request->query('state', self::DEFAULTS['state']);
        if (! array_key_exists($state, self::STATE_LABELS)) {
            $state = self::DEFAULTS['state'];
        }

        $colour = (string) $request->query('colour', 'all');
        if ($colour !== 'all' && ! NoteVocabulary::hasColour($colour)) {
            $colour = 'all';
        }

        $period = (string) $request->query('period', self::DEFAULTS['period']);
        if (! array_key_exists($period, self::PERIOD_LABELS)) {
            $period = self::DEFAULTS['period'];
        }

        $sort = (string) $request->query('sort', self::DEFAULTS['sort']);
        if (! array_key_exists($sort, self::SORT_LABELS)) {
            $sort = self::DEFAULTS['sort'];
        }

        return [
            'q' => trim((string) $request->query('q', '')) ?: null,
            'state' => $state,
            'colour' => $colour,
            'period' => $period,
            'sort' => $sort,
        ];
    }

    /**
     * The filters that are actually narrowing something, as a query string.
     *
     * @return array<string, string>
     */
    public function toQuery(array $filters): array
    {
        $query = [];

        foreach (self::DEFAULTS as $key => $default) {
            $value = $filters[$key] ?? null;

            if ($value === null || $value === '' || $value === $default) {
                continue;
            }

            $query[$key] = (string) $value;
        }

        return $query;
    }

    /**
     * The same filters, read as if no state chip were selected.
     *
     * The chips print what each one *would* give you, so a chip's own filter is
     * the one filter its count must not carry — otherwise "Filed away" always
     * reads 0 while you are standing on it, and "Pinned" reads the number of
     * pinned notes filed away, which is the one number nobody asked for.
     */
    public function withoutState(array $filters): array
    {
        return array_merge($filters, ['state' => 'everything']);
    }

    /**
     * The chips the "Filtered by" strip wears.
     *
     * Only the drawer's criteria: the state and the colour are on the chip bar
     * already, and the search term is in the box it was typed in. What is
     * visible on the page is not repeated underneath it.
     *
     * @return array<int, array{key: string, label: string, value: string, query: array<int, string>}>
     */
    public function applied(array $filters): array
    {
        $applied = [];

        if (($filters['period'] ?? 'any') !== 'any') {
            $applied[] = [
                'key' => 'period',
                'label' => self::LABELS['period'],
                'value' => self::PERIOD_LABELS[$filters['period']] ?? $filters['period'],
                'query' => ['period'],
            ];
        }

        if (($filters['sort'] ?? 'edited') !== 'edited') {
            $applied[] = [
                'key' => 'sort',
                'label' => self::LABELS['sort'],
                'value' => self::SORT_LABELS[$filters['sort']] ?? $filters['sort'],
                'query' => ['sort'],
            ];
        }

        return $applied;
    }

    /* ------------------------------------------------------------------ queries */

    /**
     * **The** query. Every readout on the page is this builder or a clone of it.
     *
     * It is handed an already-owned builder (`Note::query()->ownedBy(…)`): this
     * class narrows what a person may see, it never decides who that is.
     */
    public function apply(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['q'] ?? null, fn (Builder $q, $search) => $q->search($search))
            ->when(($filters['state'] ?? 'desk') === 'desk', fn (Builder $q) => $q->onTheDesk())
            ->when(($filters['state'] ?? 'desk') === 'pinned', fn (Builder $q) => $q->onTheDesk()->pinned())
            ->when(($filters['state'] ?? 'desk') === 'filed', fn (Builder $q) => $q->filedAway())
            ->when(($filters['colour'] ?? 'all') !== 'all', fn (Builder $q) => $q->inColour($filters['colour']))
            ->when($this->periodMoment($filters['period'] ?? 'any'), fn (Builder $q, $moment) => $q->editedSince($moment));
    }

    /** The order the table is read in — the board keeps its own. */
    public function order(Builder $query, array $filters): Builder
    {
        $scope = self::SORT_SCOPES[$filters['sort'] ?? 'edited'] ?? self::SORT_SCOPES['edited'];

        return $query->{$scope}();
    }

    /**
     * How far back "This week" and "This month" reach, from the office's own
     * today — a desk is in one timezone and the server's clock is not it.
     */
    private function periodMoment(string $period): ?Carbon
    {
        $today = DateRanges::today();

        return match ($period) {
            'today' => $today->copy()->startOfDay(),
            'week' => $today->copy()->startOfWeek(),
            'month' => $today->copy()->startOfMonth(),
            default => null,
        };
    }
}
