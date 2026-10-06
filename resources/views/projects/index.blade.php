@extends('layouts.app')

@section('title', 'Projects')
@section('page-title', 'Projects')

@section('page-actions')
    <button type="button" class="master-btn master-btn-soft" id="openQuickProjectModal">
        <i class="fa-solid fa-bolt" aria-hidden="true"></i> Quick project
    </button>
    <a href="{{ route('projects.create') }}" class="master-btn master-btn-primary">
        <i class="fa-solid fa-plus" aria-hidden="true"></i> Add project
    </a>
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/projects.css') }}">
@endpush

@php
    /* Every chip is one URL away from the others: a chip changes what it owns
       and carries the search, the client and the health with it. */
    $clientFilter = (string) $clientId;
    $filtersActive = filled($search) || $status !== 'all' || $clientFilter !== 'all' || $health !== 'all';
    $activeFilterCount = (filled($search) ? 1 : 0) + ($status !== 'all' ? 1 : 0)
        + ($clientFilter !== 'all' ? 1 : 0) + ($health !== 'all' ? 1 : 0);

    $keep = fn (array $drop) => collect(request()->except(array_merge($drop, ['page'])))
        ->reject(fn ($value) => $value === null || $value === '' || $value === 'all');

    $statusUrl = function ($value) use ($keep) {
        $query = $keep(['status'])->all();
        if ($value !== 'all') {
            $query['status'] = $value;
        }

        return route('projects.index', $query);
    };
    $healthUrl = function ($value) use ($keep) {
        $query = $keep(['health'])->all();
        if ($value !== 'all') {
            $query['health'] = $value;
        }

        return route('projects.index', $query);
    };
    $chipUrl = fn (string $key) => route('projects.index', $keep([$key])->all());

    $projectCount = $projects->total();
    $firstProject = $projects->firstItem() ?? 0;
    $lastProject = $projects->lastItem() ?? 0;
    $today = now()->startOfDay();
@endphp

