<?php

namespace App\Services;

use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/** Read-only analytical panels. Period flows and live balances are deliberately separate. */
class DashboardAnalytics
{
    public static function ageBucket(?string $due, Carbon $asOf): string
    {
        if (! $due) return 'No due date';
        $days = (int) Carbon::parse($due)->startOfDay()->diffInDays($asOf->copy()->startOfDay(), false);
        return match (true) {
            $days <= 0 => 'Not due', $days <= 30 => '1–30 days', $days <= 60 => '31–60 days',
            $days <= 90 => '61–90 days', default => '90+ days',
        };
    }

    public function panels(string $module, array $period, string $currency): array
    {
        return match ($module) {
            'sales' => [
                $this->documents('Client concentration', 'sales_invoices', 'client_company_name', ['tax', 'cash'], $period, $currency),
                $this->products('sales', $period, $currency),
                $this->group('Lead sources', 'leads', 'lead_source', 'created_at', $period, 'leads.index'),
                $this->group('Client status', 'clients', 'status', null, $period, 'clients.index'),
                $this->ageing('sales', $currency),
            ],
            'purchase' => [
                $this->documents('Vendor concentration', 'purchase_invoices', 'vendor_company_name', ['bill'], $period, $currency),
                $this->products('purchase', $period, $currency),
                $this->documents('Order and bill activity', 'purchase_invoices', 'invoice_type', ['order', 'bill'], $period, $currency),
                $this->group('Vendor status', 'vendors', 'status', null, $period, 'vendors.index'),
                $this->ageing('purchase', $currency),
            ],
            'projects' => [
                $this->group('Workflow stages', 'project_workflows', 'calculated_stage', null, $period, 'projects.index'),
                $this->owners('Project ownership', 'projects', 'assigned_to', $period, 'projects.index'),
                $this->group('Project priorities', 'projects', 'priority', null, $period, 'projects.index'),
                $this->group('Exception categories', 'project_exceptions', 'category', null, $period, 'projects.index', fn ($q) => $q->whereIn('status', ['open', 'acknowledged'])),
                $this->projectClients($period, $currency),
                $this->projectValues($currency),
            ],
            'operations' => [
                $this->group('Transport modes', 'shipments', 'shipment_mode', 'pickup_date', $period, 'shipments.index'),
                $this->group('Carrier freight', 'shipments', 'logistic_partner', 'pickup_date', $period, 'shipments.index', fn ($q) => $q->where('status', '!=', 'cancelled')->where('currency', $currency), ['shipment_cost' => 'Freight cost']),
                $this->group('Packing activity', 'packaging_lists', 'status', 'packing_date', $period, 'packaging-lists.index', null, ['total_cartons' => 'Cartons']),
                $this->receipts($period),
                $this->group('Shipment types', 'shipments', 'shipment_type', 'pickup_date', $period, 'shipments.index'),
            ],
            'finance' => [
                $this->movement('Outward by category', 'category_id', 'cashflow_categories', 'name', $period, $currency),
                $this->movement('Account movement', 'account_id', 'cashflow_accounts', 'account_name', $period, $currency),
                $this->movement('Project cash movement', 'project_id', 'projects', 'project_number', $period, $currency),
                $this->group('Payment modes', 'cashflow_entries', 'payment_mode', 'entry_date', $period, 'cashflows.index', fn ($q) => $this->cashFilter($q, $currency)),
                $this->group('Ledger controls', 'cashflow_entries', 'accounting_status', null, $period, 'cashflows.index', fn ($q) => $q->where('currency', $currency)),
            ],
            'work' => [
                $this->owners('Open task workload', 'tasks', 'assignee_id', $period, 'tasks.index'),
                $this->timeliness($period),
                $this->group('Task intake by category', 'tasks', 'category', 'created_at', $period, 'tasks.index'),
                $this->owners('Exception ownership', 'project_exceptions', 'owner_id', $period, 'projects.exceptions.index'),
                $this->group('Feedback activity', 'feedback_requests', 'status', 'created_at', $period, 'feedback.index'),
            ],
            default => [],
        };
    }

    private function panel(string $title, array $columns, array $rows, string $scope, string $basis, string $route, array $formats = [], bool $available = true): array
    {
        return compact('title', 'columns', 'rows', 'scope', 'basis', 'formats', 'available')
            + ['url' => Route::has($route) ? route($route) : null];
    }

    private function has(string $table, array $columns): bool
    {
        return Schema::hasTable($table) && collect($columns)->every(fn ($column) => Schema::hasColumn($table, $column));
    }

