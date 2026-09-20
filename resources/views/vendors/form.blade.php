@extends('layouts.app')

@section('page-title', $vendor->exists ? 'Edit Vendor' : 'Add Vendor')

@section('content')

@php($isEdit = $vendor->exists)

<div class="master-form">
    <div class="master-card master-header">
        <h1>{{ $isEdit ? 'Edit Vendor' : 'Add Vendor' }}</h1>
        <div class="master-breadcrumb"><a href="{{ url('/') }}">Home</a><span>•</span><a
                href="{{ route('vendors.index') }}">Vendors</a><span>•</span><span
                class="active">{{ $isEdit ? 'Edit' : 'Add' }}</span></div>
    </div>

    
    <form method="POST" action="{{ $isEdit ? route('vendors.update', $vendor) : route('vendors.store') }}"
        enctype="multipart/form-data" class="master-card master-form-card">
        @csrf
        @if($isEdit) @method('PUT') @endif

        <div class="master-section">
            <h3 class="master-section-title">Vendor Image</h3>
            <div class="master-image-row">
                @if($vendor->image_path)
                    <img class="master-preview" src="{{ asset('storage/' . $vendor->image_path) }}"
                        alt="{{ $vendor->vendor_name }}">
                @else
                    <span class="master-preview">🏭</span>
                @endif
                <div style="flex:1;min-width:240px;">
                    <label class="master-label">Upload Vendor Image</label>
                    <input class="master-input" type="file" name="vendor_image" accept="image/*">
                    <div class="master-help">Allowed JPG, PNG, WEBP or GIF. Max 2MB.</div>
                    @error('vendor_image')<div class="master-error">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="master-section">
            <h3 class="master-section-title">Basic Vendor Information</h3>
            <div class="master-detail-grid">
                <div class="master-field"><label class="master-label">Vendor Number</label><input class="master-input"
                        name="vendor_number" value="{{ old('vendor_number', $vendor->vendor_number) }}"
                        placeholder="Auto generated if blank">@error('vendor_number')<div class="master-error">
                        {{ $message }}</div>@enderror</div>
                <div class="master-field"><label class="master-label">Vendor Name <span
                            class="master-required">*</span></label><input class="master-input" name="vendor_name"
                        value="{{ old('vendor_name', $vendor->vendor_name) }}" required>@error('vendor_name')<div
                        class="master-error">{{ $message }}</div>@enderror</div>
                <div class="master-field"><label class="master-label">Brand Name</label><input class="master-input"
                        name="brand_name" value="{{ old('brand_name', $vendor->brand_name) }}"></div>
                <div class="master-field"><label class="master-label">Vendor Type <span
                            class="master-required">*</span></label><select class="master-select" name="vendor_type"
                        required>@foreach($typeOptions as $key => $label)<option value="{{ $key }}"
                            @selected(old('vendor_type', $vendor->vendor_type) === $key)>{{ $label }}</option>
                        @endforeach</select></div>
                <div class="master-field"><label class="master-label">Category</label><input class="master-input"
                        name="category" value="{{ old('category', $vendor->category) }}"
                        placeholder="Raw Material / Packaging / Service"></div>
                <div class="master-field"><label class="master-label">Status <span
                            class="master-required">*</span></label><select class="master-select" name="status"
                        required>@foreach($statusOptions as $key => $label)<option value="{{ $key }}"
                        @selected(old('status', $vendor->status) === $key)>{{ $label }}</option>@endforeach</select>
                </div>
            </div>
        </div>

        <div class="master-section">
            <h3 class="master-section-title">Contact & Online Links</h3>
            <div class="master-detail-grid">
                <div class="master-field"><label class="master-label">Contact Person Name</label><input
                        class="master-input" name="contact_person_name"
                        value="{{ old('contact_person_name', $vendor->contact_person_name) }}"></div>
                <div class="master-field"><label class="master-label">Contact Email</label><input class="master-input"
                        type="email" name="contact_person_email"
                        value="{{ old('contact_person_email', $vendor->contact_person_email) }}"></div>
                <div class="master-field"><label class="master-label">Contact Mobile</label><input class="master-input"
                        name="contact_person_mobile"
                        value="{{ old('contact_person_mobile', $vendor->contact_person_mobile) }}"></div>
                <div class="master-field"><label class="master-label">WhatsApp Number</label><input class="master-input"
                        name="whatsapp_number" value="{{ old('whatsapp_number', $vendor->whatsapp_number) }}"></div>
                <div class="master-field"><label class="master-label">Alternate Contact</label><input
                        class="master-input" name="alternate_contact"
                        value="{{ old('alternate_contact', $vendor->alternate_contact) }}"></div>
                <div class="master-field"><label class="master-label">Website</label><input class="master-input"
                        name="website" value="{{ old('website', $vendor->website) }}" placeholder="https://vendor.com">
                </div>
                <div class="master-field"><label class="master-label">Alibaba Link</label><input
                        class="master-input" name="alibaba_link"
                        value="{{ old('alibaba_link', $vendor->alibaba_link) }}" placeholder="https://..."></div>
            </div>
        </div>

        <div class="master-section">
            <h3 class="master-section-title">Address Details</h3>
            <div class="master-detail-grid">
                <div class="master-field full"><label class="master-label">Address</label><input
                        class="master-input" name="address" value="{{ old('address', $vendor->address) }}"></div>
                <div class="master-field"><label class="master-label">City</label><input class="master-input"
                        name="city" value="{{ old('city', $vendor->city) }}"></div>
                <div class="master-field"><label class="master-label">State</label><input class="master-input"
                        name="state" value="{{ old('state', $vendor->state) }}"></div>
                <div class="master-field"><label class="master-label">Country</label><input class="master-input"
                        name="country" value="{{ old('country', $vendor->country) }}"></div>
                <div class="master-field"><label class="master-label">Pincode</label><input class="master-input"
                        name="pincode" value="{{ old('pincode', $vendor->pincode) }}"></div>
            </div>
        </div>

        <div class="master-section">
            <h3 class="master-section-title">Tax & Bank Details</h3>
            <div class="master-detail-grid">
                <div class="master-field"><label class="master-label">GSTIN</label><input class="master-input"
                        name="gstin" value="{{ old('gstin', $vendor->gstin) }}"></div>
                <div class="master-field"><label class="master-label">PAN</label><input class="master-input" name="pan"
                        value="{{ old('pan', $vendor->pan) }}"></div>
                <div class="master-field"><label class="master-label">Tax ID</label><input class="master-input"
                        name="tax_id" value="{{ old('tax_id', $vendor->tax_id) }}"></div>
                <div class="master-field"><label class="master-label">Import Export Code</label><input
                        class="master-input" name="import_export_code"
                        value="{{ old('import_export_code', $vendor->import_export_code) }}"></div>
                <div class="master-field"><label class="master-label">Bank Name</label><input class="master-input"
                        name="bank_name" value="{{ old('bank_name', $vendor->bank_name) }}"></div>
                <div class="master-field"><label class="master-label">Account Holder Name</label><input
                        class="master-input" name="account_holder_name"
                        value="{{ old('account_holder_name', $vendor->account_holder_name) }}"></div>
                <div class="master-field"><label class="master-label">Account Number</label><input class="master-input"
                        name="account_number" value="{{ old('account_number', $vendor->account_number) }}"></div>
                <div class="master-field"><label class="master-label">IFSC Code</label><input class="master-input"
                        name="ifsc_code" value="{{ old('ifsc_code', $vendor->ifsc_code) }}"></div>
                <div class="master-field"><label class="master-label">SWIFT Code</label><input class="master-input"
                        name="swift_code" value="{{ old('swift_code', $vendor->swift_code) }}"></div>
                <div class="master-field"><label class="master-label">Bank Branch</label><input class="master-input"
                        name="bank_branch" value="{{ old('bank_branch', $vendor->bank_branch) }}"></div>
            </div>
        </div>

        <div class="master-section">
            <h3 class="master-section-title">Commercial Details</h3>
            <div class="master-detail-grid">
                <div class="master-field"><label class="master-label">Preferred Currency</label><select
                        class="master-select" name="preferred_currency">@foreach($currencyOptions as $key => $label)
                        <option value="{{ $key }}" @selected(old('preferred_currency', $vendor->preferred_currency) === $key)>{{ $label }}</option>@endforeach</select></div>
                <div class="master-field"><label class="master-label">Lead Time Days</label><input class="master-input"
                        type="number" min="0" name="lead_time_days"
                        value="{{ old('lead_time_days', $vendor->lead_time_days) }}"></div>
                <div class="master-field"><label class="master-label">Minimum Order Value</label><input
                        class="master-input" type="number" min="0" step="0.01" name="minimum_order_value"
                        value="{{ old('minimum_order_value', $vendor->minimum_order_value) }}"></div>
                <div class="master-field"><label class="master-label">Rating</label><select class="master-select"
                        name="rating">
                        <option value="">No Rating</option>@for($i = 1; $i <= 5; $i++)
                            <option value="{{ $i }}" @selected((string) old('rating', $vendor->rating) === (string) $i)>
                        {{ $i }} Star{{ $i > 1 ? 's' : '' }}</option>@endfor
                    </select></div>
                <div class="master-field two"><label class="master-label">Payment Terms</label><input
                        class="master-input" name="payment_terms"
                        value="{{ old('payment_terms', $vendor->payment_terms) }}"
                        placeholder="Advance / LC / 30 days credit"></div>
                <div class="master-field two"><label class="master-label">Notes</label><input
                        class="master-input" name="notes" value="{{ old('notes', $vendor->notes) }}"></div>
            </div>
        </div>

        <div class="master-actions">
            <a href="{{ $isEdit ? route('vendors.show', $vendor) : route('vendors.index') }}"
                class="master-btn master-btn-light">Cancel</a>
            <button type="submit"
                class="master-btn master-btn-primary">{{ $isEdit ? 'Update Vendor' : 'Create Vendor' }}</button>
        </div>
    </form>
</div>
@endsection