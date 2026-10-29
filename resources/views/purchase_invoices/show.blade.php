@extends('layouts.app')

@section('page-title', $invoice->invoice_number)

@section('content')
@php
    $paidAmount = $invoice->paidAmount();
    $openAmount = $invoice->openAmount();
    $advanceAmount = $invoice->advanceAmount();
    $balanceAmount = $invoice->isBill() ? $invoice->balanceDue() : $openAmount;
    $isImport = $invoice->gst_type === 'export';
    $taxAmount = (float) $invoice->cgst_amount + (float) $invoice->sgst_amount + (float) $invoice->igst_amount;
    $chargesAmount = (float) $invoice->freight_amount + (float) $invoice->packing_amount + (float) $invoice->other_charges;
    // Original PO lines remain untouched for audit. Allocate their monetary
    // components cumulatively, the same way subsequent bills are apportioned.
    $remainingComponents = [];
    if ($invoice->hasPartialConversions() && (float) $invoice->original_total_amount > 0) {
        foreach (['taxable_amount', 'cgst_amount', 'sgst_amount', 'igst_amount', 'freight_amount', 'packing_amount', 'other_charges'] as $component) {
            $original = (float) $invoice->{$component};
            $remainingComponents[$component] = round($original - round($original * (float) $invoice->converted_amount / (float) $invoice->original_total_amount, 3), 3);
        }
        $remainingComponents['round_off'] = round((float) $invoice->total_amount - array_sum($remainingComponents), 3);
    }
    $stateKey = $invoice->stateKey();
    $daysLate = $invoice->daysOverdue();
    $lockedConversion = $invoice->hasPartialConversions() || $invoice->isPartialConversion();
    $nextStatuses = $lockedConversion ? [] : (\App\Models\PurchaseInvoice::STATUS_FLOW[$invoice->invoice_type][$invoice->status] ?? []);
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
                    <a class="master-chip" href="{{ route('purchase-invoices.show', $invoice->convertedInvoice) }}">
                        Became {{ $invoice->convertedInvoice?->invoice_number }}
                    </a>
                @endif
                @if ($invoice->purchaseOrder)
                    <a class="master-chip" href="{{ route('purchase-invoices.show', $invoice->purchaseOrder) }}">
                        From {{ $invoice->purchaseOrder->invoice_number }}
                    </a>
                @endif
            </div>
        </div>
        <div class="record-head-actions">
            <a href="{{ route($routePrefix.'.index') }}" class="master-btn master-btn-light">Back</a>
            @unless ($lockedConversion)<a href="{{ route($routePrefix.'.edit', $invoice) }}" class="master-btn master-btn-light">Edit</a>@endunless
            @if($invoice->isBill())<a href="{{ route('packaging-lists.create',['purchase_invoice'=>$invoice->id]) }}" class="master-btn master-btn-soft"><i class="fa-solid fa-boxes-stacked"></i> Pack items</a>@endif
            @php
                $advanceOpen = $invoice->isOrder() && $invoice->canReceiveMoney()
                    ? max((float) $invoice->total_amount - $paidAmount, 0)
                    : 0;
            @endphp
            @if ($invoice->isBill() && $invoice->canReceiveMoney() && $balanceAmount > 0)
                <button type="button" class="master-btn master-btn-soft" data-open-payment
                    data-invoice-id="{{ $invoice->id }}"
                    data-invoice-number="{{ $invoice->invoice_number }}"
                    data-invoice-kind="bill"
                    data-invoice-currency="{{ $invoice->currency ?: 'RMB' }}"
                    data-invoice-rate="{{ $invoice->exchange_rate }}"
                    data-project-id="{{ $invoice->project_id }}"
                    data-invoice-amount="{{ number_format($balanceAmount, 3, '.', '') }}"
                    data-invoice-balance="{{ \App\Helpers\CommonHelper::amount($balanceAmount, $invoice->currency) }}">
                    Record payment
                </button>
            @elseif ($advanceOpen > 0)
                <button type="button" class="master-btn master-btn-soft" data-open-payment
                    data-invoice-id="{{ $invoice->id }}"
                    data-invoice-number="{{ $invoice->invoice_number }}"
                    data-invoice-kind="order"
                    data-invoice-currency="{{ $invoice->currency ?: 'RMB' }}"
                    data-invoice-rate="{{ $invoice->exchange_rate }}"
                    data-project-id="{{ $invoice->project_id }}"
                    data-invoice-amount="{{ number_format($advanceOpen, 3, '.', '') }}"
                    data-invoice-balance="{{ \App\Helpers\CommonHelper::amount($advanceOpen, $invoice->currency) }}">
                    Record advance
                </button>
            @elseif ($invoice->isOrder() && ! $invoice->canReceiveMoney() && $invoice->status !== 'cancelled' && ! $invoice->isSuperseded())
                @if (in_array('approved', $nextStatuses, true))
                    <form method="POST" action="{{ route($routePrefix.'.status', $invoice) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="approved">
                        <button type="submit" class="master-btn master-btn-soft">Approve PO to allow advance</button>
                    </form>
                @endif
            @endif
            <a href="{{ route($routePrefix.'.print', $invoice) }}" target="_blank" class="master-btn master-btn-primary">Print</a>
            <div class="master-dropdown">
                <button type="button" class="master-dropdown-toggle" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
                <div class="master-dropdown-menu">
                    <button type="button" data-copy-link="{{ \App\Services\PublicShareLinks::url($invoice) }}">
                        <i class="fa-solid fa-link" aria-hidden="true"></i> Copy public link
                    </button>
                    @if ($invoice->canConvert())
                        <a href="#purchasePartialConversion"><i class="fa-solid fa-arrow-right-arrow-left" aria-hidden="true"></i> Create linked bill</a>
                    @endif
                    @foreach ($nextStatuses as $next)
                        <form method="POST" action="{{ route($routePrefix.'.status', $invoice) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="{{ $next }}">
                            <button type="submit"><i class="fa-solid fa-check" aria-hidden="true"></i> Mark {{ \App\Models\PurchaseInvoice::statusOptions()[$next] ?? $next }}</button>
                        </form>
                    @endforeach
                    @unless ($invoice->hasPartialConversions())
                        <form method="POST" action="{{ route($routePrefix.'.destroy', $invoice) }}"
                            data-confirm="{{ $invoice->isPartialConversion() ? 'Reverse this conversion and return its payments to the PO? Only the latest conversion can be reversed.' : 'Delete '.$invoice->invoice_number.'?' }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="danger"><i class="fa-solid fa-trash" aria-hidden="true"></i> {{ $invoice->isPartialConversion() ? 'Reverse conversion' : 'Delete' }}</button>
                        </form>
                    @endunless
                </div>
            </div>
        </div>
    </header>

    @if ($invoice->isPartialConversion())
        <div class="master-alert"><strong>Linked purchase conversion.</strong> Financial figures are locked to protect the PO split. Check this bill against the supplier's paperwork. To change the split, reverse the latest conversion first; linked payments return to the PO without changing cashflow.</div>
        <form class="master-card master-section" method="POST" action="{{ route('purchase-invoices.conversion-reference', $invoice) }}">
            @csrf @method('PATCH')
            <h2 class="master-section-title">Supplier reference</h2>
            <div class="master-form-grid is-three">
                <div class="master-field"><label class="master-label" for="supplierRef">Supplier bill number</label><input class="master-input" id="supplierRef" name="vendor_bill_number" value="{{ old('vendor_bill_number', $invoice->vendor_bill_number) }}" maxlength="255"></div>
                <div class="master-field"><label class="master-label" for="supplierDate">Supplier bill date</label><input class="master-input" type="date" id="supplierDate" name="vendor_bill_date" value="{{ old('vendor_bill_date', $invoice->vendor_bill_date?->format('Y-m-d')) }}"></div>
            </div>
            <button class="master-btn master-btn-soft" type="submit">Save supplier reference</button>
        </form>
    @endif
    <div class="master-stats">
        <div class="master-stat master-stat--flat blue">
            <span class="icon"><i class="fas fa-file-invoice"></i></span>
            <div>
                <p class="master-stat-title">{{ $invoice->hasPartialConversions() ? 'Remaining PO total' : 'Total' }}</p>
                <p class="master-stat-value">{{ \App\Helpers\CommonHelper::amount($invoice->total_amount, $invoice->currency) }}</p>
                <p class="master-sub">{{ $invoice->items->count() }} {{ \Illuminate\Support\Str::plural('line', $invoice->items->count()) }}</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat teal">
            <span class="icon"><i class="fas fa-indian-rupee-sign"></i></span>
            <div>
                <p class="master-stat-title">{{ $invoice->isBill() ? 'Paid' : 'Advance paid' }}</p>
                <p class="master-stat-value">{{ \App\Helpers\CommonHelper::amount($paidAmount, $invoice->currency) }}</p>
                <p class="master-sub">{{ $invoice->isOrder() ? ($paidAmount > 0 ? 'Choose payments when creating a linked bill' : 'Optional before a bill') : ($payments->count().' payments') }}</p>
            </div>
        </div>
        @unless ($isImport)
        <div class="master-stat master-stat--flat purple">
            <span class="icon"><i class="fas fa-percent"></i></span>
            <div>
                <p class="master-stat-title">{{ $invoice->hasPartialConversions() ? 'Original PO GST' : 'GST' }}</p>
                <p class="master-stat-value">{{ \App\Helpers\CommonHelper::amount($taxAmount, $invoice->currency) }}</p>
                <p class="master-sub">{{ $invoice->gstTypeLabel() }}</p>
            </div>
        </div>
        @endunless
        <div class="master-stat master-stat--flat orange">
            <span class="icon"><i class="fas fa-scale-balanced"></i></span>
            <div>
                <p class="master-stat-title">{{ $invoice->isBill() ? 'Balance due' : 'Still open' }}</p>
                <p class="master-stat-value">{{ \App\Helpers\CommonHelper::amount($balanceAmount, $invoice->currency) }}</p>
                <p class="master-sub">{{ $invoice->isOverdue() ? $daysLate.' days late' : ($invoice->isBill() ? 'On this bill' : 'In '.$invoice->currency) }}</p>
            </div>
        </div>
    </div>

    @include('purchase_invoices.partials.partial-conversions')

    @if ($invoice->isOrder() && ! $invoice->canReceiveMoney() && ! $invoice->isSuperseded() && $invoice->status !== 'cancelled' && ! $invoice->hasPartialConversions())
        <x-step-banner
            tone="warning"
            eyebrow="Pending checker"
            title="{{ $invoice->status === 'draft' ? 'Send and approve this purchase order' : 'Approve this purchase order' }}"
            :body="$invoice->moneyGateMessage()"
        >
            <x-slot:actions>
                @if (in_array('sent', $nextStatuses, true))
                    <form method="POST" action="{{ route($routePrefix.'.status', $invoice) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="sent">
                        <button type="submit" class="master-btn master-btn-light">Mark sent</button>
                    </form>
                @endif
                @if (in_array('approved', $nextStatuses, true))
                    <form method="POST" action="{{ route($routePrefix.'.status', $invoice) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="approved">
                        <button type="submit" class="master-btn master-btn-primary">Approve purchase order</button>
                    </form>
                @endif
            </x-slot:actions>
        </x-step-banner>
    @endif

    <div class="master-card master-section">
        <div class="master-section-head">
            <div>
                <h2 class="master-section-title">{{ $invoice->hasPartialConversions() ? 'Original PO products' : 'Products' }}</h2>
                @if ($invoice->hasPartialConversions())<p class="master-sub">Original quantities and prices are retained for audit; linked bills represent the converted portions.</p>@endif
            </div>
        </div>
        @if ($invoice->items->isEmpty())
            <div class="master-empty-state">
                <p>No line items yet.</p>
                <a href="{{ route($routePrefix.'.edit', $invoice) }}" class="master-btn master-btn-light">Add items</a>
            </div>
        @else
            <div class="master-table-wrap">
                <table class="master-table si-lines">
                    <colgroup>
                        <col style="width: 4%">
                        <col>
                        <col style="width: 12%">
                        <col style="width: 16%">
                        @unless ($isImport)
                            <col style="width: 16%">
                            <col style="width: 10%">
                        @endunless
                        <col style="width: 16%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th class="is-num">Qty</th>
                            <th class="is-num">Rate</th>
                            @unless ($isImport)
                                <th class="is-num">Taxable</th>
                                <th class="is-num">GST</th>
                            @endunless
                            <th class="is-num">Amount</th>
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
                                </td>
                                <td data-label="Qty" class="is-num">{{ $figure($item->quantity) }} {{ $item->unit }}</td>
                                <td data-label="Rate" class="is-num">{{ \App\Helpers\CommonHelper::amount($item->unit_price, $invoice->currency) }}</td>
                                @unless ($isImport)
                                    <td data-label="Taxable" class="is-num">{{ \App\Helpers\CommonHelper::amount($item->taxable_amount, $invoice->currency) }}</td>
                                    <td data-label="GST" class="is-num">{{ $figure($item->gst_percent) }}%</td>
                                @endunless
                                <td data-label="Amount" class="is-num">{{ \App\Helpers\CommonHelper::amount($item->line_total, $invoice->currency) }}</td>
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
            <div class="master-section-head"><div><h2 class="master-section-title">{{ $invoice->hasPartialConversions() ? 'Original PO summary' : 'Summary' }}</h2>@if ($invoice->hasPartialConversions())<p class="master-sub">Original components, less the gross value of linked bills.</p>@endif</div></div>
            <div class="si-total-row"><span>Subtotal</span><strong>{{ \App\Helpers\CommonHelper::amount($invoice->subtotal, $invoice->currency) }}</strong></div>
            <div class="si-total-row"><span>Discount</span>
                @if ((float) $invoice->discount_amount > 0)
                    <strong>− {{ \App\Helpers\CommonHelper::amount($invoice->discount_amount, $invoice->currency) }}</strong>
                @else
                    <span class="master-empty-value">None</span>
                @endif
            </div>
            @unless ($isImport)
            @if ((float) $invoice->cgst_amount > 0)<div class="si-total-row"><span>CGST</span><strong>{{ \App\Helpers\CommonHelper::amount($invoice->cgst_amount, $invoice->currency) }}</strong></div>@endif
            @if ((float) $invoice->sgst_amount > 0)<div class="si-total-row"><span>SGST</span><strong>{{ \App\Helpers\CommonHelper::amount($invoice->sgst_amount, $invoice->currency) }}</strong></div>@endif
            @if ((float) $invoice->igst_amount > 0)<div class="si-total-row"><span>IGST</span><strong>{{ \App\Helpers\CommonHelper::amount($invoice->igst_amount, $invoice->currency) }}</strong></div>@endif
            @endunless
            @if ($chargesAmount > 0)
                @if ((float) $invoice->freight_amount > 0)<div class="si-total-row"><span>Freight</span><strong>{{ \App\Helpers\CommonHelper::amount($invoice->freight_amount, $invoice->currency) }}</strong></div>@endif
                @if ((float) $invoice->packing_amount > 0)<div class="si-total-row"><span>Packing</span><strong>{{ \App\Helpers\CommonHelper::amount($invoice->packing_amount, $invoice->currency) }}</strong></div>@endif
                @if ((float) $invoice->other_charges > 0)<div class="si-total-row"><span>Other</span><strong>{{ \App\Helpers\CommonHelper::amount($invoice->other_charges, $invoice->currency) }}</strong></div>@endif
            @endif
            @if (abs((float) $invoice->round_off) >= 0.001)
                <div class="si-total-row"><span>Round off</span><strong>{{ \App\Helpers\CommonHelper::amount($invoice->round_off, $invoice->currency) }}</strong></div>
            @endif
            @if ($invoice->hasPartialConversions())
                <div class="si-total-row"><span>Original PO total</span><strong>{{ \App\Helpers\CommonHelper::amount($invoice->original_total_amount, $invoice->currency) }}</strong></div>
                <div class="si-total-row"><span>Less linked bills</span><strong>− {{ \App\Helpers\CommonHelper::amount($invoice->converted_amount, $invoice->currency) }}</strong></div>
            @endif
            <div class="si-total-row is-grand"><span>{{ $invoice->hasPartialConversions() ? 'Remaining PO total' : 'Grand total' }}</span><strong>{{ \App\Helpers\CommonHelper::amount($invoice->total_amount, $invoice->currency) }}</strong></div>
            @if (filled($invoice->amount_in_words))
                <p class="si-words">{{ $invoice->amount_in_words }}</p>
            @endif
        </div>
    </div>

    @if ($remainingComponents)
        <div class="master-card master-section">
            <div class="master-section-head"><div>
                <h2 class="master-section-title">Apportioned remaining PO</h2>
                <p class="master-sub">The remaining gross is apportioned from the original components; cash bills remove their share of GST. Minor rounding is included below.</p>
            </div></div>
            <div class="si-total-row"><span>Net taxable / base</span><strong>{{ \App\Helpers\CommonHelper::amount($remainingComponents['taxable_amount'], $invoice->currency) }}</strong></div>
            @foreach (['cgst_amount' => 'CGST', 'sgst_amount' => 'SGST', 'igst_amount' => 'IGST', 'freight_amount' => 'Freight', 'packing_amount' => 'Packing', 'other_charges' => 'Other charges', 'round_off' => 'Rounding'] as $key => $label)
                @if (abs($remainingComponents[$key]) >= 0.001)<div class="si-total-row"><span>{{ $label }}</span><strong>{{ \App\Helpers\CommonHelper::amount($remainingComponents[$key], $invoice->currency) }}</strong></div>@endif
            @endforeach
            <div class="si-total-row is-grand"><span>Remaining PO total</span><strong>{{ \App\Helpers\CommonHelper::amount($invoice->total_amount, $invoice->currency) }}</strong></div>
        </div>
    @endif

    @if ($invoice->isBill() || ($invoice->isOrder() && ! $invoice->isSuperseded()))
        <div class="master-card master-section">
            <div class="master-section-head">
                <div>
                    <h2 class="master-section-title">{{ $invoice->isOrder() ? 'Advances' : 'Payments' }}</h2>
                    <p class="master-sub">{{ $invoice->isOrder() ? ($invoice->canReceiveMoney() ? 'Debits against this order — choose individual amounts when creating a bill' : $invoice->moneyGateMessage()) : 'Debits in the vendor ledger against this bill' }}</p>
                </div>
                @if ($invoice->isBill() && $invoice->canReceiveMoney() && $balanceAmount > 0)
                    <button type="button" class="master-btn master-btn-soft" data-open-payment
                        data-invoice-id="{{ $invoice->id }}"
                        data-invoice-number="{{ $invoice->invoice_number }}"
                        data-invoice-kind="bill"
                        data-invoice-currency="{{ $invoice->currency ?: 'RMB' }}"
                        data-invoice-rate="{{ $invoice->exchange_rate }}"
                        data-project-id="{{ $invoice->project_id }}"
                        data-invoice-amount="{{ number_format($balanceAmount, 3, '.', '') }}"
                        data-invoice-balance="{{ \App\Helpers\CommonHelper::amount($balanceAmount, $invoice->currency) }}">Record</button>
                @elseif ($invoice->isOrder() && $invoice->canReceiveMoney() && max((float) $invoice->total_amount - $paidAmount, 0) > 0)
                    <button type="button" class="master-btn master-btn-soft" data-open-payment
                        data-invoice-id="{{ $invoice->id }}"
                        data-invoice-number="{{ $invoice->invoice_number }}"
                        data-invoice-kind="order"
                        data-invoice-currency="{{ $invoice->currency ?: 'RMB' }}"
                        data-invoice-rate="{{ $invoice->exchange_rate }}"
                        data-project-id="{{ $invoice->project_id }}"
                        data-invoice-amount="{{ number_format(max((float) $invoice->total_amount - $paidAmount, 0), 3, '.', '') }}"
                        data-invoice-balance="{{ \App\Helpers\CommonHelper::amount(max((float) $invoice->total_amount - $paidAmount, 0), $invoice->currency) }}">Record advance</button>
                @endif
            </div>
            @if ($payments->isEmpty() && (float) $invoice->amount_paid <= 0)
                <div class="master-empty-state">
                    <p>{{ $invoice->isOrder() ? 'No advance against this order yet.' : 'Nothing paid against this bill yet.' }}</p>
                </div>
            @else
                <div class="si-payment-list">
                    @if ((float) $invoice->amount_paid > 0)
                        <div class="si-payment">
                            <span class="si-payment-icon"><i class="fas fa-indian-rupee-sign"></i></span>
                            <div class="si-payment-main"><strong>Opening advance</strong><span class="master-sub">Amount recorded on the document</span></div>
                            <strong class="si-payment-amount si-clear">{{ \App\Helpers\CommonHelper::amount($invoice->amount_paid, $invoice->currency) }}</strong>
                        </div>
                    @endif
                    @foreach ($payments as $payment)
                        <div class="si-payment">
                            <span class="si-payment-icon"><i class="fas fa-indian-rupee-sign"></i></span>
                            <div class="si-payment-main">
                                <strong>{{ $payment->foreign_amount < 0 ? 'Refunded' : 'Paid' }} {{ optional($payment->transaction_date)->format('d M Y') }}</strong>
                                <span class="master-sub">{{ $payment->payment_mode ?: 'Mode not recorded' }}
                                    @if (filled($payment->particular)) · {{ $payment->particular }} @endif
                                </span>
                                @if ($payment->ledger_payment_id)
                                    <span class="master-sub">Vendor payment #{{ $payment->ledger_payment_id }}@if ($payment->source_key === 'splits') · allocated portion @endif @if ($payment->account_name) · {{ $payment->account_name }} @endif</span>
                                @endif
                                @if (! empty($payment->cashflow_entry_id))
                                    <a class="si-payment-ref" href="{{ route('cashflows.show', $payment->cashflow_entry_id) }}">Cashflow entry #{{ $payment->cashflow_entry_id }}</a>
                                @endif
                            </div>
                            <strong class="si-payment-amount {{ $payment->foreign_amount < 0 ? 'si-due' : 'si-clear' }}">{{ \App\Helpers\CommonHelper::amount($payment->foreign_amount, $payment->foreign_currency ?: $invoice->currency) }}</strong>
                        </div>
                    @endforeach
                </div>
            @endif
            <div class="si-pay-foot">
                <div><span>Paid</span><strong class="si-clear">{{ \App\Helpers\CommonHelper::amount($paidAmount, $invoice->currency) }}</strong></div>
                <div><span>Still open</span><strong class="{{ $openAmount > 0 ? 'si-due' : 'si-clear' }}">{{ \App\Helpers\CommonHelper::amount($openAmount, $invoice->currency) }}</strong></div>
                @if($advanceAmount >= 0.001)<div><span>Advance / overpayment</span><strong class="si-clear">{{ \App\Helpers\CommonHelper::amount($advanceAmount, $invoice->currency) }}</strong></div>@endif
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
