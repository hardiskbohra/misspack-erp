@extends('layouts.app')

@section('page-title', $invoice->exists ? 'Edit '.$invoice->typeLabel() : 'Create '.$docLabels[$docType])

@section('content')
    @php
        $isEdit = $invoice->exists;
        $source = $sourceOrder ?? null;
        $invoiceItems = old('items', $invoice->relationLoaded('items') && $invoice->items->isNotEmpty()
            ? $invoice->items->toArray()
            : ($source?->items?->toArray() ?? []));
        if (! $invoiceItems) {
            $invoiceItems = [[
                'product_name' => '',
                'quantity' => 1,
                'unit' => 'pcs',
                'unit_price' => 0,
                'discount_percent' => 0,
                'gst_percent' => 0,
            ]];
        }
        $initialInvoiceItems = array_values($invoiceItems);
        $productOptionsJson = $products->sortBy('id')->map(fn ($product) => [
            'id' => $product->id,
            'name' => $product->name,
            'no' => $product->product_number,
            'description' => $product->description ?? '',
            'price' => 0,
        ])->values()->all();
        $ledgerReceived = $isEdit ? round($invoice->paidAmount() - (float) $invoice->amount_paid, 2) : 0;
        $nexts = \App\Models\PurchaseInvoice::STATUS_FLOW[$docType][$invoice->status] ?? [];
    @endphp
    <div class="si-form">
        <div class="master-card master-header">
            <div>
                <h1>{{ $isEdit ? 'Edit' : 'Create' }} {{ $docLabels[$docType] }}</h1>
                <div class="master-breadcrumb">
                    <a href="{{ url('/') }}">Home</a><span>•</span>
                    <a href="{{ route($routePrefix.'.index') }}">{{ $docType === 'order' ? 'Purchase Orders' : 'Purchase Bills' }}</a>
                    <span>•</span><span class="active">{{ $isEdit ? 'Edit' : 'New' }}</span>
                </div>
                @if ($isEdit)
                    <div class="record-head-chips">
                        <span class="si-status status-{{ $invoice->stateKey() }}">{{ $invoice->stateLabel() }}</span>
                        <span class="master-chip">{{ $invoice->invoice_number }}</span>
                    </div>
                @else
                    <p class="master-sub">Vendor details come from the vendor record. A purchase order becomes a bill once, and the bill is what is owed.</p>
                @endif
            </div>
            <div class="master-actions">
                @if ($isEdit)
                    <a href="{{ route($routePrefix.'.show', $invoice) }}" class="master-btn master-btn-light">View</a>
                @endif
                <a href="{{ $isEdit ? route($routePrefix.'.show', $invoice) : route($routePrefix.'.index') }}" class="master-btn master-btn-light">Back</a>
            </div>
        </div>

        @if ($errors->any())
            <div class="master-alert error"><strong>Please fix:</strong>
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ $isEdit ? route($routePrefix.'.update', $invoice) : route($routePrefix.'.store') }}" class="master-form">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div class="master-card master-section">
                <div class="master-section-head"><div><p class="master-eyebrow">Basics</p><h2>Document details</h2></div></div>
                <div class="master-form-grid is-three">
                    <div class="master-field">
                        <label class="master-label">Document type <span class="master-required">*</span></label>
                        <select class="master-select" name="invoice_type" id="invoiceType" required @disabled($isEdit)>
                            @foreach ($typeOptions as $key => $label)
                                <option value="{{ $key }}" @selected(old('invoice_type', $invoice->invoice_type) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @if ($isEdit)
                            <input type="hidden" name="invoice_type" value="{{ $invoice->invoice_type }}">
                        @endif
                    </div>
                    <div class="master-field">
                        <label class="master-label">Number</label>
                        <input class="master-input" id="invoiceNumber" name="invoice_number"
                            value="{{ old('invoice_number', $invoice->invoice_number) }}"
                            @if (! $isEdit)
                                data-next-order="{{ $nextNumbers['order'] ?? '' }}"
                                data-next-bill="{{ $nextNumbers['bill'] ?? '' }}"
                            @endif
                            placeholder="{{ $isEdit ? '' : 'MP/PO/26-27/001' }}">
                        @unless ($isEdit)
                            <p class="master-help">Next number in this series — edit it only if you need to skip one.</p>
                        @endunless
                    </div>
                    <div class="master-field">
                        <label class="master-label">Status</label>
                        <select class="master-select" name="status">
                            @foreach ($statusOptions as $key => $label)
                                <option value="{{ $key }}" @selected(old('status', $invoice->status) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field">
                        <label class="master-label">Document date</label>
                        <input class="master-input" type="date" name="invoice_date" value="{{ old('invoice_date', optional($invoice->invoice_date)->format('Y-m-d')) }}">
                    </div>
                    <div class="master-field">
                        <label class="master-label">Due date</label>
                        <input class="master-input" type="date" name="due_date" value="{{ old('due_date', optional($invoice->due_date)->format('Y-m-d')) }}">
                    </div>
                    <div class="master-field">
                        <label class="master-label">Valid until</label>
                        <input class="master-input" type="date" name="valid_until" value="{{ old('valid_until', optional($invoice->valid_until)->format('Y-m-d')) }}">
                    </div>
                    <div class="master-field">
                        <label class="master-label">Expected date</label>
                        <input class="master-input" type="date" name="expected_date" value="{{ old('expected_date', optional($invoice->expected_date)->format('Y-m-d')) }}">
                    </div>
                    <div class="master-field">
                        <label class="master-label">Currency</label>
                        <select class="master-select" name="currency" id="invoiceCurrency">
                            @foreach ($currencyOptions as $key => $label)
                                <option value="{{ $key }}" @selected(old('currency', $invoice->currency) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field">
                        <label class="master-label">Exchange rate</label>
                        <input class="master-input" type="number" step="0.000001" min="0" name="exchange_rate" id="exchangeRate" value="{{ old('exchange_rate', $invoice->exchange_rate) }}">
                    </div>
                    <div class="master-field">
                        <label class="master-label">GST type</label>
                        <select class="master-select" name="gst_type" id="gstType">
                            @foreach ($gstTypeOptions as $key => $label)
                                <option value="{{ $key }}" @selected(old('gst_type', $invoice->gst_type) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field" id="placeOfSupplyField" data-import-hide>
                        <label class="master-label">Place of supply</label>
                        <input class="master-input" name="place_of_supply" value="{{ old('place_of_supply', $invoice->place_of_supply) }}">
                    </div>
                    @if (old('invoice_type', $invoice->invoice_type) === 'bill')
                        <div class="master-field">
                            <label class="master-label">Vendor bill no.</label>
                            <input class="master-input" name="vendor_bill_number" value="{{ old('vendor_bill_number', $invoice->vendor_bill_number) }}">
                        </div>
                        <div class="master-field">
                            <label class="master-label">Vendor bill date</label>
                            <input class="master-input" type="date" name="vendor_bill_date" value="{{ old('vendor_bill_date', optional($invoice->vendor_bill_date)->format('Y-m-d')) }}">
                        </div>
                    @endif
                    <div class="master-field">
                        <label class="master-label">Our reference</label>
                        <input class="master-input" name="our_reference" value="{{ old('our_reference', $invoice->our_reference) }}">
                    </div>
                    <div class="master-field">
                        <label class="master-label">Purchase person</label>
                        <input class="master-input" name="purchase_person" value="{{ old('purchase_person', $invoice->purchase_person) }}">
                    </div>
                </div>
            </div>

            <div class="master-card master-section">
                <div class="master-section-head"><div><p class="master-eyebrow">Parties</p><h2>Vendor &amp; buyer</h2></div></div>
                <div class="master-form-grid is-three">
                    <div class="master-field">
                        <label class="master-label">Vendor</label>
                        <select class="master-select" name="vendor_id" id="vendorSelect"
                            data-snapshot-fields="{{ json_encode($vendorSnapshotFields) }}">
                            <option value="">Select vendor</option>
                            @foreach ($vendors as $vendorRow)
                                <option value="{{ $vendorRow->id }}"
                                    data-snapshot="{{ json_encode($vendorSnapshots[$vendorRow->id] ?? []) }}"
                                    @selected((string) old('vendor_id', $invoice->vendor_id) === (string) $vendorRow->id)>
                                    {{ $vendorRow->vendor_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field">
                        <label class="master-label">Project</label>
                        <select class="master-select" name="project_id">
                            <option value="">No project</option>
                            @foreach ($projects as $projectRow)
                                <option value="{{ $projectRow->id }}" @selected((string) old('project_id', $invoice->project_id) === (string) $projectRow->id)>
                                    {{ $projectRow->project_number }} - {{ $projectRow->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <p class="master-section-label">Copied onto the document</p>
                <div class="master-form-grid is-three">
                    @foreach ([
                        'vendor_company_name' => 'Vendor company',
                        'vendor_contact_name' => 'Contact',
                        'vendor_email' => 'Email',
                        'vendor_mobile' => 'Mobile',
                        'vendor_gstin' => 'GSTIN',
                        'vendor_pan' => 'PAN',
                        'vendor_city' => 'City',
                        'vendor_state' => 'State',
                        'vendor_country' => 'Country',
                        'vendor_pincode' => 'Pincode',
                    ] as $field => $label)
                        <div class="master-field">
                            <label class="master-label" for="{{ $field }}">{{ $label }}</label>
                            <input class="master-input" name="{{ $field }}" id="{{ $field }}" value="{{ old($field, $invoice->{$field}) }}">
                        </div>
                    @endforeach
                    <div class="master-field full">
                        <label class="master-label">Vendor address</label>
                        <textarea class="master-textarea" rows="2" name="vendor_address">{{ old('vendor_address', $invoice->vendor_address) }}</textarea>
                    </div>
                </div>
            </div>

            <div class="master-card master-section">
                <div class="master-section-head">
                    <div>
                        <p class="master-eyebrow">Items</p>
                        <h2>Lines</h2>
                    </div>
                    <button type="button" class="master-btn master-btn-soft" id="addItemRow"><i class="fas fa-plus"></i> Add product</button>
                </div>
                <div class="si-items master-table-wrap">
                    <table class="master-table">
                        <thead>
                            <tr>
                                <th width="28%">Product</th>
                                <th width="10%">HSN/SAC</th>
                                <th width="9%" class="is-num">Qty</th>
                                <th width="8%">Unit</th>
                                <th width="12%" class="is-num">Rate</th>
                                <th width="9%" class="is-num">Disc %</th>
                                <th width="9%" class="is-num si-tax-col">GST %</th>
                                <th width="12%" class="is-num">Amount</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="invoiceItemsBody"
                            data-ledger-received="{{ $ledgerReceived }}"
                            data-product-options='@json($productOptionsJson)'
                            data-initial-items='@json($initialInvoiceItems)'></tbody>
                    </table>
                </div>
            </div>

            <div class="master-grid is-even">
                <div class="master-card master-section">
                    <div class="master-section-head"><div><p class="master-eyebrow">Terms</p><h2>Terms &amp; notes</h2></div></div>
                    <div class="master-form-grid">
                        <div class="master-field"><label class="master-label">Payment terms</label><input class="master-input" name="payment_terms" value="{{ old('payment_terms', $invoice->payment_terms) }}"></div>
                        <div class="master-field"><label class="master-label">Delivery terms</label><input class="master-input" name="delivery_terms" value="{{ old('delivery_terms', $invoice->delivery_terms) }}"></div>
                        <div class="master-field"><label class="master-label">Dispatch terms</label><input class="master-input" name="dispatch_terms" value="{{ old('dispatch_terms', $invoice->dispatch_terms) }}"></div>
                        <div class="master-field"><label class="master-label">Transport</label><input class="master-input" name="transport_mode" value="{{ old('transport_mode', $invoice->transport_mode) }}"></div>
                        <div class="master-field full">
                            <label class="master-label">Terms &amp; conditions</label>
                            <textarea class="master-textarea" name="terms_conditions" rows="8">{{ old('terms_conditions', $invoice->terms_conditions ?: $defaultTerms) }}</textarea>
                        </div>
                        <div class="master-field full">
                            <label class="master-label">Notes</label>
                            <textarea class="master-textarea" name="notes" rows="3">{{ old('notes', $invoice->notes) }}</textarea>
                        </div>
                        <div class="master-field full">
                            <label class="master-label">Internal notes</label>
                            <textarea class="master-textarea" name="internal_notes" rows="3">{{ old('internal_notes', $invoice->internal_notes) }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="master-card master-section">
                    <div class="master-section-head"><div><p class="master-eyebrow">Totals</p><h2>Charges &amp; summary</h2></div></div>
                    <div class="master-form-grid">
                        <div class="master-field">
                            <label class="master-label">Discount type</label>
                            <select class="master-select" name="discount_type" id="discountType">
                                <option value="amount" @selected(old('discount_type', $invoice->discount_type) === 'amount')>Amount</option>
                                <option value="percent" @selected(old('discount_type', $invoice->discount_type) === 'percent')>Percent</option>
                            </select>
                        </div>
                        <div class="master-field"><label class="master-label">Discount value</label><input class="master-input" type="number" step="0.01" min="0" name="discount_value" id="discountValue" value="{{ old('discount_value', $invoice->discount_value) }}"></div>
                        <div class="master-field"><label class="master-label">Freight</label><input class="master-input" type="number" step="0.01" min="0" name="freight_amount" id="freightAmount" value="{{ old('freight_amount', $invoice->freight_amount) }}"></div>
                        <div class="master-field"><label class="master-label">Packing</label><input class="master-input" type="number" step="0.01" min="0" name="packing_amount" id="packingAmount" value="{{ old('packing_amount', $invoice->packing_amount) }}"></div>
                        <div class="master-field"><label class="master-label">Other charges</label><input class="master-input" type="number" step="0.01" min="0" name="other_charges" id="otherCharges" value="{{ old('other_charges', $invoice->other_charges) }}"></div>
                        <div class="master-field"><label class="master-label">Round off</label><input class="master-input" type="number" step="0.01" name="round_off" id="roundOff" value="{{ old('round_off', $invoice->round_off) }}"></div>
                        <div class="master-field full">
                            <label class="master-label">Opening paid</label>
                            <input class="master-input" type="number" step="0.01" min="0" name="amount_paid" id="amountPaid" value="{{ old('amount_paid', $invoice->amount_paid) }}">
                            <p class="master-help">Money already paid outside the vendor ledger. Payments filed against this bill are added on top.</p>
                        </div>
                    </div>
                    <div class="si-ledger" id="invoiceLedger">
                        <div class="si-total-row"><span>Subtotal</span><strong id="previewSubtotal">₹ 0.00</strong></div>
                        <div class="si-total-row" id="rowLineDiscount" hidden><span>Line discounts</span><strong id="previewLineDiscount">₹ 0.00</strong></div>
                        <div class="si-total-row" id="rowInvoiceDiscount" hidden><span>Invoice discount</span><strong id="previewInvoiceDiscount">₹ 0.00</strong></div>
                        <div class="si-total-row"><span>Taxable value</span><strong id="previewTaxable">₹ 0.00</strong></div>
                        <div class="si-total-row" id="rowCgst" hidden><span>CGST</span><strong id="previewCgst">₹ 0.00</strong></div>
                        <div class="si-total-row" id="rowSgst" hidden><span>SGST</span><strong id="previewSgst">₹ 0.00</strong></div>
                        <div class="si-total-row" id="rowIgst" hidden><span>IGST</span><strong id="previewIgst">₹ 0.00</strong></div>
                        <div class="si-total-row" id="rowFreight" hidden><span>Freight</span><strong id="previewFreight">₹ 0.00</strong></div>
                        <div class="si-total-row" id="rowPacking" hidden><span>Packing</span><strong id="previewPacking">₹ 0.00</strong></div>
                        <div class="si-total-row" id="rowOther" hidden><span>Other charges</span><strong id="previewOther">₹ 0.00</strong></div>
                        <div class="si-total-row" id="rowRoundOff" hidden><span>Round off</span><strong id="previewRoundOff">₹ 0.00</strong></div>
                        <div class="si-total-row is-grand"><span>Total</span><strong id="previewTotal">₹ 0.00</strong></div>
                        <div class="si-total-row" id="rowReceived" hidden><span>Paid so far</span><strong id="previewReceived">₹ 0.00</strong></div>
                        <div class="si-total-row"><span>Balance due</span><strong id="previewBalance" class="si-due">₹ 0.00</strong></div>
                    </div>
                </div>
            </div>

            @foreach ($buyerDefaults as $field => $value)
                <input type="hidden" name="{{ $field }}" value="{{ old($field, $invoice->{$field} ?: $value) }}">
            @endforeach

            <div class="master-actions is-sticky">
                <a href="{{ $isEdit ? route($routePrefix.'.show', $invoice) : route($routePrefix.'.index') }}" class="master-btn master-btn-light-dark">Cancel</a>
                <button class="master-btn master-btn-primary" type="submit">
                    {{ $isEdit ? 'Update' : 'Create' }} {{ $docLabels[$docType] }}
                </button>
            </div>
        </form>
    </div>

@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/sales-invoices.css') }}">
@endpush
@push('scripts')
    <script src="{{ $assetVer('assets/js/purchase-invoices.js') }}"></script>
@endpush
@endsection
