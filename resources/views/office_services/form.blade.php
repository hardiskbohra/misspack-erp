@extends('layouts.app')

@php($isEdit = $service->exists)

@section('title', $isEdit ? 'Edit office service' : 'Add office service')
@section('page-title', $isEdit ? 'Edit office service' : 'Add office service')

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/office-services.css') }}">
@endpush

<div class="office-service-form">
    <header class="master-card master-header">
        <div>
            <h1>{{ $isEdit ? 'Edit office service' : 'Add an office service' }}</h1>
            <p>Facility retainers for the office. They do not log in and they are not purchase vendors.</p>
            <nav class="master-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('office-services.index') }}">Office services</a><span aria-hidden="true">/</span>
                <span class="active">{{ $isEdit ? 'Edit' : 'New' }}</span>
            </nav>
        </div>
        <a href="{{ $isEdit ? route('office-services.show', $service) : route('office-services.index') }}" class="master-btn master-btn-light">Back</a>
    </header>

    @if ($errors->any())
        <div class="master-error-summary" role="alert">
            <strong>Please review the highlighted fields.</strong>
        </div>
    @endif

    <form method="POST" action="{{ $isEdit ? route('office-services.update', $service) : route('office-services.store') }}" class="master-card master-form-card">
        @csrf
        @if ($isEdit) @method('PUT') @endif

        <h3 class="master-section-title">Who they are</h3>
        <div class="master-detail-grid">
            <div class="master-field">
                <label class="master-label" for="ofs_name">Name <span class="master-required">*</span></label>
                <input class="master-input" id="ofs_name" name="name" required value="{{ old('name', $service->name) }}" placeholder="e.g. Meena — housekeeping">
                @error('name')<p class="master-error">{{ $message }}</p>@enderror
            </div>
            <div class="master-field">
                <label class="master-label" for="ofs_class">Class <span class="master-required">*</span></label>
                <select class="master-select" id="ofs_class" name="service_class" required>
                    @foreach (\App\Models\OfficeService::classOptions() as $key => $label)
                        <option value="{{ $key }}" @selected(old('service_class', $service->service_class) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="master-field">
                <label class="master-label" for="ofs_contact">Person</label>
                <input class="master-input" id="ofs_contact" name="contact_name" value="{{ old('contact_name', $service->contact_name) }}">
            </div>
            <div class="master-field">
                <label class="master-label" for="ofs_phone">Phone</label>
                <input class="master-input" id="ofs_phone" name="phone" value="{{ old('phone', $service->phone) }}">
            </div>
            <div class="master-field">
                <label class="master-label" for="ofs_email">Email</label>
                <input class="master-input" id="ofs_email" name="email" type="email" value="{{ old('email', $service->email) }}">
            </div>
            <div class="master-field">
                <label class="master-label" for="ofs_status">Status</label>
                <select class="master-select" id="ofs_status" name="status">
                    @foreach (\App\Models\OfficeService::statusOptions() as $key => $label)
                        <option value="{{ $key }}" @selected(old('status', $service->status) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="master-field full">
                <label class="master-label" for="ofs_address">Address</label>
                <textarea class="master-input" id="ofs_address" name="address" rows="2">{{ old('address', $service->address) }}</textarea>
            </div>
        </div>

        <h3 class="master-section-title">Retainer</h3>
        <div class="master-detail-grid">
            <div class="master-field">
                <label class="master-label" for="ofs_retainer">Amount (₹)</label>
                <input class="master-input" id="ofs_retainer" name="retainer_amount" type="number" step="0.01" min="0" value="{{ old('retainer_amount', $service->retainer_amount) }}">
            </div>
            <div class="master-field">
                <label class="master-label" for="ofs_cycle">Cycle</label>
                <select class="master-select" id="ofs_cycle" name="retainer_cycle">
                    @foreach (\App\Models\OfficeService::cycleOptions() as $key => $label)
                        <option value="{{ $key }}" @selected(old('retainer_cycle', $service->retainer_cycle) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <input type="hidden" name="currency" value="INR">
        </div>

        <h3 class="master-section-title">Pay them</h3>
        <p class="master-sub">Bank or UPI used when you record a cashflow debit against this service.</p>
        <div class="master-detail-grid">
            <div class="master-field">
                <label class="master-label" for="ofs_bank">Bank</label>
                <input class="master-input" id="ofs_bank" name="bank_name" value="{{ old('bank_name', $service->bank_name) }}">
            </div>
            <div class="master-field">
                <label class="master-label" for="ofs_acc_name">Account name</label>
                <input class="master-input" id="ofs_acc_name" name="bank_account_name" value="{{ old('bank_account_name', $service->bank_account_name) }}">
            </div>
            <div class="master-field">
                <label class="master-label" for="ofs_acc">Account number</label>
                <input class="master-input" id="ofs_acc" name="bank_account_number" value="{{ old('bank_account_number', $service->bank_account_number) }}">
            </div>
            <div class="master-field">
                <label class="master-label" for="ofs_ifsc">IFSC</label>
                <input class="master-input" id="ofs_ifsc" name="bank_ifsc" value="{{ old('bank_ifsc', $service->bank_ifsc) }}">
            </div>
            <div class="master-field">
                <label class="master-label" for="ofs_upi">UPI</label>
                <input class="master-input" id="ofs_upi" name="upi_id" value="{{ old('upi_id', $service->upi_id) }}">
            </div>
            <div class="master-field full">
                <label class="master-label" for="ofs_notes">Notes</label>
                <textarea class="master-input" id="ofs_notes" name="notes" rows="3">{{ old('notes', $service->notes) }}</textarea>
            </div>
        </div>

        <div class="master-form-actions">
            <button type="submit" class="master-btn master-btn-primary">{{ $isEdit ? 'Save' : 'Create' }}</button>
            <a href="{{ route('office-services.index') }}" class="master-btn master-btn-light">Cancel</a>
        </div>
    </form>
</div>
@endsection
