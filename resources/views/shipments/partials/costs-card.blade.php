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
            + Add cost
        </button>
    </div>

    <div class="ship-cost-totals">
        <div>
            <span>Total (as billed)</span>
            <strong>{{ \App\Models\Shipment::formatTotals($costTotals['by_currency']) }}</strong>
        </div>
        <div>
            <span>Total in INR</span>
            <strong>{{ $costTotals['inr'] > 0 ? '₹ '.number_format($costTotals['inr'], 2) : '—' }}</strong>
        </div>
        <div>
            <span>Paid so far</span>
            <strong>{{ $costTotals['paid_inr'] > 0 ? '₹ '.number_format($costTotals['paid_inr'], 2) : 'Nothing paid yet' }}</strong>
        </div>
        <div>
            <span>Cost per kg</span>
            <strong>{{ $shipment->costPerKg() ? '₹ '.number_format($shipment->costPerKg(), 2) : '—' }}</strong>
        </div>
    </div>

    <div class="master-table-wrap">
        <table class="master-table ship-cost-table">
            <thead>
                <tr>
                    <th>Head</th>
                    <th>Vendor</th>
                    <th>Amount</th>
                    <th>INR</th>
                    <th>Payment</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($costRows as $cost)
                    <tr>
                        <td>
                            <strong style="font-weight:600;">{{ $cost->headLabel() }}</strong>
                            <span class="master-sub">
                                {{ $cost->document_number ?: 'No document no.' }}
                                @if ($cost->incurred_on) · {{ $cost->incurred_on->format('d M Y') }} @endif
                            </span>
                        </td>
                        <td style="font-weight:500;">{{ $cost->vendor->vendor_name ?? '-' }}</td>
                        <td style="font-weight:500;">
                            {{ $cost->amountLabel() }}
                            @if (strtoupper((string) $cost->currency) !== 'INR' && $cost->exchange_rate)
                                <span class="master-sub">@ {{ rtrim(rtrim(number_format((float) $cost->exchange_rate, 4), '0'), '.') }}</span>
                            @endif
                        </td>
                        <td style="font-weight:500;">
                            {{ $cost->amount_in_inr ? '₹ '.number_format((float) $cost->amount_in_inr, 2) : '—' }}
                        </td>
                        <td style="font-weight:500;">
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
                                <button type="button" class="master-btn master-btn-soft master-btn-sm edit-cost"
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
                                    <button type="submit" class="master-btn master-btn-danger master-btn-sm">Remove</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <div class="master-empty">
                                No cost heads yet — the single "Shipment Cost" figure on the form is still used for totals.
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

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
                    ₹ {{ number_format((float) $costTotals['margin'], 2) }}
                    @if ($costTotals['margin_percent'] !== null) · {{ $costTotals['margin_percent'] }}% @endif
                </strong>
            </div>
        @else
            <div class="master-sub" style="padding:12px 0 0;">
                Link a sales invoice on the shipment to see the freight margin here.
            </div>
        @endif
    </div>
</div>

@include('shipments.partials.cost-modal')
