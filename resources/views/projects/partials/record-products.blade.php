<section class="master-tab-panel" id="project-panel-products" role="tabpanel" aria-labelledby="project-tab-products">
    <div class="project-blocks">
        <section class="master-card master-card--flat project-detail-card">
            <div class="master-section-head">
                <div>
                    <h2 class="master-section-title">Project products</h2>
                    <p class="master-sub">{{ $project->products->count() }}
                    {{ \Illuminate\Support\Str::plural('product', $project->products->count()) }} on this project,
                    written by the invoices and purchase documents raised for it — the product, the quantity, the rate
                    and the vendor are theirs. Only what the project owns is editable here: its status, its dates and
                    its notes.</p>
                </div>
            </div>

            <div class="master-table-wrap">
                <table class="master-table">
                    <thead>
                        <tr>
                            <th scope="col" class="project-col-thumb">Image</th>
                            <th scope="col">Product</th>
                            <th scope="col">Qty</th>
                            <th scope="col" class="is-num">Amount</th>
                            <th scope="col">Specifications</th>
                            <th scope="col">Vendor</th>
                            <th scope="col" class="project-col-actions">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($project->products as $projectProduct)
                            @php
                                $media = optional($projectProduct->product)->primaryMedia();
                                $milestone = $projectProduct->currentMilestone();
                            @endphp
                            <tr>
                                <td data-label="Image">
                                    @if ($media)
                                        <img class="project-thumb" src="{{ asset('storage/'.$media->file_path) }}" alt="">
                                    @else
                                        <span class="project-thumb" aria-hidden="true"><i class="fa-solid fa-box"></i></span>
                                    @endif
                                </td>
                                <td data-label="Product">
                                    <strong>{{ $projectProduct->product_name }}</strong>
                                    <span class="project-fact-note">{{ optional($projectProduct->product)->product_number ?: 'No product code' }}</span>
                                    <span class="master-badge status-{{ str_replace('_', '-', (string) $milestone?->status) }}">
                                    {{ $milestone?->title ?? 'No milestone' }}</span>
                                </td>
                                <td data-label="Qty">
                                    <strong>{{ number_format($projectProduct->quantity) }} {{ $projectProduct->unit }}</strong>
                                </td>
                                <td data-label="Amount" class="is-num">
                                    <strong>{{ \App\Helpers\CommonHelper::amount($projectProduct->total_amount, $projectProduct->currency) }}</strong>
                                </td>
                                <td data-label="Specifications">{{ $projectProduct->notes ?: '—' }}</td>
                                <td data-label="Vendor">
                                    {{ optional($projectProduct->vendor)->contact_person_name ?: '—' }}
                                    <span class="project-fact-note">{{ $projectProduct->vendor_invoice_number ?: 'No vendor invoice' }}</span>
                                </td>
                                <td data-label="Action" class="project-col-actions">
                                    <div class="master-row-actions">
                                        <button type="button" class="master-icon-btn editProductBtn"
                                            aria-label="Edit {{ $projectProduct->product_name }}"
                                            data-product='@json($projectProduct)'><i class="fas fa-pen" aria-hidden="true"></i></button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="master-empty-state">
                                        <i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i>
                                        <p>No products on this project yet. Save an invoice or a purchase document tagged to
                                        this project and its lines appear here on their own — quantity, rate and vendor come
                                        from the documents, so nothing has to be typed here at all.</p>
                                        <a class="master-btn master-btn-soft master-btn-sm"
                                            href="{{ route('sales-invoices.create', ['project_id' => $project->id]) }}">
                                            <i class="fas fa-file-invoice" aria-hidden="true"></i> Raise an invoice</a>
                                        <a class="master-btn master-btn-soft master-btn-sm"
                                            href="{{ route('purchase-invoices.create', ['project_id' => $project->id]) }}">
                                            <i class="fas fa-file-invoice" aria-hidden="true"></i> Record a purchase</a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</section>


<!--Edit Product-->
<div class="master-modal" id="editProductModal">
    <div class="master-modal-card">
        <form id="editProductForm" method="POST"
            data-update-url="{{ route('projects.products.update', ['projectProduct' => '__ID__']) }}">
            @csrf
            @method('PUT')
            <div class="master-modal-header">
                <div>
                    <h3 class="master-modal-title">Edit Product</h3>
                    <p class="master-sub">Product, quantity, rate and vendor belong to the documents that raised them
                    — edit the invoice or the purchase document and this row follows. What is here is the project's
                    own: where the product stands, when it is expected, and its notes.</p>
                </div>
                <button type="button" class="master-modal-close" id="closeEditProductModal" data-close-modal>×</button>
            </div>
            <div class="master-modal-body">
                <div class="master-modal-grid">
                    <div class="master-field">
                        <label class="master-label">Expected ready</label>
                        <input class="master-input" type="date" name="expected_ready_date">
                    </div>
                    <div class="master-field">
                        <label class="master-label">Actually ready</label>
                        <input class="master-input" type="date" name="actual_ready_date">
                    </div>
                    <div class="master-field">
                        <label class="master-label">Status</label>
                        <select class="master-select" name="status">
                            @foreach ($productStatusOptions as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field full">
                        <label class="master-label">Notes</label>
                        <textarea class="master-textarea" rows="5" name="notes" placeholder="Artwork, production, packaging notes"></textarea>
                    </div>
                </div>
            </div>
            <div class="master-modal-footer">
                <button type="button" class="master-btn master-btn-light" id="cancelEditProductModal" data-close-modal>Cancel</button>
                <button class="master-btn master-btn-primary" type="submit"> Save Product</button>
            </div>
        </form>
    </div>
</div>
