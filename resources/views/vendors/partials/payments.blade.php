@php
    $paymentLedgerAvailable = \Illuminate\Support\Facades\Schema::hasTable('vendor_payment_entries');
    $cashflowRows = $statementEntries->filter(fn ($entry) => (float) ($entry->debit_amount ?? 0) > 0 || (float) ($entry->credit_amount ?? 0) > 0);
@endphp

<div class="vendor-detail-stack">
    <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-payments-heading">
        <div class="master-section-head">
            <div>
                <h2 class="master-section-title" id="vendor-payments-heading">Vendor currency ledger</h2>
                <p class="master-sub">Bills, payments, adjustments and supporting documents in the vendor's currency.</p>
            </div>
            @if ($paymentLedgerAvailable)
                <button type="button" class="master-btn master-btn-primary" id="openAddPaymentModal">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Add ledger entry
                </button>
            @endif
        </div>

        @if ($paymentLedgerAvailable)
            <div class="master-table-wrap ui-mobile-cards">
                <table class="master-table vendor-detail-table vendor-payment-table">
                    <thead>
                        <tr>
                            <th scope="col">Date / invoice</th>
                            <th scope="col">Particular</th>
                            <th scope="col">Credit / bill</th>
                            <th scope="col">Debit / paid</th>
                            <th scope="col">₹ equivalent</th>
                            <th scope="col">Account / mode</th>
                            <th scope="col">Status / cashflow</th>
                            <th scope="col">Proof</th>
                            <th scope="col"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($vendorPaymentEntries->sortByDesc('transaction_date') as $entry)
                            @php
                                $entryAttachments = $entry->relationLoaded('attachments') ? $entry->attachments : collect();
                                $entryProject = $entry->relationLoaded('project') ? $entry->project : null;
                            @endphp
                            <tr>
                                <td data-label="Date / invoice">
                                    <strong>{{ optional($entry->transaction_date)->format('d M Y') ?: '—' }}</strong>
                                    <span class="vendor-table-meta">{{ $entry->invoice_number ?: 'No bill number' }}</span>
                                </td>
                                <td data-label="Particular">
                                    <strong>{{ $entry->particular }}</strong>
                                    <span class="vendor-table-meta">{{ $entry->categoryLabel() }} · {{ $entryProject?->project_number ?: ($entryProject ? 'Project #'.$entryProject->id : 'No project') }}</span>
                                    @if ($entry->remarks)<span class="vendor-table-meta">{{ $entry->remarks }}</span>@endif
                                </td>
                                <td data-label="Credit / bill" class="vendor-numeric vendor-amount-credit">
                                    {{ $entry->transaction_type === 'credit' ? $money($entry->foreign_amount, $entry->foreign_currency ?: 'RMB') : '—' }}
                                </td>
                                <td data-label="Debit / paid" class="vendor-numeric vendor-amount-debit">
                                    {{ $entry->transaction_type === 'debit' ? $money($entry->foreign_amount, $entry->foreign_currency ?: 'RMB') : '—' }}
                                </td>
                                <td data-label="₹ equivalent" class="vendor-numeric">
                                    {{ $money($entry->amount_in_inr, 'INR') }}
                                    <span class="vendor-table-meta">Rate {{ $entry->exchange_rate ? number_format((float) $entry->exchange_rate, 4) : '—' }}</span>
                                </td>
                                <td data-label="Account / mode">
                                    <span>{{ $entry->relationLoaded('paidAccount') ? ($entry->paidAccount?->account_name ?: 'No account') : 'No account' }}</span>
                                    <span class="vendor-table-meta">{{ $paymentOptions['mode'][$entry->payment_mode] ?? ($entry->payment_mode ? \Illuminate\Support\Str::headline($entry->payment_mode) : 'No payment mode') }}</span>
                                    @if ($entry->bank_reference_number)<span class="vendor-table-meta">{{ $entry->bank_reference_number }}</span>@endif
                                </td>
                                <td data-label="Status / cashflow">
                                    <span class="master-badge vendor-payment-status vendor-payment-status-{{ str_replace('_', '-', $entry->status) }}">{{ $entry->statusLabel() }}</span>
                                    @if ($entry->cashflow_entry_id)
                                        @if (\Illuminate\Support\Facades\Route::has('cashflows.show'))
                                            <a class="vendor-table-meta vendor-detail-link" href="{{ route('cashflows.show', $entry->cashflow_entry_id) }}">Cashflow #{{ $entry->cashflow_entry_id }}</a>
                                        @else
                                            <span class="vendor-table-meta">Cashflow #{{ $entry->cashflow_entry_id }}</span>
                                        @endif
                                    @endif
                                </td>
                                <td data-label="Proof">
                                    @forelse ($entryAttachments as $attachment)
                                        <a class="vendor-file-link" href="{{ $attachment->fileUrl() }}" target="_blank" rel="noopener">
                                            <i class="fa-solid fa-paperclip" aria-hidden="true"></i>
                                            {{ $attachment->title ?: ($attachment->extension ?: 'File') }}
                                            <span class="visually-hidden">(opens in a new tab)</span>
                                        </a>
                                    @empty
                                        <span class="master-empty-value">No files</span>
                                    @endforelse
                                </td>
                                <td data-label="Actions">
                                    <div class="master-row-actions vendor-payment-actions">
                                        @if (\Illuminate\Support\Facades\Route::has('vendors.payments.update'))
                                            <button type="button" class="master-icon-btn editPaymentBtn"
                                                data-update-url="{{ route('vendors.payments.update', [$vendor, $entry]) }}"
                                                data-payment='@json($entry)'
                                                aria-label="Edit ledger entry: {{ $entry->particular }}" title="Edit entry">
                                                <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                            </button>
                                        @endif
                                        @if (\Illuminate\Support\Facades\Route::has('vendors.payments.destroy'))
                                            <form method="POST" action="{{ route('vendors.payments.destroy', $entry) }}"
                                                data-confirm="Delete this vendor payment entry and its linked INR cashflow entry?">
                                                @csrf
                                                @method('DELETE')
                                                <button class="master-icon-btn danger" type="submit" aria-label="Delete ledger entry: {{ $entry->particular }}" title="Delete entry">
                                                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9"><div class="master-empty-state"><i class="fa-solid fa-receipt" aria-hidden="true"></i><p>No vendor ledger entries yet. Add a bill, payment or adjustment to begin.</p></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @else
            <div class="master-empty-state"><i class="fa-solid fa-database" aria-hidden="true"></i><p>The vendor-currency ledger is not installed. Existing cashflow records remain available below.</p></div>
        @endif
    </section>

    <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-cashflow-heading">
        <div class="master-section-head">
            <div>
                <h2 class="master-section-title" id="vendor-cashflow-heading">Linked INR cashflow</h2>
                <p class="master-sub">Cashbook entries linked to this vendor, including records created before the vendor-currency ledger.</p>
            </div>
            <span class="master-chip">{{ number_format($cashflowRows->count()) }} {{ \Illuminate\Support\Str::plural('entry', $cashflowRows->count()) }}</span>
        </div>

        @if ($cashflowRows->isNotEmpty())
            <div class="master-table-wrap ui-mobile-cards">
                <table class="master-table vendor-detail-table vendor-linked-cashflow-table">
                    <thead>
                        <tr>
                            <th scope="col">Date</th>
                            <th scope="col">Category</th>
                            <th scope="col">Particular</th>
                            <th scope="col">Credit</th>
                            <th scope="col">Debit / paid</th>
                            <th scope="col">Account / mode</th>
                            <th scope="col">Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cashflowRows as $entry)
                            <tr>
                                <td data-label="Date">{{ optional($entry->entry_date)->format('d M Y') ?: '—' }}</td>
                                <td data-label="Category">{{ ($entry->relationLoaded('category') ? $entry->category?->name : null) ?: ($entry->expense_head ?: '—') }}</td>
                                <td data-label="Particular"><strong>{{ $entry->particular }}</strong>@if ($entry->notes)<span class="vendor-table-meta">{{ $entry->notes }}</span>@endif</td>
                                <td data-label="Credit" class="vendor-numeric vendor-amount-credit">{{ (float) ($entry->credit_amount ?? 0) > 0 ? $money($entry->credit_amount, $entry->currency ?: 'INR') : '—' }}</td>
                                <td data-label="Debit / paid" class="vendor-numeric vendor-amount-debit">{{ (float) ($entry->debit_amount ?? 0) > 0 ? $money($entry->debit_amount, $entry->currency ?: 'INR') : '—' }}</td>
                                <td data-label="Account / mode">
                                    <span>{{ $entry->relationLoaded('account') ? ($entry->account?->account_name ?: 'No account') : 'No account' }}</span>
                                    <span class="vendor-table-meta">{{ $entry->payment_mode ?: 'No payment mode' }}</span>
                                </td>
                                <td data-label="Reference">{{ $entry->bank_reference_number ?: ($entry->invoice_bill_number ?: '—') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="master-empty-state"><i class="fa-solid fa-building-columns" aria-hidden="true"></i><p>No linked cashflow entries were found for this vendor.</p></div>
        @endif
    </section>

    @if ($paymentLedgerAvailable)
        @include('vendors.partials.payment-modals')
    @endif
</div>
