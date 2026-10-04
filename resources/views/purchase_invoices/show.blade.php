@extends('layouts.app')

@section('title', $invoice->invoice_number)
@section('page-title', $invoice->invoice_number)

@section('page-actions')
    <a class="master-btn master-btn-light" href="{{ route($invoice->routePrefix().'.index') }}">
        <i class="fas fa-arrow-left" aria-hidden="true"></i> All {{ $invoice->isOrder() ? 'orders' : 'bills' }}
    </a>
    <a class="master-btn master-btn-light" href="{{ route($invoice->routePrefix().'.edit', $invoice) }}">
        <i class="fas fa-pen" aria-hidden="true"></i> Edit
    </a>
    @if ($invoice->isOrder() && $invoice->canConvert())
        <form method="POST" action="{{ route('purchase-orders.convert', $invoice) }}" class="inline-form"
            data-confirm="Raise a purchase bill from {{ $invoice->invoice_number }}? Its lines, rates and terms are copied onto the bill, and this order is then closed to further billing."
            data-confirm-title="Convert to purchase bill" data-confirm-text="Raise bill" data-confirm-danger="0">
            @csrf
            <button type="submit" class="master-btn master-btn-primary">
                <i class="fas fa-file-invoice" aria-hidden="true"></i> Convert to purchase bill
            </button>
        </form>
    @endif
    @if ($invoice->isBill() && $billBalance > 0)
        <button type="button" class="master-btn master-btn-primary" data-open-payment
            data-invoice-id="{{ $invoice->id }}"
            data-invoice-number="{{ $invoice->invoice_number }}"
            data-invoice-currency="{{ $invoice->currency }}"
            data-invoice-rate="{{ (float) ($invoice->currency === 'INR' ? 1 : ($invoice->exchange_rate ?: 1)) }}"
            data-invoice-balance-figure="{{ number_format($billBalance, 2, '.', '') }}"
            data-invoice-balance="{{ \App\Helpers\CommonHelper::amount($billBalance, $invoice->currency) }}">
            <i class="fas fa-indian-rupee-sign" aria-hidden="true"></i> Record payment
        </button>
    @endif
@endsection

@section('content')
@php
    $isOrder = $invoice->isOrder();
    $prefix = $invoice->routePrefix();
    $docTitle = $isOrder ? 'Purchase Order' : 'Purchase Bill';
    $stateKey = $invoice->stateKey();
    $paidAmount = $invoice->paidAmount();
    $dueAmount = $invoice->balanceDue();
    $daysLate = $invoice->daysOverdue();
    $money = fn ($amount) => \App\Helpers\CommonHelper::amount($amount, $invoice->currency);
    $figure = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.') ?: '0';
@endphp

