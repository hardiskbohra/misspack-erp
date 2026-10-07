@php
    /*
     * Off the books. The dialog says the one number that matters before the form
     * is filled — what the asset is carried at today — because that is what the
     * sale is measured against, and it is the number nobody can work out in
     * their head.
     *
     * The writer stops the depreciation curve on that date, closes the hand-over
     * if one is open, and appends the reason to the remarks.
     */
    $action = isset($asset) ? route('assets.dispose', $asset) : '';
@endphp

<div class="master-modal" id="assetDisposeModal" aria-hidden="true">
    <div class="master-modal-card" role="dialog" aria-modal="true" aria-labelledby="assetDisposeTitle">
        <form method="POST" action="{{ $action }}" data-asset-form="dispose">
            @csrf
            @method('PATCH')
            <input type="hidden" name="_dialog" value="dispose">

            <div class="master-modal-header">
                <div class="master-modal-heading">
                    <span class="master-modal-icon" aria-hidden="true"><i class="fa-solid fa-box-archive"></i></span>
                    <div>
                        <h3 class="master-modal-title" id="assetDisposeTitle">Dispose of the asset</h3>
                        <p class="master-modal-subtitle" data-asset-subject>
                            {{ isset($asset) ? $asset->asset_code.' · '.$asset->name : 'Sold, scrapped or written off' }}
                        </p>
                    </div>
                </div>
                <button type="button" class="master-modal-close" data-close-modal="assetDisposeModal"
                    aria-label="Close">&times;</button>
            </div>

            <div class="master-modal-body">
                <div class="master-info-box" data-asset-current>
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                    <span>
                        @if (isset($asset))
                            Carried at <strong>{{ \App\Helpers\CommonHelper::amount($asset->netBookValue(), 'INR') }}</strong>
                            today. Depreciation stops on the date below, and the profit or loss is measured against
                            that figure.
                        @else
                            Depreciation stops on the disposal date, and the profit or loss is measured against
                            the book value on that day.
                        @endif
                    </span>
                </div>

                <div class="master-modal-grid">
                    <div class="master-field">
                        <label class="master-label" for="disposalDate">Disposed on
                            <span class="master-required" aria-hidden="true">*</span></label>
                        <input class="master-input" id="disposalDate" type="date" name="disposal_date" required
                            value="{{ old('disposal_date', now()->format('Y-m-d')) }}">
                    </div>

                    <div class="master-field">
                        <label class="master-label" for="disposalValue">What it fetched</label>
                        <input class="master-input" id="disposalValue" type="number" step="0.01" min="0"
                            name="disposal_value" placeholder="0.00">
                        <span class="master-help">Leave it empty when it was scrapped rather than sold.</span>
                    </div>

                    <div class="master-field full">
                        <label class="master-label" for="disposalReason">Why</label>
                        <input class="master-input" id="disposalReason" name="reason" maxlength="500"
                            placeholder="Sold to …, scrapped after the flood, traded in against …">
                        <span class="master-help">Appended to the asset's remarks, so the reason outlives the conversation.</span>
                    </div>
                </div>
            </div>

            <div class="master-modal-footer">
                <button type="button" class="master-btn master-btn-light" data-close-modal="assetDisposeModal">Cancel</button>
                <button class="master-btn master-btn-danger" type="submit">
                    <i class="fa-solid fa-box-archive" aria-hidden="true"></i> Dispose of it
                </button>
            </div>
        </form>
    </div>
</div>
