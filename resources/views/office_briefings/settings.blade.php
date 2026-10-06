@extends('layouts.app')

@section('title', 'Office briefing settings')
@section('page-title', 'Briefings')

@section('page-actions')
    <button type="submit" form="officeBriefingSettings" class="master-btn master-btn-primary">Save settings</button>
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/users.css') }}">
@endpush

<div class="ob-settings user-account master-list">
    <div class="master-card master-card--flat account-head">
        <span class="account-head-avatar" aria-hidden="true"><i class="fas fa-bell"></i></span>
        <div class="account-head-text">
            <h2 class="account-head-name">Briefings for the office</h2>
            <p class="master-sub">
                One set of rules for the organisation: what raises, whether it pops up,
                and who is emailed. Individual snooze and mark-read stay on each desk.
            </p>
        </div>
    </div>

    <form id="officeBriefingSettings" method="POST" action="{{ route('office-alerts.settings.update') }}">
        @csrf
        @method('PUT')

        <div class="master-grid is-even">
            <div class="master-card master-card--flat master-section">
                <h3 class="master-section-title">Organisation</h3>
                <p class="master-sub account-note">Turn the whole desk off without deleting history.</p>

                @foreach ([
                    ['enabled', 'Briefings on', 'Bell, list, scheduled sweep and live events.'],
                    ['popups', 'Interrupt once', 'Critical items open a modal the first time. Off = list only.'],
                    ['emails', 'Email watchers', 'Mail the desk when a new critical item is raised.'],
                    ['chase_emails', 'Hourly chase', 'Re-mail unacked critical items after the wait below.'],
                ] as [$key, $label, $hint])
                    <div class="master-toggle-group">
                        <div>
                            <label class="master-label" for="ob_{{ $key }}">{{ $label }}</label>
                            <p class="master-sub">{{ $hint }}</p>
                        </div>
                        <label class="master-switch">
                            <input type="checkbox" id="ob_{{ $key }}" name="{{ $key }}" value="1" @checked($settings[$key])>
                            <span class="master-slider"></span>
                        </label>
                    </div>
                @endforeach

                <div class="master-form-grid" style="margin-top:16px">
                    <div class="master-field">
                        <label class="master-label" for="chase_hours">Chase after (hours)</label>
                        <input class="master-input" id="chase_hours" type="number" min="1" max="72" name="chase_hours" value="{{ $settings['chase_hours'] }}" required>
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="stale_days">Silent file after (days)</label>
                        <input class="master-input" id="stale_days" type="number" min="1" max="14" name="stale_days" value="{{ $settings['stale_days'] }}" required>
                    </div>
                    <div class="master-field master-field-full">
                        <label class="master-label" for="extra_emails">Also email</label>
                        <textarea class="master-textarea" id="extra_emails" name="extra_emails" rows="4" placeholder="ops@misspack.com&#10;accounts@misspack.com">{{ $settings['extra_emails'] }}</textarea>
                        <p class="master-sub">One address per line, or comma-separated. These are extra to the watchers below.</p>
                    </div>
                </div>
            </div>

            <div class="master-card master-card--flat master-section">
                <h3 class="master-section-title">What to raise</h3>
                <p class="master-sub account-note">Each source can be off without touching the others.</p>

                @foreach ($sources as $key => $meta)
                    <div class="master-toggle-group">
                        <div>
                            <label class="master-label" for="ob_src_{{ $key }}">{{ $meta[0] }}</label>
                            <p class="master-sub">{{ $meta[1] }}</p>
                        </div>
                        <label class="master-switch">
                            <input type="checkbox" id="ob_src_{{ $key }}" name="sources[{{ $key }}]" value="1" @checked($settings['sources'][$key] ?? false)>
                            <span class="master-slider"></span>
                        </label>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="master-card master-card--flat master-section">
            <h3 class="master-section-title">Who is watching</h3>
            <p class="master-sub account-note">
                Office accounts only. Turn someone off to keep them out of the bell and the mail.
                Desk is for briefings only — it does not change their user record.
            </p>

            <div class="master-table-wrap">
                <table class="master-table">
                    <thead>
                        <tr>
                            <th>Watch</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Desk</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($watchers as $watcher)
                            <tr>
                                <td>
                                    <label class="master-switch">
                                        <input type="checkbox" name="watchers[{{ $watcher['id'] }}][on]" value="1" @checked($watcher['watching'])>
                                        <span class="master-slider"></span>
                                    </label>
                                </td>
                                <td>{{ $watcher['name'] }}</td>
                                <td>{{ $watcher['email'] }}</td>
                                <td>
                                    <select class="master-select" name="watchers[{{ $watcher['id'] }}][desk]">
                                        @foreach ($desks as $key => $label)
                                            <option value="{{ $key }}" @selected($watcher['desk'] === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">No office accounts yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </form>
</div>
@endsection
