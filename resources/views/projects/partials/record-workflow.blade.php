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
                $groupedSteps = $workflow->steps->groupBy('stage_key');
                $currentActions = app(\App\Services\ProjectCoordinatorQueue::class)->currentForWorkflow($workflow);
                $blockedActions = $currentActions->where('is_blocked', true)->count();
                $waitingActions = $currentActions->where('is_waiting', true)->count();
                $overdueActions = $currentActions->where('is_overdue', true)->count();
                $activeOverrides = $workflow->evaluations->filter(fn($evaluation) => $evaluation->activeOverride)->count();
                $workflowSettings = \App\Models\OfficeSetting::workflow();
                $defaultApprovalDue = now()->addDays((int)data_get($workflowSettings, 'deadlines.approval_response_days', 5))->format('Y-m-d\\TH:i');
            @endphp

            <section class="master-card master-card--flat project-detail-card workflow-command-header" aria-labelledby="workflowCommandTitle">
                <div class="workflow-command-main">
                    <div>
                        <span class="workflow-command-eyebrow">Workflow command centre</span>
                        <h2 id="workflowCommandTitle">{{ $workflow->stageLabel() }}</h2>
                        <p>Calculated stage · {{ $currentActions->count() }} current {{ \Illuminate\Support\Str::plural('action', $currentActions->count()) }} · Coordinator: {{ $project->assignedUser?->name ?: 'Unassigned' }}</p>
                    </div>
                    <div class="workflow-command-progress" aria-label="Calculated workflow progress">
                        <strong>{{ $workflow->calculated_progress }}%</strong>
                        <span><i style="width:{{ $workflow->calculated_progress }}%"></i></span>
                        <small>Calculated progress</small>
                    </div>
                </div>
                <div class="workflow-command-kpis" aria-label="Workflow attention summary">
                    <button type="button" data-workflow-open="actions"><strong>{{ $currentActions->count() }}</strong><span>Current</span></button>
                    <button type="button" data-workflow-open="actions" class="{{ $overdueActions ? 'is-danger' : '' }}"><strong>{{ $overdueActions }}</strong><span>Overdue</span></button>
                    <button type="button" data-workflow-open="actions" class="{{ $blockedActions ? 'is-danger' : '' }}"><strong>{{ $blockedActions }}</strong><span>Blocked</span></button>
                    <button type="button" data-workflow-open="actions" class="{{ $waitingActions ? 'is-warning' : '' }}"><strong>{{ $waitingActions }}</strong><span>Waiting</span></button>
                    <button type="button" data-workflow-open="history" class="{{ $activeOverrides ? 'is-warning' : '' }}"><strong>{{ $activeOverrides }}</strong><span>Overrides</span></button>
                    <button type="button" data-workflow-open="actions" class="{{ $projectExceptions->count() ? 'is-danger' : '' }}"><strong>{{ $projectExceptions->count() }}</strong><span>Exceptions</span></button>
                </div>
                <div class="workflow-stage-rail" aria-label="Workflow stages">
                    @foreach($workflowStages as $stageKey=>$stageLabel)
                        @if($groupedSteps->has($stageKey))
                            @php
                                $railSteps=$groupedSteps[$stageKey];
                                $railDone=$railSteps->whereIn('status',['completed','skipped'])->count();
                                $railBlocked=$railSteps->where('status','blocked')->count();
                                $railCurrent=$workflow->calculated_stage===$stageKey;
                            @endphp
                            <button type="button" data-workflow-open="{{ in_array($stageKey,['commercial','closure'],true)?'commercial':(in_array($stageKey,['setup'],true)?'history':'products') }}" class="{{ $railCurrent?'is-current':'' }} {{ $railBlocked?'is-blocked':'' }} {{ $railDone===$railSteps->count()?'is-complete':'' }}" title="{{ $stageLabel }} · {{ $railDone }}/{{ $railSteps->count() }} cleared"><i></i><span>{{ $stageLabel }}</span></button>
                        @endif
                    @endforeach
                </div>
            </section>

            <div class="master-tabs-card workflow-view-switcher">
                <nav class="master-tabs workflow-view-tabs" role="tablist" aria-label="Workflow views">
                    @foreach(['actions'=>['fa-bolt','Action centre'],'matrix'=>['fa-table-cells-large','Matrix'],'products'=>['fa-boxes-stacked','Products & approvals'],'commercial'=>['fa-file-invoice-dollar','Commercial & closure'],'history'=>['fa-clock-rotate-left','History & controls']] as $viewKey=>$viewMeta)
                        <a href="#workflow-{{ $viewKey }}" role="tab" id="workflow-tab-{{ $viewKey }}" class="master-tab {{ $viewKey==='actions'?'is-active':'' }}" data-workflow-open="{{ $viewKey }}" aria-selected="{{ $viewKey==='actions'?'true':'false' }}"><i class="fa-solid {{ $viewMeta[0] }}" aria-hidden="true"></i><span>{{ $viewMeta[1] }}</span></a>
                    @endforeach
                </nav>
            </div>
            <noscript><style>[data-workflow-view][hidden]{display:block!important}</style></noscript>

            <section id="workflow-actions" class="workflow-action-centre" data-workflow-view="actions" role="tabpanel" aria-labelledby="workflow-tab-actions workflowActionsTitle">
                <div class="workflow-view-heading"><div><span>Work requiring attention</span><h2 id="workflowActionsTitle">Current actions</h2><p>Only work that can move now is shown. Future controls stay out of the way.</p></div><div class="workflow-view-actions"><button type="button" class="master-btn master-btn-light master-btn-sm" data-modal-open="workflowReportModal"><i class="fa-solid fa-user-pen"></i> Coordinator report</button><form method="POST" action="{{ route('projects.workflow.evaluate',$project) }}">@csrf<button class="master-btn master-btn-soft master-btn-sm" type="submit"><i class="fa-solid fa-rotate"></i> Refresh sources</button></form></div></div>
                <div class="workflow-action-list">
                    @forelse($currentActions as $action)
                        @php
                            $actionStep = $action['step'];
                        @endphp
                        @php
                            $guide = $action['guidance'];
                            $sameWorkflow = str_contains($guide['url'], 'tab=workflow') || str_starts_with($guide['url'], '#');
                            $actionEvaluation = $workflow->evaluations->firstWhere('gate_key', $actionStep->step_key);
                            $receiptEvaluation = $guide['modal'] === 'receipt' ? $actionEvaluation : null;
                            $receiptAmount = (float) data_get($receiptEvaluation?->details, 'shortfall', 0);
                            $dispatchIssues = (array) data_get($actionEvaluation?->details, 'issues', []);
                            $blockedReason = $dispatchIssues !== []
                                ? implode(' ', $dispatchIssues)
                                : $actionStep->blocked_reason;
                            $documentShipment = $guide['modal'] === 'dispatch-documents'
                                ? $project->shipments()->where('status', \App\Models\Shipment::STATUS_PLANNING)->latest('id')->first()
                                : null;
                            $dispatchDocuments = $documentShipment
                                ? app(\App\Services\ShipmentDocuments::class)->summary($documentShipment)
                                : null;
                        @endphp
                        <article class="workflow-action-card {{ $action['is_overdue']?'is-overdue':($action['is_blocked']?'is-blocked':($action['is_waiting']?'is-waiting':'')) }}">
                            <div class="workflow-action-card-head">
                                <span class="workflow-action-state"><i class="fa-solid {{ $action['is_overdue']?'fa-clock':($action['is_blocked']?'fa-circle-xmark':($action['is_waiting']?'fa-hourglass-half':'fa-circle-play')) }}"></i></span>
                                <div><span>{{ $action['product']?->product_name ?: 'Project control' }} · {{ $actionStep->title }}</span><h3>{{ $guide['title'] }}</h3></div>
                                <span class="master-badge">{{ $action['attention'] }}</span>
                            </div>
                            <p class="workflow-action-instruction">{{ $guide['instruction'] }}</p>
                            @if($blockedReason)<div class="workflow-action-reason"><strong>Why it is blocked</strong><span>{{ $blockedReason }}</span></div>@endif
                            <ol class="workflow-action-checklist">@foreach($guide['checklist'] as $guideStep)<li><span>{{ $loop->iteration }}</span>{{ $guideStep }}</li>@endforeach</ol>
                            <footer>
                                <div class="workflow-action-meta"><span><i class="fa-solid fa-database"></i>{{ $guide['source'] }}</span><span><i class="fa-solid fa-user"></i>{{ $action['owner']?->name ?: $action['coordinator']?->name ?: 'Unassigned' }}</span><span class="{{ $action['is_overdue']?'is-danger':'' }}"><i class="fa-regular fa-calendar"></i>{{ $action['due_date']?->format('d M Y') ?: 'No due date' }}</span></div>
                                <div class="workflow-action-buttons">
                                    @if($guide['modal']==='receipt' && $workflowReceiptInvoice)
                                        <button type="button" class="master-btn master-btn-primary master-btn-sm" data-open-payment
                                            data-invoice-id="{{ $workflowReceiptInvoice->id }}"
                                            data-invoice-number="{{ $workflowReceiptInvoice->invoice_number }}"
                                            data-invoice-amount="{{ number_format($receiptAmount > 0 ? $receiptAmount : (float)$workflowReceiptInvoice->balanceDue(), 2, '.', '') }}"
                                            data-invoice-balance="{{ \App\Helpers\CommonHelper::indianCurrency($workflowReceiptInvoice->balanceDue()) }}"
                                            data-invoice-client="{{ $workflowReceiptInvoice->client_company_name ?: $project->clientName() }}"
                                            data-invoice-currency="{{ $workflowReceiptInvoice->currency ?: 'INR' }}">
                                            <i class="fa-solid fa-indian-rupee-sign"></i> {{ $guide['label'] }}
                                        </button>
                                    @elseif($guide['modal']==='dispatch-documents' && $documentShipment)
                                        <button type="button" class="master-btn master-btn-primary master-btn-sm" data-modal-open="workflowDispatchDocumentsModal{{ $actionStep->id }}"><i class="fa-solid fa-file-circle-check"></i> {{ $guide['label'] }}</button>
                                    @elseif(in_array($guide['modal'],['production','quality'],true) && $action['product'])
                                        <button type="button" class="master-btn master-btn-primary master-btn-sm" data-modal-open="{{ $guide['modal']==='quality' ? 'workflowQualityModal' : 'workflowProductionModal' }}{{ $action['product']->id }}"><i class="fa-solid {{ $guide['modal']==='quality' ? 'fa-clipboard-check' : 'fa-industry' }}"></i> {{ $guide['label'] }}</button>
                                    @elseif($sameWorkflow)
                                        <button type="button" class="master-btn master-btn-primary master-btn-sm" data-workflow-open="{{ $guide['view'] }}"><i class="fa-solid fa-arrow-right"></i> {{ $guide['label'] }}</button>
                                    @else
                                        <a class="master-btn master-btn-primary master-btn-sm" href="{{ $guide['url'] }}" @if($guide['newTab']) target="_blank" rel="noopener" @endif><i class="fa-solid fa-arrow-up-right-from-square"></i> {{ $guide['label'] }}</a>
                                    @endif
                                </div>
                            </footer>
                        </article>
                        @if($guide['modal']==='production' && $action['product'])
                            <div class="master-modal" id="workflowProductionModal{{ $action['product']->id }}" aria-hidden="true" aria-labelledby="workflowProductionTitle{{ $action['product']->id }}">
                                <div class="master-modal-card workflow-action-modal" role="dialog" aria-modal="true" aria-labelledby="workflowProductionTitle{{ $action['product']->id }}">
                                    <form method="POST" action="{{ route('projects.products.update',$action['product']) }}">@csrf @method('PUT')
                                        <div class="master-modal-header"><div class="master-modal-heading"><span class="master-modal-icon"><i class="fa-solid fa-industry"></i></span><div><h3 class="master-modal-title" id="workflowProductionTitle{{ $action['product']->id }}">Record production · {{ $action['product']->product_name }}</h3><p class="master-modal-subtitle">Update the operational status and production evidence together.</p></div></div><button type="button" class="master-modal-close" data-close-modal aria-label="Close production form">×</button></div>
                                        <div class="master-modal-body">
                                            <div class="workflow-production-summary" aria-label="Production requirement">
                                                <div><span>Required</span><strong>{{ rtrim(rtrim(number_format((float)$action['product']->quantity,3,'.',''),'0'),'.') }} {{ $action['product']->unit }}</strong></div>
                                                <div><span>Recorded</span><strong>{{ rtrim(rtrim(number_format((float)$action['product']->production_completed_quantity,3,'.',''),'0'),'.') }} {{ $action['product']->unit }}</strong></div>
                                                <div><span>Current status</span><strong>{{ $action['product']->statusLabel() }}</strong></div>
                                            </div>
                                            <div class="workflow-production-rule"><i class="fa-solid fa-arrow-right"></i><div><strong>To open Quality inspection</strong><span>Record the full required quantity, add production evidence, and change Product status to <b>QC Pending</b>. Keep <b>In Production</b> when recording a partial quantity.</span></div></div>
                                            <div class="master-modal-grid">
                                                <label class="master-field"><span class="master-label">Product status <b>*</b></span><select class="master-select" name="status" required>@foreach($productStatusOptions as $statusKey => $statusLabel)<option value="{{ $statusKey }}" @selected(old('status',$action['product']->status)===$statusKey)>{{ $statusLabel }}</option>@endforeach</select><small class="master-help">Choose QC Pending when production is fully complete.</small></label>
                                                <label class="master-field"><span class="master-label">Completed quantity to date <b>*</b></span><input class="master-input" type="number" name="production_completed_quantity" min="0" max="{{ $action['product']->quantity }}" step="0.001" value="{{ old('production_completed_quantity',$action['product']->production_completed_quantity ?: 0) }}" required><small class="master-help">Maximum {{ rtrim(rtrim(number_format((float)$action['product']->quantity,3,'.',''),'0'),'.') }} {{ $action['product']->unit }}.</small></label>
                                                <label class="master-field"><span class="master-label">Expected ready date</span><input class="master-input" type="date" name="expected_ready_date" value="{{ old('expected_ready_date',$action['product']->expected_ready_date?->format('Y-m-d')) }}"></label>
                                                <label class="master-field"><span class="master-label">Actual ready date</span><input class="master-input" type="date" name="actual_ready_date" value="{{ old('actual_ready_date',$action['product']->actual_ready_date?->format('Y-m-d')) }}"><small class="master-help">Record when the completed quantity became production-ready.</small></label>
                                                <label class="master-field full"><span class="master-label">Production evidence <b>*</b></span><textarea class="master-textarea" name="production_evidence" rows="3" maxlength="4000" placeholder="Batch, job card, work order or completion report" required>{{ old('production_evidence',$action['product']->production_evidence) }}</textarea><small class="master-help">This evidence is stored with the quantity and person recording it.</small></label>
                                                <label class="master-field full"><span class="master-label">Production notes</span><textarea class="master-textarea" name="notes" rows="3" placeholder="Delays, balance quantity, handover or QC notes">{{ old('notes',$action['product']->notes) }}</textarea></label>
                                            </div>
                                        </div>
                                        <div class="master-modal-footer"><button type="button" class="master-btn master-btn-light" data-close-modal>Cancel</button><button class="master-btn master-btn-primary" type="submit"><i class="fa-solid fa-check"></i> Save production record</button></div>
                                    </form>
                                </div>
                            </div>
                        @endif
                        @if($guide['modal']==='quality' && $action['product'])
                            @php
                                $qcProduct = $action['product'];
                                $latestQc = $qcProduct->qualityInspections->first();
                                $qcInspectionCount = $qcProduct->qualityInspections->count();
                                $qcErrors = ['batch_reference','inspected_quantity','passed_quantity','rejected_quantity','rework_quantity','result','checklist','evidence_file','notes'];
                            @endphp
                            <div class="master-modal" id="workflowQualityModal{{ $qcProduct->id }}" aria-hidden="true" aria-labelledby="workflowQualityTitle{{ $qcProduct->id }}" data-open-on-errors="{{ (int)old('workflow_action_product_id')===$qcProduct->id && $errors->hasAny($qcErrors) ? 'true' : 'false' }}">
                                <div class="master-modal-card workflow-action-modal workflow-quality-modal" role="dialog" aria-modal="true" aria-labelledby="workflowQualityTitle{{ $qcProduct->id }}">
                                    <form method="POST" enctype="multipart/form-data" action="{{ route('projects.quality-inspections.store',[$project,$qcProduct]) }}">@csrf
                                        <input type="hidden" name="workflow_action_product_id" value="{{ $qcProduct->id }}">
                                        <div class="master-modal-header"><div class="master-modal-heading"><span class="master-modal-icon"><i class="fa-solid fa-clipboard-check"></i></span><div><h3 class="master-modal-title" id="workflowQualityTitle{{ $qcProduct->id }}">Record QC inspection · {{ $qcProduct->product_name }}</h3><p class="master-modal-subtitle">Reconcile every inspected unit and preserve the inspection evidence.</p></div></div><button type="button" class="master-modal-close" data-close-modal aria-label="Close quality inspection form">×</button></div>
                                        <div class="master-modal-body">
                                            <div class="workflow-production-summary" aria-label="Quality inspection context"><div><span>Production lot</span><strong>{{ number_format((float)$qcProduct->quantity,3) }} {{ $qcProduct->unit }}</strong></div><div><span>Prior inspections</span><strong>{{ $qcInspectionCount }}</strong></div><div><span>Latest result</span><strong>{{ $latestQc?->resultLabel() ?: 'None recorded' }}</strong></div></div>
                                            <div class="workflow-production-rule"><i class="fa-solid fa-scale-balanced"></i><div><strong>Sample-lot inspection is allowed</strong><span>Inspect any representative sample. Passed + rejected + rework must equal the sample inspected; checklist exceptions and an optional attachment are retained as observations.</span></div></div>
                                            <div class="master-modal-grid">
                                                <label class="master-field"><span class="master-label">Batch / lot</span><input class="master-input" name="batch_reference" maxlength="255" value="{{ old('batch_reference') }}" placeholder="Production batch reference"></label>
                                                <label class="master-field"><span class="master-label">Result <b>*</b></span><select class="master-select" name="result" required><option value="passed" @selected(old('result','passed')==='passed')>Passed</option><option value="passed_with_observations" @selected(old('result')==='passed_with_observations')>Passed with observations</option><option value="failed" @selected(old('result')==='failed')>Failed</option><option value="rework_required" @selected(old('result')==='rework_required')>Rework required</option></select></label>
                                                <label class="master-field"><span class="master-label">Inspected <b>*</b></span><input class="master-input" type="number" name="inspected_quantity" min="0.001" step="0.001" value="{{ old('inspected_quantity') }}" placeholder="Sample quantity" required></label>
                                                <label class="master-field"><span class="master-label">Passed <b>*</b></span><input class="master-input" type="number" name="passed_quantity" min="0" step="0.001" value="{{ old('passed_quantity',0) }}" required></label>
                                                <label class="master-field"><span class="master-label">Rejected <b>*</b></span><input class="master-input" type="number" name="rejected_quantity" min="0" step="0.001" value="{{ old('rejected_quantity',0) }}" required></label>
                                                <label class="master-field"><span class="master-label">Rework <b>*</b></span><input class="master-input" type="number" name="rework_quantity" min="0" step="0.001" value="{{ old('rework_quantity',0) }}" required></label>
                                                <fieldset class="master-field full workflow-quality-checklist"><legend class="master-label">Structured checklist <b>*</b></legend><div>@foreach(\App\Models\ProjectQualityInspection::checks() as $checkKey=>$checkLabel)<label><input type="hidden" name="checklist[{{ $checkKey }}]" value="0"><input type="checkbox" name="checklist[{{ $checkKey }}]" value="1" @checked((bool)old('checklist.'.$checkKey))> <span>{{ $checkLabel }}</span></label>@endforeach</div></fieldset>
                                                <label class="master-field full"><span class="master-label">Inspection evidence <small>Optional</small></span><input class="master-input" type="file" name="evidence_file" accept=".jpg,.jpeg,.png,.webp,.pdf,.xls,.xlsx,.doc,.docx"><small class="master-help">Attach a report, photograph or worksheet when available.</small></label>
                                                <label class="master-field full"><span class="master-label">Inspection notes</span><textarea class="master-textarea" name="notes" rows="3" maxlength="4000" placeholder="Observations, defects, disposition or follow-up">{{ old('notes') }}</textarea></label>
                                            </div>
                                        </div>
                                        <div class="master-modal-footer"><button type="button" class="master-btn master-btn-light" data-close-modal>Cancel</button><button class="master-btn master-btn-primary" type="submit"><i class="fa-solid fa-lock"></i> Record immutable inspection</button></div>
                                    </form>
                                </div>
                            </div>
                        @endif
                        @if($guide['modal']==='dispatch-documents' && $documentShipment && $dispatchDocuments)
                            <div class="master-modal" id="workflowDispatchDocumentsModal{{ $actionStep->id }}" aria-hidden="true" aria-labelledby="workflowDispatchDocumentsTitle{{ $actionStep->id }}">
                                <div class="master-modal-card workflow-action-modal" role="dialog" aria-modal="true" aria-labelledby="workflowDispatchDocumentsTitle{{ $actionStep->id }}">
                                    <form method="POST" enctype="multipart/form-data" action="{{ route('projects.attachments.store',$project) }}">@csrf
                                        <div class="master-modal-header"><div class="master-modal-heading"><span class="master-modal-icon"><i class="fa-solid fa-file-circle-check"></i></span><div><h3 class="master-modal-title" id="workflowDispatchDocumentsTitle{{ $actionStep->id }}">Dispatch documents · {{ $documentShipment->shipment_number }}</h3><p class="master-modal-subtitle">Files are stored once in Project attachments and immediately re-evaluated against dispatch authorization.</p></div></div><button type="button" class="master-modal-close" data-close-modal aria-label="Close dispatch document checklist">×</button></div>
                                        <div class="master-modal-body">
                                            <div class="workflow-production-summary" aria-label="Dispatch document status"><div><span>Required</span><strong>{{ $dispatchDocuments['required'] }}</strong></div><div><span>Complete</span><strong>{{ $dispatchDocuments['done'] }}</strong></div><div><span>Missing</span><strong>{{ count($dispatchDocuments['missing']) }}</strong></div></div>
                                            <div class="workflow-quality-checklist"><div>
                                                @foreach($dispatchDocuments['checklist'] as $documentItem)
                                                    <label><i class="fa-solid {{ $documentItem['done'] ? 'fa-circle-check' : 'fa-circle' }}" style="color:{{ $documentItem['done'] ? '#15803d' : '#94a3b8' }}"></i><span>{{ $documentItem['label'] }} @if($documentItem['key']==='packing_list')<small>· confirmed linked Packaging List</small>@endif</span></label>
                                                @endforeach
                                            </div></div>
                                            <div class="workflow-production-rule"><i class="fa-solid fa-link"></i><div><strong>Packing evidence is automatic</strong><span>No allocation is entered here. A confirmed Packaging List linked to this shipment must match each project product and cover this partial shipment quantity.</span></div></div>
                                            <div class="master-modal-grid">
                                                <label class="master-field"><span class="master-label">Document type <b>*</b></span><select class="master-select" name="category" required><option value="">Select missing document</option>@foreach(\App\Models\ProjectAttachment::dispatchDocumentCategoryOptions() as $categoryKey=>$categoryLabel)<option value="{{ $categoryKey }}">{{ $categoryLabel }}</option>@endforeach</select></label>
                                                <label class="master-field"><span class="master-label">Files <b>*</b></span><input class="master-input" type="file" name="attachments[]" multiple required accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.jpg,.jpeg,.png,.webp"></label>
                                                <label class="master-field full"><span class="master-label">Title</span><input class="master-input" name="title" maxlength="255" placeholder="Optional document title"></label>
                                                <label class="master-field full"><span class="master-label">Notes</span><textarea class="master-textarea" name="notes" rows="2" placeholder="Reference number or dispatch note"></textarea></label>
                                            </div>
                                        </div>
                                        <div class="master-modal-footer"><a class="master-btn master-btn-light" href="{{ route('shipments.edit',$documentShipment) }}"><i class="fa-solid fa-truck-fast"></i> Review shipment</a><button class="master-btn master-btn-primary" type="submit"><i class="fa-solid fa-cloud-arrow-up"></i> Upload to Project</button></div>
                                    </form>
                                </div>
                            </div>
                        @endif
                    @empty
                        <div class="master-empty-state"><i class="fa-solid fa-circle-check"></i><p>No current action remains. Review closure or workflow history for completed evidence.</p></div>
                    @endforelse
                </div>
            </section>

            @if($projectExceptions->isNotEmpty())
                <section class="master-card master-card--flat master-section project-detail-card" data-workflow-view="actions" role="tabpanel" aria-labelledby="workflow-tab-actions">
                    <div class="master-section-head"><div><h2 class="master-section-title">Active exceptions</h2><p class="master-sub">Response plans track ownership and escalation without changing the underlying gate or financial condition.</p></div><a class="master-btn master-btn-danger master-btn-sm" href="{{ route('projects.exceptions.index',['status'=>'active','search'=>$project->project_number]) }}">Open exception register</a></div>
                    <div class="workflow-exception-list">@foreach($projectExceptions as $exception)<article><span class="workflow-exception-state is-{{ $exception->severity }}"><i class="fa-solid fa-triangle-exclamation"></i></span><div><strong>{{ $exception->title }}</strong><p>{{ $exception->summary }}</p></div><div class="workflow-exception-meta"><span class="master-badge">{{ $exception->statusLabel() }}</span><small>{{ ucfirst($exception->severity) }}@if($exception->escalation_level) · escalation L{{ $exception->escalation_level }}@endif · {{ $exception->owner?->name ?: 'Owner unassigned' }}</small></div></article>@endforeach</div>
                </section>
            @endif

            @include('projects.partials.workflow-matrix')

            <section id="workflow-commercial" class="master-card master-card--flat master-section project-detail-card" data-workflow-view="commercial" role="tabpanel" aria-labelledby="workflow-tab-commercial" hidden>
                <div class="master-section-head"><div><h2 class="master-section-title">Commercial and dispatch authorization gates</h2><p class="master-sub">Financial checks use the selected Proforma and receipts; dispatch authorization uses exact confirmed packing allocations that pass automatic checks, QC, approvals and paperwork. Material changes create a new auditable evaluation.</p></div><form method="POST" action="{{ route('projects.workflow.evaluate',$project) }}">@csrf<button class="master-btn master-btn-soft master-btn-sm" type="submit"><i class="fa-solid fa-rotate"></i> Run gate checks</button></form></div>
                @php
                    $visibleGateKeys = array_merge(\App\Services\ProjectWorkflowGates::AUTOMATED_GATES, \App\Services\ProjectFeedbackClosure::CONTROLLED_STEPS);
                    $currentGates = $workflow->evaluations->whereNull('project_product_id')->whereIn('gate_key', $visibleGateKeys)->unique('gate_key');
                    $currentGateIds = $currentGates->pluck('id');
                    $previousGates = $workflow->evaluations->whereNull('project_product_id')->whereIn('gate_key', $visibleGateKeys)->reject(fn ($gate) => $currentGateIds->contains($gate->id));
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
                                    @if($evaluation->gate_key===\App\Services\ProjectFeedbackClosure::GATE)<div class="project-gate-money"><span>Ask <strong>{{ str($gateDetails['request_state'] ?? 'missing')->headline() }}</strong></span><span>Response <strong>{{ !empty($gateDetails['response_id']) ? str($gateDetails['response_band'] ?? 'received')->headline() : 'Awaiting' }}</strong></span><span>Reminders <strong>{{ $gateDetails['reminder_count'] ?? 0 }}/{{ \App\Models\FeedbackRequest::MAX_REMINDERS }}</strong></span><span>Open actions <strong>{{ count($gateDetails['open_action_ids'] ?? []) }}</strong></span></div>@endif
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
                        <details class="project-gate-history"><summary>Previous gate evaluations ({{ $previousGates->count() }})</summary><div>@foreach($previousGates->take(12) as $pastGate)<span><strong>{{ $pastGate->gateLabel() }}</strong><small>{{ $pastGate->resultLabel() }} · {{ $pastGate->evaluated_at?->format('d M Y, H:i') }}</small><em>{{ $pastGate->summary }}</em>@if($pastGate->activeOverride && auth()->user()?->isAdmin())<details><summary class="master-btn master-btn-light master-btn-sm">Resolve old override</summary><form method="POST" action="{{ route('projects.workflow.overrides.revoke',[$project,$pastGate->activeOverride]) }}">@csrf @method('PATCH')<textarea class="master-textarea" name="revocation_reason" rows="2" minlength="5" maxlength="2000" required placeholder="How the accepted risk was resolved"></textarea><button class="master-btn master-btn-light master-btn-sm" type="submit">Record resolution</button></form></details>@endif</span>@endforeach</div></details>
                    @endif
                @endif
            </section>

            <section id="workflow-products" class="master-card master-card--flat master-section project-detail-card" data-workflow-view="products" role="tabpanel" aria-labelledby="workflow-tab-products" hidden>
                <div class="master-section-head"><div><h2 class="master-section-title">Client artwork and PPS approvals</h2><p class="master-sub">Every decision belongs to one exact file version. A revision makes the previous decision historical.</p></div><button type="button" class="master-btn master-btn-primary master-btn-sm" data-modal-open="workflowApprovalModal"><i class="fa-solid fa-paper-plane"></i> New approval request</button></div>
                <div class="project-approval-list">
                    @forelse($workflow->approvals as $approval)
                        <article class="project-approval is-{{ $approval->effectiveStatus() }}">
                            <header><div><strong>{{ $approval->title }}</strong><span>{{ $approval->typeLabel() }} · {{ $approval->product?->product_name }} · version {{ $approval->current_version_number }}</span></div><span class="master-badge">{{ $approval->statusLabel() }}</span></header>
                            @if($approval->isOverdue())<div class="project-approval-warning"><i class="fa-solid fa-triangle-exclamation"></i> Response overdue since {{ $approval->response_due_at->format('d M Y, H:i') }}. The request remains open.</div>@endif
                            <div class="project-approval-current"><div><span>Current file</span><a href="{{ $approval->currentVersion?->attachment?->fileUrl() }}" target="_blank">{{ $approval->currentVersion?->attachment?->original_name ?: 'File unavailable' }}</a></div><div><span>Client response</span><strong>{{ $approval->currentVersion?->statusLabel() ?: 'Pending' }}</strong>@if($approval->currentVersion?->client_comment)<small>{{ $approval->currentVersion->client_comment }}</small>@endif</div></div>
                            @if($approval->hasActiveOverride())<div class="project-approval-override"><strong>Manager override by {{ $approval->activeOverrideRecord()?->approver?->name ?: 'Administrator' }}</strong><span>{{ $approval->activeOverrideRecord()?->reason }}</span></div>@endif
                            <div class="project-approval-actions">
                                <details><summary class="master-btn master-btn-soft master-btn-sm">Add revision</summary><form method="POST" enctype="multipart/form-data" action="{{ route('projects.approvals.versions.store',[$project,$approval]) }}">@csrf<input class="master-input" type="file" name="approval_file" required><input class="master-input" type="datetime-local" name="response_due_at" value="{{ $defaultApprovalDue }}"><textarea class="master-textarea" name="submission_notes" rows="2" placeholder="What changed"></textarea><button class="master-btn master-btn-primary master-btn-sm">Send new version</button></form></details>
                                @if(auth()->user()?->isAdmin() && $approval->status!=='approved' && !$approval->hasActiveOverride())<details><summary class="master-btn master-btn-light master-btn-sm">Manager override</summary><form method="POST" action="{{ route('projects.approvals.override',[$project,$approval]) }}">@csrf<textarea class="master-textarea" name="override_reason" minlength="10" maxlength="2000" required placeholder="Reason and accepted risk"></textarea><button class="master-btn master-btn-primary master-btn-sm">Approve override</button></form></details>@endif
                                @if(auth()->user()?->isAdmin() && $approval->hasActiveOverride())<details><summary class="master-btn master-btn-light master-btn-sm">Revoke override</summary><form method="POST" action="{{ route('projects.approvals.override.revoke',[$project,$approval]) }}">@csrf @method('PATCH')<textarea class="master-textarea" name="override_revocation_reason" minlength="5" maxlength="2000" required placeholder="Reason for revocation"></textarea><button class="master-btn master-btn-light master-btn-sm">Revoke</button></form></details>@endif
                                <details><summary>Version history</summary><div class="project-approval-history">@foreach($approval->versions as $version)<span><strong>Version {{ $version->version_number }} · {{ $version->statusLabel() }}</strong><small>{{ $version->submitted_at?->format('d M Y, H:i') }}@if($version->clientUser) · {{ $version->clientUser->displayName() }}@endif</small>@if($version->client_comment)<em>{{ $version->client_comment }}</em>@endif</span>@endforeach</div></details>
                            </div>
                        </article>
                    @empty
                        <div class="master-empty-state"><i class="fa-solid fa-stamp"></i><p>No artwork or PPS approval requests have been sent yet.</p></div>
                    @endforelse
                </div>
                <div class="master-modal" id="workflowApprovalModal" aria-hidden="true" aria-labelledby="workflowApprovalTitle" data-open-on-errors="{{ $errors->hasAny(['project_product_id','approval_type','title','response_due_at','approval_file','instructions']) ? 'true' : 'false' }}">
                    <div class="master-modal-card workflow-approval-modal" role="dialog" aria-modal="true" aria-labelledby="workflowApprovalTitle">
                        <form method="POST" enctype="multipart/form-data" action="{{ route('projects.approvals.store',$project) }}">@csrf
                            <div class="master-modal-header"><div class="master-modal-heading"><span class="master-modal-icon"><i class="fa-solid fa-stamp"></i></span><div><h3 class="master-modal-title" id="workflowApprovalTitle">Send client approval request</h3><p class="master-modal-subtitle">Choose the product and exact file version the client must review.</p></div></div><button type="button" class="master-modal-close" data-close-modal aria-label="Close approval request">×</button></div>
                            <div class="master-modal-body"><div class="master-modal-grid">
                                <label class="master-field"><span class="master-label">Product <b>*</b></span><select class="master-select" name="project_product_id" required><option value="">Select product</option>@foreach($project->products->where('status','!=','cancelled') as $product)<option value="{{ $product->id }}" @selected((string)old('project_product_id')===(string)$product->id)>{{ $product->product_name }}</option>@endforeach</select></label>
                                <label class="master-field"><span class="master-label">Approval type <b>*</b></span><select class="master-select" name="approval_type" required>@foreach(\App\Models\ProjectApprovalRequest::typeOptions() as $key=>$label)<option value="{{ $key }}" @selected(old('approval_type','artwork')===$key)>{{ $label }}</option>@endforeach</select></label>
                                <label class="master-field"><span class="master-label">Title <b>*</b></span><input class="master-input" name="title" maxlength="255" value="{{ old('title') }}" required placeholder="Front artwork approval"></label>
                                <label class="master-field"><span class="master-label">Response due</span><input class="master-input" type="datetime-local" name="response_due_at" value="{{ old('response_due_at',$defaultApprovalDue) }}"></label>
                                <label class="master-field full"><span class="master-label">Approval file <b>*</b></span><input class="master-input" type="file" name="approval_file" required accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.ppt,.pptx,.zip"><small class="master-help">The decision will be tied permanently to this exact version.</small></label>
                                <label class="master-field full"><span class="master-label">Instructions</span><textarea class="master-textarea" name="instructions" rows="3" maxlength="2000" placeholder="What the client should review">{{ old('instructions') }}</textarea></label>
                            </div></div>
                            <div class="master-modal-footer"><button type="button" class="master-btn master-btn-light" data-close-modal>Cancel</button><button class="master-btn master-btn-primary" type="submit"><i class="fa-solid fa-paper-plane"></i> Send approval request</button></div>
                        </form>
                    </div>
                </div>
            </section>

            <section class="master-card master-card--flat master-section project-detail-card" data-workflow-view="products" role="tabpanel" aria-labelledby="workflow-tab-products" hidden>
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
                        @endphp
                        <details class="project-approval workflow-product-disclosure" @if($loop->first) open @endif>
                            <summary><div><strong>{{ $product->product_name }}</strong><span>{{ number_format((float)$product->quantity,3) }} {{ $product->unit }} required</span></div><span class="master-badge">Independent product</span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
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
                                                <small>{{ number_format((float)($details['required_quantity']??0),3) }} required
                                                    @if(array_key_exists('remaining_quantity',$details)) · {{ number_format((float)$details['remaining_quantity'],3) }} remaining @endif
                                                    @if(array_key_exists('sample_inspected_quantity',$details)) · {{ number_format((float)$details['sample_inspected_quantity'],3) }} sampled @endif
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
                            @if($product->qualityInspections->isNotEmpty())
                                <details class="workflow-qc-history">
                                    <summary><span>QC history</span><small>{{ $product->qualityInspections->count() }} immutable {{ \Illuminate\Support\Str::plural('inspection', $product->qualityInspections->count()) }}</small><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
                                    <div class="project-approval-history">
                                        @foreach($product->qualityInspections as $inspection)
                                            <span>
                                                <strong>{{ $inspection->resultLabel() }} · {{ number_format((float)$inspection->passed_quantity,3) }} passed</strong>
                                                <small>{{ $inspection->inspected_at?->format('d M Y, H:i') }} · {{ $inspection->inspector?->name ?: 'System' }}</small>
                                                @if($inspection->evidenceUrl())
                                                    <a href="{{ $inspection->evidenceUrl() }}" target="_blank" rel="noopener">{{ $inspection->evidence_original_name }}</a>
                                                    <small>Evidence SHA-256 {{ substr($inspection->evidence_hash,0,10) }}</small>
                                                @else
                                                    <small>No attachment supplied</small>
                                                @endif
                                                @if($inspection->notes)<em>{{ $inspection->notes }}</em>@endif
                                            </span>
                                        @endforeach
                                    </div>
                                </details>
                            @endif
                        </details>
                    @endforeach
                </div>
            </section>

            @include('projects.partials.financial-closure')

            <section class="master-card master-card--flat master-section project-detail-card" data-workflow-view="history" role="tabpanel" aria-labelledby="workflow-tab-history" hidden>
                <div class="master-section-head"><div><h2 class="master-section-title">Standard execution path · History & manual controls</h2><p class="master-sub">The complete standard path remains available for audit and controlled manual updates.</p></div></div>
                <div class="workflow-history-toolbar"><span><i class="fa-solid fa-clock-rotate-left"></i> Complete workflow evidence</span><small>Completed and future stages are grouped below for audit and controlled updates.</small></div>
                <div class="project-workflow-stages">
                    @foreach($workflowStages as $stageKey=>$stageLabel)
                        @if($groupedSteps->has($stageKey))
                            @php
                                $stageSteps = $groupedSteps[$stageKey];
                                $stageDone = $stageSteps->whereIn('status', ['completed', 'skipped'])->count();
                            @endphp
                            <details class="project-workflow-stage {{ $workflow->calculated_stage === $stageKey ? 'is-current' : '' }}" @if($workflow->calculated_stage === $stageKey) open @endif>
                                <summary><div><span>{{ str_pad((string)$loop->iteration,2,'0',STR_PAD_LEFT) }}</span><div><h3>{{ $stageLabel }}</h3><p>{{ $stageDone }} of {{ $stageSteps->count() }} steps complete</p></div></div><strong>{{ $stageSteps->count() ? round($stageDone/$stageSteps->count()*100) : 0 }}%</strong><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
                                <div class="project-workflow-step-list">
                                    @foreach($stageSteps as $step)
                                        @if(in_array($step->step_key, array_merge(\App\Services\ProjectWorkflowGates::AUTOMATED_GATES, \App\Services\ProjectOperationalReadiness::CONTROLLED_STEPS, \App\Services\ProjectApprovals::CONTROLLED_STEPS, \App\Services\ProjectFinancialClosure::CONTROLLED_STEPS, \App\Services\ProjectFeedbackClosure::CONTROLLED_STEPS), true))
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
                            </details>
                        @endif
                    @endforeach
                </div>
            </section>

            <div class="master-modal" id="workflowReportModal" aria-hidden="true" aria-labelledby="workflowReportTitle" data-open-on-errors="{{ $errors->hasAny(['reported_stage','commercial_sales_invoice_id','advance_percentage_override','reported_reason']) ? 'true' : 'false' }}">
                <div class="master-modal-card workflow-report-modal" role="dialog" aria-modal="true" aria-labelledby="workflowReportTitle" aria-describedby="workflowReportDescription">
                    <form method="POST" action="{{ route('projects.workflow.report', $project) }}">@csrf @method('PATCH')
                        <div class="master-modal-header">
                            <div class="master-modal-heading"><span class="master-modal-icon"><i class="fa-solid fa-user-pen"></i></span><div><h3 class="master-modal-title" id="workflowReportTitle">Coordinator report</h3><p class="master-modal-subtitle" id="workflowReportDescription">Update the reported stage and commercial inputs. Project progress remains calculated exclusively from workflow controls.</p></div></div>
                            <button type="button" class="master-modal-close" data-close-modal aria-label="Close coordinator report">×</button>
                        </div>
                        <div class="master-modal-body">
                            <div class="master-modal-grid workflow-report-grid">
                                <label class="master-field"><span class="master-label">Reported stage</span><select class="master-select" name="reported_stage" required>@foreach($workflowStages as $key=>$label)<option value="{{ $key }}" @selected(old('reported_stage',$workflow->reported_stage ?: $workflow->calculated_stage)===$key)>{{ $label }}</option>@endforeach</select></label>
                                <label class="master-field"><span class="master-label">Commercial baseline PI</span><select class="master-select" name="commercial_sales_invoice_id"><option value="">Not selected</option>@foreach($project->proformaInvoices as $invoice)<option value="{{ $invoice->id }}" @selected((string)old('commercial_sales_invoice_id',$workflow->commercial_sales_invoice_id)===(string)$invoice->id)>{{ $invoice->invoice_number }} · {{ $money($invoice->total_amount) }}</option>@endforeach</select><small class="master-help">Authoritative source for advance and dispatch terms.</small></label>
                                <label class="master-field"><span class="master-label">Advance override %</span><input class="master-input" type="number" name="advance_percentage_override" min="0" max="100" step="0.01" value="{{ old('advance_percentage_override',$workflow->advance_percentage_override) }}" placeholder="Use PI terms"><small class="master-help">Leave empty to use the selected PI.</small></label>
                                <label class="master-field full"><span class="master-label">Reason / execution update</span><textarea class="master-textarea" name="reported_reason" rows="4" maxlength="2000" required>{{ old('reported_reason',$workflow->reported_reason) }}</textarea></label>
                            </div>
                        </div>
                        <div class="master-modal-footer"><button type="button" class="master-btn master-btn-light" data-close-modal>Cancel</button><button class="master-btn master-btn-primary" type="submit"><i class="fa-solid fa-check"></i> Save report</button></div>
                    </form>
                </div>
            </div>

            @if($workflowReceiptInvoice)
                @include('sales_invoices.partials.payment-modal', [
                    'accounts' => $cashflowAccounts,
                    'receiptCategories' => $cashflowCategories->where('is_active', true)->whereIn('type', ['income', 'tax', 'other']),
                ])
            @endif
        @endif
    </div>
</section>
