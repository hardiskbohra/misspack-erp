@extends('layouts.app')

@section('title', $service->name)
@section('page-title', $service->name)

@section('page-actions')
    <a href="{{ route('office-services.edit', $service) }}" class="master-btn master-btn-light">Edit</a>
    <a href="{{ route('cashflows.index') }}" class="master-btn master-btn-primary">Record payment</a>
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/office-services.css') }}">
@endpush

<div class="office-service-show">
    <header class="master-card master-header">
        <div>
            <p class="master-sub">{{ $service->service_number }}</p>
            <h1>{{ $service->name }}</h1>
            <p>
                <span class="ofs-class ofs-class--{{ $service->service_class }}">{{ $service->classLabel() }}</span>
                · {{ $service->statusLabel() }}
            </p>
            <nav class="master-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('office-services.index') }}">Office services</a><span aria-hidden="true">/</span>
                <span class="active">{{ $service->name }}</span>
            </nav>
        </div>
        <form method="POST" action="{{ route('office-services.destroy', $service) }}" onsubmit="return confirm('Remove this office service?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="master-btn master-btn-light">Delete</button>
        </form>
    </header>

    <div class="master-detail-grid">
        <section class="master-card">
            <h3 class="master-section-title">Contact</h3>
            <dl class="master-dl">
                <div><dt>Person</dt><dd>{{ $service->contact_name ?: '—' }}</dd></div>
                <div><dt>Phone</dt><dd>{{ $service->phone ?: '—' }}</dd></div>
                <div><dt>Email</dt><dd>{{ $service->email ?: '—' }}</dd></div>
                <div><dt>Address</dt><dd>{{ $service->address ?: '—' }}</dd></div>
            </dl>
        </section>
        <section class="master-card">
            <h3 class="master-section-title">Retainer</h3>
            <dl class="master-dl">
                <div><dt>Amount</dt><dd>{{ $service->retainer_amount ? \App\Helpers\CommonHelper::indianCurrency($service->retainer_amount) : '—' }}</dd></div>
                <div><dt>Cycle</dt><dd>{{ $service->cycleLabel() }}</dd></div>
            </dl>
        </section>
        <section class="master-card">
            <h3 class="master-section-title">Bank</h3>
            <dl class="master-dl">
                <div><dt>Bank</dt><dd>{{ $service->bank_name ?: '—' }}</dd></div>
                <div><dt>Account</dt><dd>{{ $service->bank_account_number ?: '—' }}</dd></div>
                <div><dt>IFSC</dt><dd>{{ $service->bank_ifsc ?: '—' }}</dd></div>
                <div><dt>UPI</dt><dd>{{ $service->upi_id ?: '—' }}</dd></div>
            </dl>
        </section>
    </div>

    @if ($service->notes)
        <section class="master-card">
            <h3 class="master-section-title">Notes</h3>
            <p>{{ $service->notes }}</p>
        </section>
    @endif

    <section class="master-card">
        <h3 class="master-section-title">Recent cashflow</h3>
        <p class="master-sub">Pay them from Cashflow as Related to → Office service. Purchase orders stay for vendors.</p>
        @if ($payments->isEmpty())
            <p class="master-list-empty-text">No cashflow entries linked yet.</p>
        @else
            <table class="master-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Particular</th>
                        <th>Debit</th>
                        <th>Credit</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payments as $entry)
                        <tr>
                            <td><a href="{{ route('cashflows.show', $entry) }}">{{ optional($entry->entry_date)->format('d M Y') }}</a></td>
                            <td>{{ $entry->particular }}</td>
                            <td>{{ $entry->debit_amount ? \App\Helpers\CommonHelper::indianCurrency($entry->debit_amount) : '—' }}</td>
                            <td>{{ $entry->credit_amount ? \App\Helpers\CommonHelper::indianCurrency($entry->credit_amount) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
</div>
@endsection
