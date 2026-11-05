@php
    $reopenStatusModal = $statusModalShipment !== null;
    // Invalid requests can contain arrays where scalar fields are expected.
    // Only this form's own, safely printable old input belongs in the dialog.
    $statusModalValue = function (string $key, string $fallback = '') use ($reopenStatusModal): string {
        $value = $reopenStatusModal ? old($key, $fallback) : $fallback;
        return is_scalar($value) ? (string) $value : $fallback;
    };
    $selectedModalStatus = $statusModalValue('status', $statusModalShipment?->status ?? '');
@endphp
<div class="master-modal" id="shipmentStatusModal" aria-hidden="true" data-open-on-load="{{ $reopenStatusModal ? 'true' : 'false' }}">
    <div class="master-modal-card ship-list-status-modal" role="dialog" aria-modal="true" aria-labelledby="shipmentStatusTitle" aria-describedby="shipmentStatusContext">
        <form method="POST" action="{{ $statusModalShipment ? route('shipments.history.store', $statusModalShipment) : '' }}" data-list-status-form>
            @csrf
            <input type="hidden" name="_list_status_modal" value="1">
            <input type="hidden" name="_list_shipment_id" value="{{ $statusModalShipment?->id ?? '' }}">
            <input type="hidden" name="_expected_status" value="{{ $statusModalShipment?->status ?? '' }}">
            <div class="master-modal-header">
                <div class="master-modal-heading">
                    <span class="master-modal-icon" aria-hidden="true"><i class="fa-solid fa-truck"></i></span>
                    <div>
                        <h3 class="master-modal-title" id="shipmentStatusTitle">Update shipment status</h3>
                        <p class="master-modal-subtitle" id="shipmentStatusContext">
                            <strong data-shipment-status-number>{{ $statusModalShipment?->shipment_number ?? '' }}</strong>
                            <span aria-hidden="true"> · </span><span data-shipment-status-name>{{ $statusModalShipment?->identity_name ?? '' }}</span>
                        </p>
                    </div>
                </div>
                <button type="button" class="master-modal-close" data-list-status-close aria-label="Close status update">×</button>
            </div>
            <div class="master-modal-body">
                <p class="ship-list-status-current">Current status: <strong data-shipment-current-label>{{ $statusModalShipment?->statusLabel() ?? '' }}</strong></p>
                @if ($reopenStatusModal && $errors->any())
                    <div class="master-alert master-alert-danger" role="alert" tabindex="-1" data-list-status-errors>
                        <div><strong>Shipment status was not updated.</strong><ul>@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
                    </div>
                @endif
                <div class="master-modal-grid">
                    <div class="master-field full">
                        <label class="master-label" for="listStatus">Status <span class="master-required">*</span></label>
                        <select class="master-select" id="listStatus" name="status" data-no-select2 required aria-describedby="listStatusHelp">
                            <option value="" disabled @selected(! array_key_exists($selectedModalStatus, $statusOptions))>Choose a status</option>
                            @foreach ($statusOptions as $key => $label)
                                <option value="{{ $key }}" @selected($selectedModalStatus === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <small class="master-help" id="listStatusHelp">A new hold or delay needs a remark. Dispatch checks still apply.</small>
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="listStatusLocation">Location</label>
                        <input class="master-input" id="listStatusLocation" name="location" maxlength="255" value="{{ $statusModalValue('location') }}" placeholder="e.g. Mumbai hub">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="listStatusTime">Date &amp; time</label>
                        <input class="master-input" id="listStatusTime" type="datetime-local" name="event_time" value="{{ $statusModalValue('event_time') }}">
                        <small class="master-help">Leave blank to use the current time.</small>
                    </div>
                    <div class="master-field full" data-list-delivery-date {{ $selectedModalStatus === \App\Models\Shipment::STATUS_DELIVERED ? '' : 'hidden' }}>
                        <label class="master-label" for="listStatusDropDate">Delivery date</label>
                        <input class="master-input" id="listStatusDropDate" type="date" name="drop_date" value="{{ $statusModalValue('drop_date') }}">
                        <small class="master-help">Leave blank to keep an existing delivery date, or use today if none is saved.</small>
                    </div>
                    <div class="master-field full">
                        <label class="master-label" for="listStatusRemarks">Remarks</label>
                        <textarea class="master-textarea" id="listStatusRemarks" name="remarks" rows="3" placeholder="Add a tracking note or explain a hold or delay">{{ $statusModalValue('remarks') }}</textarea>
                    </div>
                    <div class="master-field full ship-list-status-options">
                        <label class="master-check"><input type="hidden" name="is_public" value="0"><input type="checkbox" name="is_public" value="1" {{ $statusModalValue('is_public', $reopenStatusModal ? '0' : '1') === '1' ? 'checked' : '' }}> Show on public tracking</label>
                        <label class="master-check"><input type="hidden" name="notify_client" value="0"><input type="checkbox" name="notify_client" value="1" {{ $statusModalValue('notify_client', '0') === '1' ? 'checked' : '' }}> Notify client on status change</label>
                        <small class="master-help">Notifications are sent only for shipments visible in the client portal.</small>
                    </div>
                </div>
            </div>
            <div class="master-modal-footer">
                <a class="master-btn master-btn-soft ship-list-status-tracking" data-list-status-tracking href="{{ $statusModalShipment ? route('shipments.show', ['shipment' => $statusModalShipment, 'tab' => 'tracking']) : '#' }}">View tracking</a>
                <button type="button" class="master-btn master-btn-light" data-list-status-close>Cancel</button>
                <button type="submit" class="master-btn master-btn-primary">Save status</button>
            </div>
        </form>
    </div>
</div>
