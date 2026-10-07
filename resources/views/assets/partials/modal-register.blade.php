@php
    /* The register's own dialog: a new asset, written from the list. Changing one
       happens on the asset's page (there the fields can come back filled without
       thirty rows carrying thirty forms), and both dialogs are the same partial. */
@endphp

<div class="master-modal" id="assetRegisterModal" aria-hidden="true">
    <div class="master-modal-card is-wide" role="dialog" aria-modal="true"
        aria-labelledby="assetRegisterTitle">
        <form method="POST" action="{{ route('assets.store') }}" data-asset-form="register">
            @csrf
            <input type="hidden" name="_dialog" value="assetRegisterModal">

            <div class="master-modal-header">
                <div class="master-modal-heading">
                    <span class="master-modal-icon" aria-hidden="true"><i class="fa-solid fa-industry"></i></span>
                    <div>
                        <h3 class="master-modal-title" id="assetRegisterTitle">Register an asset</h3>
                        <p class="master-modal-subtitle">
                            What it is, what it cost and where it lives. Depreciation follows from the purchase
                            date and the class — nothing here has to be worked out by hand.
                        </p>
                    </div>
                </div>
                <button type="button" class="master-modal-close" data-close-modal="assetRegisterModal"
                    aria-label="Close">&times;</button>
            </div>

            <div class="master-modal-body">
                @if ($errors->any())
                    <div class="master-info-box is-danger" role="alert">
                        <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                        {{ $errors->first() }}
                    </div>
                @endif

                @include('assets.partials.asset-form', ['asset' => null, 'suffix' => 'New'])
            </div>

            <div class="master-modal-footer">
                <button type="button" class="master-btn master-btn-light" data-close-modal="assetRegisterModal">Cancel</button>
                <button class="master-btn master-btn-primary" type="submit">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Add it to the register
                </button>
            </div>
        </form>
    </div>
</div>
