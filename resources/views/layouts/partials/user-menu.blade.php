{{--
    The user menu — one menu, two surfaces.

    The shell shows the person twice: as a chip in the top bar and as a card at
    the foot of the sidebar. Both were labels: for an employee the avatar was a
    link to `/my/profile`, and for the office account it was a `<div>` with a
    comment explaining that the office has no page of its own, "so it keeps the
    same look without pretending to be a link". A menu with no items is not a
    menu; the same markup is rendered twice, with `$surface` saying only how the
    trigger is dressed.

    The panel is the shared `.master-dropdown` (app-layout.js moves it to <body>,
    measures the trigger, flips it up when the sidebar has no room below, and
    closes it on Escape or an outside click), so this partial adds items and one
    header — not a second menu implementation.
--}}
@php
    $menuUser = Auth::user();
    $menuEmployee = $menuUser && $menuUser->isEmployee();
    $menuRole = $menuEmployee
        ? ($menuUser->designation ?: 'Employee')
        : 'Administrator';
    /* An employee's record lives in their workspace; the office has no page
       there and reads their own record in the users module instead. Asking by
       role is the difference between a door and a 302. */
    $menuRecord = $menuEmployee ? route('my.dashboard') : route('users.show', $menuUser);
@endphp

<div class="master-dropdown user-menu is-{{ $surface ?? 'topbar' }}">
    <button type="button" class="master-dropdown-toggle {{ ($surface ?? 'topbar') === 'sidebar' ? 'user-menu-card' : 'topbar-user-btn' }}"
        aria-haspopup="true" aria-expanded="false"
        aria-label="Your account — {{ $menuUser->name ?? 'Admin' }}" title="Your account">
        <span class="user-avatar-sm topbar-avatar">{{ substr($menuUser->name ?? 'A', 0, 1) }}</span>
        <span class="user-menu-label sidebar-text">
            <span class="topbar-user-name">{{ $menuUser->name ?? 'Admin' }}</span>
            <span class="user-menu-role">{{ $menuRole }}</span>
        </span>
        <i class="fas fa-chevron-{{ ($surface ?? 'topbar') === 'sidebar' ? 'right' : 'down' }} user-menu-caret" aria-hidden="true"></i>
    </button>

    <div class="master-dropdown-menu user-menu-panel" role="menu" aria-label="Your account">
        <div class="user-menu-head">
            <span class="user-avatar-sm user-menu-head-avatar" aria-hidden="true">{{ substr($menuUser->name ?? 'A', 0, 1) }}</span>
            <span class="user-menu-head-text">
                <strong>{{ $menuUser->name ?? 'Admin' }}</strong>
                <span class="user-menu-head-role">{{ $menuRole }}</span>
                <span class="user-menu-head-mail">{{ $menuUser->email }}</span>
            </span>
        </div>

        <a href="{{ route('account.index') }}" role="menuitem">
            <i class="fa-regular fa-id-card" aria-hidden="true"></i> My account
        </a>
        <a href="{{ $menuRecord }}" role="menuitem">
            <i class="fa-solid fa-address-book" aria-hidden="true"></i> {{ $menuEmployee ? 'My workspace' : 'My record' }}
        </a>

        @if ($menuEmployee)
            <a href="{{ route('my.salary') }}" role="menuitem">
                <i class="fa-solid fa-indian-rupee-sign" aria-hidden="true"></i> Salary &amp; payslips
            </a>
            <a href="{{ route('my.documents') }}" role="menuitem">
                <i class="fa-regular fa-folder-open" aria-hidden="true"></i> My documents
            </a>
        @endif

        @if (! $menuEmployee)
            <a href="{{ route('settings.index') }}" role="menuitem">
                <i class="fas fa-sliders" aria-hidden="true"></i> Settings
            </a>
        @endif

        <a href="{{ route('account.index') }}#password" role="menuitem">
            <i class="fa-solid fa-key" aria-hidden="true"></i> Change password
        </a>

        <div class="user-menu-sep" role="separator"></div>

        {{-- The top bar's theme button is hidden on a phone, so this is the only
             switch there is on small screens — and a menu is where a person
             looks for "a setting about me". --}}
        <form method="POST" action="{{ route('theme.toggle') }}">
            @csrf
            <button type="submit" role="menuitem">
                @if (session('theme', 'light') === 'light')
                    <i class="fas fa-moon" aria-hidden="true"></i> Switch to dark
                @else
                    <i class="fas fa-sun" aria-hidden="true"></i> Switch to light
                @endif
            </button>
        </form>

        <div class="user-menu-sep" role="separator"></div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="danger" role="menuitem">
                <i class="fas fa-right-from-bracket" aria-hidden="true"></i> Sign out
            </button>
        </form>
    </div>
</div>
