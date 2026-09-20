@extends('layouts.app')

@section('page-title', 'Cashflow Reports')

@section('content')
    <style>
        :root {
            --cf-primary: #4f83f1;
            --cf-primary-2: #6366f1;
            --cf-info: #159ff7;
            --cf-teal: #12cbb7;
            --cf-purple: #8b5cf6;
            --cf-orange: #f59e0b;
            --cf-red: #ef4770;
            --cf-green: #10b981;
            --cf-dark: #17233b;
            --cf-muted: #687386;
            --cf-border: #dfe7f3;
            --cf-bg: #eef3ff;
            --cf-soft: #edf5ff;
            --cf-shadow: 0 14px 35px rgba(25, 42, 70, .08);
        }

        .cf-page,
        .cf-page * {
            box-sizing: border-box
        }

        .cf-page {
            background: var(--cf-bg);
            min-height: calc(100vh - 70px);
            padding: 28px;
            color: var(--cf-dark);
            font-size: 14px
        }

        .cf-card {
            background: #fff;
            border: 1px solid var(--cf-border);
            border-radius: 18px;
            box-shadow: var(--cf-shadow)
        }

        .cf-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding: 22px 28px;
            margin-bottom: 24px
        }

        .cf-header h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 900
        }

        .cf-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap
        }

        .cf-btn {
            min-height: 42px;
            border: 0;
            border-radius: 12px;
            padding: 11px 18px;
            font-size: 14px;
            font-weight: 900;
            text-decoration: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            white-space: nowrap
        }

        .cf-btn-primary {
            background: linear-gradient(135deg, var(--cf-primary), var(--cf-primary-2));
            color: #fff
        }

        .cf-btn-soft {
            background: var(--cf-soft);
            color: var(--cf-primary)
        }

        .cf-btn-light {
            background: #f3f6fb;
            color: var(--cf-dark)
        }

        .cf-filter {
            padding: 22px 24px;
            margin-bottom: 24px
        }

        .cf-filter-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px
        }

        .cf-input,
        .cf-select {
            width: 100%;
            height: 44px;
            border: 1px solid #d8e2ef;
            border-radius: 12px;
            padding: 10px 14px;
            font-size: 14px;
            font-weight: 500;
            color: var(--cf-dark);
            outline: none
        }

        .cf-label {
            display: block;
            margin-bottom: 7px;
            color: #536079;
            font-size: 12px;
            font-weight: 700
        }

        .cf-summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 24px
        }

        .cf-stat {
            padding: 22px;
            border-radius: 18px
        }

        .cf-stat.blue {
            background: #dff1ff
        }

        .cf-stat.purple {
            background: #ece7ff
        }

        .cf-stat.teal {
            background: #dcf8f3
        }

        .cf-stat.orange {
            background: #fff2dc
        }

        .cf-stat p {
            margin: 0 0 6px;
            color: #536079;
            font-weight: 900
        }

        .cf-stat strong {
            font-size: 24px
        }

        .cf-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
            margin-bottom: 24px
        }

        .cf-section {
            padding: 22px
        }

        .cf-section h3 {
            margin: 0 0 16px;
            font-size: 16px;
            font-weight: 900
        }

        .cf-table-wrap {
            overflow-x: auto
        }

        .cf-table {
            width: 100%;
            min-width: 780px;
            border-collapse: collapse
        }

        .cf-table th,
        .cf-table td {
            padding: 14px;
            border-bottom: 1px solid var(--cf-border);
            text-align: left
        }

        .cf-table th {
            font-size: 11px;
            color: #7d8aa0;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .06em
        }

        .credit {
            color: #059669;
            font-weight: 900
        }

        .debit {
            color: #e11d48;
            font-weight: 900
        }

        .cf-sub {
            display: block;
            color: var(--cf-muted);
            font-size: 12px;
            margin-top: 3px;
            font-weight: 700
        }

        .cf-badge {
            display: inline-flex;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase
        }

        .status-reconciled {
            background: #e8fff7;
            color: #0e9f6e
        }

        .status-booked {
            background: #eaf1ff;
            color: #3f7cf4
        }

        .status-pending {
            background: #fff4e5;
            color: #d97706
        }

        .status-disputed {
            background: #ffeaf0;
            color: #e11d48
        }

        .status-ignored {
            background: #f3f6fb;
            color: #536079
        }

        @media(max-width:1100px) {
            .cf-filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr))
            }

            .cf-summary {
                grid-template-columns: repeat(2, minmax(0, 1fr))
            }

            .cf-grid {
                grid-template-columns: 1fr
            }
        }

        @media(max-width:700px) {
            .cf-page {
                padding: 14px
            }

            .cf-header {
                align-items: flex-start;
                flex-direction: column;
                padding: 18px
            }

            .cf-actions,
            .cf-actions .cf-btn {
                width: 100%
            }

            .cf-filter-grid,
            .cf-summary {
                grid-template-columns: 1fr
            }

            .cf-section {
                padding: 18px
            }
        }
    </style>
    <div class="cf">
        <div class="cf-card cf-header">
            <div>
                <h1>Cashflow Reports</h1>
                <p style="margin:5px 0 0;color:#687386;font-weight:500;">{{ $dateFrom->format('d M Y') }} to
                    {{ $dateTo->format('d M Y') }}</p>
            </div>
            <div class="cf-actions">
                <a href="{{ route('cashflows.index') }}" class="cf-btn cf-btn-light">Back</a>
                <a href="{{ route('cashflows.settings.index') }}" class="cf-btn cf-btn-soft">Settings</a>
                <a href="{{ route('cashflows.reports.pdf', request()->query()) }}" class="cf-btn cf-btn-primary">Download PDF</a></div>
        </div>
        <form method="GET" action="{{ route('cashflows.reports') }}" class="cf-card cf-filter">
            <div class="cf-filter-grid">
                <div><label class="cf-label">Report Type</label><select class="cf-select" name="report_type">
                        <option value="overall" @selected($reportType === 'overall')>Overall Cashflow</option>
                        <option value="client" @selected($reportType === 'client')>Client Statement</option>
                        <option value="vendor" @selected($reportType === 'vendor')>Vendor Statement</option>
                        <option value="cash_expense" @selected($reportType === 'cash_expense')>Cash Expenses</option>
                    </select></div>
                <div><label class="cf-label">Period</label><select class="cf-select" name="period">
                        <option value="day" @selected($period === 'day')>Day Wise</option>
                        <option value="week" @selected($period === 'week')>Week Wise</option>
                        <option value="month" @selected($period === 'month')>Month Wise</option>
                        <option value="quarter" @selected($period === 'quarter')>Quarter Wise</option>
                        <option value="year" @selected($period === 'year')>Year Wise</option>
                    </select></div>
                <div><label class="cf-label">Base Date</label><input class="cf-input" type="date" name="date"
                        value="{{ request('date', now()->toDateString()) }}"></div>
                <div><label class="cf-label">Account</label><select class="cf-select" name="account_id">
                        <option value="all">All Accounts</option>@foreach($accounts as $account)<option
                            value="{{ $account->id }}" @selected((string) $accountId === (string) $account->id)>
                        {{ $account->account_name }}</option>@endforeach
                    </select></div>
                <div><label class="cf-label">From Date</label><input class="cf-input" type="date" name="date_from"
                        value="{{ $dateFrom->toDateString() }}"></div>
                <div><label class="cf-label">To Date</label><input class="cf-input" type="date" name="date_to"
                        value="{{ $dateTo->toDateString() }}"></div>
                <div><label class="cf-label">Client</label><select class="cf-select" name="client_id">
                        <option value="all">All Clients</option>@foreach($clients as $client)<option
                            value="{{ $client->id }}" @selected((string) $clientId === (string) $client->id)>
                        {{ $client->company_name }}</option>@endforeach
                    </select></div>
                <div><label class="cf-label">Vendor</label><select class="cf-select" name="vendor_id">
                        <option value="all">All Vendors</option>@foreach($vendors as $vendor)<option
                            value="{{ $vendor->id }}" @selected((string) $vendorId === (string) $vendor->id)>
                        {{ $vendor->vendor_name }}</option>@endforeach
                    </select></div>
                <div style="display:flex;gap:10px;align-items:end;"><button class="cf-btn cf-btn-primary"
                        type="submit">Generate</button><a class="cf-btn cf-btn-light"
                        href="{{ route('cashflows.reports') }}">Reset</a></div>
            </div>
        </form>

        <div class="cf-summary">
            <div class="cf-stat blue">
                <p>Total Credit</p><strong>{{ number_format($summary['credit'], 2) }}</strong>
            </div>
            <div class="cf-stat purple">
                <p>Total Debit</p><strong>{{ number_format($summary['debit'], 2) }}</strong>
            </div>
            <div class="cf-stat teal">
                <p>Net Cashflow</p><strong>{{ number_format($summary['net'], 2) }}</strong>
            </div>
            <div class="cf-stat orange">
                <p>Total Entries</p><strong>{{ $summary['count'] }}</strong>
            </div>
        </div>

        <div class="cf-grid">
            <div class="cf-card cf-section">
                <h3>Account Summary</h3>
                <div class="cf-table-wrap">
                    <table class="cf-table">
                        <thead>
                            <tr>
                                <th>Account</th>
                                <th>Credit</th>
                                <th>Debit</th>
                                <th>Net</th>
                            </tr>
                        </thead>
                        <tbody>@forelse($accountSummary as $row)<tr>
                            <td>{{ $row['name'] }}<span class="cf-sub">{{ $row['type'] }}</span></td>
                            <td class="credit">{{ number_format($row['credit'], 2) }}</td>
                            <td class="debit">{{ number_format($row['debit'], 2) }}</td>
                            <td>{{ number_format($row['credit'] - $row['debit'], 2) }}</td>
                        </tr>@empty<tr>
                                <td colspan="4">No data.</td>
                            </tr>@endforelse</tbody>
                    </table>
                </div>
            </div>
            <div class="cf-card cf-section">
                <h3>Category Summary</h3>
                <div class="cf-table-wrap">
                    <table class="cf-table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Credit</th>
                                <th>Debit</th>
                                <th>Net</th>
                            </tr>
                        </thead>
                        <tbody>@forelse($categorySummary as $row)<tr>
                            <td>{{ $row['name'] }}<span class="cf-sub">{{ $row['type'] }}</span></td>
                            <td class="credit">{{ number_format($row['credit'], 2) }}</td>
                            <td class="debit">{{ number_format($row['debit'], 2) }}</td>
                            <td>{{ number_format($row['credit'] - $row['debit'], 2) }}</td>
                        </tr>@empty<tr>
                                <td colspan="4">No data.</td>
                            </tr>@endforelse</tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="cf-card cf-section">
            <h3>Statement Entries</h3>
            <div class="cf-table-wrap">
                <table class="cf-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Particular</th>
                            <th>Invoice/Bill</th>
                            <th>Ref.</th>
                            <th>Account</th>
                            <th>Credit</th>
                            <th>Debit</th>
                            <th>Balance</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>@forelse($entries as $entry)<tr>
                        <td>{{ $entry->entry_date?->format('d M Y') }}</td>
                        <td>{{ $entry->particular }}<span
                                class="cf-sub">{{ $entry->related_party_name ?: $entry->expense_head }}</span></td>
                        <td>{{ $entry->invoice_bill_number ?: '-' }}</td>
                        <td>{{ $entry->bank_reference_number ?: '-' }}</td>
                        <td>{{ $entry->account?->account_name }}</td>
                        <td class="credit">
                            {{ $entry->credit_amount > 0 ? number_format((float) $entry->credit_amount, 2) : '-' }}</td>
                        <td class="debit">
                            {{ $entry->debit_amount > 0 ? number_format((float) $entry->debit_amount, 2) : '-' }}</td>
                        <td>{{ $entry->balance !== null ? number_format((float) $entry->balance, 2) : '-' }}</td>
                        <td><span
                                class="cf-badge status-{{ str_replace('_', '-', $entry->accounting_status) }}">{{ $entry->statusLabel() }}</span>
                        </td>
                    </tr>@empty<tr>
                            <td colspan="9">No entries found for this report.</td>
                        </tr>@endforelse</tbody>
                </table>
            </div>
        </div>
    </div>
@endsection