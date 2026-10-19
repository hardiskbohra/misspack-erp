@extends('layouts.app')

@section('page-title', 'Module Dashboards')

@section('page-actions')
    <a class="master-btn" href="{{ route('dashboard') }}"><i class="fa-solid fa-gauge-high"></i> ERP overview</a>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/module-dashboard.css') }}">
@endpush

@section('content')
<div class="module-dashboard">
    <section class="module-directory-grid" aria-label="Available module dashboards">
        @foreach($modules as $key => $module)
            <a class="module-directory-card" href="{{ route('dashboards.show', $key) }}">
                <span class="module-directory-icon"><i class="{{ $module['icon'] }}" aria-hidden="true"></i></span>
                <span><strong>{{ $module['label'] }}</strong><small>{{ $module['description'] }}</small></span>
                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </a>
        @endforeach
    </section>
    <section class="module-directory-note">
        <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
        <div><strong>Consistent reporting controls</strong><span>Every module supports monthly, quarterly, half-yearly and yearly views with a like-for-like prior-period comparison.</span></div>
    </section>
</div>
@endsection
