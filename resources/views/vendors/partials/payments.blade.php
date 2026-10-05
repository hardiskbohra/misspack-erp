<section class="master-card master-card--flat vendor-detail-card vendor-block-card" id="vendor-block-ledger" aria-labelledby="vendor-block-ledger-title">
    <div class="vendor-panel-head">
        <div>
            <h2 class="vendor-detail-title" id="vendor-block-ledger-title">Vendor-currency ledger</h2>
            <p class="vendor-detail-help">Bills raised and payments made in the vendor's own currency — the account the statement is built from.</p>
        </div>
        <div class="vendor-panel-meta">
            <span class="vendor-pill">{{ $vendorPaymentEntries->count() }} {{ \Illuminate\Support\Str::plural('entry', $vendorPaymentEntries->count()) }}</span>
            <button type="button" class="master-btn master-btn-primary master-btn-sm" id="openAddPaymentModal">
                <i class="fas fa-plus" aria-hidden="true"></i> Add entry
            </button>
        </div>
    </div>

    <div class="master-card master-card--flat vendor-table-card vendor-table-bleed">
        <div class="master-table-wrap ui-mobile-cards">
            <table class="master-table vendor-table vendor-money-table vendor-money-table--ledger">
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Entry</th>
                        <th scope="col">Particular</th>
                        <th scope="col" class="is-num">Amount</th>
                        <th scope="col" class="ui-mobile-secondary">Account</th>
                        <th scope="col">Status</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($vendorPaymentEntries->sortByDesc('transaction_date') as $entry)
                        <tr>
                            <td data-label="Date">
                                {{ $entry->transaction_date?->format('d M Y') ?: '—' }}
                                @if ($entry->transaction_type === 'credit' && $entry->due_date)
                                    @php($daysLate = $entry->days_to_due)
                                    @if ($daysLate !== null && $daysLate < 0)
                                        <span class="vendor-late-chip">{{ abs($daysLate) }} {{ \Illuminate\Support\Str::plural('day', abs($daysLate)) }} late</span>
                                    @else
                                        <span class="master-sub">
                                            Due {{ $entry->due_date->format('d M y') }}@if ($daysLate !== null && $daysLate <= 7) · due soon @endif
                                        </span>
                                    @endif
                                @elseif ($entry->transaction_type === 'credit')
                                    <span class="master-sub">No due date</span>
                                @endif
                            </td>
                            <td data-label="Entry">
                                <strong>{{ $entry->invoice_number ?: 'No invoice' }}</strong>
                                <span class="master-sub">{{ $entry->categoryLabel() }}</span>
                            </td>
                            <td data-label="Particular">
                                <strong>{{ $entry->particular }}</strong>
                                <span class="master-sub">{{ $entry->project ? $entry->project->project_number.' - '.$entry->project->name : 'No project mapping' }}</span>
                                @if ($entry->remarks)
                                    <span class="master-sub">{{ $entry->remarks }}</span>
                                @endif
                            </td>
                            {{-- The bill and the payment never both land on one row, so
                                 the two amount columns were half empty. One cell, in
                                 the entry's own currency, with the rupee value under
                                 it — that is the pair the office reads together. --}}
                            <td data-label="Amount" class="is-num {{ $entry->transaction_type === 'credit' ? 'vendor-amount-debit' : 'vendor-amount-credit' }}">
                                {{ $money($entry->foreign_amount, $entry->foreign_currency ?: $vendorCurrency) }}
                                <span class="master-sub">{{ $entry->transaction_type === 'credit' ? 'Billed' : 'Paid' }}</span>
                            </td>
                            <td data-label="Account" class="ui-mobile-secondary">
                                {{ $entry->paidAccount?->account_name ?: '—' }}
                                <span class="master-sub">{{ $entry->payment_mode ? str_replace('_', ' ', $entry->payment_mode) : '—' }}{{ $entry->bank_reference_number ? ' · '.$entry->bank_reference_number : '' }}</span>
                            </td>
                            <td data-label="Status">
                                <span class="vendor-meta-chip">{{ $entry->statusLabel() }}</span>
                                @if ($entry->cashflow_entry_id)
                                    @if (\Illuminate\Support\Facades\Route::has('cashflows.show'))
                                        <a class="vendor-sync-link" href="{{ route('cashflows.show', $entry->cashflow_entry_id) }}"
                                            title="Open the linked INR cashflow entry">
                                            <i class="fa-solid fa-link" aria-hidden="true"></i> #{{ $entry->cashflow_entry_id }}
                                        </a>
                                    @else
                                        <span class="master-sub">Cashflow #{{ $entry->cashflow_entry_id }}</span>
                                    @endif
                                @endif
                                @foreach ($entry->attachments as $attachment)
                                    <a class="vendor-file-link" href="{{ $attachment->fileUrl() }}" target="_blank" rel="noopener"
                                        title="{{ $attachment->original_name ?: 'Proof' }}">{{ strtoupper($attachment->extension ?: 'file') }}</a>
                                @endforeach
                            </td>
                            <td data-label="Action" class="vendor-table-actions-cell">
                                <div class="master-row-actions">
                                    <div class="master-dropdown">
                                        <button type="button" class="master-dropdown-toggle"
                                            aria-label="Actions for {{ $entry->particular }}"
                                            aria-haspopup="true" aria-expanded="false">
                                            <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                        </button>
                                        <div class="master-dropdown-menu">
                                            <button type="button" class="editPaymentBtn" data-payment='@json($entry)'>
                                                <i class="fas fa-pen" aria-hidden="true"></i> Edit entry
                                            </button>
                                            @if ($entry->cashflow_entry_id && \Illuminate\Support\Facades\Route::has('cashflows.show'))
                                                <a href="{{ route('cashflows.show', $entry->cashflow_entry_id) }}">
                                                    <i class="fa-solid fa-scale-balanced" aria-hidden="true"></i> Open cashflow row
                                                </a>
                                            @endif
                                            @foreach ($entry->attachments as $attachment)
                                                <a href="{{ $attachment->fileUrl() }}" target="_blank" rel="noopener">
                                                    <i class="fa-solid fa-paperclip" aria-hidden="true"></i> {{ $attachment->original_name ?: 'Proof' }}
                                                </a>
                                            @endforeach
                                            @if (\Illuminate\Support\Facades\Route::has('vendors.payments.destroy'))
                                                <form method="POST" action="{{ route('vendors.payments.destroy', $entry) }}"
                                                    data-confirm="Delete this vendor payment entry?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="danger">
                                                        <i class="far fa-trash-alt" aria-hidden="true"></i> Delete entry
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="master-list-empty">
                                    <span class="master-list-empty-icon" aria-hidden="true"><i class="fa-solid fa-scale-balanced"></i></span>
                                    <h3 class="master-list-empty-title">No ledger entries yet</h3>
                                    <p class="master-list-empty-text">Record the vendor's bill first, then the payments that settle it — the statement and the balances follow.</p>
                                    <div class="master-list-empty-actions">
                                        <button type="button" class="master-btn master-btn-primary" id="openAddPaymentModalEmpty">
                                            <i class="fas fa-plus" aria-hidden="true"></i> Add first entry
                                        </button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
