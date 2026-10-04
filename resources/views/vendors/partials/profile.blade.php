@php($empty = fn ($value) => blank($value))
<section class="master-tab-panel" id="vendor-panel-profile" role="tabpanel" aria-labelledby="vendor-tab-profile">
    <div class="vendor-detail-grid">
        <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-profile-identity-heading">
            <h2 class="vendor-detail-title" id="vendor-profile-identity-heading">Identity</h2>
            <div class="master-facts">
                <div class="master-info"><span>Vendor number</span><strong @class(['master-empty-value' => $empty($vendor->vendor_number)])>{{ $vendor->vendor_number ?: 'Not on file' }}</strong></div>
                <div class="master-info"><span>Vendor name</span><strong>{{ $vendor->vendor_name }}</strong></div>
                <div class="master-info"><span>Brand name</span><strong @class(['master-empty-value' => $empty($vendor->brand_name)])>{{ $vendor->brand_name ?: 'Not on file' }}</strong></div>
                <div class="master-info"><span>Vendor type</span><strong>{{ $vendor->typeLabel() }}</strong></div>
                <div class="master-info"><span>Category</span><strong @class(['master-empty-value' => $empty($vendor->category)])>{{ $vendor->category ?: 'Not on file' }}</strong></div>
                <div class="master-info"><span>Status</span><strong><span class="master-badge status-{{ str_replace('_', '-', $vendor->status) }}">{{ $vendor->statusLabel() }}</span></strong></div>
                <div class="master-info"><span>Rating</span><strong @class(['master-empty-value' => ! $vendor->rating])>{{ $vendor->rating ? str_repeat('★', $vendor->rating).' ('.$vendor->rating.' of 5)' : 'Not rated' }}</strong></div>
                <div class="master-info"><span>Record created</span><strong>{{ $vendor->created_at?->format('d M Y') ?: '—' }}</strong></div>
                <div class="master-info"><span>Added by</span><strong @class(['master-empty-value' => ! $vendor->creator])>{{ $vendor->creator?->name ?: 'Not on file' }}</strong></div>
                <div class="master-info"><span>Last updated</span><strong>{{ $vendor->updated_at?->format('d M Y, h:i A') ?: '—' }}</strong></div>
            </div>
            <div class="vendor-detail-actions">
                <a class="master-btn master-btn-soft master-btn-sm" href="{{ route('vendors.edit', $vendor) }}">
                    <i class="fa-solid fa-pen" aria-hidden="true"></i> Edit these details
                </a>
            </div>
        </section>

        <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-profile-notes-heading">
            <h2 class="vendor-detail-title" id="vendor-profile-notes-heading">Internal notes</h2>
            <div class="master-facts">
                <div class="master-info is-wide"><span>Notes</span><strong class="vendor-detail-notes {{ $empty($vendor->notes) ? 'master-empty-value' : '' }}">{{ $vendor->notes ?: 'No notes on file' }}</strong></div>
            </div>
            <p class="vendor-detail-help">Notes are for your team only. They are never printed on vendor-facing paperwork.</p>
        </section>
    </div>
</section>
