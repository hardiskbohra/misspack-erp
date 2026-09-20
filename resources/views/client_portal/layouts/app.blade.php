<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Client Portal') | MissPack</title>
    <style>
        :root{--cp-primary:#4f83f1;--cp-primary2:#6366f1;--cp-red:#ef4770;--cp-green:#10b981;--cp-orange:#f59e0b;--cp-dark:#17233b;--cp-muted:#687386;--cp-border:#dfe7f3;--cp-bg:#eef3ff;--cp-soft:#edf5ff;--cp-sidebar:#162238;--cp-shadow:0 14px 35px rgba(25,42,70,.08)}*{box-sizing:border-box}body{margin:0;background:var(--cp-bg);color:var(--cp-dark);font-family:"Inter","Segoe UI",Roboto,Arial,sans-serif;font-size:14px}.cp-shell{display:flex;min-height:100vh}.cp-sidebar{width:280px;background:var(--cp-sidebar);color:#fff;position:fixed;inset:0 auto 0 0;display:flex;flex-direction:column;z-index:50}.cp-logo{padding:26px 22px;border-bottom:1px solid rgba(255,255,255,.08)}.cp-logo strong{display:block;font-size:22px;font-weight:600}.cp-logo span{display:block;font-size:11px;letter-spacing:.18em;opacity:.72}.cp-nav{padding:18px 12px;overflow:auto;flex:1}.cp-nav-section{padding:13px 10px 8px;color:rgba(255,255,255,.45);font-size:11px;text-transform:uppercase;letter-spacing:.14em;font-weight:600}.cp-nav a{display:flex;align-items:center;gap:12px;padding:13px 14px;border-radius:14px;color:rgba(255,255,255,.82);text-decoration:none;font-weight:600;margin-bottom:5px}.cp-nav a:hover,.cp-nav a.active{background:#4f83f1;color:#fff}.cp-footer{padding:14px;border-top:1px solid rgba(255,255,255,.08)}.cp-user{display:flex;align-items:center;gap:10px;background:rgba(255,255,255,.06);border-radius:16px;padding:12px}.cp-avatar{width:42px;height:42px;border-radius:14px;background:#4f83f1;display:grid;place-items:center;font-weight:600}.cp-user strong,.cp-user span{display:block}.cp-user span{font-size:12px;opacity:.66}.cp-main{margin-left:280px;min-height:100vh;width:calc(100% - 280px)}.cp-topbar{height:72px;background:#fff;border-bottom:1px solid var(--cp-border);display:flex;align-items:center;justify-content:space-between;padding:0 26px;position:sticky;top:0;z-index:30;box-shadow:0 6px 24px rgba(23,35,59,.05)}.cp-top-left{display:flex;align-items:center;gap:14px}.cp-toggle{border:0;background:#f3f6fb;color:var(--cp-dark);width:42px;height:42px;border-radius:14px;cursor:pointer;font-size:20px}.cp-title{font-size:18px;font-weight:600}.cp-top-actions{display:flex;align-items:center;gap:10px}.cp-pill{display:inline-flex;align-items:center;gap:8px;background:#f6f8fc;border:1px solid #edf0f7;border-radius:15px;padding:10px 13px;color:var(--cp-dark);text-decoration:none;font-weight:600}.cp-pill.danger{color:#ef4770;background:#fff4f7}.cp-content{padding:28px}.cp-card{background:#fff;border:1px solid var(--cp-border);border-radius:22px;box-shadow:var(--cp-shadow)}.cp-btn{border:0;border-radius:14px;padding:10px 15px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:8px;cursor:pointer;transition:.2s;white-space:nowrap}.cp-btn:hover{transform:translateY(-1px);text-decoration:none}.cp-btn-primary{background:var(--cp-red);color:#fff;box-shadow:0 10px 24px rgba(239,71,112,.24)}.cp-btn-soft{background:#eef3ff;color:var(--cp-primary)}.cp-btn-light{background:#f3f6fb;color:var(--cp-dark)}.cp-btn-green{background:#e8fff7;color:#0e9f6e}.cp-btn-sm{padding:8px 11px;border-radius:12px;font-size:12px}.cp-alert{border-radius:16px;padding:13px 15px;margin-bottom:16px;font-weight:600}.cp-alert-success{background:#e8fff7;color:#047857;border:1px solid #a7f3d0}.cp-alert-error{background:#fff0f4;color:#be123c;border:1px solid #fecdd3}.cp-alert-warning{background:#fff8ec;color:#b54708;border:1px solid #fedf89}.cp-page-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:18px}.cp-page-head h1{margin:0;font-size:28px;font-weight:600}.cp-page-head p{margin:7px 0 0;color:var(--cp-muted);font-weight:600}.cp-eyebrow{margin:0 0 6px;color:var(--cp-primary);font-size:11px;text-transform:uppercase;letter-spacing:.13em;font-weight:600}.cp-grid-6{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:14px}.cp-grid-4{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.cp-grid-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.cp-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.cp-stat{padding:18px}.cp-stat span{display:block;color:var(--cp-muted);font-size:12px;font-weight:600;text-transform:uppercase}.cp-stat strong{display:block;margin-top:7px;font-size:26px}.cp-table-wrap{overflow:auto}.cp-table{width:100%;border-collapse:collapse;min-width:850px}.cp-table th,.cp-table td{padding:15px 18px;border-bottom:1px solid var(--cp-border);text-align:left}.cp-table th{font-size:12px;text-transform:uppercase;letter-spacing:.07em;color:#7d8aa0;background:#fbfdff}.cp-badge{display:inline-flex;border-radius:999px;padding:6px 10px;font-size:11px;font-weight:600;text-transform:uppercase}.status-active,.status-approved,.status-paid,.status-delivered,.status-completed{background:#e8fff7;color:#047857}.status-in_progress,.status-in-transit,.status-in_transit,.status-sent,.status-partial{background:#eaf1ff;color:#3f7cf4}.status-pending,.status-unpaid,.status-waiting_client,.status-waiting_vendor,.status-overdue{background:#fff4e5;color:#d97706}.status-rejected,.status-cancelled,.status-delayed{background:#ffeaf0;color:#e11d48}.status-draft,.status-planning,.status-inactive{background:#f3f6fb;color:#536079}.cp-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.cp-field{display:flex;flex-direction:column;gap:7px}.cp-field label{font-size:12px;color:#536079;font-weight:600}.cp-field input,.cp-field select,.cp-field textarea{width:100%;border:1px solid #d8e2ef;border-radius:13px;padding:11px;background:#fff;outline:none}.cp-field textarea{min-height:90px;resize:vertical}.cp-field input:focus,.cp-field select:focus,.cp-field textarea:focus{border-color:var(--cp-primary);box-shadow:0 0 0 3px rgba(79,131,241,.12)}.cp-empty{text-align:center;padding:40px;border:1px dashed #d8deea;border-radius:18px;color:var(--cp-muted);font-weight:600;background:#fbfcff}.cp-file{display:flex;gap:12px;align-items:center;border:1px solid var(--cp-border);border-radius:16px;padding:12px}.cp-file img,.cp-file-icon{width:58px;height:58px;border-radius:14px;object-fit:cover;background:#eef3ff;color:#4f83f1;display:grid;place-items:center;font-size:24px}.cp-comment{border:1px solid var(--cp-border);border-radius:16px;padding:12px;margin-bottom:10px}.cp-comment-head{display:flex;justify-content:space-between;gap:10px;color:var(--cp-muted);font-size:12px}.cp-comment p{white-space:pre-wrap;margin:8px 0 0}.cp-overlay{display:none}.cp-pagination{padding:16px}.cp-muted{color:var(--cp-muted)}@media(max-width:1199px){.cp-sidebar{transform:translateX(-100%);transition:.2s}.cp-main{margin-left:0;width:100%}.cp-shell.sidebar-open .cp-sidebar{transform:translateX(0)}.cp-shell.sidebar-open .cp-overlay{display:block;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:40}.cp-grid-4{grid-template-columns:repeat(2,minmax(0,1fr))}.cp-grid-6{grid-template-columns:repeat(1,minmax(0,1fr))}}@media(max-width:767px){.cp-content{padding:14px}.cp-topbar{padding:0 14px}.cp-title{font-size:16px}.cp-page-head{flex-direction:column}.cp-grid-6,.cp-grid-4,.cp-grid-3,.cp-grid-2,.cp-form-grid{grid-template-columns:1fr}.cp-top-actions .hide-mobile{display:none}.cp-card{border-radius:18px}.cp-page-head h1{font-size:24px}.cp-sidebar{width:270px}.cp-file{align-items:flex-start}.cp-table{min-width:760px}}
        .notification-icon {
            position: relative;
            display: inline-flex;
        }
        
        .notification-dot {
            position: absolute;
            top: -6px;
            right: -8px;
            min-width: 16px;
            height: 16px;
            padding: 0 4px;
            border-radius: 20px;
            background: #ef4444;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .notification-bell {
            animation: bellRing 1.8s ease-in-out infinite;
            transform-origin: top center;
        }
        
        @keyframes bellRing {
            0%, 100% {
                transform: rotate(0deg);
            }
        
            5%, 15% {
                transform: rotate(15deg);
            }
        
            10%, 20% {
                transform: rotate(-15deg);
            }
        
            25% {
                transform: rotate(8deg);
            }
        
            30% {
                transform: rotate(-8deg);
            }
        
            35% {
                transform: rotate(0deg);
            }
        }
    </style>

    {{-- Favicon --}}
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo.png') }}">

    {{-- Fonts / Icons --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    {{-- App CSS --}}
    <link rel="stylesheet" href="{{ asset('assets/css/core.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app-layout.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/master-index.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/master-show.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/master-form.css') }}">

    @stack('styles')
</head>
<body style="line-height:1.5;">
@php
    $cpUser = $clientPortalUser ?? null;
    $cpClient = $clientPortalClient ?? ($cpUser ? $cpUser->client : null);
    $unreadCount = $cpUser ? \App\Models\ClientPortalNotification::where('client_id', $cpUser->client_id)->where(function($q) use ($cpUser){ $q->whereNull('client_portal_user_id')->orWhere('client_portal_user_id', $cpUser->id); })->where('is_read', false)->count() : 0;
@endphp
<div class="cp-shell" id="cpShell">
    <div class="cp-overlay" id="cpOverlay"></div>
    <aside class="cp-sidebar">
        <div class="cp-logo" style="line-height:1.5;">
            <strong>MissPack</strong>
            <span>Client Portal</span>
        </div>
        <nav class="cp-nav">
            <div class="cp-nav-section">Workspace</div>
            <a href="{{ route('client-portal.dashboard') }}" class="sidebar-item {{ request()->routeIs('client-portal.dashboard*') ? 'active' : '' }}">
                <i class="fas fa-th-large"></i> <span class="sidebar-text">Dashboard</span>
            </a>
            <a href="{{ route('client-portal.projects.index') }}" class="sidebar-item {{ request()->routeIs('client-portal.projects.*') ? 'active' : '' }}">
                <i class="fa-solid fa-briefcase"></i><span class="sidebar-text">Projects</span>
            </a>
            <a href="{{ route('client-portal.shipments.index') }}" class="sidebar-item {{ request()->routeIs('client-portal.shipments.*') ? 'active' : '' }}">
                <i class="fas fa-truck"></i><span class="sidebar-text">Shipments</span>
            </a>
            <a href="{{ route('client-portal.invoices.index') }}" class="sidebar-item {{ request()->routeIs('client-portal.invoices.*') ? 'active' : '' }}">
                <i class="fa-solid fa-file-invoice-dollar"></i><span class="sidebar-text">Invoices</span>
            </a>
            <a href="{{ route('client-portal.attachments.index') }}" class="sidebar-item {{ request()->routeIs('client-portal.attachments.*') ? 'active' : '' }}">
                <i class="fas fa-folder"></i><span class="sidebar-text">Attachments</span>
            </a>
            <a href="{{ route('client-portal.payments.index') }}" class="sidebar-item {{ request()->routeIs('client-portal.payments.*') ? 'active' : '' }}">
                <i class="fa-solid fa-scale-balanced"></i><span class="sidebar-text">Payments</span>
            </a>
            <div class="cp-nav-section">Account</div>
            <a href="{{ route('client-portal.kyc.show') }}" class="sidebar-item {{ request()->routeIs('client-portal.kyc.*') ? 'active' : '' }}">
                <i class="fas fa-user-gear"></i><span class="sidebar-text">KYC Form</span>
            </a>
            <a href="{{ route('client-portal.notifications.index') }}" class="sidebar-item {{ request()->routeIs('client-portal.notifications.*') ? 'active' : '' }}">
                <i class="fas fa-bell"></i><span class="sidebar-text">Notifications</span>
                @if($unreadCount)
                    <span style="margin-left:auto;background:#ef4770;color:#fff;border-radius:999px;padding:2px 7px;font-size:11px;">{{ $unreadCount }}</span>
                @endif
            </a>
        </nav>
        <div class="cp-footer">
            <div class="cp-user">
                <div class="cp-avatar">{{ strtoupper(substr($cpUser ? $cpUser->displayName() : 'C', 0, 1)) }}</div>
                <div style="min-width:0;line-height:1.5;">
                    <strong>{{ $cpUser ? $cpUser->displayName() : 'Client' }}</strong>
                    <span>{{ $cpClient ? $cpClient->company_name : 'MissPack Client' }}</span>
                </div>
            </div>
        </div>
    </aside>

    <main class="cp-main">
        <header class="cp-topbar">
            <div class="cp-top-left">
                <button type="button" class="cp-toggle" id="cpToggle">☰</button>
                <div class="cp-title">@yield('page-title', 'Client Portal')</div>
            </div>
            <div class="cp-top-actions">
                <a href="{{ route('client-portal.notifications.index') }}" class="cp-pill hide-mobile">
                    <div class="notification-icon">
                        <i class="fas fa-bell notification-bell large"></i>
                        <span class="notification-dot">{{ $unreadCount }}</span>
                    </div>
                </a>
                <a href="{{ route('client-portal.password.edit') }}" class="cp-pill hide-mobile">Password</a>
                <form method="POST" action="{{ route('client-portal.logout') }}" style="margin:0;">@csrf<button class="cp-pill danger" style="cursor:pointer;" type="submit">Logout</button></form>
            </div>
        </header>
        <div class="cp-content">
            @yield('content')
        </div>
    </main>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var shell = document.getElementById('cpShell');
    var toggle = document.getElementById('cpToggle');
    var overlay = document.getElementById('cpOverlay');
    if (toggle) toggle.addEventListener('click', function () { shell.classList.toggle('sidebar-open'); });
    if (overlay) overlay.addEventListener('click', function () { shell.classList.remove('sidebar-open'); });
});
</script>
</body>
</html>
