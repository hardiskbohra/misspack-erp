@php
    $milestones = $project->relationLoaded('milestones') ? $project->milestones : collect();
    $milestoneTotal = $milestones->count();
    $milestoneCompleted = $milestones->where('status', 'completed')->count();
    $milestonePublic = $milestones->where('is_public', true)->count();
    $milestoneOverdue = $milestones
        ->filter(function ($milestone) {
            return method_exists($milestone, 'isOverdue') && $milestone->isOverdue();
        })
        ->count();
    $milestoneProgress = $milestoneTotal ? (int) round($milestones->avg('progress_percent')) : 0;
    $productsWithMilestones = $project->products;
    $projectLevelMilestones = $milestones->whereNull('project_product_id')->values();
@endphp

<section class="pd-tab-panel" id="pd-tab-milestones" data-tab-panel="milestones" role="tabpanel">
    <div class="pmile-page">
        <div class="pmile-stats">
            <div><span>Total Milestones</span><strong>{{ $milestoneTotal }}</strong></div>
            <div><span>Completed</span><strong class="green">{{ $milestoneCompleted }}</strong></div>
            <div><span>Public To Client</span><strong class="blue">{{ $milestonePublic }}</strong></div>
            <div><span>Overdue / Attention</span><strong class="red">{{ $milestoneOverdue }}</strong></div>
            <div><span>Average Progress</span><strong>{{ $milestoneProgress }}%</strong></div>
        </div>

        <div class="pmile-actions-card">
            <div class="pd-section-head">
                <div>
                    <p class="pd-eyebrow">Product Timeline</p>
                    <h2>Project Milestone Timelines</h2>
                </div>
                <div class="pmile-head-actions">
                    @if (\Illuminate\Support\Facades\Route::has('projects.milestones.defaults'))
                        <form method="POST" action="{{ route('projects.milestones.defaults', $project) }}"
                            data-confirm="Generate default milestones for all project products? Existing milestones will not be duplicated."">
                            @csrf
                            <button type="submit" class="master-btn master-btn-soft"><i
                                    class="fa-solid fa-wand-magic-sparkles"></i> Generate Default Timeline</button>
                        </form>
                    @endif
                </div>
            </div>

            
        </div>

        <div class="pmile-product-list">
            @if ($projectLevelMilestones->count())
                @include('projects.partials.milestone-product-block', [
                    'title' => 'Project Level Milestones',
                    'productMilestones' => $projectLevelMilestones,
                    'projectProduct' => null,
                ])
            @endif

            @forelse($productsWithMilestones as $projectProduct)
                @php($productMilestones = $milestones->where('project_product_id', $projectProduct->id)->values())
                @include('projects.partials.milestone-product-block', [
                    'title' => $projectProduct->product_name,
                    'productMilestones' => $productMilestones,
                    'projectProduct' => $projectProduct,
                ])
            @empty
                @if (!$projectLevelMilestones->count())
                    <div class="pd-empty">Add products first, then generate product-wise milestone timelines.</div>
                @endif
            @endforelse
        </div>
    </div>

    <div class="pmile-modal" id="editMilestoneModal" aria-hidden="true">
        <div class="pmile-modal-backdrop" data-close-milestone-modal></div>
        <div class="pmile-modal-card">
            <form method="POST" action="#" id="editMilestoneForm">
                @csrf
                @method('PATCH')
                <input type="hidden" name="milestone_key" id="edit_milestone_key">
                <input type="hidden" name="project_product_id" id="edit_project_product_id">

                <div class="pmile-modal-head">
                    <div>
                        <p class="pd-eyebrow">Edit Timeline Step</p>
                        <h3 id="editMilestoneModalTitle">Edit Milestone</h3>
                    </div>
                    <div>
                        <button type="button" class="pmile-modal-close" data-close-milestone-modal><i
                            class="fa-solid fa-xmark"></i></button>
                    </div>
                </div>

                <div class="pmile-modal-body">
                    <div class="pmile-modal-grid">
                        <div class="master-field full">
                            <label class="master-label">Title</label>
                            <input class="master-input" type="text" name="title" id="edit_title" required>
                        </div>

                        <div class="master-field">
                            <label class="master-label">Status</label>
                            <select class="master-select" name="status" id="edit_status">
                                @foreach ($milestoneStatusOptions as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="master-field">
                            <label class="master-label">Progress %</label>
                            <input class="master-input" type="number" name="progress_percent" id="edit_progress_percent" min="0"
                                max="100">
                        </div>

                        <div class="master-field">
                            <label class="master-label">Planned Start</label>
                            <input class="master-input" type="date" name="planned_start_date" id="edit_planned_start_date">
                        </div>

                        <div class="master-field">
                            <label class="master-label">Planned End</label>
                            <input class="master-input" type="date" name="planned_end_date" id="edit_planned_end_date">
                        </div>

                        <div class="master-field">
                            <label class="master-label">Actual Start</label>
                            <input class="master-input" type="date" name="actual_start_date" id="edit_actual_start_date">
                        </div>

                        <div class="master-field">
                            <label class="master-label">Actual End</label>
                            <input class="master-input" type="date" name="actual_end_date" id="edit_actual_end_date">
                        </div>

                        <div class="master-field">
                            <label class="master-label">Owner</label>
                            <select class="master-select" name="owner_id" id="edit_owner_id">
                                <option value="">No owner</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="master-field">
                            <label class="master-label">Sort Order</label>
                            <input class="master-input" type="number" name="sort_order" id="edit_sort_order" min="0">
                        </div>

                        <label class="master-check"><input type="checkbox" name="is_public" id="edit_is_public"
                                value="1"> Public for client</label>
                        <label class="master-check"><input type="checkbox" name="is_required" id="edit_is_required"
                                value="1"> Required for progress</label>

                        <div class="master-field">
                            <label class="master-label">Client Note</label>
                            <textarea class="master-textarea" name="client_note" id="edit_client_note" rows="2"></textarea>
                        </div>

                        <div class="master-field">
                            <label class="master-label">Internal Notes</label>
                            <textarea class="master-textarea" name="internal_notes" id="edit_internal_notes" rows="2"></textarea>
                        </div>

                        <div class="master-field">
                            <label class="master-label">General Notes</label>
                            <textarea class="master-textarea" name="notes" id="edit_notes" rows="2"></textarea>
                        </div>

                        <div class="master-field">
                            <label class="master-label">Blocked Reason</label>
                            <textarea class="master-textarea" name="blocked_reason" id="edit_blocked_reason" rows="2"></textarea>
                        </div>
                    </div>
                </div>

                <div class="pmile-modal-footer">
                    <button type="button" class="master-btn master-btn-soft" data-close-milestone-modal>Cancel</button>
                    <button type="submit" class="master-btn master-btn-primary">Save Milestone</button>
                </div>
            </form>
            <div>
                <form method="POST" action="#" id="deleteMilestoneForm" class="pmile-modal-delete-form"
                    data-confirm="Delete this milestone?"">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="pd-link-danger"><i class="fa-solid fa-trash"></i> Delete Milestone</button>
                </form>
            </div>
        </div>
    </div>
</section>


@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/projects.css') }}">
@endpush
