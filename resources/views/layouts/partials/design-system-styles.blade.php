{{-- Use Vite when available; keep the checked-in CSS fallback for deployments without a frontend build. --}}
@if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
    @vite('resources/css/master.css')
@else
    <link rel="stylesheet" href="{{ asset('assets/css/design-system.css') }}?v={{ filemtime(public_path('assets/css/design-system.css')) }}">
@endif
