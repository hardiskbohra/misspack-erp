@extends('layouts.app')

@section('title', 'My Salary')
@section('page-title', 'My Salary')

@section('page-actions')
    <a class="master-btn master-btn-ghost" href="{{ route('my.payslips') }}">My payslips</a>
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
                <p class="master-stat-title">Credited in {{ $year }}</p>
                <p class="master-stat-value">{{ \App\Helpers\CommonHelper::indianCurrency($total['total']) }}</p>
                <p class="master-sub">{{ $total['months_paid'] }} {{ \Illuminate\Support\Str::plural('month', $total['months_paid']) }} paid</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat blue">
            <span class="icon">⇄</span>
            <div>
                <p class="master-stat-title">Credits</p>
                <p class="master-stat-value">{{ $total['entries'] }}</p>
                <p class="master-sub">entries filed against you in {{ $year }}</p>
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
                Every credit the office filed against your name, month by month.
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
            </p>
        </div>

        <div class="master-table-wrap">
            <table class="master-table">
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Particular</th>
                        <th scope="col">Reference</th>
                        <th scope="col">Mode</th>
                        <th scope="col" class="is-num">Credited</th>
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
                            <td data-label="Mode">
                                <span class="emp-pill is-off">{{ \App\Models\CashflowEntry::paymentModeOptions()[$entry->payment_mode] ?? '—' }}</span>
                            </td>
                            <td class="is-num" data-label="Credited">
                                <strong>{{ \App\Helpers\CommonHelper::amount($entry->credit_amount, $entry->currency ?: 'INR') }}</strong>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="master-list-empty">
                                    <span class="master-list-empty-icon" aria-hidden="true">₹</span>
                                    <p class="master-list-empty-title">No salary credited in {{ $year }}</p>
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
                            <td colspan="4"><strong>Total credited in {{ $year }}</strong></td>
                            <td class="is-num">
                                <strong>{{ \App\Helpers\CommonHelper::indianCurrency($total['total']) }}</strong>
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
