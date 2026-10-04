@extends('client_portal.layouts.app')

@section('title', 'Projects')
@section('page-title', 'Projects')

@section('content')
<div class="master-header" style="padding:5px;">
    <div>
        <h1>Projects</h1>
        <p style="font-size:16px;font-weight:500;">Only projects published by MissPack are visible here.</p>
    </div>
</div>
<div class="projects-list">
    @forelse($projects as $project)
        @php
            $statusClass = 'projects-chip-status-' . $project->status;
        @endphp
        <div class="projects-card projects-project-card" style="{{ $project->progress_percent == 100 ? 'background:#10b98150' : '' }}; line-height:1.1;">
            <div class="projects-top-bar">
                <a href="{{ route('client-portal.projects.show', $project->id) }}" style="text-decoration:none;">
                
                    <div class="projects-project-top">
                        <div>
                            <div class="projects-number">{{ $project->project_number }}</div>
                
                            <h3>{{ $project->name }}</h3>
                
                            <div class="projects-meta-row">
                                <span>
                                    <i class="fa-solid fa-user-check"></i>
                                    MissPack team
                                </span>
                                <span>
                                    <i class="fa-solid fa-calendar-days"></i>
                                    Start: {{ optional($project->start_date)->format('d M Y') ?: 'No Start' }}
                                </span>
                                <!--<span>-->
                                <!--    <i class="fa-solid fa-calendar-days"></i>-->
                                <!--    Target: {{ optional($project->target_date)->format('d M Y') ?: 'No target' }}-->
                                <!--</span>-->
                            </div>
                        </div>
                    </div>
                </a>
            
                <div class="projects-progress-wrap">
                    <div class="projects-progress-text">
                        <span>{{ $project->stageLabel() }}</span>
                        <strong>{{ $project->progress_percent }}%</strong>
                    </div>
            
                    <div class="projects-progress">
                        <span style="width: {{ $project->progress_percent }}%"></span>
                    </div>
                </div>
            
            </div>

            <div class="projects-mini-grid cp-project-safe-metrics">
                <div>
                    <span>Started</span>
                    <h3>{{ optional($project->start_date)->format('d M Y') ?: 'Not set' }}</h3>
                </div>
                <div>
                    <span>Target</span>
                    <h3>{{ optional($project->target_date)->format('d M Y') ?: 'Not set' }}</h3>
                </div>
                <div>
                    <span>Current stage</span>
                    <h3>{{ $project->stageLabel() }}</h3>
                </div>
            </div>

            <div class="projects-project-footer">
                <div class="projects-footer-tags">
                    <span class="projects-chip {{ $statusClass }}">{{ $project->statusLabel() }}</span>
                </div>
                <div class="projects-footer-actions">
                    <a href="{{ route('client-portal.projects.show', $project->id) }}"
                        class="master-btn master-btn-primary master-btn-sm">Open</a>
                </div>
            </div>
        </div>
    @empty
        <div class="projects-empty" style="line-height:1.5;">
            <div class="projects-empty-icon"><i class="fa-solid fa-briefcase"></i></div>
            <h3>No shared projects yet</h3>
            <p>Projects published by MissPack will appear here. If you expected to see one, our support team can help.</p>
            <a class="master-btn master-btn-primary" href="{{ route('client-portal.support.index') }}">Contact support</a>
        </div>
    @endforelse
</div>
<x-pagination :items="$projects" />


@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/client-portal-projects.css') }}">
@endpush
@endsection
