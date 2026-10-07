@php
    /*
     * A service, a repair, an AMC payment, a calibration — the company's repair
     * history for one asset, and the date the next one is due.
     *
     * The downtime window is what makes this more than a bill: an asset that was
     * down for four days is a fact about the year, and it is the fact the
     * maintenance log is asked for. The status field is optional because a machine
     * repaired over a weekend never stopped being in use.
     */
    $action = isset($asset) ? route('assets.maintain', $asset) : '';
@endphp

<div class="master-modal" id="assetMaintainModal" aria-hidden="true">
    <div class="master-modal-card" role="dialog" aria-modal="true" aria-labelledby="assetMaintainTitle">
        <form method="POST" action="{{ $action }}" data-asset-form="maintenance">
            @csrf
            <input type="hidden" name="_dialog" value="assetMaintainModal">

            <div class="master-modal-header">
                <div class="master-modal-heading">
                    <span class="master-modal-icon" aria-hidden="true"><i class="fa-solid fa-screwdriver-wrench"></i></span>
                    <div>
                        <h3 class="master-modal-title" id="assetMaintainTitle">Log a repair or service</h3>
                        <p class="master-modal-subtitle" data-asset-subject>
                            {{ isset($asset) ? $asset->asset_code.' · '.$asset->name : 'What was done, what it cost, when the next one is due' }}
                        </p>
                    </div>
                </div>
                <button type="button" class="master-modal-close" data-close-modal="assetMaintainModal"
                    aria-label="Close">&times;</button>
            </div>

            <div class="master-modal-body">
                <div class="master-modal-grid">
                    <div class="master-field">
                        <label class="master-label" for="maintenanceKind">What kind</label>
                        <select class="master-select" id="maintenanceKind" name="kind" required>
                            @foreach ($kindOptions as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="master-field">
                        <label class="master-label" for="maintenanceDate">Performed on
                            <span class="master-required" aria-hidden="true">*</span></label>
                        <input class="master-input" id="maintenanceDate" type="date" name="performed_on" required
                            value="{{ old('performed_on', now()->format('Y-m-d')) }}">
                    </div>

                    <div class="master-field">
                        <label class="master-label" for="maintenanceVendor">Done by (vendor)</label>
                        <select class="master-select" id="maintenanceVendor" name="vendor_id">
                            <option value="">Not a vendor on file</option>
                            @foreach ($vendorOptions as $option)
                                <option value="{{ $option->id }}">{{ $option->vendor_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="master-field">
                        <label class="master-label" for="maintenanceVendorName">Or a name</label>
                        <input class="master-input" id="maintenanceVendorName" name="vendor_name" maxlength="160"
                            placeholder="The technician, the local workshop">
                    </div>

                    <div class="master-field">
                        <label class="master-label" for="maintenanceInvoice">Invoice / job no.</label>
                        <input class="master-input" id="maintenanceInvoice" name="invoice_no" maxlength="80">
                    </div>

                    <div class="master-field">
                        <label class="master-label" for="maintenanceCost">What it cost</label>
                        <input class="master-input" id="maintenanceCost" type="number" step="0.01" min="0" name="cost"
                            placeholder="0.00">
                        <span class="master-help">A repair bill is revenue spending — it never joins the asset's cost.</span>
                    </div>

                    <div class="master-field">
                        <label class="master-label" for="maintenanceDownFrom">Down from</label>
                        <input class="master-input" id="maintenanceDownFrom" type="date" name="downtime_from">
                    </div>

                    <div class="master-field">
                        <label class="master-label" for="maintenanceDownTo">Back on</label>
                        <input class="master-input" id="maintenanceDownTo" type="date" name="downtime_to">
                    </div>

                    <div class="master-field">
                        <label class="master-label" for="maintenanceNextDue">Next service due</label>
                        <input class="master-input" id="maintenanceNextDue" type="date" name="next_due_on">
                        <span class="master-help">This is what the register's "Service due" count watches.</span>
                    </div>

                    <div class="master-field">
                        <label class="master-label" for="maintenanceStatus">Asset state after this</label>
                        <select class="master-select" id="maintenanceStatus" name="status">
                            <option value="">Leave it as it is</option>
                            @foreach ($statusOptions as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="master-field full">
                        <label class="master-label" for="maintenanceNotes">What was done</label>
                        <textarea class="master-input" id="maintenanceNotes" name="notes" rows="2" maxlength="1000"
                            placeholder="Parts replaced, complaint, what the technician said"></textarea>
                    </div>
                </div>
            </div>

            <div class="master-modal-footer">
                <button type="button" class="master-btn master-btn-light" data-close-modal="assetMaintainModal">Cancel</button>
                <button class="master-btn master-btn-primary" type="submit">
                    <i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i> Log it
                </button>
            </div>
        </form>
    </div>
</div>
