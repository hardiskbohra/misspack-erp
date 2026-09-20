@extends('layouts.app')

@section('page-title', 'Admin Dashboard')

@section('content')
@php
    $money = function ($value, $currency = '₹') { return $currency.number_format((float) $value, 2); };
    $short = function ($value) {
        $value = (float) $value;
        if (abs($value) >= 10000000) return '₹'.number_format($value / 10000000, 2).' Cr';
        if (abs($value) >= 100000) return '₹'.number_format($value / 100000, 2).' L';
        if (abs($value) >= 1000) return '₹'.number_format($value / 1000, 1).' K';
        return '₹'.number_format($value, 0);
    };
@endphp

<div class="master-page" style="padding:0;">
    <div class="master-hero">
        <div>
            <p class="master-eyebrow">ERP Command Center</p>
            <h1>MissPack Admin Dashboard</h1>
            <p>Detailed bird-eye analytics for sales, purchase, finance, operations, client activity, projects, shipments, products and module health.</p>
        </div>
        <form method="GET" action="{{ route('dashboard') }}" class="master-period-form" id="dashPeriodForm">
            <div class="master-field"><label>View</label><select name="period_type" id="periodType"><option value="range" {{ $period['type']==='range'?'selected':'' }}>Rolling Days</option><option value="quarter" {{ $period['type']==='quarter'?'selected':'' }}>Quarter</option><option value="half" {{ $period['type']==='half'?'selected':'' }}>Half Year</option><option value="year" {{ $period['type']==='year'?'selected':'' }}>Single Year</option><option value="multi_year" {{ $period['type']==='multi_year'?'selected':'' }}>Multi Year</option></select></div>
            <div class="master-field period-control period-range"><label>Range</label><select name="range"><option value="7" {{ (int)($period['range'] ?? 30)===7?'selected':'' }}>7 Days</option><option value="30" {{ (int)($period['range'] ?? 30)===30?'selected':'' }}>30 Days</option><option value="90" {{ (int)($period['range'] ?? 30)===90?'selected':'' }}>90 Days</option><option value="180" {{ (int)($period['range'] ?? 30)===180?'selected':'' }}>180 Days</option><option value="365" {{ (int)($period['range'] ?? 30)===365?'selected':'' }}>365 Days</option></select></div>
            <div class="master-field period-control period-year period-quarter period-half"><label>Year</label><select name="year">@foreach($availableYears as $year)<option value="{{ $year }}" {{ (int)($period['year'] ?? now()->year)===(int)$year?'selected':'' }}>{{ $year }}</option>@endforeach</select></div>
            <div class="master-field period-control period-quarter"><label>Quarter</label><select name="quarter"><option value="1" {{ (int)($period['quarter'] ?? 1)===1?'selected':'' }}>Q1</option><option value="2" {{ (int)($period['quarter'] ?? 1)===2?'selected':'' }}>Q2</option><option value="3" {{ (int)($period['quarter'] ?? 1)===3?'selected':'' }}>Q3</option><option value="4" {{ (int)($period['quarter'] ?? 1)===4?'selected':'' }}>Q4</option></select></div>
            <div class="master-field period-control period-half"><label>Half</label><select name="half"><option value="1" {{ (int)($period['half'] ?? 1)===1?'selected':'' }}>H1</option><option value="2" {{ (int)($period['half'] ?? 1)===2?'selected':'' }}>H2</option></select></div>
            <div class="master-field period-control period-multi_year"><label>From</label><select name="from_year">@foreach($availableYears as $year)<option value="{{ $year }}" {{ (int)($period['from_year'] ?? now()->year-2)===(int)$year?'selected':'' }}>{{ $year }}</option>@endforeach</select></div>
            <div class="master-field period-control period-multi_year"><label>To</label><select name="to_year">@foreach($availableYears as $year)<option value="{{ $year }}" {{ (int)($period['to_year'] ?? now()->year)===(int)$year?'selected':'' }}>{{ $year }}</option>@endforeach</select></div>
            <button class="master-filter-btn" type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
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

