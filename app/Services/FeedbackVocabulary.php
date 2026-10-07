<?php

namespace App\Services;

use App\Models\FeedbackAnswer;
use App\Models\FeedbackMasterOption;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Every word this module scores with, in one place.
 *
 * Three separate things would otherwise drift apart the moment one of them is
 * tweaked: the form asks six questions, the report groups by six keys, and the
 * "needs attention" queue decides a low score means 2 or less. If the form's
 * threshold and the queue's threshold are written twice, the office gets a
 * response that is a detractor on one screen and a pass on another — and then
 * nobody trusts any of it.
 *
 * So this class owns:
 *
 *   - **the dimensions** — read from `feedback_master_options` when the office
 *     has edited them, and from the built-in six when the table is empty or not
 *     migrated yet. An install with no rows must still render a working form;
 *   - **the bands** — promoter / passive / detractor, from the overall rating,
 *     the NPS score and the dimension scores, as constants and as methods. The
 *     SQL in `FeedbackResponse::scopeDetractors()` expresses the same numbers,
 *     and the check reads both;
 *   - **the words** — what a 4 means, what a 9 is called, what the NPS question
 *     says. The NPS question is asked verbatim or it is not NPS.
 */
class FeedbackVocabulary
{
    public const BAND_PROMOTER = 'promoter';
    public const BAND_PASSIVE = 'passive';
    public const BAND_DETRACTOR = 'detractor';

    /* The thresholds. A detractor is a call worth making; a promoter is a quote
       worth asking for — everything else is a satisfied client and no work. */
    public const DETRACTOR_OVERALL = 2;
    public const DETRACTOR_NPS = 6;
    public const DETRACTOR_DIMENSION = 2;
    public const PROMOTER_OVERALL = 4;
    public const PROMOTER_NPS = 9;
    public const PROMOTER_MIN_DIMENSION = 4;

    /** The words on the scale, so "3" never reads as "three out of ten" on screen. */
    public const SCORE_LABELS = [
        1 => 'Poor',
        2 => 'Below par',
        3 => 'Acceptable',
        4 => 'Good',
        5 => 'Excellent',
    ];

    public const NPS_QUESTION = 'How likely are you to recommend MissPack to another business?';

    public const NPS_ANCHORS = [
        'low' => '0 — Not at all likely',
        'high' => '10 — Extremely likely',
    ];

    /**
     * The six dimensions when the office has not set any.
     *
     * Kept in step with `2026_10_06_010500_seed_default_feedback_dimensions.php`
     * on purpose: the migration is the first draft, this is the safety net.
     */
    public const DEFAULT_DIMENSIONS = [
        ['key' => 'delivery', 'label' => 'On-time delivery', 'hint' => 'Did the goods arrive when we said they would?', 'color' => 'teal'],
        ['key' => 'product_quality', 'label' => 'Product quality', 'hint' => 'Was the packaging itself right — material, strength, finish?', 'color' => 'green'],
        ['key' => 'print_artwork', 'label' => 'Print / artwork accuracy', 'hint' => 'Did the printed artwork match the approved PPS?', 'color' => 'blue'],
        ['key' => 'communication', 'label' => 'Communication & responsiveness', 'hint' => 'Did you hear back quickly, and clearly?', 'color' => 'purple'],
        ['key' => 'packaging_dispatch', 'label' => 'Packaging & dispatch', 'hint' => 'Was the consignment packed, labelled and documented properly?', 'color' => 'orange'],
        ['key' => 'value', 'label' => 'Value for money', 'hint' => 'Was the price fair for what you received?', 'color' => 'red'],
    ];

    /** @var array<int, array<string, mixed>>|null */
    private static ?array $dimensionCache = null;

    /* ----------------------------------------------------------- dimensions */

    /**
     * The scorecard, in the order it is asked.
     *
     * @return array<int, array{key: string, label: string, hint: string, color: string}>
     */
    public static function dimensions(bool $activeOnly = true, bool $fresh = false): array
    {
        if (! $fresh && self::$dimensionCache !== null) {
            return $activeOnly
                ? array_values(array_filter(self::$dimensionCache, fn ($row) => $row['is_active']))
                : self::$dimensionCache;
        }

        $rows = null;

        if (Schema::hasTable('feedback_master_options')) {
            $rows = FeedbackMasterOption::query()
                ->group(FeedbackMasterOption::GROUP_DIMENSION)
                ->ordered()
                ->get()
                ->map(fn (FeedbackMasterOption $option) => [
                    'key' => $option->key,
                    'label' => $option->label,
                    'hint' => (string) ($option->hint ?? ''),
                    'color' => (string) ($option->color ?? 'blue'),
                    'is_active' => (bool) $option->is_active,
                ])
                ->all();
        }

        self::$dimensionCache = $rows ?: array_map(
            fn ($row) => $row + ['is_active' => true],
            self::DEFAULT_DIMENSIONS
        );

        return $activeOnly
            ? array_values(array_filter(self::$dimensionCache, fn ($row) => $row['is_active']))
            : self::$dimensionCache;
    }

    /** The keys an answer may carry — the only dimensions a POST is allowed to score. */
    public static function dimensionKeys(bool $activeOnly = true): array
    {
        return array_column(self::dimensions($activeOnly), 'key');
    }

    /** A key => label pair, for the pickers and the group-bys. */
    public static function dimensionOptions(bool $activeOnly = true): array
    {
        return array_column(self::dimensions($activeOnly), 'label', 'key');
    }

    public static function dimensionLabel(string $key): string
    {
        foreach (self::dimensions(false) as $row) {
            if ($row['key'] === $key) {
                return $row['label'];
            }
        }

        return Str::headline(str_replace('_', ' ', $key));
    }

