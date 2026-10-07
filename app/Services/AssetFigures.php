<?php

namespace App\Services;

use App\Models\FixedAsset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * What the register reads: the figures above the list, the chip counts, the
 * attention strip, and the company's year for the depreciation report.
 *
 * **One query, and one pass — and the difference is the point.** Everything that
 * is a column is read as a row of conditional sums, exactly as the other
 * modules do. Everything about *depreciation* cannot be: it is a curve, so its
 * readings are computed by `AssetDepreciation` — one calculator — over the rows
 * the filters leave, read in chunks so a register of ten thousand assets does not
 * become ten thousand rows in memory. The pass reads the columns the recipe needs
 * and nothing else.
 *
 * Two claims this class keeps true, both of which the module's check reads:
 *
 *   - the figures are about **the view you are looking at**, not the whole
 *     company — filter to the Ahmedabad office and every number above the list
 *     describes that office. The one exception is stated in words on the screens
 *     themselves: the depreciation report is asked for a *year*, and it is
 *     company-wide by design because that is the question a CA asks;
 *   - the report's total row is **the sum of the rows printed under it**. It is
 *     accumulated from the same array the table renders, not computed a second
 *     way, so the two can never disagree.
 */
class AssetFigures
{
    public function __construct(private AssetDepreciation $depreciation)
    {
    }

    /**
     * The register's figures.
     *
     * @return array<string, mixed>
     */
    public function summary(Builder $query): array
    {
        $statuses = implode(', ', array_map(
            fn (string $status) => "sum(case when status = '{$status}' then 1 else 0 end) as {$status}",
            array_keys(AssetVocabulary::STATUSES)
        ));

        $row = (clone $query)
            ->reorder()
            ->selectRaw('count(*) as total')
            ->selectRaw($statuses)
            /* The invoice total and the capitalised value are both sums of
               columns: what the GST treatment does to the basis is a case
               expression, not a pass. */
            ->selectRaw('coalesce(sum(cost), 0) as cost')
            ->selectRaw('coalesce(sum(gst_amount), 0) as gst')
            ->selectRaw('coalesce(sum(cost + gst_amount), 0) as invoice_total')
            ->selectRaw('coalesce(sum(case when depreciate_on_total then cost + gst_amount else cost end), 0) as capitalised')
            ->first();

        $figures = [
            'total' => (int) ($row->total ?? 0),
            'cost' => round((float) ($row->cost ?? 0), 2),
            'gst' => round((float) ($row->gst ?? 0), 2),
            'invoice_total' => round((float) ($row->invoice_total ?? 0), 2),
            'capitalised' => round((float) ($row->capitalised ?? 0), 2),
        ];

        foreach (array_keys(AssetVocabulary::STATUSES) as $status) {
            $figures[$status] = (int) ($row->{$status} ?? 0);
        }

        /* The curve — the one reading that is not SQL. */
        $figures += $this->curve($query);

        /* The three things that need somebody's attention this week. They obey
           the filters above them like every other figure on the page: standing
           in the Ahmedabad view, "warranty running out" means Ahmedabad's. */
        $figures['warranty_soon'] = $this->attention($query, fn (Builder $q) => $q->warrantyState('expiring'));
        $figures['insurance_soon'] = $this->attention($query, fn (Builder $q) => $q
            ->whereNotNull('insurance_expiry')
            ->whereDate('insurance_expiry', '>=', Carbon::today())
            ->whereDate('insurance_expiry', '<=', Carbon::today()->copy()->addDays(60)));
        $figures['verify_due'] = $this->attention($query, fn (Builder $q) => $q->verificationState('overdue'));
        /* The scope the drawer's "Service due" option applies — literally, so the
           number and the chip can never count different rows. */
        $figures['service_due'] = $this->attention($query, fn (Builder $q) => $q->serviceDue(60));

        return $figures;
    }

