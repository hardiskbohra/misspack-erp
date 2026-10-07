{{--
    The settings rail — one definition, every settings screen.

    This is the module's submenu: the owner ("Settings"), then one link per area,
    in the order `SettingsDirectory` lists them. `$current` names the area the
    page belongs to, so exactly one item is marked — a rail that lights two items
    is a menu that cannot say where you are — and the hub marks none, because the
    hub is not an area.

    The list comes from the directory rather than from markup here: an area added
    to the directory appears on every settings screen at once, and one that is
    renamed is renamed everywhere. Nothing on this page knows what a setting is.

    Small screens: the same links, laid out as a horizontal strip instead of a
    column (the sheet does that, not this file), because the rail is navigation
    and navigation is never `desktop-only`.
--}}
@php
    /* The one list. `AppServiceProvider` shares it with every settings view, so
       no screen has to remember to pass it; the directory is asked directly as
       well, because a rail that renders empty because a page forgot an argument
       is a menu that says the module has no areas. */
    $areas = $areas ?? app(\App\Services\SettingsDirectory::class)->areas();
    $current = $current ?? null;
@endphp

<nav class="set-nav master-card master-card--flat" aria-label="Settings areas">
    <a class="set-nav-home {{ $current === null ? 'is-active' : '' }}" href="{{ route('settings.index') }}"
        @if ($current === null) aria-current="page" @endif>
        <span class="set-nav-home-mark" aria-hidden="true"><i class="fas fa-sliders"></i></span>
        <span class="set-nav-home-text">
            <span class="set-nav-home-title">Settings</span>
            <span class="set-nav-home-sub">Every module's rules in one place</span>
        </span>
    </a>

    <div class="set-nav-list">
        @foreach ($areas as $area)
            <a class="set-nav-link {{ $current === $area['key'] ? 'is-active' : '' }}"
                href="{{ route($area['route']) }}"
                @if ($current === $area['key']) aria-current="page" @endif>
                <i class="{{ $area['icon'] }}" aria-hidden="true"></i>
                <span class="set-nav-label">{{ $area['label'] }}</span>
            </a>
        @endforeach
    </div>
</nav>