<div class="master-list pi-show">
    {{-- The header answers three questions before anybody clicks: what this is,
         where the money stands, and what can be done with it. --}}
    <header class="master-card master-header">
        <div class="record-head-main">
            <h1>{{ $invoice->invoice_number }}</h1>
            <p class="master-sub pi-show-sub">
                {{ $invoice->typeLabel() }}
                @if (filled($invoice->vendor_company_name)) · {{ $invoice->vendor_company_name }} @endif
                @if ($invoice->invoice_date) · {{ $invoice->invoice_date->format('d M Y') }} @endif
            </p>
            <div class="record-head-chips">
                <span class="pi-status status-{{ $stateKey }}">{{ $invoice->stateLabel() }}</span>

                @if ($isOrder)
                    @if ($invoice->expected_date)
                        <span class="master-chip">
                            <i class="fas fa-truck-fast" aria-hidden="true"></i>
                            Expected {{ $invoice->expected_date->format('d M Y') }}
                        </span>
                    @endif
                    @if ($invoice->valid_until)
                        <span class="master-chip">
                            <i class="fas fa-hourglass-half" aria-hidden="true"></i>
                            Valid until {{ $invoice->valid_until->format('d M Y') }}
                        </span>
                    @endif
                    @if ($invoice->isSuperseded())
                        <a class="master-chip pi-chip-converted" href="{{ route('purchase-bills.show', $invoice->convertedInvoice) }}">
                            <i class="fas fa-arrow-right-arrow-left" aria-hidden="true"></i>
                            Billed as {{ $invoice->convertedInvoice?->invoice_number }}
                        </a>
                    @endif
                @else
                    @if ($invoice->due_date)
                        <span class="master-chip {{ $dueAmount > 0 && $daysLate > 0 ? 'pi-chip-late' : '' }}">
                            <i class="fas fa-calendar-day" aria-hidden="true"></i>
                            @if ($dueAmount > 0 && $daysLate > 0)
                                {{ $daysLate }} {{ \Illuminate\Support\Str::plural('day', $daysLate) }} past due — {{ $invoice->due_date->format('d M Y') }}
                            @else
                                Due {{ $invoice->due_date->format('d M Y') }}
                            @endif
                        </span>
                    @endif
                    @if ($invoice->vendor_bill_number)
                        <span class="master-chip">
                            <i class="fas fa-file-invoice" aria-hidden="true"></i>
                            Vendor bill {{ $invoice->vendor_bill_number }}@if ($invoice->vendor_bill_date) · {{ $invoice->vendor_bill_date->format('d M Y') }}@endif
                        </span>
                    @endif
                    @if ($invoice->purchaseOrder)
                        <a class="master-chip pi-chip-converted" href="{{ route('purchase-orders.show', $invoice->purchaseOrder) }}">
                            <i class="fas fa-file-signature" aria-hidden="true"></i>
                            From order {{ $invoice->purchaseOrder->invoice_number }}
                        </a>
                    @endif
                @endif

                @if ($invoice->our_reference)
                    <span class="master-chip">
                        <i class="fas fa-hashtag" aria-hidden="true"></i> Ref {{ $invoice->our_reference }}
                    </span>
                @endif

                <span class="pi-public is-public">
                    <i class="fas fa-link" aria-hidden="true"></i> Print link live
                </span>
            </div>
        </div>

        <div class="record-head-actions">
            <a href="{{ route($prefix.'.print', $invoice) }}" target="_blank" class="master-btn master-btn-soft">
                <i class="fas fa-print" aria-hidden="true"></i> Print
            </a>
            <button type="button" class="master-btn master-btn-soft"
                data-copy-link="{{ route('purchase-invoices.public', $invoice->public_token) }}">
                <i class="fas fa-link" aria-hidden="true"></i> Copy print link
            </button>

            @if ($stateKey === 'draft')
                <form method="POST" action="{{ route($prefix.'.status', $invoice) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="{{ $isOrder ? 'sent' : 'received' }}">
                    <button type="submit" class="master-btn master-btn-soft">
                        <i class="fas fa-paper-plane" aria-hidden="true"></i> {{ $isOrder ? 'Mark sent' : 'Mark received' }}
                    </button>
                </form>
            @elseif ($isOrder && $stateKey === 'sent')
                <form method="POST" action="{{ route($prefix.'.status', $invoice) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="approved">
                    <button type="submit" class="master-btn master-btn-soft">
                        <i class="fas fa-circle-check" aria-hidden="true"></i> Approve
                    </button>
                </form>
            @endif

            @if (in_array($stateKey, ['draft', 'sent', 'approved', 'received'], true))
                <form method="POST" action="{{ route($prefix.'.status', $invoice) }}"
                    data-confirm="Cancel {{ $invoice->invoice_number }}? A cancelled document is not owed and stands for nothing."
                    data-confirm-title="Cancel this {{ $noun }}" data-confirm-text="Mark cancelled" data-confirm-danger="0">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="cancelled">
                    <button type="submit" class="master-btn master-btn-light">
                        <i class="fas fa-ban" aria-hidden="true"></i> Cancel
                    </button>
                </form>
            @endif
        </div>
    </header>

    {{-- The figures: what the document is worth, and what the ledger says. --}}
    <div class="master-stats">
        <div class="master-stat master-stat--flat blue">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-indian-rupee-sign"></i></span>
            <div>
                <p class="master-stat-title">{{ $isOrder ? 'Order value' : 'Bill total' }}</p>
                <p class="master-stat-value">{{ $money($invoice->total_amount) }}</p>
                <p class="master-sub">{{ $invoice->items->count() }} {{ \Illuminate\Support\Str::plural('line', $invoice->items->count()) }}{{ $invoice->taxable_amount ? ' · taxable '.$money($invoice->taxable_amount) : '' }}</p>
            </div>
        </div>

        @if ($isOrder)
            <div class="master-stat master-stat--flat {{ $invoice->isSuperseded() ? 'teal' : 'purple' }}">
                <span class="icon" aria-hidden="true"><i class="fa-solid fa-file-invoice"></i></span>
                <div>
                    <p class="master-stat-title">Billed against this order</p>
                    <p class="master-stat-value">{{ $money($invoice->billedAmount()) }}</p>
                    <p class="master-sub">
                        @if ($invoice->isSuperseded())
                            Billed in full as {{ $invoice->convertedInvoice?->invoice_number }}
                        @else
                            Nothing billed yet — raise the bill from this order
                        @endif
                    </p>
                </div>
            </div>
        @else
            <div class="master-stat master-stat--flat teal">
                <span class="icon" aria-hidden="true"><i class="fa-solid fa-arrow-up-right-dots"></i></span>
                <div>
                    <p class="master-stat-title">Paid</p>
                    <p class="master-stat-value">{{ $money($paidAmount) }}</p>
                    <p class="master-sub">
                        @if ($payments->count())
                            {{ $payments->count() }} {{ \Illuminate\Support\Str::plural('payment', $payments->count()) }} in the vendor ledger
                        @elseif ((float) $invoice->amount_paid > 0)
                            Opening figure only
                        @else
                            Nothing paid yet
                        @endif
                    </p>
                </div>
            </div>
            <div class="master-stat master-stat--flat {{ $dueAmount > 0 ? 'red' : 'teal' }}">
                <span class="icon" aria-hidden="true"><i class="fa-solid fa-scale-balanced"></i></span>
                <div>
                    <p class="master-stat-title">Balance payable</p>
                    <p class="master-stat-value">{{ $money($dueAmount) }}</p>
                    <p class="master-sub">
                        @if ($dueAmount <= 0)
                            Settled in full
                        @elseif ($daysLate > 0)
                            {{ $daysLate }} {{ \Illuminate\Support\Str::plural('day', $daysLate) }} past the due date
                        @elseif ($invoice->due_date)
                            Due {{ $invoice->due_date->format('d M Y') }}
                        @else
                            No due date set
                        @endif
                    </p>
                </div>
            </div>
        @endif
    </div>

    {{-- The lines. On a bill, the price is what the vendor charged; on an order,
         what we agreed to pay. --}}
    <div class="master-card master-section">
        <div class="master-section-head">
            <div>
                <h2 class="master-section-title">{{ $isOrder ? 'Items ordered' : 'Items billed' }}</h2>
                <p class="master-sub">{{ $invoice->gstTypeLabel() }}@if ($invoice->currency !== 'INR') · billed in {{ $invoice->currency }} at {{ $figure($invoice->exchange_rate) }}@endif</p>
            </div>
        </div>

        <div class="pi-items master-table-wrap">
            <table class="master-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="is-num ui-mobile-secondary">HSN/SAC</th>
                        <th class="is-num">Qty</th>
                        <th class="is-num">Rate</th>
                        <th class="is-num ui-mobile-secondary">Disc</th>
                        <th class="is-num ui-mobile-secondary">GST</th>
                        <th class="is-num">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoice->items as $item)
                        <tr>
                            <td data-label="Item">
                                <strong>{{ $item->product_name }}</strong>
                                @if (filled($item->description))
                                    <span class="master-sub">{{ $item->description }}</span>
                                @endif
                                @if ($item->remarks)
                                    <span class="master-sub">{{ $item->remarks }}</span>
                                @endif
                            </td>
                            <td data-label="HSN/SAC" class="is-num ui-mobile-secondary">{{ $item->hsn_sac ?: '—' }}</td>
                            <td data-label="Qty" class="is-num">{{ $figure($item->quantity) }} <span class="master-sub">{{ $item->unit }}</span></td>
                            <td data-label="Rate" class="is-num">{{ $money($item->unit_price) }}</td>
                            <td data-label="Disc" class="is-num ui-mobile-secondary">
                                {{ (float) $item->discount_percent > 0 ? $figure($item->discount_percent).'%' : '—' }}
                                @if ((float) $item->discount_amount > 0)
                                    <span class="master-sub">− {{ $money($item->discount_amount) }}</span>
                                @endif
                            </td>
                            <td data-label="GST" class="is-num ui-mobile-secondary">{{ $figure($item->gst_percent) }}%</td>
                            <td data-label="Amount" class="is-num"><strong>{{ $money($item->line_total) }}</strong></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><p class="master-empty-value">No lines on this document yet.</p></td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="6" class="is-num"><span class="master-sub">Total</span></td>
                        <td class="is-num"><strong>{{ $money($invoice->total_amount) }}</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        @if (filled($invoice->amount_in_words))
            <p class="pi-words">{{ $invoice->amount_in_words }}</p>
        @endif
    </div>

    <div class="master-grid is-even">
        <div class="master-card master-section">
            <div class="master-section-head">
                <div>
                    <h2 class="master-section-title">Summary</h2>
                    <p class="master-sub">{{ $invoice->gstTypeLabel() }}@if ($invoice->place_of_supply) · supply from {{ $invoice->place_of_supply }}@endif</p>
                </div>
            </div>

            <div class="pi-total-row">
                <span>Subtotal</span><strong>{{ $money($invoice->subtotal) }}</strong>
            </div>
            <div class="pi-total-row">
                <span>Discount{{ $invoice->discount_type === 'percent' && (float) $invoice->discount_value > 0 ? ' ('.$figure($invoice->discount_value).'%)' : '' }}</span>
                @if ((float) $invoice->discount_amount > 0)
                    <strong>− {{ $money($invoice->discount_amount) }}</strong>
                @else
                    <span class="master-empty-value">None</span>
                @endif
            </div>
            <div class="pi-total-row">
                <span>Taxable value</span><strong>{{ $money($invoice->taxable_amount) }}</strong>
            </div>
            @if ((float) $invoice->cgst_amount > 0)
                <div class="pi-total-row"><span>CGST</span><strong>{{ $money($invoice->cgst_amount) }}</strong></div>
            @endif
            @if ((float) $invoice->sgst_amount > 0)
                <div class="pi-total-row"><span>SGST</span><strong>{{ $money($invoice->sgst_amount) }}</strong></div>
            @endif
            @if ((float) $invoice->igst_amount > 0)
                <div class="pi-total-row"><span>IGST</span><strong>{{ $money($invoice->igst_amount) }}</strong></div>
            @endif
            @if ((float) $invoice->freight_amount > 0)
                <div class="pi-total-row"><span>Freight</span><strong>{{ $money($invoice->freight_amount) }}</strong></div>
            @endif
            @if ((float) $invoice->packing_amount > 0)
                <div class="pi-total-row"><span>Packing</span><strong>{{ $money($invoice->packing_amount) }}</strong></div>
            @endif
            @if ((float) $invoice->other_charges > 0)
                <div class="pi-total-row"><span>Other charges</span><strong>{{ $money($invoice->other_charges) }}</strong></div>
            @endif
            @if ((float) $invoice->round_off != 0)
                <div class="pi-total-row"><span>Round off</span><strong>{{ $money($invoice->round_off) }}</strong></div>
            @endif
            <div class="pi-total-row is-grand">
                <span>{{ $docTitle }} total</span><strong>{{ $money($invoice->total_amount) }}</strong>
            </div>
            @if (! $isOrder)
                <div class="pi-total-row">
                    <span>Paid</span><strong>− {{ $money($paidAmount) }}</strong>
                </div>
                <div class="pi-total-row">
                    <span>Balance payable</span>
                    <strong class="{{ $dueAmount > 0 ? 'pi-due' : 'pi-clear' }}">{{ $money($dueAmount) }}</strong>
                </div>
            @endif
        </div>

        <div>
            <div class="master-card master-section">
                <div class="master-section-head">
                    <div>
                        <h2 class="master-section-title">{{ $isOrder ? 'Ordered from' : 'Billed by' }}</h2>
                        <p class="master-sub">The vendor as this document was raised</p>
                    </div>
                    @if ($invoice->vendor)
                        <div class="master-section-meta">
                            <a class="master-btn master-btn-light master-btn-sm"
                                href="{{ route('vendors.show', $invoice->vendor) }}">
                                <i class="fas fa-user-gear" aria-hidden="true"></i> Open vendor
                            </a>
                        </div>
                    @endif
                </div>

                <div class="master-detail-grid">
                    <div class="master-info"><span>Name</span><strong>{{ $invoice->vendor_company_name ?: 'No vendor' }}</strong></div>
                    <div class="master-info"><span>Contact</span><strong>{{ $invoice->vendor_contact_name ?: '—' }}</strong></div>
                    <div class="master-info"><span>Email</span><strong>{{ $invoice->vendor_email ?: '—' }}</strong></div>
                    <div class="master-info"><span>Mobile</span><strong>{{ $invoice->vendor_mobile ?: '—' }}</strong></div>
                    <div class="master-info"><span>GSTIN</span><strong>{{ $invoice->vendor_gstin ?: '—' }}</strong></div>
                    <div class="master-info"><span>PAN</span><strong>{{ $invoice->vendor_pan ?: '—' }}</strong></div>
                </div>

                <p class="pi-address">
                    {{ collect([$invoice->vendor_address, $invoice->vendor_city, $invoice->vendor_state, $invoice->vendor_country, $invoice->vendor_pincode])->filter()->implode(', ') ?: 'No address on this document.' }}
                </p>
            </div>

            <div class="master-card master-section">
                <div class="master-section-head">
                    <div>
                        <h2 class="master-section-title">Bill to</h2>
                        <p class="master-sub">This company, as the buyer on the document</p>
                    </div>
                </div>

                <div class="master-detail-grid">
                    <div class="master-info"><span>Name</span><strong>{{ $invoice->buyer_company_name ?: 'MissPack India Pvt Ltd' }}</strong></div>
                    <div class="master-info"><span>GSTIN</span><strong>{{ $invoice->buyer_gstin ?: '—' }}</strong></div>
                    <div class="master-info"><span>PAN</span><strong>{{ $invoice->buyer_pan ?: '—' }}</strong></div>
                    <div class="master-info"><span>Email</span><strong>{{ $invoice->buyer_email ?: '—' }}</strong></div>
                </div>

                <p class="pi-address">
                    {{ collect([$invoice->buyer_address, $invoice->buyer_city, $invoice->buyer_state, $invoice->buyer_country, $invoice->buyer_pincode])->filter()->implode(', ') ?: 'No address on this document.' }}
                </p>
            </div>
        </div>
    </div>

    @if (! $isOrder)
        {{-- The vendor ledger's own rows for this bill: the posting that made it a
             payable, and every payment that has been made against it. This is the
             money the office reconciles against the bank, so the record links to
             each entry rather than restating it. --}}
        @include('purchase_invoices.partials.ledger')
    @endif

    <div class="master-grid is-even">
        <div class="master-card master-section">
            <div class="master-section-head">
                <div>
                    <h2 class="master-section-title">Terms &amp; notes</h2>
                </div>
            </div>

            @if (filled($invoice->notes))
                <div class="pi-note">
                    <span class="master-label">Notes</span>
                    <p>{{ $invoice->notes }}</p>
                </div>
            @endif
            @if (filled($invoice->internal_notes))
                <div class="pi-note is-internal">
                    <span class="master-label">Internal</span>
                    <p>{{ $invoice->internal_notes }}</p>
                </div>
            @endif
            @if (filled($invoice->terms_conditions))
                <div class="pi-note">
                    <span class="master-label">Terms &amp; conditions</span>
                    <p class="pi-terms">{{ $invoice->terms_conditions }}</p>
                </div>
            @endif
            @if (blank($invoice->notes) && blank($invoice->internal_notes) && blank($invoice->terms_conditions))
                <p class="master-empty-value">No notes or terms on this document.</p>
            @endif
        </div>

        <div class="master-card master-section">
            <div class="master-section-head">
                <div>
                    <h2 class="master-section-title">Document</h2>
                    <p class="master-sub">Where this has been, and what it became</p>
                </div>
            </div>

            <div class="pi-total-row">
                <span>Document date</span><strong>{{ $invoice->invoice_date?->format('d M Y') ?: '—' }}</strong>
            </div>
            @if ($isOrder)
                <div class="pi-total-row">
                    <span>Expected</span><strong>{{ $invoice->expected_date?->format('d M Y') ?: '—' }}</strong>
                </div>
                <div class="pi-total-row">
                    <span>Valid until</span><strong>{{ $invoice->valid_until?->format('d M Y') ?: '—' }}</strong>
                </div>
                <div class="pi-total-row">
                    <span>Billed</span>
                    <strong>{{ $invoice->isSuperseded() ? $money($invoice->billedAmount()) : 'Nothing yet' }}</strong>
                </div>
            @else
                <div class="pi-total-row">
                    <span>Due date</span><strong>{{ $invoice->due_date?->format('d M Y') ?: '—' }}</strong>
                </div>
                <div class="pi-total-row">
                    <span>Vendor's bill</span><strong>{{ $invoice->vendor_bill_number ?: '—' }}{{ $invoice->vendor_bill_date ? ' · '.$invoice->vendor_bill_date->format('d M Y') : '' }}</strong>
                </div>
            @endif
            <div class="pi-total-row">
                <span>Sent to vendor</span><strong>{{ $invoice->sent_at?->format('d M Y') ?: 'Not sent' }}</strong>
            </div>
            @if ($isOrder)
                <div class="pi-total-row">
                    <span>Approved</span><strong>{{ $invoice->approved_at?->format('d M Y') ?: 'Not approved' }}</strong>
                </div>
            @else
                <div class="pi-total-row">
                    <span>Received</span><strong>{{ $invoice->received_at?->format('d M Y') ?: 'Not marked received' }}</strong>
                </div>
            @endif
            @if ($invoice->cancelled_at)
                <div class="pi-total-row">
                    <span>Cancelled</span><strong>{{ $invoice->cancelled_at->format('d M Y') }}</strong>
                </div>
            @endif

            <div class="master-actions">
                <a href="{{ route($prefix.'.print', $invoice) }}" target="_blank" class="master-btn master-btn-soft master-btn-sm">
                    <i class="fas fa-print" aria-hidden="true"></i> Print {{ $isOrder ? 'order' : 'bill' }}
                </a>
                <form method="POST" action="{{ route($prefix.'.destroy', $invoice) }}"
                    data-confirm="Delete {{ $invoice->invoice_number }}?{{ $isOrder
                        ? ($invoice->isSuperseded() ? ' It has become a bill, and a document that has been billed cannot be deleted.' : ' Nothing points at it, and the number is gone for good.')
                        : ' Its ledger posting is removed and the order it came from goes back to being an open order. A bill that has been paid against cannot be deleted until those payments are removed from the vendor ledger.' }}"
                    data-confirm-title="Delete this {{ $noun }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="master-btn master-btn-light master-btn-sm">
                        <i class="far fa-trash-alt" aria-hidden="true"></i> Delete
                    </button>
                </form>
            </div>
        </div>
    </div>

    @unless ($isOrder)
        @include('purchase_invoices.partials.payment-modal')
    @endunless
</div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/purchase-invoices.css') }}">
@endpush

@push('scripts')
    <script src="{{ $assetVer('assets/js/purchase-invoices.js') }}"></script>
@endpush