    private function query(string $table)
    {
        $q = DB::table($table);
        if (Schema::hasColumn($table, 'deleted_at')) $q->whereNull($table.'.deleted_at');
        return $q;
    }

    private function window($query, string $column, array $period): void
    {
        $isDate = str_ends_with($column, '_date');
        $query->whereBetween($column, [$isDate ? $period['start']->toDateString() : $period['start']->toDateTimeString(),
            $period['end']->copy()->endOfDay()->toDateTimeString()]);
    }

    /** Count distributions are not monetary distributions; all sums have explicit units. */
    private function group(string $title, string $table, string $dimension, ?string $date, array $period, string $route, ?callable $filter = null, array $sums = []): array
    {
        $required = array_merge([$dimension], $date ? [$date] : [], array_keys($sums));
        $available = $this->has($table, $required);
        $columns = ['label' => 'Group', 'count' => 'Records'] + $sums;
        $rows = [];
        if ($available) {
            $q = $this->query($table);
            if ($date) $this->window($q, $date, $period);
            if ($filter) $filter($q);
            $q->select($dimension)->selectRaw('COUNT(*) as aggregate_count');
            foreach ($sums as $key => $label) $q->selectRaw('SUM('.$key.') as '.$key);
            $rows = $q->groupBy($dimension)->orderByDesc('aggregate_count')->orderBy($dimension)->get()->map(function ($row) use ($dimension, $sums) {
                $result = ['label' => trim((string) $row->{$dimension}) ?: 'Not assigned', 'count' => (int) $row->aggregate_count];
                foreach ($sums as $key => $label) $result[$key] = (float) $row->{$key};
                return $result;
            })->all();
        }
        $formats = ['count' => 'number'];
        foreach ($sums as $key => $label) $formats[$key] = $key === 'total_cartons' ? 'number' : 'money';
        return $this->panel($title, $columns, $rows, $date ? 'period' : 'live', $date ?? 'Current records', $route, $formats, $available);
    }

    private function documents(string $title, string $table, string $dimension, array $types, array $period, string $currency): array
    {
        $route = $table === 'sales_invoices' ? 'sales-invoices.index' : 'purchase-invoices.index';
        $panel = $this->group($title, $table, $dimension, 'invoice_date', $period, $route,
            fn ($q) => $q->whereIn('invoice_type', $types)->whereNotIn('status', ['draft', 'cancelled'])->where('currency', $currency), ['total_amount' => 'Document value']);
        $panel['columns'] = ['label' => 'Party / document type', 'count' => 'Documents', 'amount' => 'Document value'];
        $panel['formats'] = ['count' => 'number', 'amount' => 'money'];
        $panel['rows'] = collect($panel['rows'])->map(fn ($r) => ['label' => $r['label'], 'count' => $r['count'], 'amount' => $r['total_amount']])->sortByDesc('amount')->values()->all();
        $panel['basis'] = 'Invoice date · issued documents only · '.$currency;
        return $panel;
    }

    private function products(string $side, array $period, string $currency): array
    {
        $items = $side.'_invoice_items'; $docs = $side.'_invoices'; $fk = $side.'_invoice_id';
        $available = $this->has($items, [$fk, 'product_name', 'quantity', 'unit', 'line_total']) && $this->has($docs, ['invoice_date', 'currency', 'invoice_type', 'status']);
        $rows = [];
        if ($available) {
            $q = DB::table($items)->join($docs, $docs.'.id', '=', $items.'.'.$fk)
                ->whereIn($docs.'.invoice_type', $side === 'sales' ? ['tax', 'cash'] : ['bill'])
                ->whereNotIn($docs.'.status', ['draft', 'cancelled'])->where($docs.'.currency', $currency);
            $this->window($q, $docs.'.invoice_date', $period);
            $rows = $q->select($items.'.product_name', $items.'.unit')->selectRaw('SUM('.$items.'.quantity) as quantity, SUM('.$items.'.line_total) as amount')
                ->groupBy($items.'.product_name', $items.'.unit')->orderByDesc('amount')->get()->map(fn ($r) => ['label' => $r->product_name ?: 'Not supplied', 'unit' => $r->unit ?: 'Not supplied', 'quantity' => (float) $r->quantity, 'amount' => (float) $r->amount])->all();
        }
        return $this->panel('Product contribution', ['label' => 'Product', 'unit' => 'Unit', 'quantity' => 'Quantity', 'amount' => 'Line value'], $rows, 'period',
            'Invoice date · '.$currency.' · line values before document-level adjustments; quantities grouped by unit', $side.'-invoices.index', ['quantity' => 'quantity', 'amount' => 'money'], $available);
    }

