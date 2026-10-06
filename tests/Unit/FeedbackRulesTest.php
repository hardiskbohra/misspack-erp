<?php

namespace Tests\Unit;

use App\Models\FeedbackResponse;
use App\Services\FeedbackVocabulary;
use Tests\TestCase;

/**
 * The two questions the feedback module answers in more than one place.
 *
 * `tools/checks/feedback-check.cjs` reads the source and asserts that the band
 * is computed rather than stored, and that the SQL which finds detractors names
 * the same constants as the rule. Neither of those is enough on its own: a
 * source check cannot tell a correct expression from an off-by-one in it, and a
 * test of `band()` cannot tell whether the office's "needs attention" queue
 * agrees with it. So this file asks both, of the running code:
 *
 *   1. **the verdict** — what a score means, including the line-at-2 case that
 *      makes a generous answer a detractor;
 *   2. **the SQL that finds the same answers** — the bindings of
 *      `scopeDetractors()` are the constants the rule uses, so the queue and the
 *      number can never drift apart without this failing.
 *
 * And it pins the one figure that is easiest to fake: NPS refuses to return a
 * number when nobody has answered, because zero from no data reads exactly like
 * zero from bad data.
 *
 * Run it with:  php artisan test --filter=FeedbackRulesTest
 *
 * No database is touched: the scope is turned into SQL and read back
 * (`toSql()` + `getBindings()`), never executed.
 */
class FeedbackRulesTest extends TestCase
{
    /** A generous answer with one weak line is still a call worth making. */
    public function test_a_single_low_line_makes_the_whole_answer_a_detractor(): void
    {
        $band = FeedbackVocabulary::band(5, 10, [5, 5, 2, 5, 5, 5]);

        $this->assertSame(FeedbackVocabulary::BAND_DETRACTOR, $band);
    }

    public function test_a_low_overall_or_a_low_recommendation_is_a_detractor(): void
    {
        $this->assertSame(FeedbackVocabulary::BAND_DETRACTOR, FeedbackVocabulary::band(2, 10, [5, 5]));
        $this->assertSame(FeedbackVocabulary::BAND_DETRACTOR, FeedbackVocabulary::band(5, 6, [5, 5]));
    }

    public function test_a_promoter_needs_an_excellent_answer_with_no_weak_line(): void
    {
        $this->assertSame(FeedbackVocabulary::BAND_PROMOTER, FeedbackVocabulary::band(5, 10, [5, 4, 5]));
        $this->assertSame(FeedbackVocabulary::BAND_PROMOTER, FeedbackVocabulary::band(4, 9, [4, 4, 5]));

        /* one line at 3 is a satisfied client, not an advocate */
        $this->assertSame(FeedbackVocabulary::BAND_PASSIVE, FeedbackVocabulary::band(5, 10, [5, 3, 5]));
    }

    /** A 3 that says something is worth more than a 5 that says nothing. */
    public function test_everything_in_between_is_passive(): void
    {
        $this->assertSame(FeedbackVocabulary::BAND_PASSIVE, FeedbackVocabulary::band(3, 8, [3, 3, 4]));
        $this->assertSame(FeedbackVocabulary::BAND_PASSIVE, FeedbackVocabulary::band(4, 8, [4, 4]));
    }

    /** Blank lines are not zeros: a form scored on two lines judges on two lines. */
    public function test_a_dimension_left_blank_is_not_a_low_score(): void
    {
        $this->assertSame(FeedbackVocabulary::BAND_PROMOTER, FeedbackVocabulary::band(5, 10, [5, null, 5]));
        $this->assertSame(FeedbackVocabulary::BAND_PASSIVE, FeedbackVocabulary::band(5, 10, []));
    }

    /**
     * The queue and the rule are one rule.
     *
     * `scopeDetractors()` binds the same two constants `band()` compares
     * against; if somebody changes one without the other, the office's "needs
     * attention" count and the verdict beside it disagree, and this fails.
     */
    public function test_the_sql_that_finds_detractors_uses_the_rule_s_own_numbers(): void
    {
        $query = FeedbackResponse::query()->detractors();

        $this->assertContains(
            FeedbackVocabulary::DETRACTOR_NPS,
            $query->getBindings(),
            'the detractor scope must bind the NPS threshold the rule uses'
        );

        $this->assertContains(
            FeedbackVocabulary::DETRACTOR_OVERALL,
            $query->getBindings(),
            'the detractor scope must bind the overall threshold the rule uses'
        );

        $this->assertContains(
            FeedbackVocabulary::DETRACTOR_DIMENSION,
            $query->getBindings(),
            'a low line must be a detractor in SQL as well as in PHP'
        );
    }

    public function test_the_needs_attention_queue_is_the_detractors_nobody_has_closed(): void
    {
        $query = FeedbackResponse::query()->needsAttention();

        $this->assertStringContainsString('not exists', strtolower($query->toSql()));
        $this->assertContains(FeedbackVocabulary::DETRACTOR_NPS, $query->getBindings());
    }

    /**
     * NPS is promoters minus detractors — and nothing at all from no answers.
     */
    public function test_nps_is_promoters_minus_detractors(): void
    {
        $this->assertSame(100, FeedbackVocabulary::nps([10, 9, 10, 9]));
        $this->assertSame(-100, FeedbackVocabulary::nps([0, 3, 6]));
        $this->assertSame(0, FeedbackVocabulary::nps([10, 0]));
        $this->assertSame(50, FeedbackVocabulary::nps([10, 5])); // one promoter, one passive
    }

    public function test_nps_refuses_to_invent_a_number_from_no_answers(): void
    {
        $this->assertNull(FeedbackVocabulary::nps([]));
        $this->assertNull(FeedbackVocabulary::nps([null, '']));
    }

    public function test_the_words_a_score_wears(): void
    {
        $this->assertSame('Excellent', FeedbackVocabulary::scoreLabel(5));
        $this->assertSame('Poor', FeedbackVocabulary::scoreLabel(1));
        $this->assertSame('—', FeedbackVocabulary::scoreLabel(null));
        $this->assertSame('Promoter', FeedbackVocabulary::npsLabel(9));
        $this->assertSame('Passive', FeedbackVocabulary::npsLabel(8));
        $this->assertSame('Detractor', FeedbackVocabulary::npsLabel(6));
        $this->assertSame('No answers yet', FeedbackVocabulary::averageLabel(null));
    }

    /** The public form says the same thing in both of its doors. */
    public function test_the_intro_depends_on_what_is_being_asked(): void
    {
        $closeOut = FeedbackVocabulary::formIntro(\App\Models\FeedbackRequest::KIND_CLOSE_OUT);
        $pulse = FeedbackVocabulary::formIntro(\App\Models\FeedbackRequest::KIND_PULSE);

        $this->assertNotSame($closeOut, $pulse);
        $this->assertStringContainsString('part-way', $pulse);
    }
}