    /**
     * The chip counts: one row of sums, counted **with the chip's own state
     * lifted**, or each chip would report on the state it is standing in.
     *
     * @return array<string, int>
     */
    public function stateCounts(Builder $query): array
    {
        $keys = array_keys(AssetVocabulary::STATE_LABELS);

        $sums = implode(', ', array_map(
            fn (string $status) => "sum(case when status = '{$status}' then 1 else 0 end) as {$status}",
            array_filter($keys, fn (string $key) => $key !== 'everything')
        ));

        $row = (clone $query)->reorder()->selectRaw('count(*) as everything')->selectRaw($sums)->first();

        $counts = [];

        foreach ($keys as $key) {
            $counts[$key] = (int) ($row->{$key} ?? 0);
        }

        return $counts;
    }

    /**
     * The company's depreciation year, as the CA asks for it — and as one table:
     *
     *   - **rows** are the assets the year touched at all: what was on the books
     *     at the start, what was bought, what was written off, what was sold.
     *     An asset that had nothing to do with the year (bought after it, sold
     *     before it) is not a row of the year's schedule;
     *   - **groups** are those rows under their class, because a Schedule II
     *     working is read class by class — and each group carries its own
     *     subtotal, itself the sum of its own rows;
     *   - **totals** is the sum of every row, accumulated as the rows are built.
     *
     * @return array{year: array<string, mixed>, rows: list<array<string, mixed>>, groups: list<array<string, mixed>>, totals: array<string, mixed>}
     */
    public function yearReport(Builder $query, ?string $financialYear = null): array
    {
        $year = $financialYear ? $this->depreciation->bounds($financialYear) : null;
        $year = $year ?: $this->depreciation->financialYearOf();

        $dayBefore = $year['from']->copy()->subDay();
        $rows = [];

        (clone $query)
            ->reorder()
            ->with('category')
            ->chunkById(200, function ($assets) use (&$rows, $year, $dayBefore) {
                foreach ($assets as $asset) {
                    $row = $this->yearRow($asset, $year, $dayBefore);

                    if ($row !== null) {
                        $rows[] = $row;
                    }
                }
            });

        usort($rows, fn (array $a, array $b) => [$a['category'], $a['name']] <=> [$b['category'], $b['name']]);

        $totals = $this->emptyTotals();

        foreach ($rows as $row) {
            foreach ($totals as $key => $value) {
                $totals[$key] += is_int($value) ? 1 : (float) $row[$key];
            }
        }

        $totals = array_map(fn ($value) => is_float($value) ? round($value, 2) : $value, $totals);
        $totals['assets'] = count($rows);

        /* Grouped by class, each with its own subtotal — and no group without
           rows, so the report never prints an empty schedule heading. */
        $groups = [];

        foreach ($rows as $row) {
            $key = $row['category'];

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'category' => $key,
                    /* What the class hands its assets — the life, the method and the
                       residual the charge below is made of. An asset with its own
                       recipe says so on its row. */
                    'recipe' => $asset->category?->recipeLabel(),
                    'rows' => [],
                    'subtotal' => $this->emptyTotals(),
                ];
            }

            $groups[$key]['rows'][] = $row;

