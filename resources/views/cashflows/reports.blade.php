@extends('layouts.app')

@section('page-title', 'Cashflow Reports')

@section('content')
@push('styles')
        <link rel="stylesheet" href="{{ asset('assets/css/cashflows.css') }}">
@endpush
    <div class="cf">
        <div class="cf-card cf-header">
            <div>
                <h1>Cashflow Reports</h1>
                <p style="margin:5px 0 0;color:#687386;font-weight:500;">{{ $dateFrom->format('d M Y') }} to
                    {{ $dateTo->format('d M Y') }}</p>
            </div>
            <div class="cf-actions">
                <a href="{{ route('cashflows.index') }}" class="master-btn master-btn-light">Back</a>
                <a href="{{ route('cashflows.settings.index') }}" class="master-btn master-btn-soft">Settings</a>
                <a href="{{ route('cashflows.reports.pdf', request()->query()) }}" class="master-btn master-btn-primary">Download PDF</a></div>
        </div>
        <form method="GET" action="{{ route('cashflows.reports') }}" class="cf-card cf-filter">
            <div class="cf-filter-grid">
                <div><label class="master-label">Report Type</label><select class="master-select" name="report_type">
                        <option value="overall" @selected($reportType === 'overall')>Overall Cashflow</option>
                        <option value="client" @selected($reportType === 'client')>Client Statement</option>
                        <option value="vendor" @selected($reportType === 'vendor')>Vendor Statement</option>
                        <option value="cash_expense" @selected($reportType === 'cash_expense')>Cash Expenses</option>
                    </select></div>
                <div><label class="master-label">Period</label><select class="master-select" name="period">
                        <option value="day" @selected($period === 'day')>Day Wise</option>
                        <option value="week" @selected($period === 'week')>Week Wise</option>
                        <option value="month" @selected($period === 'month')>Month Wise</option>
                        <option value="quarter" @selected($period === 'quarter')>Quarter Wise</option>
                        <option value="year" @selected($period === 'year')>Year Wise</option>
                    </select></div>
                <div><label class="master-label">Base Date</label><input class="master-input" type="date" name="date"
                        value="{{ request('date', now()->toDateString()) }}"></div>
                <div><label class="master-label">Account</label><select class="master-select" name="account_id">
                        <option value="all">All Accounts</option>@foreach($accounts as $account)<option
                            value="{{ $account->id }}" @selected((string) $accountId === (string) $account->id)>
                        {{ $account->account_name }}</option>@endforeach
                    </select></div>
                <div><label class="master-label">From Date</label><input class="master-input" type="date" name="date_from"
                        value="{{ $dateFrom->toDateString() }}"></div>
                <div><label class="master-label">To Date</label><input class="master-input" type="date" name="date_to"
                        value="{{ $dateTo->toDateString() }}"></div>
                <div><label class="master-label">Client</label><select class="master-select" name="client_id">
                        <option value="all">All Clients</option>@foreach($clients as $client)<option
                            value="{{ $client->id }}" @selected((string) $clientId === (string) $client->id)>
                        {{ $client->company_name }}</option>@endforeach
                    </select></div>
                <div><label class="master-label">Vendor</label><select class="master-select" name="vendor_id">
                        <option value="all">All Vendors</option>@foreach($vendors as $vendor)<option
                            value="{{ $vendor->id }}" @selected((string) $vendorId === (string) $vendor->id)>
                        {{ $vendor->vendor_name }}</option>@endforeach
                    </select></div>
                <div style="display:flex;gap:10px;align-items:end;"><button class="master-btn master-btn-primary"
                        type="submit">Generate</button><a class="master-btn master-btn-light"
                        href="{{ route('cashflows.reports') }}">Reset</a></div>
            </div>
        </form>

        <div class="cf-summary">
            <div class="cf-stat blue">
                <p>Total Credit</p><strong>{{ inr($summary['credit']) }}</strong>
            </div>
            <div class="cf-stat purple">
                <p>Total Debit</p><strong>{{ inr($summary['debit']) }}</strong>
            </div>
            <div class="cf-stat teal">
                <p>Net Cashflow</p><strong>{{ inr($summary['net']) }}</strong>
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
                            <td class="credit">{{ inr($row['credit']) }}</td>
                            <td class="debit">{{ inr($row['debit']) }}</td>
                            <td>{{ inr($row['credit'] - $row['debit']) }}</td>
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
                            <td class="credit">{{ inr($row['credit']) }}</td>
                            <td class="debit">{{ inr($row['debit']) }}</td>
                            <td>{{ inr($row['credit'] - $row['debit']) }}</td>
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
                            {{ $entry->credit_amount > 0 ? inr($entry->credit_amount) : '-' }}</td>
                        <td class="debit">
                            {{ $entry->debit_amount > 0 ? inr($entry->debit_amount) : '-' }}</td>
                        <td>{{ $entry->balance !== null ? inr($entry->balance) : '-' }}</td>
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