<?php

namespace Tests\Unit;

use App\Services\RecurrenceSchedule;
use App\Services\RecurrenceVocabulary;
use Carbon\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * The one thing about this module that a source check cannot prove: **the
 * calendar**.
 *
 * `tools/checks/recurring-check.cjs` asserts that there is one calculator and
 * that the plan and the preview both call it. What it cannot see is whether the
 * arithmetic is right, and the arithmetic is where a standing payment goes wrong
 * quietly: a rent rule anchored on the 31st that pays on the 28th of *every*
 * month afterwards, a quarterly bill that drifts a day a year, a salary that
 * misses February in a leap year. Those are not crashes — they are amounts of
 * money, found months later, on a bank statement.
 *
 * So the cases below are the ones a business actually hits:
 *
 *   - the 31st, and the months that do not have one;
 *   - 29 February, and the three years that do not have one;
 *   - a window closed by a **count**, by a **date**, by both, and by neither;
 *   - the day after a pause — where the ladder restarts on the rule's own
 *     anchor day rather than paying three months of back-dated asks.
 *
 * Run it with:  php artisan test --filter=RecurrenceScheduleTest
 *
 * Nothing here touches the database: `RecurrenceSchedule` is carbon arithmetic
 * and nothing else, which is exactly why it can be tested this closely.
 */
