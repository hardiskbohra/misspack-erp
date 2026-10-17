<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#13263a">
    <title>Client portal sign in | MissPack</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/master-form.css') }}">
    @include('layouts.partials.design-system-styles', ['forceStatic' => true])
</head>
<body class="client-portal-login" data-ui-shell="public">
<main class="cp-login-shell">
    <section class="cp-login-brand" aria-labelledby="portalWelcome">
        <a class="cp-login-logo" href="{{ route('client-portal.login') }}"><span><i class="fa-solid fa-cubes-stacked"></i></span><strong>MissPack</strong></a>
        <div>
            <p class="cp-login-kicker">Client workspace</p>
            <h1 id="portalWelcome">Everything you need, in one secure place.</h1>
            <p>Follow projects and shipments, review quotations and invoices, exchange documents, and speak directly with your MissPack team.</p>
        </div>
        <ul class="cp-login-benefits">
            <li><i class="fa-solid fa-circle-check"></i> Live project and delivery visibility</li>
            <li><i class="fa-solid fa-circle-check"></i> Private billing and document access</li>
            <li><i class="fa-solid fa-circle-check"></i> One shared support inbox</li>
        </ul>
        <p class="cp-login-security"><i class="fa-solid fa-lock"></i> Secure access managed by MissPack</p>
    </section>

    <section class="cp-login-card" aria-labelledby="signInTitle">
        <div class="cp-login-card-head"><p class="cp-eyebrow">Welcome back</p><h2 id="signInTitle">Sign in to your workspace</h2><p>Use the username or email provided by your MissPack coordinator.</p></div>
        @if(session('error'))<div class="cp-flash cp-flash-error" role="alert"><i class="fa-solid fa-circle-exclamation"></i>{{ session('error') }}</div>@endif
        @if(session('success'))<div class="cp-flash cp-flash-success" role="status"><i class="fa-solid fa-circle-check"></i>{{ session('success') }}</div>@endif
        @if($errors->any())<div class="cp-flash cp-flash-error" role="alert"><i class="fa-solid fa-circle-exclamation"></i>{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('client-portal.login.submit') }}" class="cp-login-form">
            @csrf
            <div class="master-field"><label class="master-label" for="portal-login">Username or email</label><input class="master-input" id="portal-login" type="text" name="login" value="{{ old('login') }}" autocomplete="username" required autofocus></div>
            <div class="master-field"><label class="master-label" for="portal-password">Password</label><input class="master-input" id="portal-password" type="password" name="password" autocomplete="current-password" required></div>
            <button class="master-btn master-btn-primary cp-login-submit" type="submit">Sign in securely <i class="fa-solid fa-arrow-right"></i></button>
        </form>
        <div class="cp-login-help"><i class="fa-regular fa-circle-question"></i><span><strong>Having trouble signing in?</strong> Contact your MissPack coordinator to check your portal access.</span></div>
    </section>
</main>
</body>
</html>
