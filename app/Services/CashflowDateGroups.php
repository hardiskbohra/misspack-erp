<?php

namespace App\Services;

use App\Helpers\DateRanges;
use Carbon\CarbonInterface;

/** Mutually exclusive calendar groups for the cashflow ledger's date dividers. */
class CashflowDateGroups
{
    private string $today;
    private string $yesterday;
    private string $twoDaysAgo;
    private string $thisWeek;
    private string $lastWeek;
    private string $thisMonth;
    private string $lastMonth;

    public function __construct(?CarbonInterface $today = null)
    {
        // Capture one office day for the whole page, not a new clock per row.
        $today = ($today ?: DateRanges::today())->copy()->startOfDay();
        $week = $today->copy()->startOfWeek(CarbonInterface::MONDAY);
        $month = $today->copy()->startOfMonth();

        $this->today = $today->toDateString();
        $this->yesterday = $today->copy()->subDay()->toDateString();
        $this->twoDaysAgo = $today->copy()->subDays(2)->toDateString();
        $this->thisWeek = $week->toDateString();
        $this->lastWeek = $week->copy()->subWeek()->toDateString();
        $this->thisMonth = $month->toDateString();
        $this->lastMonth = $month->copy()->subMonthNoOverflow()->toDateString();
    }

    public function label(?CarbonInterface $date): string
    {
        if (! $date) {
            return 'Undated';
        }

        $day = $date->toDateString();

        // Future-dated entries must not interrupt or duplicate a past group.
        if ($day > $this->today) return 'Upcoming';
        if ($day === $this->today) return 'Today';
        if ($day === $this->yesterday) return 'Yesterday';
        if ($day === $this->twoDaysAgo) return '2 days ago';
        if ($day >= $this->thisWeek) return 'This week';
        if ($day >= $this->lastWeek) return 'Last week';
        if ($day >= $this->thisMonth) return 'This month';
        if ($day >= $this->lastMonth) return 'Last month';

        return 'Older entries';
    }
}
