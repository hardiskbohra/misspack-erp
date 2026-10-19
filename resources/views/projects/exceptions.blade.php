@extends('layouts.app')

@section('title', 'Project exceptions')
@section('page-title', 'Exceptions and escalation')

@section('page-actions')
    <form method="POST" action="{{ route('projects.exceptions.refresh') }}">@csrf<button class="master-btn master-btn-soft" type="submit"><i class="fa-solid fa-rotate" aria-hidden="true"></i> Recheck sources</button></form>
    <a class="master-btn master-btn-primary" href="{{ route('projects.work-queue.index',['scope'=>'team']) }}"><i class="fa-solid fa-list-check" aria-hidden="true"></i> Coordinator queue</a>
@endsection

@section('content')
@push('styles')<link rel="stylesheet" href="{{ $assetVer('assets/css/projects.css') }}">@endpush
@php
    $activeFilters = ($severity!=='all'?1:0)+($category!=='all'?1:0)+(filled($ownerId)?1:0)+(filled($search)?1:0);
    $removeFilter = fn($key) => route('projects.exceptions.index', collect(request()->except([$key,'page']))->reject(fn($value)=>$value===null||$value===''||$value==='all')->all());
@endphp
<div class="project project-exceptions master-list">
    <div class="master-stats project-exception-stats" aria-label="Project exception overview">
        <article class="master-stat master-stat--flat red"><span class="icon"><i class="fa-solid fa-triangle-exclamation"></i></span><div><p class="master-stat-title">Active exceptions</p><p class="master-stat-value">{{ number_format($counts['active']) }}</p><p class="master-sub">Source conditions still active</p></div></article>
        <article class="master-stat master-stat--flat red"><span class="icon"><i class="fa-solid fa-fire"></i></span><div><p class="master-stat-title">Critical</p><p class="master-stat-value">{{ number_format($counts['critical']) }}</p><p class="master-sub">Immediate management attention</p></div></article>
        <article class="master-stat master-stat--flat orange"><span class="icon"><i class="fa-solid fa-bell"></i></span><div><p class="master-stat-title">Unacknowledged</p><p class="master-stat-value">{{ number_format($counts['unacknowledged']) }}</p><p class="master-sub">No response plan recorded</p></div></article>
        <article class="master-stat master-stat--flat purple"><span class="icon"><i class="fa-solid fa-arrow-up-right-dots"></i></span><div><p class="master-stat-title">Escalated</p><p class="master-stat-value">{{ number_format($counts['escalated']) }}</p><p class="master-sub">Manager or SLA escalation</p></div></article>
        <article class="master-stat master-stat--flat orange"><span class="icon"><i class="fa-solid fa-calendar-xmark"></i></span><div><p class="master-stat-title">Review overdue</p><p class="master-stat-value">{{ number_format($counts['review_overdue']) }}</p><p class="master-sub">Follow-up deadline passed</p></div></article>
    </div>

    <section class="master-card master-card--flat" aria-label="Filter project exceptions">
        <nav class="master-list-chips" aria-label="Filter exception status">
            @foreach(['active'=>'Active','open'=>'Unacknowledged','acknowledged'=>'In follow-up','resolved'=>'Resolved','all'=>'All history'] as $key=>$label)
                <a class="master-list-chip {{ $status===$key?'is-active':'' }}" href="{{ route('projects.exceptions.index',array_merge(request()->except(['status','page']),['status'=>$key])) }}">{{ $label }}@if(isset($counts[$key]))<span class="master-list-chip-count">{{ $counts[$key] }}</span>@endif</a>
            @endforeach
        </nav>
        <form method="GET" action="{{ route('projects.exceptions.index') }}">
            <input type="hidden" name="status" value="{{ $status }}">
            <div class="master-filter-row core-filter-toolbar">
                <label class="master-search"><span aria-hidden="true"><i class="fa-solid fa-magnifying-glass"></i></span><input class="master-input" type="search" name="search" value="{{ $search }}" placeholder="Project, exception or source reason" aria-label="Search project exceptions"></label>
                <x-filter-trigger drawer="projectExceptionFilters" label="Filters" :count="$activeFilters" />
            </div>
            <x-drawer id="projectExceptionFilters" title="Filter exceptions" eyebrow="Exception register" subtitle="Filter the current source conditions and their preserved history." size="medium">
                <section class="core-drawer-section"><h3 class="core-drawer-section-title">Exception source</h3><div class="core-drawer-fields">
                    <label class="master-field"><span class="master-label">Severity</span><select class="master-select" name="severity"><option value="all">Every severity</option><option value="critical" @selected($severity==='critical')>Critical</option><option value="warning" @selected($severity==='warning')>Warning</option></select></label>
                    <label class="master-field"><span class="master-label">Category</span><select class="master-select" name="category">@foreach($categoryOptions as $key=>$label)<option value="{{ $key }}" @selected($category===$key)>{{ $label }}</option>@endforeach</select></label>
                    <label class="master-field"><span class="master-label">Follow-up owner</span><select class="master-select" name="owner_id"><option value="">Every owner</option>@foreach($owners as $user)<option value="{{ $user->id }}" @selected((string)$ownerId===(string)$user->id)>{{ $user->name ?: $user->email }}</option>@endforeach</select></label>
                </div></section>
                <x-slot:footer><a class="master-btn master-btn-soft" href="{{ route('projects.exceptions.index',['status'=>$status]) }}">Reset</a><button class="master-btn master-btn-primary" type="submit"><i class="fa-solid fa-filter"></i> Apply filters</button></x-slot:footer>
            </x-drawer>
        </form>
    </section>

    @if($activeFilters)
        <div class="master-list-applied"><span>Showing</span>
            @if(filled($search))<a href="{{ $removeFilter('search') }}">Search: {{ $search }} <i class="fa-solid fa-xmark"></i></a>@endif
            @if($severity!=='all')<a href="{{ $removeFilter('severity') }}">{{ ucfirst($severity) }} <i class="fa-solid fa-xmark"></i></a>@endif
            @if($category!=='all')<a href="{{ $removeFilter('category') }}">{{ $categoryOptions[$category] ?? $category }} <i class="fa-solid fa-xmark"></i></a>@endif
            @if(filled($ownerId))<a href="{{ $removeFilter('owner_id') }}">Owner filter <i class="fa-solid fa-xmark"></i></a>@endif
            <a class="master-list-applied-clear" href="{{ route('projects.exceptions.index',['status'=>$status]) }}">Clear all filters</a>
        </div>
    @endif

    <section class="master-card master-card--flat project-exception-card" aria-labelledby="exceptionRegisterTitle">
        <div class="master-section-head"><div><h2 class="master-section-title" id="exceptionRegisterTitle">Exception register</h2><p class="master-sub">Acknowledgment records a response plan. Only disappearance of the underlying source condition resolves an exception.</p></div><span class="master-badge">{{ number_format($exceptions->total()) }} records</span></div>
        <div class="project-exception-list">
            @forelse($exceptions as $exception)
                <article class="project-exception-item severity-{{ $exception->severity }} {{ $focus===$exception->id?'is-focused':'' }}" id="exception-{{ $exception->id }}">
                    <div class="project-exception-heading">
                        <div class="project-exception-badges"><span class="master-badge">{{ $exception->severity==='critical'?'Critical':'Warning' }}</span><span class="master-badge">{{ $exception->statusLabel() }}</span>@if($exception->escalation_level)<span class="master-badge">Escalation L{{ $exception->escalation_level }}</span>@endif</div>
                        <div><h3>{{ $exception->title }}</h3><p>{{ $exception->summary }}</p></div>
                    </div>
                    <div class="project-exception-context">
                        <span><strong>Source</strong>{{ $exception->categoryLabel() }}</span>
                        <span><strong>Product lane</strong>{{ $exception->product?->product_name ?: 'Project level' }}</span>
                        <span><strong>Owner</strong>{{ $exception->owner?->name ?: 'Unassigned' }}</span>
                        <span><strong>{{ $exception->status==='acknowledged'?'Next review':'Response due' }}</strong>{{ ($exception->status==='acknowledged'?$exception->next_review_at:$exception->due_at)?->format('d M Y, H:i') ?: 'Not set' }}@if($exception->isFollowUpOverdue())<em>Overdue</em>@endif</span>
                    </div>
                    @if($exception->response_plan)<div class="project-exception-plan"><strong>Current response plan</strong><p>{{ $exception->response_plan }}</p></div>@endif
                    <div class="project-exception-actions">
                        <a class="master-btn master-btn-soft master-btn-sm" href="{{ route('projects.show',['project'=>$exception->project,'tab'=>'workflow']) }}">Open workflow</a>
                        @if($exception->status!=='resolved')
                            <details><summary class="master-btn master-btn-light master-btn-sm">{{ $exception->status==='acknowledged'?'Update follow-up':'Acknowledge' }}</summary><form method="POST" action="{{ route('projects.exceptions.acknowledge',$exception) }}" class="project-exception-action-form">@csrf @method('PATCH')
                                <label class="master-field"><span class="master-label">Follow-up owner</span><select class="master-select" name="owner_id" required><option value="">Select owner</option>@foreach($owners as $user)<option value="{{ $user->id }}" @selected((int)$exception->owner_id===$user->id)>{{ $user->name ?: $user->email }}</option>@endforeach</select></label>
                                <label class="master-field"><span class="master-label">Next review</span><input class="master-input" type="datetime-local" name="next_review_at" value="{{ $exception->next_review_at?->format('Y-m-d\TH:i') }}" required></label>
                                <label class="master-field project-exception-action-note"><span class="master-label">Response plan and evidence</span><textarea class="master-textarea" name="response_plan" rows="3" minlength="10" maxlength="2000" required>{{ $exception->response_plan }}</textarea></label>
                                <button class="master-btn master-btn-primary master-btn-sm" type="submit">Save response plan</button>
                            </form></details>
                            @if(auth()->user()?->isAdmin() && $exception->escalation_level<2)<details><summary class="master-btn master-btn-danger master-btn-sm">Escalate</summary><form method="POST" action="{{ route('projects.exceptions.escalate',$exception) }}" class="project-exception-action-form">@csrf @method('PATCH')
                                <label class="master-field"><span class="master-label">Next management review</span><input class="master-input" type="datetime-local" name="next_review_at" required></label>
                                <label class="master-field project-exception-action-note"><span class="master-label">Escalation reason</span><textarea class="master-textarea" name="reason" rows="3" minlength="10" maxlength="2000" required></textarea></label>
                                <button class="master-btn master-btn-danger master-btn-sm" type="submit">Escalate to level {{ $exception->escalation_level+1 }}</button>
                            </form></details>@endif
                        @endif
                        <details class="project-exception-history"><summary>Audit history ({{ $exception->events->count() }})</summary><div>@foreach($exception->events as $event)<p><strong>{{ str($event->event_type)->headline() }}</strong><span>{{ $event->occurred_at?->format('d M Y, H:i') }} · {{ $event->actor?->name ?: 'System' }} · {{ ucfirst($event->severity) }}@if($event->escalation_level) · L{{ $event->escalation_level }}@endif</span>@if($event->note)<em>{{ $event->note }}</em>@endif</p>@endforeach</div></details>
                    </div>
                </article>
            @empty
                <div class="master-empty"><i class="fa-solid fa-circle-check"></i><h3>No exceptions match</h3><p>Current source controls are clear for this view. Resolved history remains available from the status filters.</p></div>
            @endforelse
        </div>
        @if($exceptions->hasPages())<div class="master-pagination">{{ $exceptions->links() }}</div>@endif
    </section>
</div>
@endsection
