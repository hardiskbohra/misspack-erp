<?php

namespace App\Services;

use App\Helpers\DateRanges;
use App\Models\CashflowEntry;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The analysis builder: money down the side, time across the top.
 *
 * An accountant's ad-hoc report is always the same question — "how much, of
 * which kind, with whom, over which months" — asked with a different word in
 * the middle. This service answers it from one definition: a *dimension* names
 * the column the rows are grouped by, a *period unit* cuts the range into
 * months, quarters or years, and a *measure* picks which figure the cells hold.
 * Whatever the screen puts on the page, the numbers come from here.
 *
 * Three rules keep the report trustworthy, because a total nobody can open is a
 * total somebody has to re-add by hand:
 *
 *   1. **One read, no grouping SQL.** The rows in the window are fetched once
 *      and bucketed in PHP. `GROUP BY DATE_FORMAT(...)` would tie the report to
 *      MySQL, and the buckets have to be computed in PHP anyway: the drill link
 *      needs each bucket's exact first and last day, and a report cell is only
 *      honest if the rows it opens are the rows it counted.
 *   2. **A cell knows its own query.** Every cell is built next to the query
 *      that opens the rows behind it (`ledgerQuery()`), so a total and its
 *      drill-down are one definition of "these rows" — not two.
 *   3. **Nothing is hidden.** When the window holds more than one currency the
 *      report says so; adding dollars to rupees is a number, not an answer.
 */
class CashflowAnalysis
{
    /** The row dimensions, in the order the picker offers them. */
    public const DIMENSIONS = [
        'none' => [
            'label' => 'Overall (one line)',
            'hint' => 'No grouping — the period totals on their own',
            'column' => null,
        ],
        'client' => [
            'label' => 'Client',
            'hint' => 'Grouped by the client the entry is against',
            'column' => 'client_id',
            'filter' => 'client_id',
        ],
        'vendor' => [
            'label' => 'Vendor',
            'hint' => 'Grouped by the vendor the entry is against',
            'column' => 'vendor_id',
            'filter' => 'vendor_id',
        ],
        'employee' => [
            'label' => 'Employee',
            'hint' => 'Grouped by the employee the entry is against',
            'column' => 'employee_id',
            'filter' => 'employee_id',
        ],
        'account' => [
            'label' => 'Account',
            'hint' => 'Grouped by the bank or cash account',
            'column' => 'account_id',
            'filter' => 'account_id',
        ],
        'category' => [
            'label' => 'Category',
            'hint' => 'Grouped by the cashflow category',
            'column' => 'category_id',
            'filter' => 'category_id',
        ],
        'expense_head' => [
            'label' => 'Expense head',
            'hint' => 'Grouped by the expense head typed on the entry',
            'column' => 'expense_head',
            'filter' => 'expense_head',
        ],
        'payment_mode' => [
            'label' => 'Payment mode',
            'hint' => 'Grouped by how the money moved',
            'column' => 'payment_mode',
            'filter' => 'payment_mode',
        ],
        'transaction_type' => [
            'label' => 'Money in / out',
            'hint' => 'Two rows: money in and money out',
            'column' => 'transaction_type',
            'filter' => 'transaction_type',
        ],
        'accounting_status' => [
            'label' => 'Accounting status',
            'hint' => 'Grouped by pending / booked / reconciled',
            'column' => 'accounting_status',
            'filter' => 'accounting_status',
        ],
        'project' => [
            'label' => 'Project',
            'hint' => 'Grouped by the project the entry belongs to',
            'column' => 'project_id',
            'filter' => 'project_id',
        ],
        'currency' => [
            'label' => 'Currency',
            'hint' => 'Grouped by the currency the money is in',
            'column' => 'currency',
            'filter' => 'currency',
        ],
    ];

    /** How the range is cut up across the top of the report. */
    public const UNITS = [
        'month' => 'Month',
        'quarter' => 'Quarter',
        'year' => 'Year',
    ];

