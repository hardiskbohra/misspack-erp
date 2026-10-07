<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#13263a">
    <title>@yield('title', 'Client Workspace') | MissPack</title>
    <script>document.documentElement.dataset.theme = localStorage.getItem('misspack-portal-theme') || 'light';</script>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
    $portalNav = [
        'Workspace' => [
            ['client-portal.dashboard', 'client-portal.dashboard*', 'fa-solid fa-house', 'Overview'],
            ['client-portal.projects.index', 'client-portal.projects.*', 'fa-solid fa-briefcase', 'Projects'],
            ['client-portal.shipments.index', 'client-portal.shipments.*', 'fa-solid fa-truck-fast', 'Shipments'],
            ['client-portal.products.index', 'client-portal.products.*', 'fa-solid fa-box-open', 'Product catalogue'],
        ],
        'Finance & files' => [
            ['client-portal.invoices.index', 'client-portal.invoices.*', 'fa-solid fa-file-invoice-dollar', 'Invoices'],
            ['client-portal.payments.index', 'client-portal.payments.*', 'fa-solid fa-arrow-right-arrow-left', 'Payments'],
            ['client-portal.statement.index', 'client-portal.statement.*', 'fa-solid fa-scale-balanced', 'Statement'],
            ['client-portal.attachments.index', 'client-portal.attachments.*', 'fa-solid fa-folder-open', 'Documents'],
        ],
        'Connect' => [
            ['client-portal.support.index', 'client-portal.support.*', 'fa-regular fa-comments', 'Support inbox'],
            ['client-portal.feedback.index', 'client-portal.feedback.*', 'fa-regular fa-comment-dots', 'Feedback'],
            ['client-portal.notifications.index', 'client-portal.notifications.*', 'fa-regular fa-bell', 'Notifications'],
        ],
        'Account' => [
            ['client-portal.account.index', 'client-portal.account.*', 'fa-regular fa-user', 'My profile'],
            ['client-portal.kyc.show', 'client-portal.kyc.*', 'fa-solid fa-address-card', 'KYC workspace'],
        ],
    ];
@endphp
<a class="cp-skip-link" href="#portalContent">Skip to content</a>
<div class="cp-shell" id="cpShell">
    <button class="cp-overlay" id="cpOverlay" type="button" aria-label="Close navigation"></button>
    <aside class="cp-sidebar" id="cpSidebar" aria-label="Primary navigation">
        <a class="cp-logo" href="{{ route('client-portal.dashboard') }}" aria-label="MissPack Client Workspace home">
            <span class="cp-logo-mark"><i class="fa-solid fa-cubes-stacked"></i></span>
            <span class="cp-logo-copy"><strong>MissPack</strong><small>CLIENT WORKSPACE</small></span>
        </a>
        <div class="cp-client-switcher">
            <span class="cp-client-avatar">{{ strtoupper(substr($cpClient?->company_name ?: 'C', 0, 1)) }}</span>
            <span><small>COMPANY ACCOUNT</small><strong>{{ $cpClient?->company_name ?: 'Client account' }}</strong></span>
        </div>
        <nav class="cp-nav">
            @foreach($portalNav as $section => $items)
                <div class="cp-nav-section">{{ $section }}</div>
                @foreach($items as [$routeName, $routePattern, $icon, $label])
                    <a href="{{ route($routeName) }}" class="cp-nav-link {{ request()->routeIs($routePattern) ? 'active' : '' }}" @if(request()->routeIs($routePattern)) aria-current="page" @endif>
                        <i class="{{ $icon }}"></i><span>{{ $label }}</span>
                        @if($routeName === 'client-portal.notifications.index' && $unreadCount)<span class="cp-nav-count">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>@endif
                    </a>
                @endforeach
            @endforeach
        </nav>
        <div class="cp-sidebar-footer">
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
                <button type="button" class="cp-toggle" id="cpToggle" aria-label="Open navigation" aria-controls="cpSidebar" aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
                <div class="cp-breadcrumb"><span>Workspace</span><i class="fa-solid fa-chevron-right"></i><strong>@yield('page-title', 'Overview')</strong></div>
            </div>
            <div class="cp-top-actions">
                <button class="cp-command-trigger" type="button" data-command-open aria-haspopup="dialog"><i class="fa-solid fa-magnifying-glass"></i><span>Find anything</span><kbd>/</kbd></button>
                <span class="cp-topbar-company"><i class="fa-regular fa-building"></i> {{ $cpClient?->company_name ?: 'Client account' }}</span>
                <button class="cp-top-icon" type="button" data-theme-toggle aria-label="Switch color theme"><i class="fa-regular fa-moon"></i></button>
                <a href="{{ route('client-portal.notifications.index') }}" class="cp-top-icon" aria-label="Notifications{{ $unreadCount ? ', '.$unreadCount.' unread' : '' }}"><i class="fa-regular fa-bell"></i>@if($unreadCount)<span></span>@endif</a>
                <a href="{{ route('client-portal.account.index') }}" class="cp-profile-chip"><span>{{ strtoupper(substr($cpUser?->displayName() ?: 'C', 0, 1)) }}</span><strong>{{ $cpUser?->displayName() ?: 'My profile' }}</strong></a>
                <form method="POST" action="{{ route('client-portal.logout') }}" class="cp-logout-form">@csrf<button class="cp-top-icon cp-logout" type="submit" aria-label="Log out"><i class="fa-solid fa-arrow-right-from-bracket"></i></button></form>
            </div>
        </header>
        <div class="cp-content" id="portalContent" tabindex="-1">
            @if(session('success'))<div class="cp-flash cp-flash-success" role="status"><i class="fa-solid fa-circle-check"></i><span>{{ session('success') }}</span></div>@endif
            @if(session('error'))<div class="cp-flash cp-flash-error" role="alert"><i class="fa-solid fa-circle-exclamation"></i><span>{{ session('error') }}</span></div>@endif
            @if(session('warning'))<div class="cp-flash cp-flash-warning" role="status"><i class="fa-solid fa-triangle-exclamation"></i><span>{{ session('warning') }}</span></div>@endif
            @if($errors->any())<div class="cp-flash cp-flash-error" role="alert"><i class="fa-solid fa-circle-exclamation"></i><span>{{ $errors->first() }}</span></div>@endif
            @yield('content')
        </div>
    </main>
