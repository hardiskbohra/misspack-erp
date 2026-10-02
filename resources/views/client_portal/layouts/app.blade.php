<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Client Portal') | MissPack</title>
    <link rel="stylesheet" href="{{ $assetVer('assets/css/client-portal.css') }}">

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
    <link rel="stylesheet" href="{{ $assetVer('assets/css/core.css') }}">
    <link rel="stylesheet" href="{{ $assetVer('assets/css/app-layout.css') }}">
    <link rel="stylesheet" href="{{ $assetVer('assets/css/master-index.css') }}">
    <link rel="stylesheet" href="{{ $assetVer('assets/css/master-show.css') }}">
    <link rel="stylesheet" href="{{ $assetVer('assets/css/master-form.css') }}">
    <link rel="stylesheet" href="{{ $assetVer('assets/css/master-detail.css') }}">
    <link rel="stylesheet" href="{{ $assetVer('assets/css/master-flat.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ $assetVer('assets/css/select2-theme.css') }}">
    <link rel="stylesheet" href="{{ $assetVer('assets/css/master-alert.css') }}">

    @stack('styles')

    {{-- Central responsive layer (must load last so it can fill module gaps) --}}
    <link rel="stylesheet" href="{{ $assetVer('assets/css/responsive.css') }}">
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
    <script src="{{ asset('assets/vendor/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/select2/js/select2.min.js') }}"></script>
    <script src="{{ $assetVer('assets/js/master-alert.js') }}"></script>
    <script src="{{ $assetVer('assets/js/master-selects.js') }}"></script>
    <script src="{{ $assetVer('assets/js/money.js') }}"></script>
    <script src="{{ $assetVer('assets/js/client-portal.js') }}"></script>
@stack('scripts')
</body>
</html>
