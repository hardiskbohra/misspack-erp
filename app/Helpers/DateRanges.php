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
     * A filter value that is a date — or nothing at all.
     *
     * A query string is written by hand, by links, and by saved views, and a
     * value that names a *range* ("all", "custom", "this_month") is not a date.
     * Handing that value to Carbon::parse() throws an InvalidFormatException and
     * takes the whole page down with a 500 — over a filter. So a value the app
     * cannot read as a date is read as no date: the filter widens, which is the
     * rule the rest of the filter vocabulary already follows. One owner, because
     * every screen reads its dates out of the query string.
     */
    public static function normalise($value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'Y/m/d', 'd-m-Y', 'd/m/Y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $value);
            } catch (\Throwable) {
                /* Carbon is strict and throws on a value that is not this shape
                   at all — which is the whole point of reading it here instead
                   of at the call site: a filter can never take a page down. */
                continue;
            }

            /* The round trip is the real test. createFromFormat() is happy to
               read "2026" as a date of nothing, and "0000-00-00" as a day that
               does not exist; only a value that survives being written back in
               the same format is a date this app meant. */
            if ($date->format($format) === $value) {
                return $date->toDateString();
            }
        }

        return null;
    }

    /** The same value, written the way the module writes a date. */
    public static function display($value, string $fallback = ''): string
    {
        $date = static::normalise($value);

        return $date ? Carbon::parse($date)->format('d M Y') : $fallback;
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
