@extends('layouts.app')

@section('title', 'My Workspace')
@section('page-title', 'My Workspace')

@section('page-actions')
    <a class="master-btn master-btn-soft" href="{{ route('my.salary') }}">My salary</a>
    <a class="master-btn master-btn-ghost" href="{{ route('my.documents') }}">My documents</a>
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/employees.css') }}">
@endpush

<div class="emp employee-workspace master-list">
    <div class="master-stats desktop-only">
        <div class="master-stat master-stat--flat green">
            <span class="icon">₹</span>
            <div>
                <p class="master-stat-title">Paid in {{ $year }}</p>
                <p class="master-stat-value">{{ \App\Helpers\CommonHelper::indianCurrency($total['total']) }}</p>
                <p class="master-sub">{{ $total['entries'] }} {{ \Illuminate\Support\Str::plural('entry', $total['entries']) }}
                    over {{ $total['months_paid'] }} {{ \Illuminate\Support\Str::plural('month', $total['months_paid']) }}</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat blue">
            <span class="icon">🧾</span>
            <div>
                <p class="master-stat-title">Payslips</p>
                <p class="master-stat-value">{{ $payslips->count() }}</p>
                <p class="master-sub">{{ $last_payslip ? 'Latest: '.$last_payslip->periodLabel() : 'None issued yet' }}</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat {{ $checklist['complete'] ? 'teal' : 'orange' }}">
            <span class="icon">📄</span>
            <div>
                <p class="master-stat-title">Documents on file</p>
                <p class="master-stat-value">{{ $checklist['present'] }}/{{ $checklist['required'] }}</p>
                <p class="master-sub">required papers{{ $checklist['verified'] ? ' · '.$checklist['verified'].' checked' : '' }}</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat {{ $profile['complete'] ? 'purple' : 'red' }}">
            <span class="icon">✓</span>
            <div>
                <p class="master-stat-title">Profile</p>
                <p class="master-stat-value">{{ $profile['complete'] ? 'Complete' : count($profile['missing']).' to add' }}</p>
                <p class="master-sub">{{ $me->designation ?: 'No designation set' }}</p>
            </div>
        </div>
    </div>

    {{-- A padded card, not a card with an inline padding: the module's insets
         come from the sheet, which is what keeps this page in step with the
         office's record page for the same person (task 49's rule). --}}
    <div class="master-card master-card--flat master-section">
        <p class="emp-hello">Hello, {{ explode(' ', trim($me->name))[0] }}.</p>
        <p class="master-sub" style="margin:4px 0 0;">
            {{ $me->designation ?: 'Team' }}@if ($me->department) · {{ $me->department }}@endif
            @if ($me->employee_code) · {{ $me->employee_code }}@endif
            @if ($me->date_of_joining) · joined {{ $me->date_of_joining->format('d M Y') }}@endif
        </p>
    </div>

    <div class="master-grid">
        <div class="master-card master-card--flat master-section">
            <h3 class="master-section-title">This year, month by month</h3>
            @include('employees.partials.pay-months', ['months' => $months, 'currency' => 'INR'])
            <div class="emp-card-foot">
                <a class="master-btn master-btn-soft master-btn-sm" href="{{ route('my.salary', ['year' => $year]) }}">
                    All salary entries</a>
            </div>
        </div>

        <div class="master-card master-card--flat master-section">
            <h3 class="master-section-title">What needs your attention</h3>
            <ul class="emp-todo">
                @forelse ($profile['missing'] as $missing)
                    <li>
                        <span class="emp-todo-dot"></span>
                        <span><strong>{{ $missing }}</strong> is missing from your profile.</span>
                        <a class="master-btn master-btn-ghost master-btn-sm" href="{{ route('my.profile') }}">Add it</a>
                    </li>
                @empty
                    <li><span class="emp-todo-dot is-ok"></span><span>Your profile is complete.</span></li>
                @endforelse
                @foreach ($checklist['rows'] as $row)
                    @if ($row['required'] && ! $row['present'])
                        <li>
                            <span class="emp-todo-dot"></span>
                            <span>Upload your <strong>{{ $row['label'] }}</strong>.</span>
                            <a class="master-btn master-btn-ghost master-btn-sm" href="{{ route('my.documents') }}">Upload</a>
                        </li>
                    @endif
                @endforeach
                @if (! $last_payslip)
                    <li><span class="emp-todo-dot is-info"></span><span>No payslip has been issued to you yet.</span></li>
                @endif
            </ul>
        </div>
    </div>
</div>
@endsection
