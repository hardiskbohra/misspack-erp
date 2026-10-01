<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $invoice->invoice_number }} - {{ $invoice->typeLabel() }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/sales-invoices-print.css') }}">
</head>

<body>
    <div class="toolbar">
        @if (!$publicMode)
        <a class="master-btn" href="{{ route('sales-invoices.show', $invoice) }}">Back</a>@else<span></span>
        @endif
        <button class="master-btn" onclick="window.print()">
            Print Invoice</button>
    </div>
    <div class="page">
        <div class="top">
            <div class="brand">
                <img class="brand-logo" src="{{ asset('images/logo-dark.png') }}" height="50"
                    alt="MissPack - Packed Perfect">
                <p style="font-size:16px;font-weight:600;">{{ 'MissPack India Pvt Ltd' }}</p>
                <p>{{ $invoice->seller_address }}<br>{{ $invoice->seller_city }}, {{ $invoice->seller_state }},
                    {{ $invoice->seller_country }} - {{ $invoice->seller_pincode }}</p>
                <p>GSTIN: {{ $invoice->seller_gstin ?: '-' }} | PAN: {{ $invoice->seller_pan ?: '-' }}<br>Email:
                    {{ $invoice->seller_email ?: '-' }} | Mobile: {{ $invoice->seller_mobile ?: '-' }}</p>
            </div>
            <div class="title">
                <h2 style="font-size:19px">{{ $invoice->typeLabel() }}</h2><strong>{{ $invoice->invoice_number }}</strong>
                <p>Status: {{ $invoice->statusLabel() }}</p>
            </div>
        </div>
        <div class="info-grid">
            <div><span>Invoice
                    Date</span><strong>{{ optional($invoice->invoice_date)->format('d M Y') ?: '-' }}</strong></div>
            <div><span>Valid</span><strong>{{ optional($invoice->valid_until)->format('d M Y') ?: '-' }}</strong></div>
            <div><span>PO Number</span><strong>{{ $invoice->po_number ?: '-' }}</strong></div>
            <div><span>Place of Supply</span><strong>{{ $invoice->place_of_supply ?: '-' }}</strong></div>
        </div>
        <div class="meta">
            <div class="box">
                <h3>Bill To</h3>
                <strong>{{ $invoice->client_company_name }}</strong><br>{{ $invoice->billing_address }}<br>{{ $invoice->billing_city }},
                {{ $invoice->billing_state }}, {{ $invoice->billing_country }} -
                {{ $invoice->billing_pincode }}<br>GSTIN: {{ $invoice->client_gstin ?: '-' }} | PAN:
                {{ $invoice->client_pan ?: '-' }}<br>Contact: {{ $invoice->client_contact_name ?: '-' }}
                {{ $invoice->client_mobile ?: '' }}
            </div>
            <div class="box">
                <h3>Ship To</h3>
                <strong>{{ $invoice->client_company_name }}</strong><br>
                {{ $invoice->shipping_address }}<br>{{ $invoice->shipping_city }},
                {{ $invoice->shipping_state }}, {{ $invoice->shipping_country }} -
                {{ $invoice->shipping_pincode }}<br>Email: {{ $invoice->client_email ?: '-' }}
            </div>
        </div>
        <table class="items">
            <thead>
                <tr>
                    <th width="3%">#</th>
                    <th width="47%">Description</th>
                    <th width="16%">Qty</th>
                    <th width="16%">Rate</th>
                    <th width="16%">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->items as $item)
                    <tr>
                        <td class="center">{{ $loop->iteration }}</td>
                        <td><strong>{{ $item->product_name }}</strong><br>{!! nl2br(e($item->description)) !!}@if ($item->remarks)
                                <br><em>{!! nl2br(e($item->remarks)) !!}</em>
                            @endif<br>
                            <em>HS Code: {{ $item->hsn_sac ?: '-' }}</em>
                        </td>
                        <td class="center">{{ number_format($item->quantity, 0) }} {{ $item->unit }}</td>
                        <td class="center">{{ \App\Helpers\CommonHelper::indianCurrency($item->unit_price) }}</td>
                        <td class="right">{{ \App\Helpers\CommonHelper::indianCurrency($item->taxable_amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="totals">
            <div>
                <!--<div class="terms"><strong>Terms & Conditions</strong><br>{{ $invoice->terms_conditions }}</div>-->
                <div class="amount-words">Amount in Words: <span style="color:grey">{{ $invoice->amount_in_words }}</span></div>
            </div>
            <div class="summary">
                <table>
                    <tr>
                        <td>Subtotal</td>
                        <td>{{ \App\Helpers\CommonHelper::indianCurrency($invoice->subtotal) }}</td>
                    </tr>
                    <tr>
                        <td>Discount</td>
                        <td>- {{ \App\Helpers\CommonHelper::indianCurrency((float) $invoice->discount_amount) }}</td>
                    </tr>
                    <tr>
                        <td>Taxable</td>
                        <td>{{ \App\Helpers\CommonHelper::indianCurrency((float) $invoice->taxable_amount) }}</td>
                    </tr>
                    <tr>
                        <td>Tax</td>
                        <td>{{ \App\Helpers\CommonHelper::indianCurrency((float) $invoice->cgst_amount + $invoice->sgst_amount + $invoice->igst_amount) }}</td>
                    </tr>
                    <tr>
                        <td>Freight/Packing/Other</td>
                        <td>{{ \App\Helpers\CommonHelper::indianCurrency((float) $invoice->freight_amount + (float) $invoice->packing_amount + (float) $invoice->other_charges) }}
                        </td>
                    </tr>
                    <tr>
                        <td>Round Off</td>
                        <td>{{ \App\Helpers\CommonHelper::indianCurrency($invoice->round_off) }}</td>
                    </tr>
                    <tr>
                        <td><strong>Grand Total</strong></td>
                        <td><strong>{{ \App\Helpers\CommonHelper::indianCurrency( $invoice->total_amount) }}</strong></td>
                    </tr>
                </table>
            </div>
        </div>
        <div class="bank-sign">
            <div class="box">
                <h3>Bank Details</h3>Bank: {{ $invoice->seller_bank_name ?: '-' }}<br>A/C Holder:
                {{ $invoice->seller_account_holder ?: '-' }}<br>A/C No.:
                {{ $invoice->seller_account_number ?: '-' }}<br>IFSC: {{ $invoice->seller_ifsc ?: '-' }}
                <br>Branch: {{ $invoice->seller_branch ?: '-' }}
            </div>
            <div class="sign">
                <strong>For {{ $invoice->seller_company_name ?: 'MissPack India Pvt Ltd' }}</strong>
                <span>Authorised Signatory</span>
            </div>
        </div>
        <div class="footer-note">This is a computer-generated invoice. Please verify all details before payment.</div>
    </div>
</body>

</html>
