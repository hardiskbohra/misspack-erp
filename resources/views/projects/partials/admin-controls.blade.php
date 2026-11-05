@if (auth()->user()?->canOverrideProjects())
    @php($projectIsClosed = in_array($project->status, \App\Models\Project::closureStatuses(), true))
    <section class="master-card master-card--flat project-detail-card project-admin-controls" id="project-admin-controls" aria-labelledby="project-admin-controls-title">
        <div class="master-section-head">
            <div>
                <h2 class="master-section-title" id="project-admin-controls-title">Administrator controls</h2>
                <p class="master-sub">Set published state or make an explicit closure decision. Workflow evidence and financial records remain separate.</p>
            </div>
            @if ($workflow)
                <a href="{{ $recordUrl('workflow') }}#workflow-history" class="master-btn master-btn-soft master-btn-sm">Review workflow rules</a>
            @endif
        </div>
        <div class="project-admin-summary">
            <span>Published status: <strong>{{ $project->statusLabel() }}</strong>@if($project->manual_status !== null && ! $projectIsClosed) · Administrator-set @endif</span>
            @if ($projectIsClosed && $project->manual_status !== null)
                <span>Status override resumes after reopening: <strong>{{ \App\Models\Project::manualStatusOptions()[$project->manual_status] ?? $project->manual_status }}</strong></span>
            @endif
            <span>Published progress: <strong>{{ $progress }}%</strong>@if($project->manual_progress_percent !== null) · Administrator-set @endif</span>
            <span>Calculated workflow: <strong>{{ $workflow ? $workflow->calculated_progress.'% · '.$workflow->stageLabel() : 'Not started' }}</strong></span>
        </div>
        <form method="POST" action="{{ route('projects.admin-state', $project) }}" class="project-admin-state-form">
            @csrf
            @method('PATCH')
            <label class="master-field" for="project-admin-status">
                <span class="master-label">Project status</span>
                <select class="master-select" id="project-admin-status" name="manual_status" @disabled($projectIsClosed)>
                    <option value="">Use workflow status</option>
                    @foreach (\App\Models\Project::manualStatusOptions() as $key => $label)
                        <option value="{{ $key }}" @selected(old('manual_status', $project->manual_status) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                @if ($projectIsClosed)
                    <input type="hidden" name="manual_status" value="{{ $project->manual_status ?? '' }}">
                    <small class="master-help">Reopen the project to change its status.</small>
                @endif
            </label>
            <label class="master-field" for="project-admin-progress">
                <span class="master-label">Progress percentage</span>
                <input class="master-input" id="project-admin-progress" name="manual_progress_percent" type="number" min="0" max="100" step="1" value="{{ old('manual_progress_percent', $project->manual_progress_percent) }}" placeholder="Use workflow value">
                <small class="master-help">Leave blank to use calculated progress.</small>
            </label>
            <label class="master-field project-admin-reason" for="project-admin-reason">
                <span class="master-label">Reason for this change</span>
                <textarea class="master-textarea" id="project-admin-reason" name="reason" rows="2" minlength="10" maxlength="2000" required placeholder="Explain the updated project state"></textarea>
            </label>
            <button class="master-btn master-btn-primary" type="submit">Save administrator state</button>
        </form>
        <p class="master-help">Overrides stay in effect until cleared. They do not complete workflow steps, approve files or settle balances.</p>

        @if (! $projectIsClosed)
            <details class="project-admin-closure">
                <summary class="master-btn master-btn-danger">Manually close project</summary>
                <form method="POST" action="{{ route('projects.workflow.financial-closure.manual-close', $project) }}" class="project-inline-form">
                    @csrf
                    <div class="master-alert warning">
                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                        <div><strong>Manual closure does not mean financially complete.</strong>
                            <span>
                                @if ($financialClosureSnapshot)
                                    {{ count($financialClosureSnapshot['open_operational_steps']) }} operational controls remain open.
                                    {{ $financialClosureSnapshot['has_outstanding_financials'] ? 'Outstanding financial items exist.' : 'No outstanding financial items were found in the current snapshot.' }}
                                @else
                                    The standard workflow will be started to record an accurate closure snapshot.
                                @endif
                                Invoices, bills, payments and other obligations remain active.
                            </span>
                        </div>
                    </div>
                    <label class="master-field" for="project-manual-close-reason">
                        <span class="master-label">Reason and follow-up plan</span>
                        <textarea class="master-textarea" id="project-manual-close-reason" name="reason" rows="3" minlength="20" maxlength="2000" required placeholder="Explain the unfinished work or balances and who will follow up"></textarea>
                    </label>
                    <label class="project-risk-acknowledgement"><input type="checkbox" name="risk_acknowledgement" value="1" required><span>I understand that outstanding work and financial obligations remain active and are not settled by this closure.</span></label>
                    <button class="master-btn master-btn-danger" type="submit">Record decision and close</button>
                </form>
            </details>
        @else
            <details class="project-admin-closure">
                <summary class="master-btn master-btn-light">Reopen project</summary>
                <form method="POST" action="{{ route('projects.workflow.financial-closure.reopen', $project) }}" class="project-inline-form">
                    @csrf
                    <label class="master-field" for="project-admin-reopen-reason"><span class="master-label">Reason for reopening</span><textarea class="master-textarea" id="project-admin-reopen-reason" name="reason" rows="2" minlength="10" maxlength="2000" required></textarea></label>
                    <button class="master-btn master-btn-primary" type="submit">Reopen with audit record</button>
                </form>
            </details>
        @endif
    </section>
@endif
