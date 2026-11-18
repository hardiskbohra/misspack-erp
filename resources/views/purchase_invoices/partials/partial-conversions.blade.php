@if ($invoice->isOrder() && ! $invoice->isSuperseded())
<section class="master-card master-section" id="purchasePartialConversion" data-remaining="{{ $invoice->total_amount }}" data-currency="{{ $invoice->currency }}">
    <div class="master-section-head"><div>
        <h2 class="master-section-title">Split this purchase</h2>
        <p class="master-sub">Create cash purchase bills or purchase bills from the remaining PO. Each amount reduces the PO total exactly; cash bills carry no GST. Check every purchase bill against the supplier's paperwork.</p>
    </div></div>
    @if ($invoice->relationLoaded('partialConversions') && $invoice->partialConversions->isNotEmpty())
        <div class="master-table-wrap"><table class="master-table">
            <thead><tr><th>Document</th><th>Type</th><th>Amount</th><th>Paid</th><th>Open</th></tr></thead>
            <tbody>
            @foreach ($invoice->partialConversions as $child)
                <tr><td data-label="Document"><a href="{{ route('purchase-invoices.show', $child) }}">{{ $child->invoice_number }}</a></td>
                    <td data-label="Type">{{ $child->typeLabel() }}</td>
                    <td data-label="Amount">{{ \App\Helpers\CommonHelper::amount($child->total_amount, $child->currency) }}</td>
                    <td data-label="Paid">{{ \App\Helpers\CommonHelper::amount($child->paidAmount(), $child->currency) }}</td>
                    <td data-label="Open">{{ \App\Helpers\CommonHelper::amount($child->balanceDue(), $child->currency) }}</td></tr>
            @endforeach
            </tbody>
        </table></div>
        <p class="master-help">To correct a split, reverse the latest linked bill first. Payment and bank records are retained.</p>
    @endif
    @if ($invoice->canConvert())
        @if ($errors->any())
            <div class="master-alert" role="alert"><strong>The conversion was not saved.</strong> {{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('purchase-invoices.convert-partial', $invoice) }}" id="purchasePartialForm">
            @csrf
            <div class="master-form-grid is-three">
                <div class="master-field"><label class="master-label" for="purchaseConversionKind">Bill type</label>
                    <select class="master-select" id="purchaseConversionKind" name="kind"><option value="cash" @selected(old('kind') === 'cash')>Cash purchase bill (no GST)</option><option value="bill" @selected(old('kind') === 'bill')>Purchase bill with apportioned GST</option></select></div>
                <div class="master-field"><label class="master-label" for="purchaseConversionMode">Convert by</label>
                    <select class="master-select" id="purchaseConversionMode" name="mode"><option value="amount" @selected(old('mode') === 'amount')>Amount ({{ $invoice->currency }})</option><option value="percent" @selected(old('mode') === 'percent')>Percentage of remaining PO</option></select></div>
                <div class="master-field"><label class="master-label" for="purchaseConversionValue">Amount / percentage <span class="master-required">*</span></label>
                    <input class="master-input" id="purchaseConversionValue" name="value" type="number" min="0.001" step="0.001" max="{{ old('mode') === 'percent' ? 100 : $invoice->total_amount }}" value="{{ old('value', old('mode') === 'percent' ? 100 : $invoice->total_amount) }}" required></div>
                <div class="master-field"><label class="master-label" for="purchaseConversionDate">Bill date</label><input class="master-input" id="purchaseConversionDate" name="invoice_date" type="date" value="{{ old('invoice_date', now()->toDateString()) }}"></div>
                <div class="master-field"><label class="master-label" for="purchaseVendorBillNumber">Supplier bill number</label><input class="master-input" id="purchaseVendorBillNumber" name="vendor_bill_number" value="{{ old('vendor_bill_number') }}" maxlength="255" placeholder="Optional"></div>
                <div class="master-field"><label class="master-label" for="purchaseVendorBillDate">Supplier bill date</label><input class="master-input" id="purchaseVendorBillDate" name="vendor_bill_date" type="date" value="{{ old('vendor_bill_date') }}"></div>
            </div>
            <p class="master-help">Bills keep the full PO quantities and scale the item rates to three decimals. Cash prices include the selected share of GST, discounts and charges without showing them separately. Any price-rounding difference appears in round off and may be larger than 0.005 {{ $invoice->currency }}; a very small rate may round to zero. Quantities repeated across bills are financial references, not separate goods received. Check the supplier's bill.</p>
            <h3 class="master-section-title">Choose payments for this bill</h3>
            <p class="master-help">Enter how much of each advance to allocate, in {{ $invoice->currency }}. All start at zero; no payment is selected automatically. Unselected money stays on the PO. No new cash or bank movement is created.</p>
            @if ((float) $invoice->amount_paid > 0 || $conversionPayments->isNotEmpty())
                <div class="master-table-wrap"><table class="master-table">
                    <thead><tr><th>Payment</th><th>Available</th><th>Allocate to bill ({{ $invoice->currency }})</th></tr></thead>
                    <tbody>
                    @if ((float) $invoice->amount_paid > 0)
                        <tr><td data-label="Payment">Opening advance</td><td data-label="Available">{{ \App\Helpers\CommonHelper::amount($invoice->amount_paid, $invoice->currency) }}</td>
                            <td data-label="Allocate"><input class="master-input" type="number" name="opening" aria-label="Allocate opening advance" min="0" step="0.001" max="{{ $invoice->amount_paid }}" value="{{ old('opening', '0') }}" data-purchase-transfer></td></tr>
                    @endif
                    @foreach ($conversionPayments as $payment)
                        <tr><td data-label="Payment"><strong>{{ $payment->particular ?: 'Advance' }}</strong><br><span class="master-sub">{{ $payment->transaction_date?->format('d M Y') }} · {{ $payment->payment_mode ?: 'Mode not recorded' }}@if ($payment->account_name) · {{ $payment->account_name }} @endif @if ($payment->ledger_payment_id) · Vendor payment #{{ $payment->ledger_payment_id }} @endif @if ($payment->cashflow_entry_id) · Cashflow #{{ $payment->cashflow_entry_id }} @endif</span></td>
                            <td data-label="Available">{{ \App\Helpers\CommonHelper::amount($payment->foreign_amount, $payment->foreign_currency ?: $invoice->currency) }}</td>
                            <td data-label="Allocate"><input class="master-input" type="number" name="{{ $payment->source_key }}[{{ $payment->id }}]" aria-label="Allocate {{ $payment->particular ?: 'advance' }} #{{ $payment->id }}" min="0" step="0.001" max="{{ $payment->foreign_amount }}" value="{{ old($payment->source_key.'.'.$payment->id, '0') }}" data-purchase-transfer></td></tr>
                    @endforeach
                    </tbody>
                </table></div>
            @else
                <p class="master-help">No recorded advances are available. The bill will remain unpaid until a payment is recorded.</p>
            @endif
            <p class="master-help" id="purchaseConversionSummary" aria-live="polite">Only the amounts entered above will move to the bill.</p>
            <button class="master-btn master-btn-primary" type="submit">Create linked bill</button>
        </form>
    @elseif ($invoice->status !== 'approved')
        <p class="master-help">Approve the purchase order before creating linked bills.</p>
    @else
        <p class="master-help">The entire PO has been converted. Any unallocated advance remains on this order.</p>
    @endif
</section>
@endif
