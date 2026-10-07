@php
    /*
     * Everything the register knows about one asset, in the four blocks the
     * office's spreadsheet had — identity, purchase, custody, compliance — plus
     * the recipe and the doors.
     *
     * The columns that are empty print as "—" rather than as nothing: a blank
     * space reads as "we have it and it is empty" in a register, and the point of
     * this page is to answer "do we know that", not just "what is it".
     */
    $line = fn ($value) => filled($value) ? $value : '—';
    /* Read once, at the top: an inline @php inside the disposed notice below
       would be a second directive block in one template, and the balance the
       Blade check reads is on the pair of them. */
    $gain = $asset->disposalGainLoss();
@endphp

<div class="master-tab-panel ast-panel">

    @if ($asset->isDisposed())
        <div class="master-info-box">
            <i class="fa-solid fa-box-archive" aria-hidden="true"></i>
            <span>
                Disposed on {{ $asset->disposal_date->format('d M Y') }} for
                {{ $asset->disposal_value === null ? 'nothing (scrapped)' : $money((float) $asset->disposal_value) }}.
                It carried a book value of {{ $money((float) $asset->bookValueOnDisposal()) }} that day —
                a {{ $gain !== null && $gain < 0 ? 'loss' : 'profit' }} of {{ $money(abs((float) $gain)) }}.
                It stays on the register as history and is charged no further depreciation.
            </span>
        </div>
    @endif

    <div class="ast-panel-grid">

        {{-- ------------------------------------------------ identity --}}
        <section class="master-card master-card--flat ast-block">
            <div class="ast-block-head">
                <div>
                    <p class="master-eyebrow">Identity</p>
                    <h2 class="master-section-title">What it is</h2>
                </div>
            </div>

            <dl class="ast-facts">
                <div><dt>Asset ID</dt><dd class="ast-mono">{{ $asset->asset_code }}</dd></div>
                <div><dt>Name</dt><dd>{{ $asset->name }}</dd></div>
                <div><dt>Class</dt><dd>{{ $line($asset->category?->name) }}</dd></div>
                <div><dt>Make / brand</dt><dd>{{ $line($asset->make) }}</dd></div>
                <div><dt>Model</dt><dd>{{ $line($asset->model) }}</dd></div>
                <div><dt>Serial / IMEI</dt><dd class="ast-mono">{{ $line($asset->serial_no) }}</dd></div>
                <div class="full">
                    <dt>Description / specifications</dt>
                    {{-- The office's own line breaks, kept — the same rule the
                         specifications on an invoice follow. --}}
                    <dd class="ast-pre">{{ $line($asset->description) }}</dd>
                </div>
                <div class="full"><dt>Remarks</dt><dd class="ast-pre">{{ $line($asset->remarks) }}</dd></div>
            </dl>
        </section>

        {{-- ------------------------------------------------ purchase --}}
        <section class="master-card master-card--flat ast-block">
            <div class="ast-block-head">
                <div>
                    <p class="master-eyebrow">Purchase</p>
                    <h2 class="master-section-title">What it cost</h2>
                </div>
            </div>

            <dl class="ast-facts">
                <div><dt>Purchase date</dt><dd>{{ $line($asset->purchase_date?->format('d M Y')) }}</dd></div>
                <div><dt>Supplier</dt><dd>{{ $line($asset->supplierLabel()) }}</dd></div>
                <div><dt>Invoice no.</dt><dd>{{ $line($asset->invoice_no) }}</dd></div>
                <div><dt>Invoice date</dt><dd>{{ $line($asset->invoice_date?->format('d M Y')) }}</dd></div>
                <div><dt>Purchase cost</dt><dd class="ast-money">{{ $money((float) $asset->cost) }}</dd></div>
                <div><dt>GST</dt><dd class="ast-money">{{ $money((float) $asset->gst_amount) }}</dd></div>
                <div><dt>Total cost</dt><dd class="ast-money">{{ $money($asset->totalCost()) }}</dd></div>
                <div>
                    <dt>Capitalised at</dt>
                    <dd class="ast-money">{{ $money($asset->capitalisedCost()) }}</dd>
                </div>
                <div class="full">
                    <dt>GST treatment</dt>
                    <dd>
                        {{ $asset->claimsInputCredit()
                            ? 'Input credit claimed, so the cost alone is depreciated.'
                            : 'GST capitalised into the cost, so the invoice total is depreciated.' }}
                    </dd>
                </div>
            </dl>
        </section>

        {{-- ------------------------------------------------ custody --}}
        <section class="master-card master-card--flat ast-block">
            <div class="ast-block-head">
                <div>
                    <p class="master-eyebrow">Custody</p>
                    <h2 class="master-section-title">Who answers for it</h2>
                </div>
                <div class="ast-block-actions">
                    @unless ($asset->isDisposed())
                        <button type="button" class="master-btn master-btn-soft master-btn-sm"
                            data-open-asset-modal="allocate">
                            <i class="fa-solid fa-hand-holding-hand" aria-hidden="true"></i> Hand over
                        </button>
                        @if ($asset->openAllocation)
                            <button type="button" class="master-btn master-btn-light master-btn-sm"
                                data-open-asset-modal="return">
                                <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Take back
                            </button>
                        @endif
                    @endunless
                </div>
            </div>

            <dl class="ast-facts">
                <div><dt>Custodian</dt><dd>{{ $asset->holderLabel() }}</dd></div>
                <div><dt>Location</dt><dd>{{ $asset->placeLabel() }}</dd></div>
                <div><dt>Department</dt><dd>{{ $line($asset->department) }}</dd></div>
                <div><dt>Status</dt>
                    <dd><span class="core-badge core-badge-{{ $asset->stateTone() }}">{{ $asset->stateLabel() }}</span></dd>
                </div>
                @if ($asset->openAllocation)
                    <div class="full">
                        <dt>Open hand-over</dt>
                        <dd>
                            Held since {{ $asset->openAllocation->allocated_on?->format('d M Y') }}
                            ({{ $asset->openAllocation->daysHeld() }} days)
                            @if ($asset->openAllocation->note)
                                — {{ $asset->openAllocation->note }}
                            @endif
                        </dd>
                    </div>
                @endif
            </dl>

            <div class="ast-block-links">
                <a href="{{ $recordUrl('allocation') }}">
                    The full hand-over history
                    <span class="master-tab-count">{{ number_format($tabCounts['allocation']) }}</span>
                </a>
            </div>
        </section>

        {{-- ------------------------------------------------ compliance --}}
        <section class="master-card master-card--flat ast-block">
            <div class="ast-block-head">
                <div>
                    <p class="master-eyebrow">Compliance</p>
                    <h2 class="master-section-title">The readings an auditor asks for</h2>
                </div>
                <div class="ast-block-actions">
                    @unless ($asset->isDisposed())
                        <button type="button" class="master-btn master-btn-soft master-btn-sm"
                            data-open-asset-modal="verify">
                            <i class="fa-solid fa-clipboard-check" aria-hidden="true"></i> Verified today
                        </button>
                    @endunless
                </div>
            </div>

            <dl class="ast-facts">
                <div>
                    <dt>Warranty</dt>
                    <dd>
                        {{ $asset->warrantyLabel() }}
                        @if ($asset->warranty_end_date)
                            <span class="ast-fact-sub">{{ $asset->warranty_end_date->format('d M Y') }}</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt>Insurance</dt>
                    <dd>{{ $asset->insuranceExpired() ? 'Expired' : ($asset->insuranceExpiring() ? 'Expiring soon' : '—') }}</dd>
                </div>
                @if ($asset->insurance_expiry)
                    <div><dt>Cover ends</dt><dd>{{ $asset->insurance_expiry->format('d M Y') }}</dd></div>
                @endif
                <div class="full">
                    <dt>Insurance details</dt>
                    <dd class="ast-pre">{{ $line($asset->insurance_details) }}</dd>
                </div>
                <div>
                    <dt>Last verified</dt>
                    <dd>{{ $line($asset->last_verified_on?->format('d M Y')) }}</dd>
                </div>
                <div><dt>Verified by</dt><dd>{{ $line($asset->verifier?->name) }}</dd></div>
                <div><dt>Condition</dt>
                    <dd><span class="core-badge core-badge-{{ $asset->conditionTone() }}">{{ $asset->conditionLabel() }}</span></dd>
                </div>
                <div><dt>Register entry</dt><dd>{{ $asset->creator?->name }} · {{ $asset->created_at?->format('d M Y') }}</dd></div>
            </dl>
        </section>

        {{-- ------------------------------------------------ the recipe --}}
        <section class="master-card master-card--flat ast-block">
            <div class="ast-block-head">
                <div>
                    <p class="master-eyebrow">Depreciation</p>
                    <h2 class="master-section-title">How this asset is written down</h2>
                </div>
                <div class="ast-block-actions">
                    <a class="master-btn master-btn-light master-btn-sm" href="{{ $recordUrl('depreciation') }}">
                        The year-by-year schedule
                    </a>
                </div>
            </div>

            <div class="ast-recipe">
                <div>
                    <span class="ast-fact-label">Method</span>
                    <span class="ast-fact-value">{{ $asset->methodLabel() }}</span>
                    <span class="ast-fact-sub">{{ $asset->inheritsRecipe() ? 'from the class' : 'set on this asset' }}</span>
                </div>
                <div>
                    <span class="ast-fact-label">Useful life</span>
                    <span class="ast-fact-value">{{ $asset->effectiveLifeYears() }} years</span>
                    <span class="ast-fact-sub">
                        @if ($asset->useful_life_years)
                            set on this asset
                        @else
                            from {{ $asset->category?->name ?: 'the class' }}
                        @endif
                    </span>
                </div>
                <div>
                    <span class="ast-fact-label">Residual value</span>
                    <span class="ast-fact-value">{{ $asset->residualPercentLabel() }}%</span>
                    <span class="ast-fact-sub">{{ $money(round($asset->capitalisedCost() * $asset->effectiveResidualPercent() / 100, 2)) }} left at the end</span>
                </div>
            </div>

            <p class="ast-lede">
                @if ($asset->depreciates())
                    {{ $asset->methodLabel() }} over {{ $asset->effectiveLifeYears() }} years from
                    {{ $asset->purchase_date?->format('d M Y') }}, pro-rated by the days it was on the books in each
                    financial year — so the year it was bought and the year it leaves pay their own share and no more.
                @else
                    This asset is not depreciated — the method is set to "not depreciated", which is what a
                    fully-written-down or a non-depreciable item carries. Its book value stays at cost.
                @endif
            </p>
        </section>

        {{-- ------------------------------------------------ the doors --}}
        @unless ($asset->isDisposed())
            <section class="master-card master-card--flat ast-block">
                <div class="ast-block-head">
                    <div>
                        <p class="master-eyebrow">The doors</p>
                        <h2 class="master-section-title">What can happen to it now</h2>
                    </div>
                </div>

                <div class="ast-doors">
                    <button type="button" class="master-btn master-btn-soft" data-open-asset-modal="maintenance">
                        <i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i> Log a repair or service
                    </button>
                    <button type="button" class="master-btn master-btn-soft" data-open-asset-modal="verify">
                        <i class="fa-solid fa-clipboard-check" aria-hidden="true"></i> Verified in person
                    </button>
                    <button type="button" class="master-btn master-btn-soft" data-open-asset-modal="edit">
                        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Change the details
                    </button>
                    <button type="button" class="master-btn master-btn-light" data-open-asset-modal="dispose">
                        <i class="fa-solid fa-box-archive" aria-hidden="true"></i> Dispose of it
                    </button>

                    <form method="POST" action="{{ route('assets.destroy', $asset) }}"
                        data-confirm="Delete {{ $asset->asset_code }} — {{ $asset->name }}? Its hand-over and repair history goes with it."
                        data-confirm-title="Delete the asset"
                        data-confirm-text="Delete it">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="master-btn master-btn-danger">
                            <i class="fa-solid fa-trash" aria-hidden="true"></i> Delete it
                        </button>
                    </form>
                </div>

                <p class="ast-lede">
                    Deleting removes the history with the asset — so an asset that has lived a life is
                    <strong>disposed of</strong> rather than deleted, and stays here as the company's record of it.
                </p>
            </section>
        @endunless
    </div>
</div>
