@php
    /*
     * The physical verification — one tap after walking the floor. The date and
     * the condition are the whole record, and the person is the one signed in:
     * "who checked it" is not a field somebody types, it is the session.
     */
    $action = isset($asset) ? route('assets.verify', $asset) : '';
@endphp

<div class="master-modal" id="assetVerifyModal" aria-hidden="true">
    <div class="master-modal-card" role="dialog" aria-modal="true" aria-labelledby="assetVerifyTitle">
        <form method="POST" action="{{ $action }}" data-asset-form="verify">
            @csrf
            @method('PATCH')
            <input type="hidden" name="_dialog" value="assetVerifyModal">

            <div class="master-modal-header">
                <div class="master-modal-heading">
                    <span class="master-modal-icon" aria-hidden="true"><i class="fa-solid fa-clipboard-check"></i></span>
                    <div>
                        <h3 class="master-modal-title" id="assetVerifyTitle">Verified in person</h3>
                        <p class="master-modal-subtitle" data-asset-subject>
                            {{ isset($asset) ? $asset->asset_code.' · '.$asset->name : 'I have seen it, on this date, in this condition' }}
                        </p>
                    </div>
                </div>
                <button type="button" class="master-modal-close" data-close-modal="assetVerifyModal"
                    aria-label="Close">&times;</button>
            </div>

            <div class="master-modal-body">
                <div class="master-info-box">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    <span data-asset-current>
                        @if (isset($asset))
                            Last checked {{ $asset->last_verified_on?->format('d M Y') ?: 'never' }}
                            @if ($asset->verifier)
                                by {{ $asset->verifier->name }}
                            @endif
                            — {{ $asset->verificationLabel() }}.
                        @else
                            The register stamps who checked it and when, and the reading an auditor asks for is
                            this column.
                        @endif
                    </span>
                </div>

                <div class="master-modal-grid">
                    <div class="master-field">
                        <label class="master-label" for="verifiedOn">Checked on</label>
                        <input class="master-input" id="verifiedOn" type="date" name="last_verified_on"
                            value="{{ old('last_verified_on', now()->format('Y-m-d')) }}">
                    </div>

                    <div class="master-field">
                        <label class="master-label" for="verifiedCondition">Condition found</label>
                        <select class="master-select" id="verifiedCondition" name="condition">
                            <option value="">Leave the condition as it is</option>
                            @foreach ($conditionOptions as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="master-modal-footer">
                <button type="button" class="master-btn master-btn-light" data-close-modal="assetVerifyModal">Cancel</button>
                <button class="master-btn master-btn-primary" type="submit">
                    <i class="fa-solid fa-clipboard-check" aria-hidden="true"></i> Record it
                </button>
            </div>
        </form>
    </div>
</div>
