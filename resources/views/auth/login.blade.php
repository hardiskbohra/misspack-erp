<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MissPack ERP – Sign In</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #0a1628;
            color: #e2e8f0;
            min-height: 100vh;
            display: flex;
        }

        /* LEFT PANEL */
        .left-panel {
            flex: 1;
            background: linear-gradient(135deg, #0a1628 0%, #0d1e38 50%, #0a1628 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 60px;
            position: relative;
            overflow: hidden;
        }
        .left-panel::before {
            content: '';
            position: absolute;
            width: 500px; height: 500px;
            border-radius: 50%;
            border: 80px solid rgba(79,142,247,0.07);
            top: -100px; left: -100px;
        }
        .left-panel::after {
            content: '';
            position: absolute;
            width: 350px; height: 350px;
            border-radius: 50%;
            border: 60px solid rgba(79,142,247,0.05);
            bottom: -80px; right: -80px;
        }
        .blob {
            position: absolute;
            border-radius: 50%;
            background: rgba(79,142,247,0.12);
        }
        .blob-1 { width:120px;height:200px;border-radius:80px;bottom:180px;right:80px;transform:rotate(-20deg); }
        .blob-2 { width:100px;height:170px;border-radius:80px;bottom:180px;right:200px;transform:rotate(-10deg); }
        .blob-3 { width:80px;height:140px;border-radius:80px;bottom:180px;right:310px;transform:rotate(-15deg); }
        .blob-4 { width:140px;height:140px;border-radius:50%;bottom:40px;right:60px;background:rgba(79,142,247,0.1); }

        .left-content { position: relative; z-index: 1; }
        .brand-logo img { height: 200px; }
        .welcome-title {
            font-size: 52px;
            font-weight: 800;
            line-height: 1.1;
            color: #fff;
            margin-bottom: 20px;
            margin-top:-20px;
        }
        .welcome-desc {
            font-size: 16px;
            color: rgba(255,255,255,0.5);
            line-height: 1.7;
            max-width: 380px;
        }
        .learn-more {
            display: inline-block;
            margin-top: 32px;
            background: var(--accent, #4f8ef7);
            color: #fff;
            padding: 12px 28px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s;
        }
        .learn-more:hover { background: #3b7de8; transform: translateY(-1px); }

        /* RIGHT PANEL */
        .right-panel {
            width: 580px;
            background: #0f1f38;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 60px 50px;
        }
        .form-logo { margin-bottom: 12px; }
        .form-logo img { height: 32px; }
        .form-title {
            font-size: 32px;
            font-weight: 800;
            color: #fff;
            margin-bottom: 6px;
        }
        .form-subtitle {
            font-size: 14px;
            color: rgba(255,255,255,0.4);
            margin-bottom: 36px;
        }

        .form-group { margin-bottom: 20px; }
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: rgba(255,255,255,0.7);
            margin-bottom: 8px;
        }
        .form-input {
            width: 100%;
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 14px 16px;
            color: #fff;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
            outline: none;
        }
        .form-input::placeholder { color: rgba(255,255,255,0.25); }
        .form-input:focus {
            border-color: #4f8ef7;
            background: rgba(79,142,247,0.08);
            box-shadow: 0 0 0 3px rgba(79,142,247,0.15);
        }
        .form-input.is-invalid { border-color: #e53e6a; }

        .input-wrapper { position: relative; }
        .input-icon {
            position: absolute; right: 14px; top: 50%;
            transform: translateY(-50%);
            color: rgba(255,255,255,0.3); cursor: pointer;
        }

        .form-row-between {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
        }
        .checkbox-wrap {
            display: flex; align-items: center; gap: 10px;
            cursor: pointer;
        }
        .checkbox-wrap input[type="checkbox"] {
            width: 18px; height: 18px;
            accent-color: #4f8ef7;
            cursor: pointer;
        }
        .checkbox-label { font-size: 13px; color: rgba(255,255,255,0.5); }
        .forgot-link {
            font-size: 13px;
            color: #4f8ef7;
            text-decoration: none;
            font-weight: 600;
        }
        .forgot-link:hover { text-decoration: underline; }

        .btn-signin {
            width: 100%;
            background: linear-gradient(135deg, #4f8ef7, #6c63ff);
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 15px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            letter-spacing: 0.3px;
        }
        .btn-signin:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 30px rgba(79,142,247,0.4);
        }
        .btn-signin:active { transform: translateY(0); }

        .error-msg {
            background: rgba(229,62,106,0.1);
            border: 1px solid rgba(229,62,106,0.3);
            color: #e53e6a;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        @media (max-width: 900px) {
            .left-panel { display: none; }
            .right-panel { width: 100%; }
        }
    </style>
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

    <script>
        document.getElementById('togglePassword').addEventListener('click', function() {
            const field = document.getElementById('passwordField');
            const isPass = field.type === 'password';
            field.type = isPass ? 'text' : 'password';
            this.className = isPass ? 'fas fa-eye-slash input-icon' : 'fas fa-eye input-icon';
        });
    </script>
</body>
</html>
