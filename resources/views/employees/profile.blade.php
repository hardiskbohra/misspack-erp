@extends('layouts.app')

@section('title', 'My Profile')
@section('page-title', 'My Profile')

@section('page-actions')
    <a class="master-btn master-btn-ghost" href="{{ route('my.dashboard') }}">Back to my workspace</a>
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/employees.css') }}">
@endpush

<div class="emp employee-profile master-list">
    @if (! $record['complete'])
        <div class="master-card master-card--flat emp-note">
            <strong>{{ count($record['missing']) }} {{ \Illuminate\Support\Str::plural('detail', count($record['missing'])) }} still to be filled in:</strong>
            {{ implode(', ', $record['missing']) }}.
            {{-- Two owners, said out loud: the employee keeps the left column
                 current, the office keeps the rest. --}}
            Fill in what is yours below; the office completes the rest.
        </div>
    @endif

    <div class="master-grid">
        @foreach ($record['groups'] as $group)
            <div class="master-card master-card--flat master-section">
                <h3 class="master-section-title">{{ $group['title'] }}</h3>
                <div class="master-facts">
                    @foreach ($group['fields'] as $field)
                        <div class="master-info">
                            <span>{{ $field['label'] }}</span>
                            <strong @class(['is-blank' => blank($field['value'])])>
                                {{ blank($field['value']) ? 'Not on file' : $field['value'] }}
                            </strong>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <div class="master-grid">
        <div class="master-card master-card--flat master-section">
            <h3 class="master-section-title">Keep your own details current</h3>
            <p class="master-sub" style="margin:0 0 14px;">
                Your address, your mobile number and who to call in an emergency are yours to change.
                Designation, pay and bank account are the office's record — ask them to update those.
            </p>

            {{-- The same form the account page renders, posting to this page's own
                 route: the fields and the rules are one file (`users.partials`),
                 and `employees-check` holds both controllers to the service's
                 field list. --}}
            @include('users.partials.own-details', [
                'action' => route('my.profile.update'),
                'submit' => 'Save my details',
                'me' => $me,
                'grid' => 'master-form-grid is-three',
                'prefix' => 'profile',
            ])
        </div>

        <div class="master-card master-card--flat master-section">
            <h3 class="master-section-title">Your password</h3>
            @include('users.partials.own-password', [
                'action' => route('my.password.update'),
                'submit' => 'Change password',
                'prefix' => 'profile',
            ])
        </div>
    </div>
</div>
@endsection
