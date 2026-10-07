<?php

namespace App\Services;

use App\Helpers\DateRanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Everything the recurring-rules page can be narrowed by, in one place.
 *
 * The four figures, the chip counts and the table are one query read four ways,
 * so "Running" has to mean the same thing to all of them; a filter is exactly
 * where that agreement breaks. The keys, the words and the defaults live here,
 * the SQL is asked of the model's own scopes, and there is one `apply()`.
 *
 * The chips carry the **state of the rule** (a draft nobody has picked up, a
 * draft waiting on an answer, what runs, what is paused, what has ended) and the
 * drawer carries the slower questions: how often it pays, and whether its plan
 * asks for anything in the next month. Nothing is asked twice — a criterion with
 * a chip of its own is not printed again in the strip.
 */
class RecurrenceFilters
{
    /** The filter keys, and what an unset one means. */
    public const DEFAULTS = [
        'q' => null,
        'state' => 'everything',   // a RecurrenceVocabulary::STATE_LABELS key
        'frequency' => 'all',      // a RecurrenceVocabulary::FREQUENCIES key, or all
        'window' => 'any',         // any | month | quarter — when the plan next asks
        'sort' => 'recent',        // recent | oldest | title | amount
    ];

    /** The drawer: which order the list is read in. */
    public const SORT_LABELS = [
        'recent' => 'Recently written',
        'oldest' => 'Oldest first',
        'title' => 'Title A–Z',
        'amount' => 'Largest amount',
    ];

    /**
     * Each order choice is one of the model's order scopes. The map is the
     * contract: a key here without a `scope<Name>()` on the rule is a select
     * that changes nothing, and `recurring-check` reads both ends.
     */
    public const SORT_SCOPES = [
        'recent' => 'recentFirst',
        'oldest' => 'oldestFirst',
        'title' => 'titleOrder',
        'amount' => 'amountOrder',
    ];

    /** The drawer: how soon the plan asks for something. */
    public const WINDOW_LABELS = [
        'any' => 'Any time',
        'month' => 'Asking in the next 30 days',
        'quarter' => 'Asking in the next 90 days',
    ];

    /** The words the "Filtered by" strip wears. */
    public const LABELS = [
        'frequency' => 'Rhythm',
        'window' => 'Plan',
        'sort' => 'Order',
    ];

    /**
     * The filters a request carries, every value checked against what the screen
     * offers: a hand-typed `?state=garbage` is the default view, not an empty
     * list and not a 500.
     *
     * @return array<string, mixed>
     */
    public function fromRequest(Request $request): array
    {
        $state = (string) $request->query('state', self::DEFAULTS['state']);
        if (! array_key_exists($state, RecurrenceVocabulary::STATE_LABELS)) {
            $state = self::DEFAULTS['state'];
        }

        $frequency = (string) $request->query('frequency', 'all');
        if ($frequency !== 'all' && ! RecurrenceVocabulary::hasFrequency($frequency)) {
            $frequency = 'all';
        }

        $window = (string) $request->query('window', self::DEFAULTS['window']);
        if (! array_key_exists($window, self::WINDOW_LABELS)) {
            $window = self::DEFAULTS['window'];
        }

        $sort = (string) $request->query('sort', self::DEFAULTS['sort']);
        if (! array_key_exists($sort, self::SORT_LABELS)) {
            $sort = self::DEFAULTS['sort'];
        }

        return [
            'q' => trim((string) $request->query('q', '')) ?: null,
            'state' => $state,
            'frequency' => $frequency,
            'window' => $window,
            'sort' => $sort,
        ];
    }

    /** The filters that are actually narrowing something, as a query string. */
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
     * The same filters read as if no state chip were selected.
     *
     * The chips print what each one *would* give you, so a chip's own filter is
     * the one filter its count must not carry — otherwise "Waiting for approval"
     * reads 0 while you stand on it, and "Running" reads the running rules that
     * are also waiting on an answer, which is not a number anybody asked for.
     */
    public function withoutState(array $filters): array
    {
        return array_merge($filters, ['state' => 'everything']);
    }

    /**
     * The chips the "Filtered by" strip wears: the drawer's criteria only. The
     * state is on the chip bar already and the search term is in its box.
     *
     * @return array<int, array{key: string, label: string, value: string, query: array<int, string>}>
     */
    public function applied(array $filters): array
    {
        $applied = [];

        if (($filters['frequency'] ?? 'all') !== 'all') {
            $applied[] = [
                'key' => 'frequency',
                'label' => self::LABELS['frequency'],
                'value' => RecurrenceVocabulary::frequencyLabel($filters['frequency']),
                'query' => ['frequency'],
            ];
        }

        if (($filters['window'] ?? 'any') !== 'any') {
            $applied[] = [
                'key' => 'window',
                'label' => self::LABELS['window'],
                'value' => self::WINDOW_LABELS[$filters['window']] ?? $filters['window'],
                'query' => ['window'],
            ];
        }

        if (($filters['sort'] ?? 'recent') !== 'recent') {
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

    /** **The** query. Every readout on the page is this builder or a clone of it. */
    public function apply(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['q'] ?? null, fn (Builder $q, $search) => $q->search($search))
            ->when(($filters['state'] ?? 'everything') !== 'everything',
                fn (Builder $q) => $q->inState($filters['state']))
            ->when(($filters['frequency'] ?? 'all') !== 'all',
                fn (Builder $q) => $q->inFrequency($filters['frequency']))
            ->when($this->windowEnd($filters['window'] ?? 'any'),
                fn (Builder $q, $end) => $q->askingBetween(DateRanges::today(), $end));
    }

    /** The order the list is read in. */
    public function order(Builder $query, array $filters): Builder
    {
        $scope = self::SORT_SCOPES[$filters['sort'] ?? 'recent'] ?? self::SORT_SCOPES['recent'];

        return $query->{$scope}();
    }

    /**
     * How far ahead "asking in the next 30 days" reaches, from the office's own
     * today — the same clock the plan and the approvals read.
     */
    private function windowEnd(string $window): ?Carbon
    {
        $today = DateRanges::today();

        return match ($window) {
            'month' => $today->copy()->addDays(30),
            'quarter' => $today->copy()->addDays(90),
            default => null,
        };
    }
}
