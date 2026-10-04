<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <title>{{ $invoice->invoice_number }} - {{ $invoice->typeLabel() }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/sales-invoices-print.css') }}">
</head>

@php
    $currency = (string) ($invoice->currency ?: 'INR');
    $money = fn ($amount) => \App\Helpers\CommonHelper::amount((float) $amount, $currency);
    $lineDiscountTotal = (float) $invoice->items->sum('discount_amount');
    $received = $invoice->receivedAmount();
    $balanceDue = $invoice->balanceDue();
    $secondaryDate = $invoice->invoice_type === 'tax'
        ? ($invoice->due_date ?: $invoice->valid_until)
        : $invoice->valid_until;
    $secondaryDateLabel = $invoice->invoice_type === 'tax' && $invoice->due_date
        ? 'Due date'
        : 'Valid until';
    $billingLocation = collect([$invoice->billing_city, $invoice->billing_state, $invoice->billing_country])
        ->filter()->implode(', ');
    $shippingLocation = collect([$invoice->shipping_city, $invoice->shipping_state, $invoice->shipping_country])
        ->filter()->implode(', ');
    $sellerLocation = collect([$invoice->seller_city, $invoice->seller_state, $invoice->seller_country])
        ->filter()->implode(', ');
@endphp

<body class="invoice-standalone">
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
                <img class="brand-logo" src="{{ asset('images/logo-dark.png') }}"
                    alt="MissPack — Packed Perfect">
                <p class="brand-company">{{ $invoice->seller_company_name ?: 'MissPack India Pvt Ltd' }}</p>
                @if ($invoice->seller_address)
                    <p class="brand-detail">{{ $invoice->seller_address }}</p>
                @endif
                @if ($sellerLocation || $invoice->seller_pincode)
                    <p class="brand-detail">
                        {{ collect([$sellerLocation, $invoice->seller_pincode])->filter()->implode(' · ') }}
                    </p>
                @endif
                @if ($invoice->seller_gstin || $invoice->seller_pan)
                    <p class="brand-detail">
                        @if ($invoice->seller_gstin) GSTIN {{ $invoice->seller_gstin }} @endif
                        @if ($invoice->seller_pan) · PAN {{ $invoice->seller_pan }} @endif
                    </p>
                @endif
                @if ($invoice->seller_email || $invoice->seller_mobile || $invoice->seller_website)
                    <p class="brand-detail">
                        {{ collect([$invoice->seller_email, $invoice->seller_mobile, $invoice->seller_website])->filter()->implode(' · ') }}
                    </p>
                @endif
            </div>
            <div class="title">
                <h2>{{ $invoice->typeLabel() }}</h2>
                <strong>{{ $invoice->invoice_number }}</strong>
                <p>Status: {{ $invoice->statusLabel() }}</p>
            </div>
        </div>
        <div class="info-grid">
            <div>
                <span>Invoice date</span>
                <strong>{{ optional($invoice->invoice_date)->format('d M Y') ?: '—' }}</strong>
            </div>
            <div>
                <span>{{ $secondaryDateLabel }}</span>
                <strong>{{ optional($secondaryDate)->format('d M Y') ?: '—' }}</strong>
            </div>
            <div>
                <span>Purchase order</span>
                <strong>{{ $invoice->po_number ?: '—' }}</strong>
                @if ($invoice->po_date)
                    <small>PO dated {{ $invoice->po_date->format('d M Y') }}</small>
                @endif
            </div>
            <div>
                <span>Place of supply</span>
                <strong>{{ $invoice->place_of_supply ?: '—' }}</strong>
            </div>
        </div>
        <div class="meta">
            <div class="box">
                <h3>Bill to</h3>
                <strong>{{ $invoice->client_company_name ?: '—' }}</strong>
                @if ($invoice->billing_address)
                    <br>{{ $invoice->billing_address }}
                @endif
                @if ($billingLocation || $invoice->billing_pincode)
                    <br>{{ collect([$billingLocation, $invoice->billing_pincode])->filter()->implode(' · ') }}
                @endif
                @if ($invoice->client_gstin || $invoice->client_pan)
                    <p class="box-detail">
                        @if ($invoice->client_gstin) GSTIN {{ $invoice->client_gstin }} @endif
                        @if ($invoice->client_pan) · PAN {{ $invoice->client_pan }} @endif
                    </p>
                @endif
                @if ($invoice->client_contact_name || $invoice->client_mobile || $invoice->client_email)
                    <p class="box-detail">
                        {{ collect([$invoice->client_contact_name, $invoice->client_mobile, $invoice->client_email])->filter()->implode(' · ') }}
                    </p>
                @endif
            </div>
            <div class="box">
                <h3>Ship to</h3>
                <strong>{{ $invoice->client_company_name ?: '—' }}</strong>
                @if ($invoice->shipping_address)
                    <br>{{ $invoice->shipping_address }}
                @endif
                @if ($shippingLocation || $invoice->shipping_pincode)
                    <br>{{ collect([$shippingLocation, $invoice->shipping_pincode])->filter()->implode(' · ') }}
                @endif
                @if ($invoice->client_email)
                    <p class="box-detail">{{ $invoice->client_email }}</p>
                @endif
            </div>
        </div>
        <table class="items">
            <thead>
                <tr>
                    <th class="center" width="4%">#</th>
                    <th width="30%">Description / HSN-SAC</th>
                    <th class="right" width="9%">Qty</th>
                    <th class="right" width="14%">Rate</th>
                    <th class="right" width="13%">Discount</th>
                    <th class="right" width="18%">Taxable value</th>
                    <th class="right" width="12%">GST %</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->items as $item)
                    @php
                        $quantity = rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ','), '0'), '.');
                        $effectiveGst = $invoice->gst_type === 'export' ? 0 : (float) $item->gst_percent;
                        $gstPercent = rtrim(rtrim(number_format($effectiveGst, 2, '.', ''), '0'), '.');
                    @endphp
                    <tr>
                        <td class="center">{{ $loop->iteration }}</td>
                        <td class="item-description">
                            <strong class="item-name">{{ $item->product_name }}</strong>
                            @if ($item->description)
                                <span class="item-detail">{!! nl2br(e($item->description)) !!}</span>
                            @endif
                            @if ($item->remarks)
                                <span class="item-detail item-remark">{!! nl2br(e($item->remarks)) !!}</span>
                            @endif
                            <span class="item-meta">HSN/SAC {{ $item->hsn_sac ?: '—' }}</span>
                        </td>
                        <td class="right">{{ $quantity }} {{ $item->unit }}</td>
                        <td class="right">{{ $money($item->unit_price) }}</td>
                        <td class="right">{{ (float) $item->discount_amount > 0 ? '-'.$money($item->discount_amount) : '—' }}</td>
                        <td class="right item-taxable">{{ $money($item->taxable_amount) }}</td>
                        <td class="right">{{ $gstPercent }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="totals">
            <div class="totals-notes">
                <div class="amount-words">
                    <strong>Amount in words</strong>
                    <span>{{ $invoice->amount_in_words ?: '—' }}</span>
                </div>
                @if ($invoice->payment_terms)
                    <div class="invoice-terms">
                        <strong>Payment terms</strong>
                        <span>{{ $invoice->payment_terms }}</span>
                    </div>
                @endif
                @if ($invoice->terms_conditions)
                    <div class="invoice-terms">
                        <strong>Terms &amp; conditions</strong>
                        <span>{{ $invoice->terms_conditions }}</span>
                    </div>
                @endif
                @if ($invoice->notes)
                    <div class="invoice-terms">
                        <strong>Notes</strong>
                        <span>{{ $invoice->notes }}</span>
                    </div>
                @endif
            </div>
            <div class="summary">
                <table>
                    <tbody>
                        <tr>
                            <td>Subtotal</td>
                            <td>{{ $money($invoice->subtotal) }}</td>
                        </tr>
                        @if ($lineDiscountTotal > 0)
                            <tr>
                                <td>Item discounts</td>
                                <td>-{{ $money($lineDiscountTotal) }}</td>
                            </tr>
                        @endif
                        @if ((float) $invoice->discount_amount > 0)
                            <tr>
                                <td>Invoice discount</td>
                                <td>-{{ $money($invoice->discount_amount) }}</td>
                            </tr>
                        @endif
                        <tr class="taxable-row">
                            <td>Taxable value</td>
                            <td>{{ $money($invoice->taxable_amount) }}</td>
                        </tr>
                        @if ((float) $invoice->cgst_amount > 0)
                            <tr><td>CGST</td><td>{{ $money($invoice->cgst_amount) }}</td></tr>
                        @endif
                        @if ((float) $invoice->sgst_amount > 0)
                            <tr><td>SGST</td><td>{{ $money($invoice->sgst_amount) }}</td></tr>
                        @endif
                        @if ((float) $invoice->igst_amount > 0)
                            <tr><td>IGST</td><td>{{ $money($invoice->igst_amount) }}</td></tr>
                        @endif
                        @if ((float) $invoice->freight_amount !== 0.0)
                            <tr><td>Freight</td><td>{{ $money($invoice->freight_amount) }}</td></tr>
                        @endif
                        @if ((float) $invoice->packing_amount !== 0.0)
                            <tr><td>Packing</td><td>{{ $money($invoice->packing_amount) }}</td></tr>
                        @endif
                        @if ((float) $invoice->other_charges !== 0.0)
                            <tr><td>Other charges</td><td>{{ $money($invoice->other_charges) }}</td></tr>
                        @endif
                        @if (abs((float) $invoice->round_off) >= 0.01)
                            <tr><td>Round off</td><td>{{ $money($invoice->round_off) }}</td></tr>
                        @endif
                        <tr class="grand-total">
                            <td>Grand total</td>
                            <td>{{ $money($invoice->total_amount) }}</td>
                        </tr>
                        @if ($received > 0)
                            <tr class="settlement-row"><td>Received to date</td><td>-{{ $money($received) }}</td></tr>
                            <tr class="balance-due"><td>Balance due</td><td>{{ $money($balanceDue) }}</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
        <div class="bank-sign">
            <div class="box">
                <h3>Bank details</h3>
                <dl class="bank-details">
                    @foreach ([
                        'Bank' => $invoice->seller_bank_name,
                        'Account holder' => $invoice->seller_account_holder,
                        'Account number' => $invoice->seller_account_number,
                        'IFSC' => $invoice->seller_ifsc,
                        'Branch' => $invoice->seller_branch,
                    ] as $label => $value)
                        @if ($value)
                            <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                        @endif
                    @endforeach
                </dl>
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
