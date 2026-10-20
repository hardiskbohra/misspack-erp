@php
    $deliveryReceiptOpen = request()->boolean('record_delivery_receipt')
        || (bool) old('delivery_receipt_form')
        || $errors->hasAny(['receipt_reference', 'recipient_name', 'received_at', 'evidence_file', 'lines', 'lines.*']);
@endphp
<div class="master-modal" id="deliveryReceiptModal" aria-hidden="true" aria-labelledby="deliveryReceiptModalTitle" data-delivery-receipt-modal data-open-on-load="{{ $deliveryReceiptOpen ? 'true' : 'false' }}">
    <div class="master-modal-card shipment-delivery-modal" role="dialog" aria-modal="true" aria-labelledby="deliveryReceiptModalTitle">
        <form method="POST" enctype="multipart/form-data" action="{{ route('shipments.delivery-receipts.store',$shipment) }}">
            @csrf
            <input type="hidden" name="delivery_receipt_form" value="1">
            <div class="master-modal-header">
                <div class="master-modal-heading"><span class="master-modal-icon"><i class="fa-solid fa-clipboard-check"></i></span><div><h3 class="master-modal-title" id="deliveryReceiptModalTitle">Record delivery receipt</h3><p class="master-modal-subtitle">{{ $shipment->shipment_number }} · reconcile accepted and exception quantities against the dispatched lines.</p></div></div>
                <button type="button" class="master-modal-close" data-delivery-receipt-close aria-label="Close delivery receipt form">×</button>
            </div>
            <div class="master-modal-body">
                <div class="master-detail-grid">
                    <label class="master-field"><span class="master-label">Receipt / POD reference</span><input class="master-input" name="receipt_reference" maxlength="255" value="{{ old('receipt_reference') }}"></label>
                    <label class="master-field"><span class="master-label">Recipient name <b>*</b></span><input class="master-input" name="recipient_name" maxlength="255" value="{{ old('recipient_name') }}" required></label>
                    <label class="master-field"><span class="master-label">Received at <b>*</b></span><input class="master-input" type="datetime-local" name="received_at" value="{{ old('received_at',now()->format('Y-m-d\TH:i')) }}" required></label>
                    <label class="master-field"><span class="master-label">POD / receipt evidence <b>*</b></span><input class="master-input" type="file" name="evidence_file" accept=".jpg,.jpeg,.png,.webp,.pdf" required></label>
                </div>

                @if($errors->hasAny(['receipt_reference', 'recipient_name', 'received_at', 'evidence_file', 'lines', 'lines.*']))
                    <div class="master-alert error"><i class="fa-solid fa-circle-exclamation"></i><div>@foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div></div>
                @endif

                <div class="master-table-wrap shipment-delivery-lines">
                    <table class="master-table">
                        <thead><tr><th>Product</th><th>Received</th><th>Accepted</th><th>Short</th><th>Damaged</th><th>Rejected</th><th>Returned</th><th>Replacement</th><th>Reason</th></tr></thead>
                        <tbody>
                            @foreach($shipment->items as $index=>$item)
                                @php
                                    $netAccepted = (float)$item->deliveryReceiptLines->sum('accepted_quantity') - (float)$item->deliveryReceiptLines->sum('returned_quantity');
                                    $remaining = round(max((float)$item->quantity - $netAccepted, 0), 3);
                                @endphp
                                <tr>
                                    <td><input type="hidden" name="lines[{{ $index }}][shipment_item_id]" value="{{ $item->id }}"><strong>{{ $item->product_name }}</strong><span class="master-sub">{{ number_format($remaining,3) }} remaining of {{ number_format((float)$item->quantity,3) }}</span></td>
                                    @foreach(['received_quantity','accepted_quantity','short_quantity','damaged_quantity','rejected_quantity','returned_quantity','replacement_quantity'] as $field)
                                        <td><input class="master-input" type="number" min="0" step="0.001" name="lines[{{ $index }}][{{ $field }}]" value="{{ old('lines.'.$index.'.'.$field, in_array($field,['received_quantity','accepted_quantity'],true) ? $remaining : 0) }}" aria-label="{{ str($field)->headline() }} for {{ $item->product_name }}"></td>
                                    @endforeach
                                    <td><input class="master-input" name="lines[{{ $index }}][discrepancy_reason]" maxlength="2000" value="{{ old('lines.'.$index.'.discrepancy_reason') }}" placeholder="Required for discrepancy" aria-label="Discrepancy reason for {{ $item->product_name }}"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <label class="master-field"><span class="master-label">Receipt notes</span><textarea class="master-textarea" name="notes" rows="3" maxlength="4000">{{ old('notes') }}</textarea></label>
            </div>
            <div class="master-modal-footer"><button type="button" class="master-btn master-btn-light" data-delivery-receipt-close>Cancel</button><button class="master-btn master-btn-primary" type="submit"><i class="fa-solid fa-lock"></i> Record immutable receipt</button></div>
        </form>
    </div>
</div>