<style>
.master-page{position:relative;display:flex;flex-direction:column;gap:18px;background:#eef3ff;min-height:calc(100vh - 70px);padding:28px;color:#17233b}.master-page *{box-sizing:border-box}.master-hero{background:linear-gradient(135deg,#4f83f1,#7b61ff);border-radius:26px;padding:26px;color:#fff;display:flex;justify-content:space-between;gap:18px;box-shadow:0 18px 45px rgba(79,131,241,.22)}.master-eyebrow{margin:0 0 6px;text-transform:uppercase;letter-spacing:.13em;font-size:11px;font-weight:600;opacity:.78}.master-hero h1{margin:0;font-size:32px;font-weight:600}.master-hero p{margin:8px 0 0;max-width:760px;opacity:.9}.master-period-form{display:grid;grid-template-columns:repeat(4,minmax(120px,1fr));gap:10px;min-width:min(720px,100%);align-items:end}.master-field{display:flex;flex-direction:column;gap:5px}.master-field label{font-size:11px;text-transform:uppercase;font-weight:600;opacity:.82}.master-field select{height:42px;border:0;border-radius:12px;padding:8px 11px;font-weight:600;color:#17233b}.master-filter-btn{height:42px;border:0;border-radius:12px;padding:8px 13px;font-weight:600;background:#ef4770;color:#fff;cursor:pointer}.master-period-badge{grid-column:span 2;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.28);border-radius:14px;padding:9px 12px;font-weight:600}.master-period-badge span{display:block;font-size:11px;opacity:.8}.master-tabs{display:flex;gap:10px;overflow-x:auto;background:#fff;border:1px solid #dfe7f3;border-radius:20px;padding:10px;box-shadow:0 14px 35px rgba(25,42,70,.08)}.master-tab{border:1px solid #dfe7f3;background:#fff;color:#687386;border-radius:14px;padding:11px 15px;font-weight:600;display:inline-flex;gap:8px;align-items:center;cursor:pointer;white-space:nowrap}.master-tab.active{background:#4f83f1;border-color:#4f83f1;color:#fff;box-shadow:0 10px 22px rgba(79,131,241,.2)}.master-panel{display:none}.master-panel.active{display:flex;flex-direction:column;gap:18px}.master-metrics-grid{display:grid;gap:14px}.master-metrics-grid.six{grid-template-columns:repeat(6,minmax(0,1fr))}.master-metrics-grid.four{grid-template-columns:repeat(4,minmax(0,1fr))}.master-metric-card,.master-card{background:#fff;border:1px solid #dfe7f3;border-radius:22px;box-shadow:0 14px 35px rgba(25,42,70,.08)}.master-metric-card{padding:16px;text-decoration:none;color:#17233b;display:flex;gap:13px;align-items:center;min-height:116px;transition:.2s}.master-metric-card:hover{transform:translateY(-2px);text-decoration:none;color:#17233b}.master-metric-icon{width:52px;height:52px;border-radius:17px;display:grid;place-items:center;color:#fff;font-size:21px;flex:0 0 52px}.master-metric-card.blue .master-metric-icon{background:#159ff7}.master-metric-card.purple .master-metric-icon{background:#8b5cf6}.master-metric-card.pink .master-metric-icon{background:#ef4770}.master-metric-card.green .master-metric-icon{background:#10b981}.master-metric-card.orange .master-metric-icon{background:#f59e0b}.master-metric-card.dark .master-metric-icon{background:#17233b}.master-metric-card span{display:block;color:#687386;font-size:12px;text-transform:uppercase;font-weight:600}.master-metric-card strong{display:block;font-size:24px;margin:4px 0}.master-metric-card small{display:block;color:#8792a5;font-weight:500}.master-card{padding:18px}.master-grid-main{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(320px,.5fr);gap:18px}.master-grid-4{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px}.master-grid-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.master-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.master-section-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:14px}.master-section-head h2{margin:0;font-size:20px}.master-pill{background:#eef3ff;color:#4f83f1;border-radius:999px;padding:7px 10px;font-size:12px;font-weight:600}.master-pill.danger{background:#fff0f4;color:#ef4770}.master-alert-list,.master-status-list,.master-activity-list{display:flex;flex-direction:column;gap:10px}.master-attention-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.master-attention-block{border:1px solid #dfe7f3;border-radius:18px;background:#fbfdff;padding:14px}.master-attention-title{display:flex;align-items:center;gap:10px;margin-bottom:10px}.master-attention-title>span{width:42px;height:42px;border-radius:14px;background:#eef3ff;display:grid;place-items:center;font-size:19px}.master-attention-title strong,.master-attention-title small{display:block}.master-attention-title small{color:#687386;font-weight:600;margin-top:3px}.master-attention-list{display:flex;flex-direction:column;gap:8px;max-height:330px;overflow:auto;padding-right:3px}.master-attention-item{display:grid;grid-template-columns:1fr auto;gap:10px;align-items:center;text-decoration:none;color:#17233b;background:#fff;border:1px solid #edf0f7;border-radius:14px;padding:11px}.master-attention-item:hover{border-color:#4f83f1;text-decoration:none}.master-attention-item strong{display:block;font-size:13px}.master-attention-item small{display:block;color:#687386;font-weight:500;margin-top:3px;line-height:1.35}.master-mini-badge{border-radius:999px;padding:5px 8px;font-size:11px;font-weight:600;background:#eef3ff;color:#4f83f1;white-space:nowrap}.master-mini-badge.danger{background:#fff0f4;color:#ef4770}.master-mini-badge.warning{background:#fff4e5;color:#d97706}.master-mini-badge.info{background:#e8f7ff;color:#0284c7}.master-mini-badge.success{background:#e8fff7;color:#0e9f6e}.master-mini-badge.today{background:#ece7ff;color:#7c3aed}.master-alert-item{display:flex;gap:11px;align-items:flex-start;text-decoration:none;color:#17233b;border:1px solid #dfe7f3;border-radius:16px;padding:12px;background:#fff}.master-alert-item small,.master-activity-item small{display:block;color:#687386;margin-top:3px;font-weight:500}.master-alert-dot{width:10px;height:10px;border-radius:50%;margin-top:5px;background:#f59e0b;box-shadow:0 0 0 4px #fff4e5}.master-alert-item.danger .master-alert-dot{background:#ef4770;box-shadow:0 0 0 4px #fff0f4}.master-empty{padding:30px;text-align:center;border:1px dashed #d8deea;border-radius:18px;color:#687386;font-weight:600;background:#fbfcff}.master-empty.small{padding:18px}.master-legend{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}.master-legend-item{display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:#687386}.master-legend-color{width:10px;height:10px;border-radius:50%}.master-status-row{display:grid;grid-template-columns:1fr auto;gap:10px;align-items:center}.master-status-row strong{font-size:13px}.master-status-row span{font-weight:600;color:#17233b}.master-status-bar{grid-column:1/-1;height:9px;background:#eef3ff;border-radius:999px;overflow:hidden}.master-status-bar i{display:block;height:100%;border-radius:999px;background:linear-gradient(90deg,#4f83f1,#10b981)}.master-table-wrap{overflow:auto}.master-table{width:100%;border-collapse:collapse;min-width:650px}.master-table th,.master-table td{padding:13px 14px;border-bottom:1px solid #dfe7f3;text-align:left}.master-table th{font-size:11px;text-transform:uppercase;letter-spacing:.07em;color:#7d8aa0;background:#fbfdff}.master-table a{text-decoration:none;color:#4f83f1}.master-mini-progress{height:8px;background:#eef3ff;border-radius:999px;overflow:hidden;min-width:120px}.master-mini-progress span{display:block;height:100%;background:linear-gradient(90deg,#4f83f1,#10b981)}.master-finance-summary{display:grid;grid-template-columns:1fr 1fr;gap:12px}.master-finance-summary div{background:#f8fbff;border:1px solid #dfe7f3;border-radius:16px;padding:14px}.master-finance-summary span{display:block;color:#687386;font-size:12px;font-weight:600;text-transform:uppercase}.master-finance-summary strong{display:block;margin-top:7px;font-size:20px}.green{color:#0e9f6e!important}.red{color:#e11d48!important}.master-module-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.master-module-card{text-decoration:none;color:#17233b;border:1px solid #dfe7f3;border-radius:18px;padding:16px;background:#fbfdff}.master-module-card span{font-size:11px;font-weight:600;text-transform:uppercase;border-radius:999px;padding:5px 8px;background:#f3f6fb;color:#536079}.master-module-card.installed span{background:#e8fff7;color:#0e9f6e}.master-module-card strong{display:block;margin-top:12px;font-size:18px}.master-module-card small{display:block;color:#687386;margin-top:4px}.master-activity-item{display:grid;grid-template-columns:44px 1fr auto;gap:12px;align-items:center;text-decoration:none;color:#17233b;border:1px solid #dfe7f3;border-radius:16px;padding:12px;background:#fff}.master-activity-type{width:44px;height:44px;border-radius:14px;display:grid;place-items:center;background:#eef3ff;color:#4f83f1;font-weight:600}.master-activity-type.project{background:#fff0f4;color:#ef4770}.master-activity-type.shipment{background:#e8fff7;color:#10b981}.master-activity-type.quote{background:#fff4e5;color:#f59e0b}.master-activity-item>span{color:#687386;font-size:12px;font-weight:600}.master-tooltip{position:fixed;z-index:99999;pointer-events:none;background:#17233b;color:#fff;border-radius:12px;padding:9px 11px;font-size:12px;font-weight:600;box-shadow:0 12px 28px rgba(0,0,0,.18);display:none;max-width:260px}.master-tooltip strong{display:block;font-size:13px;margin-bottom:3px}.master-tooltip span{display:block;opacity:.86}canvas{width:100%;max-width:100%}@media(max-width:1600px){.master-metrics-grid.six{grid-template-columns:repeat(3,minmax(0,1fr))}.master-grid-4,.master-attention-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.master-metrics-grid.four{grid-template-columns:repeat(2,minmax(0,1fr))}.master-grid-main,.master-grid-3,.master-grid-2{grid-template-columns:1fr}.master-module-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:900px){.master-period-form{grid-template-columns:repeat(2,minmax(0,1fr));min-width:0;width:100%}.master-period-badge{grid-column:1/-1}}@media(max-width:767px){.master-page{padding:14px}.master-hero{flex-direction:column;padding:20px}.master-hero h1{font-size:25px}.master-period-form{grid-template-columns:1fr}.master-metrics-grid.six,.master-metrics-grid.four,.master-grid-4,.master-attention-grid,.master-module-grid{grid-template-columns:1fr}.master-activity-item{grid-template-columns:44px 1fr}.master-activity-item>span{grid-column:2}.master-finance-summary{grid-template-columns:1fr}.master-card{padding:14px}.master-attention-list{max-height:none}}
</style>

<div class="master-tooltip" id="dashTooltip"></div>

<script>
const dashData = @json($charts);
const colors = ['#4f83f1','#10b981','#ef4770','#f59e0b','#8b5cf6','#12cbb7','#17233b','#e11d48','#06b6d4','#84cc16'];
const chartStore = {};
const tooltip = document.getElementById('dashTooltip');

function formatMoney(v){v=Number(v)||0;return '₹'+v.toLocaleString('en-IN',{maximumFractionDigits:2});}
function formatShort(v){v=Number(v)||0;if(Math.abs(v)>=10000000)return '₹'+(v/10000000).toFixed(2)+' Cr';if(Math.abs(v)>=100000)return '₹'+(v/100000).toFixed(2)+' L';if(Math.abs(v)>=1000)return '₹'+(v/1000).toFixed(1)+' K';return '₹'+v.toFixed(0);}
function setupCanvas(canvas){const dpr=window.devicePixelRatio||1;const rect=canvas.getBoundingClientRect();const h=parseInt(canvas.getAttribute('height'))||260;canvas.width=Math.max(rect.width,320)*dpr;canvas.height=h*dpr;const ctx=canvas.getContext('2d');ctx.setTransform(dpr,0,0,dpr,0,0);return {ctx,width:Math.max(rect.width,320),height:h};}
function showTip(e, hit){if(!hit){tooltip.style.display='none';return;}tooltip.innerHTML='<strong>'+hit.title+'</strong><span>'+hit.label+'</span><span>'+hit.value+'</span>';tooltip.style.display='block';tooltip.style.left=(e.clientX+14)+'px';tooltip.style.top=(e.clientY+14)+'px';}
function bindHover(canvas,id){canvas.onmousemove=function(e){const rect=canvas.getBoundingClientRect();const x=e.clientX-rect.left,y=e.clientY-rect.top;const store=chartStore[id]||{hits:[]};let hit=null;if(store.type==='donut'){for(const h of store.hits){const dx=x-h.cx,dy=y-h.cy,dist=Math.sqrt(dx*dx+dy*dy);let angle=Math.atan2(dy,dx);if(angle<-Math.PI/2)angle+=Math.PI*2;if(dist>=h.inner&&dist<=h.r&&angle>=h.start&&angle<=h.end){hit=h;break;}}}else{for(const h of store.hits){if(h.kind==='rect'&&x>=h.x&&x<=h.x+h.w&&y>=h.y&&y<=h.y+h.h){hit=h;break;}if(h.kind==='point'){const dx=x-h.x,dy=y-h.y;if(Math.sqrt(dx*dx+dy*dy)<=8){hit=h;break;}}}}showTip(e,hit);};canvas.onmouseleave=function(){tooltip.style.display='none';};}
function niceMax(values){const max=Math.max(...values.map(v=>Number(v)||0),1);return max*1.15;}
function drawLineChart(id,labels,datasets,prefix='₹'){const canvas=document.getElementById(id);if(!canvas)return;const {ctx,width,height}=setupCanvas(canvas);const pad={l:54,r:18,t:24,b:38};const plotW=width-pad.l-pad.r,plotH=height-pad.t-pad.b;const values=[];datasets.forEach(ds=>ds.data.forEach(v=>values.push(Number(v)||0)));const max=niceMax(values),min=Math.min(0,...values);const hits=[];ctx.clearRect(0,0,width,height);ctx.strokeStyle='#dfe7f3';ctx.fillStyle='#687386';ctx.font='11px Inter,Arial';for(let i=0;i<=4;i++){let y=pad.t+(plotH/4)*i;ctx.beginPath();ctx.moveTo(pad.l,y);ctx.lineTo(width-pad.r,y);ctx.stroke();ctx.fillText(formatShort(max-((max-min)/4)*i),6,y+4);}datasets.forEach((ds,di)=>{ctx.strokeStyle=ds.color||colors[di];ctx.lineWidth=2.5;ctx.beginPath();ds.data.forEach((v,i)=>{let x=pad.l+(labels.length<=1?0:(plotW/(labels.length-1))*i);let y=pad.t+plotH-(((Number(v)||0)-min)/(max-min||1))*plotH;if(i===0)ctx.moveTo(x,y);else ctx.lineTo(x,y);});ctx.stroke();ds.data.forEach((v,i)=>{let x=pad.l+(labels.length<=1?0:(plotW/(labels.length-1))*i);let y=pad.t+plotH-(((Number(v)||0)-min)/(max-min||1))*plotH;ctx.fillStyle=ds.color||colors[di];ctx.beginPath();ctx.arc(x,y,3.5,0,Math.PI*2);ctx.fill();hits.push({kind:'point',x,y,title:ds.label,label:labels[i],value:prefix?formatMoney(v):v});});});const step=Math.max(1,Math.ceil(labels.length/6));ctx.fillStyle='#687386';labels.forEach((lab,i)=>{if(i%step===0||i===labels.length-1){let x=pad.l+(labels.length<=1?0:(plotW/(labels.length-1))*i);ctx.fillText(lab,x-14,height-12);}});drawLegend(ctx,datasets,pad.l,8);chartStore[id]={type:'line',hits};bindHover(canvas,id);}
function drawBarChart(id,labels,datasets,prefix='₹'){const canvas=document.getElementById(id);if(!canvas)return;const {ctx,width,height}=setupCanvas(canvas);const pad={l:54,r:16,t:24,b:40};const plotW=width-pad.l-pad.r,plotH=height-pad.t-pad.b;const values=[];datasets.forEach(ds=>ds.data.forEach(v=>values.push(Number(v)||0)));const max=niceMax(values);const hits=[];ctx.clearRect(0,0,width,height);ctx.strokeStyle='#dfe7f3';ctx.fillStyle='#687386';ctx.font='11px Inter,Arial';for(let i=0;i<=4;i++){let y=pad.t+(plotH/4)*i;ctx.beginPath();ctx.moveTo(pad.l,y);ctx.lineTo(width-pad.r,y);ctx.stroke();ctx.fillText(formatShort(max-(max/4)*i),6,y+4);}const groupW=plotW/Math.max(labels.length,1);const barW=Math.max(5,(groupW-10)/datasets.length);labels.forEach((lab,i)=>{datasets.forEach((ds,di)=>{let v=Number(ds.data[i])||0;let h=(v/max)*plotH;let x=pad.l+i*groupW+5+di*barW;let y=pad.t+plotH-h;ctx.fillStyle=ds.color||colors[di];roundRect(ctx,x,y,barW-2,h,5);ctx.fill();hits.push({kind:'rect',x,y,w:barW-2,h,title:ds.label,label:lab,value:prefix?formatMoney(v):v});});});const step=Math.max(1,Math.ceil(labels.length/6));ctx.fillStyle='#687386';labels.forEach((lab,i)=>{if(i%step===0||i===labels.length-1)ctx.fillText(lab,pad.l+i*groupW,height-12);});drawLegend(ctx,datasets,pad.l,8);chartStore[id]={type:'bar',hits};bindHover(canvas,id);}
function drawHorizontalBar(id,dataObj,prefix='₹'){const canvas=document.getElementById(id);if(!canvas)return;const entries=Object.entries(dataObj||{}).filter(e=>Number(e[1])>0).slice(0,8);const {ctx,width,height}=setupCanvas(canvas);const pad={l:130,r:20,t:20,b:20};const plotW=width-pad.l-pad.r;const rowH=(height-pad.t-pad.b)/Math.max(entries.length,1);const max=niceMax(entries.map(e=>e[1]));const hits=[];ctx.clearRect(0,0,width,height);ctx.font='12px Inter,Arial';if(!entries.length){ctx.fillStyle='#687386';ctx.textAlign='center';ctx.fillText('No data',width/2,height/2);chartStore[id]={type:'bar',hits};bindHover(canvas,id);return;}entries.forEach((e,i)=>{let label=e[0],v=Number(e[1])||0,y=pad.t+i*rowH+6,h=Math.max(12,rowH-12),w=(v/max)*plotW;ctx.fillStyle='#687386';ctx.textAlign='right';ctx.fillText(label.substring(0,18),pad.l-8,y+h/2+4);ctx.fillStyle=colors[i%colors.length];roundRect(ctx,pad.l,y,w,h,7);ctx.fill();ctx.fillStyle='#17233b';ctx.textAlign='left';ctx.fillText(formatShort(v),pad.l+w+6,y+h/2+4);hits.push({kind:'rect',x:pad.l,y,w,h,title:label,label:'Value',value:prefix?formatMoney(v):v});});chartStore[id]={type:'bar',hits};bindHover(canvas,id);}
function drawDonut(id,dataObj,legendId,prefix=''){const canvas=document.getElementById(id);if(!canvas)return;const {ctx,width,height}=setupCanvas(canvas);const entries=Object.entries(dataObj||{}).filter(e=>Number(e[1])>0);const total=entries.reduce((s,e)=>s+Number(e[1]),0);const cx=width/2,cy=height/2,r=Math.min(width,height)/2-18,inner=r*.58;const hits=[];ctx.clearRect(0,0,width,height);if(!total){ctx.fillStyle='#687386';ctx.font='13px Inter,Arial';ctx.textAlign='center';ctx.fillText('No data',cx,cy);return;}let start=-Math.PI/2;entries.forEach((e,i)=>{let val=Number(e[1]);let end=start+(val/total)*Math.PI*2;ctx.beginPath();ctx.arc(cx,cy,r,start,end);ctx.arc(cx,cy,inner,end,start,true);ctx.closePath();ctx.fillStyle=colors[i%colors.length];ctx.fill();hits.push({cx,cy,r,inner,start,end,title:e[0],label:((val/total)*100).toFixed(1)+'%',value:prefix?formatMoney(val):val});start=end;});ctx.fillStyle='#17233b';ctx.font='900 24px Inter,Arial';ctx.textAlign='center';ctx.fillText(prefix?formatShort(total):total,cx,cy+5);ctx.font='11px Inter,Arial';ctx.fillStyle='#687386';ctx.fillText('Total',cx,cy+23);const legend=document.getElementById(legendId);if(legend){legend.innerHTML=entries.map((e,i)=>`<span class="master-legend-item"><i class="master-legend-color" style="background:${colors[i%colors.length]}"></i>${e[0]} (${prefix?formatShort(e[1]):e[1]})</span>`).join('');}chartStore[id]={type:'donut',hits};bindHover(canvas,id);}
function drawLegend(ctx,datasets,x,y){ctx.font='11px Inter,Arial';let lx=x;datasets.forEach((ds,di)=>{ctx.fillStyle=ds.color||colors[di];ctx.fillRect(lx,y,10,10);ctx.fillStyle='#17233b';ctx.fillText(ds.label,lx+14,y+9);lx+=ctx.measureText(ds.label).width+42;});}
function roundRect(ctx,x,y,w,h,r){ctx.beginPath();ctx.moveTo(x+r,y);ctx.lineTo(x+w-r,y);ctx.quadraticCurveTo(x+w,y,x+w,y+r);ctx.lineTo(x+w,y+h-r);ctx.quadraticCurveTo(x+w,y+h,x+w-r,y+h);ctx.lineTo(x+r,y+h);ctx.quadraticCurveTo(x,y+h,x,y+h-r);ctx.lineTo(x,y+r);ctx.quadraticCurveTo(x,y,x+r,y);ctx.closePath();}
function drawAllCharts(){
    const labels=dashData.labels;
    drawLineChart('overviewComboChart',labels,[{label:'Sales',data:dashData.salesPurchase.sales,color:'#4f83f1'},{label:'Purchase',data:dashData.salesPurchase.purchase,color:'#f59e0b'},{label:'Income',data:dashData.incomeExpense.income,color:'#10b981'},{label:'Expenses',data:dashData.incomeExpense.expense,color:'#ef4770'}]);
    drawDonut('projectHealthChart',dashData.status.projectHealth,'projectHealthLegend','');
    drawDonut('shipmentStatusChart',dashData.status.shipments,'shipmentStatusLegend','');
    drawDonut('expenseCategoryChart',dashData.pies.expenseCategories,'expenseCategoryLegend','₹');
    drawBarChart('pipelineChart',labels,[{label:'Leads',data:dashData.pipeline.leads,color:'#8b5cf6'},{label:'Quotes',data:dashData.pipeline.quotes,color:'#4f83f1'},{label:'Projects',data:dashData.pipeline.projects,color:'#ef4770'},{label:'Shipments',data:dashData.pipeline.shipments,color:'#10b981'}],'');
    drawBarChart('salesPurchaseChart',labels,[{label:'Sales',data:dashData.salesPurchase.sales,color:'#4f83f1'},{label:'Purchase',data:dashData.salesPurchase.purchase,color:'#f59e0b'}]);
    drawLineChart('grossMarginChart',labels,[{label:'Gross Margin',data:dashData.salesPurchase.margin,color:'#10b981'}]);
    drawHorizontalBar('salesByClientChart',dashData.pies.salesByClient,'₹');
    drawHorizontalBar('salesByProductChart',dashData.pies.salesByProduct,'₹');
    drawHorizontalBar('purchaseByVendorChart',dashData.pies.purchaseByVendor,'₹');
    drawBarChart('incomeExpenseChart',labels,[{label:'Income',data:dashData.incomeExpense.income,color:'#10b981'},{label:'Expenses',data:dashData.incomeExpense.expense,color:'#ef4770'}]);
    drawLineChart('netIncomeChart',labels,[{label:'Net Income',data:dashData.incomeExpense.net,color:'#4f83f1'}]);
    drawDonut('expenseCategoryChart2',dashData.pies.expenseCategories,'expenseCategoryLegend2','₹');
    drawBarChart('projectPaymentChart',labels,[{label:'Project Inward',data:dashData.projectPayments.inward,color:'#4f83f1'},{label:'Project Outward',data:dashData.projectPayments.outward,color:'#ef4770'}]);
}
function togglePeriodControls(){const type=document.getElementById('periodType').value;document.querySelectorAll('.period-control').forEach(el=>el.style.display='none');document.querySelectorAll('.period-'+type).forEach(el=>el.style.display='flex');}
document.addEventListener('DOMContentLoaded',function(){
    togglePeriodControls();document.getElementById('periodType')?.addEventListener('change',togglePeriodControls);
    document.querySelectorAll('.master-tab').forEach(btn=>btn.addEventListener('click',function(){document.querySelectorAll('.master-tab').forEach(b=>b.classList.remove('active'));btn.classList.add('active');document.querySelectorAll('.master-panel').forEach(p=>p.classList.toggle('active',p.dataset.panel===btn.dataset.tab));setTimeout(drawAllCharts,60);}));
    drawAllCharts();
});
window.addEventListener('resize',function(){clearTimeout(window.dashResizeTimer);window.dashResizeTimer=setTimeout(drawAllCharts,180);});
</script>
@endsection