    private function ageing(string $side, string $currency): array
    {
        $model = $side === 'sales' ? SalesInvoice::class : PurchaseInvoice::class;
        $table = $side.'_invoices';
        $available = $this->has($table, ['currency', 'invoice_type', 'status', 'due_date']);
        $buckets = [];
        if ($available) {
            // Use the canonical payment/allocation-aware balance, never total minus a cached paid figure.
            $docs = $model::query()->with(['payments', 'cashflowAllocations'])->where('currency', $currency)
                ->whereIn('invoice_type', $side === 'sales' ? ['tax', 'cash'] : ['bill'])->whereNotIn('status', ['draft', 'cancelled'])->get();
            foreach ($docs as $doc) {
                $amount = $doc->balanceDue();
                if ($amount <= 0.009) continue;
                $bucket = self::ageBucket($doc->due_date?->toDateString(), now());
                $buckets[$bucket] ??= ['label' => $bucket, 'count' => 0, 'amount' => 0.0];
                $buckets[$bucket]['count']++; $buckets[$bucket]['amount'] += $amount;
            }
        }
        $rows = [];
        foreach (['No due date', 'Not due', '1–30 days', '31–60 days', '61–90 days', '90+ days'] as $bucket) {
            if (isset($buckets[$bucket])) $rows[] = $buckets[$bucket];
        }
        return $this->panel($side === 'sales' ? 'Receivable ageing' : 'Payable ageing', ['label' => 'Days overdue', 'count' => 'Documents', 'amount' => 'Open balance'], $rows,
            'live', 'Current payment/allocation-aware balance · '.$currency.' · not limited to reporting period', $side.'-invoices.index', ['count' => 'number', 'amount' => 'money'], $available);
    }

    private function owners(string $title, string $table, string $owner, array $period, string $route): array
    {
        $panel = $this->group($title, $table, $owner, null, $period, $route, function ($q) use ($table) {
            $q->whereNotIn('status', $table === 'project_exceptions' ? ['resolved'] : ['completed', 'done', 'closed', 'cancelled', 'closed_with_outstanding_financials']);
        });
        if ($this->has('users', ['name'])) {
            $names = DB::table('users')->pluck('name', 'id');
            foreach ($panel['rows'] as &$row) $row['label'] = $row['label'] === 'Not assigned' ? 'Unassigned' : ($names[$row['label']] ?? 'User #'.$row['label']);
        }
        return $panel;
    }

    private function projectClients(array $period, string $currency): array
    {
        $panel = $this->group('Client project workload', 'projects', 'client_id', 'start_date', $period, 'projects.index', fn ($q) => $q->where('currency', $currency));
        if ($this->has('clients', ['company_name'])) {
            $names = DB::table('clients')->pluck('company_name', 'id');
            foreach ($panel['rows'] as &$row) $row['label'] = $names[$row['label']] ?? $row['label'];
        }
        $panel['basis'] = 'Project start date · '.$currency.' projects';
        return $panel;
    }

    private function projectValues(string $currency): array
    {
        $available = $this->has('projects', ['client_id', 'currency', 'progress_percent']); $rows = [];
        if ($available) {
            $projects = $this->query('projects')->where('currency', $currency)->get(['id', 'client_id', 'progress_percent']);
            $values = [];
            if ($this->has('sales_invoices', ['project_id', 'currency', 'invoice_type', 'status', 'total_amount'])) {
                $docs = SalesInvoice::query()->whereIn('project_id', $projects->pluck('id'))->where('currency', $currency)
                    ->whereNotIn('status', ['draft', 'cancelled'])->get();
                foreach ($docs as $doc) {
                    if ($doc->countsTowardsProject()) $values[$doc->project_id] = ($values[$doc->project_id] ?? 0) + $doc->receivableTotal();
                }
            }
            $names = $this->has('clients', ['company_name']) ? DB::table('clients')->pluck('company_name', 'id') : collect();
            $rows = $projects->groupBy('client_id')->map(fn ($group, $id) => [
                'label' => $names[$id] ?? ($id ? 'Client #'.$id : 'Unassigned'), 'count' => $group->count(),
                'progress' => round((float) $group->avg('progress_percent'), 1),
                'amount' => (float) $group->sum(fn ($p) => $values[$p->id] ?? 0),
            ])->sortByDesc('amount')->values()->all();
        }
        return $this->panel('Client project value', ['label' => 'Client', 'count' => 'Projects', 'progress' => 'Avg progress', 'amount' => 'Active document value'], $rows,
            'live', 'All current projects · '.$currency.' documents only · includes open proformas; converted/superseded amounts excluded. Not earned revenue or profit.', 'projects.index',
            ['count' => 'number', 'progress' => 'percent', 'amount' => 'money'], $available);
    }

