<?php

namespace App\Services;

use App\Models\FixedAsset;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * The depreciation schedule — the one calculator, and the reason the register
 * stores a recipe instead of a number.
 *
 * A private limited company depreciates by class, over a useful life, by a
 * method, down to a residual value — and the *yearly* figure is what the profit
 * and loss statement and the tax computation both want. So the unit here is the
 * **financial year** (1 April – 31 March), and everything else is a slice of it:
 *
 *   - `schedule()` — the whole curve, one row per financial year the asset was
 *     on the books, from the year it was bought to the year its life ran out or
 *     it was sold;
 *   - `chargeForYear()` — one row of that curve, by key (`2025-26`);
 *   - `accumulatedAt()` / `netBookValueAt()` — the curve read up to a date. The
 *     current year's charge is **pro-rated to that date**, so an asset is never
 *     shown as if the year had closed when it has not: this is what "the books
 *     closed today" means, and it is what an auditor recomputes for a mid-year
 *     balance sheet;
 *   - and the company's year — opening, the year's charge, additions, disposals,
 *     closing — is the sum of those per-asset readings, assembled in
 *     `AssetFigures::yearReport()` so the report's total row is provably the sum
 *     of the rows printed under it.
 *
 * Four rules the maths follows, each of them a decision rather than an accident:
 *
 *   - **the first and last years are pro-rated by days.** An asset bought on 10
 *     October is not given twelve months of depreciation in a year it was held
 *     for six, and one sold in July stops in July. The denominator is the days
 *     in that financial year, so a leap year is not a rounding error;
 *   - **the last year absorbs the rounding**, so the curve lands exactly on the
 *     residual value. Without it a 3-year schedule ends at ₹4,999.87 of a
 *     ₹5,000 residual and an auditor has to ask why;
 *   - **the curved method is anchored, not guessed.** WDV applies the rate that
 *     brings cost down to the residual *in exactly the useful life*
 *     (`1 − (residual/cost)^(1/life)`), which is the Schedule II reading: the
 *     life is the office's decision, not a side effect of the rate;
 *   - **a disposal stops the curve, and the asset leaves the books.** The year of
 *     sale is charged up to the date of sale (the same convention), and from the
 *     day after it the net book value is **zero** — because the balance sheet no
 *     longer holds it. What it fetched is compared against the value on the day
 *     it left (`FixedAsset::bookValueOnDisposal()`), which is where the profit or
 *     loss on sale comes from.
 *
 * Nothing here reads a stored accumulated figure, because there is not one. Every
 * reading in this class is a function of the asset's own columns, which is what
 * makes "the NBV column was right on the day it was typed" impossible.
 */
class AssetDepreciation
{
    /* ------------------------------------------------------- the financial year */

    /**
     * The financial year a date falls in — 1 April to 31 March, the Indian year
     * the returns are filed by.
     *
     * @return array{key: string, label: string, from: Carbon, to: Carbon}
     */
    public function financialYearOf(?CarbonInterface $date = null): array
    {
        $date = ($date ?: Carbon::today())->copy()->startOfDay();
        $startMonth = AssetVocabulary::FINANCIAL_YEAR_START_MONTH;

        $from = $date->month >= $startMonth
            ? $date->copy()->setDate($date->year, $startMonth, 1)
            : $date->copy()->setDate($date->year - 1, $startMonth, 1);

        $to = $from->copy()->addYear()->subDay();

        return [
            'key' => $this->key($from),
            'label' => 'FY '.$this->label($from),
            'from' => $from,
            'to' => $to,
        ];
    }

    public function currentKey(): string
    {
        return $this->financialYearOf()['key'];
    }

