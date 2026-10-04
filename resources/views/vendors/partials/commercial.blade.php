<div class="vendor-detail-stack">
    <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-commercial-terms-heading">
        <h2 class="master-section-title" id="vendor-commercial-terms-heading">Commercial terms</h2>
        <div class="master-facts">
            <div class="master-info"><span>Preferred currency</span><strong>{{ $vendor->preferred_currency ?: 'INR' }}</strong></div>
            <div class="master-info"><span>Payment terms</span><strong class="{{ blank($vendor->payment_terms) ? 'master-empty-value' : '' }}">{{ $vendor->payment_terms ?: 'Not on file' }}</strong></div>
            <div class="master-info"><span>Lead time</span><strong class="{{ $vendor->lead_time_days === null ? 'master-empty-value' : '' }}">{{ $vendor->lead_time_days !== null ? $vendor->lead_time_days.' days' : 'Not on file' }}</strong></div>
            <div class="master-info"><span>Minimum order value</span><strong class="{{ $vendor->minimum_order_value === null ? 'master-empty-value' : '' }}">{{ $vendor->minimum_order_value !== null ? $money($vendor->minimum_order_value, $vendor->preferred_currency ?: 'INR') : 'Not on file' }}</strong></div>
        </div>
    </section>

    <div class="vendor-detail-grid">
        <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-compliance-heading">
            <h2 class="master-section-title" id="vendor-compliance-heading">Tax & compliance</h2>
            <div class="master-facts">
                <div class="master-info"><span>GSTIN</span><strong class="{{ blank($vendor->gstin) ? 'master-empty-value' : '' }}">{{ $vendor->gstin ?: 'Not on file' }}</strong></div>
                <div class="master-info"><span>PAN</span><strong class="{{ blank($vendor->pan) ? 'master-empty-value' : '' }}">{{ $vendor->pan ?: 'Not on file' }}</strong></div>
                <div class="master-info"><span>Tax ID</span><strong class="{{ blank($vendor->tax_id) ? 'master-empty-value' : '' }}">{{ $vendor->tax_id ?: 'Not on file' }}</strong></div>
                <div class="master-info"><span>Import/export code (IEC)</span><strong class="{{ blank($vendor->import_export_code) ? 'master-empty-value' : '' }}">{{ $vendor->import_export_code ?: 'Not on file' }}</strong></div>
            </div>
        </section>

        <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-bank-heading">
            <h2 class="master-section-title" id="vendor-bank-heading">Bank & remittance</h2>
            <div class="master-facts">
                <div class="master-info"><span>Bank</span><strong class="{{ blank($vendor->bank_name) ? 'master-empty-value' : '' }}">{{ $vendor->bank_name ?: 'Not on file' }}</strong></div>
                <div class="master-info"><span>Account holder</span><strong class="{{ blank($vendor->account_holder_name) ? 'master-empty-value' : '' }}">{{ $vendor->account_holder_name ?: 'Not on file' }}</strong></div>
                <div class="master-info"><span>Account number</span><strong class="{{ blank($vendor->account_number) ? 'master-empty-value' : '' }}">{{ $vendor->account_number ?: 'Not on file' }}</strong></div>
                <div class="master-info"><span>IFSC code</span><strong class="{{ blank($vendor->ifsc_code) ? 'master-empty-value' : '' }}">{{ $vendor->ifsc_code ?: 'Not on file' }}</strong></div>
                <div class="master-info"><span>SWIFT code</span><strong class="{{ blank($vendor->swift_code) ? 'master-empty-value' : '' }}">{{ $vendor->swift_code ?: 'Not on file' }}</strong></div>
                <div class="master-info"><span>Bank branch</span><strong class="{{ blank($vendor->bank_branch) ? 'master-empty-value' : '' }}">{{ $vendor->bank_branch ?: 'Not on file' }}</strong></div>
            </div>
        </section>
    </div>

    <p class="vendor-tab-footnote">These are reference details for the purchasing team. Payment entries and their reconciled INR values are available in the Payments and Statement tabs.</p>
</div>
