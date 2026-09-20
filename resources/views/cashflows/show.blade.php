@extends('layouts.app')

@section('page-title', 'Cashflow Detail')

@section('content')
<style>

    .status-pending {
        background: #fff4e5;
        color: #d97706
    }

    .status-booked {
        background: #eaf1ff;
        color: #3f7cf4
    }

    .status-reconciled {
        background: #e8fff7;
        color: #0e9f6e
    }

    .status-disputed {
        background: #ffeaf0;
        color: #e11d48
    }

    .status-ignored {
        background: #f3f6fb;
        color: #536079
    }

    .type-credit {
        background: #e8fff7;
        color: #0e9f6e
    }

    .type-debit {
        background: #ffeaf0;
        color: #e11d48
    }

    .amount-credit {
        font-size: 30px;
        color: #059669;
        font-weight: 900
    }

    .amount-debit {
        font-size: 30px;
        color: #e11d48;
        font-weight: 900
    }

    .master-muted-box {
        background: #fbfdff;
        border: 1px solid var(--master-border);
        border-radius: 14px;
        padding: 14px;
        color: #536079;
        font-weight: 700;
        line-height: 1.55
    }
</style>
@php($statusClass = str_replace('_', '-', $entry->accounting_status))
<div class="cf">
    <div class="master-card master-header">
        <div>
            <h1>{{ $entry->particular }}</h1>
            <p style="font-size:16px;font-weight:500;">{{ $entry->entry_date?->format('d M Y') }} · Ref: {{ $entry->bank_reference_number ?: '-' }}</p>
        </div>
        <div class="master-actions"><a href="{{ route('cashflows.index') }}" class="master-btn master-btn-light">Back</a><a
                href="{{ route('cashflows.edit', $entry) }}" class="master-btn master-btn-primary">Edit Entry</a></div>
    </div>
    <div class="master-grid">
        <div>
            <div class="master-card master-section">
                <h3 class="master-section-title">Statement Details</h3>
                <div class="master-info-grid">
                    <div class="master-info"><span>Transaction Type</span><strong><span
                                class="master-badge type-{{ $entry->transaction_type }}">{{ ucfirst($entry->transaction_type) }}</span></strong>
                    </div>
                    <div class="master-info"><span>Accounting Status</span><strong><span
                                class="master-badge status-{{ $statusClass }}">{{ $entry->statusLabel() }}</span></strong>
                    </div>
                    <div class="master-info"><span>Invoice / Bill
                            Number</span><strong>{{ $entry->invoice_bill_number ?: '-' }}</strong></div>
                    <div class="master-info"><span>Bank Reference
                            Number</span><strong>{{ $entry->bank_reference_number ?: '-' }}</strong></div>
                    <div class="master-info">
                        <span>Account</span><strong>{{ $entry->account?->account_name ?: '-' }}</strong></div>
                    <div class="master-info"><span>Account
                            Type</span><strong>{{ $entry->account?->typeLabel() ?: '-' }}</strong></div>
                    <div class="master-info">
                        <span>Category</span><strong>{{ $entry->category?->name ?: 'Uncategorized' }}</strong></div>
                    <div class="master-info"><span>Payment
                            Mode</span><strong>{{ $paymentModeOptions[$entry->payment_mode] ?? '-' }}</strong></div>
                </div>
            </div>
            <div class="master-card master-section">
                <h3 class="master-section-title">Linked Party / Reporting</h3>
                <div class="master-info-grid">
                    <div class="master-info"><span>Related
                            Type</span><strong>{{ $relatedPartyOptions[$entry->related_party_type] ?? '-' }}</strong>
                    </div>
                    <div class="master-info"><span>Related
                            Party</span><strong>{{ $entry->client?->company_name ?? $entry->vendor?->vendor_name ?? $entry->related_party_name ?? '-' }}</strong>
                    </div>
                    <div class="master-info"><span>Expense Head</span><strong>{{ $entry->expense_head ?: '-' }}</strong>
                    </div>
                    <div class="master-info"><span>Created
                            By</span><strong>{{ $entry->creator?->name ?? $entry->creator?->email ?? '-' }}</strong>
                    </div>
                </div>
            </div>
        </div>
        <div>
            <div class="master-card master-section">
                <h3 class="master-section-title">Amount</h3>
                <div class="{{ $entry->transaction_type === 'credit' ? 'amount-credit' : 'amount-debit' }}">
                    {{ $entry->currency }} {{ number_format($entry->amount(), 2) }}</div>
                <p style="font-weight:800;color:#687386;">Balance:
                    {{ $entry->balance !== null ? $entry->currency . ' ' . number_format((float) $entry->balance, 2) : '-' }}
                </p>
            </div>
            <div class="master-card master-section">
                <h3 class="master-section-title">Notes</h3>
                <div class="master-muted-box">{{ $entry->notes ?: 'No notes added.' }}</div>
            </div>
        </div>
    </div>
</div>
@endsection