            foreach ($groups[$key]['subtotal'] as $field => $value) {
                $groups[$key]['subtotal'][$field] += is_int($value) ? 1 : (float) $row[$field];
            }
        }

        foreach ($groups as $key => $group) {
            $groups[$key]['subtotal'] = array_map(
                fn ($value) => is_float($value) ? round($value, 2) : $value,
                $group['subtotal']
            );
            $groups[$key]['subtotal']['assets'] = count($group['rows']);
        }

        return [
            'year' => $year,
            'rows' => $rows,
            'groups' => array_values($groups),
            'totals' => $totals,
        ];
    }

    /* ------------------------------------------------------------------ internals */

    /**
     * The curve readings over the filtered set: accumulated depreciation, net
     * book value and the current year's charge.
     *
     * Called on the register's figures and on nothing else — the report builds
     * its own totals from its rows.
     *
     * @return array<string, mixed>
     */
    private function curve(Builder $query): array
    {
        $financialYear = $this->depreciation->currentKey();
        $accumulated = 0.0;
        $netBookValue = 0.0;
        $charge = 0.0;
        $disposedValue = 0.0;

        (clone $query)
            ->reorder()
            ->select([
                'id', 'cost', 'gst_amount', 'depreciate_on_total', 'purchase_date',
                'disposal_date', 'disposal_value', 'category_id',
                'useful_life_years', 'depreciation_method', 'residual_percent',
            ])
            ->with('category:id,useful_life_years,depreciation_method,residual_percent')
            ->chunkById(200, function ($assets) use (&$accumulated, &$netBookValue, &$charge, &$disposedValue, $financialYear) {
                foreach ($assets as $asset) {
                    $accumulated += $this->depreciation->accumulatedAt($asset);
                    $netBookValue += $this->depreciation->netBookValueAt($asset);
                    $charge += $this->depreciation->chargeForYear($asset, $financialYear);
                    $disposedValue += (float) $asset->disposal_value;
                }
            });

        return [
            'accumulated' => round($accumulated, 2),
            'net_book_value' => round($netBookValue, 2),
            'charge_this_year' => round($charge, 2),
            'financial_year' => $financialYear,
            'disposal_value' => round($disposedValue, 2),
        ];
    }

    /** One asset's row in the year's schedule, or null when the year passed it by. */
    private function yearRow(FixedAsset $asset, array $year, Carbon $dayBefore): ?array
    {
        $opening = $this->depreciation->netBookValueAt($asset, $dayBefore);
        $charge = $this->depreciation->chargeForYear($asset, $year['key']);
        $closing = $this->depreciation->netBookValueAt($asset, $year['to']);

        $bought = $asset->purchase_date
            && $asset->purchase_date->greaterThanOrEqualTo($year['from'])
            && $asset->purchase_date->lessThanOrEqualTo($year['to']);

        $sold = $asset->disposal_date
            && $asset->disposal_date->greaterThanOrEqualTo($year['from'])
            && $asset->disposal_date->lessThanOrEqualTo($year['to']);

        if ($opening <= 0 && $closing <= 0 && $charge <= 0 && ! $bought && ! $sold) {
            return null;
        }

        return [
            'id' => $asset->id,
            'code' => $asset->asset_code,
            'name' => $asset->name,
            'category' => $asset->category?->name ?: 'Unclassified',
            'status' => $asset->status,
            'basis' => $asset->capitalisedCost(),
            'opening' => $opening,
            'additions' => $bought ? $asset->capitalisedCost() : 0.0,
            'charge' => $charge,
            'closing' => $closing,
            'disposed_on' => $sold ? $asset->disposal_date : null,
            'disposal_value' => $sold ? (float) $asset->disposal_value : 0.0,
            'disposal_book' => $sold ? (float) $asset->bookValueOnDisposal() : 0.0,
            'disposal_gain' => $sold ? (float) $asset->disposalGainLoss() : 0.0,
            'method' => $asset->methodLabel(),
            'life' => $asset->effectiveLifeYears(),
            /* The screen says the recipe once per class and flags the rows that
               do not follow it; the file that leaves the building says it on every
               row, because a CSV is read one line at a time. */
            'recipe' => $asset->recipeLabel(),
            'inherits_recipe' => $asset->inheritsRecipe(),
        ];
    }

    /**
     * The columns the totals are accumulated over. `assets` is a count and the
     * rest are money — the int/float split is what lets the loop treat them the
     * same way twice.
     *
     * @return array<string, int|float>
     */
    private function emptyTotals(): array
    {
        return [
            'assets' => 0,
            'basis' => 0.0,
            'opening' => 0.0,
            'additions' => 0.0,
            'charge' => 0.0,
            'closing' => 0.0,
            'disposal_value' => 0.0,
            'disposal_book' => 0.0,
            'disposal_gain' => 0.0,
        ];
    }

    /**
     * One conditional count for the attention strip, on the filtered set.
     *
     * The constraint arrives as a closure over the asset's own scopes, so an
     * attention number is the same query a chip would be — "3 warranties ending"
     * and the three rows you get when you click it are one definition, which is
     * the only way a count beside a link can be trusted.
     */
    private function attention(Builder $query, callable $constraint): int
    {
        $count = clone $query;
        $constraint($count);

        return $count->reorder()->count();
    }
}
