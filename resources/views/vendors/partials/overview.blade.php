<div class="vendor-overview-stack">
    <div class="master-stats vendor-overview-stats" aria-label="Vendor activity and financial summary">
        <div class="master-stat master-stat--flat blue">
            <span class="icon"><i class="fa-solid fa-diagram-project" aria-hidden="true"></i></span>
            <div><p class="master-stat-title">Running projects</p><p class="master-stat-value">{{ number_format($summary['running_projects']) }}</p></div>
        </div>
        <div class="master-stat master-stat--flat purple">
            <span class="icon"><i class="fa-solid fa-file-signature" aria-hidden="true"></i></span>
            <div><p class="master-stat-title">Vendor quotes</p><p class="master-stat-value">{{ number_format($summary['vendor_quotes_count']) }}</p></div>
        </div>
        <div class="master-stat master-stat--flat orange">
            <span class="icon"><i class="fa-solid fa-file-invoice" aria-hidden="true"></i></span>
            <div><p class="master-stat-title">Bills · {{ $summary['vendor_currency'] }}</p><p class="master-stat-value">{{ $money($summary['vendor_bill_foreign'], $summary['vendor_currency']) }}</p></div>
        </div>
        <div class="master-stat master-stat--flat green">
            <span class="icon"><i class="fa-solid fa-arrow-up-right-dots" aria-hidden="true"></i></span>
            <div><p class="master-stat-title">Paid · {{ $summary['vendor_currency'] }}</p><p class="master-stat-value">{{ $money($summary['vendor_paid_foreign'], $summary['vendor_currency']) }}</p></div>
        </div>
        <div class="master-stat master-stat--flat red">
            <span class="icon"><i class="fa-solid fa-scale-balanced" aria-hidden="true"></i></span>
            <div><p class="master-stat-title">Estimated balance · INR</p><p class="master-stat-value">{{ \App\Helpers\CommonHelper::indianCurrency($summary['need_to_pay']) }}</p></div>
        </div>
    </div>

    <div class="vendor-detail-grid">
        <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-overview-profile-title">
            <div class="master-section-head">
                <h2 class="master-section-title" id="vendor-overview-profile-title">At a glance</h2>
                <a class="vendor-inline-link" href="{{ $tabUrl('profile') }}">Business profile <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
            </div>
            <div class="master-facts">
                <div class="master-info"><span>Vendor number</span><strong class="{{ blank($vendor->vendor_number) ? 'master-empty-value' : '' }}">{{ $vendor->vendor_number ?: 'Not assigned' }}</strong></div>
                <div class="master-info"><span>Vendor type</span><strong>{{ $vendor->typeLabel() }}</strong></div>
                <div class="master-info"><span>Category</span><strong class="{{ blank($vendor->category) ? 'master-empty-value' : '' }}">{{ $vendor->category ?: 'Not on file' }}</strong></div>
                <div class="master-info"><span>Country</span><strong class="{{ blank($vendor->country) ? 'master-empty-value' : '' }}">{{ $vendor->country ?: 'Not on file' }}</strong></div>
                <div class="master-info is-wide"><span>Primary contact</span>
                    @if ($vendor->contact_person_name)
                        <strong>{{ $vendor->contact_person_name }}</strong>
                    @else
                        <strong class="master-empty-value">No contact on file</strong>
                    @endif
                </div>
            </div>
        </section>

        <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-overview-finance-title">
            <div class="master-section-head">
                <h2 class="master-section-title" id="vendor-overview-finance-title">Account snapshot</h2>
                <a class="vendor-inline-link" href="{{ $tabUrl('statement') }}">View statement <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
            </div>
            <div class="master-facts">
                <div class="master-info"><span>Vendor currency</span><strong>{{ $summary['vendor_currency'] }}</strong></div>
                <div class="master-info"><span>Vendor bills</span><strong>{{ $money($summary['vendor_bill_foreign'], $summary['vendor_currency']) }}</strong></div>
                <div class="master-info"><span>Paid to vendor</span><strong>{{ $money($summary['vendor_paid_foreign'], $summary['vendor_currency']) }}</strong></div>
                <div class="master-info"><span>Vendor expenses</span><strong>{{ $money($summary['vendor_expense_foreign'], $summary['vendor_currency']) }}</strong></div>
                <div class="master-info is-wide"><span>INR paid / estimated balance</span><strong>{{ \App\Helpers\CommonHelper::indianCurrency($summary['paid_to_vendor']) }} <span class="vendor-muted-inline">paid</span> · {{ \App\Helpers\CommonHelper::indianCurrency($summary['need_to_pay']) }} <span class="vendor-muted-inline">estimated balance</span></strong></div>
            </div>
        </section>
    </div>

    <div class="vendor-overview-grid">
        <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-recent-projects-title">
            <div class="master-section-head">
                <h2 class="master-section-title" id="vendor-recent-projects-title">Recent project products</h2>
                <a class="vendor-inline-link" href="{{ $tabUrl('projects') }}">All projects <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
            </div>
            <div class="vendor-activity-list">
                @forelse ($projectProducts->take(4) as $row)
                    @php($overviewProject = $row->relationLoaded('project') ? $row->project : null)
                    <div class="vendor-activity-row">
                        <div class="vendor-activity-copy">
                            <strong>{{ $row->product_name ?: 'Project product' }}</strong>
                            <span>{{ $overviewProject?->project_number ?: 'No project linked' }} · {{ $row->statusLabel() }}</span>
                        </div>
                        <strong class="vendor-activity-value">{{ $money($row->total_amount, $row->currency ?: 'INR') }}</strong>
                    </div>
                @empty
                    <div class="master-empty-state"><i class="fa-solid fa-diagram-project" aria-hidden="true"></i><p>No project products are linked to this vendor yet.</p></div>
                @endforelse
            </div>
        </section>

        <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-recent-payments-title">
            <div class="master-section-head">
                <h2 class="master-section-title" id="vendor-recent-payments-title">Recent ledger activity</h2>
                <a class="vendor-inline-link" href="{{ $tabUrl('payments') }}">Open payments <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
            </div>
            <div class="vendor-activity-list">
                @forelse ($vendorPaymentEntries->sortByDesc('transaction_date')->take(4) as $entry)
                    <div class="vendor-activity-row">
                        <div class="vendor-activity-copy">
                            <strong>{{ $entry->particular }}</strong>
                            <span>{{ optional($entry->transaction_date)->format('d M Y') ?: 'No date' }} · {{ $entry->categoryLabel() }}</span>
                        </div>
                        <strong class="vendor-activity-value {{ $entry->transaction_type === 'debit' ? 'is-positive' : 'is-neutral' }}">
                            {{ $money($entry->foreign_amount, $entry->foreign_currency ?: $summary['vendor_currency']) }}
                        </strong>
                    </div>
                @empty
                    <div class="master-empty-state"><i class="fa-solid fa-receipt" aria-hidden="true"></i><p>No manual vendor ledger entries have been recorded.</p></div>
                @endforelse
            </div>
        </section>

        <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-recent-notes-title">
            <div class="master-section-head">
                <h2 class="master-section-title" id="vendor-recent-notes-title">Team notes</h2>
                <a class="vendor-inline-link" href="{{ $tabUrl('comments') }}">View notes <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
            </div>
            <div class="vendor-activity-list">
                @if ($commentsAvailable)
                    @forelse ($vendor->comments->take(3) as $comment)
                        <article class="vendor-note-preview">
                            <div class="vendor-note-meta"><strong>{{ $comment->creator?->name ?? 'Team' }}</strong><span>{{ $comment->created_at?->format('d M Y') ?: '' }}@if($comment->is_pinned) · Pinned @endif</span></div>
                            <p>{{ \Illuminate\Support\Str::limit($comment->body, 120) }}</p>
                        </article>
                    @empty
                        <div class="master-empty-state"><i class="fa-regular fa-note-sticky" aria-hidden="true"></i><p>No team notes yet.</p></div>
                    @endforelse
                @else
                    <div class="master-empty-state"><i class="fa-regular fa-note-sticky" aria-hidden="true"></i><p>Team notes are currently unavailable.</p></div>
                @endif
            </div>
        </section>
    </div>
</div>