<div class="project project-index master-list">
    {{-- The figures. Waiting is client and vendor together: both are a project
         sitting still, and the office chases them the same way. --}}
    <div class="master-stats desktop-only" aria-label="Project overview">
        <div class="master-stat master-stat--flat blue">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-briefcase"></i></span>
            <div>
                <p class="master-stat-title">Total projects</p>
                <p class="master-stat-value">{{ number_format($stats['total']) }}</p>
                <p class="master-sub">Every project on record</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat teal">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-person-running"></i></span>
            <div>
                <p class="master-stat-title">In progress</p>
                <p class="master-stat-value">{{ number_format($stats['in_progress']) }}</p>
                <p class="master-sub">Production and workflow running</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat orange">
            <span class="icon" aria-hidden="true"><i class="fa-regular fa-clock"></i></span>
            <div>
                <p class="master-stat-title">Waiting</p>
                <p class="master-stat-value">{{ number_format($stats['waiting']) }}</p>
                <p class="master-sub">On a client or a vendor</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat green">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></span>
            <div>
                <p class="master-stat-title">Completed</p>
                <p class="master-stat-value">{{ number_format($stats['completed']) }}</p>
                <p class="master-sub">Closed with the client</p>
            </div>
        </div>
    </div>

    {{-- Search and filter: the chips are the quick filter, the drawer keeps the
         rest of the criteria in the same GET form. --}}
    <section class="master-card master-card--flat" aria-label="Search and filter projects">
        <div class="master-list-bar">
            <nav class="master-list-chips" aria-label="Filter projects by status">
                <a class="master-list-chip {{ $status === 'all' ? 'is-active' : '' }}"
                    href="{{ $statusUrl('all') }}">All projects
                    <span class="master-list-chip-count">{{ number_format($stats['total']) }}</span></a>
                @foreach ($statusOptions as $key => $label)
                    <a class="master-list-chip {{ $status === $key ? 'is-active' : '' }}"
                        href="{{ $statusUrl($key) }}">
                        {{ $label }}
                        <span class="master-list-chip-count">{{ number_format($statusCounts[$key] ?? 0) }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="master-list-chips" aria-label="Filter projects by health">
                @foreach ($healthOptions as $key => $label)
                    <a class="master-list-chip {{ $health === $key ? 'is-active' : '' }}"
                        href="{{ $healthUrl($key) }}">
                        <i class="fa-solid fa-circle project-health-dot project-health-dot--{{ $key }}" aria-hidden="true"></i>
                        {{ \Illuminate\Support\Str::before($label, ' /') }}
                        <span class="master-list-chip-count">{{ number_format($healthCounts[$key] ?? 0) }}</span>
                    </a>
                @endforeach
            </div>
        </div>

        <form method="GET" action="{{ route('projects.index') }}">
            <div class="master-filter-row core-filter-toolbar">
                <label class="master-search">
                    <span aria-hidden="true"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input class="master-input" type="search" name="search" value="{{ $search }}" maxlength="150"
                        placeholder="Search project number, name, client or scope" aria-label="Search projects">
                </label>

                <x-filter-trigger drawer="projectFiltersDrawer" label="Filters" :count="$activeFilterCount" />
            </div>

            <x-drawer id="projectFiltersDrawer" title="Filter projects" eyebrow="Project filters"
                subtitle="Narrow the list by status, client or health — the chips carry the search." size="medium">
                <section class="core-drawer-section">
                    <h3 class="core-drawer-section-title">The project</h3>
                    <div class="core-drawer-fields">
                        <label class="master-field">
                            <span class="master-label">Status</span>
                            <select class="master-select" name="status">
                                <option value="all">Every status</option>
                                @foreach ($statusOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="master-field">
                            <span class="master-label">Health</span>
                            <select class="master-select" name="health">
                                <option value="all">Every health</option>
                                @foreach ($healthOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($health === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                </section>

                <section class="core-drawer-section">
                    <h3 class="core-drawer-section-title">Who</h3>
                    <label class="master-field">
                        <span class="master-label">Client</span>
                        <select class="master-select" name="client_id">
                            <option value="all">Every client</option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}" @selected($clientFilter === (string) $client->id)>
                                    {{ $client->company_name }}</option>
                            @endforeach
                        </select>
                    </label>
                </section>

                <x-slot:footer>
                    @if ($filtersActive)
                        <a class="master-btn master-btn-soft" href="{{ route('projects.index') }}">Reset</a>
                    @endif
                    <button class="master-btn master-btn-primary" type="submit">
                        <i class="fa-solid fa-filter" aria-hidden="true"></i> Apply filters
                    </button>
                </x-slot:footer>
            </x-drawer>
        </form>

        @if ($filtersActive)
            <div class="master-list-applied" aria-label="Active filters">
                <span class="master-list-applied-title">Filtered by</span>
                @if (filled($search))
                    <span class="master-list-applied-chip">
                        <span class="master-list-applied-key">Search</span>
                        <span class="master-list-applied-value">{{ $search }}</span>
                        <a class="master-list-applied-x" href="{{ $chipUrl('search') }}"
                            aria-label="Remove the search" title="Remove the search">&times;</a>
                    </span>
                @endif
                @if ($status !== 'all')
                    <span class="master-list-applied-chip">
                        <span class="master-list-applied-key">Status</span>
                        <span class="master-list-applied-value">{{ $statusOptions[$status] ?? $status }}</span>
                        <a class="master-list-applied-x" href="{{ $chipUrl('status') }}"
                            aria-label="Remove the status filter" title="Remove the status filter">&times;</a>
                    </span>
                @endif
                @if ($clientFilter !== 'all')
                    <span class="master-list-applied-chip">
                        <span class="master-list-applied-key">Client</span>
                        <span class="master-list-applied-value">{{ $clients->firstWhere('id', (int) $clientFilter)?->company_name ?? $clientFilter }}</span>
                        <a class="master-list-applied-x" href="{{ $chipUrl('client_id') }}"
                            aria-label="Remove the client filter" title="Remove the client filter">&times;</a>
                    </span>
                @endif
                @if ($health !== 'all')
                    <span class="master-list-applied-chip">
                        <span class="master-list-applied-key">Health</span>
                        <span class="master-list-applied-value">{{ \Illuminate\Support\Str::before($healthOptions[$health] ?? $health, ' /') }}</span>
                        <a class="master-list-applied-x" href="{{ $chipUrl('health') }}"
                            aria-label="Remove the health filter" title="Remove the health filter">&times;</a>
                    </span>
                @endif
                <a class="master-list-applied-clear" href="{{ route('projects.index') }}">Clear all filters</a>
            </div>
        @endif
    </section>

    {{-- The records. One row is one project: what it is, where it stands, what it
         is worth, and the one door into it. --}}
    <section class="master-card master-table-card master-card--flat" aria-label="Project records">
        <div class="master-list-toolbar">
            <p class="master-list-hint" title="Newest project number on top, whatever its status.">
                {{ $projectCount === 0
                    ? 'No matching projects'
                    : 'Newest first · Showing '.$firstProject.'–'.$lastProject.' of '.$projectCount }}
            </p>

            <div class="master-list-toolbar-actions">
                <div class="master-list-density desktop-only" role="group" aria-label="Table density">
                    <button type="button" class="master-list-density-btn" data-density="standard" aria-pressed="true">Standard</button>
                    <button type="button" class="master-list-density-btn" data-density="comfortable" aria-pressed="false">Comfortable</button>
                    <button type="button" class="master-list-density-btn" data-density="compact" aria-pressed="false">Compact</button>
                </div>
            </div>
        </div>

        <div class="master-table-wrap ui-mobile-cards">
            <table class="master-table" data-table-settings data-table-key="projects">
                <thead>
                    <tr>
                        <th scope="col">Project</th>
                        <th scope="col">Client</th>
                        <th scope="col">Stage</th>
                        <th scope="col" class="ui-mobile-secondary">Owner</th>
                        <th scope="col">Target</th>
                        <th scope="col" class="is-num">Value</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="project-col-actions">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($projects as $project)
                        @php
                            $totals = $project->paymentTotals();
                            $statusClass = str_replace('_', '-', (string) $project->status);
                            $healthWord = \Illuminate\Support\Str::before($project->healthLabel(), ' /');
                            $isLate = $project->target_date
                                && ! in_array($project->status, ['completed', 'cancelled'], true)
                                && $project->target_date->lessThan($today);
                        @endphp
                        <tr class="project-row is-clickable" data-href="{{ route('projects.show', $project) }}">
                            <td data-label="Project">
                                <a class="project-table-name" href="{{ route('projects.show', $project) }}">{{ $project->name }}</a>
                                <span class="project-table-meta">{{ $project->project_number }}</span>
                                @if (in_array($project->priority, ['high', 'urgent'], true))
                                    <span class="master-chip project-priority-{{ $project->priority }}">{{ $project->priorityLabel() }}</span>
                                @endif
                            </td>
                            <td data-label="Client">
                                <strong>{{ $project->clientName() }}</strong>
                                @unless ($project->show_client_portal)
                                    <span class="master-sub ui-mobile-secondary">
                                        <i class="fa-solid fa-eye-slash" aria-hidden="true"></i> Portal hidden</span>
                                @endunless
                            </td>
                            <td data-label="Stage">
                                <span class="project-table-stage">
                                    <span>{{ $project->stageLabel() }}</span>
                                    <strong>{{ $project->progress_percent }}%</strong>
                                </span>
                                <span class="project-table-progress" aria-hidden="true">
                                    <span style="width: {{ max(0, min(100, (int) $project->progress_percent)) }}%"></span>
                                </span>
                            </td>
                            <td data-label="Owner" class="ui-mobile-secondary">
                                {{ $project->assignedUser?->name ?: 'Unassigned' }}
                            </td>
                            <td data-label="Target">
                                <span class="project-table-date {{ $isLate ? 'is-late' : '' }}">
                                    {{ $project->target_date?->format('d M Y') ?? 'No target' }}
                                </span>
                                <span class="master-sub ui-mobile-secondary">
                                    Start {{ $project->start_date?->format('d M Y') ?? 'not set' }}</span>
                            </td>
                            <td data-label="Value" class="is-num">
                                <strong>{{ \App\Helpers\CommonHelper::amount($project->estimated_value, $project->currency) }}</strong>
                                <span class="master-sub">{{ \App\Helpers\CommonHelper::amount($totals['outstanding'], $project->currency) }} outstanding</span>
                            </td>
                            <td data-label="Status">
                                <span class="master-badge status-{{ $statusClass }}">{{ $project->statusLabel() }}</span>
                                <span class="master-badge health-{{ $project->health }}">{{ $healthWord }}</span>
                            </td>
                            <td data-label="Action" class="project-col-actions">
                                <div class="master-row-actions">
                                    <div class="master-dropdown">
                                        <button type="button" class="master-dropdown-toggle"
                                            aria-label="Actions for {{ $project->name }}"
                                            aria-haspopup="true" aria-expanded="false">
                                            <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                        </button>
                                        <div class="master-dropdown-menu">
                                            <a href="{{ route('projects.show', $project) }}">
                                                <i class="fas fa-eye" aria-hidden="true"></i> Open project</a>
                                            <button type="button"
                                                aria-label="Quick details for {{ $project->name }}"
                                                aria-haspopup="dialog" aria-controls="projectQuickDetails" aria-expanded="false"
                                                data-drawer-open="projectQuickDetails" data-drawer-eyebrow="Project"
                                                data-drawer-title="{{ $project->name }}"
                                                data-drawer-subtitle="{{ $project->project_number }}"
                                                data-drawer-client="{{ $project->clientName() }}"
                                                data-drawer-assignee="{{ $project->assignedUser?->name ?: 'Unassigned' }}"
                                                data-drawer-stage="{{ $project->stageLabel() }}"
                                                data-drawer-status="{{ $project->statusLabel() }}"
                                                data-drawer-health="{{ $project->healthLabel() }}"
                                                data-drawer-priority="{{ $project->priorityLabel() }}"
                                                data-drawer-progress="{{ $project->progress_percent }}%"
                                                data-drawer-start-date="{{ $project->start_date?->format('d M Y') ?? 'No start date' }}"
                                                data-drawer-target-date="{{ $project->target_date?->format('d M Y') ?? 'No target date' }}"
                                                data-drawer-estimated="{{ \App\Helpers\CommonHelper::amount($project->estimated_value, $project->currency) }}"
                                                data-drawer-received="{{ \App\Helpers\CommonHelper::amount($totals['inward'], $project->currency) }}"
                                                data-drawer-expense="{{ \App\Helpers\CommonHelper::amount($totals['outward'], $project->currency) }}"
                                                data-drawer-outstanding="{{ \App\Helpers\CommonHelper::amount($totals['outstanding'], $project->currency) }}"
                                                data-drawer-record-url="{{ route('projects.show', $project) }}">
                                                <i class="fa-solid fa-circle-info" aria-hidden="true"></i> Quick details
                                            </button>
                                            <a href="{{ route('projects.edit', $project) }}">
                                                <i class="fas fa-pen" aria-hidden="true"></i> Edit project</a>
                                            <form method="POST" action="{{ route('projects.destroy', $project) }}"
                                                class="delete-project-form">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="danger"
                                                    aria-label="Delete {{ $project->name }}" title="Delete project">
                                                    <i class="far fa-trash-alt" aria-hidden="true"></i> Delete project
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="master-list-empty">
                                    <span class="master-list-empty-icon" aria-hidden="true"><i class="fa-regular fa-briefcase"></i></span>
                                    <h2 class="master-list-empty-title">
                                        {{ $filtersActive ? 'No projects match these filters' : 'Your project list is empty' }}</h2>
                                    <p class="master-list-empty-text">
                                        {{ $filtersActive
                                            ? 'Try a different search or clear the filters to see every project.'
                                            : 'Create a project when an order is confirmed, and track its products, milestones, payments and feedback from one page.' }}
                                    </p>
                                    <div class="master-list-empty-actions">
                                        @if ($filtersActive)
                                            <a href="{{ route('projects.index') }}" class="master-btn master-btn-soft">Clear filters</a>
                                        @else
                                            <a href="{{ route('projects.create') }}" class="master-btn master-btn-primary">
                                                <i class="fa-solid fa-plus" aria-hidden="true"></i> Add first project</a>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination :items="$projects" />
    </section>

    {{-- One row's quick details, filled from the opener's data-* attributes. --}}
    <x-drawer id="projectQuickDetails" title="Project details" eyebrow="Project quick view" size="wide">
        <section class="core-drawer-section">
            <h3 class="core-drawer-section-title">Overview</h3>
            <div class="core-drawer-fields">
                <div class="core-drawer-field" data-drawer-field><span class="core-drawer-field-label">Client</span><span class="core-drawer-field-value" data-drawer-bind="client"></span></div>
                <div class="core-drawer-field" data-drawer-field><span class="core-drawer-field-label">Owner</span><span class="core-drawer-field-value" data-drawer-bind="assignee"></span></div>
                <div class="core-drawer-field" data-drawer-field><span class="core-drawer-field-label">Stage</span><span class="core-drawer-field-value" data-drawer-bind="stage"></span></div>
                <div class="core-drawer-field" data-drawer-field><span class="core-drawer-field-label">Progress</span><span class="core-drawer-field-value" data-drawer-bind="progress"></span></div>
                <div class="core-drawer-field" data-drawer-field><span class="core-drawer-field-label">Status</span><span class="core-drawer-field-value" data-drawer-bind="status"></span></div>
                <div class="core-drawer-field" data-drawer-field><span class="core-drawer-field-label">Health</span><span class="core-drawer-field-value" data-drawer-bind="health"></span></div>
                <div class="core-drawer-field" data-drawer-field><span class="core-drawer-field-label">Priority</span><span class="core-drawer-field-value" data-drawer-bind="priority"></span></div>
                <div class="core-drawer-field" data-drawer-field><span class="core-drawer-field-label">Start date</span><span class="core-drawer-field-value" data-drawer-bind="start-date"></span></div>
                <div class="core-drawer-field" data-drawer-field><span class="core-drawer-field-label">Target date</span><span class="core-drawer-field-value" data-drawer-bind="target-date"></span></div>
            </div>
        </section>
        <section class="core-drawer-section">
            <h3 class="core-drawer-section-title">Financial snapshot</h3>
            <div class="core-drawer-fields">
                <div class="core-drawer-field" data-drawer-field><span class="core-drawer-field-label">Estimated</span><span class="core-drawer-field-value" data-drawer-bind="estimated"></span></div>
                <div class="core-drawer-field" data-drawer-field><span class="core-drawer-field-label">Received</span><span class="core-drawer-field-value" data-drawer-bind="received"></span></div>
                <div class="core-drawer-field" data-drawer-field><span class="core-drawer-field-label">Expense</span><span class="core-drawer-field-value" data-drawer-bind="expense"></span></div>
                <div class="core-drawer-field" data-drawer-field><span class="core-drawer-field-label">Outstanding</span><span class="core-drawer-field-value" data-drawer-bind="outstanding"></span></div>
            </div>
        </section>
        <x-slot:footer>
            <a class="master-btn master-btn-primary" data-drawer-href-bind="record-url">Open full project</a>
        </x-slot:footer>
    </x-drawer>

    {{-- Quick create: the shared dialog, so it closes, traps focus and answers
         Escape the way every other dialog in the ERP does. --}}
    <div class="master-modal" id="quickProjectModal" aria-hidden="true" aria-labelledby="quickProjectTitle">
        <div class="master-modal-card" role="dialog" aria-modal="true" aria-labelledby="quickProjectTitle"
            aria-describedby="quickProjectDescription" tabindex="-1">
            <form method="POST" action="{{ route('projects.quickStore') }}" id="quickProjectForm">
                @csrf
                <div class="master-modal-header">
                    <div class="master-modal-heading">
                        <span class="master-modal-icon"><i class="fa-solid fa-briefcase" aria-hidden="true"></i></span>
                        <div>
                            <h2 class="master-modal-title" id="quickProjectTitle">Quick project</h2>
                            <p class="master-modal-subtitle" id="quickProjectDescription">
                                Start the project with the essentials. Products, milestones and payments are added on its page.</p>
                        </div>
                    </div>
                    <button type="button" class="master-modal-close" data-close-modal="quickProjectModal"
                        aria-label="Close quick project dialog">&times;</button>
                </div>
                <div class="master-modal-body">
                    <div class="master-modal-grid">
                        <div class="master-field">
                            <label class="master-label" for="quick_project_client">Client
                                <span class="master-required" aria-hidden="true">*</span></label>
                            <select class="master-select" id="quick_project_client" name="client_id" required>
                                <option value="">Select client</option>
                                @foreach ($clients as $client)
                                    <option value="{{ $client->id }}">{{ $client->company_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quick_project_name">Project name
                                <span class="master-required" aria-hidden="true">*</span></label>
                            <input class="master-input" id="quick_project_name" type="text" name="name" required
                                maxlength="255" placeholder="e.g. MissPack bottle order — July">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quick_project_start">Start date</label>
                            <input class="master-input" id="quick_project_start" type="date" name="start_date"
                                value="{{ now()->toDateString() }}">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quick_project_target">Target date</label>
                            <input class="master-input" id="quick_project_target" type="date" name="target_date"
                                value="{{ now()->addDays(30)->toDateString() }}">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quick_project_owner">Owner</label>
                            <select class="master-select" id="quick_project_owner" name="assigned_to">
                                <option value="">Unassigned</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quick_project_priority">Priority</label>
                            <select class="master-select" id="quick_project_priority" name="priority">
                                @foreach ($priorityOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($key === 'normal')>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="master-modal-footer">
                    <button type="button" class="master-btn master-btn-light" data-close-modal="quickProjectModal">Cancel</button>
                    <button type="submit" class="master-btn master-btn-primary">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i> Create project</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
    <script src="{{ $assetVer('assets/js/projects.js') }}"></script>
@endpush
@endsection
