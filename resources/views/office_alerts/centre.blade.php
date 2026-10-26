@extends('layouts.app')

@section('title', 'Notification centre')
@section('page-title', 'Notification centre')

@php
    $teamLabels = ['all' => 'Every desk', 'office' => 'Office-wide', 'sales' => 'Sales', 'operations' => 'Operations', 'accounts' => 'Accounts'];
    $severityLabels = ['all' => 'Any severity', 'critical' => 'Needs action', 'attention' => 'Follow up', 'info' => 'FYI'];
    $stateLabels = ['open' => 'Open', 'snoozed' => 'Snoozed', 'resolved' => 'Resolved'];
    $tone = fn (string $severity) => $severity === 'critical' ? 'danger' : ($severity === 'attention' ? 'warning' : 'info');
    /* Every chip link keeps the other two filters: a filter row that clears
       its neighbours is a filter row people stop trusting. */
    $with = fn (string $key, string $value) => route('office-alerts.centre',
        array_filter(array_merge($filters, [$key => $value]), fn ($v) => $v !== null && $v !== 'all' && $v !== 'open')
    );
@endphp

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/office-briefing.css') }}">
@endpush

<div class="obc master-list">

    <div class="master-card master-card--flat obc-head">
        <span class="obc-head-mark" aria-hidden="true"><i class="fas fa-bell"></i></span>
        <div>
            <h1 class="obc-head-title">Notification centre</h1>
            <p class="master-sub">
                The whole desk, not just the bell's glance: open briefings, the ones snoozed until later,
                and the resolved trail with who cleared it. Watching the
                <strong>{{ \App\Models\OfficeAlert::teamLabel($desk === 'office' ? null : $desk) }}</strong>
                {{ $desk === 'office' ? '(every team)' : '' }} desk.
                Tune the rules in <a href="{{ route('settings.briefings') }}">briefing settings</a>.
            </p>
        </div>
    </div>

    @if (! $enabled)
        <div class="master-alert error"><i class="fa-solid fa-circle-exclamation"></i>
            <div><p>Briefings are switched off for the organisation. Nothing raises, nothing mails — re-enable under <a href="{{ route('settings.briefings') }}">organisation settings</a> if that is not intended.</p></div>
        </div>
    @else

    <div class="master-stats obc-stats">
        <div class="master-stat master-stat--flat {{ $stats['critical'] ? 'red' : 'blue' }}"><span class="icon"><i class="fa-solid fa-inbox"></i></span><div><p class="master-stat-title">Open</p><p class="master-stat-value">{{ $stats['open'] }}</p></div></div>
        <div class="master-stat master-stat--flat red"><span class="icon"><i class="fa-solid fa-triangle-exclamation"></i></span><div><p class="master-stat-title">Needs action</p><p class="master-stat-value">{{ $stats['critical'] }}</p></div></div>
        <div class="master-stat master-stat--flat orange"><span class="icon"><i class="fa-regular fa-clock"></i></span><div><p class="master-stat-title">Snoozed</p><p class="master-stat-value">{{ $stats['snoozed'] }}</p></div></div>
        <div class="master-stat master-stat--flat green"><span class="icon"><i class="fa-solid fa-circle-check"></i></span><div><p class="master-stat-title">Resolved</p><p class="master-stat-value">{{ $stats['resolved'] }}</p></div></div>
    </div>

    <section class="master-card master-card--flat obc-filters" aria-label="Filter the centre">
        <div class="obc-filter-row">
            <span class="obc-filter-label">Tray</span>
            <div class="master-list-chips">
                @foreach ($stateLabels as $key => $label)
                    <a class="master-list-chip {{ $filters['state'] === $key ? 'is-active' : '' }}" href="{{ $with('state', $key) }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>
        <div class="obc-filter-row">
            <span class="obc-filter-label">Desk</span>
            <div class="master-list-chips">
                @foreach ($teamLabels as $key => $label)
                    <a class="master-list-chip {{ $filters['team'] === $key ? 'is-active' : '' }}" href="{{ $with('team', $key) }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>
        <div class="obc-filter-row">
            <span class="obc-filter-label">Severity</span>
            <div class="master-list-chips">
                @foreach ($severityLabels as $key => $label)
                    <a class="master-list-chip {{ $filters['severity'] === $key ? 'is-active' : '' }}" href="{{ $with('severity', $key) }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>
    </section>

    <section class="master-card master-card--flat">
        <div class="master-section-head">
            <div>
                <h2 class="master-section-title">{{ $stateLabels[$filters['state']] ?? 'All' }} briefings</h2>
                <p class="master-sub">{{ count($items) }} {{ \Illuminate\Support\Str::plural('item', count($items)) }} after filters · newest first, needs-action first.</p>
            </div>
        </div>

        @if (empty($items))
            <div class="master-empty-state">
                <i class="fas {{ $filters['state'] === 'resolved' ? 'fa-circle-check' : ($filters['state'] === 'snoozed' ? 'fa-clock' : 'fa-inbox') }}"></i>
                <p>
                    @if ($filters['state'] === 'resolved')
                        Nothing resolved yet on this view.<br>
                        Acknowledged and seen items keep their trail here — who acted, when.
                    @elseif ($filters['state'] === 'snoozed')
                        Nothing sleeping.<br>
                        Snoozed briefings sit here instead of the bell until they wake.
                    @else
                        Nothing waiting.<br>
                        New KYC, shipment exceptions and pending books land here first.
                    @endif
                </p>
            </div>
        @else
            <div class="obc-list"><!-- obc-items-start -->
                @foreach ($items as $item)
                    <article class="ob-item obc-item is-{{ $item['severity'] }} {{ $item['state'] === 'snoozed' ? 'is-snoozed' : '' }} {{ $item['state'] === 'resolved' ? 'is-resolved' : '' }}">
                        <div class="ob-item-head">
                            <span class="master-status-chip {{ $tone($item['severity']) }}">{{ $item['severity_label'] }}</span>
                            <span class="ob-team">{{ $item['team_label'] }}</span>
                            <span class="ob-when">{{ $item['when'] }}</span>
                            @if ($item['state'] === 'snoozed' && $item['snoozed_until'])
                                <span class="ob-team">Snoozed until {{ $item['snoozed_until'] }}</span>
                            @endif
                            @if ($item['shared_ack'])
                                <span class="ob-team" title="One clear covers every desk">shared</span>
                            @endif
                        </div>
                        <h3>{{ $item['title'] }}</h3>
                        <p>{{ $item['body'] }}</p>

                        @if ($item['state'] === 'resolved')
                            <p class="obc-resolved-line">
                                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                                Resolved {{ $item['resolved_at'] }} · {{ $item['resolved_by'] }}.
                            </p>
                        @else
                            <div class="ob-item-actions obc-actions">
                                @if ($item['action_url'])
                                    <a class="master-btn master-btn-soft master-btn-sm" href="{{ $item['action_url'] }}">{{ $item['action_label'] }}</a>
                                @endif
                                <form method="POST" action="{{ route('office-alerts.' . ($item['requires_ack'] ? 'ack' : 'seen'), $item['id']) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="master-btn master-btn-primary master-btn-sm">{{ $item['requires_ack'] ? 'Mark read' : 'Got it' }}</button>
                                </form>
                                <form method="POST" action="{{ route('office-alerts.snooze', $item['id']) }}" class="obc-snooze">
                                    @csrf
                                    @method('PATCH')
                                    <span class="obc-snooze-label">Snooze</span>
                                    <button type="submit" name="until" value="1h" class="master-btn master-btn-ghost master-btn-sm">1h</button>
                                    <button type="submit" name="until" value="4h" class="master-btn master-btn-ghost master-btn-sm">4h</button>
                                    <button type="submit" name="until" value="tomorrow" class="master-btn master-btn-ghost master-btn-sm">Tomorrow 9:00</button>
                                </form>
                            </div>
                        @endif
                    </article>
                @endforeach
            <!-- obc-items-end --></div>
        @endif
    </section>
    @endif
</div>
@endsection
