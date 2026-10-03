@extends('layouts.app')

@section('title', 'My Payslips')
@section('page-title', 'My Payslips')

@section('page-actions')
    <a class="master-btn master-btn-ghost" href="{{ route('my.salary') }}">My salary</a>
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/employees.css') }}">
@endpush

<div class="emp employee-payslips master-list">
    <div class="master-card master-table-card master-card--flat">
        <div class="master-list-toolbar">
            <p class="master-list-hint">
                {{ $payslips->count() }} {{ \Illuminate\Support\Str::plural('payslip', $payslips->count()) }} issued to you
            </p>
        </div>

        <div class="master-table-wrap">
            <table class="master-table">
                <thead>
                    <tr>
                        <th scope="col">Month</th>
                        <th scope="col" class="is-num">Gross</th>
                        <th scope="col" class="is-num">Deductions</th>
                        <th scope="col" class="is-num">Net paid</th>
                        <th scope="col">Paid on</th>
                        <th scope="col">Slip</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payslips as $payslip)
                        <tr>
                            <td data-label="Month"><strong>{{ $payslip->periodLabel() }}</strong></td>
                            <td class="is-num" data-label="Gross">{{ \App\Helpers\CommonHelper::amount($payslip->gross_amount, $payslip->currency) }}</td>
                            <td class="is-num" data-label="Deductions">{{ \App\Helpers\CommonHelper::amount($payslip->deductions, $payslip->currency) }}</td>
                            <td class="is-num" data-label="Net paid">
                                <strong>{{ \App\Helpers\CommonHelper::amount($payslip->net_amount, $payslip->currency) }}</strong>
                            </td>
                            <td data-label="Paid on">{{ $payslip->paidOnLabel() }}</td>
                            <td data-label="Slip">
                                @if ($payslip->hasFile())
                                    <a class="master-btn master-btn-soft master-btn-sm" href="{{ route('my.payslips.file', $payslip) }}">
                                        Download
                                    </a>
                                @else
                                    {{-- The figures are the record; a slip with no PDF is
                                         still a month that was paid, and saying so is
                                         better than an empty cell. --}}
                                    <span class="master-sub">No file attached</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="master-list-empty">
                                    <span class="master-list-empty-icon" aria-hidden="true">🧾</span>
                                    <p class="master-list-empty-title">No payslip has been issued yet</p>
                                    <p class="master-list-empty-text">
                                        Payslips appear here as the office issues them — and they are kept,
                                        so you can always come back for an older month.
                                    </p>
                                    <div class="master-list-empty-actions">
                                        <a class="master-btn master-btn-soft" href="{{ route('my.salary') }}">See what I was paid</a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
