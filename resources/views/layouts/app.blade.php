<!DOCTYPE html>
<html lang="en" data-theme="{{ session('theme', 'light') }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>MissPack ERP - @yield('title', 'Office')</title>

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

    {{-- The shared list chrome — chips, applied strip, pinned grid, mobile
         cards, totals row, empty state, the modal sheet, and the rhythm
         between two stacked cards. Loaded by the shell, after the module's own
         sheet so the chrome keeps its own properties: a page cannot forget it,
         and the statement page did — it wore .master-list without ever loading
         the sheet that spaces and insets it. --}}
    <link rel="stylesheet" href="{{ $assetVer('assets/css/master-list.css') }}">
    <link rel="stylesheet" href="{{ $assetVer('assets/css/office-briefing.css') }}">

    {{-- Compatibility layer for the existing four-module markup; the shared
         core design system follows and remains the canonical component owner. --}}
    <link rel="stylesheet" href="{{ $assetVer('assets/css/app-guidelines.css') }}">
    <link rel="stylesheet" href="{{ $assetVer('assets/css/responsive.css') }}">
    @include('layouts.partials.design-system-styles')
</head>
@php
    $uiModule = match (true) {
        /* A settings area is still *that module's* settings screen: the module
           adapters key a page's card and panel treatment on the module it
           belongs to, so a cashflow settings page that stopped being a cashflow
           page would lose the treatment its own rows were designed with. The
           hub, which belongs to no single area, is the settings module. */
        request()->routeIs('dashboard') => 'dashboard',
        request()->routeIs('settings.cashflow*') => 'cashflows',
        request()->routeIs('settings.organisation*') => 'users',
        request()->routeIs('settings.leads*') => 'leads',
        request()->routeIs('settings.feedback*') => 'feedback',
        request()->routeIs('settings.briefings*') => 'briefings',
        /* The asset classes are a setting; the register is not, and it has its
           own module key because its own sheet keys its cards on it. */
        request()->routeIs('settings.assets*') => 'assets',
        request()->routeIs('assets.*') => 'assets',
        request()->routeIs('settings.*') => 'settings',
        request()->routeIs('notes.*') => 'notes',
        request()->routeIs('shipments.*') => 'shipments',
        request()->routeIs('clients.*') => 'clients',
        request()->routeIs('cashflows.*') => 'cashflows',
        request()->routeIs('users.*') => 'users',
        request()->routeIs('vendors.*') => 'vendors',
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
            /* The one item here that is neither their record nor the office's:
               notes are private to the login, whoever is holding it. */
            ['label' => 'Notes', 'route' => 'notes.index', 'active' => 'notes.*', 'icon' => 'fa-regular fa-note-sticky'],
        ];

        $sidebarItems = Auth::user() && Auth::user()->isEmployee() ? $employeeItems : [
            ['section' => 'Management'],
            ['label' => 'Dashboard', 'route' => 'dashboard', 'active' => 'dashboard', 'icon' => 'fa-solid fa-gauge-high'],
            ['label' => 'Tasks', 'route' => 'tasks.index', 'active' => 'tasks.*', 'icon' => 'fa-solid fa-layer-group'],
            ['label' => 'Products', 'route' => 'products.index', 'active' => 'products.*', 'icon' => 'fas fa-box-open'],
            ['label' => 'Shipments', 'route' => 'shipments.index', 'active' => 'shipments.*', 'icon' => 'fas fa-truck'],
            /* What clients said when the project closed. Management, not Sales:
               the queue is a thing to work, and the person who works it is not
               always the person who sold it. The public form is excluded so a
               user testing a link does not light the office item. */
            ['label' => 'Feedback', 'route' => 'feedback.index', 'active' => 'feedback.*', 'except' => ['feedback.public.*'], 'icon' => 'fa-regular fa-comment-dots'],
            ['section' => 'Sales'],
            ['label' => 'Clients', 'route' => 'clients.index', 'active' => 'clients.*', 'icon' => 'fa-solid fa-users'],
            ['label' => 'Projects', 'route' => 'projects.index', 'active' => 'projects.*', 'icon' => 'fa-solid fa-briefcase'],
            ['label' => 'Invoices', 'route' => 'sales-invoices.index', 'active' => 'sales-invoices.*', 'icon' => 'fa-solid fa-file-invoice-dollar'],
            ['section' => 'Purchase'],
            ['label' => 'Vendors', 'route' => 'vendors.index', 'active' => 'vendors.*', 'icon' => 'fa-solid fa-user-gear'],
            ['label' => 'Office services', 'route' => 'office-services.index', 'active' => 'office-services.*', 'icon' => 'fas fa-hands-helping'],
            /* One list, two documents — a purchase order and the bill it
               becomes — the same way Invoices holds a proforma and a tax invoice. */
            ['label' => 'Purchases', 'route' => 'purchase-invoices.index', 'active' => 'purchase-invoices.*', 'icon' => 'fas fa-file-invoice'],
            ['section' => 'Accounts'],
            ['label' => 'Cashflow', 'route' => 'cashflows.index', 'active' => 'cashflows.*', 'except' => ['cashflows.documents', 'cashflows.statements', 'cashflows.statements.*', 'cashflows.recurring', 'cashflows.recurring.*'], 'icon' => 'fa-solid fa-scale-balanced'],
            /* The standing payments. Next to Cashflow and not inside it: this is
               a list you work from (today's approvals), not a page you visit to
               look at the ledger. */
            ['label' => 'Recurring', 'route' => 'cashflows.recurring.index', 'active' => 'cashflows.recurring.*', 'icon' => 'fa-solid fa-arrows-rotate'],
            /* The archive is a page of the module, not a second module: it sits
               here so a month's paperwork is one click from anywhere, and the
               Cashflow item above stays dark while it is open. */
            ['label' => 'Document Archive', 'route' => 'cashflows.documents', 'active' => 'cashflows.documents', 'icon' => 'fa-regular fa-folder-open'],
            /* And the other half of what leaves the building: the party's own
               account, in the currency their statement is kept in. */
            ['label' => 'Statements', 'route' => 'cashflows.statements', 'active' => 'cashflows.statements*', 'icon' => 'fa-solid fa-file-invoice'],
            /* The register: what the company owns, on the other side of the
               balance sheet from the money the ledger counts. Its classes are a
               setting and sit in Settings with the other five areas — the assets
               themselves are records, and records have modules. */
            ['label' => 'Fixed assets', 'route' => 'assets.index', 'active' => 'assets.*', 'icon' => 'fa-solid fa-industry'],
            ['label' => 'Users', 'route' => 'users.index', 'active' => 'users.*', 'icon' => 'fas fa-users-cog'],
            /* One door for every rule in the ERP. The module-wise menu is the
               rail *inside* Settings, where the reader is already looking for a
               setting and the five areas sit next to what they change; a second
               tree here would be the same five links in two places, and the day
               the two disagree is the day one of them is wrong. */
            ['label' => 'Settings', 'route' => 'settings.index', 'active' => 'settings.*', 'icon' => 'fas fa-sliders'],
            /* Personal, not management: the office's own notes are private to
               the office's own login, exactly as an employee's are to theirs. */
            ['section' => 'Personal'],
            ['label' => 'Notes', 'route' => 'notes.index', 'active' => 'notes.*', 'icon' => 'fa-regular fa-note-sticky'],
        ];
    @endphp
    
    
            <!--['label' => 'Leads', 'route' => 'leads.index', 'active' => 'leads.*', 'icon' => 'fa-solid fa-people-group'],-->
            <!--['label' => 'Price Calculator', 'route' => 'price-calculator.index', 'active' => 'price-calculator.*', 'icon' => 'fa-solid fa-calculator'],-->

    {{-- Sidebar --}}
    <aside class="sidebar" id="sidebar" aria-label="Main sidebar">
        <div class="sidebar-logo">
            <div>
                <div class="sidebar-logo-text sidebar-text">{{ $officeBrand['name'] ?? 'MissPack' }}</div>
                <div class="sidebar-logo-sub sidebar-text">{{ $officeBrand['tagline'] ?? 'Packed Perfect' }}</div>
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

            <span class="topbar-title">@yield('page-title', 'Office')</span>

            {{-- A page's primary action belongs beside its title, not buried in
                 a toolbar: it stays reachable however far the list scrolls. --}}
            @hasSection('page-actions')
                <div class="topbar-page-actions">@yield('page-actions')</div>
            @endif

            <div class="topbar-spacer"></div>

            <div class="topbar-actions">
                @if (auth()->user()?->isAdmin())
                    <button type="button" class="topbar-btn" data-gs-open aria-label="Search" title="Search (Ctrl+/Cmd+K)">
                        <i class="fas fa-search"></i>
                    </button>
                @endif

                {{-- Notes, beside Search: it is the one screen both halves of the
                     application may open — an employee's menu and the office's
                     both carry it — and a private note is what you reach for
                     without leaving the record you are on. The control wears the
                     shell's own topbar button, so it is the same height, radius
                     and focus ring as its neighbours, and an icon-only control
                     names itself in words: the label is the tooltip. It marks
                     itself as the current page, which is how a reader who came in
                     from a sidebar label knows where they are. --}}
                <a class="topbar-btn" href="{{ route('notes.index') }}" aria-label="Notes"
                    title="Notes — private to this login"
                    @if (request()->routeIs('notes.*')) aria-current="page" @endif>
                    <i class="fa-regular fa-note-sticky" aria-hidden="true"></i>
                </a>

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

                @if (auth()->user()?->isAdmin())
                    <button type="button" class="topbar-btn" data-ob-open aria-label="Office briefings">
                        <i class="fas fa-bell"></i>
                        <span class="topbar-badge" data-ob-badge @if (($officeBriefing['unread'] ?? 0) === 0) hidden @endif>{{ ($officeBriefing['unread'] ?? 0) > 9 ? '9+' : ($officeBriefing['unread'] ?? 0) }}</span>
                    </button>
                @endif

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

    @if (auth()->user()?->isAdmin())
        @include('layouts.partials.global-search')
        @include('layouts.partials.office-briefing')
    @endif

    {{-- Vendor Scripts --}}
    <script src="{{ asset('assets/vendor/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/select2/js/select2.min.js') }}"></script>

    {{-- App Scripts --}}
    <script src="{{ $assetVer('assets/js/master-alert.js') }}"></script>
    <script src="{{ $assetVer('assets/js/master-selects.js') }}"></script>
    <script src="{{ $assetVer('assets/js/money.js') }}"></script>
    <script src="{{ $assetVer('assets/js/app-layout.js') }}"></script>
    <script src="{{ $assetVer('assets/js/global-search.js') }}"></script>
    <script src="{{ $assetVer('assets/js/office-briefing.js') }}"></script>
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