</div>

<div class="cp-command" id="cpCommand" role="dialog" aria-modal="true" aria-labelledby="cpCommandTitle" hidden>
    <button class="cp-command-backdrop" type="button" data-command-close aria-label="Close search"></button>
    <div class="cp-command-dialog">
        <h2 class="visually-hidden" id="cpCommandTitle">Navigate the client workspace</h2>
        <div class="cp-command-search"><i class="fa-solid fa-magnifying-glass"></i><input id="cpCommandInput" type="search" placeholder="Search pages and actions…" autocomplete="off"><button class="cp-command-close" type="button" data-command-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button></div>
        <div class="cp-command-results" id="cpCommandResults">
            @foreach($portalNav as $section => $items)
                <div class="cp-command-group">{{ $section }}</div>
                @foreach($items as [$routeName, $routePattern, $icon, $label])
                    <a class="cp-command-item" href="{{ route($routeName) }}" data-command-item data-search="{{ strtolower($section.' '.$label) }}"><i class="{{ $icon }}"></i><span>{{ $label }}</span><i class="fa-solid fa-arrow-right"></i></a>
                @endforeach
            @endforeach
            <div class="cp-command-empty" data-command-empty hidden>No matching workspace page.</div>
        </div>
    </div>
</div>

<nav class="cp-mobile-nav" aria-label="Mobile navigation">
    <a href="{{ route('client-portal.dashboard') }}" class="{{ request()->routeIs('client-portal.dashboard*') ? 'active' : '' }}"><i class="fa-solid fa-house"></i><span>Home</span></a>
    <a href="{{ route('client-portal.projects.index') }}" class="{{ request()->routeIs('client-portal.projects.*') ? 'active' : '' }}"><i class="fa-solid fa-briefcase"></i><span>Projects</span></a>
    <a href="{{ route('client-portal.shipments.index') }}" class="{{ request()->routeIs('client-portal.shipments.*') ? 'active' : '' }}"><i class="fa-solid fa-truck-fast"></i><span>Shipments</span></a>
    <a href="{{ route('client-portal.invoices.index') }}" class="{{ request()->routeIs('client-portal.invoices.*') ? 'active' : '' }}"><i class="fa-solid fa-file-invoice-dollar"></i><span>Invoices</span></a>
    <button type="button" data-command-open><i class="fa-solid fa-table-cells-large"></i><span>More</span></button>
</nav>

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
