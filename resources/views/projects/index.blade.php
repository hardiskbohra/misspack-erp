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
            <form method="GET" action="{{ route('projects.index') }}" class="projects-filter-form">
                <div class="master-field projects-search-field">
                    <label class="master-label">Search</label>
                    <input class="master-input" type="text" name="search" value="{{ $search }}"
                        placeholder="Search project no, name, status, scope...">
                </div>
                <div class="master-field">
                    <label class="master-label">Status</label>
                    <select class="master-select" name="status">
                        <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All Status</option>
                        @foreach ($statusOptions as $key => $label)
                            <option value="{{ $key }}" {{ $status === $key ? 'selected' : '' }}>
                                {{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="master-field">
                    <label class="master-label">Client</label>
                    <select class="master-select" name="client_id">
                        <option value="all" {{ $clientId === 'all' ? 'selected' : '' }}>All Clients</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}"
                                {{ (string) $clientId === (string) $client->id ? 'selected' : '' }}>
                                {{ $client->company_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="master-field">
                    <label class="master-label">Health</label>
                    <select class="master-select" name="health">
                        <option value="all" {{ $health === 'all' ? 'selected' : '' }}>All Health</option>
                        @foreach ($healthOptions as $key => $label)
                            <option value="{{ $key }}" {{ $health === $key ? 'selected' : '' }}>
                                {{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="projects-filter-actions">
                    <button class="master-btn master-btn-primary" type="submit"><i class="fa-solid fa-filter"></i>
                        Filter</button>
                    <a class="master-btn master-btn-soft" href="{{ route('projects.index') }}">Reset</a>
                </div>
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
                            <strong>{{ money($project->estimated_value, $project->currency) }}</strong>
                        </div>
                        <div>
                            <span>Inward</span>
                            <strong class="projects-money-green">{{ money($totals['inward'], $project->currency) }}</strong>
                        </div>
                        <div>
                            <span>Expense</span>
                            <strong class="projects-money-red">{{ money($totals['outward'], $project->currency) }}</strong>
                        </div>
                        <div>
                            <span>Outstanding</span>
                            <strong>{{ money($totals['outstanding'], $project->currency) }}</strong>
                        </div>
                        <div>
                            <span>Profit/Loss</span>
                            <strong>{{ money(($totals['inward'] - $totals['outward']), $project->currency) }}</strong>
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
