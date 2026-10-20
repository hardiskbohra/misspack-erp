@php
    $deliveryReceiptOpen = request()->boolean('record_delivery_receipt')
        || (bool) old('delivery_receipt_form')
        || $errors->hasAny(['receipt_reference', 'recipient_name', 'received_at', 'evidence_file', 'lines', 'lines.*']);

    /* One number written twice is one number too many: the operator enters what
       was accepted, damaged and rejected, and the sheet works out what arrived
       and what is still outstanding. */
    $quantity = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.') ?: '0';

    $formErrors = collect($errors->messages())
        ->reject(fn ($messages, $key) => str_starts_with((string) $key, 'lines.'))
        ->flatten();
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
                    <label class="master-field"><span class="master-label">POD / receipt evidence <small>Optional</small></span><input class="master-input" type="file" name="evidence_file" accept=".jpg,.jpeg,.png,.webp,.pdf"><small class="master-help">Attach the signed POD, delivery note or photograph when available — the line reconciliation above is the receipt either way.</small></label>
                </div>

                @if($formErrors->isNotEmpty())
                    <div class="master-alert error"><i class="fa-solid fa-circle-exclamation"></i><div>@foreach($formErrors as $message)<p>{{ $message }}</p>@endforeach</div></div>
                @endif

                <div class="shipment-delivery-guide">
                    <i class="fa-solid fa-calculator" aria-hidden="true"></i>
                    <p><strong>Received</strong> is calculated for you as accepted + damaged + rejected, so the receipt cannot fall out of balance.
                        <strong>Short</strong> fills in with whatever has not arrived — type a figure only when you are recording the shortage yourself.</p>
                </div>

                <div class="master-alert error" data-receipt-client-error hidden>
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div><p data-receipt-client-error-text></p></div>
                </div>

                <div class="master-table-wrap shipment-delivery-lines">
                    <table class="master-table">
                        <thead>
                            <tr>
                                <th scope="col">Product</th>
                                <th scope="col">Received<small>auto</small></th>
                                <th scope="col">Accepted</th>
                                <th scope="col">Damaged</th>
                                <th scope="col">Rejected</th>
                                <th scope="col">Short</th>
                                <th scope="col">Returned</th>
                                <th scope="col">Replacement</th>
                                <th scope="col">Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($shipment->items as $index=>$item)
                                @php
                                    $netAccepted = (float)$item->deliveryReceiptLines->sum('accepted_quantity') - (float)$item->deliveryReceiptLines->sum('returned_quantity');
                                    $remaining = round(max((float)$item->quantity - $netAccepted, 0), 3);
                                    $acceptedValue = (float) old('lines.'.$index.'.accepted_quantity', $remaining);
                                    $damagedValue = (float) old('lines.'.$index.'.damaged_quantity', 0);
                                    $rejectedValue = (float) old('lines.'.$index.'.rejected_quantity', 0);
                                    $returnedValue = (float) old('lines.'.$index.'.returned_quantity', 0);
                                    $replacementValue = (float) old('lines.'.$index.'.replacement_quantity', 0);
                                    $receivedValue = round($acceptedValue + $damagedValue + $rejectedValue, 3);
                                    $shortValue = round(min(max((float) old('lines.'.$index.'.short_quantity', max($remaining - $receivedValue, 0)), 0), $remaining), 3);
                                    $lineActive = $receivedValue > 0 || $shortValue > 0 || $returnedValue > 0 || $replacementValue > 0;
                                    /* What is still open to a return: everything accepted
                                       before minus what has already gone back. */
                                    $returnableBase = round(max($netAccepted, 0), 3);
                                    $lineError = $errors->first('lines.'.$index.'.accepted_quantity')
                                        ?: $errors->first('lines.'.$index.'.received_quantity')
                                        ?: $errors->first('lines.'.$index.'.short_quantity')
                                        ?: $errors->first('lines.'.$index.'.returned_quantity')
                                        ?: $errors->first('lines.'.$index.'.replacement_quantity')
                                        ?: $errors->first('lines.'.$index.'.discrepancy_reason');

                                    if ($lineError) {
                                        $lineStatus = $lineError;
                                    } elseif ($remaining <= 0 && ! $lineActive) {
                                        $lineStatus = 'Already fully received — record only a return or replacement.';
                                    } elseif (! $lineActive) {
                                        $lineStatus = 'Nothing received on this line yet.';
                                    } elseif ($shortValue > 0) {
                                        $lineStatus = $quantity($shortValue).' of '.$quantity($remaining).' dispatched has not arrived — explain why below.';
                                    } elseif ($receivedValue > $remaining) {
                                        $lineStatus = 'More than the '.$quantity($remaining).' dispatched — this receipt will be flagged.';
                                    } else {
                                        $lineStatus = 'Balanced · '.$quantity($receivedValue).' received = '.$quantity($acceptedValue).' accepted + '.$quantity($damagedValue).' damaged + '.$quantity($rejectedValue).' rejected.';
                                    }
                                @endphp
                                <tr data-receipt-line data-remaining="{{ $quantity($remaining) }}" data-returnable="{{ $quantity($returnableBase) }}" data-receipt-error="{{ $lineError }}">
                                    <td data-label="Product">
                                        <input type="hidden" name="lines[{{ $index }}][shipment_item_id]" value="{{ $item->id }}">
                                        <strong>{{ $item->product_name }}</strong>
                                        <span class="master-sub">{{ $quantity($remaining) }} of {{ $quantity((float)$item->quantity) }} {{ $item->unit }} still to receive</span>
                                        <span class="shipment-delivery-status {{ $lineError ? 'is-error' : ($shortValue > 0 || $receivedValue > $remaining ? 'is-warn' : ($lineActive ? 'is-ok' : '')) }}" data-receipt-status>{{ $lineStatus }}</span>
                                    </td>
                                    <td data-label="Received">
                                        <span class="shipment-delivery-computed" data-receipt-received>{{ $quantity($receivedValue) }}</span>
                                        <input type="hidden" name="lines[{{ $index }}][received_quantity]" value="{{ $quantity($receivedValue) }}" data-receipt-received-input>
                                    </td>
                                    @foreach(['accepted_quantity'=>'accepted','damaged_quantity'=>'damaged','rejected_quantity'=>'rejected'] as $field=>$role)
                                        <td data-label="{{ str($role)->headline() }}"><input class="master-input" type="number" min="0" step="0.001" inputmode="decimal" name="lines[{{ $index }}][{{ $field }}]" value="{{ old('lines.'.$index.'.'.$field, $quantity($role === 'accepted' ? $remaining : 0)) }}" aria-label="{{ str($role)->headline() }} for {{ $item->product_name }}" data-receipt-input="{{ $role }}"></td>
                                    @endforeach
                                    <td data-label="Short">
                                        <input class="master-input" type="number" min="0" max="{{ $quantity($remaining) }}" step="0.001" inputmode="decimal" name="lines[{{ $index }}][short_quantity]" value="{{ $quantity($shortValue) }}" aria-label="Short quantity for {{ $item->product_name }}" data-receipt-input="short">
                                    </td>
                                    @foreach(['returned_quantity'=>'returned','replacement_quantity'=>'replacement'] as $field=>$role)
                                        <td data-label="{{ str($role)->headline() }}"><input class="master-input" type="number" min="0" step="0.001" inputmode="decimal" name="lines[{{ $index }}][{{ $field }}]" value="{{ old('lines.'.$index.'.'.$field, 0) }}" aria-label="{{ str($role)->headline() }} for {{ $item->product_name }}" data-receipt-input="{{ $role }}"></td>
                                    @endforeach
                                    <td data-label="Reason"><input class="master-input" name="lines[{{ $index }}][discrepancy_reason]" maxlength="2000" value="{{ old('lines.'.$index.'.discrepancy_reason') }}" placeholder="Required for discrepancy" aria-label="Discrepancy reason for {{ $item->product_name }}" data-receipt-input="reason"></td>
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
