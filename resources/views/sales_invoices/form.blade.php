@extends('layouts.app')

@section('page-title', $invoice->exists ? 'Edit Invoice' : 'Create Invoice')

@section('content')
    @php
        $isEdit = $invoice->exists;

        $invoiceItems = old('items', $invoice->relationLoaded('items') ? $invoice->items->toArray() : []);
        if (! $invoiceItems) {
            $invoiceItems = [
                [
                    'product_name' => '',
                    'quantity' => 1,
                    'unit' => 'pcs',
                    'unit_price' => 0,
                    'discount_percent' => 0,
                    'gst_percent' => 18,
                ],
            ];
        }
        $initialInvoiceItems = array_values($invoiceItems);

        $productOptionsJson = $products
            ->sortBy('id')
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'no' => $product->product_number,
                    'description' => $product->description ?? '',
                    'price' => 0,
                ];
            })
            ->values()
            ->all();

        /* The money the form's live preview cannot see: receipts already filed
           against this invoice in the ledger. The saved balance is
           `max(total − (opening received + these), 0)` — the model's rule — so
           the preview is handed the same figure the server will use. */
        $ledgerReceived = $isEdit ? round($invoice->receivedAmount() - (float) $invoice->amount_paid, 2) : 0;
    @endphp
    <div class="si-form">
        {{-- The header every office screen wears: a card, the title, the way
             back, and — on a saved invoice — the state it is in. --}}
        <div class="master-card master-header">
            <div>
                <h1>{{ $isEdit ? 'Edit' : 'Create' }}
                    {{ $invoice->invoice_type === 'tax' ? 'Tax Invoice' : 'Proforma Invoice' }}</h1>
                <div class="master-breadcrumb"><a href="{{ url('/') }}">Home</a><span>•</span><a
                        href="{{ route('sales-invoices.index') }}">Invoices</a><span>•</span><span
                        class="active">{{ $isEdit ? 'Edit' : 'New' }}</span></div>
                @if ($isEdit)
                    <div class="record-head-chips">
                        <span class="si-status status-{{ $invoice->stateKey() }}">{{ $invoice->stateLabel() }}</span>
                        <span class="master-chip"><i class="fas fa-hashtag" aria-hidden="true"></i>{{ $invoice->invoice_number }}</span>
                        @if ($invoice->isSuperseded())
                            <span class="master-chip"><i class="fas fa-arrow-right-arrow-left" aria-hidden="true"></i>Converted to
                                {{ $invoice->convertedInvoice?->invoice_number }}</span>
                        @endif
                    </div>
                @else
                    <p class="master-sub">Client details come from the client's own record. Add products, then print or
                        share it to the client portal.</p>
                @endif
            </div>
            <div class="master-actions">
                @if ($isEdit)
                    <a href="{{ route('sales-invoices.show', $invoice) }}" class="master-btn master-btn-light"><i
                            class="fas fa-eye" aria-hidden="true"></i> View invoice</a>
                @endif
                <a href="{{ $isEdit ? route('sales-invoices.show', $invoice) : route('sales-invoices.index') }}"
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

        <form method="POST" action="{{ $isEdit ? route('sales-invoices.update', $invoice) : route('sales-invoices.store') }}"
            enctype="multipart/form-data" class="master-form" @if (! $isEdit) data-invoice-portal-confirm data-invoice-auto-gst="{{ old('_invoice_auto_gst', 'true') }}" @endif>
            @csrf
            @unless ($isEdit)
                <input type="hidden" name="_invoice_auto_gst" value="{{ old('_invoice_auto_gst', 'true') }}">
            @endunless
            @if ($isEdit)
                @method('PUT')
            @endif

            <div class="master-card master-section">
                <div class="master-section-head">
                    <div>
                        <p class="master-eyebrow">Basics</p>
                        <h2>Invoice Details</h2>
                    </div>
                </div>

                <p class="master-section-label">Identity</p>
                <div class="master-form-grid is-three">
                    <div class="master-field"><label class="master-label" for="invoiceType">Invoice Type <span
                                class="master-required">*</span></label><select class="master-select" name="invoice_type"
                            id="invoiceType" required>
                            @foreach ($typeOptions as $key => $label)
                                <option value="{{ $key }}"
                                    {{ old('invoice_type', $invoice->invoice_type) === $key ? 'selected' : '' }}>
                                    {{ $label }}</option>
                            @endforeach
                        </select>
                        @error('invoice_type')
                            <div class="master-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="master-field"><label class="master-label" for="invoiceNumber">Invoice No.</label><input
                            class="master-input" id="invoiceNumber" name="invoice_number"
                            value="{{ old('invoice_number', $invoice->invoice_number) }}" placeholder="Auto if blank">
                        <p class="master-help">Left blank, the next number for this type is taken on save.</p>
                    </div>
                    <div class="master-field"><label class="master-label" for="invoiceStatus">Status</label><select
                            class="master-select" id="invoiceStatus" name="status">
                            @foreach ($statusOptions as $key => $label)
                                <option value="{{ $key }}"
                                    {{ old('status', $invoice->status) === $key ? 'selected' : '' }}>{{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <p class="master-section-label">Dates</p>
                <div class="master-form-grid is-three">
                    <div class="master-field"><label class="master-label" for="invoiceDate">Invoice Date</label><input
                            class="master-input" type="date" id="invoiceDate" name="invoice_date"
                            value="{{ old('invoice_date', optional($invoice->invoice_date)->format('Y-m-d')) }}">
                    </div>
                    <div class="master-field"><label class="master-label" for="dueDate">Due Date</label><input
                            class="master-input" type="date" id="dueDate" name="due_date"
                            value="{{ old('due_date', optional($invoice->due_date)->format('Y-m-d')) }}">
                        <p class="master-help">Overdue, the ageing buckets and the chase all count from this date.</p>
                    </div>
                    <div class="master-field"><label class="master-label" for="validUntil">Valid Until</label><input
                            class="master-input" type="date" id="validUntil" name="valid_until"
                            value="{{ old('valid_until', optional($invoice->valid_until)->format('Y-m-d')) }}">
                    </div>
                </div>

                <p class="master-section-label">Tax & reference</p>
                <div class="master-form-grid is-three">
                    <div class="master-field"><label class="master-label" for="invoiceCurrency">Currency</label><select
                            class="master-select" id="invoiceCurrency" name="currency">
                            @foreach ($currencyOptions as $key => $label)
                                <option value="{{ $key }}"
                                    {{ old('currency', $invoice->currency) === $key ? 'selected' : '' }}>
                                    {{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field"><label class="master-label" for="exchangeRate">Exchange Rate</label><input
                            class="master-input" type="number" step="0.000001" min="0" id="exchangeRate"
                            name="exchange_rate" value="{{ old('exchange_rate', $invoice->exchange_rate) }}"
                            placeholder="1">
                        <p class="master-help">Only for a foreign-currency invoice.</p>
                    </div>
                    <div class="master-field"><label class="master-label" for="gstType">GST Type</label><select
                            class="master-select" id="gstType" name="gst_type">
                            @foreach ($gstTypeOptions as $key => $label)
                                <option value="{{ $key }}"
                                    {{ old('gst_type', $invoice->gst_type) === $key ? 'selected' : '' }}>
                                    {{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="master-help">An export invoice carries no GST on its lines.</p>
                    </div>
                    <div class="master-field"><label class="master-label" for="placeOfSupply">Place of Supply</label><input
                            class="master-input" id="placeOfSupply" name="place_of_supply"
                            value="{{ old('place_of_supply', $invoice->place_of_supply) }}"></div>
                    <div class="master-field"><label class="master-label" for="poNumber">PO Number</label><input
                            class="master-input" id="poNumber" name="po_number"
                            value="{{ old('po_number', $invoice->po_number) }}"></div>
                    <div class="master-field"><label class="master-label" for="poDate">PO Date</label><input
                            class="master-input" type="date" id="poDate" name="po_date"
                            value="{{ old('po_date', optional($invoice->po_date)->format('Y-m-d')) }}"></div>
                    <div class="master-field"><label class="master-label" for="salesPerson">Sales Person</label><input
                            class="master-input" id="salesPerson" name="sales_person"
                            value="{{ old('sales_person', $invoice->sales_person) }}"></div>
                </div>

                <div class="master-form-grid">
                    <div class="master-field full">
                        @if ($isEdit)
                            <label class="master-check full"><input type="checkbox" name="show_client_portal" value="1"
                                    {{ old('show_client_portal', $invoice->show_client_portal) ? 'checked' : '' }}> Visible
                                in the client portal</label>
                            <p class="master-help">While this is on, the client can open the invoice at its public link and
                                download the files marked client-visible.</p>
                        @else
                            <input type="hidden" name="show_client_portal" value="0" data-invoice-portal-choice>
                            <p class="master-help">You’ll be asked whether to show this invoice in the client portal when
                                you create it.</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="master-card master-section">
                <div class="master-section-head">
                    <div>
                        <p class="master-eyebrow">Client Mapping</p>
                        <h2>Client / Project</h2>
                    </div>
                </div>

                <p class="master-section-label">Who it is for</p>
                <div class="master-form-grid is-three">
                    <div class="master-field"><label class="master-label" for="clientSelect">Client</label><select
                            class="master-select" name="client_id" id="clientSelect"
                            data-snapshot-fields="{{ json_encode($clientSnapshotFields) }}">
                            <option value="">Select Client</option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}"
                                    data-snapshot="{{ json_encode($clientSnapshots[$client->id] ?? []) }}"
                                    {{ (string) old('client_id', $invoice->client_id) === (string) $client->id ? 'selected' : '' }}>
                                    {{ $client->company_name }}</option>
                            @endforeach
                        </select>
                        <p class="master-help">Picking a client brings its GSTIN, PAN, contact and both addresses
                            onto the invoice, in the fields below. The invoice keeps its own copy, so a later edit
                            to the client record never rewrites a sent invoice.</p>
                    </div>
                    <div class="master-field"><label class="master-label" for="projectSelect">Project</label><select
                            class="master-select" id="projectSelect" name="project_id">
                            <option value="">No Project</option>
                            @foreach ($projects as $project)
                                <option value="{{ $project->id }}"
                                    {{ (string) old('project_id', $invoice->project_id) === (string) $project->id ? 'selected' : '' }}>
                                    {{ $project->project_number }} - {{ $project->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <p class="master-section-label">Copied onto the invoice</p>
                <div class="master-form-grid is-three">
                    <div class="master-field"><label class="master-label" for="client_company_name">Client
                            Company</label><input class="master-input" name="client_company_name"
                            id="client_company_name" value="{{ old('client_company_name', $invoice->client_company_name) }}">
                    </div>
                    <div class="master-field"><label class="master-label" for="client_brand_name">Brand</label><input
                            class="master-input" name="client_brand_name" id="client_brand_name"
                            value="{{ old('client_brand_name', $invoice->client_brand_name) }}"></div>
                    <div class="master-field"><label class="master-label" for="client_contact_name">Contact</label><input
                            class="master-input" name="client_contact_name" id="client_contact_name"
                            value="{{ old('client_contact_name', $invoice->client_contact_name) }}"></div>
                    <div class="master-field"><label class="master-label" for="client_email">Email</label><input
                            class="master-input" name="client_email" id="client_email"
                            value="{{ old('client_email', $invoice->client_email) }}"></div>
                    <div class="master-field"><label class="master-label" for="client_mobile">Mobile</label><input
                            class="master-input" name="client_mobile" id="client_mobile"
                            value="{{ old('client_mobile', $invoice->client_mobile) }}"></div>
                    <div class="master-field"><label class="master-label" for="client_gstin">GSTIN</label><input
                            class="master-input" name="client_gstin" id="client_gstin"
                            value="{{ old('client_gstin', $invoice->client_gstin) }}"></div>
                    <div class="master-field"><label class="master-label" for="client_pan">PAN</label><input
                            class="master-input" name="client_pan" id="client_pan"
                            value="{{ old('client_pan', $invoice->client_pan) }}"></div>
                </div>

                {{-- The address block is kept in its parts, with billing and shipping
                     together in a side-by-side layout on wider screens. --}}
                <div class="si-address-pair">
                    <div class="si-address-block" role="group" aria-labelledby="billingAddressLabel">
                        <div class="si-address-head">
                            <p class="master-section-label" id="billingAddressLabel">Billing address</p>
                        </div>
                        <div class="master-form-grid">
                            <div class="master-field full"><label class="master-label" for="billing_address">Address
                                    line</label>
                                <textarea class="master-textarea" rows="2" name="billing_address"
                                    id="billing_address">{{ old('billing_address', $invoice->billing_address) }}</textarea>
                            </div>
                            <div class="master-field"><label class="master-label" for="billing_city">City</label><input
                                    class="master-input" name="billing_city" id="billing_city"
                                    value="{{ old('billing_city', $invoice->billing_city) }}"></div>
                            <div class="master-field"><label class="master-label" for="billing_state">State</label><input
                                    class="master-input" name="billing_state" id="billing_state"
                                    value="{{ old('billing_state', $invoice->billing_state) }}"></div>
                            <div class="master-field"><label class="master-label" for="billing_country">Country</label><input
                                    class="master-input" name="billing_country" id="billing_country"
                                    value="{{ old('billing_country', $invoice->billing_country) }}"></div>
                            <div class="master-field"><label class="master-label" for="billing_pincode">Pincode</label><input
                                    class="master-input" name="billing_pincode" id="billing_pincode"
                                    value="{{ old('billing_pincode', $invoice->billing_pincode) }}"></div>
                        </div>
                    </div>

                    <div class="si-address-block" role="group" aria-labelledby="shippingAddressLabel">
                        <div class="si-address-head">
                            <p class="master-section-label" id="shippingAddressLabel">Shipping address</p>
                            <button type="button" class="master-btn master-btn-soft" data-copy-billing="1"><i
                                    class="fas fa-copy" aria-hidden="true"></i> Same as billing</button>
                        </div>
                        <div class="master-form-grid">
                            <div class="master-field full"><label class="master-label" for="shipping_address">Address
                                    line</label>
                                <textarea class="master-textarea" rows="2" name="shipping_address"
                                    id="shipping_address">{{ old('shipping_address', $invoice->shipping_address) }}</textarea>
                            </div>
                            <div class="master-field"><label class="master-label" for="shipping_city">City</label><input
                                    class="master-input" name="shipping_city" id="shipping_city"
                                    value="{{ old('shipping_city', $invoice->shipping_city) }}"></div>
                            <div class="master-field"><label class="master-label" for="shipping_state">State</label><input
                                    class="master-input" name="shipping_state" id="shipping_state"
                                    value="{{ old('shipping_state', $invoice->shipping_state) }}"></div>
                            <div class="master-field"><label class="master-label" for="shipping_country">Country</label><input
                                    class="master-input" name="shipping_country" id="shipping_country"
                                    value="{{ old('shipping_country', $invoice->shipping_country) }}"></div>
                            <div class="master-field"><label class="master-label" for="shipping_pincode">Pincode</label><input
                                    class="master-input" name="shipping_pincode" id="shipping_pincode"
                                    value="{{ old('shipping_pincode', $invoice->shipping_pincode) }}"></div>
                            <div class="master-field full">
                                <p class="master-help">Left empty, the invoice is dispatched to the billing address.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="master-card master-section">
                <div class="master-section-head">
                    <div>
                        <p class="master-eyebrow">Items</p>
                        <h2>Invoice Products</h2>
                        <p class="master-sub">One line per product. Rate is per unit; the line amount is what the
                            client is charged for it, GST included.</p>
                    </div><button type="button" class="master-btn master-btn-soft" id="addItemRow"><i
                            class="fas fa-plus" aria-hidden="true"></i> Add Product</button>
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
                                <th width="9%" class="is-num">GST %</th>
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
                <div>
                    <div class="master-card master-section">
                        <div class="master-section-head">
                            <div>
                                <p class="master-eyebrow">Terms</p>
                                <h2>Terms & Notes</h2>
                            </div>
                        </div>
                        <div class="master-form-grid">
                            <div class="master-field"><label class="master-label" for="paymentTerms">Payment
                                    Terms</label><input class="master-input" id="paymentTerms" name="payment_terms"
                                    value="{{ old('payment_terms', $invoice->payment_terms) }}"></div>
                            <div class="master-field"><label class="master-label" for="deliveryTerms">Delivery
                                    Terms</label><input class="master-input" id="deliveryTerms" name="delivery_terms"
                                    value="{{ old('delivery_terms', $invoice->delivery_terms) }}"></div>
                            <div class="master-field"><label class="master-label" for="dispatchTerms">Dispatch
                                    Terms</label><input class="master-input" id="dispatchTerms" name="dispatch_terms"
                                    value="{{ old('dispatch_terms', $invoice->dispatch_terms) }}"></div>
                            <div class="master-field"><label class="master-label" for="transportMode">Transport
                                    Mode</label><input class="master-input" id="transportMode" name="transport_mode"
                                    value="{{ old('transport_mode', $invoice->transport_mode) }}"></div>
                            <div class="master-field full"><label class="master-label" for="termsConditions">Fixed Terms
                                    & Conditions</label>
                                <textarea class="master-textarea" id="termsConditions" name="terms_conditions"
                                    rows="8">{{ old('terms_conditions', $invoice->terms_conditions ?: $defaultTerms) }}</textarea>
                            </div>
                            <div class="master-field full"><label class="master-label" for="notes">Notes</label>
                                <textarea class="master-textarea" id="notes" name="notes"
                                    rows="3">{{ old('notes', $invoice->notes) }}</textarea>
                                <p class="master-help">Printed on the invoice and visible to the client.</p>
                            </div>
                            <div class="master-field full"><label class="master-label" for="internalNotes">Internal
                                    Notes</label>
                                <textarea class="master-textarea" id="internalNotes" name="internal_notes"
                                    rows="3">{{ old('internal_notes', $invoice->internal_notes) }}</textarea>
                                <p class="master-help">Office only — never printed, never in the portal.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="master-card master-section">
                        <div class="master-section-head">
                            <div>
                                <p class="master-eyebrow">Totals</p>
                                <h2>Charges & Summary</h2>
                            </div>
                        </div>
                        <div class="master-form-grid">
                            <div class="master-field"><label class="master-label" for="discountType">Discount
                                    Type</label><select class="master-select" name="discount_type" id="discountType">
                                    <option value="amount"
                                        {{ old('discount_type', $invoice->discount_type) === 'amount' ? 'selected' : '' }}>
                                        Amount</option>
                                    <option value="percent"
                                        {{ old('discount_type', $invoice->discount_type) === 'percent' ? 'selected' : '' }}>
                                        Percent</option>
                                </select></div>
                            <div class="master-field"><label class="master-label" for="discountValue">Discount
                                    Value</label><input class="master-input" type="number" step="0.01" min="0"
                                    name="discount_value" id="discountValue"
                                    value="{{ old('discount_value', $invoice->discount_value) }}"></div>
                            <div class="master-field"><label class="master-label" for="freightAmount">Freight</label><input
                                    class="master-input" type="number" step="0.01" min="0" name="freight_amount"
                                    id="freightAmount" value="{{ old('freight_amount', $invoice->freight_amount) }}"></div>
                            <div class="master-field"><label class="master-label" for="packingAmount">Packing</label><input
                                    class="master-input" type="number" step="0.01" min="0" name="packing_amount"
                                    id="packingAmount" value="{{ old('packing_amount', $invoice->packing_amount) }}"></div>
                            <div class="master-field"><label class="master-label" for="otherCharges">Other
                                    Charges</label><input class="master-input" type="number" step="0.01" min="0"
                                    name="other_charges" id="otherCharges"
                                    value="{{ old('other_charges', $invoice->other_charges) }}"></div>
                            <div class="master-field"><label class="master-label" for="roundOff">Round Off</label><input
                                    class="master-input" type="number" step="0.01" name="round_off" id="roundOff"
                                    value="{{ old('round_off', $invoice->round_off) }}"></div>
                            {{-- The invoice's *opening* figure. Receipts recorded against
                                 it — from the list's "Record payment", or a cashflow
                                 entry linked to this invoice — are added to it, and
                                 the balance every screen prints is the two together
                                 (`SalesInvoice::receivedAmount()`). --}}
                            <div class="master-field full">
                                <label class="master-label" for="amountPaid">Opening received</label>
                                <input class="master-input" type="number" step="0.01" min="0" name="amount_paid"
                                    id="amountPaid" value="{{ old('amount_paid', $invoice->amount_paid) }}"
                                    aria-describedby="amountPaidHint">
                                <p class="master-help" id="amountPaidHint">Money already received before it was recorded
                                    in the ledger. Receipts filed against this invoice are added on top.</p>
                            </div>
                        </div>

                        {{-- The money, read top to bottom. Every figure here is the one
                             `syncItemsAndTotals()` will store when the form is saved:
                             the same steps, in the same order, and the rows that do
                             not apply stay out of the way. --}}
                        <div class="si-ledger" id="invoiceLedger">
                            <div class="si-total-row" id="rowSubtotal">
                                <span>Subtotal</span><strong id="previewSubtotal">₹ 0.00</strong>
                            </div>
                            <div class="si-total-row" id="rowLineDiscount" hidden>
                                <span>Line discounts</span><strong id="previewLineDiscount">₹ 0.00</strong>
                            </div>
                            <div class="si-total-row" id="rowInvoiceDiscount" hidden>
                                <span>Invoice discount</span><strong id="previewInvoiceDiscount">₹ 0.00</strong>
                            </div>
                            <div class="si-total-row" id="rowTaxable">
                                <span>Taxable value</span><strong id="previewTaxable">₹ 0.00</strong>
                            </div>
                            <div class="si-total-row" id="rowCgst" hidden>
                                <span>CGST</span><strong id="previewCgst">₹ 0.00</strong>
                            </div>
                            <div class="si-total-row" id="rowSgst" hidden>
                                <span>SGST</span><strong id="previewSgst">₹ 0.00</strong>
                            </div>
                            <div class="si-total-row" id="rowIgst" hidden>
                                <span>IGST</span><strong id="previewIgst">₹ 0.00</strong>
                            </div>
                            <div class="si-total-row" id="rowFreight" hidden>
                                <span>Freight</span><strong id="previewFreight">₹ 0.00</strong>
                            </div>
                            <div class="si-total-row" id="rowPacking" hidden>
                                <span>Packing</span><strong id="previewPacking">₹ 0.00</strong>
                            </div>
                            <div class="si-total-row" id="rowOther" hidden>
                                <span>Other charges</span><strong id="previewOther">₹ 0.00</strong>
                            </div>
                            <div class="si-total-row" id="rowRoundOff" hidden>
                                <span>Round off</span><strong id="previewRoundOff">₹ 0.00</strong>
                            </div>
                            <div class="si-total-row is-grand" id="rowTotal">
                                <span>Invoice total</span><strong id="previewTotal">₹ 0.00</strong>
                            </div>
                            <div class="si-total-row" id="rowReceived" hidden>
                                <span>Received so far</span><strong id="previewReceived">₹ 0.00</strong>
                            </div>
                            <div class="si-total-row" id="rowBalance">
                                <span>Balance due</span><strong id="previewBalance" class="si-due">₹ 0.00</strong>
                            </div>
                            <p class="master-help">Computed as you type, and computed again when you save — the saved
                                figures are the ones every screen prints.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="master-card master-section">
                <div class="master-section-head">
                    <div>
                        <p class="master-eyebrow">Files</p>
                        <h2>Attachments</h2>
                    </div>
                    @if ($isEdit && $invoice->attachments->isNotEmpty())
                        <div class="master-section-meta">
                            <span class="master-chip"><i class="fas fa-paperclip" aria-hidden="true"></i>{{ $invoice->attachments->count() }}
                                already on this invoice</span>
                            <a href="{{ route('sales-invoices.show', $invoice) }}" class="master-btn master-btn-light">Manage
                                them on the record page</a>
                        </div>
                    @endif
                </div>
                <div class="master-form-grid">
                    <div class="master-field full"><label class="master-label" for="invoiceAttachments">Add files</label>
                        <input class="master-input" type="file" id="invoiceAttachments" name="attachments[]" multiple>
                        <p class="master-help">PDF, image or sheet, up to 20 MB each. Files added here belong to the
                            invoice and can be shared on the client portal.</p>
                    </div>
                </div>
            </div>

            @foreach ($sellerDefaults as $field => $value)
                @if (!in_array($field, ['seller_company_name'], true))
                    <input type="hidden" name="{{ $field }}"
                        value="{{ old($field, $invoice->{$field} ?: $value) }}">
                @endif
            @endforeach
            <input type="hidden" name="seller_company_name"
                value="{{ old('seller_company_name', $invoice->seller_company_name ?: $sellerDefaults['seller_company_name']) }}">

            <div class="master-actions is-sticky"><a
                    href="{{ $isEdit ? route('sales-invoices.show', $invoice) : route('sales-invoices.index') }}"
                    class="master-btn master-btn-light-dark">Cancel</a><button class="master-btn master-btn-primary"
                    type="submit"><i class="fas fa-floppy-disk" aria-hidden="true"></i>
                    {{ $isEdit ? 'Update Invoice' : 'Create Invoice' }}</button></div>
        </form>
    </div>


@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/sales-invoices.css') }}">
@endpush

@push('scripts')
    <script src="{{ $assetVer('assets/js/sales-invoices.js') }}"></script>
@endpush
@endsection
