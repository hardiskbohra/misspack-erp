@extends('layouts.app')

@section('title', 'Office services')
@section('page-title', 'Office services')

@section('page-actions')
    <a href="{{ route('office-services.create') }}" class="master-btn master-btn-primary">
        <i class="fas fa-plus" aria-hidden="true"></i> Add service
    </a>
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/office-services.css') }}">
@endpush

@php
    $chipUrl = function (string $key) {
        $keep = collect(request()->except([$key, 'page']))
            ->reject(fn ($value) => $value === null || $value === '' || $value === 'all');
        return route('office-services.index', $keep->all());
    };
@endphp

<div class="office-services master-list">
    <div class="master-stats desktop-only">
        <div class="master-stat master-stat--flat blue">
            <span class="icon" aria-hidden="true">🧹</span>
            <div>
                <p class="master-stat-title">Services</p>
                <p class="master-stat-value">{{ $stats['total'] }}</p>
                <p class="master-sub">{{ $stats['active'] }} active</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat teal">
            <span class="icon" aria-hidden="true">🏷</span>
            <div>
                <p class="master-stat-title">Classes</p>
                <p class="master-stat-value">{{ $stats['classes'] }}</p>
                <p class="master-sub">housekeeping, water, flowers…</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat green">
            <span class="icon" aria-hidden="true">₹</span>
            <div>
                <p class="master-stat-title">Monthly retainers</p>
                <p class="master-stat-value">{{ \App\Helpers\CommonHelper::indianCurrency($stats['retainers']) }}</p>
                <p class="master-sub">active monthly agreements</p>
            </div>
        </div>
    </div>

    <form method="GET" action="{{ route('office-services.index') }}" class="master-list-toolbar" role="search">
        <input class="master-input" type="search" name="search" value="{{ $search }}" placeholder="Search name, number, phone">
        <select class="master-select" name="class" aria-label="Class">
            <option value="all">All classes</option>
            @foreach ($classOptions as $key => $label)
                <option value="{{ $key }}" @selected($class === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select class="master-select" name="status" aria-label="Status">
            <option value="all">All statuses</option>
            @foreach ($statusOptions as $key => $label)
                <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="master-btn master-btn-light">Filter</button>
    </form>

    @if ($class !== 'all' || $status !== 'all' || $search)
        <div class="master-list-chips">
            @if ($search)
                <a class="master-list-chip" href="{{ $chipUrl('search') }}">“{{ $search }}” &times;</a>
            @endif
            @if ($class !== 'all')
                <a class="master-list-chip" href="{{ $chipUrl('class') }}">{{ $classOptions[$class] ?? $class }} &times;</a>
            @endif
            @if ($status !== 'all')
                <a class="master-list-chip" href="{{ $chipUrl('status') }}">{{ $statusOptions[$status] ?? $status }} &times;</a>
            @endif
        </div>
    @endif

    <div class="master-card master-table-wrap">
        <table class="master-table">
            <thead>
                <tr>
                    <th>Number</th>
                    <th>Name</th>
                    <th>Class</th>
                    <th>Contact</th>
                    <th>Retainer</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($services as $row)
                    <tr>
                        <td><a href="{{ route('office-services.show', $row) }}">{{ $row->service_number }}</a></td>
                        <td>
                            <a href="{{ route('office-services.show', $row) }}">{{ $row->name }}</a>
                            @if ($row->contact_name)
                                <div class="master-sub">{{ $row->contact_name }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="ofs-class ofs-class--{{ $row->service_class }}">{{ $row->classLabel() }}</span>
                        </td>
                        <td>{{ $row->phone ?: '—' }}</td>
                        <td>
                            @if ($row->retainer_amount)
                                {{ \App\Helpers\CommonHelper::indianCurrency($row->retainer_amount) }}
                                <div class="master-sub">{{ $row->cycleLabel() }}</div>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $row->statusLabel() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="master-list-empty">
                            No office services yet. Add the maid, water supplier, florist and the rest here — not under Users or Vendors.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $services->links() }}
</div>
@endsection