    /** Which figure the matrix holds. */
    public const MEASURES = [
        'net' => 'Net (in − out)',
        'credit' => 'Money in',
        'debit' => 'Money out',
    ];

    /** What each period is read against. */
    public const COMPARISONS = [
        'none' => 'Nothing',
        'previous' => 'Previous period',
        'last_year' => 'Same period last year',
    ];

    /**
     * A range this long is a data dump, not a report: cap the columns so
     * "month" over ten years cannot render a thousand-column table.
     */
    public const MAX_PERIODS = 36;

    /** Which figure the cells hold for this run. */
    private string $measure = 'net';

    public function dimension(string $key): array
    {
        return self::DIMENSIONS[$key] ?? self::DIMENSIONS['none'];
    }

    public function unit(string $key): string
    {
        return array_key_exists($key, self::UNITS) ? $key : 'month';
    }

    public function measure(string $key): string
    {
        return array_key_exists($key, self::MEASURES) ? $key : 'net';
    }

    public function comparison(string $key): string
    {
        return array_key_exists($key, self::COMPARISONS) ? $key : 'none';
    }

    /**
     * The report.
     *
     * @param  array<string, mixed>  $filters  the module's filters, already resolved
     * @return array<string, mixed>
     */
    public function build(
        array $filters,
        string $dimension,
        string $unit,
        string $measure,
        string $comparison,
        ?string $from,
        ?string $to
    ): array {
        $dimension = array_key_exists($dimension, self::DIMENSIONS) ? $dimension : 'none';
        $unit = $this->unit($unit);
        $this->measure = $this->measure($measure);
        $comparison = $this->comparison($comparison);

        $today = DateRanges::today();
        $to = $to ?: $today->toDateString();

        /* An empty window is never what someone meant: the report opens on the
           year to date, the same window the ledger opens on. */
        $from = $from ?: $today->copy()->startOfYear()->toDateString();

        [$from, $to] = $this->order($from, $to);
        $periods = $this->periods($from, $to, $unit, $comparison);

        $report = [
            'dimension' => $dimension,
            'unit' => $unit,
            'measure' => $this->measure,
            'comparison' => $comparison,
            'from' => $from,
            'to' => $to,
            'periods' => $periods,
            'rows' => [],
            'totals' => $this->emptyTotals(),
            'currencies' => [],
            'truncated' => false,
            'empty' => true,
        ];

        if ($periods === []) {
            return $report;
        }

        /* The range the report reads: the window, plus whatever the comparison
           needs. One query for all of it. */
        $readFrom = $periods[0]['start'];
        $readTo = $periods[count($periods) - 1]['end'];

        foreach ($periods as $period) {
            if ($period['compare_start'] && $period['compare_start'] < $readFrom) {
                $readFrom = $period['compare_start'];
            }
            if ($period['compare_end'] && $period['compare_end'] > $readTo) {
                $readTo = $period['compare_end'];
            }
        }

        $entries = $this->entries($filters, $readFrom, $readTo);

        /* Bucket once, in PHP: each row is placed in its bucket (and told apart
           from the buckets that only exist to be compared against), and the same
           placement drives the rows and the footer, so they cannot disagree. */
        $bucketed = $this->bucket($entries, $periods);

        $report['rows'] = $this->rows($bucketed, $periods, $dimension);
        $report['totals'] = $this->totals($bucketed, $periods);
        $report['currencies'] = $this->currencies($entries);
        $report['empty'] = $bucketed['in']->isEmpty();

        /* The cap bites only when the range runs past the last column drawn: a
           report that silently stops at March is worse than one that says so. */
        $last = end($periods);
        $report['truncated'] = $last !== false && $last['end'] < $to;

        return $report;
    }

