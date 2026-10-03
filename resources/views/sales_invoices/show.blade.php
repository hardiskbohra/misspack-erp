@extends('layouts.app')

@section('page-title', $invoice->invoice_number)

@section('content')
@php
    /* One rule for the money, read from the model — this page used to add up
       the payments itself, which is how the same invoice ends up with two
       different balances on two screens (`SalesInvoice::receivedAmount()`). */
    $receivedAmount = $invoice->receivedAmount();
    $balanceAmount = $invoice->balanceDue();
@endphp
    <div class="master-list">
        <div class="master-card master-header">
            <div>
                <h1>{{ $invoice->invoice_number }}</h1>
                <div class="master-breadcrumb"><a href="{{ url('/') }}">Home</a><span>•</span><a
                        href="{{ route('sales-invoices.index') }}">Invoices</a><span>•</span><span
                        class="active">{{ $invoice->invoice_number }}</span></div>
                <p class="master-sub">{{ $invoice->typeLabel() }} · {{ $invoice->client_company_name }} ·
                    {{ optional($invoice->invoice_date)->format('d M Y') }}</p>
            </div>
            <div class="master-actions">
                {{-- The screen an accountant opens when the client rings: what was
                     asked, when, and what came back — plus the same chase the list
                     offers. --}}
                <button type="button" class="master-btn master-btn-soft" data-open-reminder
                    data-invoice-id="{{ $invoice->id }}"
                    data-invoice-number="{{ $invoice->invoice_number }}"
                    data-invoice-message="{{ $invoice->reminderMessage() }}">
                    <i class="fas fa-bell" aria-hidden="true"></i> Log reminder
                </button>
                <button type="button" class="master-btn master-btn-light"
                    data-copy-text="{{ $invoice->reminderMessage() }}">
                    <i class="fas fa-comment-dots" aria-hidden="true"></i> Copy reminder text
                </button>
                <a href="{{ route('sales-invoices.index') }}" class="master-btn master-btn-light">Back</a>
                <a href="{{ route('sales-invoices.edit', $invoice) }}" class="master-btn master-btn-light">Edit</a>

                {{-- The screen an accountant opens when the client rings about
                     this invoice is exactly where a receipt should be recordable.
                     It goes into the ledger, so the statement, the ledger and
                     this balance stay one number. --}}
                @if ($balanceAmount > 0)
                    <button type="button" class="master-btn master-btn-soft" data-open-payment
                        data-invoice-id="{{ $invoice->id }}"
                        data-invoice-number="{{ $invoice->invoice_number }}"
                        data-invoice-amount="{{ number_format($balanceAmount, 2, '.', '') }}"
                        data-invoice-balance="{{ \App\Helpers\CommonHelper::indianCurrency($balanceAmount) }}">
                        <i class="fas fa-indian-rupee-sign" aria-hidden="true"></i> Record payment
                    </button>
                @endif

                <a href="{{ route('sales-invoices.print', $invoice) }}" target="_blank"
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
            <div><span>Received</span><strong>{{ \App\Helpers\CommonHelper::indianCurrency($receivedAmount) }}</strong></div>
            <div><span>Tax</span><strong>{{ \App\Helpers\CommonHelper::indianCurrency($invoice->cgst_amount + $invoice->sgst_amount + $invoice->igst_amount) }}</strong>
            </div>
            <div class="{{ $balanceAmount > 0 ? 'red' : 'green' }}"><span>Balance</span><strong>{{ \App\Helpers\CommonHelper::indianCurrency($balanceAmount) }}</strong></div>
            <div><span>Due</span><strong>{{ optional($invoice->due_date)->format('d M Y') ?: '-' }}</strong></div>
        </div>
        <div class="master-grid">
            <div class="master-card">
                <div class="si-head">
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
                <div class="si-head">
                    <h2>Billing Details</h2>
                </div>
                <div class="master-info"><span>Company</span><strong>{{ $invoice->client_company_name }}</strong></div>
                <div class="master-info"><span>GSTIN / PAN</span><strong>{{ $invoice->client_gstin ?: '-' }} /
                        {{ $invoice->client_pan ?: '-' }}</strong></div>
                <div class="master-info"><span>Billing</span><strong>{{ $invoice->billing_address }}, {{ $invoice->billing_city }} - {{ $invoice->billing_pincode }}, {{ $invoice->billing_state }}, {{ $invoice->billing_country }}</strong></div>
                <div class="master-info"><span>Shipping</span><strong>{{ $invoice->shipping_address }}, {{ $invoice->shipping_city }} - {{ $invoice->shipping_pincode }}, {{ $invoice->shipping_state }}, {{ $invoice->shipping_country }}</strong></div>
            </div>
        </div>
        <div class="master-grid">
            <div class="master-card">
                <div class="si-head">
                    <h2>Summary</h2>
                </div>
                <div class="si-total-row"><span>Subtotal</span><strong>{{ \App\Helpers\CommonHelper::indianCurrency($invoice->subtotal) }}</strong></div>
                <div class="si-total-row"><span>Discount</span><strong>- {{ \App\Helpers\CommonHelper::indianCurrency($invoice->discount_amount) }}</strong></div>
                <div class="si-total-row"><span>Tax</span><strong>{{ \App\Helpers\CommonHelper::indianCurrency($invoice->cgst_amount + $invoice->sgst_amount + $invoice->igst_amount) }}</strong></div>
                <div class="si-total-row"><span>Charges</span><strong>{{ \App\Helpers\CommonHelper::indianCurrency((float) $invoice->freight_amount + (float) $invoice->packing_amount + (float) $invoice->other_charges) }}</strong>
                </div>
                <div class="si-total-row big"><span>Grand Total</span><strong>{{ \App\Helpers\CommonHelper::indianCurrency((float) $invoice->total_amount) }}</strong></div><br>
                <p>{{ $invoice->amount_in_words }}</p>
            </div>
            <div class="master-card">
                <div class="si-head">
                    <h2>Payments</h2>
                </div>
                @forelse($invoice->payments as $payment)
                    <div class="si-file">
                        <div><strong>{{ optional($payment->entry_date)->format('d M Y') }}</strong></div>
                        <div><strong>{{ strtoupper($payment->payment_mode) }}</strong></div>
                        <div><strong>{{ \App\Helpers\CommonHelper::indianCurrency((float) $payment->credit_amount - (float) $payment->debit_amount) }}</strong></div>
                    </div>
                @empty
                <div class="master-empty">No payments.</div>
                @endforelse
            </div>
            <div class="master-card">
                <div class="si-head">
                    <h2>Chase</h2>
                    <span class="si-count">{{ $invoice->reminderCount() }}</span>
                </div>
                @forelse($invoice->reminders as $reminder)
                    <div class="si-reminder">
                        <div class="si-reminder-when">
                            <strong>{{ optional($reminder->reminded_at)->format('d M Y') }}</strong>
                            <span class="master-sub">{{ $reminder->channelLabel() }}</span>
                        </div>
                        <div class="si-reminder-what">
                            @if ($reminder->note)
                                <strong>{{ $reminder->note }}</strong>
                            @endif
                            <span class="master-sub">
                                {{ $reminder->creator?->name ? 'by '.$reminder->creator->name : 'logged' }}
                                @if ($reminder->message) · {{ \Illuminate\Support\Str::limit($reminder->message, 90) }} @endif
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="master-empty">
                        Nobody has asked for this money yet.
                        @if ($balanceAmount > 0) Ring, WhatsApp or email the client, then log it here. @endif
                    </div>
                @endforelse
            </div>
            <div class="master-card">
                <div class="si-head">
                    <h2>Notes</h2>
                </div>
                @if (filled($invoice->notes))
                    <div class="si-note">
                        <span class="master-sub">On the invoice (the client may see it)</span>
                        <p>{{ $invoice->notes }}</p>
                    </div>
                @endif
                @if (filled($invoice->internal_notes))
                    <div class="si-note is-internal">
                        <span class="master-sub">Internal — never printed, never on the portal</span>
                        <p>{{ $invoice->internal_notes }}</p>
                    </div>
                @endif
                @if (blank($invoice->notes) && blank($invoice->internal_notes))
                    <div class="master-empty">No notes on this invoice. Edit it to add one.</div>
                @endif
            </div>
            <div class="master-card">
                <div class="si-head">
                    <h2>Files</h2>
                </div>
                @forelse($invoice->attachments as $attachment)
                    <div class="si-file"><span>📄</span>
                        <div><strong>{{ $attachment->title ?: $attachment->original_name }}</strong><a
                                href="{{ $attachment->fileUrl() }}" target="_blank">Open file</a></div>
                </div>@empty<div class="master-empty">No attachments.</div>
                @endforelse
            </div>
        </div>
    </div>

    @include('sales_invoices.partials.payment-modal')
    @include('sales_invoices.partials.reminder-modal')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/sales-invoices.css') }}">
@endpush
@push('scripts')
    <script src="{{ $assetVer('assets/js/sales-invoices.js') }}"></script>
@endpush
@endsection
