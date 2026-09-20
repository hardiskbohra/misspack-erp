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
                    <div class="master-field"><label>Invoice Type</label><select name="invoice_type" id="invoiceType">
                            @foreach ($typeOptions as $key => $label)
                                <option value="{{ $key }}"
                                    {{ old('invoice_type', $invoice->invoice_type) === $key ? 'selected' : '' }}>
                                    {{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field"><label>Invoice No.</label><input name="invoice_number"
                            value="{{ old('invoice_number', $invoice->invoice_number) }}" placeholder="Auto if blank"></div>
                    <div class="master-field"><label>Status</label><select name="status">
                            @foreach ($statusOptions as $key => $label)
                                <option value="{{ $key }}"
                                    {{ old('status', $invoice->status) === $key ? 'selected' : '' }}>{{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field"><label>Invoice Date</label><input type="date" name="invoice_date"
                            value="{{ old('invoice_date', optional($invoice->invoice_date)->format('Y-m-d')) }}"></div>
                    <div class="master-field"><label>Due Date</label><input type="date" name="due_date"
                            value="{{ old('due_date', optional($invoice->due_date)->format('Y-m-d')) }}"></div>
                    <div class="master-field"><label>Valid Until</label><input type="date" name="valid_until"
                            value="{{ old('valid_until', optional($invoice->valid_until)->format('Y-m-d')) }}"></div>
                    <div class="master-field"><label>Currency</label><select name="currency">
                            @foreach ($currencyOptions as $key => $label)
                                <option value="{{ $key }}"
                                    {{ old('currency', $invoice->currency) === $key ? 'selected' : '' }}>
                                    {{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field"><label>GST Type</label><select name="gst_type" id="gstType">
                            @foreach ($gstTypeOptions as $key => $label)
                                <option value="{{ $key }}"
                                    {{ old('gst_type', $invoice->gst_type) === $key ? 'selected' : '' }}>
                                    {{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field"><label>PO Number</label><input name="po_number"
                            value="{{ old('po_number', $invoice->po_number) }}"></div>
                    <div class="master-field"><label>PO Date</label><input type="date" name="po_date"
                            value="{{ old('po_date', optional($invoice->po_date)->format('Y-m-d')) }}"></div>
                    <div class="master-field"><label>Place of Supply</label><input name="place_of_supply"
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
                    <div class="master-field"><label>Client</label><select name="client_id" id="clientSelect">
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
                    <div class="master-field"><label>Project</label><select name="project_id">
                            <option value="">No Project</option>
                            @foreach ($projects as $project)
                                <option value="{{ $project->id }}"
                                    {{ (string) old('project_id', $invoice->project_id) === (string) $project->id ? 'selected' : '' }}>
                                    {{ $project->project_number }} - {{ $project->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field">
                        <label hidden>Customer Quote</label>
                        <select name="customer_quote_id" hidden>
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
                    <div class="master-field"><label>Client Company</label><input name="client_company_name"
                            id="client_company_name"
                            value="{{ old('client_company_name', $invoice->client_company_name) }}"></div>
                    <div class="master-field"><label>Contact</label><input name="client_contact_name" id="client_contact_name"
                            value="{{ old('client_contact_name', $invoice->client_contact_name) }}"></div>
                    <div class="master-field"><label>Email</label><input name="client_email" id="client_email"
                            value="{{ old('client_email', $invoice->client_email) }}"></div>
                    <div class="master-field"><label>Mobile</label><input name="client_mobile" id="client_mobile"
                            value="{{ old('client_mobile', $invoice->client_mobile) }}"></div>
                    <div class="master-field"><label>GSTIN</label><input name="client_gstin" id="client_gstin"
                            value="{{ old('client_gstin', $invoice->client_gstin) }}"></div>
                    <div class="master-field"><label>PAN</label><input name="client_pan" id="client_pan"
                            value="{{ old('client_pan', $invoice->client_pan) }}"></div><br>
                    <div class="master-field two"><label>Billing Address</label>
                        <textarea name="billing_address" id="billing_address">{{ old('billing_address', $invoice->billing_address) }}</textarea>
                    </div>
                    <div class="master-field two"><label>Shipping Address</label>
                        <textarea name="shipping_address" id="shipping_address">{{ old('shipping_address', $invoice->shipping_address) }}</textarea>
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
                        <tbody id="invoiceItemsBody"></tbody>
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
                        <div class="master-field"><label>Payment Terms</label><input name="payment_terms"
                                value="{{ old('payment_terms', $invoice->payment_terms) }}"></div>
                        <div class="master-field"><label>Delivery Terms</label><input name="delivery_terms"
                                value="{{ old('delivery_terms', $invoice->delivery_terms) }}"></div>
                        <div class="master-field full"><label>Fixed Terms & Conditions</label>
                            <textarea name="terms_conditions" rows="8">{{ old('terms_conditions', $invoice->terms_conditions ?: $defaultTerms) }}</textarea>
                        </div>
                        <div class="master-field full"><label>Notes</label>
                            <textarea name="notes" rows="3">{{ old('notes', $invoice->notes) }}</textarea>
                        </div>
                        <div class="master-field full"><label>Internal Notes</label>
                            <textarea name="internal_notes" rows="3">{{ old('internal_notes', $invoice->internal_notes) }}</textarea>
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
                        <div class="master-field"><label>Invoice Discount Type</label><select name="discount_type"
                                id="discountType">
                                <option value="amount"
                                    {{ old('discount_type', $invoice->discount_type) === 'amount' ? 'selected' : '' }}>
                                    Amount</option>
                                <option value="percent"
                                    {{ old('discount_type', $invoice->discount_type) === 'percent' ? 'selected' : '' }}>
                                    Percent</option>
                            </select></div>
                        <div class="master-field"><label>Discount Value</label><input type="number" step="0.01"
                                name="discount_value" id="discountValue"
                                value="{{ old('discount_value', $invoice->discount_value) }}"></div>
                        <div class="master-field"><label>Freight</label><input type="number" step="0.01"
                                name="freight_amount" id="freightAmount"
                                value="{{ old('freight_amount', $invoice->freight_amount) }}"></div>
                        <div class="master-field"><label>Packing</label><input type="number" step="0.01"
                                name="packing_amount" id="packingAmount"
                                value="{{ old('packing_amount', $invoice->packing_amount) }}"></div>
                        <div class="master-field"><label>Other Charges</label><input type="number" step="0.01"
                                name="other_charges" id="otherCharges"
                                value="{{ old('other_charges', $invoice->other_charges) }}"></div>
                        <div class="master-field"><label>Round Off</label><input type="number" step="0.01"
                                name="round_off" id="roundOff" value="{{ old('round_off', $invoice->round_off) }}">
                        </div>
                        <div class="master-field"><label>Amount Paid</label><input type="number" step="0.01"
                                name="amount_paid" id="amountPaid"
                                value="{{ old('amount_paid', $invoice->amount_paid) }}"></div>
                        <div class="master-field"><label>Attachments</label><input type="file" name="attachments[]"
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

    <style>
        .master-page {
            display: flex;
            flex-direction: column;
            gap: 18px;
            background: #eef3ff;
            min-height: calc(100vh - 70px);
            padding: 28px;
            color: #17233b
        }

        .master-page * {
            box-sizing: border-box
        }

        .master-hero {
            background: linear-gradient(135deg, #4f83f1, #7b61ff);
            border-radius: 26px;
            padding: 24px;
            color: #fff;
            display: flex;
            justify-content: space-between;
            gap: 18px;
            box-shadow: 0 18px 45px rgba(79, 131, 241, .22)
        }

        .master-eyebrow {
            margin: 0 0 6px;
            text-transform: uppercase;
            letter-spacing: .13em;
            font-size: 11px;
            font-weight: 600;
            opacity: .78
        }

        .master-hero h1 {
            margin: 0;
            font-size: 30px;
            font-weight: 600
        }

        .master-hero p {
            margin: 8px 0 0;
            max-width: 780px;
            opacity: .9
        }

        .master-btn {
            border: 0;
            border-radius: 14px;
            padding: 10px 15px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer
        }

        .master-btn-primary {
            background: #ef4770;
            color: #fff;
            box-shadow: 0 10px 24px rgba(239, 71, 112, .24)
        }

        .master-btn-light {
            background: rgba(255, 255, 255, .16);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, .3)
        }

        .master-btn-soft {
            background: #eef3ff;
            color: #4f83f1
        }

        .master-btn-light-dark {
            background: #f3f6fb;
            color: #17233b
        }

        .master-alert {
            border-radius: 16px;
            padding: 13px 15px;
            font-weight: 600
        }

        .master-alert.error {
            background: #fff0f4;
            color: #be123c;
            border: 1px solid #fecdd3
        }

        .master-form {
            display: flex;
            flex-direction: column;
            gap: 18px
        }

        .master-card {
            background: #fff;
            border: 1px solid #dfe7f3;
            border-radius: 22px;
            padding: 20px;
            box-shadow: 0 14px 35px rgba(25, 42, 70, .08)
        }

        .master-section-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 14px
        }

        .master-section-head h2 {
            margin: 0
        }

        .master-grid {
            display: grid;
            gap: 12px
        }

        .master-grid.two {
            grid-template-columns: repeat(2, minmax(0, 1fr))
        }

        .master-grid.three {
            grid-template-columns: repeat(3, minmax(0, 1fr))
        }

        .master-grid.four {
            grid-template-columns: repeat(4, minmax(0, 1fr))
        }

        .master-field {
            display: flex;
            flex-direction: column;
            gap: 7px
        }

        .master-field.full {
            grid-column: 1/-1
        }

        .master-field label {
            font-size: 12px;
            color: #536079;
            font-weight: 600
        }

        .master-field input,
        .master-field select,
        .master-field textarea {
            width: 100%;
            border: 1px solid #d8e2ef;
            border-radius: 13px;
            padding: 10px 12px;
            background: #fff;
            color: #17233b;
            outline: none
        }

        .master-field input,
        .master-field select {
            height: 42px
        }

        .master-field textarea {
            resize: vertical;
            min-height: 72px
        }

        .master-check {
            display: flex;
            gap: 8px;
            align-items: center;
            font-weight: 600;
            color: #536079;
            margin-top: 25px
        }

        .master-items-wrap {
            overflow: auto
        }

        .master-items-table {
            width: 100%;
            min-width: 1050px;
            border-collapse: collapse
        }

        .master-items-table th,
        .master-items-table td {
            padding: 9px;
            border-bottom: 1px solid #dfe7f3;
            text-align: left
        }

        .master-items-table th {
            font-size: 11px;
            text-transform: uppercase;
            color: #7d8aa0;
            background: #fbfdff
        }

        .master-items-table input,
        .master-items-table textarea,
        .master-items-table select {
            width: 100%;
            border: 1px solid #d8e2ef;
            border-radius: 10px;
            padding: 8px
        }

        .master-items-table textarea {
            min-height: 38px
        }

        .master-remove {
            border: 0;
            border-radius: 10px;
            background: #fff0f4;
            color: #e11d48;
            padding: 8px 10px;
            font-weight: 600
        }

        .master-total-box {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-top: 14px
        }

        .master-total-box div {
            background: #f8fbff;
            border: 1px solid #edf2ff;
            border-radius: 14px;
            padding: 12px
        }

        .master-total-box span {
            display: block;
            color: #687386;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase
        }

        .master-total-box strong {
            display: block;
            margin-top: 5px;
            font-size: 18px
        }

        .master-submit {
            display: flex;
            justify-content: flex-end;
            gap: 10px
        }

        .master-product-select {
            min-width: 180px
        }

        @media(max-width:1200px) {

            .master-grid.four,
            .master-grid.three {
                grid-template-columns: repeat(2, minmax(0, 1fr))
            }
        }

        @media(max-width:767px) {
            .master-page {
                padding: 14px
            }

            .master-hero,
            .master-section-head,
            .master-submit {
                flex-direction: column
            }

            .master-grid.two,
            .master-grid.three,
            .master-grid.four,
            .master-total-box {
                grid-template-columns: 1fr
            }

            .master-btn,
            .master-submit .master-btn {
                width: 100%
            }

            .master-card {
                padding: 15px
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const productOptions = @json($productOptionsJson);
            const initialItems = @json($initialInvoiceItems);
            let rowIndex = 0;
            const body = document.getElementById('invoiceItemsBody');

            function money(v) {
                return '₹ ' + (Number(v || 0)).toLocaleString('en-IN', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            function productSelect(name, selected) {
                let html = `<select class="master-product-select" name="${name}"><option value="">Manual</option>`;
                productOptions.forEach(p => html +=
                    `<option value="${p.id}" ${String(selected||'')===String(p.id)?'selected':''}>${p.no} : ${p.name}</option>`
                    );
                return html + '</select>';
            }

            function addRow(item = {}) {
                const i = rowIndex++;
                body.insertAdjacentHTML('beforeend', `<tr>
            <td>
                <div>
                ${productSelect(`items[${i}][product_id]`, item.product_id || '')}
                <input name="items[${i}][project_product_id]" type="hidden" value="${item.project_product_id || ''}"></div><br>
                <div><input name="items[${i}][product_name]" value="${item.product_name || ''}" placeholder="Product name"></div><br>
                <textarea rows="3" name="items[${i}][description]" placeholder="Description">${item.description || ''}</textarea>
            </td>
            <td><input name="items[${i}][hsn_sac]" value="${item.hsn_sac || ''}" placeholder="HSN"></td>
            <td><input class="calc" type="number" step="1" min="0" name="items[${i}][quantity]" value="${item.quantity || 1}"></td>
            <td><input name="items[${i}][unit]" value="${item.unit || 'pcs'}"></td>
            <td><input class="calc" type="number" step="0.05" min="0" name="items[${i}][unit_price]" value="${item.unit_price || 0}"></td>
            <td><input class="calc" type="number" step="1" min="0" name="items[${i}][gst_percent]" value="${item.gst_percent || 18}"></td>
            <td><strong class="line-total">₹ 0.00</strong><input name="items[${i}][remarks]" placeholder="Remarks" style="margin-top:5px;" hidden></td>
            <td><button type="button" class="master-remove">×</button></td>
        </tr>`);
                calculateTotals();
            }
            initialItems.forEach(item => addRow(item));
            document.getElementById('addItemRow').addEventListener('click', () => addRow({}));
            body.addEventListener('input', e => {
                if (e.target.classList.contains('calc')) calculateTotals();
            });
            body.addEventListener('change', e => {
                if (e.target.classList.contains('master-product-select')) {
                    const product = productOptions.find(p => String(p.id) === String(e.target.value));
                    const row = e.target.closest('tr');
                    if (product && row) {
                        const nameInput = row.querySelector('input[name$="[product_name]"]');
                        const descInput = row.querySelector('textarea[name$="[description]"]');
                        if (nameInput && !nameInput.value) nameInput.value = product.name || '';
                        if (descInput && !descInput.value) descInput.value = product.description || '';
                    }
                }
            });
            body.addEventListener('click', e => {
                if (e.target.classList.contains('master-remove')) {
                    e.target.closest('tr').remove();
                    calculateTotals();
                }
            });

            ['discountValue', 'discountType', 'freightAmount', 'packingAmount', 'otherCharges', 'roundOff',
                'amountPaid', 'gstType'
            ].forEach(id => document.getElementById(id)?.addEventListener('input', calculateTotals));
            ['discountType', 'gstType'].forEach(id => document.getElementById(id)?.addEventListener('change',
                calculateTotals));

            function calculateTotals() {
                let subtotal = 0;
                let taxable = 0;
                let tax = 0;

                body.querySelectorAll('tr').forEach(row => {

                    const qty = parseFloat(
                        row.querySelector('input[name$="[quantity]"]')?.value
                    ) || 0;

                    const rate = parseFloat(
                        row.querySelector('input[name$="[unit_price]"]')?.value
                    ) || 0;

                    const gst = parseFloat(
                        row.querySelector('input[name$="[gst_percent]"]')?.value
                    ) || 0;

                    const gross = qty * rate;

                    const taxableLine = gross;

                    const taxLine =
                        document.getElementById('gstType')?.value === 'export' ?
                        0 :
                        taxableLine * gst / 100;

                    const lineTotal = taxableLine + taxLine;

                    subtotal += gross;
                    taxable += taxableLine;
                    tax += taxLine;

                    const lineTotalElement = row.querySelector('.line-total');

                    if (lineTotalElement) {
                        lineTotalElement.textContent = money(lineTotal);
                    }
                });

                const discountValue =
                    parseFloat(document.getElementById('discountValue')?.value) || 0;

                const discountType =
                    document.getElementById('discountType')?.value || 'amount';

                const invoiceDiscount =
                    discountType === 'percent' ?
                    taxable * discountValue / 100 :
                    discountValue;

                const freight =
                    parseFloat(document.getElementById('freightAmount')?.value) || 0;

                const packing =
                    parseFloat(document.getElementById('packingAmount')?.value) || 0;

                const other =
                    parseFloat(document.getElementById('otherCharges')?.value) || 0;

                const roundOff =
                    parseFloat(document.getElementById('roundOff')?.value) || 0;

                const paid =
                    parseFloat(document.getElementById('amountPaid')?.value) || 0;

                const total =
                    taxable -
                    invoiceDiscount +
                    tax +
                    freight +
                    packing +
                    other +
                    roundOff;

                const balance = total - paid;

                document.getElementById('previewSubtotal').textContent = money(taxable);
                document.getElementById('previewTax').textContent = money(tax);
                document.getElementById('previewTotal').textContent = money(total);
                document.getElementById('previewBalance').textContent = money(balance);
            }

            document.getElementById('clientSelect')?.addEventListener('change', function() {
                const o = this.options[this.selectedIndex];
                if (!o) return;
                const map = ['company_name', 'contact_name', 'email', 'mobile', 'gstin', 'pan',
                    'billing_address', 'shipping_address'
                ];
                document.getElementById('client_company_name').value = o.dataset.company || '';
                document.getElementById('client_contact_name').value = o.dataset.contact || '';
                document.getElementById('client_email').value = o.dataset.email || '';
                document.getElementById('client_mobile').value = o.dataset.mobile || '';
                document.getElementById('client_gstin').value = o.dataset.gstin || '';
                document.getElementById('client_pan').value = o.dataset.pan || '';
                document.getElementById('billing_address').value = [o.dataset.billingAddress, o.dataset
                    .billingCity, o.dataset.billingState, o.dataset.billingCountry, o.dataset
                    .billingPincode
                ].filter(Boolean).join(', ');
                document.getElementById('shipping_address').value = [o.dataset.shippingAddress, o.dataset
                    .shippingCity, o.dataset.shippingState, o.dataset.shippingCountry, o.dataset
                    .shippingPincode
                ].filter(Boolean).join(', ');
            });
            calculateTotals();
        });
    </script>
@endsection
