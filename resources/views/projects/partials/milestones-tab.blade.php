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
                            onsubmit="return confirm('Generate default milestones for all project products? Existing milestones will not be duplicated.');">
                            @csrf
                            <button type="submit" class="pd-btn pd-btn-soft"><i
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
                            <input type="text" name="title" id="edit_title" required>
                        </div>

                        <div class="master-field">
                            <label class="master-label">Status</label>
                            <select name="status" id="edit_status">
                                @foreach ($milestoneStatusOptions as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="master-field">
                            <label class="master-label">Progress %</label>
                            <input type="number" name="progress_percent" id="edit_progress_percent" min="0"
                                max="100">
                        </div>

                        <div class="master-field">
                            <label class="master-label">Planned Start</label>
                            <input type="date" name="planned_start_date" id="edit_planned_start_date">
                        </div>

                        <div class="master-field">
                            <label class="master-label">Planned End</label>
                            <input type="date" name="planned_end_date" id="edit_planned_end_date">
                        </div>

                        <div class="master-field">
                            <label class="master-label">Actual Start</label>
                            <input type="date" name="actual_start_date" id="edit_actual_start_date">
                        </div>

                        <div class="master-field">
                            <label class="master-label">Actual End</label>
                            <input type="date" name="actual_end_date" id="edit_actual_end_date">
                        </div>

                        <div class="master-field">
                            <label class="master-label">Owner</label>
                            <select name="owner_id" id="edit_owner_id">
                                <option value="">No owner</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="master-field">
                            <label class="master-label">Sort Order</label>
                            <input type="number" name="sort_order" id="edit_sort_order" min="0">
                        </div>

                        <label class="pd-check"><input type="checkbox" name="is_public" id="edit_is_public"
                                value="1"> Public for client</label>
                        <label class="pd-check"><input type="checkbox" name="is_required" id="edit_is_required"
                                value="1"> Required for progress</label>

                        <div class="master-field">
                            <label class="master-label">Client Note</label>
                            <textarea name="client_note" id="edit_client_note" rows="2"></textarea>
                        </div>

                        <div class="master-field">
                            <label class="master-label">Internal Notes</label>
                            <textarea name="internal_notes" id="edit_internal_notes" rows="2"></textarea>
                        </div>

                        <div class="master-field">
                            <label class="master-label">General Notes</label>
                            <textarea name="notes" id="edit_notes" rows="2"></textarea>
                        </div>

                        <div class="master-field">
                            <label class="master-label">Blocked Reason</label>
                            <textarea name="blocked_reason" id="edit_blocked_reason" rows="2"></textarea>
                        </div>
                    </div>
                </div>

                <div class="pmile-modal-footer">
                    <button type="button" class="pd-btn pd-btn-soft" data-close-milestone-modal>Cancel</button>
                    <button type="submit" class="pd-btn pd-btn-primary">Save Milestone</button>
                </div>
            </form>
            <div>
                <form method="POST" action="#" id="deleteMilestoneForm" class="pmile-modal-delete-form"
                    onsubmit="return confirm('Delete this milestone?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="pd-link-danger"><i class="fa-solid fa-trash"></i> Delete Milestone</button>
                </form>
            </div>
        </div>
    </div>
</section>

<style>
    .pmile-page {
        display: flex;
        flex-direction: column;
        gap: 16px
    }

    .pmile-stats {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 12px
    }

    .pmile-stats div {
        background: #f8faff;
        border: 1px solid #edf2ff;
        border-radius: 16px;
        padding: 13px
    }

    .pmile-stats span {
        display: block;
        color: #7b8495;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase
    }

    .pmile-stats strong {
        display: block;
        margin-top: 6px;
        font-size: 22px;
        color: #172033
    }

    .pmile-stats .green {
        color: #039855
    }

    .pmile-stats .blue {
        color: #2563eb
    }

    .pmile-stats .red {
        color: #d92d20
    }

    .pmile-actions-card {
        padding: 18px
    }

    .pmile-head-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap
    }

    .pmile-create {
        background: #f8faff;
        border: 1px solid #edf2ff;
        border-radius: 18px;
        padding: 14px
    }

    .pmile-create summary {
        cursor: pointer;
        color: #4f83f1;
        font-weight: 600;
        margin-bottom: 12px
    }

    .pmile-form {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
        align-items: end
    }

    .pmile-product-list {
        display: flex;
        flex-direction: column;
        gap: 16px
    }

    .pmile-product-block {
        background: #fff;
        border: 1px solid #edf0f7;
        border-radius: 22px;
        padding: 16px
    }

    .pmile-product-head {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: center;
        border-bottom: 1px solid #edf0f7;
        padding-bottom: 14px;
        margin-bottom: 14px
    }

    .pmile-product-head h3 {
        margin: 0;
        color: #172033
    }

    .pmile-product-head small {
        color: #7b8495;
        font-weight: 500
    }

    .pmile-product-progress {
        min-width: 220px
    }

    .pmile-stepper-wrap {
        width: 100%;
        overflow-x: auto;
        overflow-y: hidden;
        padding: 8px 2px 16px;
        scrollbar-width: thin
    }

    .pmile-stepper {
        display: flex;
        align-items: flex-start;
        gap: 0;
        min-width: max-content;
        position: relative;
        padding: 8px 0 4px
    }

    .pmile-step {
        position: relative;
        flex: 0 0 285px;
        min-width: 285px;
        padding: 0 12px
    }

    .pmile-step-connector {
        position: absolute;
        top: 22px;
        left: 50%;
        width: 100%;
        height: 4px;
        background: #e8eef8;
        z-index: 0
    }

    .pmile-step-connector.done {
        background: #12b76a
    }

    .pmile-step-connector.active {
        background: linear-gradient(90deg, #4f83f1, #e8eef8)
    }

    .pmile-step-node {
        position: relative;
        z-index: 2;
        width: 46px;
        height: 46px;
        border-radius: 50%;
        background: #f3f6fb;
        border: 4px solid #fff;
        box-shadow: 0 0 0 2px #dfe7f3, 0 8px 18px rgba(25, 42, 70, .12);
        display: grid;
        place-items: center;
        color: #667085;
        font-weight: 600;
        margin: 0 auto 12px
    }

    .pmile-step.status-completed .pmile-step-node {
        background: #12b76a;
        color: #fff;
        box-shadow: 0 0 0 2px #abefc6, 0 8px 18px rgba(18, 183, 106, .22)
    }

    .pmile-step.status-in-progress .pmile-step-node {
        background: #4f83f1;
        color: #fff;
        box-shadow: 0 0 0 2px #bfd7ff, 0 8px 18px rgba(79, 131, 241, .24)
    }

    .pmile-step.status-waiting .pmile-step-node {
        background: #f59e0b;
        color: #fff;
        box-shadow: 0 0 0 2px #fedf89, 0 8px 18px rgba(245, 158, 11, .22)
    }

    .pmile-step.status-blocked .pmile-step-node,
    .pmile-step.overdue .pmile-step-node {
        background: #ef4770;
        color: #fff;
        box-shadow: 0 0 0 2px #fecdca, 0 8px 18px rgba(239, 71, 112, .22)
    }

    .pmile-step-card {
        background: #fff;
        border: 1px solid #edf0f7;
        border-radius: 18px;
        padding: 14px;
        box-shadow: 0 8px 22px rgba(25, 42, 70, .06);
        min-height: 238px
    }

    .pmile-step.overdue .pmile-step-card {
        border-color: #fecdca;
        background: #fffafa
    }

    .pmile-step-card-head {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        align-items: flex-start;
        margin-bottom: 10px
    }

    .pmile-step-card .master-icon-btn {
        width: 34px;
        height: 34px;
        border: 0;
        border-radius: 11px;
        background: #eef3ff;
        color: #4f83f1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        flex: 0 0 34px
    }

    .pmile-step-card .master-icon-btn:hover {
        background: #e0ebff
    }

    .pmile-step-title-wrap {
        min-width: 0
    }

    .pmile-step-card h4 {
        margin: 0;
        color: #172033;
        font-size: 15px;
        line-height: 1.35;
        overflow-wrap: anywhere
    }

    .pmile-step-subtitle {
        display: block;
        color: #7b8495;
        font-size: 11px;
        font-weight: 600;
        margin-top: 3px
    }

    .pmile-step-badges {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        margin-bottom: 10px
    }

    .pmile-status,
    .pmile-public,
    .pmile-required,
    .pmile-overdue {
        border-radius: 999px;
        padding: 5px 8px;
        font-size: 10.5px;
        font-weight: 600
    }

    .pmile-status {
        background: #eef3ff;
        color: #4f83f1
    }

    .pmile-public {
        background: #e8fff3;
        color: #039855
    }

    .pmile-private {
        background: #f3f4f6;
        color: #667085
    }

    .pmile-required {
        background: #fff7e6;
        color: #b54708
    }

    .pmile-overdue {
        background: #fff1f3;
        color: #d92d20
    }

    .pmile-step-progress-row {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 8px;
        align-items: center;
        margin-bottom: 10px
    }

    .pmile-step-progress-row strong {
        font-size: 12px;
        color: #172033
    }

    .pmile-progress {
        height: 8px;
        background: #eef2f7;
        border-radius: 999px;
        overflow: hidden
    }

    .pmile-progress span {
        display: block;
        height: 100%;
        background: linear-gradient(90deg, #4f83f1, #12b76a)
    }

    .pmile-step.status-completed .pmile-progress span {
        background: #12b76a
    }

    .pmile-step.status-blocked .pmile-progress span {
        background: #ef4770
    }

    .pmile-step-dates {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        margin: 10px 0
    }

    .pmile-step-dates div {
        background: #f8faff;
        border: 1px solid #edf2ff;
        border-radius: 12px;
        padding: 8px
    }

    .pmile-step-dates span {
        display: block;
        color: #7b8495;
        font-size: 10.5px;
        font-weight: 600;
        text-transform: uppercase
    }

    .pmile-step-dates strong {
        display: block;
        margin-top: 3px;
        font-size: 12px;
        color: #172033
    }

    .pmile-step-owner {
        font-size: 12px;
        color: #667085;
        font-weight: 600
    }

    .pmile-notes {
        color: #344054;
        font-size: 12px;
        white-space: pre-wrap;
        margin: 8px 0 0
    }

    .pmile-notes.danger {
        color: #d92d20
    }

    .pmile-edit {
        margin-top: 10px
    }

    .pmile-edit summary {
        cursor: pointer;
        color: #4f83f1;
        font-weight: 600
    }

    .pmile-edit-form {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 9px;
        margin-top: 10px;
        background: #f8faff;
        border: 1px solid #edf2ff;
        border-radius: 14px;
        padding: 12px
    }

    .pmile-edit-form .pd-field.full {
        grid-column: 1/-1
    }

    .pmile-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center
    }

    .pmile-delete-form {
        margin: 0
    }

    .pmile-empty {
        padding: 18px;
        text-align: center;
        border: 1px dashed #d8deea;
        border-radius: 16px;
        color: #7b8495;
        background: #fbfcff;
        font-weight: 600
    }

    .pmile-modal {
        position: fixed;
        inset: 0;
        z-index: 10050;
        display: none
    }

    .pmile-modal.is-open {
        display: block
    }

    .pmile-modal-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, .58);
        backdrop-filter: blur(6px)
    }

    .pmile-modal-card {
        position: relative;
        width: min(780px, calc(100% - 24px));
        max-height: 92vh;
        margin: 4vh auto;
        background: #fff;
        border: 1px solid #edf0f7;
        border-radius: 24px;
        box-shadow: 0 24px 80px rgba(15, 23, 42, .28);
        overflow: hidden;
        display: flex;
        flex-direction: column
    }

    .pmile-modal-head {
        padding: 18px 20px;
        border-bottom: 1px solid #edf0f7;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px
    }

    .pmile-modal-head h3 {
        margin: 0;
        color: #172033;
        font-size: 20px
    }

    .pmile-modal-close {
        border: 0;
        background: #f3f6fb;
        color: #667085;
        width: 38px;
        height: 38px;
        border-radius: 13px;
        cursor: pointer
    }

    .pmile-modal-body {
        padding: 18px 20px;
        overflow: auto
    }

    .pmile-modal-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px
    }

    .pmile-modal-grid .pd-field.full {
        grid-column: 1/-1
    }

    .pmile-modal-footer {
        padding: 16px 20px;
        border-top: 1px solid #edf0f7;
        display: flex;
        justify-content: flex-end;
        gap: 10px
    }

    .pmile-modal-delete-form {
        padding: 0 20px 18px;
        display: flex;
        justify-content: flex-start
    }

    @media(max-width:1399px) {
        .pmile-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr))
        }

        .pmile-form {
            grid-template-columns: repeat(2, minmax(0, 1fr))
        }
    }

    @media(max-width:767px) {

        .pmile-stats,
        .pmile-form,
        .pmile-edit-form,
        .pmile-modal-grid {
            grid-template-columns: 1fr
        }

        .pmile-product-head {
            flex-direction: column;
            align-items: flex-start
        }

        .pmile-product-progress {
            min-width: 0;
            width: 100%
        }

        .pmile-step {
            flex-basis: 255px;
            min-width: 255px;
            padding: 0 9px
        }

        .pmile-step-card {
            min-height: 220px
        }

        .pmile-step-dates {
            grid-template-columns: 1fr
        }

        .pmile-modal-card {
            width: 100%;
            max-height: calc(100vh - 20px);
            margin: 10px auto;
            border-radius: 20px
        }

        .pmile-modal-footer {
            flex-direction: column-reverse
        }

        .pmile-modal-footer .pd-btn {
            width: 100%
        }

        .pmile-modal-delete-form {
            justify-content: center
        }
    }


    /* =========================================================
   PRODUCT MILESTONE - STEP INDICATOR
   ========================================================= */

    .pmile-step-wrapper {
        width: 100%;
        background: #fff;
        /*border-radius: 14px;*/
        padding: 20px;
        /*border: 1px solid #e8edf5;*/
        box-sizing: border-box;
    }

    .pmile-step-scroll {
        width: 100%;
        overflow-x: auto;
        overflow-y: hidden;
        padding: 10px 5px 20px;
    }

    /* Timeline container */

    .pmile-step-line {
        display: flex;
        align-items: flex-start;
        position: relative;
        min-width: max-content;
        padding: 0 25px;
    }

    /* Horizontal connecting line */

    .pmile-step-line::before {
        content: "";
        position: absolute;
        left: 65px;
        right: 65px;
        top: 23px;
        height: 2px;
        background: #dce3ec;
        z-index: 0;
    }


    /* =========================================================
   INDIVIDUAL STEP
   ========================================================= */

    .pmile-step {
        width: 100px;
        min-width: 100px;
        position: relative;
        text-align: center;
        z-index: 1;
    }


    /* Space between steps */

    .pmile-step+.pmile-step {
        margin-left: 25px;
    }


    /* =========================================================
   STEP CIRCLE
   ========================================================= */

    .pmile-step-circle {
        width: 46px;
        height: 46px;
        border-radius: 50%;
        background: #fff;
        border: 2px solid #cbd4df;

        margin: 0 auto 12px;

        display: flex;
        align-items: center;
        justify-content: center;

        font-size: 14px;
        font-weight: 600;

        position: relative;
        z-index: 2;

        transition: all .2s ease;
    }


    /* Upcoming */

    .pmile-step.upcoming .pmile-step-circle {
        color: #8b96a7;
        border-color: #cbd4df;
        background: #fff;
    }


    /* Completed */

    .pmile-step.completed .pmile-step-circle {
        color: #fff;
        background: #12b76a;
        border-color: #12b76a;
    }


    /* Current */

    .pmile-step.current .pmile-step-circle {
        color: #fff;
        background: #2563eb;
        border-color: #2563eb;

        box-shadow:
            0 0 0 5px rgba(37, 99, 235, .10);
    }


    /* Current animated ring */

    .pmile-step.current .pmile-step-circle::before {
        content: "";
        position: absolute;
        inset: -6px;

        border-radius: 50%;
        border: 2px solid rgba(37, 99, 235, .35);
    }


    /* =========================================================
   CONNECTING LINE COLORS
   ========================================================= */

    /* Completed connection */

    .pmile-step.completed::after {
        content: "";
        position: absolute;

        top: 22px;
        left: calc(50% + 23px);
        width: calc(100% + 25px);
        height: 3px;

        background: #12b76a;

        z-index: 0;
    }


    /* Current connection should remain grey */

    .pmile-step.current::after {
        content: "";
        position: absolute;

        top: 22px;
        left: calc(50% + 23px);
        width: calc(100% + 25px);
        height: 2px;

        background: #dce3ec;

        z-index: 0;
    }


    /* Last item should not create a line */

    .pmile-step:last-child::after {
        display: none;
    }


    /* =========================================================
   TITLE
   ========================================================= */

    .pmile-step-content {
        position: relative;
    }

    .pmile-step-title-row {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-height: 42px;
    }

    .pmile-step-title-row h4 {
        margin: 0;
        color: #172b4d;
        font-size: 14px;
        font-weight: 600;
        line-height: 1.35;
    }


    /* Edit button */

    .pmile-step-edit {
        width: 26px !important;
        height: 26px !important;

        padding: 0 !important;

        flex-shrink: 0;
    }


    /* =========================================================
   STATUS
   ========================================================= */

    .pmile-step-status {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 5px;

        margin-top: 8px;
        min-height: 25px;
    }

    .pmile-step-status-badge {
        display: inline-flex;
        align-items: center;

        padding: 4px 10px;

        border-radius: 20px;

        font-size: 11px;
        font-weight: 600;

        white-space: nowrap;
    }


    /* Completed */

    .pmile-step.completed .pmile-step-status-badge {
        background: #e9f9f1;
        color: #079455;
    }


    /* Current */

    .pmile-step.current .pmile-step-status-badge {
        background: #eaf2ff;
        color: #2563eb;
    }


    /* Upcoming */

    .pmile-step.upcoming .pmile-step-status-badge {
        background: #f1f3f5;
        color: #667085;
    }


    /* Overdue */

    .pmile-step-overdue {
        font-size: 10px;
        padding: 4px 7px;
        border-radius: 12px;

        background: #fff1f3;
        color: #d92d20;
    }


    /* =========================================================
   DATE
   ========================================================= */

    .pmile-step-date {
        margin-top: 8px;

        font-size: 12px;
        color: #667085;

        white-space: nowrap;
    }


    /* =========================================================
   CURRENT STEP DETAIL CARD
   ========================================================= */

    .pmile-current-card {
        margin-top: 18px;

        width: 100%;

        display: grid;

        grid-template-columns:
            1.5fr 1fr 1fr 1.5fr;

        gap: 25px;

        align-items: center;

        padding: 25px 30px;

        background: #f8fbff;

        border: 1px solid #cfe0ff;

        border-radius: 14px;

        box-sizing: border-box;
    }


    /* Current step */

    .pmile-current-main {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .pmile-current-icon {
        width: 54px;
        height: 54px;

        flex-shrink: 0;

        border-radius: 50%;

        background: #2563eb;

        color: #fff;

        display: flex;
        align-items: center;
        justify-content: center;

        font-size: 20px;

        box-shadow: 0 0 0 7px rgba(37, 99, 235, .08);
    }

    .pmile-current-label {
        color: #2563eb;

        font-size: 13px;
        font-weight: 600;

        margin-bottom: 3px;
    }

    .pmile-current-main h3 {
        margin: 0 0 7px;

        color: #172b4d;

        font-size: 18px;
        font-weight: 500;
    }

    .pmile-current-status {
        display: inline-flex;

        padding: 5px 11px;

        background: #eaf2ff;
        color: #2563eb;

        border-radius: 20px;

        font-size: 11px;
        font-weight: 600;
    }


    /* Columns */

    .pmile-current-column {
        padding-left: 25px;

        border-left: 1px solid #e4eaf2;
    }

    .pmile-current-column label,
    .pmile-current-progress label {
        display: block;

        color: #2563eb;

        font-size: 13px;
        font-weight: 600;

        margin-bottom: 8px;
    }

    .pmile-current-column strong {
        color: #344054;

        font-size: 14px;
        font-weight: 600;
    }


    /* =========================================================
   CURRENT PROGRESS
   ========================================================= */

    .pmile-current-progress {
        padding-left: 25px;

        border-left: 1px solid #e4eaf2;
    }

    .pmile-progress-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .pmile-progress-header strong {
        color: #2563eb;
        font-size: 14px;
    }

    .pmile-progress-bar {
        width: 100%;
        height: 9px;

        background: #e8edf4;

        border-radius: 10px;

        overflow: hidden;

        margin-top: 4px;
    }

    .pmile-progress-bar span {
        display: block;

        height: 100%;

        background: #2563eb;

        border-radius: inherit;

        transition: width .3s ease;
    }

    .pmile-on-track {
        margin-top: 9px;

        color: #12b76a;

        font-size: 12px;
        font-weight: 600;
    }

    .pmile-on-track i {
        margin-right: 4px;
    }

    .pmile-on-track-danger {
        color: #d92d20;
    }


    /* =========================================================
   SCROLLBAR
   ========================================================= */

    .pmile-step-scroll::-webkit-scrollbar {
        height: 6px;
    }

    .pmile-step-scroll::-webkit-scrollbar-track {
        background: #f1f3f5;
        border-radius: 10px;
    }

    .pmile-step-scroll::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }


    /* =========================================================
   RESPONSIVE
   ========================================================= */

    @media (max-width: 1000px) {

        .pmile-current-card {
            grid-template-columns: 1fr 1fr;
        }

        .pmile-current-column,
        .pmile-current-progress {
            border-left: none;
            padding-left: 0;
        }

    }


    @media (max-width: 650px) {

        .pmile-step-wrapper {
            padding: 20px 10px;
        }

        .pmile-step-line {
            padding: 0 20px;
        }

        .pmile-step {
            width: 145px;
            min-width: 145px;
        }

        .pmile-step+.pmile-step {
            margin-left: 15px;
        }

        .pmile-current-card {
            grid-template-columns: 1fr;

            padding: 20px;
        }

        .pmile-current-column,
        .pmile-current-progress {
            border-left: none;
            border-top: 1px solid #e4eaf2;

            padding-left: 0;
            padding-top: 15px;
        }

    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var milestoneMap = @json($milestoneOptions);
        var keySelect = document.getElementById('newMilestoneKey');
        var titleInput = document.getElementById('newMilestoneTitle');

        if (keySelect && titleInput) {
            keySelect.addEventListener('change', function() {
                if (keySelect.value !== 'custom' && milestoneMap[keySelect.value]) {
                    titleInput.value = milestoneMap[keySelect.value];
                }
                if (keySelect.value === 'custom') {
                    titleInput.value = '';
                    titleInput.focus();
                }
            });
        }

        var editModal = document.getElementById('editMilestoneModal');
        var editForm = document.getElementById('editMilestoneForm');
        var deleteForm = document.getElementById('deleteMilestoneForm');

        function openMilestoneModal() {
            if (!editModal) return;
            editModal.classList.add('is-open');
            editModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('task-modal-open');
            document.body.style.overflow = 'hidden';
        }

        function closeMilestoneModal() {
            if (!editModal) return;
            editModal.classList.remove('is-open');
            editModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('task-modal-open');
            document.body.style.overflow = '';
        }

        function setField(id, value) {
            var field = document.getElementById(id);
            if (field) field.value = value || '';
        }

        function setChecked(id, value) {
            var field = document.getElementById(id);
            if (field) field.checked = !!value;
        }

        function getMilestonePayload(button) {
            try {
                return JSON.parse(button.getAttribute('data-milestone') || '{}');
            } catch (e) {
                return {};
            }
        }

        document.querySelectorAll('.editMilestoneBtn').forEach(function(button) {
            button.addEventListener('click', function() {
                var milestone = getMilestonePayload(button);

                if (editForm) editForm.action = button.getAttribute('data-update-url') || '#';
                if (deleteForm) deleteForm.action = button.getAttribute('data-delete-url') ||
                    '#';

                var modalTitle = document.getElementById('editMilestoneModalTitle');
                if (modalTitle) modalTitle.textContent = milestone.title ? 'Edit ' + milestone
                    .title : 'Edit Milestone';

                console.log(this.dataset.milestone);

                setField('edit_milestone_key', milestone.milestone_key);
                setField('edit_project_product_id', milestone.project_product_id);
                setField('edit_title', milestone.title);
                setField('edit_status', milestone.status || 'not_started');
                setField('edit_progress_percent', milestone.progress_percent || 0);
                setField('edit_planned_start_date', milestone.planned_start_date);
                setField('edit_planned_end_date', milestone.planned_end_date);
                setField('edit_actual_start_date', milestone.actual_start_date);
                setField('edit_actual_end_date', milestone.actual_end_date);
                setField('edit_owner_id', milestone.owner_id);
                setField('edit_sort_order', milestone.sort_order || 0);
                setField('edit_client_note', milestone.client_note);
                setField('edit_internal_notes', milestone.internal_notes);
                setField('edit_notes', milestone.notes);
                setField('edit_blocked_reason', milestone.blocked_reason);
                setChecked('edit_is_public', milestone.is_public);
                setChecked('edit_is_required', milestone.is_required);

                openMilestoneModal();
            });
        });

        document.querySelectorAll('[data-close-milestone-modal]').forEach(function(button) {
            button.addEventListener('click', closeMilestoneModal);
        });

        editModal?.addEventListener('click', function(event) {
            if (event.target.classList.contains('pmile-modal-backdrop')) {
                closeMilestoneModal();
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && editModal?.classList.contains('is-open')) {
                closeMilestoneModal();
            }
        });
    });
</script>
