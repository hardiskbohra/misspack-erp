@extends('layouts.app')

@section('title', 'Office briefing settings')
@section('page-title', 'Office briefings')

@section('content')
<div class="ob-settings master-list">
    <div class="master-card master-card--flat account-head">
        <div class="account-head-text">
            <h2 class="account-head-name">Briefings for the office</h2>
            <p class="master-sub">
                One set of rules for the organisation: what raises, whether it pops up,
                and who is emailed. Individual snooze and mark-read stay on each desk.
            </p>
        </div>
    </div>

    @if (session('success'))
        <div class="master-alert master-alert-success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="master-alert master-alert-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('office-alerts.settings.update') }}" class="master-grid is-even">
        @csrf
        @method('PUT')

        <div>
            <div class="master-card master-card--flat master-section">
                <h3 class="master-section-title">Organisation</h3>
                <p class="master-sub account-note">Turn the whole desk off without deleting history.</p>

                @foreach ([
                    ['enabled', 'Briefings on', 'Bell, list, scheduled sweep and live events.'],
                    ['popups', 'Interrupt once', 'Critical items open a modal the first time. Off = list only.'],
                    ['emails', 'Email watchers', 'Mail the desk when a new critical item is raised.'],
                    ['chase_emails', 'Hourly chase', 'Re-mail unacked critical items after the wait below.'],
                ] as [$key, $label, $hint])
                    <div class="ob-setting-row">
                        <div>
                            <p class="ob-setting-label">{{ $label }}</p>
                            <p class="master-sub">{{ $hint }}</p>
                        </div>
                        <label class="master-switch">
                            <input type="checkbox" name="{{ $key }}" value="1" @checked($settings[$key])>
                            <span class="master-slider"></span>
                        </label>
                    </div>
                @endforeach

                <div class="master-form-grid" style="margin-top:16px">
                    <div>
                        <label class="master-label" for="chase_hours">Chase after (hours)</label>
                        <input class="master-input" id="chase_hours" type="number" min="1" max="72" name="chase_hours" value="{{ $settings['chase_hours'] }}" required>
                    </div>
                    <div>
                        <label class="master-label" for="stale_days">Silent file after (days)</label>
                        <input class="master-input" id="stale_days" type="number" min="1" max="14" name="stale_days" value="{{ $settings['stale_days'] }}" required>
                    </div>
                    <div class="master-field-full">
                        <label class="master-label" for="extra_emails">Also email</label>
                        <input class="master-input" id="extra_emails" type="text" name="extra_emails" value="{{ $settings['extra_emails'] }}" placeholder="ops@misspack.com, accounts@…">
                        <p class="master-sub">Extra addresses, comma-separated. Desk watchers still get mail from their user record.</p>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <div class="master-card master-card--flat master-section">
                <h3 class="master-section-title">What to raise</h3>
                <p class="master-sub account-note">Each source can be off without touching the others.</p>

                @foreach ($sources as $key => $meta)
                    <div class="ob-setting-row">
                        <div>
                            <p class="ob-setting-label">{{ $meta[0] }}</p>
                            <p class="master-sub">{{ $meta[1] }}</p>
                        </div>
                        <label class="master-switch">
                            <input type="checkbox" name="sources[{{ $key }}]" value="1" @checked($settings['sources'][$key] ?? false)>
                            <span class="master-slider"></span>
                        </label>
                    </div>
                @endforeach
            </div>

            <div class="master-card master-card--flat master-section" style="margin-top:16px">
                <h3 class="master-section-title">Who is watching</h3>
                <p class="master-sub account-note">Administrators, by department. An empty department sees every desk.</p>
                <ul class="ob-watcher-list">
                    @forelse ($watchers as $watcher)
                        <li>
                            <strong>{{ $watcher['name'] }}</strong>
                            <span>{{ $watcher['email'] }}</span>
                            <em>{{ $watcher['desk'] }}</em>
                        </li>
                    @empty
                        <li>No office accounts yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="master-field-full" style="grid-column:1 / -1">
            <button class="master-btn master-btn-primary" type="submit">Save briefing settings</button>
        </div>
    </form>
</div>
@endsection
