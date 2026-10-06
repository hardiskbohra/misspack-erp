@php($running = $projectProducts->reject(fn ($row) => in_array($row->status, ['delivered', 'cancelled'], true))->count())
<section class="master-card master-card--flat vendor-detail-card vendor-block-card" id="vendor-block-projects" aria-labelledby="vendor-block-projects-title">
    <div class="vendor-panel-head">
        <div>
            <h2 class="vendor-detail-title" id="vendor-block-projects-title">Projects running with this vendor</h2>
            <p class="vendor-detail-help">Every product this supplier was mapped to, newest first. Values are the project product row totals.</p>
        </div>
        <div class="vendor-panel-meta">
            <span class="vendor-pill">{{ $running }} running</span>
            <span class="vendor-pill">{{ $projectProducts->count() }} {{ \Illuminate\Support\Str::plural('row', $projectProducts->count()) }}</span>
        </div>
    </div>

    <div class="master-card master-card--flat vendor-table-card vendor-table-bleed">
        <div class="master-table-wrap ui-mobile-cards">
            <table class="master-table vendor-table">
                <thead>
                    <tr>
                        <th scope="col">Project</th>
                        <th scope="col">Product</th>
                        <th scope="col" class="is-num">Qty</th>
                        <th scope="col" class="is-num">Value</th>
                        <th scope="col">Status / stage</th>
                        <th scope="col" class="ui-mobile-secondary">Dates</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($projectProducts as $row)
                        <tr>
                            <td data-label="Project">
                                @if ($row->project)
                                    <a class="vendor-table-link" href="{{ route('projects.show', $row->project) }}">
                                        <strong>{{ $row->project->project_number ?? 'Project #'.$row->project->id }}</strong>
                                        <span class="master-sub">{{ $row->project->name ?? 'No project name' }}</span>
                                    </a>
                                @else
                                    <strong>No project</strong>
                                @endif
                            </td>
                            <td data-label="Product">
                                <strong>{{ $row->product_name }}</strong>
                                <span class="master-sub">Vendor invoice: {{ $row->vendor_invoice_number ?: '—' }}</span>
                            </td>
                            <td data-label="Qty" class="is-num">{{ number_format((int) $row->quantity) }} <span class="master-sub">{{ $row->unit }}</span></td>
                            <td data-label="Value" class="is-num"><strong>{{ $money($row->total_amount, $row->currency) }}</strong></td>
                            <td data-label="Status / stage">
                                {{ $row->statusLabel() }}
                                <span class="master-sub">{{ $row->stageLabel() }}</span>
                            </td>
                            <td data-label="Dates" class="ui-mobile-secondary">
                                Expected {{ optional($row->expected_ready_date)->format('d M Y') ?: '—' }}
                                <span class="master-sub">Actual {{ optional($row->actual_ready_date)->format('d M Y') ?: '—' }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="master-list-empty">
                                    <span class="master-list-empty-icon" aria-hidden="true"><i class="fa-solid fa-diagram-project"></i></span>
                                    <h3 class="master-list-empty-title">No project products mapped yet</h3>
                                    <p class="master-list-empty-text">Map a product to this vendor from a project to see it here.</p>
                                    @if ($routes['projects'] !== '#')
                                        <div class="master-list-empty-actions">
                                            <a class="master-btn master-btn-soft" href="{{ $routes['projects'] }}">Go to projects</a>
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