    /** The bounds of a year the screens name — `2025-26` → 1 Apr 2025 … 31 Mar 2026. */
    public function bounds(string $key): ?array
    {
        if (! preg_match('/^(\d{4})-(\d{2})$/', $key, $matches)) {
            return null;
        }

        $from = Carbon::create((int) $matches[1], AssetVocabulary::FINANCIAL_YEAR_START_MONTH, 1)->startOfDay();
        $to = $from->copy()->addYear()->subDay();

        if ($this->key($from) !== $key) {
            return null;
        }

        return ['key' => $key, 'label' => 'FY '.$this->label($from), 'from' => $from, 'to' => $to];
    }

    /**
     * The years the report can be asked for: this one and back to the earliest
     * asset the company owns, so the selector never offers a year with nothing
     * in it and never hides one that matters.
     *
     * @return array<string, string>
     */
    public function yearOptions(?CarbonInterface $earliest = null): array
    {
        $first = $this->financialYearOf($earliest ?: Carbon::today())['from'];
        $last = $this->financialYearOf()['from'];
        $options = [];

        /* Backwards from this year: a company's first year of assets is where the
           list stops, and anything older than a decade is a mistake in a date. */
        for ($cursor = $last; $cursor->greaterThanOrEqualTo($first) && count($options) < 30; $cursor = $cursor->copy()->subYear()) {
            $options[$this->key($cursor)] = 'FY '.$this->label($cursor);
        }

        return $options;
    }

    private function key(CarbonInterface $financialYearStart): string
    {
        return $financialYearStart->year.'-'.substr((string) ($financialYearStart->year + 1), -2);
    }

    private function label(CarbonInterface $financialYearStart): string
    {
        return $financialYearStart->year.'-'.substr((string) ($financialYearStart->year + 1), -2);
    }

    /* -------------------------------------------------------------- the schedule */

    /**
     * Every financial year this asset was depreciated in, oldest first.
     *
     * Each row carries the year, the dates it was held within that year, the
     * opening value, the year's charge and the closing value — which is why the
     * register screen can show a real schedule and not a summary: the rows are
     * the same curve the totals are read from.
     *
     * @return list<array{
     *     key: string, label: string, from: Carbon, to: Carbon,
     *     days: int, days_in_year: int, opening: float, charge: float, closing: float,
     *     is_first: bool, is_last: bool
     * }>
     */
    public function schedule(FixedAsset $asset): array
    {
        $basis = $asset->capitalisedCost();

        /* No date, no curve: an asset with no purchase date is an incomplete
           record, not an asset that has been owned since the beginning of time. */
        if (! $asset->depreciates() || $basis <= 0 || ! $asset->purchase_date) {
            return [];
        }

        $method = $asset->effectiveMethod();
        $life = (int) $asset->effectiveLifeYears();
        $residual = round($basis * $asset->effectiveResidualPercent() / 100, 2);
        $depreciable = round($basis - $residual, 2);

        $start = $asset->purchase_date->copy()->startOfDay();
        $end = $start->copy()->addYears($life);

        /* A disposal inside the life stops the curve there: the year of sale is
           charged up to the day it left, and nothing after it belongs to us. */
        $disposed = $asset->disposal_date && $asset->disposal_date->lt($end);
        if ($disposed) {
            $end = $asset->disposal_date->copy()->startOfDay();
        }

        $rows = [];
        $cursor = $this->financialYearOf($start);
        $opening = round($basis, 2);

        while (true) {
            $year = $this->financialYearOf($cursor['from']);
            $from = $year['from']->greaterThan($start) ? $year['from']->copy() : $start->copy();
            $to = $year['to']->lessThan($end) ? $year['to']->copy() : $end->copy();

            $days = (int) $from->diffInDays($to) + 1;
            $daysInYear = (int) $year['from']->diffInDays($year['to']) + 1;
            $isLast = $to->greaterThanOrEqualTo($end);

            $charge = $this->charge($method, $opening, $basis, $depreciable, $residual, $life, $days, $daysInYear);

            /* The last row lands on the residual exactly. Everything before it is
               the method's own arithmetic, pro-rated; this line is the rule that
               the curve ends where the office said it would. */
            if ($isLast) {
                $charge = round($opening - $residual, 2);
            }

            $charge = max(0.0, min($charge, round($opening - $residual, 2)));
            $closing = round($opening - $charge, 2);

            $rows[] = [
                'key' => $year['key'],
                'label' => $year['label'],
                'from' => $from,
                'to' => $to,
                'days' => $days,
                'days_in_year' => $daysInYear,
                'opening' => $opening,
                'charge' => $charge,
                'closing' => $closing,
                'is_first' => $from->equalTo($start),
                'is_last' => $isLast,
            ];

            if ($isLast) {
                break;
            }

            $opening = $closing;
            $cursor = $this->financialYearOf($year['to']->copy()->addDay());
        }

        return $rows;
    }

