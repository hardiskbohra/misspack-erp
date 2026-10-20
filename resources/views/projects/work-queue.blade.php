@extends('layouts.app')

@section('title', 'Coordinator work queue')
@section('page-title', 'Coordinator work queue')

@section('page-actions')
    <a class="master-btn master-btn-soft" href="{{ route('projects.index') }}"><i class="fa-solid fa-briefcase" aria-hidden="true"></i> Projects</a>
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/projects.css') }}">
@endpush

@php
    $filtersActive = $attention !== 'all' || $due !== 'all' || $stage !== 'all' || filled($search) || ($scope === 'team' && filled($coordinatorId));
    $activeFilterCount = ($attention !== 'all' ? 1 : 0) + ($due !== 'all' ? 1 : 0) + ($stage !== 'all' ? 1 : 0) + (filled($search) ? 1 : 0) + (($scope === 'team' && filled($coordinatorId)) ? 1 : 0);
    $queryWithout = fn (string $key) => route('projects.work-queue.index', collect(request()->except([$key, 'page']))->reject(fn ($value) => $value === null || $value === '' || $value === 'all')->all());
@endphp

<div class="project project-work-queue master-list">
    <div class="master-stats project-queue-stats" aria-label="Current coordinator workload">
        <article class="master-stat master-stat--flat blue"><span class="icon"><i class="fa-solid fa-list-check" aria-hidden="true"></i></span><div><p class="master-stat-title">Current actions</p><p class="master-stat-value">{{ number_format($stats['total']) }}</p><p class="master-sub">One action per active execution lane</p></div></article>
        <article class="master-stat master-stat--flat red"><span class="icon"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i></span><div><p class="master-stat-title">Overdue</p><p class="master-stat-value">{{ number_format($stats['overdue']) }}</p><p class="master-sub">Past the planned completion date</p></div></article>
        <article class="master-stat master-stat--flat orange"><span class="icon"><i class="fa-solid fa-circle-xmark" aria-hidden="true"></i></span><div><p class="master-stat-title">Blocked</p><p class="master-stat-value">{{ number_format($stats['blocked']) }}</p><p class="master-sub">Source control needs intervention</p></div></article>
        <article class="master-stat master-stat--flat purple"><span class="icon"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i></span><div><p class="master-stat-title">Waiting externally</p><p class="master-stat-value">{{ number_format($stats['waiting']) }}</p><p class="master-sub">Client or vendor follow-up</p></div></article>
        <article class="master-stat master-stat--flat teal"><span class="icon"><i class="fa-solid fa-user-slash" aria-hidden="true"></i></span><div><p class="master-stat-title">Unassigned</p><p class="master-stat-value">{{ number_format($stats['unassigned']) }}</p><p class="master-sub">Projects without a coordinator</p></div></article>
    </div>

    <section class="master-card master-card--flat" aria-label="Filter coordinator work">
        <form method="GET" action="{{ route('projects.work-queue.index') }}">
            <div class="master-list-bar">
                <nav class="master-list-chips" aria-label="Work queue scope">
                    <a class="master-list-chip {{ $scope === 'mine' ? 'is-active' : '' }}" href="{{ route('projects.work-queue.index', array_merge(request()->except(['scope','coordinator_id','page']), ['scope'=>'mine'])) }}">My projects</a>
                    <a class="master-list-chip {{ $scope === 'team' ? 'is-active' : '' }}" href="{{ route('projects.work-queue.index', array_merge(request()->except(['scope','page']), ['scope'=>'team'])) }}">Team queue</a>
                </nav>
                <label class="master-search">
                    <span aria-hidden="true"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input class="master-input" type="search" name="search" value="{{ $search }}" placeholder="Project, client, product or action" aria-label="Search coordinator queue">
                </label>
                <x-filter-trigger drawer="projectQueueFilters" :count="$activeFilterCount">Filters</x-filter-trigger>
            </div>
            <input type="hidden" name="scope" value="{{ $scope }}">
            <x-drawer id="projectQueueFilters" title="Filter work queue" eyebrow="Coordinator queue" subtitle="Narrow current execution actions without exposing future blocked steps." size="medium">
                <section class="core-drawer-section">
                    <h3 class="core-drawer-section-title">Attention</h3>
                    <div class="core-drawer-fields">
                        <label class="master-field"><span class="master-label">Needs attention</span><select class="master-select" name="attention">@foreach($attentionOptions as $key=>$label)<option value="{{ $key }}" @selected($attention===$key)>{{ $label }}</option>@endforeach</select></label>
                        <label class="master-field"><span class="master-label">Schedule</span><select class="master-select" name="due">@foreach($dueOptions as $key=>$label)<option value="{{ $key }}" @selected($due===$key)>{{ $label }}</option>@endforeach</select></label>
                        <label class="master-field"><span class="master-label">Workflow stage</span><select class="master-select" name="stage"><option value="all">Every stage</option>@foreach($stageOptions as $key=>$label)<option value="{{ $key }}" @selected($stage===$key)>{{ $label }}</option>@endforeach</select></label>
                        @if($scope === 'team')<label class="master-field"><span class="master-label">Project coordinator</span><select class="master-select" name="coordinator_id"><option value="">Every coordinator</option>@foreach($coordinators as $user)<option value="{{ $user->id }}" @selected((string)$coordinatorId===(string)$user->id)>{{ $user->name ?: $user->email }}</option>@endforeach</select></label>@endif
                    </div>
                </section>
                <x-slot:footer><a class="master-btn master-btn-soft" href="{{ route('projects.work-queue.index',['scope'=>$scope]) }}">Reset</a><button class="master-btn master-btn-primary" type="submit"><i class="fa-solid fa-filter" aria-hidden="true"></i> Apply filters</button></x-slot:footer>
            </x-drawer>
        </form>
    </section>

    @if($filtersActive)
        <div class="master-list-applied" aria-label="Applied work queue filters">
            <span>Showing</span>
            @if(filled($search))<a href="{{ $queryWithout('search') }}">Search: {{ $search }} <i class="fa-solid fa-xmark"></i></a>@endif
            @if($attention!=='all')<a href="{{ $queryWithout('attention') }}">{{ $attentionOptions[$attention] }} <i class="fa-solid fa-xmark"></i></a>@endif
            @if($due!=='all')<a href="{{ $queryWithout('due') }}">{{ $dueOptions[$due] }} <i class="fa-solid fa-xmark"></i></a>@endif
            @if($stage!=='all')<a href="{{ $queryWithout('stage') }}">{{ $stageOptions[$stage] }} <i class="fa-solid fa-xmark"></i></a>@endif
            <a class="master-list-applied-clear" href="{{ route('projects.work-queue.index',['scope'=>$scope]) }}">Clear all filters</a>
        </div>
    @endif

    <section class="master-card master-card--flat project-queue-card" aria-labelledby="queueResultsTitle">
        <div class="master-section-head"><div><h2 class="master-section-title" id="queueResultsTitle">Current execution actions</h2><p class="master-sub">{{ number_format($items->total()) }} actionable lane(s). Product lanes advance independently after shared project prerequisites.</p></div></div>
        <div class="project-queue-list">
            @forelse($items as $item)
                @php
                    $step = $item['step'];
                    $guide = $item['guidance'];
                @endphp
                <article class="project-queue-item is-{{ $item['attention_key'] }}">
                    <div class="project-queue-state"><span class="master-badge">{{ $item['attention'] }}</span><small>{{ $stageOptions[$step->stage_key] ?? str($step->stage_key)->headline() }}</small></div>
                    <div class="project-queue-project"><a href="{{ route('projects.show',['project'=>$item['project'],'tab'=>'workflow']) }}"><strong>{{ $item['project']->project_number }}</strong><span>{{ $item['project']->name }}</span></a><small>{{ $item['project']->client?->company_name ?: 'No client' }} · {{ $item['coordinator']?->name ?: 'Coordinator unassigned' }}</small></div>
                    <div class="project-queue-action"><strong>{{ $step->title }}</strong><span>{{ $item['product']?->product_name ?: 'Project-level control' }}</span>@if($step->blocked_reason)<small>{{ $step->blocked_reason }}</small>@elseif($step->notes)<small>{{ \Illuminate\Support\Str::limit($step->notes,120) }}</small>@endif</div>
                    <div class="project-queue-schedule"><strong>{{ $item['due_date']?->format('d M Y') ?: 'Not scheduled' }}</strong><span>{{ $item['due_source'] ?: 'No action deadline' }} · {{ $item['owner']?->name ?: 'Action owner unassigned' }}</span>@if($step->planned_start_date)<small>Starts {{ $step->planned_start_date->format('d M Y') }}</small>@endif</div>
                    <div class="project-queue-actions"><a class="master-btn master-btn-primary master-btn-sm" href="{{ $guide['url'] }}" @if($guide['newTab']) target="_blank" rel="noopener" @endif>{{ $guide['label'] }}</a><a class="master-btn master-btn-soft master-btn-sm" href="{{ route('projects.show',['project'=>$item['project'],'tab'=>'workflow']) }}#workflow-actions">Open guide</a><details><summary class="master-btn master-btn-light master-btn-sm">Plan</summary><form method="POST" action="{{ route('projects.work-queue.update',[$item['project'],$step]) }}" class="project-queue-plan-form">@csrf @method('PATCH')
                        <label class="master-field"><span class="master-label">Project coordinator</span><select class="master-select" name="assigned_to"><option value="">Unassigned</option>@foreach($coordinators as $user)<option value="{{ $user->id }}" @selected((int)$item['project']->assigned_to===$user->id)>{{ $user->name ?: $user->email }}</option>@endforeach</select></label>
                        <label class="master-field"><span class="master-label">Action owner</span><select class="master-select" name="owner_id"><option value="">Unassigned</option>@foreach($owners as $user)<option value="{{ $user->id }}" @selected((int)$step->owner_id===$user->id)>{{ $user->name ?: $user->email }}</option>@endforeach</select></label>
                        <label class="master-field"><span class="master-label">Planned start</span><input class="master-input" type="date" name="planned_start_date" value="{{ $step->planned_start_date?->format('Y-m-d') }}"></label>
                        <label class="master-field"><span class="master-label">Planned completion</span><input class="master-input" type="date" name="planned_end_date" value="{{ $step->planned_end_date?->format('Y-m-d') }}"></label>
                        <label class="master-field project-queue-plan-notes"><span class="master-label">Coordinator notes</span><textarea class="master-textarea" name="notes" rows="3" maxlength="2000">{{ $step->notes }}</textarea></label>
                        <button class="master-btn master-btn-primary master-btn-sm" type="submit">Save plan</button>
                    </form></details></div>
                </article>
            @empty
                <div class="master-empty"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><h3>No current actions match</h3><p>Clear filters or switch to the team queue. Completed and future dependency-blocked steps stay out of this worklist.</p><a class="master-btn master-btn-soft" href="{{ route('projects.work-queue.index',['scope'=>$scope]) }}">Clear filters</a></div>
            @endforelse
        </div>
        @if($items->hasPages())<div class="master-pagination">{{ $items->links() }}</div>@endif
    </section>
</div>
@endsection
