@php($empty = fn ($value) => blank($value))
<section class="master-tab-panel" id="vendor-panel-commercial" role="tabpanel" aria-labelledby="vendor-tab-commercial">
    <div class="vendor-detail-grid">
        <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-tax-heading">
            <h2 class="vendor-detail-title" id="vendor-tax-heading">Tax registrations</h2>
            <div class="master-facts">
                <div class="master-info"><span>GSTIN</span><strong @class(['master-empty-value' => $empty($vendor->gstin)])>{{ $vendor->gstin ?: 'Not on file' }}</strong></div>
                <div class="master-info"><span>PAN</span><strong @class(['master-empty-value' => $empty($vendor->pan)])>{{ $vendor->pan ?: 'Not on file' }}</strong></div>
                <div class="master-info"><span>Tax ID</span><strong @class(['master-empty-value' => $empty($vendor->tax_id)])>{{ $vendor->tax_id ?: 'Not on file' }}</strong></div>
                <div class="master-info"><span>Import / export code</span><strong @class(['master-empty-value' => $empty($vendor->import_export_code)])>{{ $vendor->import_export_code ?: 'Not on file' }}</strong></div>
            </div>
        </section>

        <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-bank-heading">
            <h2 class="vendor-detail-title" id="vendor-bank-heading">Bank details</h2>
            <div class="master-facts">
                <div class="master-info"><span>Bank</span><strong @class(['master-empty-value' => $empty($vendor->bank_name)])>{{ $vendor->bank_name ?: 'Not on file' }}</strong></div>
                <div class="master-info"><span>Account holder</span><strong @class(['master-empty-value' => $empty($vendor->account_holder_name)])>{{ $vendor->account_holder_name ?: 'Not on file' }}</strong></div>
                <div class="master-info"><span>Account number</span><strong @class(['master-empty-value' => $empty($vendor->account_number)])>{{ $vendor->account_number ?: 'Not on file' }}</strong></div>
                <div class="master-info"><span>IFSC</span><strong @class(['master-empty-value' => $empty($vendor->ifsc_code)])>{{ $vendor->ifsc_code ?: 'Not on file' }}</strong></div>
                <div class="master-info"><span>SWIFT</span><strong @class(['master-empty-value' => $empty($vendor->swift_code)])>{{ $vendor->swift_code ?: 'Not on file' }}</strong></div>
                <div class="master-info"><span>Branch</span><strong @class(['master-empty-value' => $empty($vendor->bank_branch)])>{{ $vendor->bank_branch ?: 'Not on file' }}</strong></div>
            </div>
        </section>

        <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-terms-heading">
            <h2 class="vendor-detail-title" id="vendor-terms-heading">Commercial terms</h2>
            <div class="master-facts">
                <div class="master-info"><span>Preferred currency</span><strong>{{ $vendor->preferred_currency ?: 'INR' }}</strong></div>
                <div class="master-info"><span>Payment terms</span><strong @class(['master-empty-value' => $empty($vendor->payment_terms)])>{{ $vendor->payment_terms ?: 'Not on file' }}</strong></div>
                <div class="master-info"><span>Lead time</span><strong @class(['master-empty-value' => $vendor->lead_time_days === null])>{{ $vendor->lead_time_days !== null ? $vendor->lead_time_days.' days' : 'Not set' }}</strong></div>
                <div class="master-info"><span>Minimum order value</span><strong @class(['master-empty-value' => $vendor->minimum_order_value === null])>{{ $vendor->minimum_order_value !== null ? $money($vendor->minimum_order_value, $vendor->preferred_currency ?: 'INR') : 'Not set' }}</strong></div>
                <div class="master-info"><span>Rating</span><strong @class(['master-empty-value' => ! $vendor->rating])>{{ $vendor->rating ? str_repeat('★', $vendor->rating).' ('.$vendor->rating.' of 5)' : 'Not rated' }}</strong></div>
            </div>
            <div class="vendor-detail-actions">
                <a class="master-btn master-btn-soft master-btn-sm" href="{{ route('vendors.edit', $vendor) }}#vendor-commercial">
                    <i class="fa-solid fa-pen" aria-hidden="true"></i> Edit tax, bank & terms
                </a>
            </div>
        </section>

        <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-currency-accounts-heading">
            <h2 class="vendor-detail-title" id="vendor-currency-accounts-heading">Balances by currency</h2>
            @if (count($currencySummary))
                <div class="master-facts">
                    @foreach ($currencySummary as $currency => $row)
                        <div class="master-info"><span>{{ $currency }} billed</span><strong>{{ $money($row['credit'], $currency) }}</strong></div>
                        <div class="master-info"><span>{{ $currency }} paid</span><strong>{{ $money($row['debit'], $currency) }}</strong></div>
                        <div class="master-info"><span>{{ $currency }} balance</span><strong @class(['vendor-amount-negative' => $row['balance'] > 0])>{{ $money($row['balance'], $currency) }}</strong></div>
                    @endforeach
                </div>
            @else
                <p class="vendor-empty-text">No vendor-currency entries yet, so there is nothing to balance per currency.</p>
            @endif
            <p class="vendor-detail-help">A vendor billed in RMB and paid in rupees holds two balances. They are kept apart and never
                added together.</p>
        </section>
    </div>
</section>
