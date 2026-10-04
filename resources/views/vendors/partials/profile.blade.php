<div class="vendor-detail-grid">
    <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-profile-heading">
        <h2 class="master-section-title" id="vendor-profile-heading">Business profile</h2>
        <div class="master-facts">
            <div class="master-info"><span>Company name</span><strong>{{ $vendor->vendor_name }}</strong></div>
            <div class="master-info"><span>Vendor number</span><strong class="{{ blank($vendor->vendor_number) ? 'master-empty-value' : '' }}">{{ $vendor->vendor_number ?: 'Not assigned' }}</strong></div>
            <div class="master-info"><span>Brand name</span><strong class="{{ blank($vendor->brand_name) ? 'master-empty-value' : '' }}">{{ $vendor->brand_name ?: 'Not on file' }}</strong></div>
            <div class="master-info"><span>Vendor type</span><strong>{{ $vendor->typeLabel() }}</strong></div>
            <div class="master-info"><span>Category</span><strong class="{{ blank($vendor->category) ? 'master-empty-value' : '' }}">{{ $vendor->category ?: 'Not on file' }}</strong></div>
            <div class="master-info"><span>Account status</span><strong><span class="master-badge vendor-status vendor-status-{{ $statusClass }}">{{ $vendor->statusLabel() }}</span></strong></div>
            <div class="master-info"><span>Supplier rating</span>
                @if ($vendor->rating)
                    <strong><span class="vendor-rating" aria-label="{{ $vendor->rating }} out of 5 stars">{{ str_repeat('★', $vendor->rating) }}<span>{{ str_repeat('☆', 5 - $vendor->rating) }}</span></span> <span class="vendor-muted-inline">{{ $vendor->rating }} of 5</span></strong>
                @else
                    <strong class="master-empty-value">Not rated</strong>
                @endif
            </div>
            <div class="master-info"><span>Record created</span><strong>{{ $vendor->created_at?->format('d M Y') ?: '—' }}</strong></div>
            <div class="master-info is-wide"><span>Created by</span><strong class="{{ blank($vendor->creator?->name) ? 'master-empty-value' : '' }}">{{ $vendor->creator?->name ?? $vendor->creator?->email ?? 'Not on file' }}</strong></div>
        </div>
    </section>

    <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-location-heading">
        <h2 class="master-section-title" id="vendor-location-heading">Location & online presence</h2>
        <div class="master-facts">
            <div class="master-info is-wide"><span>Business address</span>
                @if ($vendorAddress)
                    <address class="vendor-detail-address">{{ $vendorAddress }}</address>
                @else
                    <strong class="master-empty-value">Not on file</strong>
                @endif
            </div>
            <div class="master-info"><span>City</span><strong class="{{ blank($vendor->city) ? 'master-empty-value' : '' }}">{{ $vendor->city ?: 'Not on file' }}</strong></div>
            <div class="master-info"><span>State / province</span><strong class="{{ blank($vendor->state) ? 'master-empty-value' : '' }}">{{ $vendor->state ?: 'Not on file' }}</strong></div>
            <div class="master-info"><span>Country</span><strong class="{{ blank($vendor->country) ? 'master-empty-value' : '' }}">{{ $vendor->country ?: 'Not on file' }}</strong></div>
            <div class="master-info"><span>Postal code</span><strong class="{{ blank($vendor->pincode) ? 'master-empty-value' : '' }}">{{ $vendor->pincode ?: 'Not on file' }}</strong></div>
            <div class="master-info"><span>Website</span><strong class="vendor-profile-url {{ blank($vendor->website) ? 'master-empty-value' : '' }}">{{ $vendor->website ?: 'Not on file' }}</strong></div>
            <div class="master-info is-wide"><span>Alibaba profile</span><strong class="vendor-profile-url {{ blank($vendor->alibaba_link) ? 'master-empty-value' : '' }}">{{ $vendor->alibaba_link ?: 'Not on file' }}</strong></div>
        </div>
    </section>
</div>
