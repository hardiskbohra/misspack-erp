@php
    $count = $productMilestones->count();
    $completed = $productMilestones->where('status', 'completed')->count();
    $progress = $count ? (int) round($productMilestones->avg('progress_percent')) : 0;
@endphp
<div class="pmile-product-block">
    <div class="pmile-product-head">
        <div>
            <h3>{{ $title }}</h3>
            <small>{{ $count }} {{ \Illuminate\Support\Str::plural('milestone', $count) }} · {{ $completed }} completed</small>
        </div>
        <div class="pmile-product-progress">
            <div class="pd-progress-text"><span>Overall Progress</span><strong>{{ $progress }}%</strong></div>
            <div class="pd-progress"><span style="width: {{ $progress }}%"></span></div>
        </div>
    </div>

    @if ($count)
        <div class="pmile-step-wrapper">
            <div class="pmile-step-scroll">
                <div class="pmile-stepper">
                    @foreach ($productMilestones as $milestone)
                        @php
                            $connectorClass = $milestone->status === 'completed'
                                ? 'done'
                                : ($milestone->status === 'in_progress' ? 'active' : '');
                            $plannedRange = optional($milestone->planned_start_date)->format('d M Y')
                                . ($milestone->planned_end_date ? ' → '.optional($milestone->planned_end_date)->format('d M Y') : '');
                            $actualRange = optional($milestone->actual_start_date)->format('d M Y')
                                . ($milestone->actual_end_date ? ' → '.optional($milestone->actual_end_date)->format('d M Y') : '');
                        @endphp
                        <div class="pmile-step status-{{ $milestone->statusClass() }}">
                            <div class="pmile-step-connector {{ $connectorClass }}"></div>
                            <div class="pmile-step-node">
                                @if ($milestone->status === 'completed')
                                    <i class="fas fa-check"></i>
                                @else
                                    <span>{{ $loop->iteration }}</span>
                                @endif
                            </div>
                            <div class="pmile-step-card">
                                <div class="pmile-step-card-head">
                                    <div class="pmile-step-title-wrap">
                                        <h4>{{ $milestone->title }}</h4>
                                        @if ($milestone->description)
                                            <span class="pmile-step-subtitle">{{ \Illuminate\Support\Str::limit($milestone->description, 72) }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="pmile-step-badges">
                                    <span class="pmile-status">{{ $milestone->statusLabel() }}</span>
                                </div>
                                <div class="pmile-step-progress-row">
                                    <div class="pmile-progress"><span style="width: {{ (int) $milestone->progress_percent }}%"></span></div>
                                    <strong>{{ (int) $milestone->progress_percent }}%</strong>
                                </div>
                                <div class="pmile-step-dates">
                                    <div><span>Planned</span><strong>{{ $plannedRange ?: '—' }}</strong></div>
                                    <div><span>Actual</span><strong>{{ $actualRange ?: '—' }}</strong></div>
                                </div>
                                @if ($milestone->client_note)
                                    <p class="pmile-notes">{{ $milestone->client_note }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @else
        <div class="pmile-empty">There are no published milestones yet.</div>
    @endif
</div>
