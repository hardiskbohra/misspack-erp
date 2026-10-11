@extends('client_portal.layouts.app')

@section('title', 'Projects')
@section('page-title', 'Projects')

@section('content')
<div class="cp-page-head">
    <div><p class="cp-eyebrow">Delivery workspace</p><h1>Projects</h1><p>Follow the progress, products, approvals, files and activity for projects shared with your team.</p></div>
</div>

<section class="cp-card cp-filter-card">
    <form method="GET" action="{{ route('client-portal.projects.index') }}">
        <div class="core-filter-toolbar">
            <div class="master-field"><label class="master-label" for="project-search">Search projects</label><input class="master-input" id="project-search" name="search" value="{{ $search }}" placeholder="Project number, name or stage"></div>
            <x-filter-trigger drawer="portalProjectFiltersDrawer" :count="(filled($search) ? 1 : 0) + ($status !== 'all' ? 1 : 0)" />
        </div>
        <x-drawer id="portalProjectFiltersDrawer" title="Filter projects" eyebrow="Project filters" subtitle="Narrow published projects by status." size="medium">
            <section class="core-drawer-section"><h3 class="core-drawer-section-title">Project status</h3><div class="core-drawer-fields"><div class="master-field"><label class="master-label" for="project-status">Status</label><select class="master-select" id="project-status" name="status"><option value="all">All statuses</option>@foreach($statusOptions as $key => $label)<option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>@endforeach</select></div></div></section>
            <x-slot:footer><a class="master-btn master-btn-soft" href="{{ route('client-portal.projects.index') }}">Reset</a><button class="master-btn master-btn-primary" type="submit">Apply filters</button></x-slot:footer>
        </x-drawer>
    </form>
</section>

<div class="cp-project-list">
    @forelse($projects as $project)
        <article class="cp-card cp-project-card {{ (int)$project->progress_percent === 100 ? 'is-complete' : '' }}">
            <div class="cp-project-card-main">
                <span class="cp-project-icon"><i class="fa-solid fa-briefcase"></i></span>
                <div class="cp-project-card-copy">
                    <div class="cp-project-card-top"><div><span class="cp-product-number">{{ $project->project_number }}</span><h2><a href="{{ route('client-portal.projects.show', $project) }}">{{ $project->name }}</a></h2></div></div>
                    <div class="cp-project-meta"><span><i class="fa-regular fa-calendar"></i> Started {{ optional($project->start_date)->format('d M Y') ?: 'not set' }}</span><span><i class="fa-solid fa-bullseye"></i> Target {{ optional($project->target_date)->format('d M Y') ?: 'not set' }}</span><span><i class="fa-solid fa-layer-group"></i> {{ $project->stageLabel() }}</span></div>
                    <div class="cp-project-progress"><div><span>{{ $project->stageLabel() }}</span><strong>{{ (int)$project->progress_percent }}%</strong></div><div class="cp-progress-track"><span style="width:{{ (int)$project->progress_percent }}%"></span></div></div>
                </div>
            </div>
            <div class="cp-project-card-action"><span class="cp-badge status-{{ $project->status }}">{{ $project->statusLabel() }}</span><a class="master-btn master-btn-soft" href="{{ route('client-portal.projects.show', $project) }}">Open project <i class="fa-solid fa-arrow-right"></i></a></div>
        </article>
    @empty
        <div class="cp-card cp-empty cp-empty-spacious"><i class="fa-solid fa-briefcase"></i><strong>No shared projects found</strong><span>Published projects will appear here. Try changing your filters or ask the MissPack team for help.</span><a class="master-btn master-btn-primary" href="{{ route('client-portal.support.index') }}">Contact support</a></div>
    @endforelse
</div>
<x-pagination :items="$projects" />
@endsection
