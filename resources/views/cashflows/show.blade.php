@extends('layouts.app')

@section('title', $entry->particular ?: 'Cashflow')
@section('page-title', $entry->voucherNumber())

@section('page-actions')
    <a class="master-btn master-btn-ghost" href="{{ route('cashflows.index') }}">Ledger</a>
    @if ($entry->isMoneyOut() && ($entry->category_id || $entry->expense_head))
        <a class="master-btn master-btn-ghost" href="{{ route('cashflows.expenseStatement', $entry) }}">Expense statement</a>
    @endif
    <a class="master-btn master-btn-soft" href="{{ route('cashflows.voucher', $entry) }}">Print {{ strtolower($entry->voucherTitle()) }}</a>
    <a class="master-btn master-btn-primary" href="{{ route('cashflows.edit', $entry) }}">Edit</a>
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/employees.css') }}">
    <link rel="stylesheet" href="{{ $assetVer('assets/css/cashflows.css') }}">
@endpush

@php
    $tabUrl = fn (string $key) => route('cashflows.show', ['cashflow' => $entry, 'tab' => $key]);
    $money = fn ($value) => \App\Helpers\CommonHelper::amount($value, $entry->currency ?: 'INR');
    $statusClass = str_replace('_', '-', (string) $entry->accounting_status);
    $kind = $entry->voucherKind();
@endphp