    /** One year's charge, or zero for a year the asset was not on the books. */
    public function chargeForYear(FixedAsset $asset, string $financialYear): float
    {
        foreach ($this->schedule($asset) as $row) {
            if ($row['key'] === $financialYear) {
                return round($row['charge'], 2);
            }
        }

        return 0.0;
    }

    /**
     * What the books hold as depreciation up to a date — complete years in full,
     * and the year in progress pro-rated to that date.
     *
     * This is the reading that has no column: cost, life, method and a date go
     * in, and a figure comes out that is true of *that* date and no other.
     */
    public function accumulatedAt(FixedAsset $asset, ?CarbonInterface $on = null): float
    {
        $on = ($on ?: Carbon::today())->copy()->startOfDay();

        if (! $asset->depreciates()) {
            return 0.0;
        }

        $accumulated = 0.0;

        foreach ($this->schedule($asset) as $row) {
            if ($row['from']->greaterThan($on)) {
                break;
            }

            if ($row['to']->lessThanOrEqualTo($on)) {
                $accumulated += $row['charge'];

                continue;
            }

            /* Inside the year: the charge for the part of it that has passed. */
            $held = (int) $row['from']->diffInDays($on) + 1;
            $accumulated += $row['charge'] * ($held / max(1, $row['days']));
        }

        return round($accumulated, 2);
    }

    /**
     * What the asset is worth on the books at a date: what it cost, less what has
     * been written off it.
     *
     * **A disposed asset reads zero from the day after it left.** The schedule
     * stopped at the sale, and the balance sheet does not hold an asset the
     * company no longer has — which is exactly why the value on the day of sale
     * has its own reading (`bookValueOnDisposal()`), since that is the figure the
     * profit or loss is measured against.
     */
    public function netBookValueAt(FixedAsset $asset, ?CarbonInterface $on = null): float
    {
        $on = ($on ?: Carbon::today())->copy()->startOfDay();

        if ($asset->disposal_date && $asset->disposal_date->lessThanOrEqualTo($on)) {
            return 0.0;
        }

        if (! $asset->depreciates()) {
            return round($asset->capitalisedCost(), 2);
        }

        return max(0.0, round($asset->capitalisedCost() - $this->accumulatedAt($asset, $on), 2));
    }

    /* ---------------------------------------------------------------- the maths */

    /**
     * One year's charge, before the last-year correction.
     *
     * Straight line: the depreciable amount spread evenly over the life.
     * Written down value: the anchored rate applied to the opening value, which
     * is the same thing as saying "a fixed percentage of what is left".
     */
    private function charge(
        string $method,
        float $opening,
        float $basis,
        float $depreciable,
        float $residual,
        int $life,
        int $days,
        int $daysInYear,
    ): float {
        $fraction = $daysInYear > 0 ? $days / $daysInYear : 1.0;

        if ($method === AssetVocabulary::METHOD_WDV) {
            /* The rate that lands on the residual in exactly the life — the
               Schedule II reading, where the life is the office's decision. */
            $rate = $basis > 0 && $residual > 0 && $life > 0
                ? 1 - pow($residual / $basis, 1 / $life)
                : 0.0;

            return round($opening * $rate * $fraction, 2);
        }

        if ($life <= 0) {
            return 0.0;
        }

        return round(($depreciable / $life) * $fraction, 2);
    }
}
