@php
    /*
     * The repair and service history — the company's answer to "how much has this
     * machine cost us since we bought it", and to "when is it next due".
     *
     * Nothing here is part of the asset's cost: a repair is revenue spending, and
     * the register says so on the tab, because that is the question a reader
     * arriving here from the purchase block will have.
     */
@endphp

<div class="master-tab-panel ast-panel">

    <section class="master-card master-card--flat ast-block">
        <div class="ast-panel-head">
            <div>
                <p class="master-eyebrow">Maintenance &amp; repairs</p>
                <h2 class="master-section-title">What it has cost to keep running</h2>
                <p class="master-sub">
                    Services, repairs, AMC payments, calibrations and upgrades, newest first. These are running costs —
                    they never join the asset's purchase cost and never change the depreciation.
                </p>
            </div>
            <div class="ast-panel-actions">
                @unless ($asset->isDisposed())
                    <button type="button" class="master-btn master-btn-primary" data-open-asset-modal="maintenance">
                        <i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i> Log a repair or service
                    </button>
                @endunless
            </div>
        </div>
    </section>

    <div class="ast-panel-stats">
        <div class="ast-mini-stat">
            <span class="ast-fact-label">Spent on it</span>
            <span class="ast-fact-value ast-money">{{ $money($maintenanceSpend) }}</span>
            <span class="ast-fact-sub">across {{ number_format($tabCounts['maintenance']) }}
                {{ \Illuminate\Support\Str::plural('visit', $tabCounts['maintenance']) }}</span>
        </div>
        <div class="ast-mini-stat">
            <span class="ast-fact-label">Last attended</span>
            <span class="ast-fact-value">
                {{ $asset->maintenances->first()?->performed_on?->format('d M Y') ?: '—' }}
            </span>
            <span class="ast-fact-sub">
                {{ $asset->maintenances->first()?->kindLabel() ?: 'nothing logged yet' }}
            </span>
        </div>
        <div class="ast-mini-stat {{ $nextService && $nextService->isOverdue() ? 'is-overdue' : '' }}">
            <span class="ast-fact-label">Next service due</span>
            <span class="ast-fact-value">{{ $nextService?->next_due_on?->format('d M Y') ?: 'not scheduled' }}</span>
            <span class="ast-fact-sub">
                @if ($nextService?->isOverdue())
                    overdue
                @elseif ($nextService?->isDueSoon())
                    due within sixty days
                @elseif ($nextService)
                    in the diary
                @else
                    log one to start the diary
                @endif
            </span>
        </div>
    </div>

    @if ($asset->maintenances->isEmpty())
        <section class="master-card master-card--flat ast-table-card">
            <div class="master-list-empty">
                <span class="master-list-empty-icon" aria-hidden="true">🔧</span>
                <h3 class="master-list-empty-title">Nothing logged yet</h3>
                <p class="master-list-empty-text">
                    Every service, repair and AMC payment lands here with what it cost, how long the asset was down, and
                    when the next one is due — so the register can answer "when did we last look at it" without asking
                    around the office.
                </p>
            </div>
        </section>
    @else
        <section class="master-card master-card--flat ast-table-card">
            <div class="master-table-wrap ui-mobile-cards">
                <table class="master-table ast-table">
                    <thead>
                        <tr>
                            <th scope="col">Performed on</th>
                            <th scope="col">What kind</th>
                            <th scope="col">Done by</th>
                            <th scope="col">Invoice / job</th>
                            <th scope="col" class="ast-col-money">Cost</th>
                            <th scope="col">Downtime</th>
                            <th scope="col">Next due</th>
                            <th scope="col">What was done</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($asset->maintenances as $entry)
                            <tr>
                                <td data-label="Performed on">{{ $entry->performed_on?->format('d M Y') ?: '—' }}</td>
                                <td data-label="What kind">
                                    <span class="core-badge core-badge-{{ $entry->kindTone() }}">{{ $entry->kindLabel() }}</span>
                                </td>
                                <td data-label="Done by">{{ $entry->vendorLabel() ?: '—' }}</td>
                                <td data-label="Invoice / job">{{ filled($entry->invoice_no) ? $entry->invoice_no : '—' }}</td>
                                <td class="ast-col-money" data-label="Cost">
                                    <span class="ast-money">{{ $money((float) $entry->cost) }}</span>
                                </td>
                                <td data-label="Downtime">
                                    {{ $entry->downtimeLabel() ?: '—' }}
                                    @if ($entry->daysDown())
                                        <span class="ast-cell-sub">{{ number_format($entry->daysDown()) }}
                                            {{ \Illuminate\Support\Str::plural('day', $entry->daysDown()) }}</span>
                                    @endif
                                </td>
                                <td data-label="Next due">
                                    @if ($entry->next_due_on)
                                        {{ $entry->next_due_on->format('d M Y') }}
                                        @if ($entry->isOverdue())
                                            <span class="core-badge core-badge-danger">overdue</span>
                                        @elseif ($entry->isDueSoon())
                                            <span class="core-badge core-badge-warning">soon</span>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td data-label="What was done">
                                    {{ filled($entry->notes) ? $entry->notes : '—' }}
                                    <span class="ast-cell-sub">{{ $entry->creator?->name ?: '' }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="ast-total-row">
                            <th scope="row" colspan="4">Total spent keeping it running</th>
                            <td class="ast-col-money"><span class="ast-money">{{ $money($maintenanceSpend) }}</span></td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>
    @endif
</div>
