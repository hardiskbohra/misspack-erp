@extends('layouts.app')

@section('page-title', 'Statement — '.$statement['party']['name'])

@section('page-actions')
    <a class="master-btn master-btn-ghost desktop-only"
        href="{{ route('cashflows.statements', array_filter(['party_type' => $statement['party_type'], 'period' => $periodKey, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'currency' => $statement['currency']])) }}">All statements</a>
    <a class="master-btn master-btn-soft desktop-only"
        href="{{ route('cashflows.statements.pdf', array_filter(['partyType' => $statement['party_type'], 'party' => $statement['party_id'], 'date_from' => $dateFrom, 'date_to' => $dateTo, 'currency' => $statement['currency'], 'ageing' => $ageing ? '1' : '0'])) }}">Download PDF</a>
    <button class="master-btn master-btn-soft" type="button" id="printStatement">Print</button>
    <button class="master-btn master-btn-primary" type="button" id="openShareStatement">Share link</button>
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/cashflows.css') }}">
    <link rel="stylesheet" href="{{ $assetVer('assets/css/statement.css') }}">
@endpush
@php
    $periodOptions = $dateRangeLabels + ['all' => 'All time', 'custom' => 'Custom dates'];
    $party = $statement['party'];
    $whatsappUrl = $readyShare ? $readyShare->whatsappUrl($party['phone']) : null;
    $mailUrl = $readyShare ? $readyShare->mailUrl($party['email']) : null;
@endphp

