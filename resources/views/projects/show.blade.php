@extends('layouts.app')

@section('title', $project->name)
@section('page-title', 'Project record')

@section('content')
    @php
        $totals = $project->paymentTotals();
        $portalUrl = route('projects.public.show', $project->public_token);
        $money = fn ($amount) => \App\Helpers\CommonHelper::amount($amount, $project->currency);
        $statusClass = str_replace('_', '-', (string) $project->status);
        $healthWord = \Illuminate\Support\Str::before($project->healthLabel(), ' /');
        $isLate = $project->target_date
            && ! in_array($project->status, ['completed', 'cancelled'], true)
            && $project->target_date->lessThan(now()->startOfDay());
        $daysLate = $isLate ? (int) $project->target_date->diffInDays(now()->startOfDay()) : 0;
        $milestonesDone = $project->milestones->where('status', 'completed')->count();
        $milestonesOpen = $project->milestones->whereNotIn('status', ['completed', 'skipped'])->count();
        $receivedPercent = $project->estimated_value > 0
            ? min(100, (int) round($totals['inward'] / (float) $project->estimated_value * 100))
            : 0;
        $progress = max(0, min(100, (int) $project->progress_percent));
        $initials = \Illuminate\Support\Str::of($project->name)
            ->squish()->words(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('');
        $recordUrl = fn (string $key) => route('projects.show', ['project' => $project, 'tab' => $key]);
    @endphp

    <div class="project project-show master-list" data-project-id="{{ $project->id }}"
        data-project-tab="{{ $tab }}">

        {{-- Identity, then state, then the doors: what this project is, where it
             stands today, and the four actions an office takes from here. --}}
        <header class="master-card master-header project-record-header">
            <div class="project-record-identity">
                <span class="project-record-mark" aria-hidden="true">{{ $initials ?: 'PR' }}</span>
                <div class="project-record-copy">
                    <h1>{{ $project->name }}</h1>
                    <div class="project-record-meta">
                        <span>{{ $project->project_number }}</span>
                        <span aria-hidden="true">·</span>
                        <span>{{ $project->clientName() }}</span>
                        <span aria-hidden="true">·</span>
                        <span>Owner: {{ $project->assignedUser?->name ?: 'Unassigned' }}</span>
                        <span class="master-badge status-{{ $statusClass }}">{{ $project->statusLabel() }}</span>
                        <span class="master-badge health-{{ $project->health }}">{{ $healthWord }}</span>
                        <span class="master-chip">{{ $project->stageLabel() }}</span>
                        @if (in_array($project->priority, ['high', 'urgent'], true))
                            <span class="master-chip project-priority-chip is-{{ $project->priority }}">{{ $project->priorityLabel() }}</span>
                        @endif
                    </div>
                    <div class="project-record-progress">
                        <span class="project-record-progress-track" role="img"
                            aria-label="{{ $progress }} percent complete">
                            <span style="width: {{ $progress }}%"></span>
                        </span>
                        <span class="project-record-progress-value">{{ $progress }}% complete</span>
                        <span class="project-record-progress-note">
                            @if ($project->target_date)
                                Target {{ $project->target_date->format('d M Y') }}{{ $isLate ? ' — '.$daysLate.' '.\Illuminate\Support\Str::plural('day', $daysLate).' late' : '' }}
                            @else
                                No target date set
                            @endif
                        </span>
                    </div>
                </div>
            </div>

            <nav class="project-record-actions" aria-label="Project actions">
                <a href="{{ route('projects.index') }}" class="master-btn master-btn-light">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Projects
                </a>
                @if ($project->show_client_portal)
                    <a href="{{ $portalUrl }}" target="_blank" rel="noopener" class="master-btn master-btn-soft">
                        <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> Client portal
                    </a>
                @endif
                <button type="button" class="master-btn master-btn-soft" data-drawer-open="projectStatusDrawer"
                    aria-haspopup="dialog" aria-controls="projectStatusDrawer" aria-expanded="false">
                    <i class="fa-solid fa-sliders" aria-hidden="true"></i> Update status
                </button>
                <a href="{{ route('projects.edit', $project) }}" class="master-btn master-btn-primary">
                    <i class="fa-solid fa-pen" aria-hidden="true"></i> Edit project
                </a>
            </nav>
        </header>

        {{-- The one thing on this page that says “do something today”. --}}
        @if ($isLate)
            <div class="project-attention">
                <span class="project-attention-icon" aria-hidden="true"><i class="fa-solid fa-triangle-exclamation"></i></span>
                <div class="project-attention-copy">
                    <strong>Target date passed {{ $daysLate }} {{ \Illuminate\Support\Str::plural('day', $daysLate) }} ago</strong>
                    <span>
                        {{ $milestonesOpen }} open {{ \Illuminate\Support\Str::plural('milestone', $milestonesOpen) }} ·
                        {{ $money($totals['outstanding']) }} outstanding
                    </span>
                </div>
                <a class="master-btn master-btn-soft master-btn-sm" href="{{ $recordUrl('milestones') }}">
                    <i class="fa-solid fa-flag-checkered" aria-hidden="true"></i> Open milestones
                </a>
            </div>
        @endif

        {{-- The four figures that answer “what is this worth”. --}}
        <div class="master-stats desktop-only" aria-label="Project figures">
            <div class="master-stat master-stat--flat blue">
                <span class="icon" aria-hidden="true"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                <div>
                    <p class="master-stat-title">Estimated value</p>
                    <p class="master-stat-value">{{ $money($project->estimated_value) }}</p>
                    <p class="master-sub">
                        {{ $project->customerQuote ? 'Quote '.$project->customerQuote->quote_number : 'No quote mapped' }}
                    </p>
                </div>
            </div>
            <div class="master-stat master-stat--flat green">
                <span class="icon" aria-hidden="true"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                <div>
                    <p class="master-stat-title">Received</p>
                    <p class="master-stat-value">{{ $money($totals['inward']) }}</p>
                    <p class="master-sub">{{ $receivedPercent }}% of the estimate</p>
                </div>
            </div>
            <div class="master-stat master-stat--flat {{ $totals['outstanding'] > 0 ? 'orange' : 'teal' }}">
                <span class="icon" aria-hidden="true"><i class="fa-solid fa-scale-balanced"></i></span>
                <div>
                    <p class="master-stat-title">Outstanding</p>
                    <p class="master-stat-value">{{ $money($totals['outstanding']) }}</p>
                    <p class="master-sub">
                        {{ $totals['outstanding'] > 0 ? 'Still to be paid by the client' : 'Nothing pending' }}
                    </p>
                </div>
            </div>
            <div class="master-stat master-stat--flat {{ $totals['net'] >= 0 ? 'teal' : 'red' }}">
                <span class="icon" aria-hidden="true"><i class="fa-solid fa-chart-line"></i></span>
                <div>
                    <p class="master-stat-title">{{ $totals['net'] >= 0 ? 'Profit' : 'Loss' }}</p>
                    <p class="master-stat-value">{{ $money(abs($totals['net'])) }}</p>
                    <p class="master-sub">Expenses {{ $money($totals['outward']) }}</p>
                </div>
            </div>
        </div>

        {{-- Every panel has its own URL, so a tab can be shared, bookmarked,
             opened in a window, and the back button works — and a form posted
             from a tab returns to that tab, which the button strip could not do. --}}
        <div class="master-tabs-card">
            <nav class="master-tabs" role="tablist" aria-label="Project sections">
                @foreach ($tabs as $key => $label)
                    <a class="master-tab {{ $tab === $key ? 'is-active' : '' }}" role="tab"
                        id="project-tab-{{ $key }}" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                        href="{{ $recordUrl($key) }}">
                        {{ $label }}
                        @if (($tabCounts[$key] ?? 0) > 0)
                            <span class="master-tab-count">{{ number_format($tabCounts[$key]) }}</span>
                        @endif
                    </a>
                @endforeach
            </nav>

            <div class="master-tabs-panels">
                @if ($tab === 'products')
                    @include('projects.partials.record-products')
                @elseif ($tab === 'milestones')
                    @include('projects.partials.milestones-tab')
                @elseif ($tab === 'payments')
                    @include('projects.partials.record-money')
                @elseif ($tab === 'shipments')
                    @include('projects.partials.record-shipments')
                @elseif ($tab === 'attachments')
                    @include('projects.partials.record-documents')
                @elseif ($tab === 'comments')
                    @include('projects.partials.record-comments')
                @elseif ($tab === 'tracking')
                    @include('projects.partials.record-activity')
                @elseif ($tab === 'feedback')
                    <section class="master-tab-panel" id="project-panel-feedback" role="tabpanel"
                        aria-labelledby="project-tab-feedback">
                        @include('projects.partials.feedback-tab', ['project' => $project])
                    </section>
                @elseif ($tab === 'logs')
                    @include('projects.partials.record-logs')
                @else
                    @include('projects.partials.record-overview')
                @endif
            </div>
        </div>

        {{-- The status form lives in the shared drawer: it is the one control on
             this page that changes what the record says, and it does not need to
             occupy the header for the rest of the visit. --}}
        <x-drawer id="projectStatusDrawer" title="Update status" eyebrow="Project status"
            subtitle="Status, stage, health and progress in one place. The project's page reads them everywhere." size="medium">
            <form method="POST" action="{{ route('projects.status.update', $project) }}" id="projectStatusForm">
                @csrf
                @method('PATCH')
                <section class="core-drawer-section">
                    <h3 class="core-drawer-section-title">Where it stands</h3>
                    <div class="project-status-form">
                        <label class="master-field">
                            <span class="master-label">Status</span>
                            <select class="master-select" name="status">
                                @foreach ($statusOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($project->status === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="master-field">
                            <span class="master-label">Stage</span>
                            <select class="master-select" name="stage">
                                @foreach ($stageOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($project->stage === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="master-field">
                            <span class="master-label">Health</span>
                            <select class="master-select" name="health">
                                @foreach ($healthOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($project->health === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="master-field">
                            <span class="master-label">Progress</span>
                            <input class="master-input" type="number" name="progress_percent" min="0" max="100"
                                value="{{ $progress }}">
                            <small class="master-help">A percentage, 0 to 100.</small>
                        </label>
                    </div>
                </section>
                <x-slot:footer>
                    <button type="button" class="master-btn master-btn-light" data-drawer-close>Cancel</button>
                    <button class="master-btn master-btn-primary" type="submit">
                        <i class="fa-solid fa-check" aria-hidden="true"></i> Update status
                    </button>
                </x-slot:footer>
            </form>
        </x-drawer>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/projects.css') }}">
@endpush

@push('scripts')
    <script src="{{ $assetVer('assets/js/projects.js') }}"></script>
@endpush