    private function cashFilter($query, string $currency): void
    {
        $query->where('cashflow_entries.currency', $currency);
        if ($this->has('cashflow_categories', ['type']) && Schema::hasColumn('cashflow_entries', 'category_id')) {
            $query->whereNotExists(fn ($sub) => $sub->selectRaw('1')->from('cashflow_categories')->whereColumn('cashflow_categories.id', 'cashflow_entries.category_id')->where('cashflow_categories.type', 'transfer'));
        }
    }

    private function movement(string $title, string $dimension, string $lookup, string $name, array $period, string $currency): array
    {
        $available = $this->has('cashflow_entries', [$dimension, 'entry_date', 'currency', 'credit_amount', 'debit_amount']);
        $rows = [];
        if ($available) {
            $q = $this->query('cashflow_entries'); $this->window($q, 'entry_date', $period); $this->cashFilter($q, $currency);
            if ($dimension === 'category_id') $q->where('debit_amount', '>', 0);
            $rows = $q->select($dimension)->selectRaw('SUM(credit_amount) as inward, SUM(debit_amount) as outward')->groupBy($dimension)->get()->map(fn ($r) => ['label' => $r->{$dimension} === null ? 'Unassigned' : (string) $r->{$dimension}, 'inward' => (float) $r->inward, 'outward' => (float) $r->outward, 'net' => (float) $r->inward - (float) $r->outward])->all();
            if ($this->has($lookup, [$name])) {
                $names = DB::table($lookup)->pluck($name, 'id');
                foreach ($rows as &$row) $row['label'] = $names[$row['label']] ?? $row['label'];
                unset($row);
            }
        }
        return $this->panel($title, ['label' => 'Group', 'inward' => 'Inward', 'outward' => 'Outward', 'net' => 'Net movement'], $rows, 'period',
            'Entry date · '.$currency.' · internal transfers excluded · cash movement, not profit or account closing balance', 'cashflows.index', ['inward' => 'money', 'outward' => 'money', 'net' => 'money'], $available);
    }

    private function receipts(array $period): array
    {
        $available = $this->has('shipment_delivery_receipts', ['received_at']) && $this->has('shipment_delivery_receipt_lines', ['shipment_delivery_receipt_id', 'shipment_item_id']) && $this->has('shipment_items', ['unit']);
        $quantities = ['received', 'accepted', 'short', 'damaged', 'rejected', 'returned', 'replacement'];
        $rows = [];
        if ($available) {
            $q = DB::table('shipment_delivery_receipt_lines as l')->join('shipment_delivery_receipts as r', 'r.id', '=', 'l.shipment_delivery_receipt_id')->join('shipment_items as i', 'i.id', '=', 'l.shipment_item_id');
            $this->window($q, 'r.received_at', $period); $q->select('i.unit');
            foreach ($quantities as $key) $q->selectRaw('SUM(l.'.$key.'_quantity) as '.$key);
            $rows = $q->groupBy('i.unit')->get()->map(function ($r) use ($quantities) {
                $row = ['label' => $r->unit ?: 'Not supplied'];
                foreach ($quantities as $key) $row[$key] = (float) $r->{$key};
                return $row;
            })->all();
        }
        $columns = ['label' => 'Unit']; $formats = [];
        foreach ($quantities as $key) { $columns[$key] = ucfirst($key); $formats[$key] = 'quantity'; }
        return $this->panel('Delivery reconciliation', $columns, $rows, 'period', 'Receipt date · separate units; replacements are recorded quantities, not assumed completed deliveries', 'shipments.index', $formats, $available);
    }

    private function timeliness(array $period): array
    {
        $available = $this->has('tasks', ['completed_at', 'due_date']); $counts = [];
        if ($available) {
            $q = $this->query('tasks')->whereIn('status', ['completed', 'done', 'closed']); $this->window($q, 'completed_at', $period);
            foreach ($q->get(['completed_at', 'due_date']) as $row) {
                $label = ! $row->due_date ? 'No due date' : (Carbon::parse($row->completed_at)->lte(Carbon::parse($row->due_date)->endOfDay()) ? 'On time' : 'Late');
                $counts[$label] = ($counts[$label] ?? 0) + 1;
            }
        }
        $rows = collect($counts)->map(fn ($count, $label) => compact('label', 'count'))->values()->all();
        return $this->panel('Completion timeliness', ['label' => 'Delivery', 'count' => 'Tasks'], $rows, 'period', 'Task completion date · due-day end is on time; undated tasks reported separately', 'tasks.index', ['count' => 'number'], $available);
    }
}
