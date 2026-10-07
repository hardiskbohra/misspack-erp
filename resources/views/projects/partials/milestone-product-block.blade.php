@php
    $count = $productMilestones->count();
    $completed = $productMilestones->where('status', 'completed')->count();
    $progress = $count ? (int) round($productMilestones->avg('progress_percent')) : 0;
@endphp
<div class="master-card master-card--flat project-detail-card">
    <div class="pmile-product-head">
        <div>
            <h3 class="master-section-title">{{ $title }}</h3>
            <span class="master-sub">{{ $count }} {{ Str::plural('milestone', $count) }} · {{ $completed }} completed</span>
        </div>
        <div class="pmile-product-progress">
            <div class="project-progress-row">
                <span class="project-record-progress-track" role="img" aria-label="{{ $progress }} percent complete">
                    <span style="width: {{ $progress }}%"></span></span>
                <strong>{{ $progress }}%</strong>
            </div>
        </div>
    </div>

    @if ($count)
        <div class="pmile-step-wrapper">
            <div class="pmile-step-scroll">
                <div class="pmile-stepper">

                    @foreach ($productMilestones as $milestone)
                        @php
                            $isOverdue = method_exists($milestone, 'isOverdue') && $milestone->isOverdue();
                            $nodeState = $milestone->status;
                            $connectorClass = $milestone->status === 'completed'
                                ? 'done'
                                : ($milestone->status === 'in_progress' ? 'active' : '');
                            $milestonePayload = [
                                'id' => $milestone->id,
                                'project_product_id' => $milestone->project_product_id,
                                'milestone_key' => $milestone->milestone_key,
                                'title' => $milestone->title,
                                'description' => $milestone->description,
                                'status' => $milestone->status,
                                'progress_percent' => $milestone->progress_percent,
                                'planned_start_date' => optional($milestone->planned_start_date)->format('Y-m-d'),
                                'planned_end_date' => optional($milestone->planned_end_date)->format('Y-m-d'),
                                'actual_start_date' => optional($milestone->actual_start_date)->format('Y-m-d'),
                                'actual_end_date' => optional($milestone->actual_end_date)->format('Y-m-d'),
                                'owner_id' => $milestone->owner_id,
                                'is_public' => (bool) $milestone->is_public,
                                'is_required' => (bool) $milestone->is_required,
                                'sort_order' => $milestone->sort_order,
                                'notes' => $milestone->notes,
                                'internal_notes' => $milestone->internal_notes,
                                'client_note' => $milestone->client_note,
                                'blocked_reason' => $milestone->blocked_reason,
                            ];
                            $plannedRange = optional($milestone->planned_start_date)->format('d M Y')
                                . ($milestone->planned_end_date ? ' → ' . optional($milestone->planned_end_date)->format('d M Y') : '');
                            $actualRange = optional($milestone->actual_start_date)->format('d M Y')
                                . ($milestone->actual_end_date ? ' → ' . optional($milestone->actual_end_date)->format('d M Y') : '');
                        @endphp

                        <div class="pmile-step status-{{ $milestone->statusClass() }} {{ $isOverdue ? 'overdue' : '' }}">

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
                                            <span class="pmile-step-subtitle">{{ Str::limit($milestone->description, 52) }}</span>
                                        @endif
                                    </div>

                                    @if (! empty($editable))
                                        <button type="button" class="master-icon-btn master-btn-sm editMilestoneBtn"
                                            title="Edit milestone" data-milestone='@json($milestonePayload)'
                                            data-update-url="{{ route('projects.milestones.update', $milestone) }}"
                                            data-delete-url="{{ route('projects.milestones.destroy', $milestone) }}">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                    @endif
                                </div>

                                <div class="pmile-step-badges">
                                    <span class="master-badge status-{{ str_replace('_', '-', (string) $milestone->status) }}">{{ $milestone->statusLabel() }}</span>
                                    @if ($isOverdue)
                                        <span class="master-badge health-red">Overdue</span>
                                    @endif
                                    @if ($milestone->is_public)
                                        <span class="master-badge health-green">Public</span>
                                    @else
                                        <span class="master-badge status-draft">Internal</span>
                                    @endif
                                    @if ($milestone->is_required)
                                        <span class="master-badge status-planned">Required</span>
                                    @endif
                                </div>

                                <div class="pmile-step-progress-row">
                                    <div class="pmile-progress"><span style="width: {{ (int) $milestone->progress_percent }}%"></span></div>
                                    <strong>{{ (int) $milestone->progress_percent }}%</strong>
                                </div>

                                <div class="pmile-step-dates">
                                    <div><span>Planned</span><strong>{{ $plannedRange ?: '—' }}</strong></div>
                                    <div><span>Actual</span><strong>{{ $actualRange ?: '—' }}</strong></div>
                                </div>

                                @if ($milestone->relationLoaded('owner') && $milestone->owner)
                                    <div class="pmile-step-owner"><i class="fas fa-user"></i> {{ $milestone->owner->name }}</div>
                                @endif

                                @if ($milestone->blocked_reason)
                                    <p class="pmile-notes danger">{{ $milestone->blocked_reason }}</p>
                                @elseif($milestone->client_note)
                                    <p class="pmile-notes">{{ $milestone->client_note }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach

                </div>
            </div>
        </div>
    @else
        <div class="master-empty-state">
            <i class="fa-solid fa-flag-checkered" aria-hidden="true"></i>
            <p>No milestone on this product yet. Add one, or generate the default timeline from above.</p>
        </div>
    @endif
</div>
