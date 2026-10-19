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
                <div class="master-section-head"><div><h2 class="master-section-title">Commercial and dispatch authorization gates</h2><p class="master-sub">Financial checks use the selected Proforma and receipts; dispatch authorization uses exact verified packing allocations, QC, approvals and paperwork. Material changes create a new auditable evaluation.</p></div><form method="POST" action="{{ route('projects.workflow.evaluate',$project) }}">@csrf<button class="master-btn master-btn-soft master-btn-sm" type="submit"><i class="fa-solid fa-rotate"></i> Run gate checks</button></form></div>
                @php
                    $currentGates = $workflow->evaluations->whereNull('project_product_id')->whereIn('gate_key', \App\Services\ProjectWorkflowGates::AUTOMATED_GATES)->unique('gate_key');
                    $currentGateIds = $currentGates->pluck('id');
                    $previousGates = $workflow->evaluations->whereNull('project_product_id')->whereIn('gate_key', \App\Services\ProjectWorkflowGates::AUTOMATED_GATES)->reject(fn ($gate) => $currentGateIds->contains($gate->id));
                @endphp
                @if($currentGates->isEmpty())
                    <div class="master-empty-state"><i class="fa-solid fa-shield-halved"></i><p>Select the commercial baseline and run gate checks to evaluate the production advance and complete pre-dispatch balance.</p></div>
                @else
                    <div class="project-gate-list">
                        @foreach($currentGates as $evaluation)
                            @php
                                $gateDetails = $evaluation->details ?: [];
                                $effectiveResult = $evaluation->effectiveResult();
                            @endphp
                            <article class="project-gate is-{{ $effectiveResult }}">
                                <div class="project-gate-icon"><i class="fa-solid {{ $effectiveResult==='passed' ? 'fa-circle-check' : ($effectiveResult==='overridden' ? 'fa-triangle-exclamation' : 'fa-circle-xmark') }}"></i></div>
                                <div class="project-gate-copy"><div><strong>{{ $evaluation->gateLabel() }}</strong><span class="master-badge is-{{ $effectiveResult }}">{{ $evaluation->resultLabel() }}</span></div><p>{{ $evaluation->summary }}</p><small>Checked {{ $evaluation->evaluated_at?->format('d M Y, H:i') }} by {{ $evaluation->evaluator?->name ?: 'System' }}</small>
                                    @if(array_key_exists('required_amount',$gateDetails))<div class="project-gate-money"><span>Required <strong>{{ $money($gateDetails['required_amount']) }}</strong></span><span>Received <strong>{{ $money($gateDetails['received_amount'] ?? 0) }}</strong></span><span>Shortfall <strong>{{ $money($gateDetails['shortfall'] ?? 0) }}</strong></span></div>@endif
                                    @if($evaluation->gate_key===\App\Services\ShipmentDispatchAuthorization::GATE_KEY && !empty($gateDetails['issues']))<div class="project-approval-warning"><i class="fa-solid fa-triangle-exclamation"></i><span>{{ implode(' ', $gateDetails['issues']) }}</span></div>@endif
                                    @if($evaluation->activeOverride)<div class="project-gate-override"><strong>Manager override by {{ $evaluation->activeOverride->approver?->name ?: 'Administrator' }}</strong><span>{{ $evaluation->activeOverride->reason }}</span>@if($evaluation->activeOverride->expected_resolution_date)<small>Expected resolution {{ $evaluation->activeOverride->expected_resolution_date->format('d M Y') }}</small>@endif</div>@endif
                                </div>
                                <div class="project-gate-actions">
                                    @if($evaluation->result==='blocked' && !$evaluation->activeOverride && auth()->user()?->isAdmin())
                                        <details><summary class="master-btn master-btn-soft master-btn-sm">Manager override</summary><form method="POST" action="{{ route('projects.workflow.gates.override',[$project,$evaluation]) }}">@csrf<textarea class="master-textarea" name="reason" rows="3" minlength="10" maxlength="2000" required placeholder="Risk and reason for proceeding"></textarea><input class="master-input" type="date" name="expected_resolution_date" aria-label="Expected resolution date"><button class="master-btn master-btn-primary master-btn-sm" type="submit">Approve override</button></form></details>
                                    @elseif($evaluation->activeOverride && auth()->user()?->isAdmin())
                                        <details><summary class="master-btn master-btn-light master-btn-sm">Revoke override</summary><form method="POST" action="{{ route('projects.workflow.overrides.revoke',[$project,$evaluation->activeOverride]) }}">@csrf @method('PATCH')<textarea class="master-textarea" name="revocation_reason" rows="2" minlength="5" maxlength="2000" required placeholder="Why this override is revoked"></textarea><button class="master-btn master-btn-light master-btn-sm" type="submit">Revoke</button></form></details>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                    @if($previousGates->isNotEmpty())
                        <details class="project-gate-history"><summary>Previous gate evaluations ({{ $previousGates->count() }})</summary><div>@foreach($previousGates->take(12) as $pastGate)<span><strong>{{ $pastGate->gateLabel() }}</strong><small>{{ $pastGate->resultLabel() }} · {{ $pastGate->evaluated_at?->format('d M Y, H:i') }}</small><em>{{ $pastGate->summary }}</em></span>@endforeach</div></details>
                    @endif
                @endif
            </section>

            <section class="master-card master-card--flat master-section project-detail-card">
                <div class="master-section-head"><div><h2 class="master-section-title">Client artwork and PPS approvals</h2><p class="master-sub">Every decision belongs to one exact file version. A revision makes the previous decision historical.</p></div></div>
                <form method="POST" enctype="multipart/form-data" action="{{ route('projects.approvals.store',$project) }}" class="project-approval-create">@csrf
                    <label class="master-field"><span class="master-label">Product</span><select class="master-select" name="project_product_id" required><option value="">Select product</option>@foreach($project->products->where('status','!=','cancelled') as $product)<option value="{{ $product->id }}">{{ $product->product_name }}</option>@endforeach</select></label>
                    <label class="master-field"><span class="master-label">Approval type</span><select class="master-select" name="approval_type" required>@foreach(\App\Models\ProjectApprovalRequest::typeOptions() as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
                    <label class="master-field"><span class="master-label">Title</span><input class="master-input" name="title" maxlength="255" required placeholder="Front artwork approval"></label>
                    <label class="master-field"><span class="master-label">Response due</span><input class="master-input" type="datetime-local" name="response_due_at"></label>
                    <label class="master-field"><span class="master-label">Approval file</span><input class="master-input" type="file" name="approval_file" required accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.ppt,.pptx,.zip"></label>
                    <label class="master-field project-approval-instructions"><span class="master-label">Instructions</span><input class="master-input" name="instructions" maxlength="2000" placeholder="What the client should review"></label>
                    <button class="master-btn master-btn-primary" type="submit"><i class="fa-solid fa-paper-plane"></i> Send approval request</button>
                </form>
                <div class="project-approval-list">
                    @forelse($workflow->approvals as $approval)
                        <article class="project-approval is-{{ $approval->effectiveStatus() }}">
                            <header><div><strong>{{ $approval->title }}</strong><span>{{ $approval->typeLabel() }} · {{ $approval->product?->product_name }} · version {{ $approval->current_version_number }}</span></div><span class="master-badge">{{ $approval->statusLabel() }}</span></header>
                            @if($approval->isOverdue())<div class="project-approval-warning"><i class="fa-solid fa-triangle-exclamation"></i> Response overdue since {{ $approval->response_due_at->format('d M Y, H:i') }}. The request remains open.</div>@endif
                            <div class="project-approval-current"><div><span>Current file</span><a href="{{ $approval->currentVersion?->attachment?->fileUrl() }}" target="_blank">{{ $approval->currentVersion?->attachment?->original_name ?: 'File unavailable' }}</a></div><div><span>Client response</span><strong>{{ $approval->currentVersion?->statusLabel() ?: 'Pending' }}</strong>@if($approval->currentVersion?->client_comment)<small>{{ $approval->currentVersion->client_comment }}</small>@endif</div></div>
                            @if($approval->hasActiveOverride())<div class="project-approval-override"><strong>Manager override by {{ $approval->activeOverrideRecord()?->approver?->name ?: 'Administrator' }}</strong><span>{{ $approval->activeOverrideRecord()?->reason }}</span></div>@endif
                            <div class="project-approval-actions">
                                <details><summary class="master-btn master-btn-soft master-btn-sm">Add revision</summary><form method="POST" enctype="multipart/form-data" action="{{ route('projects.approvals.versions.store',[$project,$approval]) }}">@csrf<input class="master-input" type="file" name="approval_file" required><input class="master-input" type="datetime-local" name="response_due_at"><textarea class="master-textarea" name="submission_notes" rows="2" placeholder="What changed"></textarea><button class="master-btn master-btn-primary master-btn-sm">Send new version</button></form></details>
                                @if(auth()->user()?->isAdmin() && $approval->status!=='approved' && !$approval->hasActiveOverride())<details><summary class="master-btn master-btn-light master-btn-sm">Manager override</summary><form method="POST" action="{{ route('projects.approvals.override',[$project,$approval]) }}">@csrf<textarea class="master-textarea" name="override_reason" minlength="10" maxlength="2000" required placeholder="Reason and accepted risk"></textarea><button class="master-btn master-btn-primary master-btn-sm">Approve override</button></form></details>@endif
                                @if(auth()->user()?->isAdmin() && $approval->hasActiveOverride())<details><summary class="master-btn master-btn-light master-btn-sm">Revoke override</summary><form method="POST" action="{{ route('projects.approvals.override.revoke',[$project,$approval]) }}">@csrf @method('PATCH')<textarea class="master-textarea" name="override_revocation_reason" minlength="5" maxlength="2000" required placeholder="Reason for revocation"></textarea><button class="master-btn master-btn-light master-btn-sm">Revoke</button></form></details>@endif
                                <details><summary>Version history</summary><div class="project-approval-history">@foreach($approval->versions as $version)<span><strong>Version {{ $version->version_number }} · {{ $version->statusLabel() }}</strong><small>{{ $version->submitted_at?->format('d M Y, H:i') }}@if($version->clientUser) · {{ $version->clientUser->displayName() }}@endif</small>@if($version->client_comment)<em>{{ $version->client_comment }}</em>@endif</span>@endforeach</div></details>
                            </div>
                        </article>
                    @empty
                        <div class="master-empty-state"><i class="fa-solid fa-stamp"></i><p>No artwork or PPS approval requests have been sent yet.</p></div>
                    @endforelse
                </div>
            </section>

            <section class="master-card master-card--flat master-section project-detail-card">
                <div class="master-section-head"><div><h2 class="master-section-title">Product operational readiness</h2><p class="master-sub">Each product is evaluated independently through procurement, production, QC, packing, partial dispatch and delivery.</p></div><form method="POST" action="{{ route('projects.workflow.evaluate',$project) }}">@csrf<button class="master-btn master-btn-soft master-btn-sm" type="submit"><i class="fa-solid fa-rotate"></i> Refresh readiness</button></form></div>
                @php
                    $deliveryReconciliation = $workflow->evaluations->whereNull('project_product_id')->firstWhere('gate_key', \App\Services\ProjectOperationalReadiness::GATE_DELIVERY_RECONCILED);
                    $deliveryResult = $deliveryReconciliation?->effectiveResult() ?? 'pending';
                @endphp
                <article class="project-gate is-{{ $deliveryResult }}">
                    <div class="project-gate-icon"><i class="fa-solid {{ $deliveryResult==='passed' ? 'fa-circle-check' : ($deliveryResult==='overridden' ? 'fa-triangle-exclamation' : 'fa-truck-ramp-box') }}"></i></div>
                    <div class="project-gate-copy"><div><strong>Project delivery reconciliation</strong><span class="master-badge is-{{ $deliveryResult }}">{{ $deliveryReconciliation?->resultLabel() ?: 'Not evaluated' }}</span></div><p>{{ $deliveryReconciliation?->summary ?: 'Refresh readiness to reconcile delivered quantities across all active products.' }}</p></div>
                    <div class="project-gate-actions">
                        @if($deliveryReconciliation && $deliveryReconciliation->result==='blocked' && !$deliveryReconciliation->activeOverride && auth()->user()?->isAdmin())<details><summary class="master-btn master-btn-soft master-btn-sm">Manager override</summary><form method="POST" action="{{ route('projects.workflow.gates.override',[$project,$deliveryReconciliation]) }}">@csrf<textarea class="master-textarea" name="reason" rows="3" minlength="10" maxlength="2000" required placeholder="Risk and reason for reconciling delivery"></textarea><input class="master-input" type="date" name="expected_resolution_date"><button class="master-btn master-btn-primary master-btn-sm">Approve override</button></form></details>
                        @elseif($deliveryReconciliation?->activeOverride && auth()->user()?->isAdmin())<details><summary class="master-btn master-btn-light master-btn-sm">Revoke override</summary><form method="POST" action="{{ route('projects.workflow.overrides.revoke',[$project,$deliveryReconciliation->activeOverride]) }}">@csrf @method('PATCH')<textarea class="master-textarea" name="revocation_reason" minlength="5" maxlength="2000" required></textarea><button class="master-btn master-btn-light master-btn-sm">Revoke</button></form></details>@endif
                    </div>
                </article>
                <div class="project-approval-list">
                    @foreach($project->products->where('status','!=','cancelled') as $product)
                        @php
                            $productEvaluations = $workflow->evaluations->where('project_product_id',$product->id)->unique('gate_key')->keyBy('gate_key');
                            $passedQc = (float) $product->qualityInspections->sum('passed_quantity');
                            $remainingQc = max((float)$product->quantity-$passedQc,0);
                        @endphp
                        <article class="project-approval">
                            <header><div><strong>{{ $product->product_name }}</strong><span>{{ number_format((float)$product->quantity,3) }} {{ $product->unit }} required</span></div><span class="master-badge">Independent product</span></header>
                            <div class="project-gate-list">
                                @foreach(\App\Services\ProjectOperationalReadiness::PRODUCT_CONTROLLED_STEPS as $gateKey)
                                    @php
                                        $evaluation = $productEvaluations->get($gateKey);
                                        $effective = $evaluation?->effectiveResult() ?? 'pending';
                                        $details = $evaluation?->details ?: [];
                                    @endphp
                                    <article class="project-gate is-{{ $effective }}">
                                        <div class="project-gate-icon"><i class="fa-solid {{ $effective==='passed' ? 'fa-circle-check' : ($effective==='overridden' ? 'fa-triangle-exclamation' : 'fa-circle-xmark') }}"></i></div>
                                        <div class="project-gate-copy"><div><strong>{{ $evaluation?->gateLabel() ?: str($gateKey)->headline() }}</strong><span class="master-badge is-{{ $effective }}">{{ $evaluation?->resultLabel() ?: 'Not evaluated' }}</span></div><p>{{ $evaluation?->summary ?: 'Run readiness checks to capture source evidence.' }}</p>
                                            @if(array_key_exists('required_quantity',$details))
                                                <small>{{ number_format((float)($details['required_quantity']??0),3) }} required · {{ number_format((float)($details['remaining_quantity']??0),3) }} remaining
                                                    @if((float)($details['excess_quantity']??0)>0) · {{ number_format((float)$details['excess_quantity'],3) }} excess @endif
                                                </small>
                                            @endif
                                            @if($evaluation?->activeOverride)<div class="project-gate-override"><strong>Manager override by {{ $evaluation->activeOverride->approver?->name ?: 'Administrator' }}</strong><span>{{ $evaluation->activeOverride->reason }}</span></div>@endif
                                        </div>
                                        <div class="project-gate-actions">
                                            @if($evaluation && $evaluation->result==='blocked' && !$evaluation->activeOverride && auth()->user()?->isAdmin())<details><summary class="master-btn master-btn-soft master-btn-sm">Manager override</summary><form method="POST" action="{{ route('projects.workflow.gates.override',[$project,$evaluation]) }}">@csrf<textarea class="master-textarea" name="reason" rows="3" minlength="10" maxlength="2000" required placeholder="Risk and reason for proceeding"></textarea><input class="master-input" type="date" name="expected_resolution_date"><button class="master-btn master-btn-primary master-btn-sm">Approve override</button></form></details>
                                            @elseif($evaluation?->activeOverride && auth()->user()?->isAdmin())<details><summary class="master-btn master-btn-light master-btn-sm">Revoke override</summary><form method="POST" action="{{ route('projects.workflow.overrides.revoke',[$project,$evaluation->activeOverride]) }}">@csrf @method('PATCH')<textarea class="master-textarea" name="revocation_reason" minlength="5" maxlength="2000" required></textarea><button class="master-btn master-btn-light master-btn-sm">Revoke</button></form></details>@endif
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                            <details><summary class="master-btn master-btn-soft master-btn-sm">Record QC inspection · {{ number_format($remainingQc,3) }} remaining</summary>
                                <form method="POST" enctype="multipart/form-data" action="{{ route('projects.quality-inspections.store',[$project,$product]) }}" class="project-approval-create">@csrf
                                    <label class="master-field"><span class="master-label">Batch / lot</span><input class="master-input" name="batch_reference" maxlength="255"></label>
                                    <label class="master-field"><span class="master-label">Inspected</span><input class="master-input" type="number" name="inspected_quantity" min="0.001" step="0.001" required></label>
                                    <label class="master-field"><span class="master-label">Passed</span><input class="master-input" type="number" name="passed_quantity" min="0" step="0.001" value="0" required></label>
                                    <label class="master-field"><span class="master-label">Rejected</span><input class="master-input" type="number" name="rejected_quantity" min="0" step="0.001" value="0" required></label>
                                    <label class="master-field"><span class="master-label">Rework</span><input class="master-input" type="number" name="rework_quantity" min="0" step="0.001" value="0" required></label>
                                    <label class="master-field"><span class="master-label">Result</span><select class="master-select" name="result" required><option value="passed">Passed</option><option value="passed_with_observations">Passed with observations</option><option value="failed">Failed</option><option value="rework_required">Rework required</option></select></label>
                                    <div class="master-field project-approval-instructions"><span class="master-label">Structured checklist</span>@foreach(\App\Models\ProjectQualityInspection::CHECKS as $key=>$label)<label><input type="hidden" name="checklist[{{ $key }}]" value="0"><input type="checkbox" name="checklist[{{ $key }}]" value="1"> {{ $label }}</label>@endforeach</div>
                                    <label class="master-field"><span class="master-label">Inspection evidence</span><input class="master-input" type="file" name="evidence_file" required accept=".jpg,.jpeg,.png,.webp,.pdf,.xls,.xlsx,.doc,.docx"></label>
                                    <label class="master-field project-approval-instructions"><span class="master-label">Inspection notes</span><textarea class="master-textarea" name="notes" rows="3" maxlength="4000"></textarea></label>
                                    <button class="master-btn master-btn-primary" type="submit">Record immutable inspection</button>
                                </form>
                            </details>
                            @if($product->qualityInspections->isNotEmpty())<details><summary>QC history ({{ $product->qualityInspections->count() }})</summary><div class="project-approval-history">@foreach($product->qualityInspections as $inspection)<span><strong>{{ $inspection->resultLabel() }} · {{ number_format((float)$inspection->passed_quantity,3) }} passed</strong><small>{{ $inspection->inspected_at?->format('d M Y, H:i') }} · {{ $inspection->inspector?->name ?: 'System' }} · evidence SHA-256 {{ substr($inspection->evidence_hash,0,10) }}</small><a href="{{ $inspection->evidenceUrl() }}" target="_blank">{{ $inspection->evidence_original_name }}</a><em>{{ $inspection->notes }}</em></span>@endforeach</div></details>@endif
                        </article>
                    @endforeach
                </div>
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
                                        @if(in_array($step->step_key, array_merge(\App\Services\ProjectWorkflowGates::AUTOMATED_GATES, \App\Services\ProjectOperationalReadiness::CONTROLLED_STEPS, \App\Services\ProjectApprovals::CONTROLLED_STEPS), true))
                                            <div class="project-workflow-step is-automated"><div class="project-workflow-step-copy"><i class="fa-solid {{ in_array($step->status,['completed','skipped']) ? 'fa-circle-check' : ($step->status==='blocked' ? 'fa-circle-xmark' : 'fa-circle') }}"></i><div><strong>{{ $step->title }}</strong><span>{{ in_array($step->step_key, \App\Services\ProjectApprovals::CONTROLLED_STEPS, true) ? 'Client approval' : 'Automated gate' }} · {{ $step->statusLabel() }}</span>@if($step->blocked_reason)<small>{{ $step->blocked_reason }}</small>@endif</div></div><span class="master-badge">Controlled by {{ in_array($step->step_key, \App\Services\ProjectApprovals::CONTROLLED_STEPS, true) ? 'approval version' : 'gate checks' }}</span></div>
                                        @else
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
                                        @endif
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
