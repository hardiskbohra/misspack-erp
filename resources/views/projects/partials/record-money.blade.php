@php
    $paymentRows = $project->cashflowEntries->map(fn ($entry) => [
        'entry' => $entry,
        'amount' => $entry->amountMoved(),
        'note' => null,
    ]);
    if ($project->relationLoaded('cashflowAllocations')) {
        $paymentRows = $paymentRows->concat($project->cashflowAllocations
            ->filter(fn ($allocation) => $allocation->cashflowEntry)
            ->map(fn ($allocation) => [
                'entry' => $allocation->cashflowEntry,
                'amount' => (float) $allocation->amount,
                'note' => $allocation->note,
            ]));
    }
    $paymentRows = $paymentRows->sortByDesc(fn ($row) => ($row['entry']->entry_date?->format('Y-m-d') ?? '').'-'.str_pad((string) $row['entry']->id, 12, '0', STR_PAD_LEFT));
@endphp
<section class="master-tab-panel" id="project-panel-payments" role="tabpanel" aria-labelledby="project-tab-payments">
    <div class="project-blocks">
        <section class="master-card master-card--flat project-detail-card">
            <div class="master-section-head">
                <div>
                    <h2 class="master-section-title">Payments</h2>
                    <p class="master-sub">Ledger entries tagged to this project — money in and money out, the source of
                        truth for project-level payments</p>
                </div>
                <div class="master-section-meta">
                    <a class="master-btn master-btn-soft master-btn-sm" href="{{ route('cashflows.index') }}">
                        <i class="fa-solid fa-scale-balanced" aria-hidden="true"></i> Open ledger
                    </a>
                </div>
            </div>
            <div class="master-table-wrap">
                <table class="master-table">
                    <thead>
                        <tr>
                            <th scope="col">Date</th>
                            <th scope="col">Particular</th>
                            <th scope="col">Bank reference</th>
                            <th scope="col" class="is-num">Inward</th>
                            <th scope="col" class="is-num">Outward</th>
                            <th scope="col">Mode</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($paymentRows as $paymentRow)
                            @php($entry = $paymentRow['entry'])
                            <tr>
                                <td data-label="Date">
                                    {{ optional($entry->entry_date)->format('d M Y') ?: '—' }}
                                </td>
                                <td data-label="Particular">
                                    {{ $entry->particular ?: '—' }}
                                    @if($paymentRow['note'])<span class="project-fact-note">{{ $paymentRow['note'] }}</span>@endif
                                </td>
                                <td data-label="Bank reference">{{ $entry->bank_reference_number ?: '—' }}</td>
                                <td data-label="Inward" class="is-num">
                                    @if ($entry->credit_amount > 0)
                                        <strong class="project-money-in">{{ \App\Helpers\CommonHelper::amount($paymentRow['amount'], $entry->currency ?? $project->currency) }}</strong>
                                    @else
                                        <span class="master-empty-value">—</span>
                                    @endif
                                </td>
                                <td data-label="Outward" class="is-num">
                                    @if ($entry->debit_amount > 0)
                                        <strong class="project-money-out">{{ \App\Helpers\CommonHelper::amount($paymentRow['amount'], $entry->currency ?? $project->currency) }}</strong>
                                    @else
                                        <span class="master-empty-value">—</span>
                                    @endif
                                </td>
                                <td data-label="Mode">{{ $entry->payment_mode ? \Illuminate\Support\Str::title($entry->payment_mode) : '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="master-empty-state">
                                        <i class="fa-solid fa-scale-balanced" aria-hidden="true"></i>
                                        <p>No ledger entry is tagged to this project yet. Payments are recorded in the cashflow
                                        module with this project selected, and they are what the client portal shows as receipts.</p>
                                        <a class="master-btn master-btn-soft master-btn-sm" href="{{ route('cashflows.index') }}">
                                            <i class="fa-solid fa-scale-balanced" aria-hidden="true"></i> Open ledger
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</section>
