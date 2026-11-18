<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MissPack ERP – Two-factor check</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/login.css') }}">
    @include('layouts.partials.design-system-styles')
</head>
<body data-ui-shell="public">
    <!-- Left Panel -->
    <div class="left-panel">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
        <div class="blob blob-3"></div>
        <div class="blob blob-4"></div>
        <div class="left-content">
            <div class="brand-logo">
                <img src="{{ asset('images/logo.png') }}" alt="MissPack">
            </div>
            <h1 class="welcome-title">One more<br>step</h1>
            <p class="welcome-desc">
                Your password was right. This account also asks for the code on your phone,
                so a password alone is never enough to walk in.
            </p>
        </div>
    </div>

    <!-- Right Panel -->
    <div class="right-panel">
        <h2 class="form-title">Two-factor check</h2>
        <p class="form-subtitle">Open your authenticator app and type the six digits</p>

        @if($errors->any())
            <div class="error-msg">
                <i class="fas fa-exclamation-circle"></i>
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('two-factor.challenge.store') }}">
            @csrf

            <div class="form-group">
                <label class="form-label" for="challenge-code">Authenticator code</label>
                <div class="input-wrapper">
                    <input
                        type="text"
                        name="code"
                        id="challenge-code"
                        class="form-input {{ $errors->has('code') ? 'is-invalid' : '' }}"
                        placeholder="123 456"
                        inputmode="numeric"
                        pattern="[0-9 ]*"
                        autocomplete="one-time-code"
                        maxlength="8"
                        autofocus
                    >
                    <i class="fas fa-shield-halved input-icon"></i>
                </div>
            </div>

            <button type="submit" class="btn-signin">
                Verify &amp; Sign In &nbsp;<i class="fas fa-arrow-right"></i>
            </button>

            <div class="form-group" style="margin-top: 1.5rem;">
                <label class="form-label" for="challenge-recovery">
                    Phone not with you? Use a recovery code instead
                </label>
                <div class="input-wrapper">
                    <input
                        type="text"
                        name="recovery_code"
                        id="challenge-recovery"
                        class="form-input"
                        placeholder="abc123-def456"
                        autocomplete="off"
                        maxlength="20"
                    >
                    <i class="fas fa-key input-icon"></i>
                </div>
            </div>

            <p style="margin-top: 1rem;">
                <a href="{{ route('login') }}" class="forgot-link">Back to sign in</a>
            </p>
        </form>
    </div>

    <script src="{{ asset('assets/js/login.js') }}"></script>
</body>
</html>
