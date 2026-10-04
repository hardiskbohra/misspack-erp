@extends('layouts.app')

@section('page-title', $invoice->invoice_number)

@section('content')
@php
    /* The money is read once, from the model, in one place: this page used to
       add the payments up itself, which is how one invoice ends up with two
       different balances on two screens (`SalesInvoice::receivedAmount()`). */
    $receivedAmount = $invoice->receivedAmount();
    $balanceAmount = $invoice->balanceDue();
    $taxAmount = (float) $invoice->cgst_amount + (float) $invoice->sgst_amount + (float) $invoice->igst_amount;
    $chargesAmount = (float) $invoice->freight_amount + (float) $invoice->packing_amount + (float) $invoice->other_charges;

    /* Newest receipt first: the one that just landed is the one being read for. */
    $receipts = $invoice->payments->sortByDesc('entry_date');

    $stateKey = $invoice->stateKey();
    $daysLate = $invoice->daysOverdue();
    $balanceTone = $balanceAmount <= 0 ? 'green' : ($daysLate > 0 ? 'orange' : 'purple');
    $ageingLabel = \App\Models\SalesInvoice::ageingBuckets()[$invoice->ageingBucket()] ?? null;

    /* An address is the parts that exist, in reading order — never a run of
       commas over the fields the office left empty. */
    $addressLine = fn (...$parts) => implode(', ', array_values(array_filter(
        array_map(fn ($part) => trim((string) $part), $parts),
        fn ($part) => $part !== ''
    )));
    $billingAddress = $addressLine(
        $invoice->billing_address,
        trim($invoice->billing_city.($invoice->billing_pincode ? ' - '.$invoice->billing_pincode : '')),
        $invoice->billing_state,
        $invoice->billing_country
    );
    $shippingAddress = $addressLine(
        $invoice->shipping_address,
        trim($invoice->shipping_city.($invoice->shipping_pincode ? ' - '.$invoice->shipping_pincode : '')),
        $invoice->shipping_state,
        $invoice->shipping_country
    );

    /* A figure somebody typed: 12, not 12.000 — and 1.5 stays 1.5. */
    $figure = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.') ?: '0';
