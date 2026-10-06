<?php

namespace App\Services;

use App\Models\FeedbackAnswer;
use App\Models\FeedbackRequest;
use App\Models\FeedbackResponse;
use Illuminate\Support\Collection;

/**
 * One set of figures, read the same way by the screen and the spreadsheet.
 *
 * Every number on the feedback page is asked of this class, and the CSV asks the
 * same class for the same rows — because a figure that reads one way on screen
 * and another in the export is worse than no figure at all. Two rules it keeps:
 *
 *   - **NPS is never shown without its denominator.** "NPS 62" from four answers
 *     is a different claim from NPS 62 out of forty, and the label says which.
 *   - **the average is of answers, not of asks.** A 99% response rate that has
 *     not answered yet must not be averaged in as a zero.
 *
 * The detractor count is a count of answers, and the "needs attention" queue is
 * a count of answers nobody has acted on — different numbers on purpose, and
 * both are on the page.
 */
class FeedbackFigures
{
    public function __construct(private FeedbackFilters $filters)
    {
    }

    /* --------------------------------------------------------------- figures */

    /**
     * The row of numbers at the top of the page.
     *
     * @return array{asked:int, answered:int, awaiting:int, response_rate:?float, average:?float, nps:?int, promoters:int, passives:int, detractors:int, attention:int, avg_close_out:?float, avg_pulse:?float}
     */
    public function summary(array $filters): array
    {
        $asked = $this->filters->applyToRequests(FeedbackRequest::query(), $filters)->count();

        $responses = $this->filters->applyToResponses(
            FeedbackResponse::query()->with(['answers', 'actions', 'request']),
            $filters
        )->get();

        $scores = $responses->pluck('nps_score')->all();
        $bands = $responses->map(fn (FeedbackResponse $response) => $response->band());

        $average = $this->average($responses);
        $nps = FeedbackVocabulary::nps($scores);

        return [
            'asked' => $asked,
            'answered' => $responses->count(),
            'awaiting' => max($asked - $responses->count(), 0),
            'response_rate' => $asked > 0 ? round($responses->count() / $asked * 100, 1) : null,
            'average' => $average,
            'nps' => $nps,
            'promoters' => $bands->filter(fn ($band) => $band === FeedbackVocabulary::BAND_PROMOTER)->count(),
            'passives' => $bands->filter(fn ($band) => $band === FeedbackVocabulary::BAND_PASSIVE)->count(),
            'detractors' => $bands->filter(fn ($band) => $band === FeedbackVocabulary::BAND_DETRACTOR)->count(),
            'attention' => $responses->filter(fn (FeedbackResponse $response) => $response->isDetractor() && ! $response->hasOpenAction())->count(),
            'avg_close_out' => $this->average($responses->filter(fn (FeedbackResponse $r) => $r->request?->kind === FeedbackRequest::KIND_CLOSE_OUT)),
            'avg_pulse' => $this->average($responses->filter(fn (FeedbackResponse $r) => $r->request?->kind === FeedbackRequest::KIND_PULSE)),
        ];
    }

    /**
     * The six lines, each with its own average and its own count.
     *
     * This is the report that changes something: a dimension that is below
     * everything else is a process to fix, and it is visible here even in a month
     * where the overall average looks healthy.
     *
     * @return array<int, array{key: string, label: string, hint: string, color: string, average: ?float, count: int, low: int}>
     */
    public function dimensions(array $filters): array
    {
        $responseIds = $this->filters->applyToResponses(FeedbackResponse::query(), $filters)->select('id');

        $rows = FeedbackAnswer::query()
            ->whereIn('feedback_response_id', $responseIds)
            ->get()
            ->groupBy('dimension');

        $report = [];

        foreach (FeedbackVocabulary::dimensions() as $dimension) {
            $answers = $rows->get($dimension['key'], collect());
            $average = $answers->isEmpty() ? null : round((float) $answers->avg('score'), 2);

            $report[] = $dimension + [
                'average' => $average,
                'count' => $answers->count(),
                'low' => $answers->filter(fn (FeedbackAnswer $answer) => $answer->score <= FeedbackVocabulary::DETRACTOR_DIMENSION)->count(),
            ];
        }

        return $report;
    }

