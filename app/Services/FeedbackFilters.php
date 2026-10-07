<?php

namespace App\Services;

use App\Helpers\DateRanges;
use App\Models\FeedbackRequest;
use App\Models\FeedbackResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Every dimension the feedback list can be filtered by, in one place.
 *
 * The list, the chips that say what is on, the figures above it and the CSV the
 * office takes to a review all have to mean the same thing by "detractor" and by
 * "never opened it". A filter is exactly where that agreement breaks, so the
 * keys, the words and the defaults live here and the SQL is asked of the models'
 * own scopes rather than re-written per screen.
 *
 * The band words are the vocabulary's — a filter may say `attention`, but it
 * means what `FeedbackResponse::scopeNeedsAttention()` means, which means what
 * `FeedbackVocabulary::band()` means.
 */
class FeedbackFilters
{
    /** The filter keys, and what an unset one means. */
    public const DEFAULTS = [
        'q' => null,
        'state' => 'all',      // request state: live | opened | answered | expired | revoked
        'kind' => 'all',       // close_out | pulse
        'band' => 'all',       // promoter | passive | detractor | attention
        'client' => 0,
        'project' => 0,
        'date_from' => null,
        'date_to' => null,
    ];

    /** The chip labels, in the order the screen reads them. */
    public const LABELS = [
        'q' => 'Search',
        'state' => 'Status',
        'kind' => 'Ask',
        'band' => 'Score',
        'client' => 'Client',
        'project' => 'Project',
    ];

    /** A range: two query keys, one chip. */
    public const RANGE_LABELS = [
        'period' => ['label' => 'Period', 'keys' => ['date_from', 'date_to']],
    ];

    public const BAND_LABELS = [
        'promoter' => 'Promoters',
        'passive' => 'Passive',
        'detractor' => 'Detractors',
        'attention' => 'Needs attention',
    ];

    private const STATES = [
        FeedbackRequest::STATE_LIVE,
        FeedbackRequest::STATE_OPENED,
        FeedbackRequest::STATE_ANSWERED,
        FeedbackRequest::STATE_EXPIRED,
        FeedbackRequest::STATE_REVOKED,
    ];

    /**
     * The filters a request carries, every value checked against what the screen
     * offers: a hand-typed `?band=garbage` is "all", not a query that quietly
     * returns nothing.
     *
     * @return array<string, mixed>
     */
    public function fromRequest(Request $request): array
    {
        $state = (string) $request->query('state', 'all');
        if ($state !== 'all' && ! in_array($state, self::STATES, true)) {
            $state = 'all';
        }

        $kind = (string) $request->query('kind', 'all');
        if ($kind !== 'all' && ! array_key_exists($kind, FeedbackRequest::kindOptions())) {
            $kind = 'all';
        }

        $band = (string) $request->query('band', 'all');
        if ($band !== 'all' && ! array_key_exists($band, self::BAND_LABELS)) {
            $band = 'all';
        }

        return [
            'q' => trim((string) $request->query('q', '')) ?: null,
            'state' => $state,
            'kind' => $kind,
            'band' => $band,
            'client' => max(0, (int) $request->query('client', 0)),
            'project' => max(0, (int) $request->query('project', 0)),
            'date_from' => DateRanges::normalise($request->query('date_from')),
            'date_to' => DateRanges::normalise($request->query('date_to')),
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
     * The chips the "Filtered by" strip wears: one filter, the query keys
     * removing it drops, and the value in the office's own words.
     *
     * @return array<int, array{key: string, label: string, value: string, query: array<int, string>}>
     */
    public function applied(array $filters): array
    {
        $applied = [];

        if (($filters['q'] ?? null) !== null) {
            $applied[] = ['key' => 'q', 'label' => 'Search', 'value' => $filters['q'], 'query' => ['q']];
        }

        if (($filters['state'] ?? 'all') !== 'all') {
            $applied[] = ['key' => 'state', 'label' => 'Status', 'value' => Str::headline($filters['state']), 'query' => ['state']];
        }

        if (($filters['kind'] ?? 'all') !== 'all') {
            $applied[] = ['key' => 'kind', 'label' => 'Ask', 'value' => FeedbackRequest::kindOptions()[$filters['kind']] ?? $filters['kind'], 'query' => ['kind']];
        }

        if (($filters['band'] ?? 'all') !== 'all') {
            $applied[] = ['key' => 'band', 'label' => 'Score', 'value' => self::BAND_LABELS[$filters['band']] ?? $filters['band'], 'query' => ['band']];
        }

        if (($filters['client'] ?? 0) > 0) {
            $applied[] = ['key' => 'client', 'label' => 'Client', 'value' => 'Selected client', 'query' => ['client']];
        }

        if (($filters['project'] ?? 0) > 0) {
            $applied[] = ['key' => 'project', 'label' => 'Project', 'value' => 'Selected project', 'query' => ['project']];
        }

        if (($filters['date_from'] ?? null) !== null || ($filters['date_to'] ?? null) !== null) {
            $applied[] = [
                'key' => 'period',
                'label' => 'Period',
                'value' => trim(($filters['date_from'] ?? '').' – '.($filters['date_to'] ?? ''), ' –'),
                'query' => ['date_from', 'date_to'],
            ];
        }

        return $applied;
    }

    /* ----------------------------------------------------------------- queries */

    /** The asks, filtered. This is the list: one row per link. */
    public function applyToRequests(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['q'] ?? null, fn (Builder $q, $search) => $q->search($search))
            ->when(($filters['state'] ?? 'all') !== 'all', fn (Builder $q) => $q->state($filters['state']))
            ->when(($filters['kind'] ?? 'all') !== 'all', fn (Builder $q) => $q->kind($filters['kind']))
            ->when(($filters['client'] ?? 0) > 0, fn (Builder $q) => $q->where('client_id', $filters['client']))
            ->when(($filters['project'] ?? 0) > 0, fn (Builder $q) => $q->forProject($filters['project']))
            ->when(($filters['band'] ?? 'all') !== 'all', fn (Builder $q) => $q->band($filters['band']))
            ->when($filters['date_from'] ?? null, fn (Builder $q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $to) => $q->whereDate('created_at', '<=', $to));
    }

    /** The answers, filtered the same way. The figures and the CSV read this. */
    public function applyToResponses(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['q'] ?? null, fn (Builder $q, $search) => $q->search($search))
            ->when(($filters['kind'] ?? 'all') !== 'all', fn (Builder $q) => $q->kind($filters['kind']))
            ->when(($filters['client'] ?? 0) > 0, fn (Builder $q) => $q->forClient($filters['client']))
            ->when(($filters['project'] ?? 0) > 0, fn (Builder $q) => $q->forProject($filters['project']))
            ->when(($filters['band'] ?? 'all') === 'detractor', fn (Builder $q) => $q->detractors())
            ->when(($filters['band'] ?? 'all') === 'promoter', fn (Builder $q) => $q->promoters())
            ->when(($filters['band'] ?? 'all') === 'attention', fn (Builder $q) => $q->needsAttention())
            ->when(($filters['band'] ?? 'all') === 'passive', fn (Builder $q) => $q->whereNotIn('id', FeedbackResponse::query()->detractors()->select('id'))
                ->whereNotIn('id', FeedbackResponse::query()->promoters()->select('id')))
            ->when($filters['date_from'] ?? null, fn (Builder $q, $from) => $q->whereDate('submitted_at', '>=', $from))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $to) => $q->whereDate('submitted_at', '<=', $to));
    }
}