@endphp
    <div class="master-list si-show">
        {{-- The header answers three questions before anybody clicks: what this
             is, where the money stands, and what can be done with it. The state
             chip comes first — it is the question the page was opened to ask. --}}
        <header class="master-card master-header">
            <div class="record-head-main">
                <h1>{{ $invoice->invoice_number }}</h1>
                <p class="master-sub si-show-sub">
                    {{ $invoice->typeLabel() }}
                    @if (filled($invoice->client_company_name)) · {{ $invoice->client_company_name }} @endif
                    @if ($invoice->invoice_date) · {{ $invoice->invoice_date->format('d M Y') }} @endif
                </p>
                <div class="record-head-chips">
                    <span class="si-status status-{{ $stateKey }}">{{ $invoice->stateLabel() }}</span>

                    @if ($invoice->due_date)
                        <span class="master-chip {{ $balanceAmount > 0 && $daysLate > 0 ? 'si-chip-late' : '' }}">
                            <i class="fas fa-calendar-day" aria-hidden="true"></i>
                            @if ($balanceAmount > 0 && $daysLate > 0)
                                {{ $daysLate }} {{ \Illuminate\Support\Str::plural('day', $daysLate) }} past due — {{ $invoice->due_date->format('d M Y') }}
                            @else
                                Due {{ $invoice->due_date->format('d M Y') }}
                            @endif
                        </span>
                    @endif

                    @if ($invoice->isSuperseded())
                        <a class="master-chip si-chip-converted"
                            href="{{ route('sales-invoices.show', $invoice->convertedInvoice) }}">
                            <i class="fas fa-arrow-right-arrow-left" aria-hidden="true"></i>
                            Became {{ $invoice->convertedInvoice?->invoice_number }}
                        </a>
                    @endif

                    <span class="si-portal {{ $invoice->show_client_portal ? 'is-public' : 'is-private' }}">
                        <i class="fas fa-{{ $invoice->show_client_portal ? 'toggle-on' : 'toggle-off' }}" aria-hidden="true"></i>
                        {{ $invoice->show_client_portal ? 'In client portal' : 'Not in portal' }}
                    </span>

                    @if (filled($invoice->po_number))
                        <span class="master-chip">
                            <i class="fas fa-file-signature" aria-hidden="true"></i>
                            PO {{ $invoice->po_number }}@if ($invoice->po_date) · {{ $invoice->po_date->format('d M Y') }}@endif
                        </span>
                    @endif

                    @if ($invoice->lastRemindedAt())
                        <span class="master-chip">
                            <i class="fas fa-bell" aria-hidden="true"></i>
                            Last asked {{ $invoice->lastRemindedAt()->format('d M Y') }} · {{ $invoice->reminderCount() }}×
                        </span>
                    @endif
                </div>
            </div>

            <div class="record-head-actions">
                <a href="{{ route('sales-invoices.index') }}" class="master-btn master-btn-light">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i> Back
                </a>
                <a href="{{ route('sales-invoices.edit', $invoice) }}" class="master-btn master-btn-light">
                    <i class="fas fa-pen" aria-hidden="true"></i> Edit
                </a>

                @if ($balanceAmount > 0)
                    <button type="button" class="master-btn master-btn-soft" data-open-payment
                        data-invoice-id="{{ $invoice->id }}"
                        data-invoice-number="{{ $invoice->invoice_number }}"
                        data-invoice-amount="{{ number_format($balanceAmount, 2, '.', '') }}"
                        data-invoice-balance="{{ \App\Helpers\CommonHelper::indianCurrency($balanceAmount) }}">
                        <i class="fas fa-indian-rupee-sign" aria-hidden="true"></i> Record payment
                    </button>
                @endif

                <button type="button" class="master-btn master-btn-soft" data-open-reminder
                    data-invoice-id="{{ $invoice->id }}"
                    data-invoice-number="{{ $invoice->invoice_number }}"
                    data-invoice-message="{{ $invoice->reminderMessage() }}">
                    <i class="fas fa-bell" aria-hidden="true"></i> Log reminder
                </button>

                <a href="{{ route('sales-invoices.print', $invoice) }}" target="_blank"
                    class="master-btn master-btn-primary">
                    <i class="fas fa-print" aria-hidden="true"></i> Print
                </a>

                <div class="master-dropdown">
                    <button type="button" class="master-dropdown-toggle"
                        aria-label="More actions for {{ $invoice->invoice_number }}"
                        aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                    </button>

                    <div class="master-dropdown-menu">
                        <button type="button" data-copy-text="{{ $invoice->reminderMessage() }}">
                            <i class="fas fa-comment-dots" aria-hidden="true"></i> Copy reminder text
                        </button>
                        <button type="button" data-copy-link="{{ route('sales-invoices.public', $invoice->public_token) }}">
                            <i class="fas fa-link" aria-hidden="true"></i> Copy client link
                        </button>
                        <a href="{{ route('sales-invoices.public', $invoice->public_token) }}" target="_blank" rel="noopener">
                            <i class="fas fa-up-right-from-square" aria-hidden="true"></i> Open client copy
                        </a>
                        @if ($invoice->status === 'draft')
                            <form method="POST" action="{{ route('sales-invoices.markSent', $invoice) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit">
                                    <i class="fas fa-paper-plane" aria-hidden="true"></i> Mark sent
                                </button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('sales-invoices.portal', $invoice) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit">
                                <i class="fas fa-toggle-{{ $invoice->show_client_portal ? 'on' : 'off' }}" aria-hidden="true"></i>
                                {{ $invoice->show_client_portal ? 'Hide from portal' : 'Show in portal' }}
                            </button>
                        </form>
                        @if ($invoice->invoice_type === 'proforma' && ! $invoice->isSuperseded())
                            <form method="POST" action="{{ route('sales-invoices.convert', $invoice) }}"
                                data-confirm="Create a tax invoice from {{ $invoice->invoice_number }}? The advance and every receipt on it will move to the new tax invoice.">
                                @csrf
                                <button type="submit">
                                    <i class="fas fa-file-invoice" aria-hidden="true"></i> Convert to tax invoice
                                </button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('sales-invoices.duplicate', $invoice) }}">
                            @csrf
                            <button type="submit">
                                <i class="fas fa-copy" aria-hidden="true"></i> Duplicate as draft
                            </button>
                        </form>
                        <form method="POST" action="{{ route('sales-invoices.destroy', $invoice) }}"
                            data-confirm="Delete {{ $invoice->invoice_number }}?">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="danger">
                                <i class="fas fa-trash" aria-hidden="true"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        {{-- Five figures, each one a fact the page was opened for: what was
             asked, what came back, the tax in it, what is still open and when
             it was due. The wording under each is the sentence a figure alone
             cannot say. --}}
        <div class="master-stats">
            <div class="master-stat master-stat--flat blue">
                <span class="icon" aria-hidden="true"><i class="fas fa-file-invoice"></i></span>
                <div>
                    <p class="master-stat-title">Invoice total</p>
                    <p class="master-stat-value">{{ \App\Helpers\CommonHelper::indianCurrency($invoice->total_amount) }}</p>
                    <p class="master-sub">
                        {{ $invoice->items->count() }} {{ \Illuminate\Support\Str::plural('line', $invoice->items->count()) }}
                        @if ($invoice->currency !== 'INR')
                            · {{ $invoice->currency }}{{ $invoice->exchange_rate ? ' at '.$figure($invoice->exchange_rate) : '' }}
                        @endif
                    </p>
                </div>
            </div>

            <div class="master-stat master-stat--flat teal">
                <span class="icon" aria-hidden="true"><i class="fas fa-indian-rupee-sign"></i></span>
                <div>
                    <p class="master-stat-title">Received</p>
                    <p class="master-stat-value">{{ \App\Helpers\CommonHelper::indianCurrency($receivedAmount) }}</p>
                    <p class="master-sub">
                        @if ($invoice->isSuperseded())
                            Moved to {{ $invoice->convertedInvoice?->invoice_number }}
                        @elseif ($receipts->isEmpty())
                            Nothing received yet
                        @else
                            {{ $receipts->count() }} {{ \Illuminate\Support\Str::plural('receipt', $receipts->count()) }} in the ledger
                        @endif
                    </p>
                </div>
            </div>

            <div class="master-stat master-stat--flat purple">
                <span class="icon" aria-hidden="true"><i class="fas fa-percent"></i></span>
                <div>
                    <p class="master-stat-title">GST</p>
                    <p class="master-stat-value">{{ \App\Helpers\CommonHelper::indianCurrency($taxAmount) }}</p>
                    <p class="master-sub">{{ $invoice->gstTypeLabel() }}</p>
                </div>
            </div>

            <div class="master-stat master-stat--flat {{ $balanceTone }}">
                <span class="icon" aria-hidden="true"><i class="fas fa-scale-balanced"></i></span>
                <div>
                    <p class="master-stat-title">Balance due</p>
                    <p class="master-stat-value">{{ \App\Helpers\CommonHelper::indianCurrency($balanceAmount) }}</p>
                    <p class="master-sub">
                        @if ($invoice->isSuperseded())
                            Moved to the tax invoice
                        @elseif ($balanceAmount <= 0)
                            Received in full
                        @else
                            {{ $ageingLabel }}
                        @endif
                    </p>
                </div>
            </div>

            <div class="master-stat master-stat--flat orange">
                <span class="icon" aria-hidden="true"><i class="fas fa-calendar-check"></i></span>
                <div>
                    <p class="master-stat-title">Due date</p>
                    <p class="master-stat-value">
                        @if ($invoice->due_date)
                            {{ $invoice->due_date->format('d M Y') }}
                        @else
                            <span class="master-empty-value">Not set</span>
                        @endif
                    </p>
                    <p class="master-sub">
                        {{ filled($invoice->payment_terms) ? $invoice->payment_terms : 'No payment terms on the invoice' }}
                    </p>
                </div>
            </div>
        </div>

        {{-- The line items get the page's full width: they are the one block on
             this page that cannot be read in half a column — this table used to
             sit in the left of a 2:1 grid and scroll sideways inside its own
             card while the right-hand column stayed mostly empty. --}}
        <div class="master-card master-section">
            <div class="master-section-head">
                <div>
                    <h2 class="master-section-title">Products</h2>
                    <p class="master-sub">What was sold, taxed per line as the invoice books it</p>
                </div>
                <div class="master-section-meta">
                    <span class="master-chip">
                        {{ $invoice->items->count() }} {{ \Illuminate\Support\Str::plural('line', $invoice->items->count()) }}
                    </span>
                </div>
            </div>

            @if ($invoice->items->isEmpty())
                <div class="master-empty-state">
                    <i class="fas fa-box-open"></i>
                    <p>No line items on this invoice yet. They are entered on <strong>Edit</strong>.</p>
                    <a href="{{ route('sales-invoices.edit', $invoice) }}" class="master-btn master-btn-light">Add items</a>
                </div>
            @else
                <div class="master-table-wrap">
                    <table class="master-table">
                        <thead>
                            <tr>
                                <th width="4%">#</th>
                                <th width="31%">Product</th>
                                <th width="12%" class="is-num">Qty</th>
                                <th width="13%" class="is-num">Rate</th>
                                <th width="13%" class="is-num">Taxable</th>
                                <th width="9%" class="is-num">GST</th>
                                <th width="18%" class="is-num">Line total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoice->items as $item)
                                <tr>
                                    <td data-label="#">{{ $loop->iteration }}</td>
                                    <td data-label="Product">
                                        <strong>{{ $item->product_name }}</strong>
                                        @if (filled($item->description))
                                            <span class="master-sub">{!! nl2br(e($item->description)) !!}</span>
                                        @endif
                                        <span class="master-sub">HSN/SAC {{ $item->hsn_sac ?: 'not set' }}</span>
                                    </td>
                                    <td data-label="Qty" class="is-num">{{ $figure($item->quantity) }} {{ $item->unit }}</td>
                                    <td data-label="Rate" class="is-num">{{ \App\Helpers\CommonHelper::indianCurrency($item->unit_price) }}</td>
                                    <td data-label="Taxable" class="is-num">{{ \App\Helpers\CommonHelper::indianCurrency($item->taxable_amount) }}</td>
                                    <td data-label="GST" class="is-num">{{ $figure($item->gst_percent) }}%</td>
                                    <td data-label="Line total" class="is-num">{{ \App\Helpers\CommonHelper::indianCurrency($item->line_total) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="master-grid is-even">
            {{-- Who this is addressed to. Every field that the office left empty
                 says so in words — a fact row is never a bare dash, and an
                 address is never a run of commas over missing parts. --}}
            <div>
                <div class="master-card master-section">
                    <div class="master-section-head">
                        <div>
                            <h2 class="master-section-title">Billing details</h2>
                            <p class="master-sub">Who this invoice was raised on, and where it goes</p>
                        </div>
                        @if (filled($invoice->client_brand_name))
                            <div class="master-section-meta">
                                <span class="master-chip">{{ $invoice->client_brand_name }}</span>
                            </div>
                        @endif
                    </div>

                    <div class="master-facts">
                        <div class="master-info">
                            <span>Client</span>
                            @if (filled($invoice->client_company_name))
                                <strong>{{ $invoice->client_company_name }}</strong>
                            @else
                                <span class="master-empty-value">No client recorded</span>
                            @endif
                        </div>
                        <div class="master-info">
                            <span>GSTIN</span>
                            @if (filled($invoice->client_gstin))
                                <strong>{{ $invoice->client_gstin }}</strong>
                            @else
                                <span class="master-empty-value">Not on file</span>
                            @endif
                        </div>
                        <div class="master-info">
                            <span>PAN</span>
                            @if (filled($invoice->client_pan))
                                <strong>{{ $invoice->client_pan }}</strong>
                            @else
                                <span class="master-empty-value">Not on file</span>
                            @endif
                        </div>
                        <div class="master-info">
                            <span>Place of supply</span>
                            @if (filled($invoice->place_of_supply) || filled($invoice->billing_state))
                                <strong>{{ $invoice->place_of_supply ?: $invoice->billing_state }}</strong>
                            @else
                                <span class="master-empty-value">Not on file</span>
                            @endif
                        </div>
                        <div class="master-info is-wide">
                            <span>Contact</span>
                            @if (filled($invoice->client_contact_name) || filled($invoice->client_email) || filled($invoice->client_mobile))
                                <strong>{{ $invoice->client_contact_name ?: 'Client contact' }}</strong>
                                <span class="master-address-sub">
                                    @if (filled($invoice->client_email))<b>Email</b> {{ $invoice->client_email }}@endif
                                    @if (filled($invoice->client_email) && filled($invoice->client_mobile)) · @endif
                                    @if (filled($invoice->client_mobile))<b>Mobile</b> {{ $invoice->client_mobile }}@endif
                                </span>
                            @else
                                <span class="master-empty-value">No contact on file</span>
                            @endif
                        </div>
                        <div class="master-info is-wide">
                            <span>Billing address</span>
                            @if ($billingAddress !== '')
                                <strong>{{ $billingAddress }}</strong>
                            @else
                                <span class="master-empty-value">No billing address</span>
                            @endif
                        </div>
                        <div class="master-info is-wide">
                            <span>Shipping address</span>
                            @if ($shippingAddress === '')
                                <span class="master-empty-value">No shipping address</span>
                            @elseif ($shippingAddress === $billingAddress)
                                <strong>Same as the billing address</strong>
                            @else
                                <strong>{{ $shippingAddress }}</strong>
                                @if (filled($invoice->transport_mode))
                                    <span class="master-address-sub"><b>Transport</b> {{ $invoice->transport_mode }}</span>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- What the invoice adds up to, head by head. A head the invoice
                 does not charge is said in words rather than printed as ₹0.00:
                 a money column full of zeroes hides the two rows that matter. --}}
            <div>
                <div class="master-card master-section">
                    <div class="master-section-head">
                        <div>
                            <h2 class="master-section-title">Summary</h2>
                            <p class="master-sub">
                                {{ $invoice->gstTypeLabel() }}@if ($invoice->currency !== 'INR') · {{ $invoice->currency }}@endif
                            </p>
                        </div>
                        @if ($invoice->valid_until)
                            <div class="master-section-meta">
                                <span class="master-chip">
                                    <i class="fas fa-hourglass-half" aria-hidden="true"></i>
                                    Valid until {{ $invoice->valid_until->format('d M Y') }}
                                </span>
                            </div>
                        @endif
                    </div>

                    <div class="si-total-row">
                        <span>Subtotal</span>
                        <strong>{{ \App\Helpers\CommonHelper::indianCurrency($invoice->subtotal) }}</strong>
                    </div>
                    <div class="si-total-row">
                        <span>Discount{{ $invoice->discount_type === 'percent' && (float) $invoice->discount_value > 0 ? ' ('.$figure($invoice->discount_value).'%)' : '' }}</span>
                        @if ((float) $invoice->discount_amount > 0)
                            <strong>− {{ \App\Helpers\CommonHelper::indianCurrency($invoice->discount_amount) }}</strong>
                        @else
                            <span class="master-empty-value">None</span>
                        @endif
                    </div>

                    @if ((float) $invoice->cgst_amount > 0)
                        <div class="si-total-row">
                            <span>CGST</span>
                            <strong>{{ \App\Helpers\CommonHelper::indianCurrency($invoice->cgst_amount) }}</strong>
                        </div>
                    @endif
                    @if ((float) $invoice->sgst_amount > 0)
                        <div class="si-total-row">
                            <span>SGST</span>
                            <strong>{{ \App\Helpers\CommonHelper::indianCurrency($invoice->sgst_amount) }}</strong>
                        </div>
                    @endif
                    @if ((float) $invoice->igst_amount > 0)
                        <div class="si-total-row">
                            <span>IGST</span>
                            <strong>{{ \App\Helpers\CommonHelper::indianCurrency($invoice->igst_amount) }}</strong>
                        </div>
                    @endif
                    @if ($taxAmount <= 0)
                        <div class="si-total-row">
                            <span>GST</span>
                            <span class="master-empty-value">No tax charged</span>
                        </div>
                    @endif

                    @if ($chargesAmount > 0)
                        @if ((float) $invoice->freight_amount > 0)
                            <div class="si-total-row">
                                <span>Freight</span>
                                <strong>{{ \App\Helpers\CommonHelper::indianCurrency($invoice->freight_amount) }}</strong>
                            </div>
                        @endif
                        @if ((float) $invoice->packing_amount > 0)
                            <div class="si-total-row">
                                <span>Packing</span>
                                <strong>{{ \App\Helpers\CommonHelper::indianCurrency($invoice->packing_amount) }}</strong>
                            </div>
                        @endif
                        @if ((float) $invoice->other_charges > 0)
                            <div class="si-total-row">
                                <span>Other charges</span>
                                <strong>{{ \App\Helpers\CommonHelper::indianCurrency($invoice->other_charges) }}</strong>
                            </div>
                        @endif
                    @else
                        <div class="si-total-row">
                            <span>Freight, packing &amp; other</span>
                            <span class="master-empty-value">None</span>
                        </div>
                    @endif

                    @if ((float) $invoice->round_off != 0)
                        <div class="si-total-row">
                            <span>Round off</span>
                            <strong>{{ \App\Helpers\CommonHelper::indianCurrency($invoice->round_off) }}</strong>
                        </div>
                    @endif

                    <div class="si-total-row is-grand">
                        <span>Grand total</span>
                        <strong>{{ \App\Helpers\CommonHelper::indianCurrency($invoice->total_amount) }}</strong>
                    </div>

                    @if (filled($invoice->amount_in_words))
                        <p class="si-words">{{ $invoice->amount_in_words }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="master-grid is-even">
            {{-- The receipts, as the ledger booked them, and what is still open.
                 The receipt is recordable from here — this is the page the
                 accountant is on when the client rings about this invoice. --}}
            <div>
                <div class="master-card master-section">
                    <div class="master-section-head">
                        <div>
                            <h2 class="master-section-title">Payments</h2>
                            <p class="master-sub">Every receipt booked against this invoice, newest first</p>
                        </div>
                        @if ($balanceAmount > 0)
                            <div class="master-section-meta">
                                <button type="button" class="master-btn master-btn-soft" data-open-payment
                                    data-invoice-id="{{ $invoice->id }}"
                                    data-invoice-number="{{ $invoice->invoice_number }}"
                                    data-invoice-amount="{{ number_format($balanceAmount, 2, '.', '') }}"
                                    data-invoice-balance="{{ \App\Helpers\CommonHelper::indianCurrency($balanceAmount) }}">
                                    <i class="fas fa-plus" aria-hidden="true"></i> Record
                                </button>
                            </div>
                        @endif
                    </div>

                    @if ($invoice->isSuperseded())
                        <div class="master-empty-state">
                            <i class="fas fa-arrow-right-arrow-left"></i>
                            <p>This proforma became {{ $invoice->convertedInvoice?->invoice_number }}. The advance and every receipt filed against it moved to that tax invoice — one document, one claim on the money.</p>
                            <a href="{{ route('sales-invoices.show', $invoice->convertedInvoice) }}"
                                class="master-btn master-btn-light">Open the tax invoice</a>
                        </div>
                    @elseif ($receipts->isEmpty())
                        <div class="master-empty-state">
                            <i class="fas fa-receipt"></i>
                            <p>Nothing received against this invoice yet. Book the receipt here and the ledger, the client statement and this balance all read the same number.</p>
                            @if ($balanceAmount > 0)
                                <button type="button" class="master-btn master-btn-light" data-open-payment
                                    data-invoice-id="{{ $invoice->id }}"
                                    data-invoice-number="{{ $invoice->invoice_number }}"
                                    data-invoice-amount="{{ number_format($balanceAmount, 2, '.', '') }}"
                                    data-invoice-balance="{{ \App\Helpers\CommonHelper::indianCurrency($balanceAmount) }}">
                                    Record payment
                                </button>
                            @endif
                        </div>
                    @else
                        <div class="si-payment-list">
                            @foreach ($receipts as $payment)
                                @php
                                    $netReceipt = (float) $payment->credit_amount - (float) $payment->debit_amount;
                                @endphp
                                <div class="si-payment">
                                    <span class="si-payment-icon" aria-hidden="true">
                                        <i class="fas fa-{{ $netReceipt < 0 ? 'arrow-rotate-left' : 'indian-rupee-sign' }}"></i>
                                    </span>
                                    <div class="si-payment-main">
                                        <strong>{{ $netReceipt < 0 ? 'Refunded' : 'Received' }} {{ $payment->entry_date ? $payment->entry_date->format('d M Y') : '' }}</strong>
                                        <span class="master-sub">
                                            {{ $payment->payment_mode ? strtoupper($payment->payment_mode) : 'Mode not recorded' }}
                                            @if (filled($payment->particular)) · {{ \Illuminate\Support\Str::limit($payment->particular, 70) }} @endif
                                        </span>
                                        @if (filled($payment->bank_reference_number) || filled($payment->invoice_bill_number))
                                            <span class="master-sub">
                                                @if (filled($payment->invoice_bill_number))<b>Bill</b> {{ $payment->invoice_bill_number }} @endif
                                                @if (filled($payment->invoice_bill_number) && filled($payment->bank_reference_number)) · @endif
                                                @if (filled($payment->bank_reference_number))<b>Ref</b> {{ $payment->bank_reference_number }}@endif
                                            </span>
                                        @endif
                                        {{-- The receipt is a ledger entry, and the office
                                             reconciles against the ledger: the row names
                                             the entry it came from and opens it. --}}
                                        <a class="si-payment-ref" href="{{ route('cashflows.show', $payment) }}">
                                            <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i>
                                            Cashflow entry #{{ $payment->id }}
                                        </a>
                                    </div>
                                    <strong class="si-payment-amount {{ $netReceipt < 0 ? 'si-due' : 'si-clear' }}">
                                        {{ \App\Helpers\CommonHelper::indianCurrency($netReceipt) }}
                                    </strong>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="si-pay-foot">
                        <div>
                            <span>Received</span>
                            <strong class="si-clear">{{ \App\Helpers\CommonHelper::indianCurrency($receivedAmount) }}</strong>
                        </div>
                        <div>
                            <span>Still open</span>
                            <strong class="{{ $balanceAmount > 0 ? 'si-due' : 'si-clear' }}">
                                {{ \App\Helpers\CommonHelper::indianCurrency($balanceAmount) }}
                            </strong>
                        </div>
                    </div>
                </div>
            </div>

            {{-- The chase: when the office last asked for this money, how, and
                 what came back. An empty log says so and offers the next step
                 rather than printing a bare zero. --}}
            <div>
                <div class="master-card master-section">
                    <div class="master-section-head">
                        <div>
                            <h2 class="master-section-title">Chase</h2>
                            <p class="master-sub">
                                @if ($invoice->lastRemindedAt())
                                    Last asked {{ $invoice->lastRemindedAt()->format('d M Y') }} · asked {{ $invoice->reminderCount() }}×
                                @else
                                    Nobody has asked for this money yet
                                @endif
                            </p>
                        </div>
                        <div class="master-section-meta">
                            <button type="button" class="master-btn master-btn-soft" data-open-reminder
                                data-invoice-id="{{ $invoice->id }}"
                                data-invoice-number="{{ $invoice->invoice_number }}"
                                data-invoice-message="{{ $invoice->reminderMessage() }}">
                                <i class="fas fa-bell" aria-hidden="true"></i> Log reminder
                            </button>
                        </div>
                    </div>

                    @if ($invoice->reminders->isEmpty())
                        <div class="master-empty-state">
                            <i class="fas fa-bell-slash"></i>
                            <p>
                                Nobody has asked for this money yet.
                                @if ($balanceAmount > 0)
                                    Ring, WhatsApp or email the client, then log it here — the log is what the next person reads.
                                @else
                                    The invoice is settled, so there is nothing to chase.
                                @endif
                            </p>
                        </div>
                    @else
                        <div class="si-reminder-list">
                            @foreach ($invoice->reminders as $reminder)
                                <div class="si-reminder">
                                    <div class="si-reminder-when">
                                        <strong>{{ $reminder->reminded_at ? $reminder->reminded_at->format('d M Y') : '' }}</strong>
                                        <span class="master-sub">{{ $reminder->channelLabel() }}</span>
                                    </div>
                                    <div class="si-reminder-what">
                                        @if (filled($reminder->note))
                                            <strong>{{ $reminder->note }}</strong>
                                        @endif
                                        <span class="master-sub">
                                            {{ $reminder->creator?->name ? 'by '.$reminder->creator->name : 'logged' }}
                                            @if (filled($reminder->message)) · {{ \Illuminate\Support\Str::limit($reminder->message, 90) }} @endif
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="master-grid is-even">
            {{-- The terms the client is told, and the two notes: the one that
                 prints and the one that never leaves the office. --}}
            <div>
                <div class="master-card master-section">
                    <div class="master-section-head">
                        <div>
                            <h2 class="master-section-title">Terms &amp; notes</h2>
                            <p class="master-sub">What the client is told, and what stays in the office</p>
                        </div>
                        <div class="master-section-meta">
                            <a href="{{ route('sales-invoices.edit', $invoice) }}" class="master-btn master-btn-light">
                                <i class="fas fa-pen" aria-hidden="true"></i> Edit
                            </a>
                        </div>
                    </div>

                    @php
                        $hasTerms = filled($invoice->payment_terms) || filled($invoice->delivery_terms) || filled($invoice->dispatch_terms) || filled($invoice->terms_conditions);
                    @endphp

                    @if ($hasTerms)
                        <div class="master-facts">
                            @if (filled($invoice->payment_terms))
                                <div class="master-info">
                                    <span>Payment terms</span>
                                    <strong>{{ $invoice->payment_terms }}</strong>
                                </div>
                            @endif
                            @if (filled($invoice->delivery_terms))
                                <div class="master-info">
                                    <span>Delivery</span>
                                    <strong>{{ $invoice->delivery_terms }}</strong>
                                </div>
                            @endif
                            @if (filled($invoice->dispatch_terms))
                                <div class="master-info">
                                    <span>Dispatch</span>
                                    <strong>{{ $invoice->dispatch_terms }}</strong>
                                </div>
                            @endif
                            @if (filled($invoice->transport_mode))
                                <div class="master-info">
                                    <span>Transport</span>
                                    <strong>{{ $invoice->transport_mode }}</strong>
                                </div>
                            @endif
                            @if (filled($invoice->sales_person))
                                <div class="master-info">
                                    <span>Sales person</span>
                                    <strong>{{ $invoice->sales_person }}</strong>
                                </div>
                            @endif
                            @if (filled($invoice->terms_conditions))
                                <div class="master-info is-wide">
                                    <span>Terms &amp; conditions</span>
                                    <strong>{!! nl2br(e($invoice->terms_conditions)) !!}</strong>
                                </div>
                            @endif
                        </div>
                    @endif

                    @if (filled($invoice->notes) || filled($invoice->internal_notes))
                        <div class="si-note-list">
                            @if (filled($invoice->notes))
                                <div class="si-note">
                                    <span class="master-sub">On the invoice — the client may see it</span>
                                    <p>{!! nl2br(e($invoice->notes)) !!}</p>
                                </div>
                            @endif
                            @if (filled($invoice->internal_notes))
                                <div class="si-note is-internal">
                                    <span class="master-sub">Internal — never printed, never on the portal</span>
                                    <p>{!! nl2br(e($invoice->internal_notes)) !!}</p>
                                </div>
                            @endif
                        </div>
                    @endif

                    @if (! $hasTerms && blank($invoice->notes) && blank($invoice->internal_notes))
                        <div class="master-empty-state">
                            <i class="fas fa-note-sticky"></i>
                            <p>No terms and no notes on this invoice. The office fills them in on <strong>Edit</strong> — they are what prints on the copy the client keeps.</p>
                            <a href="{{ route('sales-invoices.edit', $invoice) }}" class="master-btn master-btn-light">Add terms</a>
                        </div>
                    @endif
                </div>
            </div>

            {{-- The files filed with this invoice. Each row says what the file
                 is, how big it is and whether the client can see it — the icon
                 and the size are the model's own reading of the file. --}}
            <div>
                <div class="master-card master-section">
                    <div class="master-section-head">
                        <div>
                            <h2 class="master-section-title">Files</h2>
                            <p class="master-sub">{{ $invoice->attachments->count() }} {{ \Illuminate\Support\Str::plural('file', $invoice->attachments->count()) }} filed with this invoice</p>
                        </div>
                        <div class="master-section-meta">
                            <a href="{{ route('sales-invoices.edit', $invoice) }}" class="master-btn master-btn-light">
                                <i class="fas fa-paperclip" aria-hidden="true"></i> Manage files
                            </a>
                        </div>
                    </div>

                    @if ($invoice->attachments->isEmpty())
                        <div class="master-empty-state">
                            <i class="fas fa-folder-open"></i>
                            <p>No files filed with this invoice. Attach the signed copy, the e-way bill or the payment advice from <strong>Edit</strong>.</p>
                            <a href="{{ route('sales-invoices.edit', $invoice) }}" class="master-btn master-btn-light">Add a file</a>
                        </div>
                    @else
                        <div class="si-file-list">
                            @foreach ($invoice->attachments as $attachment)
                                <div class="si-file">
                                    <span class="si-file-icon" aria-hidden="true"><i class="fas {{ $attachment->icon() }}"></i></span>
                                    <div class="si-file-main">
                                        <strong>{{ $attachment->title ?: ($attachment->original_name ?: 'File') }}</strong>
                                        <span class="master-sub">
                                            {{ $attachment->extension ? strtoupper($attachment->extension) : 'File' }}
                                            @if ((int) $attachment->file_size > 0) · {{ $attachment->sizeLabel() }} @endif
                                        </span>
                                    </div>
                                    <span class="si-portal {{ $attachment->is_public ? 'is-public' : 'is-private' }}">
                                        {{ $attachment->is_public ? 'Client can see it' : 'Internal' }}
                                    </span>
                                    <a href="{{ $attachment->fileUrl() }}" target="_blank" rel="noopener"
                                        class="master-btn master-btn-light">
                                        <i class="fas fa-up-right-from-square" aria-hidden="true"></i> Open
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
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
