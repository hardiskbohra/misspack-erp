@php
    /**
     * The overview: who this vendor is, who answers the phone, what the money
     * looks like, what the relationship has been worth, and what has happened
     * lately. Every card links to the tab that owns the detail behind it.
     */
    $empty = fn ($value) => blank($value);
    $overdue = $payables['overdue'] ?? 0;
@endphp
<section class="master-tab-panel" id="vendor-panel-overview" role="tabpanel" aria-labelledby="vendor-tab-overview">
    @if ($overdue > 0)
        {{-- The one thing on this page that says "do something today". --}}
        <div class="vendor-attention">
            <span class="vendor-attention-icon" aria-hidden="true"><i class="fa-solid fa-triangle-exclamation"></i></span>
            <div class="vendor-attention-copy">
                <strong>{{ \App\Helpers\CommonHelper::indianCurrency($overdue) }} is past its due date</strong>
                <span>{{ $payables['overdue_count'] }} {{ \Illuminate\Support\Str::plural('bill', $payables['overdue_count']) }} on this vendor — oldest first, with the statement ready to send.</span>
            </div>
            <a class="master-btn master-btn-soft master-btn-sm" href="{{ $recordUrl('money') }}">
                <i class="fa-solid fa-scale-balanced" aria-hidden="true"></i> Open payables
            </a>
        </div>
    @endif

    <div class="vendor-detail-grid">
        <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-snapshot-heading">
            <div class="vendor-card-head">
                <h2 class="vendor-detail-title" id="vendor-snapshot-heading">Supplier snapshot</h2>
                <a class="vendor-card-link" href="{{ $recordUrl('profile') }}">Full profile</a>
            </div>
            <div class="master-facts">
                <div class="master-info"><span>Vendor number</span><strong @class(['master-empty-value' => $empty($vendor->vendor_number)])>{{ $vendor->vendor_number ?: 'Not on file' }}</strong></div>
                <div class="master-info"><span>Type</span><strong>{{ $vendor->typeLabel() }}</strong></div>
                <div class="master-info"><span>Category</span><strong @class(['master-empty-value' => $empty($vendor->category)])>{{ $vendor->category ?: 'Not on file' }}</strong></div>
                <div class="master-info"><span>Brand</span><strong @class(['master-empty-value' => $empty($vendor->brand_name)])>{{ $vendor->brand_name ?: 'Not on file' }}</strong></div>
                <div class="master-info"><span>Status</span><strong><span class="master-badge status-{{ str_replace('_', '-', $vendor->status) }}">{{ $vendor->statusLabel() }}</span></strong></div>
                <div class="master-info"><span>Rating</span><strong @class(['master-empty-value' => ! $vendor->rating])>{{ $vendor->rating ? str_repeat('★', $vendor->rating).' ('.$vendor->rating.' of 5)' : 'Not rated' }}</strong></div>
                <div class="master-info"><span>Preferred currency</span><strong>{{ $vendor->preferred_currency ?: 'INR' }}</strong></div>
                <div class="master-info"><span>Lead time</span><strong @class(['master-empty-value' => $vendor->lead_time_days === null])>{{ $vendor->lead_time_days !== null ? $vendor->lead_time_days.' days' : 'Not set' }}</strong></div>
                <div class="master-info"><span>Payment terms</span><strong @class(['master-empty-value' => $empty($vendor->payment_terms)])>{{ $vendor->payment_terms ?: 'Not on file' }}</strong></div>
                <div class="master-info"><span>Record created</span><strong @class(['master-empty-value' => ! $vendor->created_at])>{{ $vendor->created_at?->format('d M Y') ?: '—' }}@if ($vendor->creator) <span class="vendor-detail-note">by {{ $vendor->creator->name }}</span>@endif</strong></div>
            </div>
        </section>

        <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-overview-contact-heading">
            <div class="vendor-card-head">
                <h2 class="vendor-detail-title" id="vendor-overview-contact-heading">Primary contact</h2>
                <a class="vendor-card-link" href="{{ $recordUrl('contacts') }}">
                    {{ $contactsAvailable && $vendor->relationLoaded('contacts') && $vendor->contacts->count() ? $vendor->contacts->count().' others' : 'All contacts' }}
                </a>
            </div>
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
                <div class="master-info"><span>Website</span>
                    @if ($vendor->website)
                        <a class="vendor-detail-link" href="{{ $vendor->website }}" target="_blank" rel="noopener noreferrer">{{ $vendor->website }}</a>
                    @else
                        <strong class="master-empty-value">Not on file</strong>
                    @endif
                </div>
            </div>
            <div class="vendor-detail-actions">
                @if ($vendor->contact_person_email)
                    <a class="master-btn master-btn-soft master-btn-sm" href="mailto:{{ $vendor->contact_person_email }}">
                        <i class="fa-regular fa-envelope" aria-hidden="true"></i> Email
                    </a>
                @endif
                @if ($vendor->contact_person_mobile)
                    <a class="master-btn master-btn-soft master-btn-sm" href="tel:{{ preg_replace('/[^0-9+]/', '', $vendor->contact_person_mobile) }}">
                        <i class="fa-solid fa-phone" aria-hidden="true"></i> Call
                    </a>
                @endif
                <a class="master-btn master-btn-light master-btn-sm" href="{{ $recordUrl('contacts') }}">
                    <i class="fa-regular fa-address-book" aria-hidden="true"></i> Contacts
                </a>
            </div>
        </section>

        <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-overview-money-heading">
            <div class="vendor-card-head">
                <h2 class="vendor-detail-title" id="vendor-overview-money-heading">Money at a glance</h2>
                <a class="vendor-card-link" href="{{ $recordUrl('money') }}">Open money tab</a>
            </div>
            <div class="master-facts">
                <div class="master-info"><span>Outstanding</span><strong class="{{ $payables['outstanding'] > 0 ? 'vendor-amount-debit' : '' }}">{{ $money($payables['outstanding']) }}</strong></div>
                <div class="master-info"><span>Overdue</span><strong class="{{ $overdue > 0 ? 'vendor-amount-debit' : 'master-empty-value' }}">{{ $overdue > 0 ? $money($overdue) : 'Nothing late' }}</strong></div>
                <div class="master-info"><span>Next due</span><strong @class(['master-empty-value' => ! $payables['next_due']])>{{ $payables['next_due']?->format('d M Y') ?: 'No due date set' }}</strong></div>
                <div class="master-info"><span>Open bills</span><strong>{{ number_format(count($payables['rows'])) }}</strong></div>
                <div class="master-info"><span>Billed, all time</span><strong>{{ $money($performance['billed']) }}</strong></div>
                <div class="master-info"><span>Settled</span><strong>{{ $performance['settled_percent'] }}%</strong></div>
                <div class="master-info"><span>Vendor-currency balance</span><strong @class(['vendor-amount-negative' => $summary['vendor_balance_foreign'] > 0])>{{ $money($summary['vendor_balance_foreign'], $summary['vendor_currency']) }}</strong></div>
                <div class="master-info"><span>Ledger entries</span><strong>{{ number_format($summary['statement_count']) }}</strong></div>
            </div>
        </section>

        <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-overview-record-heading">
            <div class="vendor-card-head">
                <h2 class="vendor-detail-title" id="vendor-overview-record-heading">What is on the record</h2>
                <a class="vendor-card-link" href="{{ $recordUrl('procurement') }}">Procurement</a>
            </div>
            <div class="master-facts">
                <div class="master-info"><span>Project products</span><strong>{{ number_format($summary['project_products_count']) }}</strong></div>
                <div class="master-info"><span>Running projects</span><strong>{{ number_format($summary['running_projects']) }}</strong></div>
                <div class="master-info"><span>Products supplied</span><strong>{{ number_format($summary['products_count']) }}</strong></div>
                <div class="master-info"><span>Shipments</span><strong>{{ number_format($summary['shipments_count']) }}</strong></div>
                <div class="master-info"><span>Documents</span><strong>{{ number_format($summary['attachments_count']) }}</strong></div>
                <div class="master-info"><span>Comments</span><strong>{{ $vendor->relationLoaded('comments') ? number_format($vendor->comments->count()) : '—' }}</strong></div>
                <div class="master-info is-wide"><span>Internal notes</span><strong class="vendor-detail-notes {{ blank($vendor->notes) ? 'master-empty-value' : '' }}">{{ $vendor->notes ?: 'No internal notes on file' }}</strong></div>
            </div>
            <div class="vendor-detail-actions">
                <a class="master-btn master-btn-soft master-btn-sm" href="{{ $recordUrl('documents') }}">
                    <i class="fa-regular fa-folder-open" aria-hidden="true"></i> Documents
                </a>
                <a class="master-btn master-btn-soft master-btn-sm" href="{{ $recordUrl('procurement') }}">
                    <i class="fa-solid fa-truck" aria-hidden="true"></i> Shipments
                </a>
            </div>
        </section>
    </div>

    @include('vendors.partials.performance')

    <div class="vendor-detail-grid vendor-detail-grid--lists">
        <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-activity-heading">
            <div class="vendor-card-head">
                <h2 class="vendor-detail-title" id="vendor-activity-heading">Recent activity</h2>
                <span class="vendor-card-link">Last {{ count($vendorActivity) }}</span>
            </div>
            @include('vendors.partials.timeline')
        </section>

        <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-latest-projects-heading">
            <div class="vendor-card-head">
                <h2 class="vendor-detail-title" id="vendor-latest-projects-heading">Latest project products</h2>
                <a class="vendor-card-link" href="{{ $recordUrl('procurement') }}">All projects</a>
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
    </div>
</section>
