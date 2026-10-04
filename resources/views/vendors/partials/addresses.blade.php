@php
    $empty = fn ($value) => blank($value);
    $fullAddress = collect([$vendor->address, $vendor->city, $vendor->state, $vendor->country, $vendor->pincode])
        ->filter()
        ->implode(', ');
@endphp
<section class="master-tab-panel" id="vendor-panel-addresses" role="tabpanel" aria-labelledby="vendor-tab-addresses">
    <div class="vendor-detail-grid">
        <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-address-main-heading">
            <h2 class="vendor-detail-title" id="vendor-address-main-heading">Registered address</h2>
            <div class="master-facts">
                <div class="master-info is-wide"><span>Address</span>
                    @if ($fullAddress)
                        <address class="vendor-detail-address">{{ $vendor->address ?: 'Address line not on file' }}<br>
                            {{ collect([$vendor->city, $vendor->state])->filter()->implode(', ') }}@if ($vendor->pincode) {{ $vendor->pincode }}@endif<br>
                            {{ $vendor->country ?: 'Country not on file' }}</address>
                    @else
                        <strong class="master-empty-value">Not on file</strong>
                    @endif
                </div>
                <div class="master-info"><span>City</span><strong @class(['master-empty-value' => $empty($vendor->city)])>{{ $vendor->city ?: 'Not on file' }}</strong></div>
                <div class="master-info"><span>State / province</span><strong @class(['master-empty-value' => $empty($vendor->state)])>{{ $vendor->state ?: 'Not on file' }}</strong></div>
                <div class="master-info"><span>Country</span><strong @class(['master-empty-value' => $empty($vendor->country)])>{{ $vendor->country ?: 'Not on file' }}</strong></div>
                <div class="master-info"><span>Postal code</span><strong @class(['master-empty-value' => $empty($vendor->pincode)])>{{ $vendor->pincode ?: 'Not on file' }}</strong></div>
            </div>
        </section>

        <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-address-use-heading">
            <h2 class="vendor-detail-title" id="vendor-address-use-heading">Where this address is used</h2>
            <p class="vendor-detail-help">The vendor address is the shipping origin on purchase paperwork and the source line on
                shipping marks. Keep it as the supplier writes it.</p>
            <div class="master-facts">
                <div class="master-info"><span>Shipments</span><strong>{{ number_format($summary['shipments_count']) }}</strong></div>
                <div class="master-info"><span>Project rows</span><strong>{{ number_format($summary['project_products_count']) }}</strong></div>
            </div>
            <div class="vendor-detail-actions">
                <a class="master-btn master-btn-soft master-btn-sm" href="{{ route('vendors.edit', $vendor) }}#vendor-addresses">
                    <i class="fa-solid fa-pen" aria-hidden="true"></i> Edit the address
                </a>
            </div>
        </section>
    </div>
</section>
