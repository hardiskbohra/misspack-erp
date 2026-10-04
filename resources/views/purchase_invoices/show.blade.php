@extends('layouts.app')

@section('page-title', $invoice->invoice_number)

@section('content')
@php
    $paidAmount = $invoice->paidAmount();
    $balanceAmount = $invoice->balanceDue();
    $taxAmount = (float) $invoice->cgst_amount + (float) $invoice->sgst_amount + (float) $invoice->igst_amount;
    $chargesAmount = (float) $invoice->freight_amount + (float) $invoice->packing_amount + (float) $invoice->other_charges;
    $stateKey = $invoice->stateKey();
    $daysLate = $invoice->daysOverdue();
    $nextStatuses = \App\Models\PurchaseInvoice::STATUS_FLOW[$invoice->invoice_type][$invoice->status] ?? [];
    $figure = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.') ?: '0';
    $addressLine = fn (...$parts) => implode(', ', array_values(array_filter(
        array_map(fn ($part) => trim((string) $part), $parts),
        fn ($part) => $part !== ''
    )));
    $vendorAddress = $addressLine(
        $invoice->vendor_address,
        trim($invoice->vendor_city.($invoice->vendor_pincode ? ' - '.$invoice->vendor_pincode : '')),
        $invoice->vendor_state,
        $invoice->vendor_country
    );
