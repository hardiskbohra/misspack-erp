@extends('layouts.app')

@section('page-title', 'ERP Overview Dashboard')

@section('page-actions')
    <form method="GET" action="{{ route('dashboard') }}" id="dashPeriodForm">
        <div class="core-filter-toolbar">
            <x-filter-trigger drawer="dashboardPeriodDrawer" label="Reporting period" />
            <div class="master-period-badge">{{ $period['label'] }}<span>{{ $start->format('d M Y') }} - {{ $end->format('d M Y') }}</span></div>
        </div>
        <x-drawer id="dashboardPeriodDrawer" title="Choose reporting period" eyebrow="Dashboard range"
            subtitle="Change the reporting window used by the dashboard charts and totals." size="medium">
            <section class="core-drawer-section">
                <h3 class="core-drawer-section-title">Period</h3>
                <div class="core-drawer-fields">
                    <div class="master-field">
                        <label class="master-label" for="periodType">View</label>
                        <select class="master-select" name="period_type" id="periodType">
                            <option value="range" @selected($period['type'] === 'range')>Rolling days</option>
                            <option value="month" @selected($period['type'] === 'month')>Month</option>
                            <option value="quarter" @selected($period['type'] === 'quarter')>Quarter</option>
                            <option value="half" @selected($period['type'] === 'half')>Half year</option>
                            <option value="year" @selected($period['type'] === 'year')>Single year</option>
                            <option value="multi_year" @selected($period['type'] === 'multi_year')>Multi-year</option>
                        </select>
                    </div>
                    <div class="master-field period-control period-range">
                        <label class="master-label" for="periodRange">Range</label>
                        <select class="master-select" id="periodRange" name="range">
                            <option value="7" @selected((int) ($period['range'] ?? 30) === 7)>7 days</option>
                            <option value="30" @selected((int) ($period['range'] ?? 30) === 30)>30 days</option>
                            <option value="90" @selected((int) ($period['range'] ?? 30) === 90)>90 days</option>
                            <option value="180" @selected((int) ($period['range'] ?? 30) === 180)>180 days</option>
                            <option value="365" @selected((int) ($period['range'] ?? 30) === 365)>365 days</option>
                        </select>
                    </div>
                    <div class="master-field period-control period-year period-month period-quarter period-half">
                        <label class="master-label" for="periodYear">Year</label>
                        <select class="master-select" id="periodYear" name="year">
                            @foreach($availableYears as $year)
                                <option value="{{ $year }}" @selected((int) ($period['year'] ?? now()->year) === (int) $year)>{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field period-control period-month">
                        <label class="master-label" for="periodMonth">Month</label>
                        <select class="master-select" id="periodMonth" name="month">
                            @foreach(range(1, 12) as $month)
                                <option value="{{ $month }}" @selected((int) ($period['month'] ?? now()->month) === $month)>{{ \Carbon\Carbon::create(null, $month)->format('F') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field period-control period-quarter">
                        <label class="master-label" for="periodQuarter">Quarter</label>
                        <select class="master-select" id="periodQuarter" name="quarter">
                            <option value="1" @selected((int) ($period['quarter'] ?? 1) === 1)>Q1</option>
                            <option value="2" @selected((int) ($period['quarter'] ?? 1) === 2)>Q2</option>
                            <option value="3" @selected((int) ($period['quarter'] ?? 1) === 3)>Q3</option>
                            <option value="4" @selected((int) ($period['quarter'] ?? 1) === 4)>Q4</option>
                        </select>
                    </div>
                    <div class="master-field period-control period-half">
                        <label class="master-label" for="periodHalf">Half</label>
                        <select class="master-select" id="periodHalf" name="half">
                            <option value="1" @selected((int) ($period['half'] ?? 1) === 1)>H1</option>
                            <option value="2" @selected((int) ($period['half'] ?? 1) === 2)>H2</option>
                        </select>
                    </div>
                    <div class="master-field period-control period-multi_year">
                        <label class="master-label" for="periodFromYear">From</label>
                        <select class="master-select" id="periodFromYear" name="from_year">
                            @foreach($availableYears as $year)
                                <option value="{{ $year }}" @selected((int) ($period['from_year'] ?? now()->year - 2) === (int) $year)>{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field period-control period-multi_year">
                        <label class="master-label" for="periodToYear">To</label>
                        <select class="master-select" id="periodToYear" name="to_year">
                            @foreach($availableYears as $year)
                                <option value="{{ $year }}" @selected((int) ($period['to_year'] ?? now()->year) === (int) $year)>{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </section>
            <x-slot:footer>
                <button class="master-btn master-btn-primary" type="submit">
                    <i class="fa-solid fa-filter" aria-hidden="true"></i> Apply period
                </button>
            </x-slot:footer>
        </x-drawer>
    </form>
@endsection

@section('content')
@php
    $money = function ($value, $currency = 'INR') { return \App\Helpers\CommonHelper::amount($value, $currency); };
    $short = function ($value) {
        $value = (float) $value;
        if (abs($value) >= 10000000) return \App\Helpers\CommonHelper::indianCurrency($value / 10000000).' Cr';
        if (abs($value) >= 100000) return \App\Helpers\CommonHelper::indianCurrency($value / 100000).' L';
        if (abs($value) >= 1000) return \App\Helpers\CommonHelper::indianCurrency($value / 1000).' K';

        return \App\Helpers\CommonHelper::indianCurrency($value);
    };
@endphp

<div class="master-page" id="dashboardRoot" data-chart-data='@json($charts)'>
    <div class="dashboard-period-mobile">
        <button type="button" class="master-btn master-btn-soft" data-drawer-open="dashboardPeriodDrawer" aria-haspopup="dialog" aria-controls="dashboardPeriodDrawer" aria-expanded="false">
            <i class="fa-solid fa-sliders" aria-hidden="true"></i><span>Reporting period</span>
        </button>
        <div><strong>{{ $period['label'] }}</strong><small>{{ $start->format('d M Y') }} – {{ $end->format('d M Y') }}</small></div>
    </div>
    <div class="master-tabs" role="tablist" aria-label="Dashboard sections">
        <button class="master-tab active" id="dashboardTabOverview" data-tab="overview" type="button" role="tab" aria-selected="true" aria-controls="dashboardPanelOverview"><i class="fa-solid fa-gauge-high"></i> Overview</button>
        <button class="master-tab" id="dashboardTabSales" data-tab="sales" type="button" role="tab" aria-selected="false" aria-controls="dashboardPanelSales"><i class="fa-solid fa-chart-line"></i> Sales &amp; purchase</button>
        <button class="master-tab" id="dashboardTabFinance" data-tab="finance" type="button" role="tab" aria-selected="false" aria-controls="dashboardPanelFinance"><i class="fa-solid fa-indian-rupee-sign"></i> Finance</button>
        <button class="master-tab" id="dashboardTabOperations" data-tab="operations" type="button" role="tab" aria-selected="false" aria-controls="dashboardPanelOperations"><i class="fa-solid fa-diagram-project"></i> Operations</button>
        <button class="master-tab" id="dashboardTabModules" data-tab="modules" type="button" role="tab" aria-selected="false" aria-controls="dashboardPanelModules"><i class="fa-solid fa-layer-group"></i> All modules</button>
    </div>

    <section class="master-panel active" id="dashboardPanelOverview" data-panel="overview" role="tabpanel" aria-labelledby="dashboardTabOverview">
        <div class="master-metrics-grid six">
            <a class="master-metric-card blue" href="{{ $routes['clients'] }}"><div class="master-metric-icon"><i class="fa-solid fa-building-user"></i></div><div><span>Total Clients</span><strong>{{ $metrics['clients_total'] }}</strong><small>{{ $metrics['clients_approved'] }} approved · {{ $metrics['clients_under_review'] }} under review</small></div></a>
            <a class="master-metric-card purple" href="{{ $routes['leads'] }}"><div class="master-metric-icon"><i class="fa-solid fa-people-group"></i></div><div><span>Lead Pipeline</span><strong>{{ $metrics['leads_total'] }}</strong><small>{{ $metrics['leads_range'] }} new in this period</small></div></a>
            <a class="master-metric-card pink" href="{{ $routes['projects'] }}"><div class="master-metric-icon"><i class="fa-solid fa-briefcase"></i></div><div><span>Active Projects</span><strong>{{ $metrics['projects_active'] }}</strong><small>{{ $metrics['projects_at_risk'] }} at risk</small></div></a>
            <a class="master-metric-card green" href="{{ $routes['shipments'] }}"><div class="master-metric-icon"><i class="fa-solid fa-truck-fast"></i></div><div><span>Shipments</span><strong>{{ $metrics['shipments_total'] }}</strong><small>{{ $metrics['shipments_in_transit'] }} in transit · {{ $metrics['shipments_delayed'] }} delayed</small></div></a>
            <a class="master-metric-card orange" href="{{ $routes['products'] }}"><div class="master-metric-icon"><i class="fa-solid fa-box-open"></i></div><div><span>Products</span><strong>{{ $metrics['products_total'] }}</strong><small>{{ $metrics['products_ready_stock'] }} ready stock</small></div></a>
            <a class="master-metric-card dark" href="{{ $routes['tasks'] }}"><div class="master-metric-icon"><i class="fa-solid fa-list-check"></i></div><div><span>Open Tasks</span><strong>{{ $metrics['tasks_open'] }}</strong><small>{{ $metrics['tasks_due_today'] }} due today · {{ $metrics['tasks_overdue'] }} overdue</small></div></a>
        </div>

        @if(count($alerts))
            <section class="master-critical-strip" aria-labelledby="criticalAlertTitle">
                <div class="master-critical-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div class="master-critical-copy">
                    <span>Critical alerts</span>
                    <strong id="criticalAlertTitle">{{ count($alerts) }} item{{ count($alerts) === 1 ? '' : 's' }} need immediate attention</strong>
                    <div>@foreach(array_slice($alerts, 0, 3) as $alert)<a href="{{ $alert['url'] }}">{{ $alert['title'] }}: {{ $alert['message'] }}</a>@endforeach</div>
                </div>
                <a class="master-btn master-btn-soft" href="#immediateAttention">Review attention</a>
            </section>
        @endif

        <div class="master-card master-comparison-card">
            <div class="master-section-head">
                <div><p class="master-eyebrow">Performance Comparison</p><h2>Current performance against prior periods</h2></div>
                <span class="master-pill">Like-for-like dates</span>
            </div>
            <div class="master-comparison-grid">
                @foreach($comparisons as $comparison)
                    <section class="master-comparison-group">
                        <div class="master-comparison-head"><strong>{{ $comparison['label'] }}</strong><small>Current · Previous · Change</small></div>
                        @foreach(['sales' => ['Sales', true], 'margin' => ['Gross margin', true], 'net' => ['Net cashflow', true], 'projects' => ['New projects', false]] as $key => $definition)
                            @php($comparisonMetric = $comparison['metrics'][$key])
                            <div class="master-comparison-row">
                                <span>{{ $definition[0] }}</span>
                                <strong>{{ $definition[1] ? $short($comparisonMetric['current']) : number_format($comparisonMetric['current']) }}</strong>
                                <small>vs {{ $definition[1] ? $short($comparisonMetric['previous']) : number_format($comparisonMetric['previous']) }}</small>
                                <em class="{{ $comparisonMetric['change'] > 0 ? 'up' : ($comparisonMetric['change'] < 0 ? 'down' : 'flat') }}">
                                    <i class="fa-solid {{ $comparisonMetric['change'] > 0 ? 'fa-arrow-trend-up' : ($comparisonMetric['change'] < 0 ? 'fa-arrow-trend-down' : 'fa-minus') }}"></i>
                                    {{ abs($comparisonMetric['change']) }}%
                                </em>
                            </div>
                        @endforeach
                    </section>
                @endforeach
            </div>
        </div>

        <div class="master-card" id="immediateAttention">
            <div class="master-section-head">
                <div><p class="master-eyebrow">Immediate Attention Required</p><h2>Today, overdue and client portal updates</h2></div>
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
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Business movement</p><h2>Sales, purchases, income and expense trend</h2></div><span class="master-pill">{{ $period['label'] }}</span></div><canvas id="overviewComboChart" height="280"></canvas></div>
        </div>

        <div class="master-grid-4">
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Project Health</p><h2>Health split</h2></div></div><canvas id="projectHealthChart" height="240"></canvas><div class="master-legend" id="projectHealthLegend"></div></div>
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Shipment Status</p><h2>Logistics split</h2></div></div><canvas id="shipmentStatusChart" height="240"></canvas><div class="master-legend" id="shipmentStatusLegend"></div></div>
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Expense Category</p><h2>Expense breakdown</h2></div></div><canvas id="expenseCategoryChart" height="240"></canvas><div class="master-legend" id="expenseCategoryLegend"></div></div>
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Pipeline</p><h2>Records trend</h2></div></div><canvas id="pipelineChart" height="240"></canvas></div>
        </div>
    </section>

    <section class="master-panel" id="dashboardPanelSales" data-panel="sales" role="tabpanel" aria-labelledby="dashboardTabSales">
        <div class="master-metrics-grid four">
            <div class="master-metric-card blue"><div class="master-metric-icon"><i class="fa-solid fa-arrow-trend-up"></i></div><div><span>Period Sales</span><strong>{{ $short($metrics['period_sales']) }}</strong><small>Tax invoices raised</small></div></div>
            <div class="master-metric-card orange"><div class="master-metric-icon"><i class="fa-solid fa-cart-shopping"></i></div><div><span>Period Purchase</span><strong>{{ $short($metrics['period_purchase']) }}</strong><small>Vendor bills received</small></div></div>
            <div class="master-metric-card {{ $metrics['period_gross_margin'] < 0 ? 'pink' : 'green' }}"><div class="master-metric-icon"><i class="fa-solid {{ $metrics['period_gross_margin'] < 0 ? 'fa-arrow-trend-down' : 'fa-percent' }}"></i></div><div><span>Gross margin</span><strong>{{ $short($metrics['period_gross_margin']) }}</strong><small>{{ $metrics['period_gross_margin'] < 0 ? 'Purchases exceed sales' : $metrics['period_margin_percent'].'% margin' }}</small></div></div>
            <div class="master-metric-card purple"><div class="master-metric-icon"><i class="fa-solid fa-hand-holding-dollar"></i></div><div><span>Project Receipts</span><strong>{{ $short($metrics['period_project_inward']) }}</strong><small>Received on projects this period</small></div></div>
        </div>
        <div class="master-grid-2">
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Sales vs Purchase</p><h2>Sales and purchase by period</h2></div></div><canvas id="salesPurchaseChart" height="320"></canvas></div>
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Margin</p><h2>Gross margin trend</h2></div></div><canvas id="grossMarginChart" height="320"></canvas></div>
        </div>
        <div class="master-grid-2">
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Client Revenue</p><h2>Top sales by client</h2></div></div><canvas id="salesByClientChart" height="330"></canvas></div>
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Product Revenue</p><h2>Top sales by product</h2></div></div><canvas id="salesByProductChart" height="330"></canvas></div>
        </div>
        <div class="master-grid-3">
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Leads</p><h2>Lead funnel</h2></div></div><div class="master-status-list">@include('dashboard.partials.status-list', ['items' => $charts['status']['leads']])</div></div>
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Clients</p><h2>Client status</h2></div></div><div class="master-status-list">@include('dashboard.partials.status-list', ['items' => $charts['status']['clients']])</div></div>
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Purchase</p><h2>Purchases by vendor</h2></div></div><canvas id="purchaseByVendorChart" height="270"></canvas></div>
        </div>
    </section>

    <section class="master-panel" id="dashboardPanelFinance" data-panel="finance" role="tabpanel" aria-labelledby="dashboardTabFinance">
        <div class="master-metrics-grid four">
            <div class="master-metric-card green"><div class="master-metric-icon"><i class="fa-solid fa-circle-arrow-down"></i></div><div><span>Income</span><strong>{{ $short($metrics['period_income']) }}</strong><small>Cashflow credit in period</small></div></div>
            <div class="master-metric-card pink"><div class="master-metric-icon"><i class="fa-solid fa-circle-arrow-up"></i></div><div><span>Expenses</span><strong>{{ $short($metrics['period_expense']) }}</strong><small>Cashflow debit in period</small></div></div>
            <div class="master-metric-card {{ $metrics['period_net_income'] < 0 ? 'pink' : 'blue' }}"><div class="master-metric-icon"><i class="fa-solid {{ $metrics['period_net_income'] < 0 ? 'fa-arrow-trend-down' : 'fa-scale-balanced' }}"></i></div><div><span>Net income</span><strong>{{ $short($metrics['period_net_income']) }}</strong><small>{{ $metrics['period_net_income'] < 0 ? 'Expenses exceed income' : 'Income minus expenses' }}</small></div></div>
            <div class="master-metric-card orange"><div class="master-metric-icon"><i class="fa-solid fa-hourglass-half"></i></div><div><span>Outstanding</span><strong>{{ $short($metrics['project_outstanding_all']) }}</strong><small>Project estimated - inward</small></div></div>
        </div>
        <div class="master-grid-2">
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Finance</p><h2>Income and expenses by period</h2></div></div><canvas id="incomeExpenseChart" height="320"></canvas></div>
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Net</p><h2>Net income trend</h2></div></div><canvas id="netIncomeChart" height="320"></canvas></div>
        </div>
        <div class="master-grid-3">
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Expense Category</p><h2>Expense categories</h2></div></div><canvas id="expenseCategoryChart2" height="260"></canvas><div class="master-legend" id="expenseCategoryLegend2"></div></div>
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Project Payments</p><h2>Inward vs outward</h2></div></div><canvas id="projectPaymentChart" height="260"></canvas></div>
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Summary</p><h2>Finance summary</h2></div></div><div class="master-finance-summary"><div><span>Project Inward</span><strong class="green">{{ $money($metrics['project_inward_all']) }}</strong></div><div><span>Project Expense</span><strong class="red">{{ $money($metrics['project_outward_all']) }}</strong></div><div><span>Project Profit</span><strong class="{{ $metrics['project_profit_all'] >= 0 ? 'green' : 'red' }}">{{ $money($metrics['project_profit_all']) }}</strong></div><div><span>Cashflow Net</span><strong>{{ $money($metrics['cash_net_range']) }}</strong></div></div></div>
        </div>
    </section>

    <section class="master-panel" id="dashboardPanelOperations" data-panel="operations" role="tabpanel" aria-labelledby="dashboardTabOperations">
        <div class="master-metrics-grid four">
            <a class="master-metric-card pink" href="{{ $routes['projects'] }}"><div class="master-metric-icon"><i class="fa-solid fa-briefcase"></i></div><div><span>Total Projects</span><strong>{{ $metrics['projects_total'] }}</strong><small>{{ $metrics['projects_completed'] }} completed</small></div></a>
            <a class="master-metric-card green" href="{{ $routes['shipments'] }}"><div class="master-metric-icon"><i class="fa-solid fa-plane-departure"></i></div><div><span>In Transit</span><strong>{{ $metrics['shipments_in_transit'] }}</strong><small>{{ $metrics['shipments_delivered'] }} delivered</small></div></a>
            <a class="master-metric-card dark" href="{{ $routes['tasks'] }}"><div class="master-metric-icon"><i class="fa-solid fa-list-check"></i></div><div><span>Tasks</span><strong>{{ $metrics['tasks_total'] }}</strong><small>{{ $metrics['tasks_completed'] }} completed</small></div></a>
            <a class="master-metric-card orange" href="{{ $routes['vendors'] }}"><div class="master-metric-icon"><i class="fa-solid fa-user-gear"></i></div><div><span>Vendors</span><strong>{{ $metrics['vendors_total'] }}</strong><small>in the vendor ledger</small></div></a>
        </div>
        <div class="master-grid-3">
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Project Status</p><h2>Project workload</h2></div></div><div class="master-status-list">@include('dashboard.partials.status-list', ['items' => $charts['status']['projects']])</div></div>
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Task Status</p><h2>Task board</h2></div></div><div class="master-status-list">@include('dashboard.partials.status-list', ['items' => $charts['status']['tasks']])</div></div>
            <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Shipment Status</p><h2>Shipment board</h2></div></div><div class="master-status-list">@include('dashboard.partials.status-list', ['items' => $charts['status']['shipments']])</div></div>
        </div>
        <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Top Clients</p><h2>Project value by client</h2></div></div><div class="master-table-wrap"><table class="master-table"><thead><tr><th>Client</th><th>Projects</th><th>Avg Progress</th><th>Value</th></tr></thead><tbody>@forelse($topClients as $client)<tr><td><a href="{{ $client['url'] }}"><strong>{{ $client['name'] }}</strong></a></td><td>{{ $client['projects'] }}</td><td><div class="master-mini-progress"><span style="width:{{ $client['progress'] }}%"></span></div><small>{{ $client['progress'] }}%</small></td><td>{{ $money($client['value']) }}</td></tr>@empty<tr><td colspan="4"><div class="master-empty small">No project/client value data.</div></td></tr>@endforelse</tbody></table></div></div>
    </section>

    <section class="master-panel" id="dashboardPanelModules" data-panel="modules" role="tabpanel" aria-labelledby="dashboardTabModules">
        <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">System Coverage</p><h2>Module health</h2></div><span class="master-pill">{{ count($moduleHealth) }} modules</span></div><div class="master-module-grid">@foreach($moduleHealth as $module)<a href="{{ $module['route'] }}" class="master-module-card {{ $module['installed'] ? 'installed' : 'missing' }}"><span>{{ $module['installed'] ? 'Active' : 'Missing' }}</span><strong>{{ $module['name'] }}</strong><small>{{ $module['count'] }} records</small></a>@endforeach</div></div>
        <div class="master-card"><div class="master-section-head"><div><p class="master-eyebrow">Recent</p><h2>ERP activity feed</h2></div></div><div class="master-activity-list">@forelse($recentActivity as $activity)<a href="{{ $activity['url'] }}" class="master-activity-item"><div class="master-activity-type {{ $activity['type'] }}">{{ strtoupper(substr($activity['label'], 0, 1)) }}</div><div><strong>{{ $activity['title'] }}</strong><small>{{ $activity['label'] }} · {{ $activity['number'] }} @if($activity['status']) · {{ \Illuminate\Support\Str::headline($activity['status']) }} @endif</small></div><span>{{ $activity['date'] }}</span></a>@empty<div class="master-empty small">No activity yet.</div>@endforelse</div></div>
    </section>
</div>


@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/dashboard.css') }}">
@endpush
<div class="master-tooltip" id="dashTooltip"></div>


@push('scripts')
    <script src="{{ $assetVer('assets/js/dashboard.js') }}"></script>
@endpush
@endsection
