@extends('client_portal.layouts.app')

@section('title', 'Statement')
@section('page-title', 'Statement of account')

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/statement.css') }}">
    <link rel="stylesheet" href="{{ $assetVer('assets/css/document-print.css') }}">
@endpush
@php
    $party = $statement['party'];
    $totals = $statement['totals'];
    $money = fn ($value) => \App\Helpers\CommonHelper::amount((float) $value, $statement['currency']);
    $periodOptions = $dateRangeLabels + ['all' => 'All time', 'custom' => 'Custom dates'];
@endphp

<div class="cp-page-head">
    <div>
        <p class="cp-eyebrow">Accounts</p>
        <h1>Statement of account</h1>
        <p>Everything billed to you and everything we have received, with the balance as on the last day of the period.
        </p>
    </div>
</div>

<div class="cp-card cp-filter-card no-print">
    <form method="GET" action="{{ route('client-portal.statement.index') }}">
        <div class="core-filter-toolbar">
            <x-filter-trigger drawer="portalStatementFiltersDrawer" label="Statement period"
                :count="(($periodKey !== 'this_month' || filled($dateFrom) || filled($dateTo)) ? 1 : 0) + ($statement['currency'] !== 'INR' ? 1 : 0)" />
            <button class="master-btn master-btn-soft" type="button" onclick="window.print()">Print</button>
        </div>
        <x-drawer id="portalStatementFiltersDrawer" title="Statement period" eyebrow="Statement filters"
            subtitle="Choose the statement period, date range, and currency." size="medium">
            <section class="core-drawer-section">
                <h3 class="core-drawer-section-title">Reporting period</h3>
                <div class="core-drawer-fields">
                    <div class="master-field">
                        <label class="master-label" for="statementPeriod">Period</label>
                        <select class="master-select" id="statementPeriod" name="period">
                            @foreach ($periodOptions as $key => $label)
                                <option value="{{ $key }}" @selected($periodKey === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="statementFrom">From</label>
                        <input class="master-input" id="statementFrom" type="date" name="date_from" value="{{ $dateFrom }}">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="statementTo">To</label>
                        <input class="master-input" id="statementTo" type="date" name="date_to" value="{{ $dateTo }}">
                    </div>
                    @if (count($currencyOptions) > 1)
                        <div class="master-field">
                            <label class="master-label" for="statementCurrency">Currency</label>
                            <select class="master-select" id="statementCurrency" name="currency">
                                @foreach ($currencyOptions as $code)
                                    <option value="{{ $code }}" @selected($statement['currency'] === $code)>
                                        {{ \App\Helpers\CommonHelper::currencyLabel($code) }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>
            </section>
            <x-slot:footer>
                <button class="master-btn master-btn-primary" type="submit">Show statement</button>
            </x-slot:footer>
        </x-drawer>
    </form>
</div>

<div class="cp-card">
    <div class="stmt-portal-shell">
        @include('cashflows.partials.statement', ['statement' => $statement, 'context' => 'portal'])
    </div>
</div>
@endsection