    /**
     * Place every entry in its bucket.
     *
     * @param  Collection<int, CashflowEntry>  $entries
     * @param  array<int, array<string, mixed>>  $periods
     * @return array{in: Collection, byIndex: array<int, Collection>, compare: Collection}
     */
    private function bucket(Collection $entries, array $periods): array
    {
        $in = collect();
        $byIndex = [];
        $compare = collect();

        foreach ($entries as $entry) {
            $date = substr((string) $entry->entry_date, 0, 10);

            foreach ($periods as $index => $period) {
                if ($date >= $period['start'] && $date <= $period['end']) {
                    $in->push($entry);
                    $byIndex[$index][] = $entry;
                    break;
                }

                if ($period['compare_start'] && $date >= $period['compare_start'] && $date <= $period['compare_end']) {
                    $compare->push(['index' => $index, 'entry' => $entry]);
                    break;
                }

                /* An entry outside every bucket — the clamped tail of a
                   previous-period window — belongs to neither this report's
                   totals nor any comparison: it is read and left alone. */
            }
        }

        return ['in' => $in, 'byIndex' => $byIndex, 'compare' => $compare];
    }

    /**
     * The rows of the matrix: one per dimension value, a cell for every period,
     * and the row's own totals.
     *
     * @param  array{in: Collection, byIndex: array<int, Collection>, compare: Collection}  $bucketed
     * @param  array<int, array<string, mixed>>  $periods
     * @return array<int, array<string, mixed>>
     */
    private function rows(array $bucketed, array $periods, string $dimension): array
    {
        $definition = $this->dimension($dimension);

        /* One lookup for every name the report will print: a client report of
           two hundred clients must not run two hundred queries to name them. */
        $names = $this->names($dimension, $bucketed['in']->merge(collect($bucketed['compare'])->pluck('entry')));

        $groups = [];
        $ensure = function ($value) use (&$groups, $definition, $names) {
            $key = $this->groupKey($value);

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'key' => $key,
                    'value' => $value,
                    'label' => $this->groupLabel($value, $definition, $names),
                    'cells' => [],
                    'compare_cells' => [],
                    'credit' => 0.0,
                    'debit' => 0.0,
                    'compare_credit' => 0.0,
                    'compare_debit' => 0.0,
                    'count' => 0,
                ];
            }