    /** The average of the overall rating, of whatever set is handed in. */
    public function average(Collection $responses): ?float
    {
        return $responses->isEmpty() ? null : round((float) $responses->avg('overall_rating'), 2);
    }

    /* ---------------------------------------------------------------- queues */

    /** Answers nobody is doing anything about yet, oldest first. */
    public function needsAttention(int $limit = 25): Collection
    {
        return FeedbackResponse::query()
            ->with(['client', 'project', 'request', 'answers', 'actions.owner'])
            ->needsAttention()
            ->oldest('submitted_at')
            ->limit($limit)
            ->get();
    }

    /** Answers the office may quote, newest first, each with its consented channels. */
    public function quotable(int $limit = 30): Collection
    {
        return FeedbackResponse::query()
            ->with(['client', 'project', 'answers'])
            ->quotable()
            ->whereNotNull('testimonial')
            ->latest('submitted_at')
            ->limit($limit)
            ->get();
    }

    /** A client's own history, newest first — the panel sales opens before quoting. */
    public function clientHistory(int $clientId, int $limit = 20): Collection
    {
        return FeedbackResponse::query()
            ->with(['request', 'project', 'answers'])
            ->forClient($clientId)
            ->latest('submitted_at')
            ->limit($limit)
            ->get();
    }

    /** The average per month for a client or project, for the small trend line. */
    public function monthlyTrend(array $filters, int $months = 6): array
    {
        $from = now()->subMonths($months - 1)->startOfMonth();

        $responses = $this->filters->applyToResponses(FeedbackResponse::query(), $filters)
            ->where('submitted_at', '>=', $from)
            ->get()
            ->groupBy(fn (FeedbackResponse $response) => $response->submitted_at?->format('Y-m'));

        $trend = [];

        for ($i = 0; $i < $months; $i++) {
            $month = now()->subMonths($months - 1 - $i);
            $key = $month->format('Y-m');
            $set = $responses->get($key, collect());

            $trend[] = [
                'key' => $key,
                'label' => $month->format('M'),
                'average' => $set->isEmpty() ? null : round((float) $set->avg('overall_rating'), 2),
                'count' => $set->count(),
            ];
        }

        return $trend;
    }

    /* -------------------------------------------------------------- the list */

    /**
     * The list's query: every ask, with its answer if there is one.
     *
     * Filters on the *answer* (band) narrow the asks by their answer rather than
     * hiding the unanswered ones, so a "Detractors" chip lists the two links
     * whose answers were bad and nothing else.
     */
    public function requestQuery(array $filters)
    {
        return $this->filters
            ->applyToRequests(FeedbackRequest::query()->with(['project', 'client', 'response.answers', 'response.actions', 'creator']), $filters)
            ->latest('id');
    }

    /**
     * The rows of the CSV: one line per answer, with its ask beside it.
     *
     * The same filters as the screen, so "export what I am looking at" means it.
     */
    public function csvRows(array $filters): array
    {
        $responses = $this->filters->applyToResponses(
            FeedbackResponse::query()->with(['client', 'project', 'request', 'answers', 'actions']),
            $filters
        )->latest('submitted_at')->get();

        $dimensionKeys = FeedbackVocabulary::dimensionKeys(false);

        return $responses->map(function (FeedbackResponse $response) use ($dimensionKeys) {
            $scores = $response->answers->pluck('score', 'dimension');

            $dimensionColumns = collect($dimensionKeys)
                ->mapWithKeys(fn ($key) => [$key => $scores->get($key)])
                ->all();

            return [
                'submitted_at' => $response->submitted_at?->format('d M Y H:i'),
                'client' => $response->clientName(),
                'project' => $response->project?->name ?? '—',
                'ask' => $response->request?->kindLabel() ?? '—',
                'respondent' => $response->is_anonymous ? 'Anonymous' : $response->respondent_name,
                'overall_rating' => $response->overall_rating,
                'nps_score' => $response->nps_score,
                'band' => $response->bandLabel(),
                'would_order_again' => $response->wouldOrderAgainLabel(),
                'went_well' => $response->went_well,
                'could_improve' => $response->could_improve,
                'testimonial' => $response->testimonial,
                'consent' => implode(', ', $response->consentChannels()) ?: 'None',
                'open_actions' => $response->actions->filter(fn ($action) => $action->isOpen())->count(),
                ...$dimensionColumns,
            ];
        })->all();
    }
}
