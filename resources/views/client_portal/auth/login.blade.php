<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Portal Login | MissPack</title>
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;background:linear-gradient(135deg,#eef3ff,#f8fbff);font-family:"Inter","Segoe UI",Roboto,Arial,sans-serif;color:#17233b;display:grid;place-items:center;padding:18px}.login-wrap{width:min(980px,100%);display:grid;grid-template-columns:1fr 420px;background:#fff;border:1px solid #dfe7f3;border-radius:28px;box-shadow:0 24px 70px rgba(25,42,70,.14);overflow:hidden}.login-brand{background:linear-gradient(135deg,#4f83f1,#6366f1);color:#fff;padding:42px;display:flex;flex-direction:column;justify-content:space-between;min-height:560px}.login-brand h1{font-size:38px;margin:0}.login-brand p{font-size:16px;line-height:1.6;opacity:.92}.brand-logo strong{display:block;font-size:25px}.brand-logo span{display:block;letter-spacing:.18em;font-size:11px;opacity:.75}.login-card{padding:42px;display:flex;flex-direction:column;justify-content:center}.eyebrow{font-size:11px;text-transform:uppercase;letter-spacing:.13em;font-weight:600;color:#4f83f1;margin:0 0 8px}.login-card h2{margin:0 0 8px;font-size:28px}.login-card p{margin:0 0 22px;color:#687386;font-weight:500}.field{display:flex;flex-direction:column;gap:7px;margin-bottom:14px}.field label{font-size:12px;font-weight:600;color:#536079}.field input{height:46px;border:1px solid #d8e2ef;border-radius:14px;padding:10px 13px;outline:none}.field input:focus{border-color:#4f83f1;box-shadow:0 0 0 3px rgba(79,131,241,.12)}.btn{width:100%;border:0;border-radius:14px;background:#ef4770;color:#fff;font-weight:600;padding:13px 16px;cursor:pointer;box-shadow:0 10px 24px rgba(239,71,112,.24)}.alert{border-radius:14px;padding:12px 14px;font-weight:500;margin-bottom:14px}.alert-error{background:#fff0f4;color:#be123c;border:1px solid #fecdd3}.alert-success{background:#e8fff7;color:#047857;border:1px solid #a7f3d0}.help{margin-top:16px;text-align:center;color:#687386;font-size:12px;font-weight:500}@media(max-width:820px){.login-wrap{grid-template-columns:1fr}.login-brand{min-height:auto;padding:28px}.login-card{padding:28px}.login-brand h1{font-size:30px}}
    </style>
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
