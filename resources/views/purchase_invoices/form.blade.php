@extends('layouts.app')

@php
    /* Blade runs the page top to bottom, and the title is set before the body is
       rendered: the words have to be in hand here, not down in `content`. */
    $isEdit = $invoice->exists;
    $isOrder = $docType === 'order';
    $docTitle = $isOrder ? 'Purchase Order' : 'Purchase Bill';
    $prefix = $isOrder ? 'purchase-orders' : 'purchase-bills';
    $noun = $isOrder ? 'purchase order' : 'purchase bill';
@endphp

@section('page-title', ($isEdit ? 'Edit ' : 'Create ').$docTitle)

@section('content')
    @php

        $invoiceItems = old('items', $isEdit ? $invoice->items->toArray() : []);
        if (! $invoiceItems) {
            $invoiceItems = [[
                'product_name' => '',
                'quantity' => 1,
                'unit' => 'pcs',
                'unit_price' => 0,
                'discount_percent' => 0,
                'gst_percent' => 18,
            ]];
        }
        $initialInvoiceItems = array_values($invoiceItems);

        $productOptionsJson = $products
            ->sortBy('id')
            ->map(fn ($product) => [
                'id' => $product->id,
                'name' => $product->name,
                'no' => $product->product_number,
                'description' => $product->description ?? '',
            ])
            ->values()
            ->all();

        /* The money the form's live preview cannot see: payments already filed
           against this bill in the vendor ledger. The saved balance is
           `max(total − (opening paid + these), 0)` — the model's rule — so the
           preview is handed the same figure the server will use. */
        $ledgerPaid = $isEdit ? round($invoice->paidAmount() - (float) $invoice->amount_paid, 2) : 0;
    @endphp

    <div class="pi-form">
        {{-- The header every office screen wears: a card, the title, the way back,
             and — on a saved document — the state it is in and what it became. --}}
        <div class="master-card master-header">
            <div>
                <h1>{{ $isEdit ? 'Edit' : 'Create' }} {{ $docTitle }}</h1>
                <div class="master-breadcrumb">
                    <a href="{{ url('/') }}">Home</a><span>•</span>
                    <a href="{{ route($prefix.'.index') }}">{{ $isOrder ? 'Purchase Orders' : 'Purchase Bills' }}</a><span>•</span>
                    <span class="active">{{ $isEdit ? 'Edit' : 'New' }}</span>
                </div>
                @if ($isEdit)
                    <div class="record-head-chips">
                        <span class="pi-status status-{{ $invoice->stateKey() }}">{{ $invoice->stateLabel() }}</span>
                        <span class="master-chip"><i class="fas fa-hashtag" aria-hidden="true"></i>{{ $invoice->invoice_number }}</span>
                        @if ($invoice->vendor_bill_number)
                            <span class="master-chip"><i class="fas fa-file-invoice" aria-hidden="true"></i>Vendor {{ $invoice->vendor_bill_number }}</span>
                        @endif
                        @if ($invoice->isSuperseded())
                            <span class="master-chip"><i class="fas fa-arrow-right-arrow-left" aria-hidden="true"></i>Billed as
                                {{ $invoice->convertedInvoice?->invoice_number }}</span>
                        @elseif ($invoice->purchaseOrder)
                            <span class="master-chip"><i class="fas fa-file-signature" aria-hidden="true"></i>From
                                {{ $invoice->purchaseOrder->invoice_number }}</span>
                        @endif
                    </div>
                @else
                    <p class="master-sub">
                        Vendor details come from the vendor's own record. Add the items being bought — the bill that
                        arrives is raised from this order, with its lines copied onto it.
                    </p>
                @endif
            </div>
            <div class="master-actions">
                @if ($isEdit)
                    <a href="{{ route($prefix.'.show', $invoice) }}" class="master-btn master-btn-light">
                        <i class="fas fa-eye" aria-hidden="true"></i> View {{ $isOrder ? 'order' : 'bill' }}</a>
                @endif
                <a href="{{ $isEdit ? route($prefix.'.show', $invoice) : route($prefix.'.index') }}"
                    class="master-btn master-btn-light">Back</a>
            </div>
        </div>

        @if ($errors->any())
            <div class="master-alert error"><strong>Please fix:</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ $isEdit ? route($prefix.'.update', $invoice) : route($prefix.'.store') }}"
            class="master-form">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <div class="master-card master-section">
                <div class="master-section-head">
                    <div>
                        <p class="master-eyebrow">Basics</p>
                        <h2>{{ $docTitle }} Details</h2>
                    </div>
                </div>

                <p class="master-section-label">Identity</p>
                <div class="master-form-grid is-three">
                    <div class="master-field">
                        <label class="master-label" for="invoiceNumber">{{ $isOrder ? 'Order' : 'Bill' }} No.</label>
                        <input class="master-input" id="invoiceNumber" name="invoice_number"
                            value="{{ old('invoice_number', $invoice->invoice_number) }}" placeholder="Auto if blank">
                        <p class="master-help">Left blank, the next number in the {{ $isOrder ? 'MP/PO' : 'MP/PB' }} series is taken on save.</p>
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="invoiceStatus">Status</label>
                        <select class="master-select" id="invoiceStatus" name="status">
                            @foreach ($statusOptions as $key => $label)
                                <option value="{{ $key }}" {{ old('status', $invoice->status) === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @if (! $isEdit)
                            <p class="master-help">A bill becomes invoiceable once it is marked received.</p>
                        @endif
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="invoiceCurrency">Currency</label>
                        <select class="master-select" id="invoiceCurrency" name="currency">
                            @foreach ($currencyOptions as $key => $label)
                                <option value="{{ $key }}" {{ old('currency', $invoice->currency) === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <p class="master-section-label">Dates</p>
                <div class="master-form-grid is-three">
                    <div class="master-field">
                        <label class="master-label" for="invoiceDate">{{ $isOrder ? 'Order date' : 'Bill date' }}</label>
                        <input class="master-input" type="date" id="invoiceDate" name="invoice_date"
                            value="{{ old('invoice_date', optional($invoice->invoice_date)->format('Y-m-d')) }}">
                    </div>
                    @if ($isOrder)
                        <div class="master-field">
                            <label class="master-label" for="expectedDate">Expected by</label>
                            <input class="master-input" type="date" id="expectedDate" name="expected_date"
                                value="{{ old('expected_date', optional($invoice->expected_date)->format('Y-m-d')) }}">
                            <p class="master-help">The date the supplier has promised. It drives nothing but the chase.</p>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="validUntil">Valid until</label>
                            <input class="master-input" type="date" id="validUntil" name="valid_until"
                                value="{{ old('valid_until', optional($invoice->valid_until)->format('Y-m-d')) }}">
                            <p class="master-help">The rate holds until this date; after it, ask for a fresh quote.</p>
                        </div>
                    @else
                        <div class="master-field">
                            <label class="master-label" for="dueDate">Due date</label>
                            <input class="master-input" type="date" id="dueDate" name="due_date"
                                value="{{ old('due_date', optional($invoice->due_date)->format('Y-m-d')) }}">
                            <p class="master-help">Overdue, the ageing and the chase all count from this date.</p>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="vendorBillNumber">Vendor's bill no.</label>
                            <input class="master-input" id="vendorBillNumber" name="vendor_bill_number"
                                value="{{ old('vendor_bill_number', $invoice->vendor_bill_number) }}"
                                placeholder="Their number, as printed">
                            <p class="master-help">The number the vendor's own bill carries — the one the office reconciles against.</p>
                        </div>
                    @endif
                </div>

                @if (! $isOrder)
                    <p class="master-section-label">The vendor's paperwork</p>
                    <div class="master-form-grid is-three">
                        <div class="master-field">
                            <label class="master-label" for="vendorBillDate">Their bill date</label>
                            <input class="master-input" type="date" id="vendorBillDate" name="vendor_bill_date"
                                value="{{ old('vendor_bill_date', optional($invoice->vendor_bill_date)->format('Y-m-d')) }}">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="ourReference">Our reference</label>
                            <input class="master-input" id="ourReference" name="our_reference"
                                value="{{ old('our_reference', $invoice->our_reference) }}" placeholder="GRN / gate entry / docket">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="purchasePerson">Purchase person</label>
                            <input class="master-input" id="purchasePerson" name="purchase_person"
                                value="{{ old('purchase_person', $invoice->purchase_person) }}">
                        </div>
                    </div>
                @endif

                <p class="master-section-label">Tax</p>
                <div class="master-form-grid is-three">
                    <div class="master-field">
                        <label class="master-label" for="gstType">GST Type</label>
                        <select class="master-select" id="gstType" name="gst_type">
                            @foreach ($gstTypeOptions as $key => $label)
                                <option value="{{ $key }}" {{ old('gst_type', $invoice->gst_type) === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="master-help">An import carries no GST on its lines. Picking a vendor sets this from their state.</p>
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="placeOfSupply">Place of supply</label>
                        <input class="master-input" id="placeOfSupply" name="place_of_supply"
                            value="{{ old('place_of_supply', $invoice->place_of_supply) }}">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="exchangeRate">Exchange rate</label>
                        <input class="master-input" type="number" step="0.000001" min="0" id="exchangeRate" name="exchange_rate"
                            value="{{ old('exchange_rate', $invoice->exchange_rate) }}" placeholder="1">
                        <p class="master-help">Only for a foreign-currency document. The vendor ledger stores the rupee value at this rate.</p>
                    </div>
                </div>
            </div>

            <div class="master-card master-section">
                <div class="master-section-head">
                    <div>
                        <p class="master-eyebrow">Vendor Mapping</p>
                        <h2>Vendor / Project</h2>
                    </div>
                </div>

                <p class="master-section-label">Who it is from</p>
                <div class="master-form-grid is-three">
                    <div class="master-field">
                        <label class="master-label" for="vendorSelect">Vendor</label>
                        <select class="master-select" name="vendor_id" id="vendorSelect"
                            data-snapshot-fields="{{ json_encode($vendorSnapshotFields) }}">
                            <option value="">Select Vendor</option>
                            @foreach ($vendors as $vendor)
                                <option value="{{ $vendor->id }}"
                                    data-snapshot="{{ json_encode($vendorSnapshots[$vendor->id] ?? []) }}"
                                    {{ (string) old('vendor_id', $invoice->vendor_id) === (string) $vendor->id ? 'selected' : '' }}>
                                    {{ $vendor->vendor_number ? $vendor->vendor_number.' · ' : '' }}{{ $vendor->vendor_name }}</option>
                            @endforeach
                        </select>
                        <p class="master-help">Picking a vendor brings its GSTIN, PAN, contact and address onto this document,
                            in the fields below. The document keeps its own copy, so a later edit to the vendor record never
                            rewrites a bill that has already been received.</p>
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="projectSelect">Project</label>
                        <select class="master-select" id="projectSelect" name="project_id">
                            <option value="">No Project</option>
                            @foreach ($projects as $project)
                                <option value="{{ $project->id }}"
                                    {{ (string) old('project_id', $invoice->project_id) === (string) $project->id ? 'selected' : '' }}>
                                    {{ $project->project_number }} - {{ $project->name }}</option>
                            @endforeach
                        </select>
                        <p class="master-help">What the material is bought for. The project's own costs read from this.</p>
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="sourceOrder">Raised from order</label>
                        <input class="master-input" id="sourceOrder"
                            value="{{ $sourceOrder?->invoice_number ?: 'Not raised from an order' }}" readonly>
                        @if ($sourceOrder)
                            <p class="master-help">Every line and term on this bill was copied from that order, and the
                                order is billed from here on — it cannot be billed a second time.</p>
                        @else
                            <p class="master-help">A bill is raised from an order by that order's own
                                <em>Convert to purchase bill</em> action, so the two can never disagree about what was
                                bought and nothing is billed twice.</p>
                        @endif
                    </div>
                </div>

                <p class="master-section-label">Copied from the vendor record</p>
                <div class="master-form-grid is-three">
                    <div class="master-field"><label class="master-label" for="vendor_company_name">Vendor name</label>
                        <input class="master-input" name="vendor_company_name" id="vendor_company_name"
                            value="{{ old('vendor_company_name', $invoice->vendor_company_name) }}"></div>
                    <div class="master-field"><label class="master-label" for="vendor_contact_name">Contact</label>
                        <input class="master-input" name="vendor_contact_name" id="vendor_contact_name"
                            value="{{ old('vendor_contact_name', $invoice->vendor_contact_name) }}"></div>
                    <div class="master-field"><label class="master-label" for="vendor_email">Email</label>
                        <input class="master-input" name="vendor_email" id="vendor_email"
                            value="{{ old('vendor_email', $invoice->vendor_email) }}"></div>
                    <div class="master-field"><label class="master-label" for="vendor_mobile">Mobile</label>
                        <input class="master-input" name="vendor_mobile" id="vendor_mobile"
                            value="{{ old('vendor_mobile', $invoice->vendor_mobile) }}"></div>
                    <div class="master-field"><label class="master-label" for="vendor_gstin">GSTIN</label>
                        <input class="master-input" name="vendor_gstin" id="vendor_gstin"
                            value="{{ old('vendor_gstin', $invoice->vendor_gstin) }}"></div>
                    <div class="master-field"><label class="master-label" for="vendor_pan">PAN</label>
                        <input class="master-input" name="vendor_pan" id="vendor_pan"
                            value="{{ old('vendor_pan', $invoice->vendor_pan) }}"></div>
                    <div class="master-field"><label class="master-label" for="vendor_country">Country</label>
                        <input class="master-input" name="vendor_country" id="vendor_country"
                            value="{{ old('vendor_country', $invoice->vendor_country) }}"></div>
                    <div class="master-field"><label class="master-label" for="vendor_state">State</label>
                        <input class="master-input" name="vendor_state" id="vendor_state"
                            value="{{ old('vendor_state', $invoice->vendor_state) }}"></div>
                    <div class="master-field"><label class="master-label" for="vendor_city">City</label>
                        <input class="master-input" name="vendor_city" id="vendor_city"
                            value="{{ old('vendor_city', $invoice->vendor_city) }}"></div>
                    <div class="master-field"><label class="master-label" for="vendor_pincode">Pincode</label>
                        <input class="master-input" name="vendor_pincode" id="vendor_pincode"
                            value="{{ old('vendor_pincode', $invoice->vendor_pincode) }}"></div>
                    <div class="master-field full"><label class="master-label" for="vendor_address">Address</label>
                        <textarea class="master-textarea" rows="2" name="vendor_address"
                            id="vendor_address">{{ old('vendor_address', $invoice->vendor_address) }}</textarea></div>
                </div>
            </div>

            <div class="master-card master-section">
                <div class="master-section-head">
                    <div>
                        <p class="master-eyebrow">Items</p>
                        <h2>{{ $isOrder ? 'Items Ordered' : 'Items Billed' }}</h2>
                        <p class="master-sub">One line per item. Rate is per unit; the line amount is what the supplier
                            charges for it, GST included.</p>
                    </div>
                    <button type="button" class="master-btn master-btn-soft" id="addItemRow">
                        <i class="fas fa-plus" aria-hidden="true"></i> Add Item</button>
                </div>
                <div class="pi-items master-table-wrap">
                    <table class="master-table">
                        <thead>
                            <tr>
                                <th width="28%">Item</th>
                                <th width="10%">HSN/SAC</th>
                                <th width="9%" class="is-num">Qty</th>
                                <th width="8%">Unit</th>
                                <th width="12%" class="is-num">Rate</th>
                                <th width="9%" class="is-num">Disc %</th>
                                <th width="9%" class="is-num">GST %</th>
                                <th width="12%" class="is-num">Amount</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="purchaseItemsBody"
                            data-ledger-paid="{{ $ledgerPaid }}"
                            data-currency="{{ old('currency', $invoice->currency ?: 'INR') }}"
                            data-product-options='@json($productOptionsJson)'
                            data-initial-items='@json($initialInvoiceItems)'></tbody>
                    </table>
                </div>
            </div>

            <div class="master-grid is-even">
                <div>
                    <div class="master-card master-section">
                        <div class="master-section-head">
                            <div>
                                <p class="master-eyebrow">Terms</p>
                                <h2>Terms &amp; Notes</h2>
                            </div>
                        </div>
                        <div class="master-form-grid">
                            <div class="master-field"><label class="master-label" for="paymentTerms">Payment terms</label>
                                <input class="master-input" id="paymentTerms" name="payment_terms"
                                    value="{{ old('payment_terms', $invoice->payment_terms) }}"></div>
                            <div class="master-field"><label class="master-label" for="deliveryTerms">Delivery terms</label>
                                <input class="master-input" id="deliveryTerms" name="delivery_terms"
                                    value="{{ old('delivery_terms', $invoice->delivery_terms) }}"></div>
                            <div class="master-field"><label class="master-label" for="dispatchTerms">Dispatch terms</label>
                                <input class="master-input" id="dispatchTerms" name="dispatch_terms"
                                    value="{{ old('dispatch_terms', $invoice->dispatch_terms) }}"></div>
                            <div class="master-field"><label class="master-label" for="transportMode">Transport mode</label>
                                <input class="master-input" id="transportMode" name="transport_mode"
                                    value="{{ old('transport_mode', $invoice->transport_mode) }}"></div>
                            <div class="master-field full">
                                <label class="master-label" for="termsConditions">Fixed Terms &amp; Conditions</label>
                                <textarea class="master-textarea" id="termsConditions" name="terms_conditions"
                                    rows="10">{{ old('terms_conditions', $invoice->terms_conditions ?: $defaultTerms) }}</textarea>
                            </div>
                            <div class="master-field full"><label class="master-label" for="notes">Notes</label>
                                <textarea class="master-textarea" id="notes" name="notes"
                                    rows="3">{{ old('notes', $invoice->notes) }}</textarea>
                                <p class="master-help">Printed on the document and read by the vendor.</p>
                            </div>
                            <div class="master-field full"><label class="master-label" for="internalNotes">Internal notes</label>
                                <textarea class="master-textarea" id="internalNotes" name="internal_notes"
                                    rows="3">{{ old('internal_notes', $invoice->internal_notes) }}</textarea>
                                <p class="master-help">Office only — never printed, never sent.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="master-card master-section">
                        <div class="master-section-head">
                            <div>
                                <p class="master-eyebrow">Totals</p>
                                <h2>Charges &amp; Summary</h2>
                            </div>
                        </div>
                        <div class="master-form-grid">
                            <div class="master-field"><label class="master-label" for="discountType">Discount type</label>
                                <select class="master-select" name="discount_type" id="discountType">
                                    <option value="amount" {{ old('discount_type', $invoice->discount_type) === 'amount' ? 'selected' : '' }}>Amount</option>
                                    <option value="percent" {{ old('discount_type', $invoice->discount_type) === 'percent' ? 'selected' : '' }}>Percent</option>
                                </select></div>
                            <div class="master-field"><label class="master-label" for="discountValue">Discount value</label>
                                <input class="master-input" type="number" step="0.01" min="0" name="discount_value" id="discountValue"
                                    value="{{ old('discount_value', $invoice->discount_value) }}"></div>
                            <div class="master-field"><label class="master-label" for="freightAmount">Freight</label>
                                <input class="master-input" type="number" step="0.01" min="0" name="freight_amount" id="freightAmount"
                                    value="{{ old('freight_amount', $invoice->freight_amount) }}"></div>
                            <div class="master-field"><label class="master-label" for="packingAmount">Packing</label>
                                <input class="master-input" type="number" step="0.01" min="0" name="packing_amount" id="packingAmount"
                                    value="{{ old('packing_amount', $invoice->packing_amount) }}"></div>
                            <div class="master-field"><label class="master-label" for="otherCharges">Other charges</label>
                                <input class="master-input" type="number" step="0.01" min="0" name="other_charges" id="otherCharges"
                                    value="{{ old('other_charges', $invoice->other_charges) }}"></div>
                            <div class="master-field"><label class="master-label" for="roundOff">Round off</label>
                                <input class="master-input" type="number" step="0.01" name="round_off" id="roundOff"
                                    value="{{ old('round_off', $invoice->round_off) }}"></div>
                            @if (! $isOrder)
                                {{-- The bill's *opening* figure. Payments filed against it — from
                                     the listing's "Record payment", or an entry in the vendor
                                     ledger carrying this bill — are added on top, and the balance
                                     every screen prints is the two together. --}}
                                <div class="master-field full">
                                    <label class="master-label" for="amountPaid">Opening paid</label>
                                    <input class="master-input" type="number" step="0.01" min="0" name="amount_paid"
                                        id="amountPaid" value="{{ old('amount_paid', $invoice->amount_paid) }}"
                                        aria-describedby="amountPaidHint">
                                    <p class="master-help" id="amountPaidHint">Money already paid before it was recorded in the
                                        vendor ledger. Payments filed against this bill are added on top.</p>
                                </div>
                            @endif
                        </div>

                        {{-- The money, read top to bottom. Every figure here is the one
                             `syncItemsAndTotals()` will store when the form is saved: the
                             same steps, in the same order, and the rows that do not apply
                             stay out of the way. --}}
                        <div class="pi-ledger" id="purchaseLedger">
                            <div class="pi-total-row" id="rowSubtotal">
                                <span>Subtotal</span><strong id="previewSubtotal">—</strong>
                            </div>
                            <div class="pi-total-row" id="rowLineDiscount" hidden>
                                <span>Line discounts</span><strong id="previewLineDiscount">—</strong>
                            </div>
                            <div class="pi-total-row" id="rowDocDiscount" hidden>
                                <span>Document discount</span><strong id="previewDocDiscount">—</strong>
                            </div>
                            <div class="pi-total-row" id="rowTaxable">
                                <span>Taxable value</span><strong id="previewTaxable">—</strong>
                            </div>
                            <div class="pi-total-row" id="rowCgst" hidden>
                                <span>CGST</span><strong id="previewCgst">—</strong>
                            </div>
                            <div class="pi-total-row" id="rowSgst" hidden>
                                <span>SGST</span><strong id="previewSgst">—</strong>
                            </div>
                            <div class="pi-total-row" id="rowIgst" hidden>
                                <span>IGST</span><strong id="previewIgst">—</strong>
                            </div>
                            <div class="pi-total-row" id="rowFreight" hidden>
                                <span>Freight</span><strong id="previewFreight">—</strong>
                            </div>
                            <div class="pi-total-row" id="rowPacking" hidden>
                                <span>Packing</span><strong id="previewPacking">—</strong>
                            </div>
                            <div class="pi-total-row" id="rowOther" hidden>
                                <span>Other charges</span><strong id="previewOther">—</strong>
                            </div>
                            <div class="pi-total-row" id="rowRoundOff" hidden>
                                <span>Round off</span><strong id="previewRoundOff">—</strong>
                            </div>
                            <div class="pi-total-row is-grand" id="rowTotal">
                                <span>{{ $docTitle }} total</span><strong id="previewTotal">—</strong>
                            </div>
                            @if (! $isOrder)
                                <div class="pi-total-row" id="rowPaid" hidden>
                                    <span>Paid so far</span><strong id="previewPaid">—</strong>
                                </div>
                                <div class="pi-total-row" id="rowBalance">
                                    <span>Balance payable</span><strong id="previewBalance" class="pi-due">—</strong>
                                </div>
                            @endif
                            <p class="master-help">
                                Computed as you type, and computed again when you save — the saved figures are the ones
                                every screen prints. Amounts are in
                                <span data-preview-currency>{{ old('currency', $invoice->currency ?: 'INR') }}</span>.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Our own company details travel on the document as hidden fields:
                 the buyer on every purchase document is this company, and the form
                 is not the place to retype it. --}}
            @foreach ($buyerDefaults as $field => $value)
                <input type="hidden" name="{{ $field }}" value="{{ old($field, $invoice->{$field} ?: $value) }}">
            @endforeach

            <div class="master-actions is-sticky">
                <a href="{{ $isEdit ? route($prefix.'.show', $invoice) : route($prefix.'.index') }}"
                    class="master-btn master-btn-light-dark">Cancel</a>
                <button class="master-btn master-btn-primary" type="submit">
                    <i class="fas fa-floppy-disk" aria-hidden="true"></i>
                    {{ $isEdit ? 'Update' : 'Create' }} {{ $isOrder ? 'Order' : 'Bill' }}</button>
            </div>
        </form>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/purchase-invoices.css') }}">
@endpush

@push('scripts')
    <script src="{{ $assetVer('assets/js/purchase-invoices.js') }}"></script>
@endpush
