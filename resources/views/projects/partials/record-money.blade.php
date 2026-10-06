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
                            <th scope="col" class="is-num">Amount</th>
                            <th scope="col">Mode</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($project->cashflowEntries as $entry)
                            <tr>
                                <td data-label="Date">
                                    {{ optional($entry->entry_date)->format('d M Y') ?: '—' }}
                                    <span class="project-fact-note">{{ $entry->transaction_type === 'credit' ? 'Inward' : 'Expense' }}</span>
                                </td>
                                <td data-label="Particular">{{ $entry->particular ?: '—' }}</td>
                                <td data-label="Bank reference">{{ $entry->bank_reference_number ?: '—' }}</td>
                                <td data-label="Amount" class="is-num">
                                    <strong class="{{ $entry->transaction_type === 'credit' ? 'project-money-in' : 'project-money-out' }}">
                                    {{ \App\Helpers\CommonHelper::amount($entry->transaction_type === 'credit' ? $entry->credit_amount : $entry->debit_amount, $entry->currency ?? $project->currency) }}</strong>
                                </td>
                                <td data-label="Mode">{{ $entry->payment_mode ? \Illuminate\Support\Str::title($entry->payment_mode) : '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
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
