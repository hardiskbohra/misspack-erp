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
                <button type="button" class="projects-btn projects-btn-light" data-open-modal="quickProjectModal">
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
                <div class="projects-field projects-search-field">
                    <label>Search</label>
                    <input type="text" name="search" value="{{ $search }}"
                        placeholder="Search project no, name, status, scope...">
                </div>
                <div class="projects-field">
                    <label>Status</label>
                    <select name="status">
                        <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All Status</option>
                        @foreach ($statusOptions as $key => $label)
                            <option value="{{ $key }}" {{ $status === $key ? 'selected' : '' }}>
                                {{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="projects-field">
                    <label>Client</label>
                    <select name="client_id">
                        <option value="all" {{ $clientId === 'all' ? 'selected' : '' }}>All Clients</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}"
                                {{ (string) $clientId === (string) $client->id ? 'selected' : '' }}>
                                {{ $client->company_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="projects-field">
                    <label>Health</label>
                    <select name="health">
                        <option value="all" {{ $health === 'all' ? 'selected' : '' }}>All Health</option>
                        @foreach ($healthOptions as $key => $label)
                            <option value="{{ $key }}" {{ $health === $key ? 'selected' : '' }}>
                                {{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="projects-filter-actions">
                    <button class="projects-btn projects-btn-primary" type="submit"><i class="fa-solid fa-filter"></i>
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
                            <strong>{{ $project->currency == 'INR' ? '₹' : $project->currency }}
                                {{ number_format((float) $project->estimated_value, 2) }}</strong>
                        </div>
                        <div>
                            <span>Inward</span>
                            <strong class="projects-money-green">{{ $project->currency == 'INR' ? '₹' : $project->currency }}
                                {{ number_format($totals['inward'], 2) }}</strong>
                        </div>
                        <div>
                            <span>Expense</span>
                            <strong class="projects-money-red">{{ $project->currency == 'INR' ? '₹' : $project->currency }}
                                {{ number_format($totals['outward'], 2) }}</strong>
                        </div>
                        <div>
                            <span>Outstanding</span>
                            <strong>{{ $project->currency == 'INR' ? '₹' : $project->currency }} {{ number_format($totals['outstanding'], 2) }}</strong>
                        </div>
                        <div>
                            <span>Profit/Loss</span>
                            <strong>{{ $project->currency == 'INR' ? '₹' : $project->currency }} {{ number_format(($totals['inward'] - $totals['outward']), 2) }}</strong>
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
                                class="projects-btn projects-btn-primary projects-btn-sm">Open</a>
                            <a href="{{ route('projects.edit', $project) }}"
                                class="projects-btn projects-btn-soft projects-btn-sm"><i class="fa-solid fa-pen"></i></a>
                                    
                            <form method="POST"
                                  action="{{ route('projects.destroy', $project) }}"
                                  class="delete-project-form">
                            
                                @csrf
                                @method('DELETE')
                            
                                <button type="submit" class="projects-btn projects-btn-soft projects-btn-sm">
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
                    <button type="button" class="projects-btn projects-btn-primary"
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
                <button type="button" class="projects-icon-btn" data-close-modal><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="POST" action="{{ route('projects.quickStore') }}" class="projects-modal-body">
                @csrf
                <div class="projects-field">
                    <label>Client <span>*</span></label>
                    <select name="client_id" required>
                        <option value="">Select client</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}">{{ $client->company_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="projects-field">
                    <label>Accepted Quote (optional)</label>
                    <select name="customer_quote_id">
                        <option value="">No quote mapping</option>
                        @foreach ($quotes as $quote)
                            <option value="{{ $quote->id }}">{{ $quote->quote_number }} - {{ $quote->title }}
                            </option>
                        @endforeach
                    </select>
                    <small>If selected, quote products will be imported automatically.</small>
                </div>
                <div class="projects-field">
                    <label>Project Name <span>*</span></label>
                    <input type="text" name="name" placeholder="e.g. MissPack Bottle Order - July" required>
                </div>
                <div class="projects-two-col">
                    <div class="projects-field">
                        <label>Start Date</label>
                        <input type="date" name="start_date" value="{{ now()->toDateString() }}">
                    </div>
                    <div class="projects-field">
                        <label>Target Date</label>
                        <input type="date" name="target_date" value="{{ now()->addDays(30)->toDateString() }}">
                    </div>
                </div>
                <div class="projects-two-col">
                    <div class="projects-field">
                        <label>Assignee</label>
                        <select name="assigned_to">
                            <option value="">Unassigned</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="projects-field">
                        <label>Priority</label>
                        <select name="priority">
                            @foreach ($priorityOptions as $key => $label)
                                <option value="{{ $key }}" {{ $key === 'normal' ? 'selected' : '' }}>
                                    {{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="projects-modal-actions">
                    <button type="button" class="projects-btn projects-btn-soft" data-close-modal>Cancel</button>
                    <button type="submit" class="projects-btn projects-btn-primary">Create Project</button>
                </div>
            </form>
        </div>
    </div>

    <style>
        .projects-page {
            display: flex;
            flex-direction: column;
            gap: 18px
        }

        .projects-hero {
            background: linear-gradient(135deg, #4f83f1, #7b61ff);
            border-radius: 24px;
            padding: 24px;
            color: #fff;
            display: flex;
            justify-content: space-between;
            gap: 18px;
            box-shadow: 0 18px 45px rgba(79, 131, 241, .22)
        }

        .projects-eyebrow {
            margin: 0 0 6px;
            text-transform: uppercase;
            letter-spacing: .12em;
            font-size: 11px;
            font-weight: 500;
            opacity: .82
        }

        .projects-hero h1 {
            margin: 0;
            font-size: 30px;
            font-weight: 600
        }

        .projects-hero p {
            margin: 8px 0 0;
            max-width: 760px;
            opacity: .9
        }

        .projects-hero-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap
        }

        .projects-btn {
            border: 0;
            border-radius: 14px;
            padding: 10px 16px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            transition: .2s;
            white-space: nowrap
        }

        .projects-btn:hover {
            transform: translateY(-1px);
            text-decoration: none
        }

        .projects-btn-primary {
            background: #ef4770;
            color: #fff;
            box-shadow: 0 10px 24px rgba(239, 71, 112, .24)
        }

        .projects-btn-light {
            background: rgba(255, 255, 255, .18);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, .35)
        }

        .projects-btn-soft {
            background: #eef3ff;
            color: #4f83f1
        }

        .projects-btn-sm {
            padding: 8px 12px;
            border-radius: 12px;
            font-size: 13px
        }

        .projects-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px
        }

        .projects-stat-card {
            background: #fff;
            border-radius: 20px;
            padding: 18px;
            border: 1px solid #edf0f7;
            box-shadow: 0 10px 30px rgba(22, 34, 51, .06)
        }

        .projects-stat-card span {
            font-size: 12px;
            color: #768197;
            font-weight: 500;
            text-transform: uppercase
        }

        .projects-stat-card strong {
            display: block;
            font-size: 30px;
            margin: 8px 0;
            color: #1e2a3b
        }

        .projects-stat-card small {
            color: #98a2b3
        }

        .projects-stat-blue strong {
            color: #4f83f1
        }

        .projects-stat-amber strong {
            color: #f59e0b
        }

        .projects-stat-green strong {
            color: #12b76a
        }

        .projects-card {
            background: #fff;
            border: 1px solid #edf0f7;
            border-radius: 22px;
            box-shadow: 0 12px 32px rgba(22, 34, 51, .06)
        }

        .projects-filter-card {
            padding: 16px
        }

        .projects-filter-form {
            display: grid;
            grid-template-columns: 1.5fr .8fr 1fr .8fr auto;
            gap: 12px;
            align-items: end
        }

        .projects-field {
            display: flex;
            flex-direction: column;
            gap: 7px
        }

        .projects-field label {
            font-size: 12px;
            color: #5d6b82;
            font-weight: 600
        }

        .projects-field label span {
            color: #ef4770
        }

        .projects-field input,
        .projects-field select,
        .projects-field textarea {
            width: 100%;
            border: 1px solid #dfe5f2;
            border-radius: 14px;
            padding: 11px 12px;
            background: #fff;
            color: #1e2a3b;
            outline: none
        }

        .projects-field input:focus,
        .projects-field select:focus,
        .projects-field textarea:focus {
            border-color: #4f83f1;
            box-shadow: 0 0 0 4px rgba(79, 131, 241, .1)
        }

        .projects-field small {
            color: #8a94a6
        }

        .projects-filter-actions {
            display: flex;
            gap: 8px
        }

        .projects-list {
            display: grid;
            gap: 14px
        }

        .projects-project-card {
            padding: 18px
        }

        .projects-top-bar {
            display:flex;
            justify-content:space-between;
            align-items:flex-start;
            gap:32px;
            margin-bottom: 18px;
        }
        
        .projects-project-top{
            flex:1;
            min-width:0;
        }
        
        .projects-progress-wrap{
            width:360px;
            flex-shrink:0;
            align-self:center;
        }
        
        .projects-progress-text{
            display:flex;
            justify-content:space-between;
            align-items:center;
            margin-bottom:10px;
        }
        
        .projects-progress{
            height:12px;
            background:#e5e7eb;
            border-radius:999px;
            overflow:hidden;
        }
        
        .projects-progress span{
            display:block;
            height:100%;
            border-radius:999px;
            background:linear-gradient(90deg,#4f7cff,#18b66b);
        }

        .projects-number {
            font-size: 12px;
            color: #4f83f1;
            font-weight: 600
        }

        .projects-project-card h3 {
            margin: 4px 0 8px;
            font-size: 20px;
            color: #172033
        }

        .projects-meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            color: #6b7688;
            font-size: 13px
        }

        .projects-meta-row i {
            color: #9aa4b6
        }

        .projects-card-actions {
            display: flex;
            gap: 8px;
            align-items: flex-start;
            flex-wrap: wrap;
            justify-content: flex-end
        }

        .projects-chip {
            border-radius: 999px;
            padding: 7px 10px;
            font-size: 12px;
            font-weight: 600;
            background: #eef2f7;
            color: #5b6678
        }

        .projects-chip-status-in_progress {
            background: #eaf2ff;
            color: #2563eb
        }

        .projects-chip-status-completed {
            background: #e8fff3;
            color: #039855
        }

        .projects-chip-status-on_hold,
        .projects-chip-status-waiting_client,
        .projects-chip-status-waiting_vendor {
            background: #fff7e6;
            color: #b54708
        }

        .projects-chip-status-cancelled {
            background: #fff1f3;
            color: #d92d20
        }

        .projects-health-green {
            background: #e8fff3;
            color: #039855
        }

        .projects-health-amber {
            background: #fff7e6;
            color: #b54708
        }

        .projects-health-red {
            background: #fff1f3;
            color: #d92d20
        }

        .projects-progress-wrap {
            margin: 18px 0
        }

        .projects-progress-text {
            display: flex;
            justify-content: space-between;
            color: #667085;
            font-size: 13px;
            margin-bottom: 8px
        }

        .projects-progress-text strong {
            color: #172033
        }

        .projects-progress {
            height: 10px;
            background: #eef2f7;
            border-radius: 999px;
            overflow: hidden
        }

        .projects-progress span {
            display: block;
            height: 100%;
            background: linear-gradient(90deg, #4f83f1, #12b76a);
            border-radius: 999px
        }

        .projects-mini-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 10px
        }

        .projects-mini-grid div {
            background: #f8faff;
            border: 1px solid #edf2ff;
            border-radius: 16px;
            padding: 12px
        }

        .projects-mini-grid span {
            display: block;
            color: #7d8797;
            font-size: 12px;
            font-weight: 500
        }

        .projects-mini-grid strong {
            display: block;
            margin-top: 5px;
            color: #1e2a3b
        }

        .projects-money-green {
            color: #039855 !important
        }

        .projects-money-red {
            color: #d92d20 !important
        }

        .projects-project-footer {
            margin-top: 16px;
            padding-top: 14px;
            border-top: 1px solid #edf0f7;
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center
        }

        .projects-footer-tags,
        .projects-footer-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap
        }

        .projects-tag {
            background: #f7f9fc;
            border: 1px solid #edf0f7;
            color: #667085;
            border-radius: 999px;
            padding: 7px 10px;
            font-size: 12px;
            font-weight: 500
        }

        .projects-tag-public {
            background: #eef9ff;
            color: #0b72b9
        }

        .projects-empty {
            text-align: center;
            padding: 50px;
            background: #fff;
            border-radius: 24px;
            border: 1px dashed #d8deea
        }

        .projects-empty-icon {
            width: 66px;
            height: 66px;
            margin: 0 auto 12px;
            border-radius: 20px;
            background: #eef3ff;
            color: #4f83f1;
            display: grid;
            place-items: center;
            font-size: 28px
        }

        .projects-pagination {
            display: flex;
            justify-content: flex-end
        }

        .projects-modal {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: none
        }

        .projects-modal.is-open {
            display: block
        }

        .projects-modal-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, .55);
            backdrop-filter: blur(5px)
        }

        .projects-modal-panel {
            position: relative;
            background: #fff;
            width: min(620px, calc(100% - 24px));
            margin: 6vh auto;
            border-radius: 24px;
            box-shadow: 0 24px 80px rgba(15, 23, 42, .28);
            overflow: hidden
        }

        .projects-modal-head {
            padding: 18px 20px;
            border-bottom: 1px solid #edf0f7;
            display: flex;
            justify-content: space-between;
            align-items: center
        }

        .projects-modal-head h3 {
            margin: 0;
            color: #172033
        }

        .projects-icon-btn {
            border: 0;
            background: #f2f5fa;
            color: #64748b;
            width: 38px;
            height: 38px;
            border-radius: 12px;
            cursor: pointer
        }

        .projects-modal-body {
            padding: 18px 20px;
            display: flex;
            flex-direction: column;
            gap: 14px
        }

        .projects-two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px
        }

        .projects-modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 6px
        }

        @media(max-width:1199px) {
            .projects-filter-form {
                grid-template-columns: 1fr 1fr
            }

            .projects-search-field {
                grid-column: 1/-1
            }

            .projects-filter-actions {
                grid-column: 1/-1
            }

            .projects-mini-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr))
            }

            .projects-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr))
            }
        }

        @media(max-width:767px) {

            .projects-hero,
            .projects-project-top,
            .projects-project-footer {
                flex-direction: column
            }

            .projects-hero-actions,
            .projects-footer-actions {
                width: 100%
            }

            .projects-hero-actions .projects-btn,
            .projects-footer-actions .projects-btn {
                flex: 1
            }

            .projects-filter-form,
            .projects-two-col {
                grid-template-columns: 1fr
            }

            .projects-stats,
            .projects-mini-grid {
                grid-template-columns: 1fr
            }

            .projects-modal-panel {
                margin: 12px auto
            }

            .projects-card-actions {
                justify-content: flex-start
            }

            .projects-project-card {
                padding: 14px
            }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('[data-open-modal]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var modal = document.getElementById(btn.getAttribute('data-open-modal'));
                    if (modal) modal.classList.add('is-open');
                });
            });
            document.querySelectorAll('[data-close-modal]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    btn.closest('.projects-modal').classList.remove('is-open');
                });
            });
            document.querySelectorAll('.delete-project-form').forEach(form => {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
            
                    Swal.fire({
                        title: 'Delete Project?',
                        text: "This action cannot be undone.",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, Delete',
                        cancelButtonText: 'Cancel',
                        reverseButtons: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            form.submit();
                        }
                    });
                });
            });
        });
        
        
    </script>
@endsection
