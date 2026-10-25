@props([
    'id',
    'title' => 'Details',
    'subtitle' => null,
    'eyebrow' => 'Quick view',
    'size' => 'medium',
])

@php
    $drawerSize = in_array($size, ['narrow', 'medium', 'wide'], true) ? $size : 'medium';
@endphp

<div class="core-drawer-layer" id="{{ $id }}-layer" data-drawer-layer hidden aria-hidden="true">
    <button type="button" class="core-drawer-backdrop" data-drawer-close tabindex="-1" aria-label="Close details"></button>
    <aside id="{{ $id }}" class="core-drawer core-drawer--{{ $drawerSize }}" role="dialog" aria-modal="true"
        aria-labelledby="{{ $id }}-title" aria-describedby="{{ $id }}-description" tabindex="-1">
        <header class="core-drawer-header">
            <div class="core-drawer-heading">
                <p class="core-drawer-eyebrow" data-drawer-eyebrow @if (! $eyebrow) hidden @endif>{{ $eyebrow }}</p>
                <h2 class="core-drawer-title" id="{{ $id }}-title" data-drawer-heading>{{ $title }}</h2>
                <p class="core-drawer-subtitle" id="{{ $id }}-description" data-drawer-subtitle @if (! $subtitle) hidden @endif>{{ $subtitle }}</p>
            </div>
            <button type="button" class="core-drawer-close" data-drawer-close aria-label="Close details">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" aria-hidden="true" focusable="false"><path d="m6 6 12 12M18 6 6 18" /></svg>
            </button>
        </header>
        <div class="core-drawer-content">
            {{ $slot }}
        </div>
        @isset($footer)
            <div class="core-drawer-footer">{{ $footer }}</div>
        @endisset
    </aside>
</div>
