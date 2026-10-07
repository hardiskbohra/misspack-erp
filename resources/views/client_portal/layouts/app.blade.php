<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#112b46">
    <title>@yield('title', 'Client Workspace') | MissPack</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="{{ $assetVer('assets/css/client-portal.css') }}">
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
    <link rel="stylesheet" href="{{ $assetVer('assets/css/responsive.css') }}">
    <link rel="stylesheet" href="{{ $assetVer('assets/css/client-portal-workspace.css') }}">
    @include('layouts.partials.design-system-styles')
</head>
<body class="client-workspace-body" data-ui-shell="portal">
@php
    $cpUser = $clientPortalUser ?? null;
    $cpClient = $clientPortalClient ?? ($cpUser ? $cpUser->client : null);
    $unreadCount = $cpUser
        ? \App\Models\ClientPortalNotification::query()
            ->where('client_id', $cpUser->client_id)
            ->where(fn($q) => $q->whereNull('client_portal_user_id')->orWhere('client_portal_user_id', $cpUser->id))
            ->where('is_read', false)
            ->count()
        : 0;
    $currentRoute = request()->route()?->getName() ?? '';
@endphp
<div class="cp-shell" id="cpShell">
    <button class="cp-overlay" id="cpOverlay" type="button" aria-label="Close navigation"></button>
    <aside class="cp-sidebar" id="cpSidebar">
        <a class="cp-logo" href="{{ route('client-portal.dashboard') }}" aria-label="MissPack Client Workspace home">
            <span class="cp-logo-mark"><i class="fa-solid fa-cubes-stacked"></i></span>
            <span class="cp-logo-copy"><strong>MissPack</strong><small>CLIENT WORKSPACE</small></span>
        </a>

        <div class="cp-client-switcher">
            <span class="cp-client-avatar">{{ strtoupper(substr($cpClient?->company_name ?: 'C', 0, 1)) }}</span>
            <span><small>WORKING WITH</small><strong>{{ $cpClient?->company_name ?: 'Client account' }}</strong></span>
            <i class="fa-solid fa-chevron-down"></i>
        </div>

        <nav class="cp-nav" aria-label="Client workspace navigation">
            <div class="cp-nav-section">Workspace</div>
            <a href="{{ route('client-portal.dashboard') }}" class="cp-nav-link {{ request()->routeIs('client-portal.dashboard*') ? 'active' : '' }}"><i class="fa-solid fa-house"></i><span>Overview</span></a>
            <a href="{{ route('client-portal.projects.index') }}" class="cp-nav-link {{ request()->routeIs('client-portal.projects.*') ? 'active' : '' }}"><i class="fa-solid fa-briefcase"></i><span>Projects</span></a>
            <a href="{{ route('client-portal.shipments.index') }}" class="cp-nav-link {{ request()->routeIs('client-portal.shipments.*') ? 'active' : '' }}"><i class="fa-solid fa-truck-fast"></i><span>Shipments</span></a>
            <a href="{{ route('client-portal.products.index') }}" class="cp-nav-link {{ request()->routeIs('client-portal.products.*') ? 'active' : '' }}"><i class="fa-solid fa-box-open"></i><span>Product catalogue</span></a>

            <div class="cp-nav-section">Finance & files</div>
            <a href="{{ route('client-portal.invoices.index') }}" class="cp-nav-link {{ request()->routeIs('client-portal.invoices.*') ? 'active' : '' }}"><i class="fa-solid fa-file-invoice-dollar"></i><span>Invoices</span></a>
            <a href="{{ route('client-portal.payments.index') }}" class="cp-nav-link {{ request()->routeIs('client-portal.payments.*') ? 'active' : '' }}"><i class="fa-solid fa-arrow-right-arrow-left"></i><span>Payments</span></a>
            <a href="{{ route('client-portal.statement.index') }}" class="cp-nav-link {{ request()->routeIs('client-portal.statement.*') ? 'active' : '' }}"><i class="fa-solid fa-scale-balanced"></i><span>Statement</span></a>
            <a href="{{ route('client-portal.attachments.index') }}" class="cp-nav-link {{ request()->routeIs('client-portal.attachments.*') ? 'active' : '' }}"><i class="fa-solid fa-folder-open"></i><span>Documents</span></a>
            <a href="{{ route('client-portal.feedback.index') }}" class="cp-nav-link {{ request()->routeIs('client-portal.feedback.*') ? 'active' : '' }}"><i class="fa-regular fa-comment-dots"></i><span>Feedback</span></a>

            <div class="cp-nav-section">Stay in touch</div>
            <a href="{{ route('client-portal.support.index') }}" class="cp-nav-link {{ request()->routeIs('client-portal.support.*') ? 'active' : '' }}"><i class="fa-regular fa-comments"></i><span>Support inbox</span><span class="cp-nav-pulse"></span></a>

            <div class="cp-nav-section">Account</div>
            <a href="{{ route('client-portal.account.index') }}" class="cp-nav-link {{ request()->routeIs('client-portal.account.*') ? 'active' : '' }}"><i class="fa-regular fa-user"></i><span>My profile</span></a>
            <a href="{{ route('client-portal.kyc.show') }}" class="cp-nav-link {{ request()->routeIs('client-portal.kyc.*') ? 'active' : '' }}"><i class="fa-solid fa-address-card"></i><span>KYC workspace</span></a>
            <a href="{{ route('client-portal.notifications.index') }}" class="cp-nav-link {{ request()->routeIs('client-portal.notifications.*') ? 'active' : '' }}"><i class="fa-regular fa-bell"></i><span>Notifications</span>@if($unreadCount)<span class="cp-nav-count">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>@endif</a>
        </nav>

        <div class="cp-sidebar-footer">
            <div class="cp-help-card"><span><i class="fa-solid fa-headset"></i></span><strong>Need a hand?</strong><small>Talk with the MissPack team.</small><a href="{{ route('client-portal.support.index') }}">Get support <i class="fa-solid fa-arrow-right"></i></a></div>
            <div class="cp-sidebar-user">
                <span class="cp-user-avatar">{{ strtoupper(substr($cpUser?->displayName() ?: 'C', 0, 1)) }}</span>
                <span class="cp-user-copy"><strong>{{ $cpUser?->displayName() ?: 'Client' }}</strong><small>{{ $cpUser?->email ?: 'Portal account' }}</small></span>
                <a href="{{ route('client-portal.account.index') }}" aria-label="Open profile"><i class="fa-solid fa-ellipsis"></i></a>
            </div>
        </div>
    </aside>

    <main class="cp-main">
        <header class="cp-topbar">
            <div class="cp-top-left">
                <button type="button" class="cp-toggle" id="cpToggle" aria-label="Open navigation"><i class="fa-solid fa-bars"></i></button>
                <div class="cp-breadcrumb"><span>Client workspace</span><i class="fa-solid fa-chevron-right"></i><strong>@yield('page-title', 'Overview')</strong></div>
            </div>
            <div class="cp-top-actions">
                <span class="cp-topbar-company"><i class="fa-regular fa-building"></i> {{ $cpClient?->company_name ?: 'Client account' }}</span>
                <a href="{{ route('client-portal.notifications.index') }}" class="cp-top-icon" aria-label="Notifications"><i class="fa-regular fa-bell"></i>@if($unreadCount)<span></span>@endif</a>
                <a href="{{ route('client-portal.account.index') }}" class="cp-profile-chip"><span>{{ strtoupper(substr($cpUser?->displayName() ?: 'C', 0, 1)) }}</span><strong>{{ $cpUser?->displayName() ?: 'My profile' }}</strong><i class="fa-solid fa-chevron-down"></i></a>
                <form method="POST" action="{{ route('client-portal.logout') }}" class="cp-logout-form">@csrf<button class="cp-top-icon cp-logout" type="submit" aria-label="Log out"><i class="fa-solid fa-arrow-right-from-bracket"></i></button></form>
            </div>
        </header>
        <div class="cp-content">
            @if(session('success'))<div class="cp-flash cp-flash-success"><i class="fa-solid fa-circle-check"></i><span>{{ session('success') }}</span></div>@endif
            @if(session('error'))<div class="cp-flash cp-flash-error"><i class="fa-solid fa-circle-exclamation"></i><span>{{ session('error') }}</span></div>@endif
            @if(session('warning'))<div class="cp-flash cp-flash-warning"><i class="fa-solid fa-triangle-exclamation"></i><span>{{ session('warning') }}</span></div>@endif
            @if($errors->any())<div class="cp-flash cp-flash-error"><i class="fa-solid fa-circle-exclamation"></i><span>{{ $errors->first() }}</span></div>@endif
            @yield('content')
        </div>
    </main>
</div>
<script src="{{ asset('assets/vendor/jquery/jquery.min.js') }}"></script>
<script src="{{ asset('assets/vendor/select2/js/select2.min.js') }}"></script>
<script src="{{ $assetVer('assets/js/master-alert.js') }}"></script>
<script src="{{ $assetVer('assets/js/master-selects.js') }}"></script>
<script src="{{ $assetVer('assets/js/master-list.js') }}"></script>
<script src="{{ $assetVer('assets/js/master-drawer.js') }}"></script>
<script src="{{ $assetVer('assets/js/money.js') }}"></script>
<script src="{{ $assetVer('assets/js/client-portal.js') }}"></script>
@stack('scripts')
</body>
</html>
