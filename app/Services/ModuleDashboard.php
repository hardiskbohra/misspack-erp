<?php

namespace App\Services;

use App\Models\CashflowEntry;
use App\Models\ProjectCoordinatorQueue;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class ModuleDashboard
{
    public const MODULES = [
        'sales' => ['label' => 'Sales', 'icon' => 'fa-solid fa-chart-line', 'description' => 'Leads, clients, invoicing and collections'],
        'purchase' => ['label' => 'Purchase', 'icon' => 'fa-solid fa-cart-shopping', 'description' => 'Purchase orders, vendor bills and payables'],
        'projects' => ['label' => 'Projects', 'icon' => 'fa-solid fa-diagram-project', 'description' => 'Execution health, workflow and exceptions'],
        'operations' => ['label' => 'Operations', 'icon' => 'fa-solid fa-truck-fast', 'description' => 'Packing, shipments and delivery reconciliation'],
        'finance' => ['label' => 'Finance', 'icon' => 'fa-solid fa-scale-balanced', 'description' => 'Cash movement, obligations and ledger controls'],
        'work' => ['label' => 'Work & People', 'icon' => 'fa-solid fa-list-check', 'description' => 'Team capacity, coordinator work, tasks and ownership'],
    ];

    public function build(string $module, array $period): array
    {
        return match ($module) {
            'purchase' => $this->purchase($period),
            'projects' => $this->projects($period),
            'operations' => $this->operations($period),
            'finance' => $this->finance($period),
            'work' => $this->work($period),
            default => $this->sales($period),
        } + self::MODULES[$module];
    }

    private function sales(array $period): array
    {
        $sales = fn (Carbon $start, Carbon $end) => $this->sumRange('sales_invoices', 'invoice_date', 'total_amount', $start, $end, fn ($query) => $query->where('invoice_type', 'tax')->where('status', '!=', 'cancelled'));
        $invoiceCount = fn (Carbon $start, Carbon $end) => $this->countRange('sales_invoices', 'invoice_date', $start, $end, fn ($query) => $query->where('invoice_type', 'tax')->where('status', '!=', 'cancelled'));
        $leadCount = fn (Carbon $start, Carbon $end) => $this->countRange('leads', 'created_at', $start, $end);
        $converted = fn (Carbon $start, Carbon $end) => $this->countRange('leads', 'updated_at', $start, $end, fn ($query) => $query->whereIn('status', ['converted', 'won', 'closed_won']));
        $invoices = Schema::hasTable('sales_invoices') ? SalesInvoice::query()->with(['payments', 'cashflowAllocations'])->where('status', '!=', 'cancelled')->get() : collect();
        $receivable = round((float) $invoices->sum(fn ($invoice) => $invoice->balanceDue()), 2);
        $overdue = $invoices->filter(fn ($invoice) => $invoice->isOverdue())->values();

        return [
            'metrics' => [
                $this->metric('Period sales', $sales, $period, 'money', 'Tax invoices raised', 'green', $this->url('sales-invoices.index')),
                $this->metric('Tax invoices', $invoiceCount, $period, 'number', 'Issued in selected period', 'blue', $this->url('sales-invoices.index')),
                $this->metric('New leads', $leadCount, $period, 'number', 'Created in selected period', 'purple', $this->url('leads.index')),
                $this->metric('Converted leads', $converted, $period, 'number', 'Won or converted in period', 'green', $this->url('leads.index')),
                $this->pointMetric('Receivables', $receivable, 'money', 'Open across all active client documents', 'orange', $this->url('sales-invoices.index')),
                $this->pointMetric('Overdue invoices', $overdue->count(), 'number', 'Past due with a live balance', $overdue->isNotEmpty() ? 'red' : 'green', $this->url('sales-invoices.index')),
            ],
            'trend' => $this->series('sales_invoices', 'invoice_date', 'total_amount', $period, fn ($query) => $query->where('invoice_type', 'tax')->where('status', '!=', 'cancelled')),
            'trend_label' => 'Tax invoice value', 'trend_format' => 'money',
            'breakdowns' => [
                ['title' => 'Invoice status', 'items' => $this->breakdown('sales_invoices', 'status', fn ($query) => $query->where('invoice_type', 'tax'))],
                ['title' => 'Lead pipeline', 'items' => $this->breakdown('leads', 'status')],
            ],
            'alerts' => $overdue->sortByDesc(fn ($invoice) => $invoice->balanceDue())->take(8)->map(fn ($invoice) => [
                'level' => 'critical', 'title' => $invoice->invoice_number,
                'message' => 'Overdue client balance '.\App\Helpers\CommonHelper::amount($invoice->balanceDue(), $invoice->currency),
                'url' => $this->url('sales-invoices.show', $invoice),
            ])->values()->all(),
        ];
    }

    private function purchase(array $period): array
    {
        $bills = fn (Carbon $start, Carbon $end) => $this->sumRange('purchase_invoices', 'invoice_date', 'total_amount', $start, $end, fn ($query) => $query->where('invoice_type', 'bill')->where('status', '!=', 'cancelled'));
        $orders = fn (Carbon $start, Carbon $end) => $this->sumRange('purchase_invoices', 'invoice_date', 'total_amount', $start, $end, fn ($query) => $query->where('invoice_type', 'order')->where('status', '!=', 'cancelled'));
        $billCount = fn (Carbon $start, Carbon $end) => $this->countRange('purchase_invoices', 'invoice_date', $start, $end, fn ($query) => $query->where('invoice_type', 'bill')->where('status', '!=', 'cancelled'));
        $orderCount = fn (Carbon $start, Carbon $end) => $this->countRange('purchase_invoices', 'invoice_date', $start, $end, fn ($query) => $query->where('invoice_type', 'order')->where('status', '!=', 'cancelled'));
        $documents = Schema::hasTable('purchase_invoices') ? PurchaseInvoice::query()->with(['payments', 'cashflowAllocations'])->where('invoice_type', 'bill')->where('status', '!=', 'cancelled')->get() : collect();
        $payable = round((float) $documents->sum(fn ($bill) => $bill->balanceDue()), 2);
        $overdue = $documents->filter(fn ($bill) => $bill->isOverdue())->values();

        return [
            'metrics' => [
                $this->metric('Vendor bills', $bills, $period, 'money', 'Bills received in period', 'orange', $this->url('purchase-invoices.index')),
                $this->metric('Purchase orders', $orders, $period, 'money', 'Orders raised in period', 'blue', $this->url('purchase-invoices.index')),
                $this->metric('Bill count', $billCount, $period, 'number', 'Payable documents received', 'purple', $this->url('purchase-invoices.index')),
                $this->metric('Order count', $orderCount, $period, 'number', 'Procurement documents raised', 'blue', $this->url('purchase-invoices.index')),
                $this->pointMetric('Payables', $payable, 'money', 'Open vendor bill balances', 'orange', $this->url('purchase-invoices.index')),
                $this->pointMetric('Overdue bills', $overdue->count(), 'number', 'Past due and unpaid', $overdue->isNotEmpty() ? 'red' : 'green', $this->url('purchase-invoices.index')),
            ],
            'trend' => $this->series('purchase_invoices', 'invoice_date', 'total_amount', $period, fn ($query) => $query->where('invoice_type', 'bill')->where('status', '!=', 'cancelled')),
            'trend_label' => 'Vendor bill value', 'trend_format' => 'money',
            'breakdowns' => [
                ['title' => 'Bill status', 'items' => $this->breakdown('purchase_invoices', 'status', fn ($query) => $query->where('invoice_type', 'bill'))],
                ['title' => 'Order status', 'items' => $this->breakdown('purchase_invoices', 'status', fn ($query) => $query->where('invoice_type', 'order'))],
            ],
            'alerts' => $overdue->sortByDesc(fn ($bill) => $bill->balanceDue())->take(8)->map(fn ($bill) => [
                'level' => 'critical', 'title' => $bill->referenceNumber(),
                'message' => 'Overdue vendor balance '.\App\Helpers\CommonHelper::amount($bill->balanceDue(), $bill->currency),
                'url' => $this->url('purchase-invoices.show', $bill),
            ])->values()->all(),
        ];
    }

    private function projects(array $period): array
    {
        $started = fn (Carbon $start, Carbon $end) => $this->countRange('projects', 'start_date', $start, $end);
        $completed = fn (Carbon $start, Carbon $end) => $this->countRange('projects', 'completed_at', $start, $end, fn ($query) => $query->whereIn('status', ['completed', 'closed', 'closed_with_outstanding_financials']));
        $active = $this->countWhere('projects', fn ($query) => $query->whereIn('status', ['planned', 'in_progress', 'waiting_client', 'waiting_vendor', 'on_hold']));
        $atRisk = $this->countWhere('projects', fn ($query) => $query->whereIn('health', ['amber', 'red'])->whereNotIn('status', ['closed', 'cancelled']));
        $exceptions = $this->countWhere('project_exceptions', fn ($query) => $query->whereIn('status', ['open', 'acknowledged']));
        $unassigned = $this->countWhere('projects', fn ($query) => $query->whereNull('assigned_to')->whereNotIn('status', ['closed', 'cancelled']));
        $alerts = Schema::hasTable('project_exceptions') ? DB::table('project_exceptions')->join('projects', 'projects.id', '=', 'project_exceptions.project_id')->select('project_exceptions.*', 'projects.project_number')->whereIn('project_exceptions.status', ['open', 'acknowledged'])->orderByRaw("case when project_exceptions.severity = 'critical' then 0 else 1 end")->orderByDesc('project_exceptions.escalation_level')->limit(8)->get() : collect();

        return [
            'metrics' => [
                $this->metric('Projects started', $started, $period, 'number', 'Started in selected period', 'blue', $this->url('projects.index')),
                $this->metric('Projects completed', $completed, $period, 'number', 'Operational or financial closure', 'green', $this->url('projects.index')),
                $this->pointMetric('Active projects', $active, 'number', 'Currently in execution', 'blue', $this->url('projects.index')),
                $this->pointMetric('At risk', $atRisk, 'number', 'Amber or red project health', $atRisk ? 'red' : 'green', $this->url('projects.index')),
                $this->pointMetric('Active exceptions', $exceptions, 'number', 'Open or in follow-up', $exceptions ? 'red' : 'green', $this->url('projects.exceptions.index')),
                $this->pointMetric('No coordinator', $unassigned, 'number', 'Active projects needing ownership', $unassigned ? 'orange' : 'green', $this->url('projects.work-queue.index', ['scope' => 'team', 'attention' => 'unassigned'])),
            ],
            'trend' => $this->series('projects', 'start_date', null, $period),
            'trend_label' => 'Projects started', 'trend_format' => 'number',
            'breakdowns' => [
                ['title' => 'Project health', 'items' => $this->breakdown('projects', 'health')],
                ['title' => 'Calculated workflow stage', 'items' => $this->breakdown('project_workflows', 'calculated_stage')],
            ],
            'alerts' => $alerts->map(fn ($row) => [
                'level' => $row->severity === 'critical' ? 'critical' : 'warning', 'title' => $row->project_number,
                'message' => $row->title, 'url' => $this->url('projects.exceptions.index', ['status' => 'active', 'focus' => $row->id]),
            ])->all(),
        ];
    }

    private function operations(array $period): array
    {
        $shipmentCount = fn (Carbon $start, Carbon $end) => $this->countRange('shipments', 'pickup_date', $start, $end);
        $shipmentCost = fn (Carbon $start, Carbon $end) => $this->sumRange('shipments', 'pickup_date', 'shipment_cost', $start, $end, fn ($query) => $query->where('status', '!=', 'cancelled'));
        $packingCount = fn (Carbon $start, Carbon $end) => $this->countRange('packaging_lists', 'packing_date', $start, $end);
        $delivered = fn (Carbon $start, Carbon $end) => $this->countRange('shipments', 'drop_date', $start, $end, fn ($query) => $query->where('status', 'delivered'));
        $inTransit = $this->countWhere('shipments', fn ($query) => $query->whereIn('status', ['picked_up', 'in_transit', 'out_for_delivery']));
        $delayed = $this->countWhere('shipments', fn ($query) => $query->whereIn('status', ['delayed', 'custom_hold']));
        $alerts = Schema::hasTable('shipments') ? DB::table('shipments')->whereIn('status', ['delayed', 'custom_hold'])->latest('updated_at')->limit(8)->get() : collect();

        return [
            'metrics' => [
                $this->metric('Shipments started', $shipmentCount, $period, 'number', 'Picked up in selected period', 'blue', $this->url('shipments.index')),
                $this->metric('Delivered', $delivered, $period, 'number', 'Delivery completed in period', 'green', $this->url('shipments.index')),
                $this->metric('Freight cost', $shipmentCost, $period, 'money', 'Shipment cost in period', 'purple', $this->url('shipments.index')),
                $this->metric('Packaging lists', $packingCount, $period, 'number', 'Prepared in selected period', 'blue', $this->url('packaging-lists.index')),
                $this->pointMetric('In transit', $inTransit, 'number', 'Currently moving', 'orange', $this->url('shipments.index')),
                $this->pointMetric('Delayed / hold', $delayed, 'number', 'Needs logistics intervention', $delayed ? 'red' : 'green', $this->url('shipments.index')),
            ],
            'trend' => $this->series('shipments', 'pickup_date', null, $period),
            'trend_label' => 'Shipments started', 'trend_format' => 'number',
            'breakdowns' => [
                ['title' => 'Shipment status', 'items' => $this->breakdown('shipments', 'status')],
                ['title' => 'Packaging status', 'items' => $this->breakdown('packaging_lists', 'status')],
            ],
            'alerts' => $alerts->map(fn ($row) => [
                'level' => 'critical', 'title' => $row->shipment_number,
                'message' => str($row->status)->headline().' · '.($row->delay_reason ?: 'Review shipment exception'),
                'url' => $this->url('shipments.show', $row->id),
            ])->all(),
        ];
    }

    private function finance(array $period): array
    {
        $inward = fn (Carbon $start, Carbon $end) => $this->sumRange('cashflow_entries', 'entry_date', 'credit_amount', $start, $end, fn ($query) => $this->excludeTransfers($query));
        $outward = fn (Carbon $start, Carbon $end) => $this->sumRange('cashflow_entries', 'entry_date', 'debit_amount', $start, $end, fn ($query) => $this->excludeTransfers($query));
        $net = fn (Carbon $start, Carbon $end) => $inward($start, $end) - $outward($start, $end);
        $entries = Schema::hasTable('cashflow_entries') ? CashflowEntry::query()->with('allocations')->get() : collect();
        $unallocatedEntries = $entries->filter(fn ($entry) => $entry->unallocatedAmount() > 0.009)->values();
        $unallocated = round((float) $unallocatedEntries->sum(fn ($entry) => $entry->unallocatedAmount()), 2);
        $tentative = $this->countWhere('cashflow_entries', fn ($query) => $query->whereNotIn('accounting_status', ['booked', 'reconciled']));
        $salesDocs = Schema::hasTable('sales_invoices') ? SalesInvoice::query()->with(['payments', 'cashflowAllocations'])->where('status', '!=', 'cancelled')->get() : collect();
        $purchaseDocs = Schema::hasTable('purchase_invoices') ? PurchaseInvoice::query()->with(['payments', 'cashflowAllocations'])->where('invoice_type', 'bill')->where('status', '!=', 'cancelled')->get() : collect();
        $receivable = round((float) $salesDocs->sum(fn ($invoice) => $invoice->balanceDue()), 2);
        $payable = round((float) $purchaseDocs->sum(fn ($bill) => $bill->balanceDue()), 2);

        return [
            'metrics' => [
                $this->metric('Inward movement', $inward, $period, 'money', 'Cashflow credits; not income classification', 'green', $this->url('cashflows.index')),
                $this->metric('Outward movement', $outward, $period, 'money', 'Cashflow debits; not expense classification', 'red', $this->url('cashflows.index')),
                $this->metric('Net movement', $net, $period, 'money', 'Inward less outward movement', 'blue', $this->url('cashflows.index')),
                $this->pointMetric('Receivables', $receivable, 'money', 'Open client documents', 'orange', $this->url('sales-invoices.index')),
                $this->pointMetric('Payables', $payable, 'money', 'Open vendor bills', 'orange', $this->url('purchase-invoices.index')),
                $this->pointMetric('Unallocated entries', $unallocatedEntries->count(), 'number', $tentative.' tentative ledger entries', ($unallocatedEntries->isNotEmpty() || $tentative) ? 'red' : 'green', $this->url('cashflows.index')),
            ],
            'trend' => $this->dualSeries('cashflow_entries', 'entry_date', 'credit_amount', 'debit_amount', $period, fn ($query) => $this->excludeTransfers($query)),
            'trend_label' => 'Inward and outward movement', 'trend_format' => 'money',
            'breakdowns' => [
                ['title' => 'Accounting status', 'items' => $this->breakdown('cashflow_entries', 'accounting_status')],
                ['title' => 'Transaction direction', 'items' => $this->breakdown('cashflow_entries', 'transaction_type')],
            ],
            'alerts' => collect([
                $tentative ? ['level' => 'critical', 'title' => 'Tentative ledger entries', 'message' => $tentative.' entries still need booking or reconciliation.', 'url' => $this->url('cashflows.index')] : null,
                $unallocated > 0.009 ? ['level' => 'critical', 'title' => 'Unallocated cash', 'message' => number_format($unallocated, 2).' remains unapplied in aggregate entry-currency values.', 'url' => $this->url('cashflows.index')] : null,
            ])->filter()->values()->all(),
        ];
    }

    private function work(array $period): array
    {
        $created = fn (Carbon $start, Carbon $end) => $this->countRange('tasks', 'created_at', $start, $end);
        $completed = fn (Carbon $start, Carbon $end) => $this->countRange('tasks', 'completed_at', $start, $end);
        $activeTeam = $this->has('users', ['employment_status'])
            ? $this->countWhere('users', fn ($query) => $query->where('employment_status', 'active'))
            : $this->countWhere('users', fn ($query) => $query);
        $open = $this->countWhere('tasks', fn ($query) => $query->whereNotIn('status', ['completed', 'done', 'closed']));
        $overdue = $this->countWhere('tasks', fn ($query) => $query->whereNotIn('status', ['completed', 'done', 'closed'])->whereNotNull('due_date')->where('due_date', '<', today()));
        $queueItems = Schema::hasTable('project_workflows') ? app(ProjectCoordinatorQueue::class)->items(null, false) : collect();
        $unassigned = $queueItems->filter(fn ($item) => $item['coordinator'] === null)->count();
        $exceptions = $this->countWhere('project_exceptions', fn ($query) => $query->whereIn('status', ['open', 'acknowledged'])->whereNull('owner_id'));
        $alerts = Schema::hasTable('tasks') ? DB::table('tasks')->whereNotIn('status', ['completed', 'done', 'closed'])->whereNotNull('due_date')->where('due_date', '<', today())->orderBy('due_date')->limit(8)->get() : collect();

        return [
            'metrics' => [
                $this->pointMetric('Active team', $activeTeam, 'number', 'People currently available to the office', 'blue', $this->url('users.index')),
                $this->metric('Tasks completed', $completed, $period, 'number', 'Closed in selected period', 'green', $this->url('tasks.index')),
                $this->pointMetric('Open tasks', $open, 'number', 'Across the office task board', 'blue', $this->url('tasks.index')),
                $this->pointMetric('Overdue tasks', $overdue, 'number', 'Past due and incomplete', $overdue ? 'red' : 'green', $this->url('tasks.index')),
                $this->pointMetric('Coordinator actions', $queueItems->count(), 'number', 'Current project execution lanes', 'purple', $this->url('projects.work-queue.index', ['scope' => 'team'])),
                $this->pointMetric('Ownership gaps', $unassigned + $exceptions, 'number', 'Queue items or exceptions without owners', ($unassigned + $exceptions) ? 'red' : 'green', $this->url('projects.exceptions.index')),
            ],
            'trend' => $this->series('tasks', 'created_at', null, $period),
            'trend_label' => 'Tasks created', 'trend_format' => 'number',
            'breakdowns' => [
                ['title' => 'Task status', 'items' => $this->breakdown('tasks', 'status')],
                ['title' => 'Task priority', 'items' => $this->breakdown('tasks', 'priority')],
            ],
            'alerts' => $alerts->map(fn ($row) => [
                'level' => 'critical', 'title' => $row->title,
                'message' => 'Task overdue since '.Carbon::parse($row->due_date)->format('d M Y'),
                'url' => $this->url('tasks.edit', $row->id),
            ])->all(),
        ];
    }

    private function metric(string $label, callable $resolver, array $period, string $format, string $hint, string $tone, string $url): array
    {
        $current = (float) $resolver($period['start'], $period['end']);
        $previous = (float) $resolver($period['previousStart'], $period['previousEnd']);
        $change = $previous == 0.0 ? ($current == 0.0 ? 0.0 : 100.0) : (($current - $previous) / abs($previous)) * 100;
        return compact('label', 'current', 'previous', 'format', 'hint', 'tone', 'url') + ['change' => round($change, 1), 'comparison' => true];
    }

    private function pointMetric(string $label, float|int $current, string $format, string $hint, string $tone, string $url): array
    {
        return compact('label', 'current', 'format', 'hint', 'tone', 'url') + ['previous' => null, 'change' => null, 'comparison' => false];
    }

    private function sumRange(string $table, string $dateColumn, string $amountColumn, Carbon $start, Carbon $end, ?callable $filter = null): float
    {
        if (! $this->has($table, [$dateColumn, $amountColumn])) return 0.0;
        $query = DB::table($table)->whereBetween($dateColumn, [$this->boundary($start, $dateColumn), $this->boundary($end, $dateColumn, true)]);
        if ($filter) $filter($query);
        return (float) $query->sum($amountColumn);
    }

    private function countRange(string $table, string $dateColumn, Carbon $start, Carbon $end, ?callable $filter = null): int
    {
        if (! $this->has($table, [$dateColumn])) return 0;
        $query = DB::table($table)->whereBetween($dateColumn, [$this->boundary($start, $dateColumn), $this->boundary($end, $dateColumn, true)]);
        if ($filter) $filter($query);
        return (int) $query->count();
    }

    private function countWhere(string $table, callable $filter): int
    {
        if (! Schema::hasTable($table)) return 0;
        $query = DB::table($table); $filter($query); return (int) $query->count();
    }

    private function breakdown(string $table, string $column, ?callable $filter = null): array
    {
        if (! $this->has($table, [$column])) return [];
        $query = DB::table($table); if ($filter) $filter($query);
        return $query->selectRaw($column.', count(*) as aggregate')->groupBy($column)->orderByDesc('aggregate')->get()
            ->map(fn ($row) => ['label' => str((string) $row->{$column})->headline()->toString(), 'value' => (int) $row->aggregate])->all();
    }

    private function series(string $table, string $dateColumn, ?string $amountColumn, array $period, ?callable $filter = null): array
    {
        return $this->segments($period)->map(function ($segment) use ($table, $dateColumn, $amountColumn, $filter) {
            $value = $amountColumn
                ? $this->sumRange($table, $dateColumn, $amountColumn, $segment['start'], $segment['end'], $filter)
                : $this->countRange($table, $dateColumn, $segment['start'], $segment['end'], $filter);
            return $segment + ['value' => $value];
        })->all();
    }

    private function dualSeries(string $table, string $dateColumn, string $first, string $second, array $period, ?callable $filter = null): array
    {
        return $this->segments($period)->map(fn ($segment) => $segment + [
            'value' => $this->sumRange($table, $dateColumn, $first, $segment['start'], $segment['end'], $filter),
            'secondary' => $this->sumRange($table, $dateColumn, $second, $segment['start'], $segment['end'], $filter),
        ])->all();
    }

    private function segments(array $period): \Illuminate\Support\Collection
    {
        $days = max(1, (int) $period['start']->diffInDays($period['end']) + 1);
        $size = max(1, (int) ceil($days / 6));
        $cursor = $period['start']->copy(); $segments = collect();
        while ($cursor->lte($period['end']) && $segments->count() < 6) {
            $end = $cursor->copy()->addDays($size - 1)->endOfDay()->min($period['end']);
            $segments->push(['label' => $cursor->format('d M').' – '.$end->format('d M'), 'start' => $cursor->copy(), 'end' => $end->copy()]);
            $cursor = $end->copy()->addSecond()->startOfDay();
        }
        return $segments;
    }

    private function excludeTransfers($query): void
    {
        if (Schema::hasTable('cashflow_categories') && Schema::hasColumn('cashflow_entries', 'category_id')) {
            $query->whereNotExists(function ($subquery) {
                $subquery->selectRaw('1')->from('cashflow_categories')
                    ->whereColumn('cashflow_categories.id', 'cashflow_entries.category_id')
                    ->where('cashflow_categories.type', 'transfer');
            });
        }
    }

    private function has(string $table, array $columns): bool
    {
        if (! Schema::hasTable($table)) return false;
        return collect($columns)->every(fn ($column) => Schema::hasColumn($table, $column));
    }

    private function boundary(Carbon $date, string $column, bool $end = false): string
    {
        return str_ends_with($column, '_date') || in_array($column, ['entry_date', 'invoice_date', 'pickup_date', 'drop_date', 'packing_date', 'start_date', 'due_date'], true)
            ? $date->toDateString() : ($end ? $date->endOfDay()->toDateTimeString() : $date->startOfDay()->toDateTimeString());
    }

    private function url(string $route, mixed $parameters = []): string
    {
        return Route::has($route) ? route($route, $parameters) : '#';
    }
}
