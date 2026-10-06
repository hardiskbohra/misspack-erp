<section class="master-tab-panel" id="project-panel-products" role="tabpanel" aria-labelledby="project-tab-products">
    <div class="project-blocks">
        <section class="master-card master-card--flat project-detail-card">
            <div class="master-section-head">
                <div>
                    <h2 class="master-section-title">Project products</h2>
                    <p class="master-sub">{{ $project->products->count() }}
                    {{ \Illuminate\Support\Str::plural('product', $project->products->count()) }} on this project</p>
                </div>
                <div class="master-section-meta">
                    <button type="button" class="master-btn master-btn-primary addProductBtn" id="openAddProductModal">
                        <i class="fas fa-plus" aria-hidden="true"></i> Add product</button>
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
                                        <form method="POST" action="{{ route('projects.products.destroy', $projectProduct) }}"
                                            data-confirm="Remove this product from the project?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="master-icon-btn danger"
                                                aria-label="Remove {{ $projectProduct->product_name }}"><i class="fas fa-trash" aria-hidden="true"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="master-empty-state">
                                        <i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i>
                                        <p>No products on this project yet. Add the first one — its milestones, value and vendor
                                        follow from there.</p>
                                        <button type="button" class="master-btn master-btn-soft master-btn-sm"
                                            data-modal-open="addProductModal"><i class="fas fa-plus" aria-hidden="true"></i> Add product</button>
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


<!--Add Product-->
<div class="master-modal" id="addProductModal" aria-hidden="true">
    <div class="master-modal-card">
        <form method="POST" action="{{ route('projects.products.store', $project) }}">
            @csrf
            <div class="master-modal-header">
                <div class="master-modal-heading">
                    <div>
                        <h3 class="master-modal-title">Add Product</h3>
                    </div>
                </div>
                <button type="button" class="master-modal-close" data-close-modal id="closeAddProductModal">×</button>
            </div>
            <div class="master-modal-body">
                <div class="master-modal-grid">
                    <div class="master-field">
                        <label class="master-label">Mapped Product</label>
                        <select class="master-select" name="product_id" required>
                            <option value="">Manual product</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}">{{ $product->product_number }} - {{ $product->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field" style="display:none;">
                        <label class="master-label">Product Name</label>
                        <input class="master-input" type="text" name="product_name" placeholder="Required if product not selected">
                    </div>
                    <div class="master-field small">
                        <label class="master-label">Qty</label>
                        <input class="master-input" type="number" step="0.001" min="0.001" name="quantity" value="1" required>
                    </div>
                    <div class="master-field small" style="display:none;">
                        <label class="master-label">Unit</label>
                        <input class="master-input" type="text" name="unit" value="pcs">
                    </div>
                    <div class="master-field small">
                        <label class="master-label">Unit Price</label>
                        <input class="master-input" type="number" step="0.01" min="0" name="unit_price" value="0">
                    </div>
                    <div class="master-field">
                        <label class="master-label">Status</label>
                        <select class="master-select" name="status">
                            @foreach($productStatusOptions as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field">
                        <label class="master-label">Vendor</label>
                        <select class="master-select" name="vendor_id">
                            <option value="">Unassigned</option>
                            @foreach ($vendors as $vendor)
                                <option value="{{ $vendor->id }}">{{ $vendor->contact_person_name }} ({{ $vendor->vendor_name }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field">
                        <label class="master-label">Vendor Invoice Number</label>
                        <input class="master-input" type="text" name="vendor_invoice_number">
                    </div>
                    <div class="master-field">
                        <label class="master-label">Expected Ready</label>
                        <input class="master-input" type="date" name="expected_ready_date">
                    </div>
                    <div class="master-field full">
                        <label class="master-label">Notes</label>
                        <textarea class="master-textarea" rows="5" name="notes" placeholder="Artwork, production, packaging notes"></textarea>
                    </div>
                    <input type="hidden" name="currency" value="{{ $project->currency }}">
                </div>
            </div>
            <div class="master-modal-footer">
                <button type="button" class="master-btn master-btn-light" id="cancelAddProductModal" data-close-modal>Cancel</button>
                <button class="master-btn master-btn-primary" type="submit"> Add Product</button>
            </div>
        </form>
    </div>
</div>

<!--Edit Product-->
<div class="master-modal" id="editProductModal">
    <div class="master-modal-card">
        <form id="editProductForm" method="POST"
            data-update-url="{{ route('projects.products.update', ['projectProduct' => '__ID__']) }}">
            @csrf
            @method('PUT')
            <div class="master-modal-header">
                <div><h3 class="master-modal-title">Edit Product</h3></div>
                <button type="button" class="master-modal-close" id="closeEditProductModal" data-close-modal>×</button>
            </div>
            <div class="master-modal-body">
                <div class="master-modal-grid">

                    <div class="master-field">
                        <label class="master-label">Mapped Product</label>
                        <select class="master-select" name="product_id">
                            <option value="">Manual product</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}"> {{ $product->name }}{{ $product->sku ? ' - ' . $product->sku : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field small">
                        <label class="master-label">Qty</label>
                        <input class="master-input" type="number" step="0.001" min="0.001" name="quantity" value="1" required>
                    </div>
                    <div class="master-field small">
                        <label class="master-label">Unit Price</label>
                        <input class="master-input" type="number" step="0.01" min="0" name="unit_price" value="0">
                    </div>
                    <div class="master-field">
                        <label class="master-label">Expected Ready</label>
                        <input class="master-input" type="date" name="expected_ready_date">
                    </div>
                    <!--<div class="master-field">-->
                    <!--    <label class="master-label">Assignee</label>-->
                    <!--    <select class="master-select" name="assigned_to">-->
                    <!--        <option value="">Unassigned</option>-->
                    <!--        @foreach ($users as $user)-->
                    <!--            <option value="{{ $user->id }}">{{ $user->name }}</option>-->
                    <!--        @endforeach-->
                    <!--    </select>-->
                    <!--</div>-->
                    <div class="master-field">
                        <label class="master-label">Vendor</label>
                        <select class="master-select" name="vendor_id">
                            <option value="">Unassigned</option>
                            @foreach ($vendors as $vendor)
                                <option value="{{ $vendor->id }}">{{ $vendor->contact_person_name }} ({{ $vendor->vendor_name }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field">
                        <label class="master-label">Vendor Invoice Number</label>
                        <input class="master-input" type="text" name="vendor_invoice_number">
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
                    <!--<div class="master-field">-->
                    <!--    <label class="master-label">Stage</label>-->
                    <!--    <select class="master-select" name="stage"> -->
                    <!--        @foreach ($productStageOptions as $key => $label) -->
                    <!--            <option value="{{ $key }}">{{ $label }}</option> -->
                    <!--        @endforeach -->
                    <!--    </select> -->
                    <!--</div>-->
                </div>
            </div>
            <div class="master-modal-footer">
                <button type="button" class="master-btn master-btn-light" id="cancelEditProductModal" data-close-modal>Cancel</button>
                <button class="master-btn master-btn-primary" type="submit"> Save Product</button>
            </div>
        </form>
    </div>
</div>
