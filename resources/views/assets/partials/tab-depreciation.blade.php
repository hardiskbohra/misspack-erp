@php
    /*
     * One asset's whole depreciation life, financial year by financial year,
     * computed — not stored.
     *
     * The figures here come from `AssetDepreciation`, the single calculator the
     * register, the record, the report and the export all read. That is why the
     * tab needs no "recalculate" button and why the numbers on the three screens
     * cannot disagree: the recipe is on the asset, the arithmetic is in one class,
     * and the year is the Indian financial year.
     *
     * Two blocks, and both of them cards — the record's composition, the same one
     * the overview uses: what the curve is made of (and where it stands today),
     * and the curve itself. The recipe box used to be the whole panel's only
     * surface on a soft panel background, and the table had none at all.
     */
    $life = $asset->effectiveLifeYears();
    $closed = $asset->isDisposed() || ($asset->purchase_date && $asset->purchase_date->copy()->addYears((int) $life)->isPast());
@endphp

<div class="master-tab-panel ast-panel">

    {{-- ──────────────────────────────── what the curve is made of, and where it stands --}}
    <section class="master-card master-card--flat ast-block">

        <div class="ast-panel-head">
            <div>
                <p class="master-eyebrow">Depreciation schedule</p>
                <h2 class="master-section-title">The asset, year by year</h2>
                <p class="master-sub">
                    Opening value, the year's charge, and what is left at the close of each financial year
                    (1 April – 31 March). The charge is pro-rated by the days the asset was on the books in that
                    year, so the year it was bought and the year it leaves are the smaller rows.
                </p>
            </div>
            <div class="ast-panel-actions">
                <a class="master-btn master-btn-soft" href="{{ route('assets.depreciation', ['year' => $currentYear]) }}">
                    <i class="fa-solid fa-calculator" aria-hidden="true"></i> The company's year
                </a>
            </div>
        </div>

        {{-- Six facts in a grid of three: the four the charge is made of, then the
             two that say where the curve stands today. A deliberate three — an
             `auto-fit` grid puts as many tracks as the window allows, which is how
             six facts came to render as five and a stray one. The two "today"
             figures live here, once: the table's footer printed the same two
             numbers again under column headings they are not the totals of. --}}
        <div class="ast-recipe">
            <div>
                <span class="ast-fact-label">Method</span>
                <span class="ast-fact-value">{{ $asset->methodLabel() }}</span>
                <span class="ast-fact-sub">{{ $asset->inheritsRecipe() ? 'from the class' : 'set on this asset' }}</span>
            </div>
            <div>
                <span class="ast-fact-label">Useful life</span>
                <span class="ast-fact-value">{{ $life }} years</span>
                <span class="ast-fact-sub">
                    from {{ $asset->purchase_date?->format('d M Y') }} to
                    {{ $asset->purchase_date?->copy()->addYears((int) $life)->format('d M Y') }}
                </span>
            </div>
            <div>
                <span class="ast-fact-label">Capitalised cost</span>
                <span class="ast-fact-value ast-money">{{ $money($asset->capitalisedCost()) }}</span>
                <span class="ast-fact-sub">{{ $money($asset->totalCost()) }} on the invoice</span>
            </div>
            <div>
                <span class="ast-fact-label">Residual value</span>
                <span class="ast-fact-value ast-money">{{ $money(round($asset->capitalisedCost() * $asset->effectiveResidualPercent() / 100, 2)) }}</span>
                <span class="ast-fact-sub">{{ $asset->residualPercentLabel() }}% of cost, left at the end</span>
            </div>
            <div>
                <span class="ast-fact-label">Written off to date</span>
                <span class="ast-fact-value ast-money">{{ $money($accumulated) }}</span>
                <span class="ast-fact-sub">{{ $asset->isDisposed() ? 'stopped at disposal' : 'as at today' }}</span>
            </div>
            <div>
                <span class="ast-fact-label">Net book value today</span>
                <span class="ast-fact-value ast-money">{{ $money($netBookValue) }}</span>
                <span class="ast-fact-sub">
                    @if ($closed)
                        the curve has closed
                    @else
                        {{ $money($chargeThisYear) }} will move this year
                    @endif
                </span>
            </div>
        </div>
    </section>

    @if ($schedule === [])
        <section class="master-card master-card--flat ast-table-card">
            <div class="master-list-empty">
                <span class="master-list-empty-icon" aria-hidden="true">📉</span>
                <h3 class="master-list-empty-title">Nothing to depreciate</h3>
                <p class="master-list-empty-text">
                    This asset's method is set to "not depreciated", or it has no cost to write down — so it sits on
                    the register at cost and never appears in a year's schedule.
                </p>
            </div>
        </section>
    @else
        {{-- ──────────────────────────────────────────────────────── the curve --}}
        {{-- Four columns, because a single asset's curve is read in four: the year,
             what it opened at, what the year charged, what it closed at. "Charged
             from" and "Charged to" carried the same 1 April – 31 March on every
             full year and are the pro-rated window now, under the year they belong
             to, on the only rows where they differ. --}}
        <section class="master-card master-card--flat ast-table-card">
            <div class="master-table-wrap ui-mobile-cards">
                <table class="master-table ast-table ast-schedule-table">
                    <thead>
                        <tr>
                            <th scope="col">Financial year</th>
                            <th scope="col" class="ast-col-money">Opening</th>
                            <th scope="col" class="ast-col-money">Charge for the year</th>
                            <th scope="col" class="ast-col-money">Closing</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($schedule as $row)
                            <tr class="{{ $row['is_last'] ? 'ast-row-last' : '' }}">
                                <td data-label="Financial year">
                                    {{ $row['label'] }}
                                    @if ($row['days'] < $row['days_in_year'])
                                        <span class="ast-cell-sub">
                                            {{ $row['from']->format('d M Y') }} → {{ $row['to']->format('d M Y') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="ast-col-money" data-label="Opening">
                                    <span class="ast-money">{{ $money($row['opening']) }}</span>
                                </td>
                                <td class="ast-col-money" data-label="Charge for the year">
                                    <span class="ast-money">{{ $money($row['charge']) }}</span>
                                    @if ($row['days'] < $row['days_in_year'])
                                        <span class="ast-cell-sub">{{ number_format($row['days']) }} of
                                            {{ number_format($row['days_in_year']) }} days</span>
                                    @endif
                                </td>
                                <td class="ast-col-money" data-label="Closing">
                                    <span class="ast-money">{{ $money($row['closing']) }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- The method's own footnote, on the table card: a block inside a table
                 card owes its own inset and a hairline above it, the way the
                 register's applied-filter strip does in the card above its list. --}}
            <p class="ast-table-foot">
                @if ($asset->isDisposed() && $asset->disposal_date)
                    The curve stopped on {{ $asset->disposal_date->format('d M Y') }}: the year of the sale is charged
                    up to that day, and the {{ $money((float) $asset->bookValueOnDisposal()) }} it was carried at then
                    is what the sale price was measured against.
                @elseif ($asset->effectiveMethod() === \App\Services\AssetVocabulary::METHOD_WDV)
                    Written-down-value depreciation is charged on the book value each year, at the rate that lands the
                    asset exactly on its residual value at the end of {{ $life }} years. It is the method that charges
                    most in the first year — and the last year absorbs the rounding, so the curve closes where it should.
                @else
                    Straight-line depreciation spreads the cost evenly across {{ $life }} years. The year the asset was
                    bought and the year it leaves are pro-rated by days, which is why the first and last rows are the
                    smaller ones.
                @endif
            </p>
        </section>
    @endif
</div>