            return $key;
        };

        foreach ($periods as $index => $period) {
            foreach ($bucketed['byIndex'][$index] ?? [] as $entry) {
                $key = $ensure($definition['column'] === null ? null : $entry->getAttribute($definition['column']));

                $groups[$key]['credit'] = round($groups[$key]['credit'] + (float) $entry->credit_amount, 2);
                $groups[$key]['debit'] = round($groups[$key]['debit'] + (float) $entry->debit_amount, 2);
                $groups[$key]['count']++;
                $groups[$key]['cells'][$index][] = $entry;
            }
        }

        /* The comparison rows are the same ledger at a shifted date: they belong
           to the bucket they are read against, never to a row of their own. */
        foreach ($bucketed['compare'] as $placed) {
            $entry = $placed['entry'];
            $key = $ensure($definition['column'] === null ? null : $entry->getAttribute($definition['column']));

            $groups[$key]['compare_credit'] = round($groups[$key]['compare_credit'] + (float) $entry->credit_amount, 2);
            $groups[$key]['compare_debit'] = round($groups[$key]['compare_debit'] + (float) $entry->debit_amount, 2);
            $groups[$key]['compare_cells'][$placed['index']][] = $entry;
        }

        $rows = [];

        foreach ($groups as $group) {
            $group['net'] = round($group['credit'] - $group['debit'], 2);
            $group['compare_net'] = round($group['compare_credit'] - $group['compare_debit'], 2);
            $group['delta'] = $this->delta($group['net'], $group['compare_net']);
            $group['measure_value'] = $this->measureOf($group);
            $group['direction'] = $this->direction($group['measure_value']);
            $group['cells'] = $this->cells($group['cells'] ?? [], $group['compare_cells'] ?? [], $periods);

            $rows[] = $group;
        }

        /* Heaviest first: a report is read to find where the money is. */
        usort($rows, function (array $a, array $b) {
            $difference = abs($b['measure_value']) <=> abs($a['measure_value']);

            return $difference !== 0 ? $difference : strcmp((string) $a['label'], (string) $b['label']);
        });

        return $rows;
    }

    /**
     * One row's cells: the window's figures, the comparison's figures beside
     * them, and the count behind each — a cell that would open nothing says so.
     *
     * @param  array<int, array<int, CashflowEntry>>  $raw
     * @param  array<int, array<int, CashflowEntry>>  $rawCompare
     * @param  array<int, array<string, mixed>>  $periods
     * @return array<int, array<string, mixed>>
     */
    private function cells(array $raw, array $rawCompare, array $periods): array
    {
        $cells = [];

        foreach ($periods as $index => $period) {
            $entries = $raw[$index] ?? [];
            $compareEntries = $rawCompare[$index] ?? [];

            $credit = round(array_sum(array_map(fn ($entry) => (float) $entry->credit_amount, $entries)), 2);
            $debit = round(array_sum(array_map(fn ($entry) => (float) $entry->debit_amount, $entries)), 2);
            $compareCredit = round(array_sum(array_map(fn ($entry) => (float) $entry->credit_amount, $compareEntries)), 2);
            $compareDebit = round(array_sum(array_map(fn ($entry) => (float) $entry->debit_amount, $compareEntries)), 2);

            $net = round($credit - $debit, 2);
            $compareNet = round($compareCredit - $compareDebit, 2);
            $measure = $this->measureOf(['credit' => $credit, 'debit' => $debit]);
            $compareMeasure = $this->measureOf(['credit' => $compareCredit, 'debit' => $compareDebit]);

            $cells[$index] = [
                'period' => $period,
                'credit' => $credit,
                'debit' => $debit,
                'net' => $net,
                'compare_credit' => $compareCredit,
                'compare_debit' => $compareDebit,
                'compare_net' => $compareNet,
                'delta' => $this->delta($measure, $compareMeasure),
                'count' => count($entries),
                'measure_value' => $measure,
                'compare_value' => $compareMeasure,
                'direction' => $this->direction($measure),
                'empty' => $entries === [],
            ];
        }

        return $cells;
    }

    /**
     * The footer: every period's totals, so the column footings and the row
     * totals add up to the same grand total the ledger shows for the window.
     *
     * @param  array{in: Collection, byIndex: array<int, Collection>, compare: Collection}  $bucketed
     * @param  array<int, array<string, mixed>>  $periods
     * @return array<string, mixed>
     */
    private function totals(array $bucketed, array $periods): array
    {
        $totals = $this->emptyTotals();
        $cells = [];

        foreach ($periods as $index => $period) {
            $rows = $bucketed['byIndex'][$index] ?? [];

            $cells[$index] = [
                'period' => $period,
                'credit' => round(array_sum(array_map(fn ($entry) => (float) $entry->credit_amount, $rows)), 2),
                'debit' => round(array_sum(array_map(fn ($entry) => (float) $entry->debit_amount, $rows)), 2),
                'compare_credit' => 0.0,
                'compare_debit' => 0.0,
                'count' => count($rows),
            ];

            $totals['credit'] = round($totals['credit'] + $cells[$index]['credit'], 2);
            $totals['debit'] = round($totals['debit'] + $cells[$index]['debit'], 2);
            $totals['count'] += count($rows);
        }

        foreach ($bucketed['compare'] as $placed) {
            $index = $placed['index'];
            $entry = $placed['entry'];

            $cells[$index]['compare_credit'] = round($cells[$index]['compare_credit'] + (float) $entry->credit_amount, 2);
            $cells[$index]['compare_debit'] = round($cells[$index]['compare_debit'] + (float) $entry->debit_amount, 2);
            $totals['compare_credit'] = round($totals['compare_credit'] + (float) $entry->credit_amount, 2);
            $totals['compare_debit'] = round($totals['compare_debit'] + (float) $entry->debit_amount, 2);
        }

        foreach ($cells as $index => $cell) {
            $cell['net'] = round($cell['credit'] - $cell['debit'], 2);
            $cell['compare_net'] = round($cell['compare_credit'] - $cell['compare_debit'], 2);
            $cell['measure_value'] = $this->measureOf($cell);
            $cell['compare_value'] = $this->measureOf(['credit' => $cell['compare_credit'], 'debit' => $cell['compare_debit']]);
            $cell['delta'] = $this->delta($cell['measure_value'], $cell['compare_value']);
            $cell['direction'] = $this->direction($cell['measure_value']);
            $cell['empty'] = $cell['count'] === 0;

            $totals['cells'][$index] = $cell;
        }

        $totals['net'] = round($totals['credit'] - $totals['debit'], 2);
        $totals['compare_net'] = round($totals['compare_credit'] - $totals['compare_debit'], 2);
        $totals['measure_value'] = $this->measureOf($totals);
        $totals['compare_value'] = $this->measureOf(['credit' => $totals['compare_credit'], 'debit' => $totals['compare_debit']]);
        $totals['delta'] = $this->delta($totals['measure_value'], $totals['compare_value']);
        $totals['direction'] = $this->direction($totals['measure_value']);

        return $totals;
    }

    /**
     * The period columns: every bucket of the range, each with the dates it
     * covers, the dates it is compared against, and the labels an accountant
     * reads ("Aug 2026", "Q2 2026-27", "2026-27").
     *
     * @return array<int, array<string, mixed>>
     */
    public function periods(string $from, string $to, string $unit, string $comparison = 'none'): array
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();

        if ($start->greaterThan($end)) {
            return [];
        }

        $periods = [];
        $cursor = $this->bucketStart($start, $unit);

        while ($cursor->lessThanOrEqualTo($end) && count($periods) < self::MAX_PERIODS) {
            $bucketEnd = $this->bucketEnd($cursor, $unit);

            /* A range that starts mid-bucket still shows that bucket in full,
               clamped to the range: the totals must match what the ledger shows
               for the same dates, so the link and the bucket use these edges. */
            $coveredStart = $cursor->greaterThan($start) ? $cursor : $start;
            $coveredEnd = $bucketEnd->greaterThan($end) ? $end : $bucketEnd;

            $period = [
                'key' => $cursor->format('Y-m-d'),
                'label' => $this->bucketLabel($cursor, $unit),
                'short' => $this->bucketShort($cursor, $unit),
                'unit' => $unit,
                'start' => $coveredStart->toDateString(),
                'end' => $coveredEnd->toDateString(),
                'compare_start' => null,
                'compare_end' => null,
                'compare_label' => null,
            ];

            $periods[] = $period;

            if ($bucketEnd->greaterThanOrEqualTo($end)) {
                break;
            }

            $cursor = $bucketEnd->copy()->addDay()->startOfDay();
        }

        return $this->compare($periods, $unit, $comparison);
    }

    /**
     * Give every bucket the window it is read against.
     *
     * The comparison is the whole axis **shifted**, not the bucket in front of
     * it: over April–June, "previous period" is January–March, so April is read
     * against January and June against March. Comparing April with March would
     * be reading a bucket against a row that is itself in the report — the same
     * entries counted twice, once as a figure and once as its own comparison.
     *
     * The shift keeps the day-of-month alignment of a clamped range (a report
     * starting on the 5th compares the 5th onward) and never overflows a short
     * month: 31 March shifted back a quarter is 31 December, never 2 January.
     *
     * @param  array<int, array<string, mixed>>  $periods
     * @return array<int, array<string, mixed>>
     */
    private function compare(array $periods, string $unit, string $comparison): array
    {
        if ($comparison === 'none' || $periods === []) {
            return $periods;
        }

        $shift = $comparison === 'previous'
            ? count($periods)
            : ['month' => 12, 'quarter' => 4, 'year' => 1][$unit];

        foreach ($periods as $index => $period) {
            $start = Carbon::parse($period['start']);
            $end = Carbon::parse($period['end']);

            [$start, $end] = match ($unit) {
                'year' => [$start->subYearsNoOverflow($shift), $end->subYearsNoOverflow($shift)],
                'quarter' => [$start->subQuartersNoOverflow($shift), $end->subQuartersNoOverflow($shift)],
                default => [$start->subMonthsNoOverflow($shift), $end->subMonthsNoOverflow($shift)],
            };

            $periods[$index]['compare_start'] = $start->toDateString();
            $periods[$index]['compare_end'] = $end->toDateString();
            $periods[$index]['compare_label'] = $this->bucketLabel($start, $unit);
        }

        return $periods;
    }

    /**
     * The query string that opens the ledger on the rows behind a cell or a
     * row: the dimension value, the dates, and every filter the report itself
     * was run with. This is the whole point of the builder — a figure nobody
     * can open is a figure somebody has to re-add by hand.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>|null  $period
     * @return array<string, string>
     */
    public function ledgerQuery(array $filters, string $dimension, ?array $period, $value = null, bool $includeValue = true): array
    {
        $query = app(CashflowFilters::class)->toQuery($filters);
        $definition = $this->dimension($dimension);

        if ($includeValue && isset($definition['filter'])) {
            $value = $value === null ? '' : $value;

            $query[$definition['filter']] = $this->groupKey($value) === CashflowFilters::NOT_SET_KEY
                ? CashflowFilters::NOT_SET
                : (string) $value;
        }

        if ($period !== null) {
            $query['date_from'] = $period['start'];
            $query['date_to'] = $period['end'];
        }

        /* The ledger opens newest-first; a report is read from its beginning, so
           the rows behind a report figure open in the report's own order. */
        $query['sort'] = 'oldest';

        return $query;
    }

    /**
     * The entries of the window, in one read.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, CashflowEntry>
     */
    private function entries(array $filters, string $from, string $to): Collection
    {
        $relations = ['account', 'category'];

        if (Schema::hasColumn('cashflow_entries', 'employee_id')) {
            $relations[] = 'employee';
        }

        $query = CashflowEntry::query()
            ->with($relations)
            ->whereDate('entry_date', '>=', $from)
            ->whereDate('entry_date', '<=', $to)
            ->oldest('entry_date')
            ->oldest('id');

        return app(CashflowFilters::class)->apply($query, $filters)->get();
    }

    /**
     * Every currency the window holds — one is normal, more than one is a number
     * that needs saying out loud.
     *
     * @param  Collection<int, CashflowEntry>  $entries
     * @return array<int, string>
     */
    private function currencies(Collection $entries): array
    {
        return $entries
            ->map(fn (CashflowEntry $entry) => strtoupper((string) ($entry->currency ?: 'INR')))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Every name this run will print, in one query per dimension.
     *
     * @param  Collection<int, CashflowEntry>  $entries
     * @return array<int|string, string>
     */
    private function names(string $dimension, Collection $entries): array
    {
        $definition = $this->dimension($dimension);
        $column = $definition['column'] ?? null;

        $sources = [
            'client_id' => ['clients', 'company_name'],
            'vendor_id' => ['vendors', 'vendor_name'],
            'employee_id' => ['users', 'name'],
            'account_id' => ['cashflow_accounts', 'account_name'],
            'category_id' => ['cashflow_categories', 'name'],
            'project_id' => ['projects', 'name'],
        ];

        if ($column === null || ! isset($sources[$column])) {
            return [];
        }

        [$table, $nameColumn] = $sources[$column];

        if (! Schema::hasTable($table)) {
            return [];
        }

        $ids = $entries
            ->map(fn (CashflowEntry $entry) => $entry->getAttribute($column))
            ->filter(fn ($value) => $value !== null && $value !== '' && $value !== 0 && $value !== '0')
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        return DB::table($table)->whereIn('id', $ids)->pluck($nameColumn, 'id')->all();
    }

    /**
     * What a row is called. An id is not a name: the row shows the client's
     * company name, a record that has since been deleted keeps its id rather
     * than becoming a blank, and nothing filled in reads "Not set".
     *
     * @param  array<int|string, string>  $names
     */
    public function groupLabel($value, array $definition, array $names = []): string
    {
        $column = $definition['column'] ?? null;

        if ($column === null) {
            return 'All entries';
        }

        if ($this->groupKey($value) === CashflowFilters::NOT_SET_KEY) {
            return 'Not set';
        }

        if (isset($names[(int) $value])) {
            return (string) $names[(int) $value];
        }

        $vocabularies = [
            'transaction_type' => CashflowEntry::transactionTypeOptions(),
            'accounting_status' => CashflowEntry::accountingStatusOptions(),
            'payment_mode' => CashflowEntry::paymentModeOptions(),
        ];

        if (isset($vocabularies[$column])) {
            return (string) ($vocabularies[$column][$value] ?? $value);
        }

        if ($column === 'currency') {
            return strtoupper((string) $value);
        }

        return (string) $value;
    }

    /** Null, empty and zero all mean the same thing on a row: nothing was filled in. */
    private function groupKey($value): string
    {
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return CashflowFilters::NOT_SET_KEY;
        }

        return (string) $value;
    }

    /** The figure this run's cells hold. */
    private function measureOf(array $row): float
    {
        return match ($this->measure) {
            'credit' => round((float) ($row['credit'] ?? 0), 2),
            'debit' => round((float) ($row['debit'] ?? 0), 2),
            default => round((float) ($row['credit'] ?? 0) - (float) ($row['debit'] ?? 0), 2),
        };
    }

    private function direction(float $amount): string
    {
        if (abs($amount) < 0.005) {
            return 'flat';
        }

        return $amount > 0 ? 'in' : 'out';
    }

    /** Change against a comparison figure, as a percentage — null when there is nothing to compare against. */
    private function delta(float $value, float $compare): ?float
    {
        if (abs($compare) < 0.005) {
            return null;
        }

        return round((($value - $compare) / abs($compare)) * 100, 1);
    }

    private function emptyTotals(): array
    {
        return [
            'credit' => 0.0,
            'debit' => 0.0,
            'net' => 0.0,
            'compare_credit' => 0.0,
            'compare_debit' => 0.0,
            'compare_net' => 0.0,
            'delta' => null,
            'count' => 0,
            'cells' => [],
            'measure_value' => 0.0,
            'direction' => 'flat',
        ];
    }

    /** A range whose ends are the wrong way round is still a range. */
    private function order(string $from, string $to): array
    {
        return $from > $to ? [$to, $from] : [$from, $to];
    }

    private function bucketStart(Carbon $date, string $unit): Carbon
    {
        return match ($unit) {
            'year' => $date->copy()->startOfYear(),
            'quarter' => $date->copy()->startOfQuarter(),
            default => $date->copy()->startOfMonth(),
        };
    }

    private function bucketEnd(Carbon $date, string $unit): Carbon
    {
        return match ($unit) {
            'year' => $date->copy()->endOfYear(),
            'quarter' => $date->copy()->endOfQuarter(),
            default => $date->copy()->endOfMonth(),
        };
    }

    /**
     * The label a bucket wears. Calendar quarters and calendar years, because
     * that is what the rest of the module means by a period: the ledger's "This
     * year" chip is January–December, and a report that cut the same window into
     * April–March quarters would be answering a different question from the one
     * the operator clicked to get here. (A financial-year reading is a
     * module-wide vocabulary change — the chips, the presets and the buckets
     * together — not a relabelling of this one screen.)
     */
    public function bucketLabel(Carbon $date, string $unit): string
    {
        return match ($unit) {
            'year' => $date->format('Y'),
            'quarter' => 'Q'.$date->quarter.' '.$date->format('Y'),
            default => $date->format('M Y'),
        };
    }

    private function bucketShort(Carbon $date, string $unit): string
    {
        return match ($unit) {
            'year' => (string) $date->format('Y'),
            'quarter' => 'Q'.$date->quarter,
            default => $date->format('M'),
        };
    }

}

