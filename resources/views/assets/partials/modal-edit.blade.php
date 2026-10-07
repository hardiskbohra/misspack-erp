@php
    /* Changing an asset — the record's own door, on the record's own page.
       It is the same field partial as the list's dialog, filled from the asset,
       so there is one definition of what a fixed asset has and two ways in. */
@endphp

<div class="master-modal" id="assetEditModal" aria-hidden="true">
    <div class="master-modal-card is-wide" role="dialog" aria-modal="true"
        aria-labelledby="assetEditTitle">
        <form method="POST" action="{{ route('assets.update', $asset) }}" data-asset-form="edit">
            @csrf
            @method('PUT')
            <input type="hidden" name="_dialog" value="edit">

            <div class="master-modal-header">
                <div class="master-modal-heading">
                    <span class="master-modal-icon" aria-hidden="true"><i class="fa-solid fa-pen-to-square"></i></span>
                    <div>
                        <h3 class="master-modal-title" id="assetEditTitle">Change {{ $asset->asset_code }}</h3>
                        <p class="master-modal-subtitle">
                            Correcting a figure here changes the depreciation from that day on — the record is
                            one asset, not a copy of one.
                        </p>
                    </div>
                </div>
                <button type="button" class="master-modal-close" data-close-modal="assetEditModal"
                    aria-label="Close">&times;</button>
            </div>

            <div class="master-modal-body">
                @if ($errors->any())
                    <div class="master-info-box is-danger" role="alert">
                        <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                        {{ $errors->first() }}
                    </div>
                @endif

                @include('assets.partials.asset-form', ['asset' => $asset, 'suffix' => 'Edit'])
            </div>

            <div class="master-modal-footer">
                <button type="button" class="master-btn master-btn-light" data-close-modal="assetEditModal">Cancel</button>
                <button class="master-btn master-btn-primary" type="submit">
                    <i class="fa-solid fa-check" aria-hidden="true"></i> Save the changes
                </button>
            </div>
        </form>
    </div>
</div>
