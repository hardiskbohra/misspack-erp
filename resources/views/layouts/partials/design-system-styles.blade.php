{{-- Use Vite when available; standalone/auth screens may force the checked-in fallback
     so a stale local Vite marker cannot leave a security-critical page unstyled. --}}
@php($useStaticDesignSystem = ($forceStatic ?? false) || (! file_exists(public_path('build/manifest.json')) && ! file_exists(public_path('hot'))))
@if ($useStaticDesignSystem)
    <link rel="stylesheet" href="{{ asset('assets/css/design-system.css') }}?v={{ filemtime(public_path('assets/css/design-system.css')) }}">
@else
    @vite('resources/css/master.css')
@endif
