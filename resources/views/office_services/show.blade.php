@extends('layouts.app')

@section('title', $service->name)
@section('page-title', $service->name)

@section('page-actions')
    <a class="master-btn master-btn-ghost" href="{{ route('office-services.index') }}">All services</a>
    <span class="emp-pill is-off">Facility retainer — no login</span>
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/employees.css') }}">
    <link rel="stylesheet" href="{{ $assetVer('assets/css/users.css') }}">
    <link rel="stylesheet" href="{{ $assetVer('assets/css/office-services.css') }}">
@endpush

@php
    $tabUrl = fn (string $key) => route('office-services.show', ['office_service' => $service, 'tab' => $key]);
@endphp

<div class="emp employee-record master-list office-service-record">
    <div class="master-card master-card--flat emp-record-head">
        <div class="emp-record-who">
            <span class="emp-avatar" aria-hidden="true">{{ $service->initials() }}</span>
            <div>
                <h1>{{ $service->name }}</h1>
                <p class="master-sub emp-record-line">
                    {{ $service->service_number }}
                    @if ($service->contact_name) · {{ $service->contact_name }}@endif
                    @if ($service->phone) · {{ $service->phone }}@endif
                </p>
                <p class="emp-record-tags">
                    <span class="emp-pill is-info">{{ $service->classLabel() }}</span>
                    <span class="emp-pill {{ $service->status === 'active' ? 'is-ok' : 'is-off' }}">{{ $service->statusLabel() }}</span>
                    @if ($service->retainer_amount)
                        <span class="emp-pill is-off">{{ \App\Helpers\CommonHelper::indianCurrency($service->retainer_amount) }} · {{ $service->cycleLabel() }}</span>
                    @endif
                    @if ($service->email)<span class="emp-pill is-off">{{ $service->email }}</span>@endif
                </p>
            </div>
        </div>
    </div>

    <div class="master-stats desktop-only">
        <div class="master-stat master-stat--flat green">
            <span class="icon" aria-hidden="true">₹</span>
            <div>
                <p class="master-stat-title">Paid in {{ now()->year }}</p>
                <p class="master-stat-value">{{ \App\Helpers\CommonHelper::indianCurrency($paidYear) }}</p>
                <p class="master-sub">cashflow linked to this service</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat blue">
            <span class="icon" aria-hidden="true">🧾</span>
            <div>
                <p class="master-stat-title">Payments</p>
                <p class="master-stat-value">{{ $payments->count() }}</p>
                <p class="master-sub">recent cashflow rows</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat {{ $missing ? 'orange' : 'green' }}">
            <span class="icon" aria-hidden="true">{{ $missing ? '!' : '✓' }}</span>
            <div>
                <p class="master-stat-title">Profile</p>
                <p class="master-stat-value">{{ $missing ? count($missing).' to add' : 'Complete' }}</p>
                <p class="master-sub">{{ $missing ? implode(', ', $missing) : 'nothing outstanding' }}</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat teal">
            <span class="icon" aria-hidden="true">🏦</span>
            <div>
                <p class="master-stat-title">Pay to</p>
                <p class="master-stat-value">{{ $service->upi_id ? 'UPI' : ($service->bank_account_number ? 'Bank' : '—') }}</p>
                <p class="master-sub">{{ $service->upi_id ?: ($service->bank_ifsc ?: 'no account on file') }}</p>
            </div>
        </div>
    </div>

    <div class="master-tabs-card">
        <div class="master-tabs" role="tablist" aria-label="Record sections">
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
                    <div class="master-grid">
                        <div class="master-card master-card--flat master-section">
                            <h3 class="master-section-title">What is missing</h3>
                            <ul class="emp-todo">
                                @forelse ($missing as $item)
                                    <li>
                                        <span class="emp-todo-dot" aria-hidden="true"></span>
                                        <span><strong>{{ $item }}</strong> is missing from the record.</span>
                                        <a class="master-btn master-btn-ghost master-btn-sm" href="{{ $tabUrl('details') }}">Open details</a>
                                    </li>
                                @empty
                                    <li>
                                        <span class="emp-todo-dot" aria-hidden="true"></span>
                                        <span>The record has phone, retainer and a way to pay.</span>
                                    </li>
                                @endforelse
                            </ul>
                        </div>
                        <div class="master-card master-card--flat master-section">
                            <h3 class="master-section-title">How to pay</h3>
                            <p class="master-sub">Record a cashflow debit with Related to → Office service. Purchase orders stay for vendors.</p>
                            <div class="emp-card-foot">
                                <a class="master-btn master-btn-soft master-btn-sm" href="{{ route('cashflows.index') }}">Open cashflow</a>
                                <a class="master-btn master-btn-ghost master-btn-sm" href="{{ $tabUrl('money') }}">Payment history</a>
                            </div>
                        </div>
                    </div>
                </section>
            @elseif ($tab === 'details')
                <section class="master-tab-panel" aria-label="Details">
                    <div class="master-card master-card--flat master-section">
                        <h3 class="master-section-title">Edit the record</h3>
                        <form method="POST" action="{{ route('office-services.update', $service) }}">
                            @csrf
                            @method('PUT')
                            <p class="master-section-label">Identity</p>
                            <div class="master-form-grid is-three">
                                <div class="master-field">
                                    <label class="master-label" for="recordName">Name</label>
                                    <input class="master-input" id="recordName" name="name" value="{{ old('name', $service->name) }}" required>
                                </div>
                                <div class="master-field">
                                    <label class="master-label" for="recordClass">Class</label>
                                    <select class="master-select" id="recordClass" name="service_class" required>
                                        @foreach ($classOptions as $key => $label)
                                            <option value="{{ $key }}" @selected(old('service_class', $service->service_class) === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="master-field">
                                    <label class="master-label" for="recordStatus">Status</label>
                                    <select class="master-select" id="recordStatus" name="status">
                                        @foreach ($statusOptions as $key => $label)
                                            <option value="{{ $key }}" @selected(old('status', $service->status) === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="master-field">
                                    <label class="master-label" for="recordContact">Person</label>
                                    <input class="master-input" id="recordContact" name="contact_name" value="{{ old('contact_name', $service->contact_name) }}">
                                </div>
                                <div class="master-field">
                                    <label class="master-label" for="recordPhone">Phone</label>
                                    <input class="master-input" id="recordPhone" name="phone" value="{{ old('phone', $service->phone) }}">
                                </div>
                                <div class="master-field">
                                    <label class="master-label" for="recordEmail">Email</label>
                                    <input class="master-input" id="recordEmail" type="email" name="email" value="{{ old('email', $service->email) }}">
                                </div>
                            </div>
                            <p class="master-section-label">Retainer</p>
                            <div class="master-form-grid is-three">
                                <div class="master-field">
                                    <label class="master-label" for="recordRetainer">Amount (₹)</label>
                                    <input class="master-input" id="recordRetainer" name="retainer_amount" type="number" step="0.01" min="0" value="{{ old('retainer_amount', $service->retainer_amount) }}">
                                </div>
                                <div class="master-field">
                                    <label class="master-label" for="recordCycle">Cycle</label>
                                    <select class="master-select" id="recordCycle" name="retainer_cycle">
                                        @foreach ($cycleOptions as $key => $label)
                                            <option value="{{ $key }}" @selected(old('retainer_cycle', $service->retainer_cycle) === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <input type="hidden" name="currency" value="INR">
                            </div>
                            <p class="master-section-label">Pay them</p>
                            <div class="master-form-grid is-three">
                                <div class="master-field">
                                    <label class="master-label" for="recordBank">Bank</label>
                                    <input class="master-input" id="recordBank" name="bank_name" value="{{ old('bank_name', $service->bank_name) }}">
                                </div>
                                <div class="master-field">
                                    <label class="master-label" for="recordAccName">Account name</label>
                                    <input class="master-input" id="recordAccName" name="bank_account_name" value="{{ old('bank_account_name', $service->bank_account_name) }}">
                                </div>
                                <div class="master-field">
                                    <label class="master-label" for="recordAcc">Account number</label>
                                    <input class="master-input" id="recordAcc" name="bank_account_number" value="{{ old('bank_account_number', $service->bank_account_number) }}">
                                </div>
                                <div class="master-field">
                                    <label class="master-label" for="recordIfsc">IFSC</label>
                                    <input class="master-input" id="recordIfsc" name="bank_ifsc" value="{{ old('bank_ifsc', $service->bank_ifsc) }}">
                                </div>
                                <div class="master-field">
                                    <label class="master-label" for="recordUpi">UPI</label>
                                    <input class="master-input" id="recordUpi" name="upi_id" value="{{ old('upi_id', $service->upi_id) }}">
                                </div>
                                <div class="master-field full">
                                    <label class="master-label" for="recordAddress">Address</label>
                                    <textarea class="master-input" id="recordAddress" name="address" rows="2">{{ old('address', $service->address) }}</textarea>
                                </div>
                                <div class="master-field full">
                                    <label class="master-label" for="recordNotes">Notes</label>
                                    <textarea class="master-input" id="recordNotes" name="notes" rows="3">{{ old('notes', $service->notes) }}</textarea>
                                </div>
                            </div>
                            <div class="emp-card-foot">
                                <button type="submit" class="master-btn master-btn-primary">Save</button>
                            </div>
                        </form>
                    </div>
                </section>
            @else
                <section class="master-tab-panel" aria-label="Payments">
                    <div class="master-card master-card--flat master-section">
                        <h3 class="master-section-title">Cashflow</h3>
                        <p class="master-sub">Debits filed against this office service.</p>
                        @if ($payments->isEmpty())
                            <div class="master-list-empty">
                                <p class="master-list-empty-title">No payments yet</p>
                                <p class="master-list-empty-text">Record one from Cashflow, Related to → Office service.</p>
                                <div class="master-list-empty-actions">
                                    <a class="master-btn master-btn-primary" href="{{ route('cashflows.index') }}">Open cashflow</a>
                                </div>
                            </div>
                        @else
                            <div class="master-table-wrap">
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
                                            <tr class="is-clickable" data-href="{{ route('cashflows.show', $entry) }}">
                                                <td>{{ optional($entry->entry_date)->format('d M Y') }}</td>
                                                <td>{{ $entry->particular }}</td>
                                                <td>{{ $entry->debit_amount ? \App\Helpers\CommonHelper::indianCurrency($entry->debit_amount) : '—' }}</td>
                                                <td>{{ $entry->credit_amount ? \App\Helpers\CommonHelper::indianCurrency($entry->credit_amount) : '—' }}</td>
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
