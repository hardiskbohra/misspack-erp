@extends('layouts.app')

@section('page-title', 'Project Management')

@section('content')
    <div class="projects-page">
        <div class="projects-hero">
            <div>
                <h1>Projects</h1>
                <p>Create a project, map client/products, track progress, comments, attachments and
                    payments in one place.</p>
            </div>
            <div class="projects-hero-actions">
                <button type="button" class="master-btn master-btn-light" data-open-modal="quickProjectModal">
                    <i class="fa-solid fa-bolt"></i> Quick Project
                </button>
                <a href="{{ route('projects.create') }}" class="master-btn master-btn-primary">
                    <i class="fa-solid fa-plus"></i> Add Project
                </a>
            </div>
        </div>

        <div class="projects-stats">
            <div class="projects-stat-card">
                <span>Total Projects</span>
                <strong>{{ $stats['total'] }}</strong>
                <small>All deals in execution</small>
            </div>
            <div class="projects-stat-card projects-stat-blue">
                <span>In Progress</span>
                <strong>{{ $stats['in_progress'] }}</strong>
                <small>Active production/workflow</small>
            </div>
            <div class="projects-stat-card projects-stat-amber">
                <span>Waiting</span>
                <strong>{{ $stats['waiting'] }}</strong>
                <small>Client/vendor dependency</small>
            </div>
            <div class="projects-stat-card projects-stat-green">
                <span>Completed</span>
                <strong>{{ $stats['completed'] }}</strong>
                <small>Closed successfully</small>
            </div>
        </div>

        <div class="projects-card projects-filter-card">
            <form method="GET" action="{{ route('projects.index') }}">
                <div class="core-filter-toolbar">
                    <div class="master-field projects-search-field">
                        <label class="master-label" for="projectSearch">Search</label>
                        <input class="master-input" id="projectSearch" type="text" name="search" value="{{ $search }}"
                            placeholder="Search project no, name, status, scope...">
                    </div>
                    <x-filter-trigger drawer="projectFiltersDrawer" :count="(filled($search) ? 1 : 0) + ($status !== 'all' ? 1 : 0) + ($clientId !== 'all' ? 1 : 0) + ($health !== 'all' ? 1 : 0)" />
                </div>
                <x-drawer id="projectFiltersDrawer" title="Filter projects" eyebrow="Project filters"
                    subtitle="Narrow projects by status, client, or project health." size="medium">
                    <section class="core-drawer-section">
                        <h3 class="core-drawer-section-title">Project details</h3>
                        <div class="core-drawer-fields">
                            <div class="master-field">
                                <label class="master-label" for="projectFilterStatus">Status</label>
                                <select class="master-select" id="projectFilterStatus" name="status">
                                    <option value="all" @selected($status === 'all')>All statuses</option>
                                    @foreach ($statusOptions as $key => $label)
                                        <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="master-field">
                                <label class="master-label" for="projectFilterClient">Client</label>
                                <select class="master-select" id="projectFilterClient" name="client_id">
                                    <option value="all" @selected($clientId === 'all')>All clients</option>
                                    @foreach ($clients as $client)
                                        <option value="{{ $client->id }}" @selected((string) $clientId === (string) $client->id)>
                                            {{ $client->company_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="master-field">
                                <label class="master-label" for="projectFilterHealth">Health</label>
                                <select class="master-select" id="projectFilterHealth" name="health">
                                    <option value="all" @selected($health === 'all')>All health</option>
                                    @foreach ($healthOptions as $key => $label)
                                        <option value="{{ $key }}" @selected($health === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </section>
                    <x-slot:footer>
                        <a class="master-btn master-btn-soft" href="{{ route('projects.index') }}">Reset</a>
                        <button class="master-btn master-btn-primary" type="submit">
                            <i class="fa-solid fa-filter" aria-hidden="true"></i> Apply filters
                        </button>
                    </x-slot:footer>
                </x-drawer>
            </form>
        </div>

        <div class="projects-list">
            @forelse($projects as $project)
                @php
                    $totals = $project->paymentTotals();
                    $statusClass = 'projects-chip-status-' . $project->status;
                    $healthClass = 'projects-health-' . $project->health;
                @endphp
                <div class="projects-card projects-project-card" style="{{ $project->progress_percent == 100 ? 'background:#10b98150' : '' }}">
                    <div class="projects-top-bar">
                        
                        <div class="projects-project-top">
                            <div>
                                <div class="projects-number">{{ $project->project_number }}</div>
                    
                                <h3>{{ $project->name }}</h3>
                    
                                <div class="projects-meta-row">
                                    <span>
                                        <i class="fa-solid fa-building"></i>
                                        {{ $project->client ? $project->client->company_name : 'Client #' . $project->client_id }}
                                    </span>
                                    <span>
                                        <i class="fa-solid fa-user-check"></i>
                                        {{ $project->assignedUser ? $project->assignedUser->name : 'Unassigned' }}
                                    </span>
                                    <span>
                                        <i class="fa-solid fa-calendar-days"></i>
                                        Start: {{ optional($project->start_date)->format('d M Y') ?: 'No Start' }}
                                    </span>
                                    <span>
                                        <i class="fa-solid fa-calendar-days"></i>
                                        Target: {{ optional($project->target_date)->format('d M Y') ?: 'No target' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    
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
                            <strong>{{ \App\Helpers\CommonHelper::amount($project->estimated_value, $project->currency) }}</strong>
                        </div>
                        <div>
                            <span>Inward</span>
                            <strong class="projects-money-green">{{ \App\Helpers\CommonHelper::amount($totals['inward'], $project->currency) }}</strong>
                        </div>
                        <div>
                            <span>Expense</span>
                            <strong class="projects-money-red">{{ \App\Helpers\CommonHelper::amount($totals['outward'], $project->currency) }}</strong>
                        </div>
                        <div>
                            <span>Outstanding</span>
                            <strong>{{ \App\Helpers\CommonHelper::amount($totals['outstanding'], $project->currency) }}</strong>
                        </div>
                        <div>
                            <span>Profit/Loss</span>
                            <strong>{{ \App\Helpers\CommonHelper::amount(($totals['inward'] - $totals['outward']), $project->currency) }}</strong>
                        </div>
                    </div>

                    <div class="projects-project-footer">
                        <div class="projects-footer-tags">
                            <span class="projects-chip {{ $statusClass }}">{{ $project->statusLabel() }}</span>
                            <span class="projects-chip {{ $healthClass }}">{{ $project->healthLabel() }}</span>
                            <span class="projects-tag"><i class="fa-solid fa-layer-group"></i>
                                {{ $project->priorityLabel() }}</span>
                            @if ($project->show_client_portal)
                                <span class="projects-tag projects-tag-public"><i class="fa-solid fa-eye"></i> Client portal
                                    active</span>
                            @else
                                <span class="projects-tag"><i class="fa-solid fa-eye-slash"></i> Portal hidden</span>
                            @endif
                        </div>
                        <div class="projects-footer-actions">
                            <button type="button" class="master-btn master-btn-soft master-btn-sm"
                                aria-label="Quick details for {{ $project->name }}" title="Quick details"
                                aria-haspopup="dialog" aria-controls="projectQuickDetails" aria-expanded="false"
                                data-drawer-open="projectQuickDetails" data-drawer-eyebrow="Project"
                                data-drawer-title="{{ $project->name }}"
                                data-drawer-subtitle="{{ $project->project_number }}"
                                data-drawer-client="{{ $project->client?->company_name ?: 'Client #'.$project->client_id }}"
                                data-drawer-assignee="{{ $project->assignedUser?->name ?: 'Unassigned' }}"
                                data-drawer-stage="{{ $project->stageLabel() }}"
                                data-drawer-status="{{ $project->statusLabel() }}"
                                data-drawer-health="{{ $project->healthLabel() }}"
                                data-drawer-priority="{{ $project->priorityLabel() }}"
                                data-drawer-progress="{{ $project->progress_percent }}%"
                                data-drawer-start-date="{{ optional($project->start_date)->format('d M Y') ?: 'No start date' }}"
                                data-drawer-target-date="{{ optional($project->target_date)->format('d M Y') ?: 'No target date' }}"
                                data-drawer-estimated="{{ \App\Helpers\CommonHelper::amount($project->estimated_value, $project->currency) }}"
                                data-drawer-received="{{ \App\Helpers\CommonHelper::amount($totals['inward'], $project->currency) }}"
                                data-drawer-expense="{{ \App\Helpers\CommonHelper::amount($totals['outward'], $project->currency) }}"
                                data-drawer-outstanding="{{ \App\Helpers\CommonHelper::amount($totals['outstanding'], $project->currency) }}"
                                data-drawer-record-url="{{ route('projects.show', $project) }}">
                                <i class="fa-solid fa-circle-info" aria-hidden="true"></i> Details
                            </button>
                            <a href="{{ route('projects.show', $project) }}"
                                class="master-btn master-btn-primary master-btn-sm">Open</a>
                            <a href="{{ route('projects.edit', $project) }}"
                                class="master-btn master-btn-soft master-btn-sm"><i class="fa-solid fa-pen"></i></a>
                                    
                            <form method="POST"
                                  action="{{ route('projects.destroy', $project) }}"
                                  class="delete-project-form">
                            
                                @csrf
                                @method('DELETE')
                            
                                <button type="submit" class="master-btn master-btn-soft master-btn-sm">
                                    <i class="fas fa-trash"></i>
                                </button>
                            
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="projects-empty" style="line-height:1.5;">
                    <div class="projects-empty-icon"><i class="fa-solid fa-briefcase"></i></div>
                    <h3>No projects found</h3>
                    <p>Create your first project/deal once a client finalises the quote.</p><br>
                    <button type="button" class="master-btn master-btn-primary"
                        data-open-modal="quickProjectModal">Quick Project</button>
                </div>
            @endforelse
        </div>

        <x-pagination :items="$projects" />
    </div>

    <x-drawer id="projectQuickDetails" title="Project details" eyebrow="Project quick view" size="wide">
        <section class="core-drawer-section">
            <h3 class="core-drawer-section-title">Overview</h3>
            <div class="core-drawer-fields">
                <div class="core-drawer-field" data-drawer-field><span class="core-drawer-field-label">Client</span><span class="core-drawer-field-value" data-drawer-bind="client"></span></div>
                <div class="core-drawer-field" data-drawer-field><span class="core-drawer-field-label">Assignee</span><span class="core-drawer-field-value" data-drawer-bind="assignee"></span></div>
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

    <div class="projects-modal" id="quickProjectModal" aria-hidden="true">
        <div class="projects-modal-backdrop" data-close-modal></div>
        <div class="projects-modal-panel">
            <div class="projects-modal-head">
                <div>
                    <p class="projects-eyebrow">Fast entry</p>
                    <h3>Create Quick Project</h3>
                </div>
                <button type="button" class="master-icon-btn" data-close-modal><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="POST" action="{{ route('projects.quickStore') }}" class="projects-modal-body">
                @csrf
                <div class="master-field">
                    <label class="master-label">Client <span>*</span></label>
                    <select class="master-select" name="client_id" required>
                        <option value="">Select client</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}">{{ $client->company_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="master-field">
                    <label class="master-label">Accepted Quote (optional)</label>
                    <select class="master-select" name="customer_quote_id">
                        <option value="">No quote mapping</option>
                        @foreach ($quotes as $quote)
                            <option value="{{ $quote->id }}">{{ $quote->quote_number }} - {{ $quote->title }}
                            </option>
                        @endforeach
                    </select>
                    <small>If selected, quote products will be imported automatically.</small>
                </div>
                <div class="master-field">
                    <label class="master-label">Project Name <span>*</span></label>
                    <input class="master-input" type="text" name="name" placeholder="e.g. MissPack Bottle Order - July" required>
                </div>
                <div class="projects-two-col">
                    <div class="master-field">
                        <label class="master-label">Start Date</label>
                        <input class="master-input" type="date" name="start_date" value="{{ now()->toDateString() }}">
                    </div>
                    <div class="master-field">
                        <label class="master-label">Target Date</label>
                        <input class="master-input" type="date" name="target_date" value="{{ now()->addDays(30)->toDateString() }}">
                    </div>
                </div>
                <div class="projects-two-col">
                    <div class="master-field">
                        <label class="master-label">Assignee</label>
                        <select class="master-select" name="assigned_to">
                            <option value="">Unassigned</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field">
                        <label class="master-label">Priority</label>
                        <select class="master-select" name="priority">
                            @foreach ($priorityOptions as $key => $label)
                                <option value="{{ $key }}" {{ $key === 'normal' ? 'selected' : '' }}>
                                    {{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="projects-modal-actions">
                    <button type="button" class="master-btn master-btn-soft" data-close-modal>Cancel</button>
                    <button type="submit" class="master-btn master-btn-primary">Create Project</button>
                </div>
            </form>
        </div>
    </div>

@push('styles')
        <link rel="stylesheet" href="{{ asset('assets/css/projects.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/js/projects.js') }}"></script>
@endpush
@endsection
