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
@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/sales-invoices.css') }}">
@endpush
@endsection
