@extends('layouts.app')

@section('page-title', 'Cashflow Detail')

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/cashflows.css') }}">
@endpush
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
    @if (! empty($linkedShipmentCost))
        <div class="master-card master-section cf-sync-note">
            <h3 class="master-section-title">Linked Shipment Cost</h3>
            <p style="margin:0 0 6px;font-weight:700;">
                This INR entry is generated from a shipment cost head
                @if (\Illuminate\Support\Facades\Route::has('shipments.show') && $linkedShipmentCost->shipment)
                    — <a href="{{ route('shipments.show', $linkedShipmentCost->shipment) }}">open the shipment</a>
                @endif
            </p>
            <p style="margin:0;font-weight:600;color:#687386;">
                {{ $linkedShipmentCost->shipment?->shipment_number }}
                · {{ $linkedShipmentCost->headLabel() }}
                · {{ $linkedShipmentCost->amountLabel() }}
                @if ($linkedShipmentCost->exchange_rate)
                    @ {{ rtrim(rtrim(number_format((float) $linkedShipmentCost->exchange_rate, 2), '0'), '.') }}
                @endif
                · edit or delete it from the shipment page and this entry follows automatically.
            </p>
        </div>
    @endif
    @if (! empty($linkedVendorPayment))
        <div class="master-card master-section cf-sync-note">
            <h3 class="master-section-title">Linked Vendor Payment</h3>
            <p style="margin:0 0 6px;font-weight:700;">
                This INR entry is generated from a vendor payment
                @if (\Illuminate\Support\Facades\Route::has('vendors.show'))
                    — <a href="{{ route('vendors.show', $linkedVendorPayment->vendor_id) }}#payments">open the vendor ledger</a>
                @endif
            </p>
            <p style="margin:0;font-weight:600;color:#687386;">
                {{ $linkedVendorPayment->relationLoaded('vendor') && $linkedVendorPayment->vendor ? $linkedVendorPayment->vendor->vendor_name : 'Vendor' }}
                · {{ \App\Helpers\CommonHelper::amount($linkedVendorPayment->foreign_amount, $linkedVendorPayment->foreign_currency) }}
                @if ($linkedVendorPayment->exchange_rate)
                    @ {{ rtrim(rtrim(number_format((float) $linkedVendorPayment->exchange_rate, 2), '0'), '.') }}
                @endif
                · edit or delete it from the vendor page and this entry follows automatically.
            </p>
        </div>
    @endif
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
                    {{ \App\Helpers\CommonHelper::amount($entry->amount(), $entry->currency) }}</div>
                <p style="font-weight:800;color:#687386;">Balance:
                    {{ $entry->balance !== null ? \App\Helpers\CommonHelper::amount($entry->balance, $entry->currency) : '-' }}
                </p>
            </div>
            <div class="master-card master-section">
                <h3 class="master-section-title">Notes</h3>
                <div class="master-muted-box">{{ $entry->notes ?: 'No notes added.' }}</div>
            </div>
        </div>

        {{-- The documents card is a full-width row of this grid: it takes the
             grid's own gutter — and the 22px rhythm the columns above keep —
             instead of hanging under them with no margin of its own. --}}
        @include('cashflows.partials.documents-card', ['documentTypeOptions' => $documentTypeOptions])
    </div>
</div>
@endsection