class RecurrenceScheduleTest extends TestCase
{
    private RecurrenceSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schedule = new RecurrenceSchedule();
    }

    /** The dates as strings, so a failure reads like the list it is. */
    private function days(array $dates): array
    {
        return array_map(fn ($date) => $date->toDateString(), $dates);
    }

    /* ------------------------------------------------- the anchor day survives */

    /**
     * The classic bug this class exists to prevent: 31 January + one month is
     * 28 February, and February + one month is **28 March** only if you stepped
     * from the clamped date. It must be 31 March.
     */
    public function test_a_rule_on_the_31st_pays_the_last_day_when_there_is_no_31st(): void
    {
        $dates = $this->schedule->dates('monthly', Carbon::parse('2027-01-31'), null, null, 5);

        $this->assertSame([
            '2027-01-31',
            '2027-02-28',
            '2027-03-31',
            '2027-04-30',
            '2027-05-31',
        ], $this->days($dates));
    }

    public function test_the_same_rule_in_a_leap_year_pays_the_29th(): void
    {
        $dates = $this->schedule->dates('monthly', Carbon::parse('2028-01-31'), null, null, 3);

        $this->assertSame(['2028-01-31', '2028-02-29', '2028-03-31'], $this->days($dates));
    }

    /** A yearly rule on 29 February keeps the 29th when the year has one. */
    public function test_a_leap_day_rule_clamps_and_then_finds_its_day_again(): void
    {
        $dates = $this->schedule->dates('yearly', Carbon::parse('2028-02-29'), null, null, 5);

        $this->assertSame([
            '2028-02-29',
            '2029-02-28',
            '2030-02-28',
            '2031-02-28',
            '2032-02-29',
        ], $this->days($dates));
    }

    public function test_quarterly_and_half_yearly_are_the_same_rule_three_and_six_months_out(): void
    {
        $quarterly = $this->schedule->dates('quarterly', Carbon::parse('2026-11-30'), null, null, 3);
        $halfYearly = $this->schedule->dates('half_yearly', Carbon::parse('2026-11-30'), null, null, 3);

        $this->assertSame(['2026-11-30', '2027-02-28', '2027-05-30'], $this->days($quarterly));
        $this->assertSame(['2026-11-30', '2027-05-30', '2027-11-30'], $this->days($halfYearly));
    }

    public function test_daily_and_weekly_are_days_and_weeks(): void
    {
        $daily = $this->schedule->dates('daily', Carbon::parse('2026-10-07'), null, null, 3);
        $weekly = $this->schedule->dates('weekly', Carbon::parse('2026-10-07'), null, null, 3);

        $this->assertSame(['2026-10-07', '2026-10-08', '2026-10-09'], $this->days($daily));
        $this->assertSame(['2026-10-07', '2026-10-14', '2026-10-21'], $this->days($weekly));
    }

    /* -------------------------------------------------------- the two windows */

    public function test_a_count_stops_the_ladder_exactly_at_the_count(): void
    {
        $dates = $this->schedule->dates('monthly', Carbon::parse('2026-10-05'), null, 3, 24);

        $this->assertCount(3, $dates);
        $this->assertSame('2026-12-05', end($dates)->toDateString());
    }

    public function test_an_end_date_stops_the_ladder_inside_the_month(): void
    {
        $dates = $this->schedule->dates('monthly', Carbon::parse('2026-10-05'), Carbon::parse('2026-12-31'), null, 24);

        $this->assertSame(['2026-10-05', '2026-11-05', '2026-12-05'], $this->days($dates));
    }

    /** Both endings at once: whichever comes first wins. */
    public function test_the_end_date_and_the_count_are_each_a_ceiling(): void
    {
        $byCount = $this->schedule->dates('monthly', Carbon::parse('2026-10-05'), Carbon::parse('2027-12-31'), 2, 24);
        $byDate = $this->schedule->dates('monthly', Carbon::parse('2026-10-05'), Carbon::parse('2026-12-01'), 10, 24);

        $this->assertCount(2, $byCount);
        $this->assertCount(3, $byDate);
    }

    /** And neither: the ladder is capped by the window, and the cap is not a rule. */
    public function test_neither_ending_means_the_ladder_runs_to_the_window(): void
    {
        $dates = $this->schedule->dates('monthly', Carbon::parse('2026-10-05'), null, null, RecurrenceVocabulary::PLAN_WINDOW);

        $this->assertCount(RecurrenceVocabulary::PLAN_WINDOW, $dates);
    }

    public function test_an_end_date_before_the_start_is_an_empty_plan(): void
    {
        $dates = $this->schedule->dates('monthly', Carbon::parse('2026-10-05'), Carbon::parse('2026-10-04'), null, 24);

        $this->assertSame([], $dates);
    }

    public function test_the_first_date_of_a_plan_is_the_effective_date(): void
    {
        $dates = $this->schedule->dates('weekly', Carbon::parse('2026-10-07'), null, 4, 24);

        $this->assertSame('2026-10-07', $dates[1]->toDateString());
    }

    /* ------------------------------------------------------------ waking up */

    /**
     * A rule paused across a quarter resumes on its own day of the month, not on
     * the day the office happened to press the button — and it does not owe the
     * dates it slept through.
     */
    public function test_a_paused_rule_resumes_on_its_anchor_day_not_the_day_it_was_resumed(): void
    {
        $next = $this->schedule->firstOnOrAfter('monthly', Carbon::parse('2026-01-05'), Carbon::parse('2026-04-17'));

        $this->assertSame('2026-05-05', $next->toDateString());
    }

    public function test_a_rule_resumed_on_its_own_day_asks_that_day(): void
    {
        $next = $this->schedule->firstOnOrAfter('monthly', Carbon::parse('2026-01-05'), Carbon::parse('2026-04-05'));

        $this->assertSame('2026-04-05', $next->toDateString());
    }

    public function test_a_daily_rule_resumed_after_a_gap_starts_today(): void
    {
        $next = $this->schedule->firstOnOrAfter('daily', Carbon::parse('2026-01-05'), Carbon::parse('2026-04-17'));

        $this->assertSame('2026-04-17', $next->toDateString());
    }

    /** A rule approved before its effective date plans from that date, not today. */
    public function test_a_future_effective_date_is_where_the_plan_starts(): void
    {
        $next = $this->schedule->firstOnOrAfter('monthly', Carbon::parse('2026-12-05'), Carbon::parse('2026-10-07'));

        $this->assertSame('2026-12-05', $next->toDateString());
    }

    /* ------------------------------------------------------------- the vocabulary */

    public function test_every_rhythm_the_form_offers_is_a_rhythm_the_calendar_knows(): void
    {
        foreach (array_keys(RecurrenceVocabulary::FREQUENCIES) as $frequency) {
            $dates = $this->schedule->dates($frequency, Carbon::parse('2026-10-07'), null, 2, 24);

            $this->assertCount(2, $dates, 'the schedule has no case for '.$frequency);
            $this->assertTrue(
                $dates[2]->toDateString() > $dates[1]->toDateString(),
                $frequency.' did not move forward',
            );
        }
    }

    public function test_an_unknown_rhythm_is_an_error_rather_than_a_silent_default(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->schedule->step(Carbon::parse('2026-10-07'), 'fortnightly', 7);
    }

    public function test_the_default_ladder_is_step_repeated(): void
    {
        $plain = $this->schedule->dates('monthly', Carbon::parse('2026-01-31'), null, 4, 24);
        $stepped = [
            Carbon::parse('2026-01-31'),
            $this->schedule->step(Carbon::parse('2026-01-31'), 'monthly', 31),
        ];

        $this->assertSame($stepped[1]->toDateString(), $plain[2]->toDateString());
    }
}
