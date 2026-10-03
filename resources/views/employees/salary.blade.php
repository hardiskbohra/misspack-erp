@extends('layouts.app')

@section('title', 'My Salary')
@section('page-title', 'My Salary')

@section('page-actions')
    <a class="master-btn master-btn-soft" href="#payslips">My payslips</a>
    <a class="master-btn master-btn-ghost" href="{{ route('my.dashboard') }}">My workspace</a>
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/employees.css') }}">
@endpush

<div class="emp employee-salary master-list">
    <div class="master-stats desktop-only">
        <div class="master-stat master-stat--flat green">
            <span class="icon">₹</span>
            <div>
                <p class="master-stat-title">Paid in {{ $year }}</p>
                <p class="master-stat-value">{{ \App\Helpers\CommonHelper::indianCurrency($total['total']) }}</p>
                <p class="master-sub">
                    {{ $total['payments'] }} {{ \Illuminate\Support\Str::plural('payment', $total['payments']) }}
                    over {{ $total['months_paid'] }} {{ \Illuminate\Support\Str::plural('month', $total['months_paid']) }}
                    @if ($total['recovered'] > 0)
                        · {{ \App\Helpers\CommonHelper::indianCurrency($total['recovered']) }} back
                    @endif
                </p>
            </div>
        </div>
        <div class="master-stat master-stat--flat blue">
            <span class="icon">⇄</span>
            <div>
                <p class="master-stat-title">Ledger entries</p>
                <p class="master-stat-value">{{ $total['entries'] }}</p>
                <p class="master-sub">
                    filed against you in {{ $year }}
                    @if ($total['pending'] > 0)
                        · {{ $total['pending'] }} still pending
                    @endif
                </p>
            </div>
        </div>
    </div>

    <div class="master-card master-card--flat">
        <div class="master-list-bar">
            <div class="master-list-chips">
                @foreach ($years as $option)
                    <a class="master-list-chip {{ (int) $option === (int) $year ? 'is-active' : '' }}"
                        href="{{ route('my.salary', ['year' => $option]) }}">{{ $option }}</a>
                @endforeach
            </div>
            <p class="master-sub" style="margin:0;">
                Every entry the office filed against your name, month by month — a payment out, or money back.
            </p>
        </div>

        <div class="emp-months-wrap">
            @include('employees.partials.pay-months', ['months' => $months, 'currency' => 'INR'])
        </div>
    </div>

    {{-- One table: the months the office paid, and the payslip of each month on
         the same row. It used to be two — the entries here, the slips in a card
         below — listing the same months, and the reader matched them by eye.
         The card keeps the id the old /my/payslips link lands on. --}}
    <div class="master-card master-table-card master-card--flat" id="payslips">
        <div class="master-list-toolbar">
            <p class="master-list-hint">
                {{ $total['entries'] }} {{ \Illuminate\Support\Str::plural('entry', $total['entries']) }} in {{ $year }}
                · net <strong>{{ \App\Helpers\CommonHelper::indianCurrency($total['total']) }}</strong>
                · {{ $payslips->count() }} {{ \Illuminate\Support\Str::plural('payslip', $payslips->count()) }} issued
            </p>
        </div>

        @include('employees.partials.pay-table', [
            'rows' => $payRows,
            'context' => 'employee',
            'user' => $me,
            'year' => $year,
            'total' => $total,
        ])
    </div>

</div>
@endsection