    public static function dimensionHint(string $key): string
    {
        foreach (self::dimensions(false) as $row) {
            if ($row['key'] === $key) {
                return $row['hint'];
            }
        }

        return '';
    }

    /** Forgets the memoised list — after the settings screen writes a row. */
    public static function flush(): void
    {
        self::$dimensionCache = null;
    }

    /* --------------------------------------------------------------- scoring */

    public static function scoreLabel(?int $score): string
    {
        return self::SCORE_LABELS[$score] ?? '—';
    }

    /** The tone a five-point score wears; the same bands the overall uses. */
    public static function scoreTone(?int $score): string
    {
        return match (true) {
            $score === null => 'grey',
            $score <= self::DETRACTOR_DIMENSION => 'red',
            $score === 3 => 'orange',
            $score === 4 => 'blue',
            default => 'green',
        };
    }

    /**
     * The verdict for one answer.
     *
     * A detractor is not only a client who scored us badly overall: one line at
     * 2 — a print run that arrived wrong, a phone that was never answered — is
     * a problem worth a phone call even when the rest of the form is generous.
     * That is why the dimension scores are part of the rule.
     *
     * @param  array<int, int|null>  $dimensionScores
     */
    public static function band(?int $overall, ?int $nps, array $dimensionScores = []): string
    {
        $scores = array_values(array_filter(
            array_map(fn ($score) => $score === null ? null : (int) $score, $dimensionScores),
            fn ($score) => $score !== null
        ));

        $lowDimension = $scores !== [] && min($scores) <= self::DETRACTOR_DIMENSION;

        if (($overall !== null && $overall <= self::DETRACTOR_OVERALL)
            || ($nps !== null && $nps <= self::DETRACTOR_NPS)
            || $lowDimension) {
            return self::BAND_DETRACTOR;
        }

        $hasWeakLine = $scores !== [] && min($scores) < self::PROMOTER_MIN_DIMENSION;

        if ($overall !== null && $overall >= self::PROMOTER_OVERALL
            && $nps !== null && $nps >= self::PROMOTER_NPS
            && ! $hasWeakLine) {
            return self::BAND_PROMOTER;
        }

        return self::BAND_PASSIVE;
    }

    public static function bandLabel(string $band): string
    {
        return self::bandOptions()[$band] ?? Str::headline($band);
    }

    public static function bandTone(string $band): string
    {
        return [
            self::BAND_PROMOTER => 'green',
            self::BAND_PASSIVE => 'blue',
            self::BAND_DETRACTOR => 'red',
        ][$band] ?? 'grey';
    }

    public static function bandOptions(): array
    {
        return [
            self::BAND_PROMOTER => 'Promoter',
            self::BAND_PASSIVE => 'Passive',
            self::BAND_DETRACTOR => 'Detractor',
        ];
    }

    public static function npsLabel(?int $score): string
    {
        return match (true) {
            $score === null => '—',
            $score >= self::PROMOTER_NPS => 'Promoter',
            $score <= self::DETRACTOR_NPS => 'Detractor',
            default => 'Passive',
        };
    }

    /**
     * NPS: promoters minus detractors, as a percentage of everyone who answered.
     *
     * `null` when nobody has answered. Returning zero would put a number and a
     * decimal point on a page with no data behind it, which is how a report
     * starts lying quietly.
     *
     * @param  array<int, int>  $npsScores
     */
    public static function nps(array $npsScores): ?int
    {
        $scores = array_values(array_filter($npsScores, fn ($score) => $score !== null && $score !== ''));

        if ($scores === []) {
            return null;
        }

        $promoters = count(array_filter($scores, fn ($score) => (int) $score >= self::PROMOTER_NPS));
        $detractors = count(array_filter($scores, fn ($score) => (int) $score <= self::DETRACTOR_NPS));

        return (int) round(($promoters - $detractors) / count($scores) * 100);
    }

    /**
     * The sentence under an average, so a figure is never read as a verdict.
     * A 4.2 built from six answers is not the same claim as one built from sixty.
     */
    public static function averageLabel(?float $average): string
    {
        return match (true) {
            $average === null => 'No answers yet',
            $average >= 4.5 => 'Excellent',
            $average >= 3.5 => 'Good',
            $average >= 2.5 => 'Mixed',
            default => 'Poor',
        };
    }

    /* ------------------------------------------------------------ the form */

    public static function wouldOrderAgainOptions(): array
    {
        return [
            'yes' => 'Yes, happily',
            'maybe' => 'Maybe — depends',
            'no' => 'No',
        ];
    }

    public static function consentOptions(): array
    {
        return [
            'publish_website' => 'Our website',
            'publish_social' => 'Social media',
            'publish_sales' => 'Quotes and proposals',
            'publish_case_study' => 'A case study',
        ];
    }

    /** The intro the form opens with — one wording, public page and portal alike. */
    public static function formIntro(string $kind): string
    {
        return $kind === \App\Models\FeedbackRequest::KIND_PULSE
            ? 'We are part-way through your project. Two minutes now would let us fix anything that is slipping before it reaches you.'
            : 'The project is done. Two minutes of honesty now is worth more to us than any compliment later.';
    }

    /** How many dimensions a submitted form must have scored. */
    public static function minimumAnswers(): int
    {
        return max(1, (int) ceil(count(self::dimensions()) / 2));
    }

    /** The dimensions an answer covered, with their labels — for the office view. */
    public static function answerMap(FeedbackAnswer ...$answers): array
    {
        $map = [];

        foreach ($answers as $answer) {
            $map[$answer->dimension] = $answer;
        }

        return $map;
    }
}
