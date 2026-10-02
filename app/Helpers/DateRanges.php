<?php

namespace App\Helpers;

use Illuminate\Support\Carbon;

/**
 * The listing screens' quick date ranges.
 *
 * One definition of "this month / last month / this year / last year" for the
 * whole app. A list needs four things to agree — the chip label, the range it
 * links to, the count printed on it and whether it is the range that is
 * currently applied — and if each module computes those itself they drift: the
 * chip says "Last month" while the query means the month before last, or the
 * chip never lights up because one side compares dates and the other compares
 * timestamps.
 *
 * The ranges are resolved in the business timezone, so "this month" is the
 * month the office is in and not the one the server's clock is in.
 */
class DateRanges
{
    /**
     * The keys in render order, with the label each chip wears.
     *
     * @var array<string, string>
     */
    public const LABELS = [
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'this_year' => 'This year',
        'last_year' => 'Last year',
    ];

    /**
     * Every preset as a closed from/to pair of Y-m-d strings.
     *
     * @return array<string, array{from: string, to: string}>
     */
    public static function presets(?Carbon $today = null): array
    {
        $today = $today ?: static::today();
        $startOfMonth = $today->copy()->startOfMonth();
        $thisYear = $today->copy()->startOfYear();

        return [
            'this_month' => [
                'from' => $startOfMonth->toDateString(),
                'to' => $startOfMonth->copy()->endOfMonth()->toDateString(),
            ],
            'last_month' => [
                'from' => $startOfMonth->copy()->subMonthNoOverflow()->toDateString(),
                'to' => $startOfMonth->copy()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ],
            'this_year' => [
                'from' => $thisYear->toDateString(),
                'to' => $thisYear->copy()->endOfYear()->toDateString(),
            ],
            'last_year' => [
                'from' => $thisYear->copy()->subYear()->toDateString(),
                'to' => $thisYear->copy()->subYear()->endOfYear()->toDateString(),
            ],
        ];
    }

    /**
     * Which preset the applied range is — null when the user typed their own
     * dates. Both ends must match: half a month is not "Last month".
     */
    public static function keyOf(?string $from, ?string $to, ?Carbon $today = null): ?string
    {
        if (! $from || ! $to) {
            return null;
        }

        foreach (static::presets($today) as $key => $range) {
            if ($from === $range['from'] && $to === $range['to']) {
                return $key;
            }
        }

        return null;
    }

    /** The label for a key, or null when the key is not a preset. */
    public static function label(?string $key): ?string
    {
        return $key === null ? null : (static::LABELS[$key] ?? null);
    }

    /** The office's today, not the server's. */
    public static function today(): Carbon
    {
        return Carbon::now(config('app.business_timezone', 'Asia/Kolkata'));
    }
}
