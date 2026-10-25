<?php

namespace App\Http\Controllers;

use App\Services\DashboardPeriod;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $period = $this->periodContext($request);
        $start = $period['start'];
        $end = $period['end'];
        $currency = $request->validate(['currency' => ['nullable', 'in:INR,USD,RMB']])['currency'] ?? 'INR';
        // Normalize every overview window (including rolling and multi-year) to
        // exact dates so a module cannot silently switch to its default month.
        $moduleQuery = ['period_type' => 'custom', 'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(), 'currency' => $currency];
        $modulePeriod = app(DashboardPeriod::class)->fromRequest(Request::create('/', 'GET', $moduleQuery));
        $modules = \App\Services\ModuleDashboard::availableModules($request->user());
        $summaries = [];
        foreach ($modules as $key => $definition) {
            $data = app(\App\Services\ModuleDashboard::class)->build($key, $modulePeriod, $currency, false);
            $summaries[$key] = $definition + ['metrics' => array_slice($data['metrics'], 0, 2), 'alerts' => count($data['alerts']),
                'url' => route('dashboards.show', ['module' => $key] + $moduleQuery)];
        }
        $availableYears = $this->availableYears();
        return view('dashboard.index', compact('period', 'start', 'end', 'currency', 'summaries', 'availableYears', 'moduleQuery'));
    }

    private function periodContext(Request $request): array
    {
        $type = (string) $request->query('period_type', 'year');
        if (! in_array($type, ['range', 'month', 'quarter', 'half', 'year', 'multi_year', 'custom'], true)) {
            $type = 'year';
        }

        $currentYear = (int) now()->format('Y');
        $year = (int) $request->query('year', $currentYear);
        if ($year < 2000 || $year > $currentYear + 2) {
            $year = $currentYear;
        }

        $range = (int) $request->query('range', 30);
        if (! in_array($range, [7, 30, 90, 180, 365], true)) {
            $range = 30;
        }

        if ($type === 'custom') {
            [$start, $end, $label] = app(DashboardPeriod::class)->customRange($request);
            $days = (int) $start->diffInDays($end) + 1;
            // Keep short windows detailed and multi-year charts bounded in size.
            $group = $days <= 90 ? 'day' : ($days <= 1096 ? 'month' : 'year');
            $labels = match ($group) {
                'day' => $this->dateLabels($start, $end),
                'month' => $this->monthLabels($start, $end),
                default => $this->yearLabels($start->year, $end->year),
            };

            return compact('type', 'label', 'year', 'range', 'start', 'end', 'group', 'labels')
                + ['quarter' => null, 'half' => null];
        }

        if ($type === 'range') {
            $start = now()->subDays($range - 1)->startOfDay();
            $end = now()->endOfDay();
            return [
                'type' => $type,
                'label' => 'Last '.$range.' Days',
                'year' => $year,
                'quarter' => null,
                'half' => null,
                'range' => $range,
                'start' => $start,
                'end' => $end,
                'group' => 'day',
                'labels' => $this->dateLabels($start, $end),
            ];
        }

        if ($type === 'month') {
            $month = (int) $request->query('month', now()->month);
            if ($month < 1 || $month > 12) {
                $month = now()->month;
            }
            $start = Carbon::create($year, $month, 1)->startOfDay();
            $end = $start->copy()->endOfMonth()->endOfDay();

            return [
                'type' => $type,
                'label' => $start->format('F Y'),
                'year' => $year,
                'month' => $month,
                'quarter' => null,
                'half' => null,
                'range' => $range,
                'start' => $start,
                'end' => $end,
                'group' => 'day',
                'labels' => $this->dateLabels($start, $end),
            ];
        }

        if ($type === 'quarter') {
            $quarter = (int) $request->query('quarter', (int) ceil(now()->month / 3));
            if ($quarter < 1 || $quarter > 4) {
                $quarter = 1;
            }
            $startMonth = (($quarter - 1) * 3) + 1;
            $start = Carbon::create($year, $startMonth, 1)->startOfDay();
            $end = $start->copy()->addMonths(2)->endOfMonth()->endOfDay();
            return [
                'type' => $type,
                'label' => 'Q'.$quarter.' '.$year,
                'year' => $year,
                'quarter' => $quarter,
                'half' => null,
                'range' => $range,
                'start' => $start,
                'end' => $end,
                'group' => 'month',
                'labels' => $this->monthLabels($start, $end),
            ];
        }

        if ($type === 'half') {
            $half = (int) $request->query('half', now()->month <= 6 ? 1 : 2);
            if (! in_array($half, [1, 2], true)) {
                $half = 1;
            }
            $startMonth = $half === 1 ? 1 : 7;
            $start = Carbon::create($year, $startMonth, 1)->startOfDay();
            $end = $start->copy()->addMonths(5)->endOfMonth()->endOfDay();
            return [
                'type' => $type,
                'label' => 'H'.$half.' '.$year,
                'year' => $year,
                'quarter' => null,
                'half' => $half,
                'range' => $range,
                'start' => $start,
                'end' => $end,
                'group' => 'month',
                'labels' => $this->monthLabels($start, $end),
            ];
        }

        if ($type === 'multi_year') {
            $fromYear = (int) $request->query('from_year', max($currentYear - 2, 2000));
            $toYear = (int) $request->query('to_year', $currentYear);
            if ($fromYear > $toYear) {
                $tmp = $fromYear;
                $fromYear = $toYear;
                $toYear = $tmp;
            }
            $fromYear = max(2000, min($fromYear, $currentYear + 2));
            $toYear = max(2000, min($toYear, $currentYear + 2));
            $start = Carbon::create($fromYear, 1, 1)->startOfDay();
            $end = Carbon::create($toYear, 12, 31)->endOfDay();
            return [
                'type' => $type,
                'label' => $fromYear.' - '.$toYear,
                'year' => $year,
                'from_year' => $fromYear,
                'to_year' => $toYear,
                'quarter' => null,
                'half' => null,
                'range' => $range,
                'start' => $start,
                'end' => $end,
                'group' => 'year',
                'labels' => $this->yearLabels($fromYear, $toYear),
            ];
        }

        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end = Carbon::create($year, 12, 31)->endOfDay();

        return [
            'type' => 'year',
            'label' => (string) $year,
            'year' => $year,
            'quarter' => null,
            'half' => null,
            'range' => $range,
            'start' => $start,
            'end' => $end,
            'group' => 'month',
            'labels' => $this->monthLabels($start, $end),
        ];
    }

    private function sumExpressionSeries(string $table, string $dateColumn, string $expression, array $period, ?callable $callback = null): array
    {
        $series = array_fill_keys(array_keys($period['labels']), 0);

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $dateColumn)) {
            return array_values($series);
        }

        $bucketSql = $this->bucketSql($dateColumn, $period['group']);
        $query = DB::table($table)
            ->selectRaw($bucketSql.' as bucket, SUM('.$expression.') as total')
            ->whereBetween($dateColumn, [$this->dateBoundary($period['start'], $dateColumn), $this->dateBoundary($period['end'], $dateColumn, true)])
            ->groupBy('bucket')
            ->orderBy('bucket');

        if ($callback) {
            $callback($query);
        }

        foreach ($query->pluck('total', 'bucket')->toArray() as $bucket => $total) {
            if (array_key_exists((string) $bucket, $series)) {
                $series[(string) $bucket] = round((float) $total, 2);
            }
        }

        return array_values($series);
    }

    private function bucketSql(string $dateColumn, string $group): string
    {
        if ($group === 'year') {
            return 'YEAR('.$dateColumn.')';
        }
        if ($group === 'month') {
            return "DATE_FORMAT(".$dateColumn.", '%Y-%m')";
        }

        return 'DATE('.$dateColumn.')';
    }

    private function dateBoundary(Carbon $date, string $dateColumn, bool $end = false)
    {
        $dateOnlyColumns = ['entry_date', 'payment_date', 'invoice_date', 'pickup_date', 'drop_date', 'start_date', 'target_date'];

        if (in_array($dateColumn, $dateOnlyColumns, true)) {
            return $date->toDateString();
        }

        return $end ? $date->copy()->endOfDay() : $date->copy()->startOfDay();
    }

    private function dateLabels(Carbon $start, Carbon $end): array
    {
        $labels = [];
        $date = $start->copy();

        while ($date->lte($end)) {
            $labels[$date->toDateString()] = $date->format('d M');
            $date->addDay();
        }

        return $labels;
    }

    private function monthLabels(Carbon $start, Carbon $end): array
    {
        $labels = [];
        $date = $start->copy()->startOfMonth();

        while ($date->lte($end)) {
            $labels[$date->format('Y-m')] = $date->format('M Y');
            $date->addMonth();
        }

        return $labels;
    }

    private function yearLabels(int $fromYear, int $toYear): array
    {
        $labels = [];
        for ($year = $fromYear; $year <= $toYear; $year++) {
            $labels[(string) $year] = (string) $year;
        }

        return $labels;
    }

    private function availableYears(): array
    {
        $current = (int) now()->format('Y');
        $min = $current - 5;

        foreach ([
            ['cashflow_entries', 'entry_date'],
            ['projects', 'created_at'],
            ['shipments', 'created_at'],
        ] as $item) {
            [$table, $column] = $item;
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                $value = DB::table($table)->min($column);
                if ($value) {
                    $year = (int) Carbon::parse($value)->format('Y');
                    $min = min($min, $year);
                }
            }
        }

        $years = [];
        for ($year = $current + 1; $year >= $min; $year--) {
            $years[] = $year;
        }

        return $years;
    }

}
