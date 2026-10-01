@extends('layouts.app')

@section('page-title', $invoice->exists ? 'Edit Invoice' : 'Create Invoice')

@section('content')
    @php
        $isEdit = $invoice->exists;
        $invoiceItems = old('items', $invoice->relationLoaded('items') ? $invoice->items->toArray() : []);
        if (!$invoiceItems) {
            $invoiceItems = [
                [
                    'product_name' => '',
                    'quantity' => 1,
                    'unit' => 'pcs',
                    'unit_price' => 0,
                    'gst_percent' => 18,
                    'discount_percent' => 0,
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
    @endphp
    <div class="sif">
        <div class="master-hero" style="margin-bottom:15px;">
            <div>
                <p class="master-eyebrow">{{ $isEdit ? 'Update invoice' : 'Generate sales invoice' }}</p>
                <h1>{{ $isEdit ? 'Edit' : 'Create' }}
                    {{ $invoice->invoice_type === 'tax' ? 'Tax Invoice' : 'Proforma Invoice' }}</h1>
                <p>Client details are derived from the Client object. Add multiple products and print/share to client
                    portal.</p>
            </div><a href="{{ $isEdit ? route('sales-invoices.show', $invoice) : route('sales-invoices.index') }}"
                class="master-btn master-btn-light">Back</a>
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
            enctype="multipart/form-data" class="master-form">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <div class="master-card">
                <div class="master-section-head">
                    <div>
                        <p class="master-eyebrow">Basic</p>
                        <h2>Invoice Details</h2>
                    </div>
                </div>
                <div class="master-grid four">
                    <div class="master-field"><label class="master-label">Invoice Type</label><select class="master-select" name="invoice_type" id="invoiceType">
                            @foreach ($typeOptions as $key => $label)
                                <option value="{{ $key }}"
                                    {{ old('invoice_type', $invoice->invoice_type) === $key ? 'selected' : '' }}>
                                    {{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field"><label class="master-label">Invoice No.</label><input class="master-input" name="invoice_number"
                            value="{{ old('invoice_number', $invoice->invoice_number) }}" placeholder="Auto if blank"></div>
                    <div class="master-field"><label class="master-label">Status</label><select class="master-select" name="status">
                            @foreach ($statusOptions as $key => $label)
                                <option value="{{ $key }}"
                                    {{ old('status', $invoice->status) === $key ? 'selected' : '' }}>{{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field"><label class="master-label">Invoice Date</label><input class="master-input" type="date" name="invoice_date"
                            value="{{ old('invoice_date', optional($invoice->invoice_date)->format('Y-m-d')) }}"></div>
                    <div class="master-field"><label class="master-label">Due Date</label><input class="master-input" type="date" name="due_date"
                            value="{{ old('due_date', optional($invoice->due_date)->format('Y-m-d')) }}"></div>
                    <div class="master-field"><label class="master-label">Valid Until</label><input class="master-input" type="date" name="valid_until"
                            value="{{ old('valid_until', optional($invoice->valid_until)->format('Y-m-d')) }}"></div>
                    <div class="master-field"><label class="master-label">Currency</label><select class="master-select" name="currency">
                            @foreach ($currencyOptions as $key => $label)
                                <option value="{{ $key }}"
                                    {{ old('currency', $invoice->currency) === $key ? 'selected' : '' }}>
                                    {{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field"><label class="master-label">GST Type</label><select class="master-select" name="gst_type" id="gstType">
                            @foreach ($gstTypeOptions as $key => $label)
                                <option value="{{ $key }}"
                                    {{ old('gst_type', $invoice->gst_type) === $key ? 'selected' : '' }}>
                                    {{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field"><label class="master-label">PO Number</label><input class="master-input" name="po_number"
                            value="{{ old('po_number', $invoice->po_number) }}"></div>
                    <div class="master-field"><label class="master-label">PO Date</label><input class="master-input" type="date" name="po_date"
                            value="{{ old('po_date', optional($invoice->po_date)->format('Y-m-d')) }}"></div>
                    <div class="master-field"><label class="master-label">Place of Supply</label><input class="master-input" name="place_of_supply"
                            value="{{ old('place_of_supply', $invoice->place_of_supply) }}"></div>
                    <label class="master-check"><input type="checkbox" name="show_client_portal" value="1"
                            {{ old('show_client_portal', $invoice->show_client_portal) ? 'checked' : '' }}> Visible in
                        Client Portal</label>
                </div>
            </div>

            <div class="master-card">
                <div class="master-section-head">
                    <div>
                        <p class="master-eyebrow">Client Mapping</p>
                        <h2>Client / Project / Quote</h2>
                    </div>
                </div>
                <div class="master-grid four">
                    <div class="master-field"><label class="master-label">Client</label><select class="master-select" name="client_id" id="clientSelect">
                            <option value="">Select Client</option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}" data-company="{{ $client->company_name }}"
                                    data-brand="{{ $client->brand_name }}"
                                    data-contact="{{ $client->account_person_name ?: $client->ceo_name }}"
                                    data-email="{{ $client->account_person_email ?: $client->ceo_email }}"
                                    data-mobile="{{ $client->account_person_contact ?: $client->ceo_contact }}"
                                    data-gstin="{{ $client->gstin }}" data-pan="{{ $client->pan }}"
                                    data-billing-address="{{ $client->billing_address }}"
                                    data-billing-city="{{ $client->billing_city }}"
                                    data-billing-state="{{ $client->billing_state }}"
                                    data-billing-country="{{ $client->billing_country }}"
                                    data-billing-pincode="{{ $client->billing_pincode }}"
                                    data-shipping-address="{{ $client->shipping_address ?: $client->billing_address }}"
                                    data-shipping-city="{{ $client->shipping_city ?: $client->billing_city }}"
                                    data-shipping-state="{{ $client->shipping_state ?: $client->billing_state }}"
                                    data-shipping-country="{{ $client->shipping_country ?: $client->billing_country }}"
                                    data-shipping-pincode="{{ $client->shipping_pincode ?: $client->billing_pincode }}"
                                    {{ (string) old('client_id', $invoice->client_id) === (string) $client->id ? 'selected' : '' }}>
                                    {{ $client->company_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field"><label class="master-label">Project</label><select class="master-select" name="project_id">
                            <option value="">No Project</option>
                            @foreach ($projects as $project)
                                <option value="{{ $project->id }}"
                                    {{ (string) old('project_id', $invoice->project_id) === (string) $project->id ? 'selected' : '' }}>
                                    {{ $project->project_number }} - {{ $project->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field">
                        <label class="master-label" hidden>Customer Quote</label>
                        <select class="master-select" name="customer_quote_id" hidden>
                            <option value="">No Quote</option>
                            @foreach ($quotes as $quote)
                                <option value="{{ $quote->id }}"
                                    {{ (string) old('customer_quote_id', $invoice->customer_quote_id) === (string) $quote->id ? 'selected' : '' }}>
                                    {{ $quote->quote_number }} - {{ $quote->title }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="master-grid four" style="margin-top:14px;">
                    <div class="master-field"><label class="master-label">Client Company</label><input class="master-input" name="client_company_name"
                            id="client_company_name"
                            value="{{ old('client_company_name', $invoice->client_company_name) }}"></div>
                    <div class="master-field"><label class="master-label">Contact</label><input class="master-input" name="client_contact_name" id="client_contact_name"
                            value="{{ old('client_contact_name', $invoice->client_contact_name) }}"></div>
                    <div class="master-field"><label class="master-label">Email</label><input class="master-input" name="client_email" id="client_email"
                            value="{{ old('client_email', $invoice->client_email) }}"></div>
                    <div class="master-field"><label class="master-label">Mobile</label><input class="master-input" name="client_mobile" id="client_mobile"
                            value="{{ old('client_mobile', $invoice->client_mobile) }}"></div>
                    <div class="master-field"><label class="master-label">GSTIN</label><input class="master-input" name="client_gstin" id="client_gstin"
                            value="{{ old('client_gstin', $invoice->client_gstin) }}"></div>
                    <div class="master-field"><label class="master-label">PAN</label><input class="master-input" name="client_pan" id="client_pan"
                            value="{{ old('client_pan', $invoice->client_pan) }}"></div><br>
                    <div class="master-field two"><label class="master-label">Billing Address</label>
                        <textarea class="master-textarea" name="billing_address" id="billing_address">{{ old('billing_address', $invoice->billing_address) }}</textarea>
                    </div>
                    <div class="master-field two"><label class="master-label">Shipping Address</label>
                        <textarea class="master-textarea" name="shipping_address" id="shipping_address">{{ old('shipping_address', $invoice->shipping_address) }}</textarea>
                    </div>
                </div>
            </div>

            <div class="master-card">
                <div class="master-section-head">
                    <div>
                        <p class="master-eyebrow">Items</p>
                        <h2>Invoice Products</h2>
                    </div><button type="button" class="master-btn master-btn-soft" id="addItemRow">+ Add Product</button>
                </div>
                <div class="master-items-wrap">
                    <table class="master-items-table">
                        <thead>
                            <tr>
                                <th width="30%">Product</th>
                                <th width="10%">HSN</th>
                                <th width="10%">Qty</th>
                                <th width="10%">Unit</th>
                                <th width="15%">Rate</th>
                                <th width="10%">GST %</th>
                                <th width="15%">Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="invoiceItemsBody"
                                data-product-options='@json($productOptionsJson)'
                                data-initial-items='@json($initialInvoiceItems)'></tbody>
                    </table>
                </div>
            </div>

            <div class="master-grid two">
                <div class="master-card">
                    <div class="master-section-head">
                        <div>
                            <p class="master-eyebrow">Terms</p>
                            <h2>Terms & Notes</h2>
                        </div>
                    </div>
                    <div class="master-grid two">
                        <div class="master-field"><label class="master-label">Payment Terms</label><input class="master-input" name="payment_terms"
                                value="{{ old('payment_terms', $invoice->payment_terms) }}"></div>
                        <div class="master-field"><label class="master-label">Delivery Terms</label><input class="master-input" name="delivery_terms"
                                value="{{ old('delivery_terms', $invoice->delivery_terms) }}"></div>
                        <div class="master-field full"><label class="master-label">Fixed Terms & Conditions</label>
                            <textarea class="master-textarea" name="terms_conditions" rows="8">{{ old('terms_conditions', $invoice->terms_conditions ?: $defaultTerms) }}</textarea>
                        </div>
                        <div class="master-field full"><label class="master-label">Notes</label>
                            <textarea class="master-textarea" name="notes" rows="3">{{ old('notes', $invoice->notes) }}</textarea>
                        </div>
                        <div class="master-field full"><label class="master-label">Internal Notes</label>
                            <textarea class="master-textarea" name="internal_notes" rows="3">{{ old('internal_notes', $invoice->internal_notes) }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="master-card">
                    <div class="master-section-head">
                        <div>
                            <p class="master-eyebrow">Totals</p>
                            <h2>Charges & Summary</h2>
                        </div>
                    </div>
                    <div class="master-grid two">
                        <div class="master-field"><label class="master-label">Invoice Discount Type</label><select class="master-select" name="discount_type"
                                id="discountType">
                                <option value="amount"
                                    {{ old('discount_type', $invoice->discount_type) === 'amount' ? 'selected' : '' }}>
                                    Amount</option>
                                <option value="percent"
                                    {{ old('discount_type', $invoice->discount_type) === 'percent' ? 'selected' : '' }}>
                                    Percent</option>
                            </select></div>
                        <div class="master-field"><label class="master-label">Discount Value</label><input class="master-input" type="number" step="0.01"
                                name="discount_value" id="discountValue"
                                value="{{ old('discount_value', $invoice->discount_value) }}"></div>
                        <div class="master-field"><label class="master-label">Freight</label><input class="master-input" type="number" step="0.01"
                                name="freight_amount" id="freightAmount"
                                value="{{ old('freight_amount', $invoice->freight_amount) }}"></div>
                        <div class="master-field"><label class="master-label">Packing</label><input class="master-input" type="number" step="0.01"
                                name="packing_amount" id="packingAmount"
                                value="{{ old('packing_amount', $invoice->packing_amount) }}"></div>
                        <div class="master-field"><label class="master-label">Other Charges</label><input class="master-input" type="number" step="0.01"
                                name="other_charges" id="otherCharges"
                                value="{{ old('other_charges', $invoice->other_charges) }}"></div>
                        <div class="master-field"><label class="master-label">Round Off</label><input class="master-input" type="number" step="0.01"
                                name="round_off" id="roundOff" value="{{ old('round_off', $invoice->round_off) }}">
                        </div>
                        <div class="master-field"><label class="master-label">Amount Paid</label><input class="master-input" type="number" step="0.01"
                                name="amount_paid" id="amountPaid"
                                value="{{ old('amount_paid', $invoice->amount_paid) }}"></div>
                        <div class="master-field"><label class="master-label">Attachments</label><input class="master-input" type="file" name="attachments[]"
                                multiple></div>
                    </div>
                    <div class="master-total-box">
                        <div><span>Subtotal</span><strong id="previewSubtotal">₹ 0.00</strong></div>
                        <div><span>Tax</span><strong id="previewTax">₹ 0.00</strong></div>
                        <div><span>Total</span><strong id="previewTotal">₹ 0.00</strong></div>
                        <div><span>Balance</span><strong id="previewBalance">₹ 0.00</strong></div>
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

            <div class="master-submit"><a
                    href="{{ $isEdit ? route('sales-invoices.show', $invoice) : route('sales-invoices.index') }}"
                    class="master-btn master-btn-light-dark">Cancel</a><button class="master-btn master-btn-primary"
                    type="submit">{{ $isEdit ? 'Update Invoice' : 'Create Invoice' }}</button></div>
        </form>
    </div>


@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/sales-invoices.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/js/sales-invoices.js') }}"></script>
@endpush
@endsection
