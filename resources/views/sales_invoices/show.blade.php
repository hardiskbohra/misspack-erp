@extends('layouts.app')

@section('page-title', $invoice->invoice_number)

@section('content')
@php
    $paidAmount = $invoice->payments->sum('credit_amount') - $invoice->payments->sum('debit_amount');
    $balanceAmount = (float) $invoice->total_amount - $paidAmount;
@endphp
    <div class="master-page" style="padding:0;">
        <div class="master-hero">
            <div>
                <p class="master-eyebrow">{{ $invoice->typeLabel() }}</p>
                <h1>{{ $invoice->invoice_number }}</h1>
                <p>{{ $invoice->client_company_name }} · {{ optional($invoice->invoice_date)->format('d M Y') }}</p>
            </div>
            <div class="master-actions"><a href="{{ route('sales-invoices.index') }}" class="master-btn master-btn-light">Back</a><a
                    href="{{ route('sales-invoices.edit', $invoice) }}" class="master-btn master-btn-light">Edit</a><a
                    href="{{ route('sales-invoices.print', $invoice) }}" target="_blank"
                    class="master-btn master-btn-primary">Print</a>
                @if (!$invoice->show_client_portal)
                    <form method="POST" action="{{ route('sales-invoices.markSent', $invoice) }}">@csrf
                        @method('PATCH')<button class="master-btn master-btn-soft" type="submit">Mark Sent / Portal</button>
                    </form>
                @endif
            </div>
        </div>
        <div class="master-stats">
            <div><span>Total</span><strong>{{ \App\Helpers\CommonHelper::indianCurrency($invoice->total_amount) }}</strong></div>
            <div><span>Taxable</span><strong>{{ \App\Helpers\CommonHelper::indianCurrency($invoice->taxable_amount) }}</strong></div>
            <div><span>Tax</span><strong>{{ \App\Helpers\CommonHelper::indianCurrency($invoice->cgst_amount + $invoice->sgst_amount + $invoice->igst_amount) }}</strong>
            </div>
            <div><span>Balance</span><strong>{{ \App\Helpers\CommonHelper::indianCurrency($balanceAmount) }}</strong></div>
            <div><span>Portal</span><strong>{{ $invoice->show_client_portal ? 'Visible' : 'Hidden' }}</strong></div>
        </div>
        <div class="master-grid" style="margin-top:-25px;">
            <div class="master-card">
                <div class="master-head">
                    <p class="master-eyebrow">Items</p>
                    <h2>Products</h2>
                </div>
                <div class="master-table-wrap">
                    <table class="master-table">
                        <thead>
                            <tr>
                                <th width="2%">#</th>
                                <th width="35%">Product</th>
                                <th width="10%">Qty</th>
                                <th width="10%">Rate</th>
                                <th width="10%">Taxable</th>
                                <th width="10%">GST</th>
                                <th width="15%">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoice->items as $item)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>
                                        <strong>{{ $item->product_name }}</strong><span>{!! nl2br(e($item->description)) !!}</span><span>HSN: {{ $item->hsn_sac ?: '-' }}</span>
                                    </td>
                                    <td>{{ number_format((float) $item->quantity,0) }} {{ $item->unit }}</td>
                                    <td>{{ \App\Helpers\CommonHelper::indianCurrency($item->unit_price) }}</td>
                                    <td>{{ \App\Helpers\CommonHelper::indianCurrency($item->taxable_amount) }}</td>
                                    <td>{{ number_format($item->gst_percent, 0) }}%</td>
                                    <td>{{ \App\Helpers\CommonHelper::indianCurrency($item->line_total) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="master-card">
                <div class="master-head">
                    <p class="master-eyebrow">Client</p>
                    <h2>Billing Details</h2>
                </div>
                <div class="master-info"><span>Company</span><strong>{{ $invoice->client_company_name }}</strong></div>
                <div class="master-info"><span>GSTIN / PAN</span><strong>{{ $invoice->client_gstin ?: '-' }} /
                        {{ $invoice->client_pan ?: '-' }}</strong></div>
                <div class="master-info"><span>Billing</span><strong>{{ $invoice->billing_address }}, {{ $invoice->billing_city }} - {{ $invoice->billing_pincode }}, {{ $invoice->billing_state }}, {{ $invoice->billing_country }}</strong></div>
                <div class="master-info"><span>Shipping</span><strong>{{ $invoice->shipping_address }}, {{ $invoice->shipping_city }} - {{ $invoice->shipping_pincode }}, {{ $invoice->shipping_state }}, {{ $invoice->shipping_country }}</strong></div>
            </div>
        </div>
        <div class="master-grid" style="margin-top:18px;">
            <div class="master-card">
                <div class="master-head">
                    <p class="master-eyebrow">Totals</p>
                    <h2>Summary</h2>
                </div>
                <div class="master-total-row"><span>Subtotal</span><strong>{{ \App\Helpers\CommonHelper::indianCurrency($invoice->subtotal) }}</strong></div>
                <div class="master-total-row"><span>Discount</span><strong>- {{ \App\Helpers\CommonHelper::indianCurrency($invoice->discount_amount) }}</strong></div>
                <div class="master-total-row"><span>Tax</span><strong>{{ \App\Helpers\CommonHelper::indianCurrency($invoice->cgst_amount + $invoice->sgst_amount + $invoice->igst_amount) }}</strong></div>
                <div class="master-total-row"><span>Charges</span><strong>{{ \App\Helpers\CommonHelper::indianCurrency((float) $invoice->freight_amount + (float) $invoice->packing_amount + (float) $invoice->other_charges) }}</strong>
                </div>
                <div class="master-total-row big"><span>Grand Total</span><strong>{{ \App\Helpers\CommonHelper::indianCurrency((float) $invoice->total_amount) }}</strong></div><br>
                <p>{{ $invoice->amount_in_words }}</p>
            </div>
            <div class="master-card">
                <div class="master-head">
                    <p class="master-eyebrow">Transactions</p>
                    <h2>Payments</h2>
                </div>
                @forelse($invoice->payments as $payment)
                    <div class="master-file" style="display:flex;justify-content:space-between">
                        <div><strong>{{ optional($payment->entry_date)->format('d M Y') }}</strong></div>
                        <div><strong>{{ strtoupper($payment->payment_mode) }}</strong></div>
                        <div><strong><b>{{ \App\Helpers\CommonHelper::indianCurrency((float) $payment->credit_amount) }}</b></strong></div>
                    </div>
                @empty
                <div class="master-empty">No payments.</div>
                @endforelse
            </div>
            <div class="master-card">
                <div class="master-head">
                    <p class="master-eyebrow">Attachments</p>
                    <h2>Files</h2>
                </div>
                @forelse($invoice->attachments as $attachment)
                    <div class="master-file"><span>📄</span>
                        <div><strong>{{ $attachment->title ?: $attachment->original_name }}</strong><a
                                href="{{ $attachment->fileUrl() }}" target="_blank">Open file</a></div>
                </div>@empty<div class="master-empty">No attachments.</div>
                @endforelse
            </div>
        </div>
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
            gap: 18px
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
            font-size: 30px
        }

        .master-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center
        }

        .master-btn {
            border: 0;
            border-radius: 14px;
            padding: 10px 15px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px
        }

        .master-btn-primary {
            background: #ef4770;
            color: #fff
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

        .master-alert {
            border-radius: 16px;
            padding: 13px 15px;
            font-weight: 600
        }

        .master-alert.success {
            background: #e8fff7;
            color: #047857
        }

        .master-stats {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 14px
        }

        .master-stats div,
        .master-card {
            background: #fff;
            border: 1px solid #dfe7f3;
            border-radius: 22px;
            padding: 18px;
            box-shadow: 0 14px 35px rgba(25, 42, 70, .08)
        }

        .master-stats span {
            display: block;
            color: #687386;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase
        }

        .master-stats strong {
            display: block;
            margin-top: 6px;
            font-size: 20px
        }

        .master-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.4fr) minmax(320px, .6fr);
            gap: 18px
        }

        .master-head h2 {
            margin: 0 0 14px
        }

        .master-table-wrap {
            overflow: auto
        }

        .master-table {
            width: 100%;
            min-width: 780px;
            border-collapse: collapse
        }

        .master-table th,
        .master-table td {
            padding: 12px;
            border-bottom: 1px solid #dfe7f3;
            text-align: left;
            vertical-align: top
        }

        .master-table th {
            font-size: 11px;
            text-transform: uppercase;
            color: #7d8aa0
        }

        .master-table span,
        .master-info span {
            display: block;
            color: #687386;
            font-size: 12px;
            margin-top: 3px
        }

        .master-info {
            background: #f8fbff;
            border: 1px solid #edf2ff;
            border-radius: 14px;
            padding: 12px;
            margin-bottom: 10px
        }

        .master-info strong {
            display: block;
            margin-top: 5px
        }

        .master-total-row {
            display: flex;
            justify-content: space-between;
            border-bottom: 1px solid #edf0f7;
            padding: 10px 0
        }

        .master-total-row.big {
            font-size: 20px
        }

        .master-file {
            display: flex;
            gap: 10px;
            border: 1px solid #edf0f7;
            border-radius: 14px;
            padding: 10px;
            margin-bottom: 10px
        }

        .master-file a {
            display: block;
            color: #4f83f1;
            font-weight: 600;
            text-decoration: none;
            margin-top: 5px
        }

        .master-empty {
            text-align: center;
            padding: 25px;
            color: #687386;
            border: 1px dashed #d8deea;
            border-radius: 16px
        }

        @media(max-width:991px) {

            .master-grid,
            .master-stats {
                grid-template-columns: 1fr 1fr
            }
        }

        @media(max-width:767px) {
            .master-page {
                padding: 14px
            }

            .master-hero {
                flex-direction: column
            }

            .master-actions,
            .master-actions .master-btn {
                width: 100%
            }

            .master-grid,
            .master-stats {
                grid-template-columns: 1fr
            }
        }
    </style>
@endsection
