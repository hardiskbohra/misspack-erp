<?php

namespace Tests\Unit;

use App\Services\CashflowDateGroups;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class CashflowDateGroupsTest extends TestCase
{
    public function test_leap_day_groups_use_calendar_boundaries_without_mutating_the_clock(): void
    {
        $today = Carbon::parse('2024-03-01 23:55:00', 'Asia/Kolkata');
        $groups = new CashflowDateGroups($today);
        $this->assertSame('2024-03-01 23:55:00', $today->format('Y-m-d H:i:s'));
        foreach ([
            '2024-03-02' => 'Upcoming', '2024-03-01' => 'Today', '2024-02-29' => 'Yesterday',
            '2024-02-28' => '2 days ago', '2024-02-26' => 'This week', '2024-02-25' => 'Last week',
            '2024-02-19' => 'Last week', '2024-02-18' => 'Last month', '2024-02-01' => 'Last month',
            '2024-01-31' => 'Older entries',
        ] as $date => $label) {
            $this->assertSame($label, $groups->label(CarbonImmutable::parse($date)), $date);
        }
        $this->assertSame('Undated', $groups->label(null));
    }

    public function test_groups_remain_contiguous_in_date_order_across_calendar_boundaries(): void
    {
        $rank = array_flip(['Upcoming', 'Today', 'Yesterday', '2 days ago', 'This week', 'Last week',
            'This month', 'Last month', 'Older entries']);
        foreach (['2024-03-01', '2026-02-01', '2026-10-05', '2026-11-30', '2027-01-04'] as $date) {
            $today = CarbonImmutable::parse($date, 'Asia/Kolkata');
            $groups = new CashflowDateGroups($today);
            $previous = -1;
            for ($offset = 3; $offset >= -75; $offset--) {
                $day = $today->addDays($offset);
                $current = $rank[$groups->label($day)];
                $this->assertGreaterThanOrEqual($previous, $current, 'Date '.$day->toDateString().' for today '.$date);
                $previous = $current;
            }
        }
    }
}
