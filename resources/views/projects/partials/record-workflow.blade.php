<section class="master-tab-panel" id="project-panel-workflow" role="tabpanel" aria-labelledby="project-tab-workflow">
    <div class="project-blocks">
        @if (!$workflow)
            <section class="master-card master-card--flat master-section project-detail-card">
                <div class="master-empty-state">
                    <i class="fa-solid fa-diagram-project" aria-hidden="true"></i>
                    <p>Start the standard execution workflow to coordinate commercial readiness, every product, packing, dispatch and closure.</p>
                    <form method="POST" action="{{ route('projects.workflow.start', $project) }}">@csrf
                        <button class="master-btn master-btn-primary" type="submit"><i class="fa-solid fa-play"></i> Start standard workflow</button>
                    </form>
                </div>
            </section>
        @else
            @php
                $reportedVariance = $workflow->reported_progress === null ? null : $workflow->reported_progress - $workflow->calculated_progress;
                $groupedSteps = $workflow->steps->groupBy('stage_key');
            @endphp
            <div class="master-stats" aria-label="Workflow summary">
                <div class="master-stat master-stat--flat blue"><span class="icon"><i class="fa-solid fa-route"></i></span><div><p class="master-stat-title">Calculated stage</p><p class="master-stat-value">{{ $workflow->stageLabel() }}</p><p class="master-sub">From required execution steps</p></div></div>
                <div class="master-stat master-stat--flat green"><span class="icon"><i class="fa-solid fa-chart-line"></i></span><div><p class="master-stat-title">Calculated progress</p><p class="master-stat-value">{{ $workflow->calculated_progress }}%</p><p class="master-sub">Weighted completed work</p></div></div>
                <div class="master-stat master-stat--flat purple"><span class="icon"><i class="fa-solid fa-user-pen"></i></span><div><p class="master-stat-title">Coordinator report</p><p class="master-stat-value">{{ $workflow->reported_progress === null ? 'Not reported' : $workflow->reported_progress.'%' }}</p><p class="master-sub">{{ $workflow->reported_stage ? ($workflowStages[$workflow->reported_stage] ?? $workflow->reported_stage) : 'No manual stage' }}</p></div></div>
                <div class="master-stat master-stat--flat {{ $reportedVariance !== null && abs($reportedVariance) > 15 ? 'orange' : 'teal' }}"><span class="icon"><i class="fa-solid fa-code-compare"></i></span><div><p class="master-stat-title">Reported variance</p><p class="master-stat-value">{{ $reportedVariance === null ? '—' : (($reportedVariance > 0 ? '+' : '').$reportedVariance.'%') }}</p><p class="master-sub">Manual versus calculated</p></div></div>
            </div>

            <section class="master-card master-card--flat master-section project-detail-card">
                <div class="master-section-head"><div><h2 class="master-section-title">Coordinator report</h2><p class="master-sub">Manual reporting stays alongside the calculated workflow and never bypasses a gate.</p></div></div>
                <form method="POST" action="{{ route('projects.workflow.report', $project) }}" class="project-workflow-report">@csrf @method('PATCH')
                    <label class="master-field"><span class="master-label">Reported stage</span><select class="master-select" name="reported_stage" required>@foreach($workflowStages as $key=>$label)<option value="{{ $key }}" @selected(old('reported_stage',$workflow->reported_stage ?: $workflow->calculated_stage)===$key)>{{ $label }}</option>@endforeach</select></label>
                    <label class="master-field"><span class="master-label">Reported progress</span><input class="master-input" type="number" name="reported_progress" min="0" max="100" value="{{ old('reported_progress',$workflow->reported_progress ?? $workflow->calculated_progress) }}" required></label>
                    <label class="master-field"><span class="master-label">Commercial baseline PI</span><select class="master-select" name="commercial_sales_invoice_id"><option value="">Not selected</option>@foreach($project->proformaInvoices as $invoice)<option value="{{ $invoice->id }}" @selected((string)old('commercial_sales_invoice_id',$workflow->commercial_sales_invoice_id)===(string)$invoice->id)>{{ $invoice->invoice_number }} · {{ $money($invoice->total_amount) }}</option>@endforeach</select></label>
                    <label class="master-field"><span class="master-label">Advance override %</span><input class="master-input" type="number" name="advance_percentage_override" min="0" max="100" step="0.01" value="{{ old('advance_percentage_override',$workflow->advance_percentage_override) }}" placeholder="Use PI terms"></label>
                    <label class="master-field project-workflow-report-note"><span class="master-label">Reason / execution update</span><textarea class="master-textarea" name="reported_reason" rows="3" maxlength="2000" required>{{ old('reported_reason',$workflow->reported_reason) }}</textarea></label>
                    <div class="project-workflow-report-action"><button class="master-btn master-btn-primary" type="submit"><i class="fa-solid fa-check"></i> Save report</button></div>
                </form>
            </section>

            <section class="master-card master-card--flat master-section project-detail-card">
                <div class="master-section-head"><div><h2 class="master-section-title">Standard execution path</h2><p class="master-sub">Project-level controls and product-level execution remain separate while contributing to one calculated progress.</p></div></div>
                <div class="project-workflow-stages">
                    @foreach($workflowStages as $stageKey=>$stageLabel)
                        @if($groupedSteps->has($stageKey))
                            @php
                                $stageSteps = $groupedSteps[$stageKey];
                                $stageDone = $stageSteps->whereIn('status', ['completed', 'skipped'])->count();
                            @endphp
                            <section class="project-workflow-stage {{ $workflow->calculated_stage === $stageKey ? 'is-current' : '' }}">
                                <header><div><span>{{ str_pad((string)$loop->iteration,2,'0',STR_PAD_LEFT) }}</span><div><h3>{{ $stageLabel }}</h3><p>{{ $stageDone }} of {{ $stageSteps->count() }} steps complete</p></div></div><strong>{{ $stageSteps->count() ? round($stageDone/$stageSteps->count()*100) : 0 }}%</strong></header>
                                <div class="project-workflow-step-list">
                                    @foreach($stageSteps as $step)
                                        <form method="POST" action="{{ route('projects.workflow.steps.update',[$project,$step]) }}" class="project-workflow-step">@csrf @method('PATCH')
                                            <div class="project-workflow-step-copy"><i class="fa-solid {{ in_array($step->status,['completed','skipped']) ? 'fa-circle-check' : ($step->status==='blocked' ? 'fa-circle-xmark' : 'fa-circle') }}"></i><div><strong>{{ $step->title }}</strong><span>{{ $step->product ? $step->product->product_name : 'Project-level control' }} · {{ $step->owner?->name ?: 'Coordinator unassigned' }}</span>@if($step->blocked_reason)<small>{{ $step->blocked_reason }}</small>@endif</div></div>
                                            <select class="master-select" name="status" aria-label="Status for {{ $step->title }}">
                                                @foreach($workflowStepStatuses as $key=>$label)
                                                    @continue(in_array($key, ['skipped', 'cancelled'], true))
                                                    <option value="{{ $key }}" @selected($step->status===$key)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <input class="master-input" name="blocked_reason" value="{{ $step->blocked_reason }}" placeholder="Blocked reason">
                                            <input type="hidden" name="notes" value="{{ $step->notes }}">
                                            <button class="master-btn master-btn-soft master-btn-sm" type="submit">Update</button>
                                        </form>
                                    @endforeach
                                </div>
                            </section>
                        @endif
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</section>