<div class="cf cashflow-statement master-list">

    <div class="master-card master-card--flat stmt-toolbar no-print">
        <form method="GET" action="{{ route('cashflows.statements.show', ['partyType' => $statement['party_type'], 'party' => $statement['party_id']]) }}">
            <div class="master-filter-row">
                <div class="master-search">
                    <span aria-hidden="true">🧾</span>
                    <input class="master-input" type="text" value="{{ $party['name'] }}" readonly
                        aria-label="Party" tabindex="-1">
                </div>
                <select class="master-select" name="period" aria-label="Period">
                    @foreach ($periodOptions as $key => $label)
                        <option value="{{ $key }}" @selected($periodKey === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <input class="master-input desktop-only" type="date" name="date_from" value="{{ $dateFrom }}"
                    aria-label="From" title="From">
                <input class="master-input desktop-only" type="date" name="date_to" value="{{ $dateTo }}"
                    aria-label="To" title="To">
                @if (count($currencyOptions) > 1)
                    <select class="master-select" name="currency" aria-label="Statement currency">
                        @foreach ($currencyOptions as $code)
                            <option value="{{ $code }}" @selected($statement['currency'] === $code)>
                                {{ \App\Helpers\CommonHelper::currencyLabel($code) }}</option>
                        @endforeach
                    </select>
                @endif
                <label class="stmt-toggle">
                    <input type="checkbox" name="ageing" value="1" @checked($ageing)>
                    <span>Ageing</span>
                </label>

                <div class="master-list-filter-group">
                    <button class="master-btn master-btn-primary" type="submit">Show</button>
                </div>
            </div>
            <p class="stmt-toolbar-note">
                {{ $statement['source_note'] }}
                @if ($statement['period']['from'] || $statement['period']['to'])
                    · Balance as on
                    {{ ($statement['period']['to'] ?? now())->format('d M Y') }}
                @endif
            </p>
        </form>
    </div>

    @if ($readyShare)
        {{-- The link exists; these are the three ways it leaves the building.
             Everything here is print-hidden, so the printer only ever prints
             the statement. --}}
        <div class="master-card master-card--flat stmt-ready no-print">
            <div class="stmt-ready-head">
                <p class="stmt-ready-title">Link ready for {{ $readyShare->party_name }}</p>
                <p class="master-sub">
                    {{ \App\Helpers\CommonHelper::currencyLabel($readyShare->party_currency) }} statement, {{ $readyShare->periodLabel() }} ·
                    sent by {{ $readyShare->channelLabel() }} · expires {{ $readyShare->expiresLabel() }}
                </p>
            </div>
            <div class="stmt-ready-row">
                <input class="master-input" id="statementShareUrl" type="text" readonly
                    value="{{ $readyShare->url() }}" aria-label="Statement link">
                <button class="master-btn master-btn-primary" type="button" data-copy-target="statementShareUrl"
                    data-copy-label="Copy link">Copy link</button>
                @if ($whatsappUrl)
                    <a class="master-btn master-btn-soft" href="{{ $whatsappUrl }}" target="_blank" rel="noopener">Open WhatsApp</a>
                @elseif ($party['phone'] === null)
                    <span class="stmt-ready-hint">Add a mobile to this {{ strtolower($party['kind']) }} to send on WhatsApp.</span>
                @endif
                @if ($mailUrl)
                    <a class="master-btn master-btn-soft" href="{{ $mailUrl }}">Open email</a>
                @elseif ($party['email'] === null)
                    <span class="stmt-ready-hint">Add an email to this {{ strtolower($party['kind']) }} to send it by mail.</span>
                @endif
            </div>
        </div>
    @endif

    <div class="master-card master-card--flat stmt-sheet">
        @include('cashflows.partials.statement', ['statement' => $statement, 'context' => 'app'])
    </div>

    <div class="master-card master-card--flat no-print">
        <div class="master-list-bar">
            <div class="stmt-log-head">
                <p class="stmt-log-title">Links for this statement</p>
                <p class="master-sub">Revoking closes the link; removing only forgets it.</p>
            </div>
        </div>
        <div class="master-table-wrap">
            <table class="master-table">
                <thead>
                    <tr>
                        <th scope="col">Sent</th>
                        <th scope="col">Period</th>
                        <th scope="col">Via</th>
                        <th scope="col">Expires</th>
                        <th scope="col">Opens</th>
                        <th scope="col">State</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($shares as $share)
                        <tr>
                            <td data-label="Sent">{{ $share->created_at?->format('d M Y, h:i A') }}</td>
                            <td data-label="Period">
                                {{ $share->title() }}
                                <span class="master-sub">{{ \App\Helpers\CommonHelper::currencyLabel($share->party_currency) }}</span>
                            </td>
                            <td data-label="Via">{{ $share->channelLabel() }}</td>
                            <td data-label="Expires">{{ $share->expiresLabel() }}</td>
                            <td data-label="Opens">
                                {{ number_format($share->views) }}
                                @if ($share->last_viewed_at)
                                    <span class="master-sub">last {{ $share->last_viewed_at->format('d M Y, h:i A') }}</span>
                                @endif
                            </td>
                            <td data-label="State">
                                <span
                                    class="cf-doc-state {{ $share->state() === 'live' ? 'is-linked' : 'is-unlinked' }}">{{ $share->stateLabel() }}</span>
                            </td>
                            <td data-label="Action">
                                <div class="stmt-log-actions">
                                    <a class="master-btn master-btn-soft master-btn-sm" href="{{ $share->url() }}"
                                        target="_blank" rel="noopener">Open</a>
                                    @if ($share->state() === 'live')
                                        <form method="POST" action="{{ route('cashflows.statements.shares.revoke', $share) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button class="master-btn master-btn-light master-btn-sm" type="submit">Revoke</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('cashflows.statements.shares.destroy', $share) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="master-btn master-btn-light master-btn-sm" type="submit">Remove</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7"><div class="stmt-empty">No link for this party yet.</div></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="master-modal" id="shareStatementModal" aria-hidden="true">
    <div class="master-modal-card" role="dialog" aria-modal="true" aria-labelledby="shareStatementTitle">
        <form method="POST" action="{{ route('cashflows.statements.shares.store') }}">
            @csrf
            <input type="hidden" name="party_type" value="{{ $statement['party_type'] }}">
            <input type="hidden" name="party_id" value="{{ $statement['party_id'] }}">
            <input type="hidden" name="period" value="{{ $periodKey }}">
            <input type="hidden" name="date_from" value="{{ $dateFrom }}">
            <input type="hidden" name="date_to" value="{{ $dateTo }}">
            <input type="hidden" name="currency" value="{{ $statement['currency'] }}">
            <input type="hidden" name="ageing" value="{{ $ageing ? 1 : 0 }}">

            <div class="master-modal-header">
                <div class="master-modal-heading">
                    <span class="master-modal-icon">🔗</span>
                    <div>
                        <h3 class="master-modal-title" id="shareStatementTitle">Send this statement</h3>
                        <p class="master-modal-subtitle">
                            {{ $party['name'] }} · {{ $statement['period']['label'] }} ·
                            {{ \App\Helpers\CommonHelper::currencyLabel($statement['currency']) }}
                        </p>
                    </div>
                </div>
                <button type="button" class="master-modal-close" data-close-modal="shareStatementModal"
                    aria-label="Close">&times;</button>
            </div>

            <div class="master-modal-body">
                <div class="master-modal-grid">
                    <div class="master-field">
                        <label class="master-label" for="shareChannel">Send via</label>
                        <select class="master-select" id="shareChannel" name="channel">
                            @foreach ($channelChoices as $key => $label)
                                <option value="{{ $key }}" @selected($key === 'whatsapp' && $party['phone'])>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="shareExpiry">Link expires</label>
                        <select class="master-select" id="shareExpiry" name="expires_in">
                            @foreach ($expiryChoices as $days => $label)
                                <option value="{{ $days }}" @selected($days === 30)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field full">
                        <label class="master-label" for="shareNote">Note printed on the statement</label>
                        <textarea class="master-input" id="shareNote" name="note" rows="3" maxlength="300"
                            placeholder="e.g. Kindly confirm the balance by 10 Nov."></textarea>
                    </div>
                    <div class="master-field full">
                        <label class="master-label" for="shareLabel">Label in the log</label>
                        <input class="master-input" id="shareLabel" name="label" maxlength="120"
                            placeholder="{{ $statement['period']['label'] }}">
                    </div>
                </div>

                <p class="stmt-modal-note">
                    The statement is rebuilt from the ledger every time the link is opened, so a bill booked
                    tomorrow shows up in it. Whoever holds the link can read it without a login — revoke it here
                    when it has served its purpose.
                </p>
            </div>

            <div class="master-modal-footer">
                <button type="button" class="master-btn master-btn-light" data-close-modal="shareStatementModal">Cancel</button>
                <button type="submit" class="master-btn master-btn-primary">Create link</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
    <script src="{{ $assetVer('assets/js/cashflows.js') }}"></script>
@endpush
@endsection
