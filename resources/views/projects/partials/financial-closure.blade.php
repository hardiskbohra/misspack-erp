@php
    $closureEvaluation = $workflow->evaluations->whereNull('project_product_id')->where('gate_key', \App\Services\ProjectFinancialClosure::GATE)->first();
    $closureHistory = $workflow->relationLoaded('financialClosures') ? $workflow->financialClosures : collect();
    $latestClosure = $closureHistory->first();
    $closureSnapshotStale = $latestClosure && $financialClosureSnapshot && !hash_equals((string) $latestClosure->content_hash, (string) $financialClosureSnapshot['content_hash']);
    $formatClosureTotals = function (array $totals): string {
        return collect($totals)->map(fn ($amount, $currency) => \App\Helpers\CommonHelper::amount((float) $amount, $currency))->implode(' · ') ?: 'None';
    };
@endphp
<section class="master-card master-card--flat master-section project-detail-card project-financial-closure" data-workflow-view="commercial" role="tabpanel" hidden aria-labelledby="workflow-tab-commercial financialClosureTitle">
    <div class="master-section-head">
        <div>
            <h2 class="master-section-title" id="financialClosureTitle">Financial closure</h2>
            <p class="master-sub">A source-controlled review of receivables, payables, advances and unapplied cash. Closing never settles or hides the underlying ledger.</p>
        </div>
        <form method="POST" action="{{ route('projects.workflow.financial-closure.evaluate', $project) }}">
            @csrf
            <button class="master-btn master-btn-soft master-btn-sm" type="submit">Review current balances</button>
        </form>
    </div>

    @if($financialClosureSnapshot)
        @if($project->status === 'closed_with_outstanding_financials')
            <div class="master-alert error"><i class="fa-solid fa-triangle-exclamation"></i><div><strong>Closed with outstanding financials</strong><span>Receivables, payables and cash allocations remain active. Resolve them in their source modules, then review and close as financially complete.</span></div></div>
        @elseif($project->status === 'closed')
            <div class="master-alert master-alert-success"><i class="fa-solid fa-circle-check"></i><div><strong>Financially complete</strong><span>The closure snapshot is preserved below. Later source changes do not rewrite that decision.</span></div></div>
        @endif
        @if($closureSnapshotStale)
            <div class="master-alert warning"><i class="fa-solid fa-rotate"></i><div><strong>Balances changed after the latest closure decision</strong><span>The saved closure fingerprint no longer matches current invoices, bills, receipts, payments or allocations. Review again; history remains unchanged.</span></div></div>
        @endif

        <div class="master-stats project-financial-stats">
            <article class="master-stat"><div><p class="master-stat-title">Receivables</p><p class="master-stat-value">{{ $formatClosureTotals($financialClosureSnapshot['totals']['receivables']) }}</p><p class="master-sub">Client invoices still due</p></div></article>
            <article class="master-stat"><div><p class="master-stat-title">Payables</p><p class="master-stat-value">{{ $formatClosureTotals($financialClosureSnapshot['totals']['payables']) }}</p><p class="master-sub">Vendor bills still due</p></div></article>
            <article class="master-stat"><div><p class="master-stat-title">Client advances</p><p class="master-stat-value">{{ $formatClosureTotals($financialClosureSnapshot['totals']['client_advances']) }}</p><p class="master-sub">Allocated excess receipts</p></div></article>
            <article class="master-stat"><div><p class="master-stat-title">Vendor advances</p><p class="master-stat-value">{{ $formatClosureTotals($financialClosureSnapshot['totals']['vendor_advances']) }}</p><p class="master-sub">Allocated excess payments</p></div></article>
            <article class="master-stat"><div><p class="master-stat-title">Unapplied client cash</p><p class="master-stat-value">{{ $formatClosureTotals($financialClosureSnapshot['totals']['unallocated_client_cash']) }}</p><p class="master-sub">Project-attributable inward remainder</p></div></article>
            <article class="master-stat"><div><p class="master-stat-title">Unapplied vendor cash</p><p class="master-stat-value">{{ $formatClosureTotals($financialClosureSnapshot['totals']['unallocated_vendor_cash']) }}</p><p class="master-sub">Project-attributable outward remainder</p></div></article>
        </div>

        <div class="project-financial-closure-actions">
            @if(!$financialClosureSnapshot['operationally_ready'])
                <div class="master-alert warning"><i class="fa-solid fa-lock"></i><div><strong>Operational closure is not ready</strong><span>{{ count($financialClosureSnapshot['open_operational_steps']) }} other workflow step(s) remain open. Complete or formally override those controls first.</span></div></div>
            @elseif(!$financialClosureSnapshot['has_outstanding_financials'] && $project->status !== 'closed')
                <form method="POST" action="{{ route('projects.workflow.financial-closure.close', $project) }}">@csrf<button class="master-btn master-btn-primary" type="submit">Close as financially complete</button></form>
            @elseif($financialClosureSnapshot['has_outstanding_financials'] && auth()->user()?->isAdmin() && $project->status !== 'closed_with_outstanding_financials')
                <details>
                    <summary class="master-btn master-btn-danger">Manager exception: close with outstanding financials</summary>
                    <form method="POST" action="{{ route('projects.workflow.financial-closure.close-outstanding', $project) }}" class="project-approval-create">@csrf
                        <label class="master-field project-approval-instructions"><span class="master-label">Exception reason and follow-up evidence</span><textarea class="master-textarea" name="reason" rows="4" minlength="20" maxlength="2000" required placeholder="Explain why operational closure cannot wait, who owns resolution, and how it will be followed up."></textarea></label>
                        <label class="master-field"><span class="master-label">Expected resolution date</span><input class="master-input" type="date" name="expected_resolution_date" min="{{ now()->format('Y-m-d') }}" required></label>
                        <label class="project-risk-acknowledgement"><input type="checkbox" name="risk_acknowledgement" value="1" required><span>I accept that the balances below remain live financial obligations and that project closure does not settle, cancel or conceal them.</span></label>
                        <button class="master-btn master-btn-danger" type="submit">Record exception and close</button>
                    </form>
                </details>
            @endif
            @if(in_array($project->status, \App\Models\Project::closureStatuses(), true) && auth()->user()?->isAdmin())
                <details>
                    <summary class="master-btn master-btn-light master-btn-sm">Reopen project</summary>
                    <form method="POST" action="{{ route('projects.workflow.financial-closure.reopen', $project) }}" class="project-inline-form">@csrf<textarea class="master-textarea" name="reason" minlength="10" maxlength="2000" required placeholder="Reason for reopening"></textarea><button class="master-btn master-btn-light master-btn-sm" type="submit">Reopen with audit record</button></form>
                </details>
            @endif
        </div>

        <details class="project-financial-sources">
            <summary><span><i class="fa-solid fa-database" aria-hidden="true"></i> Outstanding source detail and fingerprint</span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
            <div class="project-financial-source-grid">
                <div><h3>Client documents</h3>@forelse($financialClosureSnapshot['receivables'] as $row)<p><strong>{{ $row['document_number'] }}</strong><span>{{ \App\Helpers\CommonHelper::amount($row['outstanding'], $row['currency']) }} due · {{ \App\Helpers\CommonHelper::amount($row['advance'], $row['currency']) }} advance · {{ $row['status'] }}</span></p>@empty<p><span>No active sales documents.</span></p>@endforelse</div>
                <div><h3>Vendor bills</h3>@forelse($financialClosureSnapshot['payables'] as $row)<p><strong>{{ $row['document_number'] }}</strong><span>{{ \App\Helpers\CommonHelper::amount($row['outstanding'], $row['currency']) }} due · {{ \App\Helpers\CommonHelper::amount($row['advance'], $row['currency']) }} advance · {{ $row['status'] }}</span></p>@empty<p><span>No active vendor bills.</span></p>@endforelse</div>
                <div><h3>Unapplied project cash</h3>@forelse($financialClosureSnapshot['unallocated_cash'] as $row)<p><strong>#{{ $row['source_id'] }} · {{ ucfirst($row['transaction_type']) }}</strong><span>{{ \App\Helpers\CommonHelper::amount($row['unallocated'], $row['currency']) }} unapplied · {{ $row['particular'] }}</span></p>@empty<p><span>No project-attributable unapplied cash.</span></p>@endforelse</div>
            </div>
            <code>SHA-256 {{ $financialClosureSnapshot['content_hash'] }}</code>
        </details>
    @endif

    @if($closureHistory->isNotEmpty())
        <details class="project-approval-history"><summary>Immutable closure history ({{ $closureHistory->count() }})</summary>
            @foreach($closureHistory as $decision)
                <span><strong>{{ $decision->closure_type_label }}</strong><small>{{ $decision->closed_at?->format('d M Y, H:i') }} · {{ $decision->approver?->name ?: 'System' }} · SHA-256 {{ substr($decision->content_hash,0,12) }}</small>@if($decision->reason)<em>{{ $decision->reason }}</em>@endif @if($decision->expected_resolution_date)<small>Expected resolution {{ $decision->expected_resolution_date->format('d M Y') }}</small>@endif</span>
            @endforeach
        </details>
    @endif
</section>
