@php
    /* Take an asset back: the open hand-over closes on the day and with the
       condition it came back in, and the asset stands in the register with no
       holder. The door refuses when nothing is out — the writer in
       `AssetIntake::returnAsset()` does the refusing, not this form. */
    $action = isset($asset) ? route('assets.takeBack', $asset) : '';
@endphp

<div class="master-modal" id="assetReturnModal" aria-hidden="true">
    <div class="master-modal-card" role="dialog" aria-modal="true" aria-labelledby="assetReturnTitle">
        <form method="POST" action="{{ $action }}" data-asset-form="return">
            @csrf
            @method('PATCH')
            <input type="hidden" name="_dialog" value="assetReturnModal">

            <div class="master-modal-header">
                <div class="master-modal-heading">
                    <span class="master-modal-icon" aria-hidden="true"><i class="fa-solid fa-rotate-left"></i></span>
                    <div>
                        <h3 class="master-modal-title" id="assetReturnTitle">Take it back</h3>
                        <p class="master-modal-subtitle" data-asset-subject>
                            {{ isset($asset) ? $asset->asset_code.' · '.$asset->name : 'Close the open hand-over' }}
                        </p>
                    </div>
                </div>
                <button type="button" class="master-modal-close" data-close-modal="assetReturnModal"
                    aria-label="Close">&times;</button>
            </div>

            <div class="master-modal-body">
                <div class="master-info-box">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    <span data-asset-current>
                        @if (isset($asset))
                            {{ $asset->holderLabel() }} has held it since {{ $asset->openAllocation?->allocated_on?->format('d M Y') ?: '—' }}.
                        @else
                            The hand-over is closed as of the date below, and the condition it came back in is
                            written down beside it.
                        @endif
                    </span>
                </div>

                <div class="master-modal-grid">
                    <div class="master-field">
                        <label class="master-label" for="returnedOn">Came back on</label>
                        <input class="master-input" id="returnedOn" type="date" name="returned_on"
                            value="{{ old('returned_on', now()->format('Y-m-d')) }}">
                    </div>

                    <div class="master-field">
                        <label class="master-label" for="returnedCondition">Condition it came back in</label>
                        <select class="master-select" id="returnedCondition" name="condition">
                            <option value="">Not noted</option>
                            @foreach ($conditionOptions as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <span class="master-help">Written into the asset's condition as well — it is the latest news about it.</span>
                    </div>

                    <div class="master-field">
                        <label class="master-label" for="returnedLocation">Back at</label>
                        <input class="master-input" id="returnedLocation" name="location" maxlength="120"
                            list="faLocationList" value="{{ old('location') }}" placeholder="Workshop, store room">
                    </div>

                    <div class="master-field">
                        <label class="master-label" for="returnedDepartment">Department</label>
                        <input class="master-input" id="returnedDepartment" name="department" maxlength="80"
                            value="{{ old('department') }}">
                    </div>
                </div>
            </div>

            <div class="master-modal-footer">
                <button type="button" class="master-btn master-btn-light" data-close-modal="assetReturnModal">Cancel</button>
                <button class="master-btn master-btn-primary" type="submit">
                    <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Take it back
                </button>
            </div>
        </form>
    </div>
</div>
