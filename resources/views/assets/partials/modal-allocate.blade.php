@php
    /*
     * Hand an asset over — to a person, to a place, or both.
     *
     * This is the door that keeps three things true at once: the hand-over
     * history grows a row, the asset's custodian/location/department move with
     * it, and the asset stops being "in store" if that is what it was. The
     * writing is `AssetIntake::allocate()`, which is the only thing in the
     * application that does any of those three.
     *
     * On the record the asset is known and the form posts where it belongs; on
     * the list the row's menu names the asset in `data-action` and `assets.js`
     * points the form at it — one dialog rather than one per row.
     */
    $action = isset($asset) ? route('assets.allocate', $asset) : '';
@endphp

<div class="master-modal" id="assetAllocateModal" aria-hidden="true">
    <div class="master-modal-card" role="dialog" aria-modal="true" aria-labelledby="assetAllocateTitle">
        <form method="POST" action="{{ $action }}" data-asset-form="allocate">
            @csrf
            <input type="hidden" name="_dialog" value="allocate">

            <div class="master-modal-header">
                <div class="master-modal-heading">
                    <span class="master-modal-icon" aria-hidden="true"><i class="fa-solid fa-hand-holding-hand"></i></span>
                    <div>
                        <h3 class="master-modal-title" id="assetAllocateTitle">Hand it over</h3>
                        <p class="master-modal-subtitle" data-asset-subject>
                            {{ isset($asset) ? $asset->asset_code.' · '.$asset->name : 'Who has it from when' }}
                        </p>
                    </div>
                </div>
                <button type="button" class="master-modal-close" data-close-modal="assetAllocateModal"
                    aria-label="Close">&times;</button>
            </div>

            <div class="master-modal-body">
                <div class="master-info-box" data-asset-current>
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    <span>
                        @if (isset($asset))
                            Currently {{ $asset->holderLabel() }} at {{ $asset->placeLabel() }}.
                        @else
                            Picking a person moves the asset to them; the older hand-over closes itself.
                        @endif
                    </span>
                </div>

                <div class="master-modal-grid">
                    <div class="master-field">
                        <label class="master-label" for="allocatedTo">Hand it to</label>
                        <select class="master-select" id="allocatedTo" name="allocated_to">
                            <option value="">Nobody on file</option>
                            @foreach ($peopleOptions as $person)
                                <option value="{{ $person->id }}">{{ $person->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="master-field">
                        <label class="master-label" for="allocatedName">Or a name</label>
                        <input class="master-input" id="allocatedName" name="holder_name" maxlength="160"
                            placeholder="A client's site engineer, a contractor">
                        <span class="master-help">For a person the ERP has no login for — the history still names them.</span>
                    </div>

                    <div class="master-field">
                        <label class="master-label" for="allocatedLocation">Location</label>
                        <input class="master-input" id="allocatedLocation" name="location" maxlength="120"
                            list="faLocationList" value="{{ old('location', $asset->location ?? '') }}">
                    </div>

                    <div class="master-field">
                        <label class="master-label" for="allocatedDepartment">Department</label>
                        <input class="master-input" id="allocatedDepartment" name="department" maxlength="80"
                            value="{{ old('department', $asset->department ?? '') }}">
                    </div>

                    <div class="master-field">
                        <label class="master-label" for="allocatedOn">Handed over on
                            <span class="master-required" aria-hidden="true">*</span></label>
                        <input class="master-input" id="allocatedOn" type="date" name="allocated_on" required
                            value="{{ old('allocated_on', now()->format('Y-m-d')) }}">
                    </div>

                    <div class="master-field full">
                        <label class="master-label" for="allocatedNote">Note</label>
                        <input class="master-input" id="allocatedNote" name="note" maxlength="500"
                            placeholder="What went with it, what it is for">
                    </div>
                </div>
            </div>

            <div class="master-modal-footer">
                <button type="button" class="master-btn master-btn-light" data-close-modal="assetAllocateModal">Cancel</button>
                <button class="master-btn master-btn-primary" type="submit">
                    <i class="fa-solid fa-hand-holding-hand" aria-hidden="true"></i> Hand it over
                </button>
            </div>
        </form>
    </div>
</div>
