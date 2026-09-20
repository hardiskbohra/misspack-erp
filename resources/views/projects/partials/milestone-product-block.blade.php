@php
    $count = $productMilestones->count();
    $completed = $productMilestones->where('status', 'completed')->count();
    $progress = $count ? (int) round($productMilestones->avg('progress_percent')) : 0;
@endphp
<div class="pmile-product-block">
    <div class="pmile-product-head">
        <div>
            <h3 style="color:blue;">{{ $title }}</h3>
            <small>{{ $count }} milestones · {{ $completed }} completed</small>
        </div>
        <div class="pmile-product-progress">
            <div class="pd-progress-text"><span>Overall Progress</span><strong> - {{ $progress }}%</strong></div>
            <div class="pd-progress"><span style="width: {{ $progress }}%"></span></div>
        </div>
    </div>

    @if ($count)
        {{-- PRODUCT MILESTONE STEP INDICATOR --}}
        <div class="pmile-step-wrapper">

            <div class="pmile-step-scroll">

                <div class="pmile-step-line">

                    @foreach ($productMilestones as $index => $milestone)
                        @php
                            $isCompleted =
                                $milestone->progress_percent >= 100 || strtolower($milestone->status) === 'completed';

                            $isCurrent =
                                !$isCompleted &&
                                (strtolower($milestone->status) === 'in_progress' ||
                                    strtolower($milestone->status) === 'in progress' ||
                                    $milestone->progress_percent > 0);

                            $isOverdue = $milestone->isOverdue();

                            $stepClass = $isCompleted ? 'completed' : ($isCurrent ? 'current' : 'upcoming');

                            $nodeLabel = $milestone->status === 'completed' ? '✓' : $loop->iteration;
                            $connectorClass =
                                $milestone->status === 'completed'
                                    ? 'done'
                                    : ($milestone->status === 'in_progress'
                                        ? 'active'
                                        : '');
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
                        @endphp

                        <div class="pmile-step {{ $stepClass }} {{ $isOverdue ? 'overdue' : '' }}">

                            {{-- STEP CIRCLE --}}
                            <div class="pmile-step-circle">

                                @if ($isCompleted)
                                    <i class="fas fa-check"></i>
                                @else
                                    <span>{{ $index + 1 }}</span>
                                @endif

                            </div>

                            {{-- STEP CONTENT --}}
                            <div class="pmile-step-content">

                                <div class="pmile-step-title-row">

                                    <h4>
                                        {{ $milestone->title }}
                                    </h4>

                                    {{-- EDIT --}}
                                    <button type="button" class="master-icon-btn master-btn-sm editMilestoneBtn"
                                        title="Edit milestone" data-milestone='@json($milestonePayload)'
                                        data-update-url="{{ route('projects.milestones.update', $milestone) }}"
                                        data-delete-url="{{ route('projects.milestones.destroy', $milestone) }}">
                                        <i class="fas fa-pen"></i>
                                    </button>

                                </div>

                                {{-- STATUS --}}
                                <div class="pmile-step-status">

                                    <span class="pmile-step-status-badge">
                                        {{ $milestone->statusLabel() }}
                                    </span>

                                    @if ($isOverdue)
                                        <span class="pmile-step-overdue">
                                            Overdue
                                        </span>
                                    @endif

                                </div>

                                {{-- DATE --}}
                                <div class="pmile-step-date">

                                    @if ($milestone->planned_start_date)
                                        {{ optional($milestone->planned_start_date)->format('d M') }}

                                        @if ($milestone->planned_end_date)
                                            → {{ optional($milestone->planned_end_date)->format('d M') }}
                                        @endif
                                    @else
                                        -
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @else
        <div class="pmile-empty">No milestones for this product yet.</div>
    @endif
</div>
