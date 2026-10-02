@extends('layouts.app')

@section('page-title', 'Admin Dashboard')

@section('content')
@php
    $money = function ($value, $currency = 'INR') { return money($value, $currency); };
    $short = function ($value) {
        $value = (float) $value;
        if (abs($value) >= 10000000) return inr($value / 10000000).' Cr';
        if (abs($value) >= 100000) return inr($value / 100000).' L';
        if (abs($value) >= 1000) return inr($value / 1000).' K';

        return inr($value);
    };
@endphp

<div class="master-page" id="dashboardRoot" data-chart-data='@json($charts)' style="padding:0;">
    <div class="master-hero">
        <div>
            <p class="master-eyebrow">ERP Command Center</p>
            <h1>MissPack Admin Dashboard</h1>
            <p>Detailed bird-eye analytics for sales, purchase, finance, operations, client activity, projects, shipments, products and module health.</p>
        </div>
        <form method="GET" action="{{ route('dashboard') }}" class="master-period-form" id="dashPeriodForm">
            <div class="master-field"><label class="master-label">View</label><select class="master-select" name="period_type" id="periodType"><option value="range" {{ $period['type']==='range'?'selected':'' }}>Rolling Days</option><option value="quarter" {{ $period['type']==='quarter'?'selected':'' }}>Quarter</option><option value="half" {{ $period['type']==='half'?'selected':'' }}>Half Year</option><option value="year" {{ $period['type']==='year'?'selected':'' }}>Single Year</option><option value="multi_year" {{ $period['type']==='multi_year'?'selected':'' }}>Multi Year</option></select></div>
            <div class="master-field period-control period-range"><label class="master-label">Range</label><select class="master-select" name="range"><option value="7" {{ (int)($period['range'] ?? 30)===7?'selected':'' }}>7 Days</option><option value="30" {{ (int)($period['range'] ?? 30)===30?'selected':'' }}>30 Days</option><option value="90" {{ (int)($period['range'] ?? 30)===90?'selected':'' }}>90 Days</option><option value="180" {{ (int)($period['range'] ?? 30)===180?'selected':'' }}>180 Days</option><option value="365" {{ (int)($period['range'] ?? 30)===365?'selected':'' }}>365 Days</option></select></div>
            <div class="master-field period-control period-year period-quarter period-half"><label class="master-label">Year</label><select class="master-select" name="year">@foreach($availableYears as $year)<option value="{{ $year }}" {{ (int)($period['year'] ?? now()->year)===(int)$year?'selected':'' }}>{{ $year }}</option>@endforeach</select></div>
            <div class="master-field period-control period-quarter"><label class="master-label">Quarter</label><select class="master-select" name="quarter"><option value="1" {{ (int)($period['quarter'] ?? 1)===1?'selected':'' }}>Q1</option><option value="2" {{ (int)($period['quarter'] ?? 1)===2?'selected':'' }}>Q2</option><option value="3" {{ (int)($period['quarter'] ?? 1)===3?'selected':'' }}>Q3</option><option value="4" {{ (int)($period['quarter'] ?? 1)===4?'selected':'' }}>Q4</option></select></div>
            <div class="master-field period-control period-half"><label class="master-label">Half</label><select class="master-select" name="half"><option value="1" {{ (int)($period['half'] ?? 1)===1?'selected':'' }}>H1</option><option value="2" {{ (int)($period['half'] ?? 1)===2?'selected':'' }}>H2</option></select></div>
            <div class="master-field period-control period-multi_year"><label class="master-label">From</label><select class="master-select" name="from_year">@foreach($availableYears as $year)<option value="{{ $year }}" {{ (int)($period['from_year'] ?? now()->year-2)===(int)$year?'selected':'' }}>{{ $year }}</option>@endforeach</select></div>
            <div class="master-field period-control period-multi_year"><label class="master-label">To</label><select class="master-select" name="to_year">@foreach($availableYears as $year)<option value="{{ $year }}" {{ (int)($period['to_year'] ?? now()->year)===(int)$year?'selected':'' }}>{{ $year }}</option>@endforeach</select></div>
            <button class="master-btn master-btn-primary" type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
            <div class="master-period-badge">{{ $period['label'] }}<span>{{ $start->format('d M Y') }} - {{ $end->format('d M Y') }}</span></div>
        </form>
    </div>

    <div class="master-tabs" role="tablist">
        <button class="master-tab active" data-tab="overview" type="button"><i class="fa-solid fa-gauge-high"></i> Overview</button>
        <button class="master-tab" data-tab="sales" type="button"><i class="fa-solid fa-chart-line"></i> Sales & Purchase</button>
        <button class="master-tab" data-tab="finance" type="button"><i class="fa-solid fa-indian-rupee-sign"></i> Finance</button>
        <button class="master-tab" data-tab="operations" type="button"><i class="fa-solid fa-diagram-project"></i> Operations</button>
        <button class="master-tab" data-tab="modules" type="button"><i class="fa-solid fa-layer-group"></i> Modules</button>
    </div>

    <section class="master-panel active" data-panel="overview">
        <div class="master-metrics-grid six">
            <a class="master-metric-card blue" href="{{ $routes['clients'] }}"><div class="master-metric-icon"><i class="fa-solid fa-building-user"></i></div><div><span>Total Clients</span><strong>{{ $metrics['clients_total'] }}</strong><small>{{ $metrics['clients_approved'] }} approved · {{ $metrics['clients_under_review'] }} under review</small></div></a>
            <a class="master-metric-card purple" href="{{ $routes['leads'] }}"><div class="master-metric-icon"><i class="fa-solid fa-people-group"></i></div><div><span>Lead Pipeline</span><strong>{{ $metrics['leads_total'] }}</strong><small>{{ $metrics['leads_range'] }} new in this period</small></div></a>
            <a class="master-metric-card pink" href="{{ $routes['projects'] }}"><div class="master-metric-icon"><i class="fa-solid fa-briefcase"></i></div><div><span>Active Projects</span><strong>{{ $metrics['projects_active'] }}</strong><small>{{ $metrics['projects_at_risk'] }} at risk</small></div></a>
            <a class="master-metric-card green" href="{{ $routes['shipments'] }}"><div class="master-metric-icon"><i class="fa-solid fa-truck-fast"></i></div><div><span>Shipments</span><strong>{{ $metrics['shipments_total'] }}</strong><small>{{ $metrics['shipments_in_transit'] }} in transit · {{ $metrics['shipments_delayed'] }} delayed</small></div></a>
            <a class="master-metric-card orange" href="{{ $routes['products'] }}"><div class="master-metric-icon"><i class="fa-solid fa-box-open"></i></div><div><span>Products</span><strong>{{ $metrics['products_total'] }}</strong><small>{{ $metrics['products_ready_stock'] }} ready stock</small></div></a>
            <a class="master-metric-card dark" href="{{ $routes['tasks'] }}"><div class="master-metric-icon"><i class="fa-solid fa-list-check"></i></div><div><span>Open Tasks</span><strong>{{ $metrics['tasks_open'] }}</strong><small>{{ $metrics['tasks_due_today'] }} due today · {{ $metrics['tasks_overdue'] }} overdue</small></div></a>
        </div>

        <div class="master-card master-attention-card">
            <div class="master-section-head">
                <div><p class="master-eyebrow">Immediate Attention Required</p><h2>Today, Overdue & Client Portal Updates</h2></div>
                <span class="master-pill danger">Live Ops</span>
            </div>
            <div class="master-attention-grid">
                <div class="master-attention-block today">
                    <div class="master-attention-title"><span>📌</span><div><strong>Tasks Due Today</strong><small>{{ count($attention['today_tasks']) }} visible</small></div></div>
                    <div class="master-attention-list">
                        @forelse($attention['today_tasks'] as $task)
                            <a href="{{ $task['url'] }}" class="master-attention-item">
                                <div><strong>{{ $task['title'] }}</strong><small>{{ $task['assignee'] }} · {{ \Illuminate\Support\Str::headline($task['priority']) }} · Due {{ $task['due'] }}</small></div>
                                <span class="master-mini-badge today">Today</span>
                            </a>
                        @empty
                            <div class="master-empty small">No tasks due today.</div>
                        @endforelse
                    </div>
                </div>

                <div class="master-attention-block overdue">
                    <div class="master-attention-title"><span>⏰</span><div><strong>Overdue Tasks</strong><small>{{ $metrics['tasks_overdue'] }} total overdue</small></div></div>
                    <div class="master-attention-list">
                        @forelse($attention['overdue_tasks'] as $task)
                            <a href="{{ $task['url'] }}" class="master-attention-item">
                                <div><strong>{{ $task['title'] }}</strong><small>{{ $task['assignee'] }} · Due {{ $task['due'] }}</small></div>
                                <span class="master-mini-badge danger">{{ $task['days_overdue'] }}d</span>
                            </a>
                        @empty
                            <div class="master-empty small">No overdue tasks.</div>
                        @endforelse
                    </div>
                </div>

                <div class="master-attention-block shipment">
                    <div class="master-attention-title"><span>🚚</span><div><strong>In Transit Shipments</strong><small>{{ $metrics['shipments_in_transit'] }} total in transit</small></div></div>
                    <div class="master-attention-list">
                        @forelse($attention['in_transit_shipments'] as $shipment)
                            <a href="{{ $shipment['url'] }}" class="master-attention-item">
                                <div><strong>{{ $shipment['number'] }} · {{ $shipment['identity'] }}</strong><small>{{ $shipment['route'] }} · {{ $shipment['partner'] }} · {{ $shipment['tracking'] }}</small></div>
                                <span class="master-mini-badge info">Track</span>
                            </a>
                        @empty
                            <div class="master-empty small">No shipments in transit.</div>
                        @endforelse
                    </div>
                </div>

                <div class="master-attention-block portal">
                    <div class="master-attention-title"><span>🧾</span><div><strong>New KYC Submissions</strong><small>{{ count($attention['kyc_submissions']) }} latest</small></div></div>
                    <div class="master-attention-list">
                        @forelse($attention['kyc_submissions'] as $kyc)
                            <a href="{{ $kyc['url'] }}" class="master-attention-item">
                                <div><strong>{{ $kyc['client'] }}</strong><small>{{ $kyc['number'] }} · {{ $kyc['status'] }} · {{ $kyc['submitted'] }}</small></div>
                                <span class="master-mini-badge warning">KYC</span>
                            </a>
                        @empty
                            <div class="master-empty small">No new KYC submissions.</div>
                        @endforelse
                    </div>
                </div>

                <div class="master-attention-block portal">
                    <div class="master-attention-title"><span>📎</span><div><strong>New Client Documents</strong><small>{{ count($attention['portal_documents']) }} pending review</small></div></div>
                    <div class="master-attention-list">
                        @forelse($attention['portal_documents'] as $document)
                            <a href="{{ $document['url'] }}" class="master-attention-item">
                                <div><strong>{{ $document['title'] }}</strong><small>{{ $document['client'] }} · {{ $document['category'] }} · {{ $document['date'] }}</small></div>
                                <span class="master-mini-badge info">Doc</span>
                            </a>
                        @empty
                            <div class="master-empty small">No new client documents.</div>
                        @endforelse
                    </div>
                </div>

                <div class="master-attention-block portal">
                    <div class="master-attention-title"><span>💬</span><div><strong>New Client Comments</strong><small>{{ count($attention['portal_comments']) }} unread internal</small></div></div>
                    <div class="master-attention-list">
                        @forelse($attention['portal_comments'] as $comment)
                            <a href="{{ $comment['url'] }}" class="master-attention-item">
                                <div><strong>{{ $comment['client'] }} · {{ $comment['related'] }}</strong><small>{{ $comment['body'] }} · {{ $comment['date'] }}</small></div>
                                <span class="master-mini-badge success">New</span>
                            </a>
                        @empty
                            <div class="master-empty small">No unread client comments.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="master-grid-main">
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Business Movement</p><h2>Sales, Purchase, Income & Expense Trend</h2></div><span class="master-pill">{{ $period['label'] }}</span></div><canvas id="overviewComboChart" height="310"></canvas></div>
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Attention</p><h2>Critical Alerts</h2></div><span class="master-pill danger">{{ count($alerts) }}</span></div><div class="master-alert-list">@forelse($alerts as $alert)<a href="{{ $alert['url'] }}" class="master-alert-item {{ $alert['level'] }}"><span class="master-alert-dot"></span><div><strong>{{ $alert['title'] }}</strong><small>{{ $alert['message'] }}</small></div></a>@empty<div class="master-empty small">No critical alerts right now.</div>@endforelse</div></div>
        </div>

        <div class="master-grid-4">
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Project Health</p><h2>Health Split</h2></div></div><canvas id="projectHealthChart" height="240"></canvas><div class="master-legend" id="projectHealthLegend"></div></div>
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Shipment Status</p><h2>Logistics Split</h2></div></div><canvas id="shipmentStatusChart" height="240"></canvas><div class="master-legend" id="shipmentStatusLegend"></div></div>
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Expense Category</p><h2>Expense Pie</h2></div></div><canvas id="expenseCategoryChart" height="240"></canvas><div class="master-legend" id="expenseCategoryLegend"></div></div>
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Pipeline</p><h2>Records Trend</h2></div></div><canvas id="pipelineChart" height="240"></canvas></div>
        </div>
    </section>

    <section class="master-panel" data-panel="sales">
        <div class="master-metrics-grid four">
            <div class="master-metric-card blue"><div class="master-metric-icon"><i class="fa-solid fa-arrow-trend-up"></i></div><div><span>Period Sales</span><strong>{{ $short($metrics['period_sales']) }}</strong><small>Customer quotes shared/accepted</small></div></div>
            <div class="master-metric-card orange"><div class="master-metric-icon"><i class="fa-solid fa-cart-shopping"></i></div><div><span>Period Purchase</span><strong>{{ $short($metrics['period_purchase']) }}</strong><small>Vendor quotes / purchase cost</small></div></div>
            <div class="master-metric-card green"><div class="master-metric-icon"><i class="fa-solid fa-percent"></i></div><div><span>Gross Margin</span><strong>{{ $short($metrics['period_gross_margin']) }}</strong><small>{{ $metrics['period_margin_percent'] }}% margin</small></div></div>
            <div class="master-metric-card purple"><div class="master-metric-icon"><i class="fa-solid fa-file-invoice-dollar"></i></div><div><span>Accepted Quotes</span><strong>{{ $metrics['quotes_accepted'] }}</strong><small>{{ $metrics['quotes_total'] }} total quotations</small></div></div>
        </div>
        <div class="master-grid-2">
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Sales vs Purchase</p><h2>Month/Period Wise Sales & Purchase</h2></div></div><canvas id="salesPurchaseChart" height="320"></canvas></div>
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Margin</p><h2>Gross Margin Trend</h2></div></div><canvas id="grossMarginChart" height="320"></canvas></div>
        </div>
        <div class="master-grid-2" style="margin-top:18px;">
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Client Revenue</p><h2>Top Sales by Client</h2></div></div><canvas id="salesByClientChart" height="330"></canvas></div>
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Product Revenue</p><h2>Top Sales by Product</h2></div></div><canvas id="salesByProductChart" height="330"></canvas></div>
        </div>
        <div class="master-grid-3" style="margin-top:18px;">
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Leads</p><h2>Lead Funnel</h2></div></div><div class="master-status-list">@include('dashboard.partials.status-list', ['items' => $charts['status']['leads']])</div></div>
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Quotes</p><h2>Quote Status</h2></div></div><div class="master-status-list">@include('dashboard.partials.status-list', ['items' => $charts['status']['quotes']])</div></div>
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Purchase</p><h2>Purchase by Vendor</h2></div></div><canvas id="purchaseByVendorChart" height="270"></canvas></div>
        </div>
    </section>

    <section class="master-panel" data-panel="finance">
        <div class="master-metrics-grid four">
            <div class="master-metric-card green"><div class="master-metric-icon"><i class="fa-solid fa-circle-arrow-down"></i></div><div><span>Income</span><strong>{{ $short($metrics['period_income']) }}</strong><small>Cashflow credit in period</small></div></div>
            <div class="master-metric-card pink"><div class="master-metric-icon"><i class="fa-solid fa-circle-arrow-up"></i></div><div><span>Expenses</span><strong>{{ $short($metrics['period_expense']) }}</strong><small>Cashflow debit in period</small></div></div>
            <div class="master-metric-card blue"><div class="master-metric-icon"><i class="fa-solid fa-scale-balanced"></i></div><div><span>Net Income</span><strong>{{ $short($metrics['period_net_income']) }}</strong><small>Income minus expenses</small></div></div>
            <div class="master-metric-card orange"><div class="master-metric-icon"><i class="fa-solid fa-hourglass-half"></i></div><div><span>Outstanding</span><strong>{{ $short($metrics['project_outstanding_all']) }}</strong><small>Project estimated - inward</small></div></div>
        </div>
        <div class="master-grid-2">
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Finance</p><h2>Month/Period Wise Income & Expenses</h2></div></div><canvas id="incomeExpenseChart" height="320"></canvas></div>
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Net</p><h2>Net Income Trend</h2></div></div><canvas id="netIncomeChart" height="320"></canvas></div>
        </div>
        <div class="master-grid-3" style="margin-top:18px;">
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Expense Category</p><h2>Category Pie</h2></div></div><canvas id="expenseCategoryChart2" height="260"></canvas><div class="master-legend" id="expenseCategoryLegend2"></div></div>
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Project Payments</p><h2>Inward vs Outward</h2></div></div><canvas id="projectPaymentChart" height="260"></canvas></div>
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Summary</p><h2>Finance Summary</h2></div></div><div class="master-finance-summary"><div><span>Project Inward</span><strong class="green">{{ $money($metrics['project_inward_all']) }}</strong></div><div><span>Project Expense</span><strong class="red">{{ $money($metrics['project_outward_all']) }}</strong></div><div><span>Project Profit</span><strong class="{{ $metrics['project_profit_all'] >= 0 ? 'green' : 'red' }}">{{ $money($metrics['project_profit_all']) }}</strong></div><div><span>Cashflow Net</span><strong>{{ $money($metrics['cash_net_range']) }}</strong></div></div></div>
        </div>
    </section>

    <section class="master-panel" data-panel="operations">
        <div class="master-metrics-grid four">
            <a class="master-metric-card pink" href="{{ $routes['projects'] }}"><div class="master-metric-icon"><i class="fa-solid fa-briefcase"></i></div><div><span>Total Projects</span><strong>{{ $metrics['projects_total'] }}</strong><small>{{ $metrics['projects_completed'] }} completed</small></div></a>
            <a class="master-metric-card green" href="{{ $routes['shipments'] }}"><div class="master-metric-icon"><i class="fa-solid fa-plane-departure"></i></div><div><span>In Transit</span><strong>{{ $metrics['shipments_in_transit'] }}</strong><small>{{ $metrics['shipments_delivered'] }} delivered</small></div></a>
            <a class="master-metric-card dark" href="{{ $routes['tasks'] }}"><div class="master-metric-icon"><i class="fa-solid fa-list-check"></i></div><div><span>Tasks</span><strong>{{ $metrics['tasks_total'] }}</strong><small>{{ $metrics['tasks_completed'] }} completed</small></div></a>
            <a class="master-metric-card orange" href="{{ $routes['vendors'] }}"><div class="master-metric-icon"><i class="fa-solid fa-user-gear"></i></div><div><span>Vendors</span><strong>{{ $metrics['vendors_total'] }}</strong><small>{{ $metrics['vendor_quotes_total'] }} vendor quotes</small></div></a>
        </div>
        <div class="master-grid-3">
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Project Status</p><h2>Project Workload</h2></div></div><div class="master-status-list">@include('dashboard.partials.status-list', ['items' => $charts['status']['projects']])</div></div>
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Task Status</p><h2>Task Board</h2></div></div><div class="master-status-list">@include('dashboard.partials.status-list', ['items' => $charts['status']['tasks']])</div></div>
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Shipment Status</p><h2>Shipment Board</h2></div></div><div class="master-status-list">@include('dashboard.partials.status-list', ['items' => $charts['status']['shipments']])</div></div>
        </div>
        <div class="master-card" style="margin-top:18px;"><div class="master-section-head"><div><p class="master-eyebrow">Top Clients</p><h2>Project Value by Client</h2></div></div><div class="master-table-wrap"><table class="master-table"><thead><tr><th>Client</th><th>Projects</th><th>Avg Progress</th><th>Value</th></tr></thead><tbody>@forelse($topClients as $client)<tr><td><a href="{{ $client['url'] }}"><strong>{{ $client['name'] }}</strong></a></td><td>{{ $client['projects'] }}</td><td><div class="master-mini-progress"><span style="width:{{ $client['progress'] }}%"></span></div><small>{{ $client['progress'] }}%</small></td><td>{{ $money($client['value']) }}</td></tr>@empty<tr><td colspan="4"><div class="master-empty small">No project/client value data.</div></td></tr>@endforelse</tbody></table></div></div>
    </section>

    <section class="master-panel" data-panel="modules">
        <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">System Coverage</p><h2>Module Health</h2></div><span class="master-pill">{{ count($moduleHealth) }} modules</span></div><div class="master-module-grid">@foreach($moduleHealth as $module)<a href="{{ $module['route'] }}" class="master-module-card {{ $module['installed'] ? 'installed' : 'missing' }}"><span>{{ $module['installed'] ? 'Active' : 'Missing' }}</span><strong>{{ $module['name'] }}</strong><small>{{ $module['count'] }} records</small></a>@endforeach</div></div>
        <div class="master-card" style="margin-top:18px;"><div class="master-section-head"><div><p class="master-eyebrow">Recent</p><h2>ERP Activity Feed</h2></div></div><div class="master-activity-list">@forelse($recentActivity as $activity)<a href="{{ $activity['url'] }}" class="master-activity-item"><div class="master-activity-type {{ $activity['type'] }}">{{ strtoupper(substr($activity['label'], 0, 1)) }}</div><div><strong>{{ $activity['title'] }}</strong><small>{{ $activity['label'] }} · {{ $activity['number'] }} @if($activity['status']) · {{ \Illuminate\Support\Str::headline($activity['status']) }} @endif</small></div><span>{{ $activity['date'] }}</span></a>@empty<div class="master-empty small">No activity yet.</div>@endforelse</div></div>
    </section>
</div>


@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
@endpush
<div class="master-tooltip" id="dashTooltip"></div>


@push('scripts')
    <script src="{{ asset('assets/js/dashboard.js') }}"></script>
@endpush
@endsection
