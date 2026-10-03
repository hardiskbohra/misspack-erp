@extends('layouts.app')

@section('title', 'My Account')
@section('page-title', 'My Account')

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/users.css') }}">
@endpush

<div class="user-account master-list">
    {{-- Who this account belongs to, said once at the top: the menu behind the
         avatar leads here, so the page opens by confirming whose it is. --}}
    <div class="master-card master-card--flat account-head">
        <span class="account-head-avatar" aria-hidden="true">
            @if ($me->avatar)
                <img src="{{ asset('storage/'.$me->avatar) }}" alt="">
            @else
                {{ $me->initials ?: strtoupper(substr($me->name ?? 'U', 0, 2)) }}
            @endif
        </span>
        <div class="account-head-text">
            <h2 class="account-head-name">{{ $me->name }}</h2>
            <p class="account-head-meta">
                {{ $employee ? ($me->designation ?: 'Employee') : 'Administrator' }}
                @if ($me->employee_code) · {{ $me->employee_code }} @endif
                · {{ $me->email }}
            </p>
            <p class="master-sub">
                @if ($employee)
                    Your name, designation, pay and bank account are the office's record — ask them to
                    change those. Everything below is yours.
                @else
                    You are signed in as the office. Your own name and sign-in address are on your
                    record, which you can open from here.
                @endif
            </p>
        </div>
        <a class="master-btn master-btn-soft" href="{{ $recordUrl }}">
            <i class="fas fa-id-card" aria-hidden="true"></i> Open my record
        </a>
    </div>

    <div class="master-grid is-even">
        <div>
            <div class="master-card master-card--flat master-section">
                <h3 class="master-section-title">Your details</h3>
                <p class="master-sub account-note">
                    Your number, your address, your birthday and who to call in an emergency — the
                    things only you know. The office sees them straight away.
                </p>

                @include('users.partials.own-details', [
                    'action' => route('account.profile.update'),
                    'submit' => 'Save my details',
                    'me' => $me,
                    'grid' => 'master-form-grid',
                    'sticky' => true,
                    'prefix' => 'account',
                ])
            </div>

            {{-- Appearance is a switch with a sentence, not a section: beside its
                 own sentence the card is as tall as the button it holds, and this
                 column is where it belongs — it is a thing about *you*, and it
                 keeps the two columns ending together instead of one of them
                 leaving three hundred pixels of white underneath. --}}
            <div class="master-card master-card--flat master-section">
                <div class="account-switch">
                    <div>
                        <h3 class="master-section-title">Appearance</h3>
                        <p class="master-sub account-note">
                            The same switch as the button in the top bar — here for phones, where
                            that button is hidden.
                        </p>
                    </div>

                    <form method="POST" action="{{ route('theme.toggle') }}">
                        @csrf
                        <button class="master-btn master-btn-soft" type="submit">
                            @if (session('theme', 'light') === 'light')
                                <i class="fas fa-moon" aria-hidden="true"></i> Switch to dark
                            @else
                                <i class="fas fa-sun" aria-hidden="true"></i> Switch to light
                            @endif
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div>
            <div class="master-card master-card--flat master-section" id="password">
                <h3 class="master-section-title">Password</h3>
                <p class="master-sub account-note">
                    At least eight characters. You stay signed in on this device.
                </p>

                @include('users.partials.own-password', [
                    'action' => route('account.password.update'),
                    'submit' => 'Change password',
                    'prefix' => 'account',
                ])
            </div>

            <div class="master-card master-card--flat master-section">
                <h3 class="master-section-title">Yours to reach</h3>
                <ul class="account-links">
                    <li>
                        <a href="{{ $recordUrl }}">
                            <i class="fas fa-id-card" aria-hidden="true"></i>
                            <span>
                                <strong>My record</strong>
                                <em>{{ $employee ? 'Your workspace: what you earn, your papers' : 'The full record the office keeps for you' }}</em>
                            </span>
                        </a>
                    </li>
                    @if ($employee)
                        <li>
                            <a href="{{ route('my.salary') }}">
                                <i class="fa-solid fa-indian-rupee-sign" aria-hidden="true"></i>
                                <span>
                                    <strong>Salary &amp; payslips</strong>
                                    <em>Every month the ledger filed, and its slip</em>
                                </span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('my.documents') }}">
                                <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
                                <span>
                                    <strong>My documents</strong>
                                    <em>Papers on file, and the ones still missing</em>
                                </span>
                            </a>
                        </li>
                    @else
                        <li>
                            <a href="{{ route('users.index') }}">
                                <i class="fas fa-users" aria-hidden="true"></i>
                                <span>
                                    <strong>Users</strong>
                                    <em>Everybody's record, including your own</em>
                                </span>
                            </a>
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ $assetVer('assets/js/users.js') }}"></script>
@endpush
