{{--
    The two-factor card on My Account.

    Three states, one card: *off* offers to start, *pending* shows the key and
    asks the authenticator to prove it got it, *on* shows the recovery-code and
    turn-off forms — the last two behind the current password, so a session left
    open cannot quietly weaken its own account (`TwoFactorController`).

    Variables: $me (the signed-in user).
--}}
@php
    $twoFactor = app(\App\Services\TwoFactorAuthentication::class);
    $enabled = $twoFactor->hasEnabled($me);
    $pending = $twoFactor->isPending($me);
    $provisioningUri = $pending ? $twoFactor->provisioningUri($me) : null;
    $flashedCodes = session('twoFactorRecoveryCodes');
@endphp

<h3 class="master-section-title">Two-factor authentication</h3>

@if ($flashedCodes)
    <div class="master-info-box is-success" role="status">
        <p><strong>Your recovery codes — shown only this once.</strong>
            Each works one time when your phone is not with you. Save them somewhere safe:</p>
        <ul style="columns: 2; font-family: monospace; margin: .5rem 0 0;">
            @foreach ($flashedCodes as $code)
                <li>{{ $code }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if ($enabled)
    <p class="master-sub account-note">
        <i class="fas fa-shield-halved" aria-hidden="true"></i>
        <strong>On.</strong> Signing in asks for your password and the code on your phone.
    </p>

    <div class="master-grid is-even">
        <form method="POST" action="{{ route('account.two-factor.recovery') }}">
            @csrf
            <div class="master-field full">
                <label class="master-label" for="tf-regen-password">New recovery codes</label>
                <input class="master-input {{ $errors->twoFactor->has('current_password') ? 'is-invalid' : '' }}"
                    id="tf-regen-password" type="password" name="current_password"
                    placeholder="Current password" autocomplete="current-password">
            </div>
            <div class="master-actions">
                <button class="master-btn master-btn-soft" type="submit">Fresh set of codes</button>
            </div>
        </form>

        <form method="POST" action="{{ route('account.two-factor.destroy') }}">
            @csrf
            @method('DELETE')
            <div class="master-field full">
                <label class="master-label" for="tf-off-password">Turn it off</label>
                <input class="master-input {{ $errors->twoFactor->has('current_password') ? 'is-invalid' : '' }}"
                    id="tf-off-password" type="password" name="current_password"
                    placeholder="Current password" autocomplete="current-password">
                @error('current_password', 'twoFactor')<p class="master-error">{{ $message }}</p>@enderror
            </div>
            <div class="master-actions">
                <button class="master-btn master-btn-danger" type="submit">Turn two-factor off</button>
            </div>
        </form>
    </div>

@elseif ($pending)
    <p class="master-sub account-note">
        <strong>Finish setting up.</strong> Add this key to your authenticator app
        (Google Authenticator, Microsoft Authenticator, 1Password — any TOTP app), then type
        the six digits it shows for <strong>{{ $me->email }}</strong>.
    </p>

    <div class="master-info-box">
        <p style="margin: 0;">Setup key — type it exactly, spaces optional:</p>
        <p style="font-family: monospace; font-size: 1.1rem; letter-spacing: .08em; margin: .35rem 0;">
            {{ strtoupper(trim(chunk_split($me->two_factor_secret, 4, ' '))) }}
        </p>
        <p style="margin: 0;">
            On your phone?
            <a href="{{ $provisioningUri }}">Open this in the authenticator app</a>.
        </p>
    </div>

    <form method="POST" action="{{ route('account.two-factor.confirm') }}">
        @csrf
        <div class="master-form-grid">
            <div class="master-field">
                <label class="master-label" for="tf-confirm-code">The six digits the app shows</label>
                <input class="master-input {{ $errors->twoFactor->has('code') ? 'is-invalid' : '' }}"
                    id="tf-confirm-code" type="text" name="code" placeholder="123 456"
                    inputmode="numeric" autocomplete="one-time-code" maxlength="8">
                @error('code', 'twoFactor')<p class="master-error">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="master-actions">
            <button class="master-btn master-btn-soft" type="submit">Turn two-factor on</button>
        </div>
    </form>

    <form method="POST" action="{{ route('account.two-factor.destroy') }}" style="margin-top: .5rem;">
        @csrf
        @method('DELETE')
        <button class="master-btn master-btn-soft" type="submit">Cancel setup</button>
    </form>

@else
    <p class="master-sub account-note">
        <strong>Off.</strong> Your password is the only thing between a stranger and this
        account. Turn on two-factor and signing in also needs the code on your phone — a
        stolen password then gets nobody in.
    </p>

    <form method="POST" action="{{ route('account.two-factor.store') }}">
        @csrf
        <div class="master-actions">
            <button class="master-btn master-btn-soft" type="submit">
                <i class="fas fa-shield-halved" aria-hidden="true"></i> Set up two-factor
            </button>
        </div>
    </form>
@endif