<div class="cf cf-record emp employee-record master-list">
    <div class="master-card master-card--flat emp-record-head">
        <div class="emp-record-who">
            <span class="emp-avatar cf-record-avatar is-{{ $kind }}" aria-hidden="true">
                {{ $entry->isMoneyOut() ? 'Out' : 'In' }}
            </span>
            <div>
                <h1>{{ $entry->particular ?: 'Cashflow entry' }}</h1>
                <p class="master-sub emp-record-line">
                    {{ $entry->voucherNumber() }}
                    · {{ $entry->entry_date?->format('d M Y') }}
                    @if ($entry->account) · {{ $entry->account->account_name }}@endif
                    @if ($entry->bank_reference_number) · Ref {{ $entry->bank_reference_number }}@endif
                </p>
                <p class="emp-record-tags">
                    <span class="emp-pill {{ $entry->isMoneyOut() ? 'is-warn' : 'is-ok' }}">{{ $entry->isMoneyOut() ? 'Money out' : 'Money in' }}</span>
                    <span class="emp-pill is-info">{{ $entry->voucherTitle() }}</span>
                    <span class="emp-pill {{ $entry->accounting_status === 'reconciled' ? 'is-ok' : ($entry->accounting_status === 'pending' ? 'is-warn' : 'is-off') }}">{{ $entry->statusLabel() }}</span>
                    @if ($entry->category)<span class="emp-pill is-off">{{ $entry->category->name }}</span>@endif
                    @if ($entry->partyLabel())<span class="emp-pill is-off">{{ $entry->partyLabel() }}</span>@endif
                    @if ($entry->recurrenceOccurrence?->rule)
                        <a class="emp-pill is-info" href="{{ route('cashflows.recurring.show', $entry->recurrenceOccurrence->rule) }}"
                            title="Posted by the recurring rule {{ $entry->recurrenceOccurrence->rule->title }}">↻ Recurring rule</a>
                    @endif
                </p>
            </div>
        </div>
        <div class="cf-record-nav">
            @if ($previousEntry)
                <a class="master-btn master-btn-light master-btn-sm" href="{{ route('cashflows.show', $previousEntry) }}">Previous</a>
            @endif
            @if ($nextEntry)
                <a class="master-btn master-btn-light master-btn-sm" href="{{ route('cashflows.show', $nextEntry) }}">Next</a>
            @endif
        </div>
    </div>

    <div class="master-stats desktop-only">
        <div class="master-stat master-stat--flat {{ $entry->isMoneyOut() ? 'orange' : 'green' }}">
            <span class="icon" aria-hidden="true">₹</span>
            <div>
                <p class="master-stat-title">{{ $entry->isMoneyOut() ? 'Paid' : 'Received' }}</p>
                <p class="master-stat-value">{{ $money($entry->amountMoved()) }}</p>
                <p class="master-sub">{{ \App\Helpers\CommonHelper::inWords($entry->amountMoved(), $entry->currency ?: 'INR') }}</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat blue">
            <span class="icon" aria-hidden="true">🏦</span>
            <div>
                <p class="master-stat-title">Account</p>
                <p class="master-stat-value">{{ $entry->account?->typeLabel() ?: '—' }}</p>
                <p class="master-sub">{{ $entry->account?->account_name }} · bal {{ $entry->balance !== null ? $money($entry->balance) : '—' }}</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat teal">
            <span class="icon" aria-hidden="true">📅</span>
            <div>
                <p class="master-stat-title">This type in {{ $entry->entry_date?->format('M Y') }}</p>
                <p class="master-stat-value">{{ $money($typeMonthTotal) }}</p>
                <p class="master-sub">{{ $entry->category?->name ?: ($entry->expense_head ?: 'Same direction') }}</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat {{ $entry->attachments->isEmpty() ? 'orange' : 'green' }}">
            <span class="icon" aria-hidden="true">📄</span>
            <div>
                <p class="master-stat-title">Documents</p>
                <p class="master-stat-value">{{ $entry->attachments->count() }}</p>
                <p class="master-sub">{{ $entry->attachments->isEmpty() ? 'nothing on file' : 'filed against this entry' }}</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat {{ $missing ? 'orange' : 'green' }}">
            <span class="icon" aria-hidden="true">{{ $missing ? '!' : '✓' }}</span>
            <div>
                <p class="master-stat-title">Record</p>
                <p class="master-stat-value">{{ $missing ? count($missing).' to add' : 'Complete' }}</p>
                <p class="master-sub">{{ $missing ? implode(', ', array_slice($missing, 0, 2)) : 'nothing outstanding' }}</p>
            </div>
        </div>
    </div>

    <div class="master-tabs-card">
        <div class="master-tabs" role="tablist" aria-label="Entry sections">
            @foreach ($tabs as $key => $label)
                <a class="master-tab {{ $tab === $key ? 'is-active' : '' }}" role="tab"
                    aria-selected="{{ $tab === $key ? 'true' : 'false' }}" href="{{ $tabUrl($key) }}">
                    {{ $label }}
                    @if (($tabCounts[$key] ?? 0) > 0)
                        <span class="master-tab-count">{{ $tabCounts[$key] }}</span>
                    @endif
                </a>
            @endforeach
        </div>

        <div class="master-tabs-panels">
            @if ($tab === 'overview')
                <section class="master-tab-panel" aria-label="Overview">
                    @if (! empty($linkedShipmentCost) || ! empty($linkedVendorPayment))
                        <div class="master-card master-card--flat master-section cf-sync-note">
                            @if (! empty($linkedShipmentCost))
                                <h3 class="master-section-title">Linked shipment cost</h3>
                                <p class="master-sub">
                                    Generated from a shipment cost head
                                    @if (\Illuminate\Support\Facades\Route::has('shipments.show') && $linkedShipmentCost->shipment)
                                        — <a href="{{ route('shipments.show', $linkedShipmentCost->shipment) }}">open the shipment</a>
                                    @endif
                                    · {{ $linkedShipmentCost->shipment?->shipment_number }}
                                    · {{ $linkedShipmentCost->headLabel() }}
                                </p>
                            @endif
                            @if (! empty($linkedVendorPayment))
                                <h3 class="master-section-title">Linked vendor payment</h3>
                                <p class="master-sub">
                                    Generated from a vendor payment
                                    @if (\Illuminate\Support\Facades\Route::has('vendors.show'))
                                        — <a href="{{ route('vendors.show', $linkedVendorPayment->vendor_id) }}#payments">open the vendor ledger</a>
                                    @endif
                                    · {{ $linkedVendorPayment->vendor?->vendor_name }}
                                    · {{ \App\Helpers\CommonHelper::amount($linkedVendorPayment->foreign_amount, $linkedVendorPayment->foreign_currency) }}
                                </p>
                            @endif
                        </div>
                    @endif

                    <div class="master-grid">
                        <div class="master-card master-card--flat master-section">
                            <h3 class="master-section-title">What is missing</h3>
                            <ul class="emp-todo">
                                @forelse ($missing as $item)
                                    <li>
                                        <span class="emp-todo-dot" aria-hidden="true"></span>
                                        <span><strong>{{ $item }}</strong></span>
                                        @if ($item === 'Supporting document')
                                            <a class="master-btn master-btn-ghost master-btn-sm" href="{{ $tabUrl('documents') }}">File</a>
                                        @endif
                                    </li>
                                @empty
                                    <li>
                                        <span class="emp-todo-dot" aria-hidden="true"></span>
                                        <span>Party, category, status and a document are on file.</span>
                                    </li>
                                @endforelse
                            </ul>
                        </div>

                        <div class="master-card master-card--flat master-section">
                            <h3 class="master-section-title">Paperwork</h3>
                            <p class="master-sub">Print the voucher the books expect for this account, or the expense statement for this head in the financial year.</p>
                            <div class="emp-card-foot">
                                <a class="master-btn master-btn-primary master-btn-sm" href="{{ route('cashflows.voucher', $entry) }}">{{ $entry->voucherTitle() }}</a>
                                @if ($entry->isMoneyOut() && ($entry->category_id || $entry->expense_head))
                                    <a class="master-btn master-btn-soft master-btn-sm" href="{{ route('cashflows.expenseStatement', $entry) }}">FY expense statement</a>
                                    <a class="master-btn master-btn-ghost master-btn-sm" href="{{ route('cashflows.reports', array_filter(['dimension' => $entry->category_id ? 'category' : 'expense_head', 'category_id' => $entry->category_id, 'expense_head' => $entry->expense_head])) }}">Open report</a>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="master-card master-card--flat master-section">
                        <h3 class="master-section-title">Statement details</h3>
                        <div class="master-info-grid">
                            <div class="master-info"><span>Voucher</span><strong>{{ $entry->voucherNumber() }} · {{ $entry->voucherTitle() }}</strong></div>
                            <div class="master-info"><span>Date</span><strong>{{ $entry->entry_date?->format('d M Y') }}</strong></div>
                            <div class="master-info"><span>Account</span><strong>{{ $entry->account?->account_name ?: '—' }}</strong></div>
                            <div class="master-info"><span>Account type</span><strong>{{ $entry->account?->typeLabel() ?: '—' }}</strong></div>
                            <div class="master-info"><span>Payment mode</span><strong>{{ $paymentModeOptions[$entry->payment_mode] ?? '—' }}</strong></div>
                            <div class="master-info"><span>Bank reference</span><strong>{{ $entry->bank_reference_number ?: '—' }}</strong></div>
                            <div class="master-info"><span>Invoice / bill</span><strong>{{ $entry->invoice_bill_number ?: '—' }}</strong></div>
                            <div class="master-info"><span>Category</span><strong>{{ $entry->category?->name ?: 'Uncategorized' }}</strong></div>
                            <div class="master-info"><span>Expense head</span><strong>{{ $entry->expense_head ?: '—' }}</strong></div>
                            <div class="master-info"><span>Related to</span><strong>{{ $relatedPartyOptions[$entry->related_party_type] ?? '—' }} · {{ $entry->partyLabel() ?: '—' }}</strong></div>
                            @if ($entry->project)
                                <div class="master-info"><span>Project</span><strong>{{ $entry->project->project_number }} · {{ $entry->project->name }}</strong></div>
                            @endif
                            @if ($entry->salesInvoice)
                                <div class="master-info"><span>Sales invoice</span><strong>{{ $entry->salesInvoice->invoice_number }}</strong></div>
                            @endif
                            <div class="master-info"><span>Created by</span><strong>{{ $entry->creator?->name ?? '—' }}</strong></div>
                        </div>
                    </div>

                    <div class="master-card master-card--flat master-section">
                        <h3 class="master-section-title">Notes</h3>
                        <div class="master-muted-box">{{ $entry->notes ?: 'No notes added.' }}</div>
                    </div>

                    <div class="master-card master-card--flat master-section">
                        <h3 class="master-section-title">Accounting status</h3>
                        <p class="master-sub">Book, reconcile or flag the row without opening the full edit form.</p>
                        <form method="POST" action="{{ route('cashflows.status', $entry) }}" class="cf-status-row">
                            @csrf
                            @method('PATCH')
                            <select class="master-select" name="accounting_status" aria-label="Accounting status">
                                @foreach ($accountingStatusOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($entry->accounting_status === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="master-btn master-btn-primary master-btn-sm">Update status</button>
                        </form>
                    </div>

                    <div class="emp-card-foot">
                        <form method="POST" action="{{ route('cashflows.duplicate', $entry) }}">
                            @csrf
                            <button type="submit" class="master-btn master-btn-soft">Duplicate as new entry</button>
                        </form>
                        <form method="POST" action="{{ route('cashflows.destroy', $entry) }}" onsubmit="return confirm('Delete this cashflow entry?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="master-btn master-btn-light">Delete</button>
                        </form>
                    </div>
                </section>
            @elseif ($tab === 'documents')
                <section class="master-tab-panel" aria-label="Documents">
                    @include('cashflows.partials.documents-card', ['documentTypeOptions' => $documentTypeOptions])
                </section>
            @else
                <section class="master-tab-panel" aria-label="Related">
                    <div class="master-card master-card--flat master-section">
                        <h3 class="master-section-title">Related entries</h3>
                        <p class="master-sub">Other rows for the same party, or the same category this month if no party is linked.</p>
                        @if ($relatedEntries->isEmpty())
                            <div class="master-list-empty">
                                <p class="master-list-empty-title">Nothing related yet</p>
                                <p class="master-list-empty-text">Link a client, vendor, employee or office service to group this payment with others.</p>
                            </div>
                        @else
                            <div class="master-table-wrap">
                                <table class="master-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Particular</th>
                                            <th>Account</th>
                                            <th>Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($relatedEntries as $row)
                                            <tr class="is-clickable" data-href="{{ route('cashflows.show', $row) }}">
                                                <td>{{ $row->entry_date?->format('d M Y') }}</td>
                                                <td>{{ $row->particular }}</td>
                                                <td>{{ $row->account?->account_name }}</td>
                                                <td>{{ \App\Helpers\CommonHelper::amount($row->amountMoved(), $row->currency ?: 'INR') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </section>
            @endif
        </div>
    </div>
</div>
@endsection
