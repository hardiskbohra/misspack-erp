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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    {{-- Apply saved desktop sidebar state before CSS paints --}}
    <script>
        (function () {
            try {
                var isMobileOrTablet = window.matchMedia('(max-width: 1199px)').matches;

                if (!isMobileOrTablet && localStorage.getItem('sidebarCollapsed') === '1') {
                    document.documentElement.classList.add('sidebar-collapsed');
                }
            } catch (e) {}
        })();
    </script>

    {{-- App CSS --}}
    <link rel="stylesheet" href="{{ asset('assets/css/core.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app-layout.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/master-index.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/master-show.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/master-form.css') }}">

    @stack('styles')
</head>
<body>
    @php
        $sidebarItems = [
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
            ['label' => 'Cashflow', 'route' => 'cashflows.index', 'active' => 'cashflows.*', 'icon' => 'fa-solid fa-scale-balanced'],
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
                    <a href="{{ route($item['route']) }}"
                       class="sidebar-item {{ request()->routeIs($item['active']) ? 'active' : '' }}">
                        <i class="{{ $item['icon'] }}"></i>
                        <span class="sidebar-text">{{ $item['label'] }}</span>
                    </a>
                @endif
            @endforeach
        </nav>

        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="user-avatar-sm">{{ substr(Auth::user()->name ?? 'A', 0, 1) }}</div>

                <div class="sidebar-user-info sidebar-text">
                    <div class="user-name">{{ Auth::user()->name ?? 'Admin' }}</div>
                    <div class="user-role">Administrator</div>
                </div>

                <form method="POST" action="{{ route('logout') }}" class="sidebar-logout-form">
                    @csrf
                    <button type="submit" class="sidebar-logout-btn" title="Logout" aria-label="Logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </button>
                </form>
            </div>
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

                <div class="topbar-user-btn">
                    <div class="user-avatar-sm topbar-avatar">
                        {{ substr(Auth::user()->name ?? 'A', 0, 1) }}
                    </div>
                    <span class="topbar-user-name desktop-only">{{ Auth::user()->name ?? 'Admin' }}</span>
                </div>
            </div>
        </header>

        <main class="page-content">
            @yield('content')
        </main>
    </div>

    {{-- Vendor Scripts --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    {{-- App Scripts --}}
    <script src="{{ asset('assets/js/app-layout.js') }}"></script>

    {{-- Flash Messages --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            @if(session('success'))
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: @json(session('success')),
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
            @endif

            @if(session('error'))
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: @json(session('error')),
                    confirmButtonColor: '#ef4770'
                });
            @endif

            @if($errors->any())
                var validationErrors = @json($errors->all());
                Swal.fire({
                    icon: 'error',
                    title: 'Validation Error',
                    html: '<ul style="text-align:left;margin:0;padding-left:18px;">' +
                        validationErrors.map(function (error) {
                            return '<li>' + escapeHtml(error) + '</li>';
                        }).join('') +
                        '</ul>',
                    confirmButtonColor: '#ef4770'
                });
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
