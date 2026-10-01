<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Portal Login | MissPack</title>
@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/client-portal-login.css') }}">
@endpush
</head>
<body>
<div class="login-wrap">
    <div class="login-brand">
        <div class="brand-logo"><strong>MissPack</strong><span>PACKED PERFECT</span></div>
        <div><h1>Client Portal</h1><p>Track your projects, shipments, quotations, invoices, payments, products and documents from one secure portal.</p></div>
        <div style="opacity:.8;font-size:13px;">Secure access shared by MissPack internal team.</div>
    </div>
    <div class="login-card">
        <p class="eyebrow">Welcome back</p>
        <h2>Sign in</h2>
        <p>Use the username and one-time password shared by MissPack.</p>
        @if(session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="alert alert-error">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('client-portal.login.submit') }}">
            @csrf
            <div class="field"><label>Username or Email</label><input type="text" name="login" value="{{ old('login') }}" required autofocus></div>
            <div class="field"><label>Password</label><input type="password" name="password" required></div>
            <button class="btn" type="submit">Login to Portal</button>
        </form>
        <div class="help">Having trouble? Contact your MissPack coordinator.</div>
    </div>
</div>
</body>
</html>
