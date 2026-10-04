@php
    $costTotals = $costTotals ?? $shipment->costSummary();
    $costRows = $shipment->relationLoaded('costs') ? $shipment->costs : $shipment->costs()->get();
@endphp

<div class="master-card master-card--flat master-section" id="shipmentCosts">
    <div class="master-section-head">
        <div>
            <h3 class="master-section-title">Freight Cost Breakdown</h3>
            <p class="master-sub">
                Every head in the currency it was billed in. Heads with a paid account post to the cashflow ledger
                automatically — one entry, both ledgers.
            </p>
        </div>
        <button type="button" class="master-btn master-btn-primary master-btn-sm" id="openCostModal" data-mode="create">
            <i class="fa-solid fa-plus"></i> Add cost
        </button>
    </div>

    <div class="ship-cost-totals">
        <div>
            <span>Total spent</span>
            <strong>{{ $costTotals['inr'] > 0 ? \App\Models\Shipment::formatInr($costTotals['inr']) : '—' }}</strong>
        </div>
        <div>
            <span>Paid so far</span>
            <strong>{{ $costTotals['paid_inr'] > 0 ? \App\Models\Shipment::formatInr($costTotals['paid_inr']) : 'Nothing paid yet' }}</strong>
        </div>
        <div>
            <span>Cost per kg</span>
            <strong>{{ $shipment->costPerKg() ? \App\Models\Shipment::formatInr($shipment->costPerKg()) : '—' }}</strong>
        </div>
    </div>

    @if ($costRows->isEmpty())
        <div class="master-empty-state">
            <i class="fa-solid fa-receipt"></i>
            <p>No cost heads yet. The single “Shipment Cost” figure on the form is still used for the totals above.</p>
        </div>
    @else
        <div class="master-table-wrap">
            <table class="master-table ship-cost-table">
                <thead>
                    <tr>
                        <th>Head</th>
                        <th>Vendor</th>
                        <th class="is-num">Amount</th>
                        <th class="is-num">₹</th>
                        <th>Payment</th>
                        <th class="ship-col-actions">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($costRows as $cost)
                        <tr>
                            <td>
                                <strong>{{ $cost->headLabel() }}</strong>
                                <span class="master-sub">
                                    {{ $cost->document_number ?: 'No document no.' }}
                                    @if ($cost->incurred_on) · {{ $cost->incurred_on->format('d M Y') }} @endif
                                </span>
                            </td>
                            <td class="ship-cell-muted">{{ $cost->vendor->vendor_name ?? '—' }}</td>
                            <td class="is-num">
                                {{ $cost->amountLabel() }}
                                @if (strtoupper((string) $cost->currency) !== 'INR' && $cost->exchange_rate)
                                    <span class="master-sub">@ {{ rtrim(rtrim(number_format((float) $cost->exchange_rate, 4), '0'), '.') }}</span>
                                @endif
                            </td>
                            <td class="is-num">
                                {{ $cost->amount_in_inr ? \App\Models\Shipment::formatInr($cost->amount_in_inr) : '—' }}
                            </td>
                            <td>
                                @if ($cost->isPaid())
                                    <span class="master-badge status-delivered">Paid</span>
                                    <span class="master-sub">
                                        {{ $cost->paidAccount->account_name ?? 'Account' }}
                                        @if ($cost->paid_on) · {{ $cost->paid_on->format('d M Y') }} @endif
                                        @if ($cost->payment_mode) · {{ strtoupper($cost->payment_mode) }} @endif
                                    </span>
                                    @if ($cost->cashflow_entry_id && \Illuminate\Support\Facades\Route::has('cashflows.show'))
                                        <a class="cf-sync-chip" href="{{ route('cashflows.show', $cost->cashflow_entry_id) }}">↔ Cashflow #{{ $cost->cashflow_entry_id }}</a>
                                    @endif
                                @else
                                    <span class="master-badge status-pending">Unpaid</span>
                                    <span class="master-sub">Set a paid account when it settles</span>
                                @endif
                            </td>
                            <td>
                                <div class="master-row-actions">
                                    <button type="button" class="master-btn master-btn-ghost master-btn-sm edit-cost"
                                        data-cost="{{ json_encode([
                                            'id' => $cost->id,
                                            'cost_head' => $cost->cost_head,
                                            'label' => $cost->label,
                                            'amount' => $cost->amount,
                                            'currency' => $cost->currency,
                                            'exchange_rate' => $cost->exchange_rate,
                                            'vendor_id' => $cost->vendor_id,
                                            'incurred_on' => optional($cost->incurred_on)->format('Y-m-d'),
                                            'document_number' => $cost->document_number,
                                            'paid_account_id' => $cost->paid_account_id,
                                            'payment_mode' => $cost->payment_mode,
                                            'paid_on' => optional($cost->paid_on)->format('Y-m-d'),
                                            'notes' => $cost->notes,
                                        ]) }}">
                                        Edit
                                    </button>
                                    <form method="POST" action="{{ route('shipments.costs.destroy', $cost) }}" data-confirm="Remove this cost head?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="master-btn master-btn-danger-ghost master-btn-sm">Remove</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="ship-margin">
        @if ($costTotals['invoice_total'])
            <div>
                <span>Linked invoice</span>
                <strong>
                    @if ($shipment->salesInvoice && \Illuminate\Support\Facades\Route::has('sales-invoices.show'))
                        <a href="{{ route('sales-invoices.show', $shipment->salesInvoice) }}">{{ $shipment->salesInvoice->invoice_number }}</a>
                    @else
                        {{ $shipment->salesInvoice->invoice_number ?? '—' }}
                    @endif
                    · {{ \App\Models\Shipment::formatAmount($costTotals['invoice_currency'], $costTotals['invoice_total']) }}
                </strong>
            </div>
            <div>
                <span>Gross margin after freight</span>
                <strong class="{{ ($costTotals['margin'] ?? 0) < 0 ? 'is-negative' : 'is-positive' }}">
                    {{ \App\Helpers\CommonHelper::indianCurrency($costTotals['margin']) }}
                    @if ($costTotals['margin_percent'] !== null) · {{ $costTotals['margin_percent'] }}% @endif
                </strong>
            </div>
        @else
            <p class="master-sub ship-margin-hint">
                Link a sales invoice on the shipment to see the freight margin here.
            </p>
        @endif
    </div>
</div>

@include('shipments.partials.cost-modal')
