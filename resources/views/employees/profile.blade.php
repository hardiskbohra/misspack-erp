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
                <div class="master-detail-list">
                    @foreach ($group['fields'] as $field)
                        <div class="master-info">
                            <span>{{ $field['label'] }}</span>
                            <strong class="{{ blank($field['value']) ? 'is-blank' : '' }}">
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

            <form method="POST" action="{{ route('my.profile.update') }}">
                @csrf
                @method('PUT')

                <div class="master-form-grid">
                    <div class="master-field">
                        <label class="master-label" for="profileMobile">Mobile</label>
                        <input class="master-input" id="profileMobile" name="mobile" value="{{ old('mobile', $me->mobile) }}"
                            placeholder="+91 …">
                        @error('mobile')<p class="master-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="profileDob">Date of birth</label>
                        <input class="master-input" id="profileDob" type="date" name="date_of_birth"
                            value="{{ old('date_of_birth', $me->date_of_birth?->format('Y-m-d')) }}">
                        @error('date_of_birth')<p class="master-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="profileEmergencyName">Emergency contact</label>
                        <input class="master-input" id="profileEmergencyName" name="emergency_contact_name"
                            value="{{ old('emergency_contact_name', $me->emergency_contact_name) }}" placeholder="Name">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="profileEmergencyMobile">Emergency mobile</label>
                        <input class="master-input" id="profileEmergencyMobile" name="emergency_contact_mobile"
                            value="{{ old('emergency_contact_mobile', $me->emergency_contact_mobile) }}" placeholder="+91 …">
                    </div>
                    <div class="master-field full">
                        <label class="master-label" for="profileAddress">Address</label>
                        <textarea class="master-input" id="profileAddress" name="address" rows="3"
                            placeholder="Where you live">{{ old('address', $me->address) }}</textarea>
                    </div>
                </div>

                <div class="master-actions">
                    <button class="master-btn master-btn-primary" type="submit">Save my details</button>
                </div>
            </form>
        </div>

        <div class="master-card master-card--flat master-section">
            <h3 class="master-section-title">Your password</h3>
            <form method="POST" action="{{ route('my.password.update') }}">
                @csrf
                <div class="master-form-grid">
                    <div class="master-field full">
                        <label class="master-label" for="currentPassword">Current password</label>
                        <input class="master-input" id="currentPassword" type="password" name="current_password" autocomplete="current-password">
                        @error('current_password')<p class="master-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="newPassword">New password</label>
                        <input class="master-input" id="newPassword" type="password" name="password" autocomplete="new-password">
                        @error('password')<p class="master-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="confirmPassword">Repeat it</label>
                        <input class="master-input" id="confirmPassword" type="password" name="password_confirmation" autocomplete="new-password">
                    </div>
                </div>
                <div class="master-actions">
                    <button class="master-btn master-btn-soft" type="submit">Change password</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
