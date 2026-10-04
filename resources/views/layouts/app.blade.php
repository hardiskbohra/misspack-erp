<!DOCTYPE html>
<html lang="en" data-theme="{{ session('theme', 'light') }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>MissPack ERP - @yield('title', 'Dashboard')</title>

    {{-- Favicon --}}
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo.png') }}">

    {{-- Fonts / Icons --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    {{-- Apply saved desktop sidebar state before CSS paints --}}
    <script>
        (function () {
            try {
                var isTabletOrMobile = window.matchMedia('(max-width: 991px)').matches;
                var isCompactDesktop = window.matchMedia('(min-width: 992px) and (max-width: 1199px)').matches;
                var savedCollapsed = localStorage.getItem('sidebarCollapsed') === '1';

                if (!isTabletOrMobile && (isCompactDesktop || savedCollapsed)) {
                    document.documentElement.classList.add('sidebar-collapsed');
                }
            } catch (e) {}
        })();
    </script>

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

    {{-- The shared list chrome — chips, applied strip, density, pinned grid,
         mobile cards, totals row, empty state, the modal sheet, and the rhythm
         between two stacked cards. Loaded by the shell, after the module's own
         sheet so the chrome keeps its own properties: a page cannot forget it,
         and the statement page did — it wore .master-list without ever loading
         the sheet that spaces and insets it. --}}
    <link rel="stylesheet" href="{{ $assetVer('assets/css/master-list.css') }}">

    {{-- Compatibility layer for the existing four-module markup; the shared
         core design system follows and remains the canonical component owner. --}}
    <link rel="stylesheet" href="{{ $assetVer('assets/css/app-guidelines.css') }}">
    <link rel="stylesheet" href="{{ $assetVer('assets/css/responsive.css') }}">
    @include('layouts.partials.design-system-styles')
</head>
@php
    $uiModule = match (true) {
        request()->routeIs('shipments.*') => 'shipments',
        request()->routeIs('clients.*') => 'clients',
        request()->routeIs('vendors.*') => 'vendors',
        request()->routeIs('cashflows.*') => 'cashflows',
        request()->routeIs('users.*') => 'users',
        default => null,
    };
@endphp
<body data-ui-shell="app" @if($uiModule) data-ui-module="{{ $uiModule }}" @endif>
    @php
        /* An employee's menu is their own record, and this is the only menu the
           application shows them: the office's screens are behind a middleware
           they cannot pass, so listing them here would be offering doors that
           do not open. The workspace pages are the same four questions in the
           same order a payroll clerk would ask them. */
        $employeeItems = [
            ['section' => 'My Workspace'],
            ['label' => 'Dashboard', 'route' => 'my.dashboard', 'active' => 'my.dashboard', 'icon' => 'fas fa-th-large'],
            ['label' => 'My Salary', 'route' => 'my.salary', 'active' => 'my.salary', 'icon' => 'fa-solid fa-indian-rupee-sign'],
            ['label' => 'My Documents', 'route' => 'my.documents', 'active' => 'my.documents*', 'icon' => 'fa-regular fa-folder-open'],
            ['label' => 'My Profile', 'route' => 'my.profile', 'active' => 'my.profile*', 'icon' => 'fa-regular fa-id-card'],
        ];

        $sidebarItems = Auth::user() && Auth::user()->isEmployee() ? $employeeItems : [
            ['section' => 'Dashboards'],
            ['label' => 'Dashboard', 'route' => 'dashboard', 'active' => 'dashboard', 'icon' => 'fas fa-th-large'],
            ['section' => 'Management'],
            ['label' => 'Tasks', 'route' => 'tasks.index', 'active' => 'tasks.*', 'icon' => 'fa-solid fa-layer-group'],
            ['label' => 'Products', 'route' => 'products.index', 'active' => 'products.*', 'icon' => 'fas fa-box-open'],
            ['label' => 'Shipments', 'route' => 'shipments.index', 'active' => 'shipments.*', 'icon' => 'fas fa-truck'],
            ['section' => 'Sales'],
            ['label' => 'Clients', 'route' => 'clients.index', 'active' => 'clients.*', 'icon' => 'fa-solid fa-users'],
            ['label' => 'Projects', 'route' => 'projects.index', 'active' => 'projects.*', 'icon' => 'fa-solid fa-briefcase'],
            ['label' => 'Invoices', 'route' => 'sales-invoices.index', 'active' => 'sales-invoices.*', 'icon' => 'fa-solid fa-file-invoice-dollar'],
            ['section' => 'Purchase'],
            ['label' => 'Vendors', 'route' => 'vendors.index', 'active' => 'vendors.*', 'icon' => 'fa-solid fa-user-gear'],
            ['label' => 'Vendor Quotes', 'route' => 'vendor-quotes.index', 'active' => 'vendor-quotes.*', 'icon' => 'fa-solid fa-money-bill'],
            ['section' => 'Accounts'],
            ['label' => 'Cashflow', 'route' => 'cashflows.index', 'active' => 'cashflows.*', 'except' => ['cashflows.documents', 'cashflows.statements', 'cashflows.statements.*'], 'icon' => 'fa-solid fa-scale-balanced'],
            /* The archive is a page of the module, not a second module: it sits
               here so a month's paperwork is one click from anywhere, and the
               Cashflow item above stays dark while it is open. */
            ['label' => 'Document Archive', 'route' => 'cashflows.documents', 'active' => 'cashflows.documents', 'icon' => 'fa-regular fa-folder-open'],
            /* And the other half of what leaves the building: the party's own
               account, in the currency their statement is kept in. */
            ['label' => 'Statements', 'route' => 'cashflows.statements', 'active' => 'cashflows.statements*', 'icon' => 'fa-solid fa-file-invoice'],
            ['label' => 'Users', 'route' => 'users.index', 'active' => 'users.*', 'icon' => 'fas fa-users-cog'],
        ];
    @endphp
    
    
            <!--['label' => 'Leads', 'route' => 'leads.index', 'active' => 'leads.*', 'icon' => 'fa-solid fa-people-group'],-->
            <!--['label' => 'Lead Quotes', 'route' => 'lead-quotes.index', 'active' => 'lead-quotes.*', 'icon' => 'fa-solid fa-file-invoice-dollar'],-->
            <!--['label' => 'Price Calculator', 'route' => 'price-calculator.index', 'active' => 'price-calculator.*', 'icon' => 'fa-solid fa-calculator'],-->

    {{-- Sidebar --}}
    <aside class="sidebar" id="sidebar" aria-label="Main sidebar">
        <div class="sidebar-logo">
            <div>
                <div class="sidebar-logo-text sidebar-text">MissPack</div>
                <div class="sidebar-logo-sub sidebar-text">Packed Perfect</div>
            </div>
        </div>

        <nav class="sidebar-nav">
            @foreach($sidebarItems as $item)
                @if(isset($item['section']))
                    <div class="sidebar-section">{{ $item['section'] }}</div>
                @else
                    @php
                        /* One item lights up at a time: an item may exclude the
                           routes a sibling item owns. */
                        $itemActive = request()->routeIs($item['active'])
                            && ! (isset($item['except']) && request()->routeIs($item['except']));
                    @endphp
                    <a href="{{ route($item['route']) }}"
                       class="sidebar-item {{ $itemActive ? 'active' : '' }}"
                       aria-label="{{ $item['label'] }}" title="{{ $item['label'] }}">
                        <i class="{{ $item['icon'] }}"></i>
                        <span class="sidebar-text">{{ $item['label'] }}</span>
                    </a>
                @endif
            @endforeach
        </nav>

        {{-- The card at the foot of the sidebar is the second half of the user
             menu, not a label with a logout icon: everything a person can do
             about their own account is in the one panel both surfaces open. The
             separate logout button went with it — the panel has Sign out, and two
             controls for one action is how they drift. --}}
        <div class="sidebar-footer">
            @include('layouts.partials.user-menu', ['surface' => 'sidebar'])
        </div>
    </aside>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    {{-- Main --}}
    <div class="main-wrap" id="mainWrap">
        <header class="topbar">
            <button type="button" class="topbar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                <i class="fas fa-bars"></i>
            </button>

            <span class="topbar-title">@yield('page-title', 'Dashboard')</span>

            {{-- A page's primary action belongs beside its title, not buried in
                 a toolbar: it stays reachable however far the list scrolls. --}}
            @hasSection('page-actions')
                <div class="topbar-page-actions">@yield('page-actions')</div>
            @endif

            <div class="topbar-spacer"></div>

            <div class="topbar-actions">
                <button type="button" class="topbar-btn desktop-only" aria-label="Search">
                    <i class="fas fa-search"></i>
                </button>

                <form method="POST" action="{{ route('theme.toggle') }}" class="theme-form">
                    @csrf
                    <button type="submit" class="theme-toggle desktop-only">
                        @if(session('theme', 'light') === 'light')
                            <i class="fas fa-moon"></i> <span>Dark</span>
                        @else
                            <i class="fas fa-sun"></i> <span>Light</span>
                        @endif
                    </button>
                </form>

                <button type="button" class="topbar-btn desktop-only" aria-label="Notifications">
                    <i class="fas fa-bell"></i>
                    <span class="topbar-badge">3</span>
                </button>

                {{-- The avatar opens your own account — for both roles. It used
                     to be a link only for an employee and a dead <div> for the
                     office, which is a menu that does nothing for the person most
                     likely to want it. --}}
                @include('layouts.partials.user-menu', ['surface' => 'topbar'])
            </div>
        </header>

        <main class="page-content">
            @yield('content')
        </main>
    </div>

    {{-- Vendor Scripts --}}
    <script src="{{ asset('assets/vendor/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/select2/js/select2.min.js') }}"></script>

    {{-- App Scripts --}}
    <script src="{{ $assetVer('assets/js/master-alert.js') }}"></script>
    <script src="{{ $assetVer('assets/js/master-selects.js') }}"></script>
    <script src="{{ $assetVer('assets/js/money.js') }}"></script>
    <script src="{{ $assetVer('assets/js/app-layout.js') }}"></script>
    <script src="{{ $assetVer('assets/js/master-list.js') }}"></script>
    <script src="{{ $assetVer('assets/js/master-drawer.js') }}"></script>

    {{-- Flash Messages (custom alerts) --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            @if(session('success'))
                MasterAlert.toast(@json(session('success')), 'success', { title: 'Success' });
            @endif

            @if(session('warning'))
                MasterAlert.toast(@json(session('warning')), 'warning', { title: 'Check paperwork' });
            @endif

            @if(session('error'))
                MasterAlert.alert(@json(session('error')), { title: 'Error', type: 'error', danger: true });
            @endif

            @if($errors->any())
                var validationErrors = @json($errors->all());
                MasterAlert.alert('<ul>' + validationErrors.map(function (error) {
                    return '<li>' + escapeHtml(error) + '</li>';
                }).join('') + '</ul>', { title: 'Validation Error', type: 'error', html: true, danger: true });
            @endif
        });

        function escapeHtml(text) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(text));
            return div.innerHTML;
        }
    </script>

    @stack('scripts')
</body>
</html>
