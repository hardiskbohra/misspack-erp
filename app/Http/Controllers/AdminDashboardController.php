<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $period = $this->periodContext($request);
        $range = (int) $request->query('range', 30); // kept for backward compatibility
        $start = $period['start'];
        $end = $period['end'];
        $labels = array_values($period['labels']);

        $salesSeries = $this->salesSeries($period);
        $purchaseSeries = $this->purchaseSeries($period);
        $incomeSeries = $this->cashflowSeries('credit_amount', $period);
        $expenseSeries = $this->cashflowSeries('debit_amount', $period);
        $projectInwardSeries = $this->projectPaymentSeries('inward', $period);
        $projectOutwardSeries = $this->projectPaymentSeries('outward', $period);

        $charts = [
            'period' => [
                'type' => $period['type'],
                'label' => $period['label'],
                'group' => $period['group'],
                'start' => $start->format('d M Y'),
                'end' => $end->format('d M Y'),
            ],
            'labels' => $labels,
            'salesPurchase' => [
                'sales' => $salesSeries,
                'purchase' => $purchaseSeries,
                'margin' => $this->subtractSeries($salesSeries, $purchaseSeries),
            ],
            'incomeExpense' => [
                'income' => $incomeSeries,
                'expense' => $expenseSeries,
                'net' => $this->subtractSeries($incomeSeries, $expenseSeries),
            ],
            'projectPayments' => [
                'inward' => $projectInwardSeries,
                'outward' => $projectOutwardSeries,
                'net' => $this->subtractSeries($projectInwardSeries, $projectOutwardSeries),
            ],
            'pipeline' => [
                'leads' => $this->countSeries('leads', $this->dateColumn('leads', ['created_at']), $period),
                'quotes' => $this->countSeries('customer_quotes', $this->dateColumn('customer_quotes', ['quote_date', 'created_at']), $period),
                'projects' => $this->countSeries('projects', $this->dateColumn('projects', ['start_date', 'created_at']), $period),
                'shipments' => $this->countSeries('shipments', $this->dateColumn('shipments', ['pickup_date', 'created_at']), $period),
            ],
            'status' => [
                'projects' => $this->breakdown(\App\Models\Project::class, 'projects', 'status'),
                'projectHealth' => $this->breakdown(\App\Models\Project::class, 'projects', 'health'),
                'shipments' => $this->breakdown(\App\Models\Shipment::class, 'shipments', 'status'),
                'quotes' => $this->breakdown(\App\Models\CustomerQuote::class, 'customer_quotes', 'status'),
                'leads' => $this->breakdown(\App\Models\Lead::class, 'leads', 'status'),
                'tasks' => $this->breakdown(\App\Models\Task::class, 'tasks', 'status'),
                'clients' => $this->breakdown(\App\Models\Client::class, 'clients', 'status'),
            ],
            'pies' => [
                'expenseCategories' => $this->expenseCategoryBreakdown($period),
                'salesByClient' => $this->salesByClientBreakdown($period),
                'salesByProduct' => $this->salesByProductBreakdown($period),
                'purchaseByVendor' => $this->purchaseByVendorBreakdown($period),
            ],
        ];

        $metrics = $this->metrics($period, $charts);
        $topClients = $this->topClients();
        $topProducts = $this->topProducts($period);
        $recentActivity = $this->recentActivity();
        $alerts = $this->alerts();
        $attention = $this->immediateAttention();
        $moduleHealth = $this->moduleHealth();
        $routes = $this->routes();
        $availableYears = $this->availableYears();

        return view('dashboard.index', compact(
            'range',
            'period',
            'start',
            'end',
            'metrics',
            'charts',
            'topClients',
            'topProducts',
            'recentActivity',
            'alerts',
            'attention',
            'moduleHealth',
            'routes',
            'availableYears'
        ));
    }

    private function periodContext(Request $request): array
    {
        $type = (string) $request->query('period_type', 'year');
        if (! in_array($type, ['range', 'quarter', 'half', 'year', 'multi_year'], true)) {
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

    private function metrics(array $period, array $charts): array
    {
        $start = $period['start'];
        $end = $period['end'];
        $salesTotal = array_sum($charts['salesPurchase']['sales']);
        $purchaseTotal = array_sum($charts['salesPurchase']['purchase']);
        $incomeTotal = array_sum($charts['incomeExpense']['income']);
        $expenseTotal = array_sum($charts['incomeExpense']['expense']);
        $projectInwardRange = array_sum($charts['projectPayments']['inward']);
        $projectOutwardRange = array_sum($charts['projectPayments']['outward']);

        $estimatedProjectValue = $this->sum(\App\Models\Project::class, 'projects', 'estimated_value');
        $projectInwardAll = $this->sum(\App\Models\ProjectPayment::class, 'project_payments', 'amount', function ($query) {
            $query->where('transaction_type', 'inward');
        });
        $projectOutwardAll = $this->sum(\App\Models\ProjectPayment::class, 'project_payments', 'amount', function ($query) {
            $query->where('transaction_type', 'outward');
        });

        return [
            'period_sales' => $salesTotal,
            'period_purchase' => $purchaseTotal,
            'period_gross_margin' => $salesTotal - $purchaseTotal,
            'period_margin_percent' => $salesTotal > 0 ? round((($salesTotal - $purchaseTotal) / $salesTotal) * 100, 2) : 0,
            'period_income' => $incomeTotal,
            'period_expense' => $expenseTotal,
            'period_net_income' => $incomeTotal - $expenseTotal,
            'period_project_inward' => $projectInwardRange,
            'period_project_outward' => $projectOutwardRange,
            'period_project_profit' => $projectInwardRange - $projectOutwardRange,

            'clients_total' => $this->count(\App\Models\Client::class, 'clients'),
            'clients_approved' => $this->count(\App\Models\Client::class, 'clients', function ($query) { $query->where('status', 'approved'); }),
            'clients_under_review' => $this->count(\App\Models\Client::class, 'clients', function ($query) { $query->where('status', 'under_review'); }),

            'leads_total' => $this->count(\App\Models\Lead::class, 'leads'),
            'leads_range' => $this->count(\App\Models\Lead::class, 'leads', function ($query) use ($start, $end) { $query->whereBetween('created_at', [$start, $end]); }),
            'leads_converted' => $this->count(\App\Models\Lead::class, 'leads', function ($query) { $query->whereIn('status', ['converted', 'won', 'closed_won']); }),

            'quotes_total' => $this->count(\App\Models\CustomerQuote::class, 'customer_quotes'),
            'quotes_range' => $this->count(\App\Models\CustomerQuote::class, 'customer_quotes', function ($query) use ($start, $end) { $query->whereBetween('created_at', [$start, $end]); }),
            'quotes_accepted' => $this->count(\App\Models\CustomerQuote::class, 'customer_quotes', function ($query) { $query->where('status', 'accepted'); }),
            'quotes_value' => $this->sum(\App\Models\CustomerQuote::class, 'customer_quotes', 'total_amount'),

            'projects_total' => $this->count(\App\Models\Project::class, 'projects'),
            'projects_active' => $this->count(\App\Models\Project::class, 'projects', function ($query) { $query->whereIn('status', ['planned', 'in_progress', 'waiting_client', 'waiting_vendor', 'on_hold']); }),
            'projects_completed' => $this->count(\App\Models\Project::class, 'projects', function ($query) { $query->where('status', 'completed'); }),
            'projects_at_risk' => $this->count(\App\Models\Project::class, 'projects', function ($query) { $query->whereIn('health', ['amber', 'red']); }),
            'project_estimated_value' => $estimatedProjectValue,
            'project_inward_all' => $projectInwardAll,
            'project_outward_all' => $projectOutwardAll,
            'project_profit_all' => $projectInwardAll - $projectOutwardAll,
            'project_outstanding_all' => max($estimatedProjectValue - $projectInwardAll, 0),
            'project_inward_range' => $projectInwardRange,
            'project_outward_range' => $projectOutwardRange,
            'project_profit_range' => $projectInwardRange - $projectOutwardRange,

            'shipments_total' => $this->count(\App\Models\Shipment::class, 'shipments'),
            'shipments_in_transit' => $this->count(\App\Models\Shipment::class, 'shipments', function ($query) { $query->where('status', 'in_transit'); }),
            'shipments_delivered' => $this->count(\App\Models\Shipment::class, 'shipments', function ($query) { $query->where('status', 'delivered'); }),
            'shipments_delayed' => $this->count(\App\Models\Shipment::class, 'shipments', function ($query) { $query->whereIn('status', ['delayed', 'custom_hold']); }),

            'products_total' => $this->count(\App\Models\Product::class, 'products'),
            'products_active' => $this->count(\App\Models\Product::class, 'products', function ($query) { $query->where('status', 'active'); }),
            'products_ready_stock' => $this->count(\App\Models\Product::class, 'products', function ($query) { $query->where('ready_stock_available', true); }),

            'tasks_total' => $this->count(\App\Models\Task::class, 'tasks'),
            'tasks_open' => $this->count(\App\Models\Task::class, 'tasks', function ($query) { $query->whereNotIn('status', ['completed', 'done', 'closed']); }),
            'tasks_completed' => $this->count(\App\Models\Task::class, 'tasks', function ($query) { $query->whereIn('status', ['completed', 'done', 'closed']); }),
            'tasks_due_today' => $this->dueTodayTasksCount(),
            'tasks_overdue' => $this->overdueTasks(),

            'vendors_total' => $this->count(\App\Models\Vendor::class, 'vendors'),
            'vendor_quotes_total' => $this->count(\App\Models\VendorQuote::class, 'vendor_quotes'),
            'cash_credit_range' => $incomeTotal,
            'cash_debit_range' => $expenseTotal,
            'cash_net_range' => $incomeTotal - $expenseTotal,
            'users_total' => $this->count(\App\Models\User::class, 'users'),
        ];
    }

    private function hasModelTable(string $model, string $table): bool
    {
        return class_exists($model) && Schema::hasTable($table);
    }

    private function count(string $model, string $table, ?callable $callback = null): int
    {
        if (! $this->hasModelTable($model, $table)) {
            return 0;
        }

        $query = $model::query();
        if ($callback) {
            $callback($query);
        }

        return (int) $query->count();
    }

    private function sum(string $model, string $table, string $column, ?callable $callback = null): float
    {
        if (! $this->hasModelTable($model, $table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        $query = $model::query();
        if ($callback) {
            $callback($query);
        }

        return (float) $query->sum($column);
    }

    private function tableColumn(string $table, string $column): bool
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, $column);
    }

    private function dateColumn(string $table, array $columns): ?string
    {
        if (! Schema::hasTable($table)) {
            return null;
        }

        foreach ($columns as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    private function salesSeries(array $period): array
    {
        $dateColumn = $this->dateColumn('customer_quotes', ['quote_date', 'created_at']);
        if (! $dateColumn || ! $this->tableColumn('customer_quotes', 'total_amount')) {
            return $this->zeroSeries($period);
        }

        return $this->sumSeries('customer_quotes', $dateColumn, 'total_amount', $period, function ($query) {
            if (Schema::hasColumn('customer_quotes', 'status')) {
                $query->whereIn('status', ['sent', 'accepted', 'revised']);
            }
        });
    }

    private function purchaseSeries(array $period): array
    {
        if (! Schema::hasTable('vendor_quotes')) {
            return $this->zeroSeries($period);
        }

        $dateColumn = $this->dateColumn('vendor_quotes', ['quote_date', 'created_at']);
        if (! $dateColumn) {
            return $this->zeroSeries($period);
        }

        $hasLandingCost = Schema::hasColumn('vendor_quotes', 'landing_cost_inr');
        $hasUnitPurchase = Schema::hasColumn('vendor_quotes', 'vendor_unit_price') && Schema::hasColumn('vendor_quotes', 'quantity');

        $expression = '0';
        if ($hasLandingCost && $hasUnitPurchase) {
            $expression = 'CASE WHEN COALESCE(landing_cost_inr, 0) > 0 THEN COALESCE(landing_cost_inr, 0) ELSE COALESCE(vendor_unit_price, 0) * COALESCE(quantity, 0) END';
        } elseif ($hasLandingCost) {
            $expression = 'COALESCE(landing_cost_inr, 0)';
        } elseif ($hasUnitPurchase) {
            $expression = 'COALESCE(vendor_unit_price, 0) * COALESCE(quantity, 0)';
        }

        return $this->sumExpressionSeries('vendor_quotes', $dateColumn, $expression, $period, function ($query) {
            if (Schema::hasColumn('vendor_quotes', 'status')) {
                $query->whereIn('status', ['received', 'shortlisted', 'approved', 'converted']);
            }
        });
    }

    private function cashflowSeries(string $amountColumn, array $period): array
    {
        $dateColumn = $this->dateColumn('cashflow_entries', ['entry_date', 'created_at']);
        if (! $dateColumn || ! $this->tableColumn('cashflow_entries', $amountColumn)) {
            return $this->zeroSeries($period);
        }

        return $this->sumSeries('cashflow_entries', $dateColumn, $amountColumn, $period);
    }

    private function projectPaymentSeries(string $type, array $period): array
    {
        $dateColumn = $this->dateColumn('project_payments', ['payment_date', 'created_at']);
        if (! $dateColumn || ! $this->tableColumn('project_payments', 'amount')) {
            return $this->zeroSeries($period);
        }

        return $this->sumSeries('project_payments', $dateColumn, 'amount', $period, function ($query) use ($type) {
            if (Schema::hasColumn('project_payments', 'transaction_type')) {
                $query->where('transaction_type', $type);
            }
        });
    }

    private function zeroSeries(array $period): array
    {
        return array_values(array_fill_keys(array_keys($period['labels']), 0));
    }

    private function subtractSeries(array $a, array $b): array
    {
        $out = [];
        $count = max(count($a), count($b));
        for ($i = 0; $i < $count; $i++) {
            $out[] = round((float) ($a[$i] ?? 0) - (float) ($b[$i] ?? 0), 2);
        }

        return $out;
    }

    private function sumSeries(string $table, string $dateColumn, string $sumColumn, array $period, ?callable $callback = null): array
    {
        return $this->sumExpressionSeries($table, $dateColumn, $sumColumn, $period, $callback);
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

    private function countSeries(string $table, ?string $dateColumn, array $period, ?callable $callback = null): array
    {
        $series = array_fill_keys(array_keys($period['labels']), 0);

        if (! $dateColumn || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $dateColumn)) {
            return array_values($series);
        }

        $bucketSql = $this->bucketSql($dateColumn, $period['group']);
        $query = DB::table($table)
            ->selectRaw($bucketSql.' as bucket, COUNT(*) as total')
            ->whereBetween($dateColumn, [$this->dateBoundary($period['start'], $dateColumn), $this->dateBoundary($period['end'], $dateColumn, true)])
            ->groupBy('bucket')
            ->orderBy('bucket');

        if ($callback) {
            $callback($query);
        }

        foreach ($query->pluck('total', 'bucket')->toArray() as $bucket => $total) {
            if (array_key_exists((string) $bucket, $series)) {
                $series[(string) $bucket] = (int) $total;
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
        $dateOnlyColumns = ['entry_date', 'payment_date', 'quote_date', 'invoice_date', 'pickup_date', 'drop_date', 'start_date', 'target_date'];

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

    private function expenseCategoryBreakdown(array $period): array
    {
        if (! Schema::hasTable('cashflow_entries') || ! Schema::hasColumn('cashflow_entries', 'debit_amount')) {
            return [];
        }

        $dateColumn = $this->dateColumn('cashflow_entries', ['entry_date', 'created_at']);
        if (! $dateColumn) {
            return [];
        }

        $query = DB::table('cashflow_entries')
            ->where('debit_amount', '>', 0)
            ->whereBetween('cashflow_entries.'.$dateColumn, [$this->dateBoundary($period['start'], $dateColumn), $this->dateBoundary($period['end'], $dateColumn, true)]);

        if (Schema::hasTable('cashflow_categories') && Schema::hasColumn('cashflow_entries', 'category_id')) {
            $query->leftJoin('cashflow_categories', 'cashflow_entries.category_id', '=', 'cashflow_categories.id')
                ->selectRaw("COALESCE(cashflow_categories.name, cashflow_entries.expense_head, cashflow_entries.related_party_type, 'Other') as label, SUM(cashflow_entries.debit_amount) as total");
        } else {
            $query->selectRaw("COALESCE(expense_head, related_party_type, 'Other') as label, SUM(debit_amount) as total");
        }

        return $query->groupBy('label')
            ->orderByDesc('total')
            ->limit(10)
            ->pluck('total', 'label')
            ->mapWithKeys(function ($value, $key) {
                return [Str::headline((string) $key) => round((float) $value, 2)];
            })
            ->toArray();
    }

    private function salesByClientBreakdown(array $period): array
    {
        if (! Schema::hasTable('customer_quotes') || ! Schema::hasColumn('customer_quotes', 'total_amount')) {
            return [];
        }

        $dateColumn = $this->dateColumn('customer_quotes', ['quote_date', 'created_at']);
        if (! $dateColumn) {
            return [];
        }

        $query = DB::table('customer_quotes')
            ->whereBetween('customer_quotes.'.$dateColumn, [$this->dateBoundary($period['start'], $dateColumn), $this->dateBoundary($period['end'], $dateColumn, true)]);

        if (Schema::hasColumn('customer_quotes', 'status')) {
            $query->whereIn('customer_quotes.status', ['sent', 'accepted', 'revised']);
        }

        if (Schema::hasTable('clients') && Schema::hasColumn('customer_quotes', 'client_id')) {
            $query->leftJoin('clients', 'customer_quotes.client_id', '=', 'clients.id')
                ->selectRaw("COALESCE(clients.company_name, customer_quotes.customer_company_name, 'Unknown Client') as label, SUM(customer_quotes.total_amount) as total");
        } else {
            $query->selectRaw("COALESCE(customer_company_name, 'Unknown Client') as label, SUM(total_amount) as total");
        }

        return $query->groupBy('label')->orderByDesc('total')->limit(8)->pluck('total', 'label')
            ->mapWithKeys(function ($value, $key) { return [(string) $key => round((float) $value, 2)]; })
            ->toArray();
    }

    private function salesByProductBreakdown(array $period): array
    {
        if (! Schema::hasTable('customer_quote_items') || ! Schema::hasTable('customer_quotes')) {
            return [];
        }

        $dateColumn = $this->dateColumn('customer_quotes', ['quote_date', 'created_at']);
        if (! $dateColumn) {
            return [];
        }

        return DB::table('customer_quote_items')
            ->join('customer_quotes', 'customer_quote_items.customer_quote_id', '=', 'customer_quotes.id')
            ->whereBetween('customer_quotes.'.$dateColumn, [$this->dateBoundary($period['start'], $dateColumn), $this->dateBoundary($period['end'], $dateColumn, true)])
            ->when(Schema::hasColumn('customer_quotes', 'status'), function ($query) {
                $query->whereIn('customer_quotes.status', ['sent', 'accepted', 'revised']);
            })
            ->selectRaw("COALESCE(customer_quote_items.product_name, 'Product') as label, SUM(customer_quote_items.amount) as total")
            ->groupBy('label')
            ->orderByDesc('total')
            ->limit(8)
            ->pluck('total', 'label')
            ->mapWithKeys(function ($value, $key) { return [(string) $key => round((float) $value, 2)]; })
            ->toArray();
    }

    private function purchaseByVendorBreakdown(array $period): array
    {
        if (! Schema::hasTable('vendor_quotes')) {
            return [];
        }

        $dateColumn = $this->dateColumn('vendor_quotes', ['quote_date', 'created_at']);
        if (! $dateColumn) {
            return [];
        }

        $hasLandingCost = Schema::hasColumn('vendor_quotes', 'landing_cost_inr');
        $hasUnitPurchase = Schema::hasColumn('vendor_quotes', 'vendor_unit_price') && Schema::hasColumn('vendor_quotes', 'quantity');

        $expression = '0';
        if ($hasLandingCost && $hasUnitPurchase) {
            $expression = 'CASE WHEN COALESCE(landing_cost_inr, 0) > 0 THEN COALESCE(landing_cost_inr, 0) ELSE COALESCE(vendor_unit_price, 0) * COALESCE(quantity, 0) END';
        } elseif ($hasLandingCost) {
            $expression = 'COALESCE(landing_cost_inr, 0)';
        } elseif ($hasUnitPurchase) {
            $expression = 'COALESCE(vendor_unit_price, 0) * COALESCE(quantity, 0)';
        }

        return DB::table('vendor_quotes')
            ->whereBetween($dateColumn, [$this->dateBoundary($period['start'], $dateColumn), $this->dateBoundary($period['end'], $dateColumn, true)])
            ->selectRaw("COALESCE(vendor_name, 'Vendor') as label, SUM(".$expression.") as total")
            ->groupBy('label')
            ->orderByDesc('total')
            ->limit(8)
            ->pluck('total', 'label')
            ->mapWithKeys(function ($value, $key) { return [(string) $key => round((float) $value, 2)]; })
            ->toArray();
    }

    private function breakdown(string $model, string $table, string $column): array
    {
        if (! $this->hasModelTable($model, $table) || ! Schema::hasColumn($table, $column)) {
            return [];
        }

        return $model::query()
            ->select($column, DB::raw('COUNT(*) as total'))
            ->groupBy($column)
            ->orderByDesc('total')
            ->pluck('total', $column)
            ->mapWithKeys(function ($value, $key) {
                return [Str::headline((string) $key) => (int) $value];
            })
            ->toArray();
    }

    private function immediateAttention(): array
    {
        return [
            'today_tasks' => $this->todayDueTaskList(8),
            'overdue_tasks' => $this->overdueTaskList(8),
            'in_transit_shipments' => $this->inTransitShipmentDetails(8),
            'kyc_submissions' => $this->recentKycSubmissions(8),
            'portal_documents' => $this->newPortalDocuments(8),
            'portal_comments' => $this->newPortalComments(8),
        ];
    }

    private function taskDueDateColumn(): ?string
    {
        if (! $this->hasModelTable(\App\Models\Task::class, 'tasks')) {
            return null;
        }

        return Schema::hasColumn('tasks', 'due_date') ? 'due_date' : (Schema::hasColumn('tasks', 'deadline') ? 'deadline' : null);
    }

    private function dueTodayTasksCount(): int
    {
        $dateColumn = $this->taskDueDateColumn();
        if (! $dateColumn) {
            return 0;
        }

        return (int) \App\Models\Task::query()
            ->whereDate($dateColumn, today())
            ->whereNotIn('status', ['completed', 'done', 'closed'])
            ->count();
    }

    private function todayDueTaskList(int $limit = 8): array
    {
        $dateColumn = $this->taskDueDateColumn();
        if (! $dateColumn) {
            return [];
        }

        $query = \App\Models\Task::query();
        if (method_exists(\App\Models\Task::class, 'assignee')) {
            $query->with('assignee');
        }

        return $query->whereDate($dateColumn, today())
            ->whereNotIn('status', ['completed', 'done', 'closed'])
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(function ($task) use ($dateColumn) {
                return [
                    'title' => $task->title ?? 'Task #'.$task->id,
                    'priority' => $task->priority ?? '-',
                    'status' => $task->status ?? '-',
                    'assignee' => ($task->relationLoaded('assignee') && $task->assignee) ? $task->assignee->name : 'Unassigned',
                    'due' => $task->{$dateColumn} ? Carbon::parse($task->{$dateColumn})->format('d M Y') : '-',
                    'url' => $this->routeUrl('tasks.index'),
                ];
            })
            ->toArray();
    }

    private function overdueTasks(): int
    {
        $dateColumn = $this->taskDueDateColumn();
        if (! $dateColumn) {
            return 0;
        }

        return (int) \App\Models\Task::query()
            ->whereDate($dateColumn, '<', today())
            ->whereNotIn('status', ['completed', 'done', 'closed'])
            ->count();
    }

    private function overdueTaskList(int $limit = 8): array
    {
        $dateColumn = $this->taskDueDateColumn();
        if (! $dateColumn) {
            return [];
        }

        $query = \App\Models\Task::query();
        if (method_exists(\App\Models\Task::class, 'assignee')) {
            $query->with('assignee');
        }

        return $query->whereDate($dateColumn, '<', today())
            ->whereNotIn('status', ['completed', 'done', 'closed'])
            ->orderBy($dateColumn)
            ->limit($limit)
            ->get()
            ->map(function ($task) use ($dateColumn) {
                return [
                    'title' => $task->title ?? 'Task #'.$task->id,
                    'priority' => $task->priority ?? '-',
                    'status' => $task->status ?? '-',
                    'assignee' => ($task->relationLoaded('assignee') && $task->assignee) ? $task->assignee->name : 'Unassigned',
                    'due' => $task->{$dateColumn} ? Carbon::parse($task->{$dateColumn})->format('d M Y') : '-',
                    'days_overdue' => $task->{$dateColumn} ? Carbon::parse($task->{$dateColumn})->diffInDays(today()) : 0,
                    'url' => $this->routeUrl('tasks.index'),
                ];
            })
            ->toArray();
    }

    private function inTransitShipmentDetails(int $limit = 8): array
    {
        if (! $this->hasModelTable(\App\Models\Shipment::class, 'shipments')) {
            return [];
        }

        $query = \App\Models\Shipment::query();
        if (method_exists(\App\Models\Shipment::class, 'histories')) {
            $query->with(['histories' => function ($history) {
                $history->latest('event_time')->latest('id')->limit(1);
            }]);
        }

        return $query->where('status', 'in_transit')
            ->latest('pickup_date')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(function ($shipment) {
                $latest = ($shipment->relationLoaded('histories') && $shipment->histories->count()) ? $shipment->histories->first() : null;
                return [
                    'number' => $shipment->shipment_number ?? '#'.$shipment->id,
                    'identity' => $shipment->identity_name ?? 'Shipment',
                    'route' => trim(($shipment->from_name ?: $shipment->from_city ?: '-').' → '.($shipment->to_name ?: $shipment->to_city ?: '-')),
                    'partner' => $shipment->logistic_partner ?: '-',
                    'tracking' => $shipment->tracking_number ?: 'No tracking',
                    'pickup' => $shipment->pickup_date ? Carbon::parse($shipment->pickup_date)->format('d M Y') : '-',
                    'latest' => $latest ? trim(($latest->location ?: '').' '.$latest->remarks) : 'No latest public update',
                    'url' => Route::has('shipments.show') ? route('shipments.show', $shipment->id) : '#',
                ];
            })
            ->toArray();
    }

    private function recentKycSubmissions(int $limit = 8): array
    {
        if (! $this->hasModelTable(\App\Models\Client::class, 'clients') || ! Schema::hasColumn('clients', 'kyc_submitted_at')) {
            return [];
        }

        $query = \App\Models\Client::query()->whereNotNull('kyc_submitted_at');

        if (Schema::hasColumn('clients', 'status')) {
            $query->where(function ($nested) {
                $nested->where('status', 'under_review')
                    ->orWhere('kyc_submitted_at', '>=', now()->subDays(7));
            });
        } else {
            $query->where('kyc_submitted_at', '>=', now()->subDays(7));
        }

        return $query->latest('kyc_submitted_at')
            ->limit($limit)
            ->get()
            ->map(function ($client) {
                return [
                    'client' => $client->company_name ?? 'Client #'.$client->id,
                    'number' => $client->client_number ?? '#'.$client->id,
                    'status' => method_exists($client, 'statusLabel') ? $client->statusLabel() : Str::headline((string) ($client->status ?? '-')),
                    'submitted' => $client->kyc_submitted_at ? Carbon::parse($client->kyc_submitted_at)->format('d M Y, h:i A') : '-',
                    'url' => Route::has('clients.show') ? route('clients.show', $client->id) : '#',
                ];
            })
            ->toArray();
    }

    private function newPortalDocuments(int $limit = 8): array
    {
        if (! Schema::hasTable('client_portal_documents')) {
            return [];
        }

        $query = DB::table('client_portal_documents')
            ->leftJoin('clients', 'client_portal_documents.client_id', '=', 'clients.id')
            ->leftJoin('client_portal_users', 'client_portal_documents.client_portal_user_id', '=', 'client_portal_users.id')
            ->select(
                'client_portal_documents.id',
                'client_portal_documents.client_id',
                'client_portal_documents.title',
                'client_portal_documents.original_name',
                'client_portal_documents.category',
                'client_portal_documents.related_type',
                'client_portal_documents.related_id',
                'client_portal_documents.created_at',
                'clients.company_name',
                'client_portal_users.name as portal_user_name',
                'client_portal_users.username as portal_username'
            );

        if (Schema::hasColumn('client_portal_documents', 'is_reviewed')) {
            $query->where('client_portal_documents.is_reviewed', false);
        }

        return $query->latest('client_portal_documents.id')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                return [
                    'title' => $row->title ?: ($row->original_name ?: 'Client document'),
                    'client' => $row->company_name ?: 'Client #'.$row->client_id,
                    'uploaded_by' => $row->portal_user_name ?: ($row->portal_username ?: 'Client'),
                    'category' => Str::headline((string) $row->category),
                    'related' => Str::headline((string) ($row->related_type ?: 'general')).($row->related_id ? ' #'.$row->related_id : ''),
                    'date' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y, h:i A') : '-',
                    'url' => Route::has('clients.portal.show') ? route('clients.portal.show', $row->client_id) : (Route::has('clients.show') ? route('clients.show', $row->client_id) : '#'),
                ];
            })
            ->toArray();
    }

    private function newPortalComments(int $limit = 8): array
    {
        if (! Schema::hasTable('client_portal_comments')) {
            return [];
        }

        $query = DB::table('client_portal_comments')
            ->leftJoin('clients', 'client_portal_comments.client_id', '=', 'clients.id')
            ->leftJoin('client_portal_users', 'client_portal_comments.client_portal_user_id', '=', 'client_portal_users.id')
            ->where('client_portal_comments.author_type', 'client')
            ->select(
                'client_portal_comments.id',
                'client_portal_comments.client_id',
                'client_portal_comments.body',
                'client_portal_comments.related_type',
                'client_portal_comments.related_id',
                'client_portal_comments.created_at',
                'clients.company_name',
                'client_portal_users.name as portal_user_name',
                'client_portal_users.username as portal_username'
            );

        if (Schema::hasColumn('client_portal_comments', 'is_read_by_internal')) {
            $query->where('client_portal_comments.is_read_by_internal', false);
        }

        return $query->latest('client_portal_comments.id')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                return [
                    'body' => Str::limit((string) $row->body, 90),
                    'client' => $row->company_name ?: 'Client #'.$row->client_id,
                    'author' => $row->portal_user_name ?: ($row->portal_username ?: 'Client'),
                    'related' => Str::headline((string) ($row->related_type ?: 'general')).($row->related_id ? ' #'.$row->related_id : ''),
                    'date' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y, h:i A') : '-',
                    'url' => Route::has('clients.portal.show') ? route('clients.portal.show', $row->client_id) : (Route::has('clients.show') ? route('clients.show', $row->client_id) : '#'),
                ];
            })
            ->toArray();
    }

    private function topClients(): array
    {
        if (! $this->hasModelTable(\App\Models\Project::class, 'projects') || ! $this->hasModelTable(\App\Models\Client::class, 'clients')) {
            return [];
        }

        return \App\Models\Project::query()
            ->with('client')
            ->select('client_id', DB::raw('COUNT(*) as project_count'), DB::raw('SUM(estimated_value) as total_value'), DB::raw('AVG(progress_percent) as avg_progress'))
            ->whereNotNull('client_id')
            ->groupBy('client_id')
            ->orderByDesc('total_value')
            ->limit(8)
            ->get()
            ->map(function ($row) {
                return [
                    'name' => $row->client ? $row->client->company_name : 'Client #'.$row->client_id,
                    'projects' => (int) $row->project_count,
                    'value' => (float) $row->total_value,
                    'progress' => round((float) $row->avg_progress, 1),
                    'url' => Route::has('clients.show') ? route('clients.show', $row->client_id) : '#',
                ];
            })
            ->toArray();
    }

    private function topProducts(array $period): array
    {
        if (! Schema::hasTable('customer_quote_items') || ! Schema::hasTable('customer_quotes')) {
            return [];
        }

        $dateColumn = $this->dateColumn('customer_quotes', ['quote_date', 'created_at']);
        if (! $dateColumn) {
            return [];
        }

        return DB::table('customer_quote_items')
            ->join('customer_quotes', 'customer_quote_items.customer_quote_id', '=', 'customer_quotes.id')
            ->whereBetween('customer_quotes.'.$dateColumn, [$this->dateBoundary($period['start'], $dateColumn), $this->dateBoundary($period['end'], $dateColumn, true)])
            ->select('customer_quote_items.product_name', DB::raw('SUM(customer_quote_items.quantity) as qty'), DB::raw('SUM(customer_quote_items.amount) as value'), DB::raw('COUNT(*) as quote_count'))
            ->groupBy('customer_quote_items.product_name')
            ->orderByDesc('value')
            ->limit(8)
            ->get()
            ->map(function ($row) {
                return [
                    'name' => $row->product_name ?: 'Product',
                    'qty' => (float) $row->qty,
                    'value' => (float) $row->value,
                    'quotes' => (int) $row->quote_count,
                ];
            })
            ->toArray();
    }

    private function recentActivity(): array
    {
        $items = [];

        $this->pushRecent($items, \App\Models\Project::class, 'projects', 'project', 'Project', 'name', 'project_number', 'projects.show');
        $this->pushRecent($items, \App\Models\Shipment::class, 'shipments', 'shipment', 'Shipment', 'identity_name', 'shipment_number', 'shipments.show');
        $this->pushRecent($items, \App\Models\CustomerQuote::class, 'customer_quotes', 'quote', 'Quote', 'title', 'quote_number', 'customer-quotes.show');
        $this->pushRecent($items, \App\Models\Lead::class, 'leads', 'lead', 'Lead', 'title', 'lead_number', 'leads.show');
        $this->pushRecent($items, \App\Models\Client::class, 'clients', 'client', 'Client', 'company_name', 'client_number', 'clients.show');

        usort($items, function ($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });

        return array_slice($items, 0, 14);
    }

    private function pushRecent(array &$items, string $model, string $table, string $type, string $label, string $titleColumn, string $numberColumn, string $routeName): void
    {
        if (! $this->hasModelTable($model, $table)) {
            return;
        }

        $rows = $model::query()->latest('id')->limit(5)->get();

        foreach ($rows as $row) {
            $items[] = [
                'type' => $type,
                'label' => $label,
                'title' => $row->{$titleColumn} ?? $label,
                'number' => $row->{$numberColumn} ?? '#'.$row->id,
                'status' => $row->status ?? null,
                'date' => $row->created_at ? $row->created_at->format('d M Y, h:i A') : '-',
                'timestamp' => $row->created_at ? $row->created_at->timestamp : 0,
                'url' => Route::has($routeName) ? route($routeName, $row->id) : '#',
            ];
        }
    }

    private function alerts(): array
    {
        $alerts = [];

        if ($this->hasModelTable(\App\Models\Project::class, 'projects')) {
            $redProjects = \App\Models\Project::query()->where('health', 'red')->whereNotIn('status', ['completed', 'cancelled'])->latest('id')->limit(5)->get();
            foreach ($redProjects as $project) {
                $alerts[] = ['level' => 'danger', 'title' => 'Critical project', 'message' => $project->name.' needs attention.', 'url' => Route::has('projects.show') ? route('projects.show', $project->id) : '#'];
            }
        }

        if ($this->hasModelTable(\App\Models\Shipment::class, 'shipments')) {
            $shipments = \App\Models\Shipment::query()->whereIn('status', ['delayed', 'custom_hold'])->latest('id')->limit(5)->get();
            foreach ($shipments as $shipment) {
                $alerts[] = ['level' => 'warning', 'title' => 'Shipment alert', 'message' => $shipment->shipment_number.' - '.$shipment->statusLabel(), 'url' => Route::has('shipments.show') ? route('shipments.show', $shipment->id) : '#'];
            }
        }

        $overdue = $this->overdueTasks();
        if ($overdue > 0) {
            $alerts[] = ['level' => 'danger', 'title' => 'Overdue tasks', 'message' => $overdue.' task(s) are overdue.', 'url' => Route::has('tasks.index') ? route('tasks.index') : '#'];
        }

        return array_slice($alerts, 0, 10);
    }

    private function moduleHealth(): array
    {
        return [
            ['name' => 'Tasks', 'installed' => $this->hasModelTable(\App\Models\Task::class, 'tasks'), 'count' => $this->count(\App\Models\Task::class, 'tasks'), 'route' => $this->routeUrl('tasks.index')],
            ['name' => 'Clients', 'installed' => $this->hasModelTable(\App\Models\Client::class, 'clients'), 'count' => $this->count(\App\Models\Client::class, 'clients'), 'route' => $this->routeUrl('clients.index')],
            ['name' => 'Leads', 'installed' => $this->hasModelTable(\App\Models\Lead::class, 'leads'), 'count' => $this->count(\App\Models\Lead::class, 'leads'), 'route' => $this->routeUrl('leads.index')],
            ['name' => 'Products', 'installed' => $this->hasModelTable(\App\Models\Product::class, 'products'), 'count' => $this->count(\App\Models\Product::class, 'products'), 'route' => $this->routeUrl('products.index')],
            ['name' => 'Projects', 'installed' => $this->hasModelTable(\App\Models\Project::class, 'projects'), 'count' => $this->count(\App\Models\Project::class, 'projects'), 'route' => $this->routeUrl('projects.index')],
            ['name' => 'Shipments', 'installed' => $this->hasModelTable(\App\Models\Shipment::class, 'shipments'), 'count' => $this->count(\App\Models\Shipment::class, 'shipments'), 'route' => $this->routeUrl('shipments.index')],
            ['name' => 'Cashflow', 'installed' => $this->hasModelTable(\App\Models\CashflowEntry::class, 'cashflow_entries'), 'count' => $this->count(\App\Models\CashflowEntry::class, 'cashflow_entries'), 'route' => $this->routeUrl('cashflows.index')],
            ['name' => 'Vendors', 'installed' => $this->hasModelTable(\App\Models\Vendor::class, 'vendors'), 'count' => $this->count(\App\Models\Vendor::class, 'vendors'), 'route' => $this->routeUrl('vendors.index')],
        ];
    }

    private function availableYears(): array
    {
        $current = (int) now()->format('Y');
        $min = $current - 5;

        foreach ([
            ['customer_quotes', 'quote_date'],
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

    private function routes(): array
    {
        return [
            'tasks' => $this->routeUrl('tasks.index'),
            'clients' => $this->routeUrl('clients.index'),
            'leads' => $this->routeUrl('leads.index'),
            'products' => $this->routeUrl('products.index'),
            'projects' => $this->routeUrl('projects.index'),
            'shipments' => $this->routeUrl('shipments.index'),
            'cashflows' => $this->routeUrl('cashflows.index'),
            'quotes' => $this->routeUrl('customer-quotes.index'),
            'vendors' => $this->routeUrl('vendors.index'),
            'users' => $this->routeUrl('users.index'),
        ];
    }

    private function routeUrl(string $name): string
    {
        return Route::has($name) ? route($name) : '#';
    }
}
