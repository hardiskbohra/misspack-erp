@php
    /*
     * The hand-over history — who held the asset, where, from when to when, and
     * in what condition it came back.
     *
     * The open row is stated as open and counted to today, because that row *is*
     * the asset's custody: the register's custodian, location and department are
     * read from it, and the list keeps exactly one of them open at a time.
     */
    $open = $asset->openAllocation;
@endphp

<div class="master-tab-panel ast-panel">

    <section class="master-card master-card--flat ast-block">
        <div class="ast-panel-head">
            <div>
                <p class="master-eyebrow">Allocation</p>
                <h2 class="master-section-title">Who has held it</h2>
                <p class="master-sub">
                    Every hand-over, newest first. The open one is who has it now — the asset's custodian, location and
                    department are this row, not a copy of it.
                </p>
            </div>
            <div class="ast-panel-actions">
                @unless ($asset->isDisposed())
                    <button type="button" class="master-btn master-btn-primary" data-open-asset-modal="allocate">
                        <i class="fa-solid fa-hand-holding-hand" aria-hidden="true"></i> Hand it over
                    </button>
                    @if ($open)
                        <button type="button" class="master-btn master-btn-soft" data-open-asset-modal="return">
                            <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Take it back
                        </button>
                    @endif
                @endunless
            </div>
        </div>
    </section>

    @if ($open)
        <div class="master-info-box ast-open-holder">
            <i class="fa-solid fa-user-clock" aria-hidden="true"></i>
            <span>
                Currently with <strong>{{ $open->holderLabel() }}</strong>
                @if ($open->placeLabel() !== '—')
                    at {{ $open->placeLabel() }}
                @endif
                since {{ $open->allocated_on?->format('d M Y') }} —
                {{ number_format($open->daysHeld()) }} days.
            </span>
        </div>
    @elseif (! $asset->isDisposed())
        <div class="master-info-box">
            <i class="fa-solid fa-box" aria-hidden="true"></i>
            <span>Nobody holds this asset at the moment — it is in the company's hands, not an individual's.</span>
        </div>
    @endif

    @if ($asset->allocations->isEmpty())
        <section class="master-card master-card--flat ast-table-card">
            <div class="master-list-empty">
                <span class="master-list-empty-icon" aria-hidden="true">🤝</span>
                <h3 class="master-list-empty-title">Never handed over</h3>
                <p class="master-list-empty-text">
                    Register who takes it, and the hand-over history starts here — with the date, the place and the
                    condition it comes back in.
                </p>
            </div>
        </section>
    @else
        <section class="master-card master-card--flat ast-table-card">
            <div class="master-table-wrap ui-mobile-cards">
                <table class="master-table ast-table">
                    <thead>
                        <tr>
                            <th scope="col">Held by</th>
                            <th scope="col">Where</th>
                            <th scope="col">From</th>
                            <th scope="col">To</th>
                            <th scope="col">Came back in</th>
                            <th scope="col">Note</th>
                            <th scope="col">Recorded by</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($asset->allocations as $allocation)
                            <tr class="{{ $allocation->isOpen() ? 'ast-row-open' : '' }}">
                                <td data-label="Held by">
                                    {{ $allocation->holderLabel() }}
                                    @if ($allocation->isOpen())
                                        <span class="core-badge core-badge-info">holding it now</span>
                                    @endif
                                </td>
                                <td data-label="Where">{{ $allocation->placeLabel() }}</td>
                                <td data-label="From">{{ $allocation->allocated_on?->format('d M Y') ?: '—' }}</td>
                                <td data-label="To">
                                    {{ $allocation->returned_on?->format('d M Y') ?: '—' }}
                                    @if ($allocation->daysHeld())
                                        <span class="ast-cell-sub">{{ number_format($allocation->daysHeld()) }} days</span>
                                    @endif
                                </td>
                                <td data-label="Came back in">
                                    @if ($allocation->returnConditionLabel())
                                        <span class="core-badge core-badge-neutral">{{ $allocation->returnConditionLabel() }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td data-label="Note">{{ filled($allocation->note) ? $allocation->note : '—' }}</td>
                                <td data-label="Recorded by">
                                    {{ $allocation->creator?->name ?: '—' }}
                                    <span class="ast-cell-sub">{{ $allocation->created_at?->format('d M Y') }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
