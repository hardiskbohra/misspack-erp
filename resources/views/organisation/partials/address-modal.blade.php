<div class="master-modal" id="orgAddressModal" aria-hidden="true">
    <div class="master-modal-card" role="dialog" aria-modal="true" aria-labelledby="orgAddressTitle">
        <form method="POST" action="{{ route('organisation.addresses.store') }}" id="orgAddressForm"
            data-store="{{ route('organisation.addresses.store') }}"
            data-add-title="Add an address"
            data-edit-title="Edit address"
            data-add-sub="Billing, shipping or a branch."
            data-edit-sub="Changes apply to new documents. Issued paperwork keeps its snapshot.">
            @csrf
            <input type="hidden" name="_method" value="PUT" data-org-method disabled>
            <div class="master-modal-header">
                <div class="master-modal-heading">
                    <span class="master-modal-icon" aria-hidden="true"><i class="fas fa-map-marker-alt"></i></span>
                    <div>
                        <h3 class="master-modal-title" id="orgAddressTitle" data-org-title>Add an address</h3>
                        <p class="master-modal-subtitle" data-org-subtitle>Billing, shipping or a branch.</p>
                    </div>
                </div>
                <button type="button" class="master-modal-close" data-close-modal="orgAddressModal" aria-label="Close">&times;</button>
            </div>
            <div class="master-modal-body">
                @include('organisation.partials.address-fields', ['address' => null, 'kinds' => $kinds])
            </div>
            <div class="master-modal-footer">
                <button type="button" class="master-btn master-btn-ghost" data-close-modal="orgAddressModal">Cancel</button>
                <button type="submit" class="master-btn master-btn-primary">Save address</button>
            </div>
        </form>
    </div>
</div>
