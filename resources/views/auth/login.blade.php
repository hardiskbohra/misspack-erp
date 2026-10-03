<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MissPack ERP – Sign In</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/login.css') }}">
</head>
<body>
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
            <h1 class="welcome-title">Welcome to<br>MissPack</h1>
            <p class="welcome-desc">
                MissPack ERP helps you manage users, leads, customers, stocks and more — organized and beautifully designed.
            </p>
            <a href="#" class="learn-more">Learn More</a>
        </div>
    </div>

    <!-- Right Panel -->
    <div class="right-panel">
        <h2 class="form-title">Sign In</h2>
        <p class="form-subtitle">Your Admin Dashboard</p>

        @if($errors->any())
            <div class="error-msg">
                <i class="fas fa-exclamation-circle"></i>
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.post') }}">
            @csrf

            <div class="form-group">
                <label class="form-label">Email Address</label>
                <div class="input-wrapper">
                    <input
                        type="email"
                        name="email"
                        class="form-input {{ $errors->has('email') ? 'is-invalid' : '' }}"
                        placeholder="admin@misspack.com"
                        value="{{ old('email') }}"
                        required
                        autofocus
                    >
                    <i class="fas fa-envelope input-icon"></i>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <div class="input-wrapper">
                    <input
                        type="password"
                        name="password"
                        id="passwordField"
                        class="form-input"
                        placeholder="••••••••"
                        required
                    >
                    <i class="fas fa-eye input-icon" id="togglePassword" style="cursor:pointer;"></i>
                </div>
            </div>

            <div class="form-row-between">
                <label class="checkbox-wrap">
                    <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                    <span class="checkbox-label">Remember this Device</span>
                </label>
                <a href="#" class="forgot-link">Forgot Password?</a>
            </div>

            <button type="submit" class="btn-signin">
                Sign In &nbsp;<i class="fas fa-arrow-right"></i>
            </button>
        </form>
    </div>

    <script src="{{ asset('assets/js/login.js') }}"></script>
</body>
</html>
