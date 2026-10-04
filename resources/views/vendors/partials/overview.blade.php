@php($empty = fn ($value) => blank($value))
<section class="master-tab-panel" id="vendor-panel-overview" role="tabpanel" aria-labelledby="vendor-tab-overview">
    <div class="vendor-detail-grid">
        <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-snapshot-heading">
            <h2 class="vendor-detail-title" id="vendor-snapshot-heading">Supplier snapshot</h2>
            <div class="master-facts">
                <div class="master-info"><span>Vendor number</span><strong @class(['master-empty-value' => $empty($vendor->vendor_number)])>{{ $vendor->vendor_number ?: 'Not on file' }}</strong></div>
                <div class="master-info"><span>Type</span><strong>{{ $vendor->typeLabel() }}</strong></div>
                <div class="master-info"><span>Category</span><strong @class(['master-empty-value' => $empty($vendor->category)])>{{ $vendor->category ?: 'Not on file' }}</strong></div>
                <div class="master-info"><span>Brand</span><strong @class(['master-empty-value' => $empty($vendor->brand_name)])>{{ $vendor->brand_name ?: 'Not on file' }}</strong></div>
                <div class="master-info"><span>Status</span><strong><span class="master-badge status-{{ str_replace('_', '-', $vendor->status) }}">{{ $vendor->statusLabel() }}</span></strong></div>
                <div class="master-info"><span>Rating</span><strong @class(['master-empty-value' => ! $vendor->rating])>{{ $vendor->rating ? str_repeat('★', $vendor->rating).' ('.$vendor->rating.' of 5)' : 'Not rated' }}</strong></div>
                <div class="master-info"><span>Preferred currency</span><strong>{{ $vendor->preferred_currency ?: 'INR' }}</strong></div>
                <div class="master-info"><span>Lead time</span><strong @class(['master-empty-value' => $vendor->lead_time_days === null])>{{ $vendor->lead_time_days !== null ? $vendor->lead_time_days.' days' : 'Not set' }}</strong></div>
                <div class="master-info is-wide"><span>Payment terms</span><strong @class(['master-empty-value' => $empty($vendor->payment_terms)])>{{ $vendor->payment_terms ?: 'Not on file' }}</strong></div>
                <div class="master-info"><span>Record created</span><strong>{{ $vendor->created_at?->format('d M Y') ?: '—' }}</strong></div>
                <div class="master-info"><span>Added by</span><strong @class(['master-empty-value' => ! $vendor->creator])>{{ $vendor->creator?->name ?: 'Not on file' }}</strong></div>
            </div>
        </section>

        <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-overview-contact-heading">
            <h2 class="vendor-detail-title" id="vendor-overview-contact-heading">Primary contact</h2>
            <div class="master-facts">
                <div class="master-info"><span>Contact person</span><strong @class(['master-empty-value' => $empty($vendor->contact_person_name)])>{{ $vendor->contact_person_name ?: 'Not on file' }}</strong></div>
                <div class="master-info"><span>Email</span>
                    @if ($vendor->contact_person_email)
                        <a class="vendor-detail-link" href="mailto:{{ $vendor->contact_person_email }}">{{ $vendor->contact_person_email }}</a>
                    @else
                        <strong class="master-empty-value">Not on file</strong>
                    @endif
                </div>
                <div class="master-info"><span>Mobile</span>
                    @if ($vendor->contact_person_mobile)
                        <a class="vendor-detail-link" href="tel:{{ preg_replace('/[^0-9+]/', '', $vendor->contact_person_mobile) }}">{{ $vendor->contact_person_mobile }}</a>
                    @else
                        <strong class="master-empty-value">Not on file</strong>
                    @endif
                </div>
                <div class="master-info"><span>WhatsApp</span><strong @class(['master-empty-value' => $empty($vendor->whatsapp_number)])>{{ $vendor->whatsapp_number ?: 'Not on file' }}</strong></div>
                <div class="master-info"><span>Alternate contact</span><strong @class(['master-empty-value' => $empty($vendor->alternate_contact)])>{{ $vendor->alternate_contact ?: 'Not on file' }}</strong></div>
                <div class="master-info is-wide"><span>Website</span>
                    @if ($vendor->website)
                        <a class="vendor-detail-link" href="{{ $vendor->website }}" target="_blank" rel="noopener noreferrer">{{ $vendor->website }}</a>
                    @else
                        <strong class="master-empty-value">Not on file</strong>
                    @endif
                </div>
                <div class="master-info is-wide"><span>Alibaba</span>
                    @if ($vendor->alibaba_link)
                        <a class="vendor-detail-link" href="{{ $vendor->alibaba_link }}" target="_blank" rel="noopener noreferrer">Open Alibaba profile</a>
                    @else
                        <strong class="master-empty-value">Not on file</strong>
                    @endif
                </div>
            </div>
        </section>

        <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-overview-money-heading">
            <h2 class="vendor-detail-title" id="vendor-overview-money-heading">Money at a glance</h2>
            <div class="master-facts">
                <div class="master-info"><span>Ledger entries</span><strong>{{ number_format($summary['statement_count']) }}</strong></div>
                <div class="master-info"><span>Linked cashflow rows</span><strong>{{ number_format($summary['cashflow_count']) }}</strong></div>
                <div class="master-info"><span>Bills raised, in rupees</span><strong>{{ $money($summary['expected_payable']) }}</strong></div>
                <div class="master-info"><span>Paid, in rupees</span><strong>{{ $money($summary['paid_to_vendor']) }}</strong></div>
                <div class="master-info"><span>Vendor-currency balance</span><strong @class(['vendor-amount-negative' => $summary['vendor_balance_foreign'] > 0])>{{ $money($summary['vendor_balance_foreign'], $summary['vendor_currency']) }}</strong></div>
                <div class="master-info"><span>Refunds received</span><strong>{{ $money($summary['received_from_vendor']) }}</strong></div>
                <div class="master-info is-wide"><span>Project value</span><strong>{{ $money($summary['project_value']) }} <span class="vendor-detail-note">from project product rows</span></strong></div>
            </div>
        </section>

        <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-overview-documents-heading">
            <h2 class="vendor-detail-title" id="vendor-overview-documents-heading">Record contents</h2>
            <div class="master-facts">
                <div class="master-info"><span>Projects</span><strong>{{ number_format($summary['project_products_count']) }} product {{ \Illuminate\Support\Str::plural('row', $summary['project_products_count']) }}</strong></div>
                <div class="master-info"><span>Products supplied</span><strong>{{ number_format($summary['products_count']) }}</strong></div>
                <div class="master-info"><span>Shipments</span><strong>{{ number_format($summary['shipments_count']) }}</strong></div>
                <div class="master-info"><span>Documents</span><strong>{{ number_format($summary['attachments_count']) }}</strong></div>
                <div class="master-info"><span>Comments</span><strong>{{ $vendor->relationLoaded('comments') ? number_format($vendor->comments->count()) : '—' }}</strong></div>
                <div class="master-info is-wide"><span>Notes</span><strong class="vendor-detail-notes {{ blank($vendor->notes) ? 'master-empty-value' : '' }}">{{ $vendor->notes ?: 'No internal notes on file' }}</strong></div>
            </div>
            <div class="vendor-detail-actions">
                <a class="master-btn master-btn-soft master-btn-sm" href="{{ $recordUrl('attachments') }}">
                    <i class="fa-regular fa-folder-open" aria-hidden="true"></i> Documents
                </a>
                <a class="master-btn master-btn-soft master-btn-sm" href="{{ $recordUrl('shipments') }}">
                    <i class="fa-solid fa-truck" aria-hidden="true"></i> Shipments
                </a>
            </div>
        </section>
    </div>

    <div class="vendor-detail-grid vendor-detail-grid--lists">
        <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-latest-projects-heading">
            <div class="vendor-card-head">
                <h2 class="vendor-detail-title" id="vendor-latest-projects-heading">Latest project products</h2>
                <a class="vendor-card-link" href="{{ $recordUrl('projects') }}">All projects</a>
            </div>
            @forelse($projectProducts->take(5) as $row)
                <div class="vendor-mini-row">
                    <div>
                        <strong>{{ $row->product_name }}</strong>
                        <small>{{ $row->project?->project_number ?? 'No project' }} · {{ $row->statusLabel() }}</small>
                    </div>
                    <b class="is-num">{{ $money($row->total_amount, $row->currency) }}</b>
                </div>
            @empty
                <p class="vendor-empty-text">No project product mapping yet. Map a product to this vendor from a project.</p>
            @endforelse
        </section>

        <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-latest-ledger-heading">
            <div class="vendor-card-head">
                <h2 class="vendor-detail-title" id="vendor-latest-ledger-heading">Latest ledger entries</h2>
                <a class="vendor-card-link" href="{{ $recordUrl('payments') }}">All entries</a>
            </div>
            @forelse($vendorPaymentEntries->sortByDesc('transaction_date')->take(5) as $entry)
                <div class="vendor-mini-row">
                    <div>
                        <strong>{{ $entry->particular }}</strong>
                        <small>{{ $entry->transaction_date?->format('d M Y') ?: '—' }} · {{ $entry->categoryLabel() }}</small>
                    </div>
                    <b class="is-num {{ $entry->transaction_type === 'credit' ? 'vendor-amount-debit' : 'vendor-amount-credit' }}">
                        {{ $entry->transaction_type === 'credit' ? '+' : '−' }}
                        {{ $money($entry->foreign_amount, $entry->foreign_currency ?: 'RMB') }}
                    </b>
                </div>
            @empty
                <p class="vendor-empty-text">No vendor-currency ledger entries yet. Add the first bill or payment from the Payments tab.</p>
            @endforelse
        </section>

        <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-latest-comments-heading">
            <div class="vendor-card-head">
                <h2 class="vendor-detail-title" id="vendor-latest-comments-heading">Latest comments</h2>
                <a class="vendor-card-link" href="{{ $recordUrl('comments') }}">All comments</a>
            </div>
            @if ($commentsAvailable && $vendor->relationLoaded('comments'))
                @forelse($vendor->comments->take(4) as $comment)
                    <div class="vendor-mini-row">
                        <div>
                            <strong>{{ $comment->creator?->name ?: 'Internal team' }}</strong>
                            <small>{{ $comment->created_at?->format('d M Y, h:i A') }}@if ($comment->is_pinned) · Pinned @endif</small>
                        </div>
                        <p class="vendor-mini-note">{{ \Illuminate\Support\Str::limit($comment->body, 90) }}</p>
                    </div>
                @empty
                    <p class="vendor-empty-text">No comments yet. Add one to leave a note for the next person.</p>
                @endforelse
            @else
                <p class="vendor-empty-text">Comments are not enabled on this database yet.</p>
            @endif
        </section>
    </div>
</section>
