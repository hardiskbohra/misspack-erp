@extends('client_portal.layouts.app')

@section('title', 'Payments')
@section('page-title', 'Payments')

@section('content')
<div class="cp-page-head">
    <div>
        <p class="cp-eyebrow">Finance · published receipts</p>
        <h1>Payments</h1>
        <p>Confirmed receipts shared by MissPack. Only explicitly published client payments appear here.</p>
    </div>
    <a class="master-btn master-btn-soft" href="{{ route('client-portal.statement.index') }}"><i class="fa-solid fa-file-invoice"></i> View statement</a>
</div>

@if($currencyTotals->isNotEmpty())
    <div class="cp-grid-4 cp-payment-totals">
        @foreach($currencyTotals as $total)
            <div class="cp-card cp-stat">
                <span>{{ $total->currency }} · {{ $total->payment_count }} {{ \Illuminate\Support\Str::plural('receipt', (int) $total->payment_count) }}</span>
                <strong>{{ \App\Helpers\CommonHelper::amount($total->total_amount, $total->currency) }}</strong>
            </div>
        @endforeach
    </div>
@endif

<div class="cp-card cp-filter-card">
    <form method="GET" class="cp-payment-filters">
        <div class="master-field">
            <label class="master-label" for="payment-search">Search</label>
            <input class="master-input" id="payment-search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Project or reference">
        </div>
        <div class="master-field">
            <label class="master-label" for="payment-currency">Currency</label>
            <input class="master-input" id="payment-currency" name="currency" value="{{ $filters['currency'] ?? '' }}" maxlength="10" placeholder="All currencies">
        </div>
        <div class="master-field">
            <label class="master-label" for="payment-from">From</label>
            <input class="master-input" id="payment-from" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
        </div>
        <div class="master-field">
            <label class="master-label" for="payment-to">To</label>
            <input class="master-input" id="payment-to" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
        </div>
        <div class="cp-filter-actions">
            <button class="master-btn master-btn-primary" type="submit">Apply filters</button>
            <a class="master-btn master-btn-light" href="{{ route('client-portal.payments.index') }}">Reset</a>
        </div>
    </form>
</div>

<div class="cp-card cp-payment-table-card">
    <div class="cp-section-heading">
        <div>
            <p class="cp-eyebrow">Receipt history</p>
            <h2>Shared with your team</h2>
        </div>
        <span class="cp-muted">{{ $payments->total() }} receipts</span>
    </div>
    <div class="cp-table-wrap ui-mobile-cards">
        <table class="cp-table" data-table-settings data-table-key="portal-payments">
            <thead><tr><th>Date</th><th class="ui-mobile-secondary">Project</th><th>Reference</th><th class="ui-mobile-secondary">Method</th><th class="cp-number">Amount</th></tr></thead>
            <tbody>
                @forelse($payments as $payment)
                    <tr>
                        <td data-label="Date">{{ optional($payment->payment_date)->format('d M Y') ?: '—' }}</td>
                        <td data-label="Project" class="ui-mobile-secondary">
                            @if($payment->project)
                                <a class="cp-record-link" href="{{ route('client-portal.projects.show', $payment->project) }}">{{ $payment->project->name }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td data-label="Reference">{{ $payment->reference_number ?: 'Client receipt' }}</td>
                        <td data-label="Method" class="ui-mobile-secondary">{{ $payment->payment_mode ? strtoupper($payment->payment_mode) : '—' }}</td>
                        <td data-label="Amount" class="cp-number"><strong>{{ \App\Helpers\CommonHelper::amount($payment->amount, $payment->currency) }}</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="5"><div class="cp-empty">No confirmed receipts match these filters yet.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="cp-pagination">{{ $payments->links() }}</div>
</div>
@endsection
