<section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-projects-heading">
    <div class="master-section-head">
        <div>
            <h2 class="master-section-title" id="vendor-projects-heading">Project products</h2>
            <p class="master-sub">{{ number_format($summary['running_projects']) }} running {{ \Illuminate\Support\Str::plural('project', $summary['running_projects']) }} · {{ number_format($projectProducts->count()) }} linked product rows</p>
        </div>
        @if (\Illuminate\Support\Facades\Route::has('projects.index'))
            <a class="master-btn master-btn-soft" href="{{ route('projects.index') }}">Open projects</a>
        @endif
    </div>

    @if ($projectProducts->isNotEmpty())
        <div class="master-table-wrap ui-mobile-cards">
            <table class="master-table vendor-detail-table vendor-project-table">
                <thead>
                    <tr>
                        <th scope="col">Project</th>
                        <th scope="col">Product</th>
                        <th scope="col">Quantity</th>
                        <th scope="col">Value</th>
                        <th scope="col">Status / stage</th>
                        <th scope="col">Ready date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($projectProducts as $row)
                        <tr>
                            <td data-label="Project">
                                @if ($row->relationLoaded('project') && $row->project && \Illuminate\Support\Facades\Route::has('projects.show'))
                                    <a class="vendor-table-name" href="{{ route('projects.show', $row->project) }}">{{ $row->project->project_number ?: 'Project #'.$row->project->id }}</a>
                                    <span class="vendor-table-meta">{{ $row->project->name }}</span>
                                @else
                                    <span class="master-empty-value">No project linked</span>
                                @endif
                            </td>
                            <td data-label="Product">
                                <strong>{{ $row->product_name ?: 'Product not specified' }}</strong>
                                <span class="vendor-table-meta">Vendor invoice: {{ $row->vendor_invoice_number ?: 'Not provided' }}</span>
                            </td>
                            <td data-label="Quantity">{{ number_format((int) $row->quantity) }} {{ $row->unit }}</td>
                            <td data-label="Value" class="vendor-numeric">{{ $money($row->total_amount, $row->currency ?: 'INR') }}</td>
                            <td data-label="Status / stage">
                                <span class="master-badge vendor-project-status vendor-project-status-{{ str_replace('_', '-', $row->status) }}">{{ $row->statusLabel() }}</span>
                                <span class="vendor-table-meta">{{ $row->stageLabel() }}</span>
                            </td>
                            <td data-label="Ready date">
                                <span>Expected {{ optional($row->expected_ready_date)->format('d M Y') ?: '—' }}</span>
                                <span class="vendor-table-meta">Actual {{ optional($row->actual_ready_date)->format('d M Y') ?: '—' }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="master-empty-state"><i class="fa-solid fa-diagram-project" aria-hidden="true"></i><p>No project products are currently mapped to this vendor.</p></div>
    @endif
</section>
