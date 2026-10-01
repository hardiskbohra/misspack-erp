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
            $totals = $project->paymentTotals();
            $statusClass = 'projects-chip-status-' . $project->status;
            $healthClass = 'projects-health-' . $project->health;
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
                                    Neha Bohra
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

            <div class="projects-mini-grid">
                <div>
                    <span>Estimated</span>
                    <h3>{{ $project->currency == 'INR' ? '₹' : $project->currency }}
                        {{ number_format((float) $project->estimated_value, 2) }}</h3>
                </div>
                <div>
                    <span>Paid</span>
                    <h3 class="projects-money-green">{{ $project->currency == 'INR' ? '₹' : $project->currency }}
                        {{ number_format($totals['inward'], 2) }}</h3>
                </div>
                <div>
                    <span>Balance</span>
                    <h3>{{ $project->currency == 'INR' ? '₹' : $project->currency }} {{ number_format($totals['outstanding'], 2) }}</h3>
                </div>
            </div>

            <div class="projects-project-footer">
                <div class="projects-footer-tags">
                    <span class="projects-chip {{ $statusClass }}">{{ $project->statusLabel() }}</span>
                    <span class="projects-chip {{ $healthClass }}">{{ $project->healthLabel() }}</span>
                </div>
                <div class="projects-footer-actions">
                    <a href="{{ route('client-portal.projects.show', $project->id) }}"
                        class="projects-btn projects-btn-primary projects-btn-sm">Open</a>
                </div>
            </div>
        </div>
    @empty
        <div class="projects-empty" style="line-height:1.5;">
            <div class="projects-empty-icon"><i class="fa-solid fa-briefcase"></i></div>
            <h3>No projects found</h3>
            <p>Create your first project/deal once a client finalises the quote.</p><br>
            <button type="button" class="projects-btn projects-btn-primary"
                data-open-modal="quickProjectModal">Quick Project</button>
        </div>
    @endforelse
</div>
<x-pagination :items="$projects" />


@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/client-portal-projects.css') }}">
@endpush
@endsection
