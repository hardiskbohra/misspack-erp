@php
    $costRow = new \App\Models\ShipmentCost(['currency' => $shipment->currency ?: 'INR']);
@endphp

<div class="master-modal" id="costModal" aria-hidden="true"
    data-update-url="{{ route('shipments.costs.update', ['cost' => 'COST_ID']) }}">
    <div class="master-modal-card" role="dialog" aria-modal="true" aria-labelledby="costModalTitle">
        <form method="POST" action="{{ route('shipments.costs.store', $shipment) }}" id="costForm">
            @csrf
            <input type="hidden" name="_method" value="POST" id="costMethod">
            <div class="master-modal-header">
                <div class="master-modal-heading">
                    <span class="master-modal-icon">₹</span>
                    <div>
                        <h3 class="master-modal-title" id="costModalTitle">Add Cost Head</h3>
                        <p class="master-modal-subtitle desktop-only">Freight, duty, CHA — each in its own currency</p>
                    </div>
                </div>
                <button type="button" class="master-modal-close" data-close-modal>×</button>
            </div>

            <div class="master-modal-body">
                <div class="master-modal-grid">
                    <div class="master-field">
                        <label class="master-label" for="costHead">Cost Head <span class="master-required">*</span></label>
                        <select class="master-select" name="cost_head" id="costHead" required>
                            @foreach ($costHeads as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field" id="costLabelField" hidden>
                        <label class="master-label" for="costLabel">Describe it</label>
                        <input class="master-input" name="label" id="costLabel" maxlength="120" placeholder="e.g. Port storage">
                    </div>

                    <div class="master-field">
                        <label class="master-label" for="costAmount">Amount <span class="master-required">*</span></label>
                        <input class="master-input" type="number" step="0.01" min="0" name="amount" id="costAmount" required>
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="costCurrency">Currency <span class="master-required">*</span></label>
                        <select class="master-select" name="currency" id="costCurrency" required>
                            @foreach ($currencyOptions as $key => $label)
                                <option value="{{ $key }}" @selected(($shipment->currency ?: 'INR') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field" id="costRateField">
                        <label class="master-label" for="costRate">Exchange Rate</label>
                        <input class="master-input" type="number" step="0.000001" min="0" name="exchange_rate" id="costRate"
                            placeholder="Required for non-INR bills">
                        <small class="master-sub">INR value = amount × rate (frozen at today's rate).</small>
                    </div>

                    <div class="master-field">
                        <label class="master-label" for="costVendor">Vendor / Forwarder</label>
                        <select class="master-select" name="vendor_id" id="costVendor">
                            <option value="">Not recorded</option>
                            @foreach (\App\Models\Vendor::query()->orderBy('vendor_name')->get(['id', 'vendor_name', 'contact_person_name']) as $vendor)
                                <option value="{{ $vendor->id }}">{{ $vendor->vendor_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="costDocument">Bill / Document No.</label>
                        <input class="master-input" name="document_number" id="costDocument" maxlength="255">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="costIncurredOn">Bill Date</label>
                        <input class="master-input" type="date" name="incurred_on" id="costIncurredOn">
                    </div>

                    <div class="master-field">
                        <label class="master-label" for="costAccount">Paid From Account</label>
                        <select class="master-select" name="paid_account_id" id="costAccount">
                            <option value="">Not paid yet</option>
                            @foreach ($paidAccounts as $account)
                                <option value="{{ $account->id }}">{{ $account->account_name }}</option>
                            @endforeach
                        </select>
                        <small class="master-sub">Setting this posts the INR entry to cashflow.</small>
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="costMode">Payment Mode</label>
                        <select class="master-select" name="payment_mode" id="costMode">
                            @foreach ($paymentModeOptions as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="costPaidOn">Paid On</label>
                        <input class="master-input" type="date" name="paid_on" id="costPaidOn">
                    </div>
                    <div class="master-field full">
                        <label class="master-label" for="costNotes">Notes</label>
                        <textarea class="master-textarea" name="notes" id="costNotes" rows="2"></textarea>
                    </div>
                </div>
            </div>

            <div class="master-modal-footer">
                <button type="button" class="master-btn master-btn-light" data-close-modal>Cancel</button>
                <button type="submit" class="master-btn master-btn-primary" id="costSubmit">Add Cost</button>
            </div>
        </form>
    </div>
</div>
