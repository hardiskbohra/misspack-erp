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

    <div class="master-card master-table-card master-card--flat">
        <div class="master-list-toolbar">
            <p class="master-list-hint">
                {{ $total['entries'] }} {{ \Illuminate\Support\Str::plural('entry', $total['entries']) }} in {{ $year }}
                · net <strong>{{ \App\Helpers\CommonHelper::indianCurrency($total['total']) }}</strong>
            </p>
        </div>

        <div class="master-table-wrap">
            <table class="master-table">
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Particular</th>
                        <th scope="col">Reference</th>
                        <th scope="col">Way</th>
                        <th scope="col">Mode</th>
                        <th scope="col" class="is-num">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entries as $entry)
                        <tr>
                            <td data-label="Date">{{ $entry->entry_date?->format('d M Y') }}</td>
                            <td data-label="Particular">
                                <strong>{{ $entry->particular ?: 'Salary' }}</strong>
                                @if ($entry->notes)<span class="master-sub">{{ $entry->notes }}</span>@endif
                            </td>
                            <td data-label="Reference">{{ $entry->bank_reference_number ?: '—' }}</td>
                            <td data-label="Way">
                                <span class="emp-pill {{ $entry->isMoneyOut() ? 'is-ok' : 'is-off' }}">
                                    {{ $entry->isMoneyOut() ? 'Paid to you' : 'Back to the office' }}
                                </span>
                            </td>
                            <td data-label="Mode">
                                <span class="emp-pill is-off">{{ \App\Models\CashflowEntry::paymentModeOptions()[$entry->payment_mode] ?? '—' }}</span>
                            </td>
                            <td class="is-num" data-label="Amount">
                                <strong>{{ $entry->signedAmountLabel() }}</strong>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="master-list-empty">
                                    <span class="master-list-empty-icon" aria-hidden="true">₹</span>
                                    <p class="master-list-empty-title">No salary paid in {{ $year }}</p>
                                    <p class="master-list-empty-text">
                                        When the office pays you through the cashflow ledger and files the entry
                                        against your name, it appears here — with the reference it was paid on.
                                    </p>
                                    <div class="master-list-empty-actions">
                                        @foreach (array_slice($years, 0, 3) as $option)
                                            @if ((int) $option !== (int) $year)
                                                <a class="master-btn master-btn-soft" href="{{ route('my.salary', ['year' => $option]) }}">{{ $option }}</a>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($entries->isNotEmpty())
                    <tfoot>
                        <tr class="master-list-total">
                            <td colspan="5"><strong>Net paid in {{ $year }}</strong></td>
                            <td class="is-num">
                                <strong>{{ \App\Helpers\CommonHelper::indianCurrency($total['total']) }}</strong>
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- My payslips. They are here, on the salary page, and not on one of their
         own: a payslip is a month of this salary, printed from these figures —
         two pages listing the same months is two places to disagree. --}}
    <div class="master-card master-table-card master-card--flat" id="payslips">
        <div class="master-list-toolbar">
            <p class="master-list-hint">
                {{ $payslips->count() }} {{ \Illuminate\Support\Str::plural('payslip', $payslips->count()) }} issued to me
            </p>
        </div>

        @include('employees.partials.payslip-table', ['payslips' => $payslips, 'context' => 'employee', 'user' => $me])
    </div>

    @if ($previewSlipDoc)
        <div class="master-card master-card--flat master-section" id="payslip-sheet">
            <h3 class="master-section-title">Payslip for {{ $previewSlip->periodLabel() }} · {{ $previewSlip->slipNumber() }}</h3>
            <p class="master-sub user-modal-foot">
                Printed from the office's record for that month. Use <strong>Print</strong> on the slip, or the
                <strong>Payslip</strong> button in the list, for a copy to keep.
            </p>
            @include('employees.partials.payslip', ['doc' => $previewSlipDoc, 'context' => 'app', 'payslip' => $previewSlip])
        </div>
    @endif
</div>
@endsection