@endphp
<div class="master-list si-show">
    <header class="master-card master-header">
        <div class="record-head-main">
            <h1>{{ $invoice->invoice_number }}</h1>
            <p class="master-sub si-show-sub">
                {{ $invoice->typeLabel() }}
                @if (filled($invoice->vendor_company_name)) · {{ $invoice->vendor_company_name }} @endif
                @if ($invoice->invoice_date) · {{ $invoice->invoice_date->format('d M Y') }} @endif
            </p>
            <div class="record-head-chips">
                <span class="si-status status-{{ $stateKey }}">{{ $invoice->stateLabel() }}</span>
                @if ($invoice->due_date)
                    <span class="master-chip {{ $balanceAmount > 0 && $daysLate > 0 ? 'si-chip-late' : '' }}">
                        Due {{ $invoice->due_date->format('d M Y') }}
                    </span>
                @endif
                @if ($invoice->isSuperseded())
                    <a class="master-chip" href="{{ route('purchase-bills.show', $invoice->convertedInvoice) }}">
                        Became {{ $invoice->convertedInvoice?->invoice_number }}
                    </a>
                @endif
                @if ($invoice->purchaseOrder)
                    <a class="master-chip" href="{{ route('purchase-orders.show', $invoice->purchaseOrder) }}">
                        From {{ $invoice->purchaseOrder->invoice_number }}
                    </a>
                @endif
            </div>
        </div>
        <div class="record-head-actions">
            <a href="{{ route($routePrefix.'.index') }}" class="master-btn master-btn-light">Back</a>
            <a href="{{ route($routePrefix.'.edit', $invoice) }}" class="master-btn master-btn-light">Edit</a>
            @if ($invoice->isBill() && $balanceAmount > 0)
                <button type="button" class="master-btn master-btn-soft" data-open-payment
                    data-invoice-id="{{ $invoice->id }}"
                    data-invoice-number="{{ $invoice->invoice_number }}"
                    data-invoice-amount="{{ number_format($balanceAmount, 2, '.', '') }}"
                    data-invoice-balance="{{ \App\Helpers\CommonHelper::indianCurrency($balanceAmount) }}">
                    Record payment
                </button>
            @endif
            <a href="{{ route($routePrefix.'.print', $invoice) }}" target="_blank" class="master-btn master-btn-primary">Print</a>
            <div class="master-dropdown">
                <button type="button" class="master-dropdown-toggle" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
                <div class="master-dropdown-menu">
                    <button type="button" data-copy-link="{{ route('purchase-invoices.public', $invoice->public_token) }}">
                        Copy public link
                    </button>
                    @if ($invoice->canConvert())
                        <form method="POST" action="{{ route('purchase-orders.convert', $invoice) }}"
                            data-confirm="Raise a purchase bill from {{ $invoice->invoice_number }}?">
                            @csrf
                            <button type="submit">Convert to bill</button>
                        </form>
                    @endif
                    @foreach ($nextStatuses as $next)
                        <form method="POST" action="{{ route($routePrefix.'.status', $invoice) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="{{ $next }}">
                            <button type="submit">Mark {{ \App\Models\PurchaseInvoice::statusOptions()[$next] ?? $next }}</button>
                        </form>
                    @endforeach
                    <form method="POST" action="{{ route($routePrefix.'.destroy', $invoice) }}"
                        data-confirm="Delete {{ $invoice->invoice_number }}?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <div class="master-stats">
        <div class="master-stat master-stat--flat blue">
            <span class="icon"><i class="fas fa-file-invoice"></i></span>
            <div>
                <p class="master-stat-title">Total</p>
                <p class="master-stat-value">{{ \App\Helpers\CommonHelper::indianCurrency($invoice->total_amount) }}</p>
                <p class="master-sub">{{ $invoice->items->count() }} {{ \Illuminate\Support\Str::plural('line', $invoice->items->count()) }}</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat teal">
            <span class="icon"><i class="fas fa-indian-rupee-sign"></i></span>
            <div>
                <p class="master-stat-title">{{ $invoice->isBill() ? 'Paid' : 'Not payable' }}</p>
                <p class="master-stat-value">{{ \App\Helpers\CommonHelper::indianCurrency($invoice->isBill() ? $paidAmount : 0) }}</p>
                <p class="master-sub">{{ $invoice->isOrder() ? 'Raise a bill to pay this order' : ($payments->count().' payments') }}</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat purple">
            <span class="icon"><i class="fas fa-percent"></i></span>
            <div>
                <p class="master-stat-title">GST</p>
                <p class="master-stat-value">{{ \App\Helpers\CommonHelper::indianCurrency($taxAmount) }}</p>
                <p class="master-sub">{{ $invoice->gstTypeLabel() }}</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat orange">
            <span class="icon"><i class="fas fa-scale-balanced"></i></span>
            <div>
                <p class="master-stat-title">Balance due</p>
                <p class="master-stat-value">{{ \App\Helpers\CommonHelper::indianCurrency($balanceAmount) }}</p>
                <p class="master-sub">{{ $invoice->isOverdue() ? $daysLate.' days late' : ($invoice->isBill() ? 'On this bill' : 'Orders are not owed') }}</p>
            </div>
        </div>
    </div>

    <div class="master-card master-section">
        <div class="master-section-head">
            <div>
                <h2 class="master-section-title">Products</h2>
            </div>
        </div>
        @if ($invoice->items->isEmpty())
            <div class="master-empty-state">
                <p>No line items yet.</p>
                <a href="{{ route($routePrefix.'.edit', $invoice) }}" class="master-btn master-btn-light">Add items</a>
            </div>
        @else
            <div class="master-table-wrap">
                <table class="master-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th class="is-num">Qty</th>
                            <th class="is-num">Rate</th>
                            <th class="is-num">Taxable</th>
                            <th class="is-num">GST</th>
                            <th class="is-num">Line total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoice->items as $item)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    <strong>{{ $item->product_name }}</strong>
                                    @if (filled($item->description))
                                        <span class="master-sub">{!! nl2br(e($item->description)) !!}</span>
                                    @endif
                                </td>
                                <td class="is-num">{{ $figure($item->quantity) }} {{ $item->unit }}</td>
                                <td class="is-num">{{ \App\Helpers\CommonHelper::indianCurrency($item->unit_price) }}</td>
                                <td class="is-num">{{ \App\Helpers\CommonHelper::indianCurrency($item->taxable_amount) }}</td>
                                <td class="is-num">{{ $figure($item->gst_percent) }}%</td>
                                <td class="is-num">{{ \App\Helpers\CommonHelper::indianCurrency($item->line_total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="master-grid is-even">
        <div class="master-card master-section">
            <div class="master-section-head"><div><h2 class="master-section-title">Vendor</h2></div></div>
            <div class="master-facts">
                <div class="master-info"><span>Vendor</span><strong>{{ $invoice->vendor_company_name ?: 'Not recorded' }}</strong></div>
                <div class="master-info"><span>GSTIN</span><strong>{{ $invoice->vendor_gstin ?: 'Not on file' }}</strong></div>
                <div class="master-info"><span>PAN</span><strong>{{ $invoice->vendor_pan ?: 'Not on file' }}</strong></div>
                <div class="master-info is-wide"><span>Address</span><strong>{{ $vendorAddress ?: 'No address' }}</strong></div>
                <div class="master-info is-wide"><span>Contact</span>
                    <strong>{{ $invoice->vendor_contact_name ?: '—' }}</strong>
                    <span class="master-address-sub">{{ collect([$invoice->vendor_email, $invoice->vendor_mobile])->filter()->implode(' · ') }}</span>
                </div>
            </div>
        </div>
        <div class="master-card master-section">
            <div class="master-section-head"><div><h2 class="master-section-title">Summary</h2></div></div>
            <div class="si-total-row"><span>Subtotal</span><strong>{{ \App\Helpers\CommonHelper::indianCurrency($invoice->subtotal) }}</strong></div>
            <div class="si-total-row"><span>Discount</span>
                @if ((float) $invoice->discount_amount > 0)
                    <strong>− {{ \App\Helpers\CommonHelper::indianCurrency($invoice->discount_amount) }}</strong>
                @else
                    <span class="master-empty-value">None</span>
                @endif
            </div>
            @if ((float) $invoice->cgst_amount > 0)<div class="si-total-row"><span>CGST</span><strong>{{ \App\Helpers\CommonHelper::indianCurrency($invoice->cgst_amount) }}</strong></div>@endif
            @if ((float) $invoice->sgst_amount > 0)<div class="si-total-row"><span>SGST</span><strong>{{ \App\Helpers\CommonHelper::indianCurrency($invoice->sgst_amount) }}</strong></div>@endif
            @if ((float) $invoice->igst_amount > 0)<div class="si-total-row"><span>IGST</span><strong>{{ \App\Helpers\CommonHelper::indianCurrency($invoice->igst_amount) }}</strong></div>@endif
            @if ($chargesAmount > 0)
                @if ((float) $invoice->freight_amount > 0)<div class="si-total-row"><span>Freight</span><strong>{{ \App\Helpers\CommonHelper::indianCurrency($invoice->freight_amount) }}</strong></div>@endif
                @if ((float) $invoice->packing_amount > 0)<div class="si-total-row"><span>Packing</span><strong>{{ \App\Helpers\CommonHelper::indianCurrency($invoice->packing_amount) }}</strong></div>@endif
                @if ((float) $invoice->other_charges > 0)<div class="si-total-row"><span>Other</span><strong>{{ \App\Helpers\CommonHelper::indianCurrency($invoice->other_charges) }}</strong></div>@endif
            @endif
            <div class="si-total-row is-grand"><span>Grand total</span><strong>{{ \App\Helpers\CommonHelper::indianCurrency($invoice->total_amount) }}</strong></div>
            @if (filled($invoice->amount_in_words))
                <p class="si-words">{{ $invoice->amount_in_words }}</p>
            @endif
        </div>
    </div>

    @if ($invoice->isBill())
        <div class="master-card master-section">
            <div class="master-section-head">
                <div>
                    <h2 class="master-section-title">Payments</h2>
                    <p class="master-sub">Debits in the vendor ledger against this bill</p>
                </div>
                @if ($balanceAmount > 0)
                    <button type="button" class="master-btn master-btn-soft" data-open-payment
                        data-invoice-id="{{ $invoice->id }}"
                        data-invoice-number="{{ $invoice->invoice_number }}"
                        data-invoice-amount="{{ number_format($balanceAmount, 2, '.', '') }}"
                        data-invoice-balance="{{ \App\Helpers\CommonHelper::indianCurrency($balanceAmount) }}">Record</button>
                @endif
            </div>
            @if ($payments->isEmpty())
                <div class="master-empty-state">
                    <p>Nothing paid against this bill yet.</p>
                </div>
            @else
                <div class="si-payment-list">
                    @foreach ($payments as $payment)
                        <div class="si-payment">
                            <span class="si-payment-icon"><i class="fas fa-indian-rupee-sign"></i></span>
                            <div class="si-payment-main">
                                <strong>Paid {{ optional($payment->transaction_date)->format('d M Y') }}</strong>
                                <span class="master-sub">{{ $payment->payment_mode ?: 'Mode not recorded' }}
                                    @if (filled($payment->particular)) · {{ $payment->particular }} @endif
                                </span>
                            </div>
                            <strong class="si-payment-amount si-clear">{{ \App\Helpers\CommonHelper::indianCurrency($payment->amount_in_inr) }}</strong>
                        </div>
                    @endforeach
                </div>
            @endif
            <div class="si-pay-foot">
                <div><span>Paid</span><strong class="si-clear">{{ \App\Helpers\CommonHelper::indianCurrency($paidAmount) }}</strong></div>
                <div><span>Still open</span><strong class="{{ $balanceAmount > 0 ? 'si-due' : 'si-clear' }}">{{ \App\Helpers\CommonHelper::indianCurrency($balanceAmount) }}</strong></div>
            </div>
        </div>
    @endif

    @include('purchase_invoices.partials.payment-modal')
</div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/sales-invoices.css') }}">
@endpush
@push('scripts')
    <script src="{{ $assetVer('assets/js/purchase-invoices.js') }}"></script>
@endpush
