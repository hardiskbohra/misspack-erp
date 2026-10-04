@php
    /**
     * The vendor ledger's rows for this bill.
     *
     * A purchase bill is a payable, and the payable lives in
     * `vendor_payment_entries` — one credit posted by `PurchaseBillLedger` when
     * the bill was saved, and one debit for every payment made against it. The
     * record shows those rows rather than restating them, because the ledger is
     * what the office reconciles against the bank; each row opens its entry in
     * the vendor module.
     */
    $posted = $ledgerEntries->firstWhere('transaction_type', 'credit');
    $paidRows = $ledgerEntries->where('transaction_type', 'debit');
@endphp
<div class="master-card master-section">
    <div class="master-section-head">
        <div>
            <h2 class="master-section-title">Vendor ledger</h2>
            <p class="master-sub">
                What this bill posted, and every payment filed against it — the same rows the vendor's own Money tab reads.
            </p>
        </div>
        <div class="master-section-meta">
            <span class="master-chip">
                <i class="fas fa-scale-balanced" aria-hidden="true"></i>
                {{ $ledgerEntries->count() }} {{ \Illuminate\Support\Str::plural('row', $ledgerEntries->count()) }}
            </span>
            @if ($invoice->vendor)
                <a class="master-btn master-btn-soft master-btn-sm"
                    href="{{ route('vendors.show', ['vendor' => $invoice->vendor, 'tab' => 'money']) }}">
                    <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i> Open in vendor money tab
                </a>
            @endif
        </div>
    </div>

    @if ($ledgerEntries->isEmpty())
        <p class="master-empty-value">
            Nothing has reached the vendor ledger for this bill yet. It posts on save — if this bill was just created,
            reload the page.
        </p>
    @else
        <div class="master-table-wrap ui-mobile-cards">
            <table class="master-table pi-ledger-table">
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Entry</th>
                        <th scope="col">Particular</th>
                        <th scope="col" class="is-num">Amount</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ledgerEntries as $entry)
                        @php($isBill = $entry->transaction_type === 'credit')
                        <tr>
                            <td data-label="Date">{{ $entry->transaction_date?->format('d M Y') ?: '—' }}</td>
                            <td data-label="Entry">
                                <strong>{{ $isBill ? 'Bill posted' : 'Payment' }}</strong>
                                <span class="master-sub">{{ $entry->categoryLabel() }}</span>
                            </td>
                            <td data-label="Particular">
                                <strong>{{ $entry->particular }}</strong>
                                @if ($entry->bank_reference_number)
                                    <span class="master-sub">{{ $entry->payment_mode ? str_replace('_', ' ', $entry->payment_mode).' · ' : '' }}{{ $entry->bank_reference_number }}</span>
                                @endif
                            </td>
                            <td data-label="Amount" class="is-num {{ $isBill ? 'pi-amount-bill' : 'pi-amount-paid' }}">
                                {{ \App\Helpers\CommonHelper::amount($entry->foreign_amount, $entry->foreign_currency ?: $invoice->currency) }}
                                <span class="master-sub">{{ \App\Helpers\CommonHelper::indianCurrency($entry->amount_in_inr) }}</span>
                            </td>
                            <td data-label="Status">
                                {{-- The ledger's own states, drawn in this module's tones:
                                     booked is a row nobody has reconciled yet, cleared is one
                                     the bank has matched, cancelled is written off. --}}
                                <span class="pi-status status-{{ ['booked' => 'received', 'cleared' => 'paid', 'cancelled' => 'cancelled'][$entry->status] ?? 'draft' }}">{{ $entry->statusLabel() }}</span>
                                @if ($entry->cashflow_entry_id)
                                    <a class="master-sub pi-ledger-link"
                                        href="{{ route('cashflows.show', $entry->cashflow_entry_id) }}">
                                        <i class="fas fa-link" aria-hidden="true"></i> Cashflow #{{ $entry->cashflow_entry_id }}
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="pi-pay-foot">
            <div>
                <span>Posted</span>
                <strong>{{ \App\Helpers\CommonHelper::indianCurrency($posted?->amount_in_inr ?? 0) }}</strong>
            </div>
            <div>
                <span>Paid</span>
                <strong>{{ \App\Helpers\CommonHelper::indianCurrency($paidRows->sum('amount_in_inr')) }}</strong>
            </div>
            <div>
                <span>Balance</span>
                <strong class="{{ $invoice->balanceDue() > 0 ? 'pi-due' : 'pi-clear' }}">
                    {{ \App\Helpers\CommonHelper::indianCurrency($invoice->balanceDue()) }}
                </strong>
            </div>
        </div>
    @endif
</div>
