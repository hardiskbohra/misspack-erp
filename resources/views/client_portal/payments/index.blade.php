@extends('client_portal.layouts.app')

@section('title', 'Payments')
@section('page-title', 'Payments')

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/client-portal-payments.css') }}">
@endpush
<div class="master-header" style="padding:5px;margin-bottom:15px;">
    <div>
        <h1>Payments</h1>
        <p style="font-size:16px;font-weight:500;">Public payment entries linked with your projects.</p>
    </div>
</div>
<div class="cp-grid-4" style="margin-bottom:15px;">
    <div class="cp-card cp-stat">
        <span>Paid</span>
        <strong>₹{{ number_format($totals['inward'], 2) }}</strong>
    </div>
    <div class="cp-card cp-stat">
        <span>Invoiced</span>
        <strong>₹{{ number_format($totals['invoiced'], 2) }}</strong>
    </div>
    <div class="cp-card cp-stat">
        <span>Pending Invoiced Amount</span>
        <strong>₹{{ number_format($totals['inward']-$totals['invoiced'], 2) }}</strong>
    </div>
</div>
<div class="cp-card">
    <div class="cp-table-wrap">
        <table class="cp-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Particular</th>
                    <th>Project</th>
                    <th>Mode</th>
                    <th>Amount</th>
                    <th>Accounting Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payments as $payment)
                    <tr>
                        <td>{{ optional($payment->entry_date)->format('d M y') ?: '-' }}</td>
                        <td>
                            <span>{{ $payment->particular ?: '-' }}</span><br>
                            <strong style="font-size:11px;color:grey;">{{ $payment->bank_reference_number ?? '-' }}</strong>
                        </td>
                        <td>
                            <span>{{ $payment->project ? $payment->project->name : '-' }}</span><br>
                            <strong style="font-size:11px;color:grey;">Invoice: {{ $payment->invoice_bill_number ?? '-' }}</strong>
                        </td>
                        <td>
                            <span class="pd-chip pd-mode-{{ $payment->payment_mode }}" style="font-size:11px;">{{ $payment->payment_mode ? strtoupper($payment->payment_mode) : '-' }}</span></td>
                        <td>{{ $payment->currency }} {{ number_format((float)$payment->credit_amount, 2) }}</td>
                        <td>
                            <span class="pd-chip pd-status-{{ $payment->accounting_status }}">{{ $payment->statusLabel() }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <div class="cp-empty">No public payment entries.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-pagination :items="$payments" />
</div>
@endsection
