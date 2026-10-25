@extends('layouts.app')

@section('page-title', 'Module Dashboards')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/module-dashboard.css') }}">
@endpush

@section('content')
<div class="module-dashboard">
    <section class="module-directory-grid" aria-label="Available module dashboards">
        @forelse($modules as $key => $module)
            <a class="core-card module-directory-card" href="{{ route('dashboards.show', ['module' => $key] + request()->only(['period_type', 'year', 'month', 'quarter', 'half', 'start_date', 'end_date', 'currency'])) }}">
                <span class="module-directory-icon"><i class="{{ $module['icon'] }}" aria-hidden="true"></i></span>
                <span><strong>{{ $module['label'] }}</strong><small>{{ $module['description'] }}</small></span>
                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </a>
        @empty
            <div class="core-card module-directory-empty master-empty-state">
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                <strong>No module dashboards available</strong>
                <p>Ask an administrator to grant access to the modules you need.</p>
            </div>
        @endforelse
    </section>
    <section class="core-card module-directory-note">
        <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
        <div><strong>Consistent reporting controls</strong><span>Every module supports monthly, quarterly, half-yearly and yearly views with a like-for-like prior-period comparison.</span></div>
    </section>
</div>
@endsection
