@extends('layouts.app')

@section('title', $vendor->exists ? 'Edit vendor' : 'Add vendor')
@section('page-title', $vendor->exists ? 'Edit vendor' : 'Add vendor')

@section('content')

@php($isEdit = $vendor->exists)

<div class="vendor-form">
    <header class="master-card master-header">
        <div class="vendor-form-heading">
            <h1>{{ $isEdit ? 'Edit vendor' : 'Add a vendor' }}</h1>
            <p>{{ $isEdit ? 'Keep this supplier’s contacts, tax and bank details, and terms up to date.' : 'Create a supplier record. You can add more details now or complete them later.' }}</p>
            <nav class="master-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('dashboard') }}">Home</a><span aria-hidden="true">/</span>
                <a href="{{ route('vendors.index') }}">Vendors</a><span aria-hidden="true">/</span>
                <span class="active" aria-current="page">{{ $isEdit ? 'Edit' : 'New' }}</span>
            </nav>
        </div>
        <a href="{{ $isEdit ? route('vendors.show', $vendor) : route('vendors.index') }}" class="master-btn master-btn-light">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> {{ $isEdit ? 'Back to vendor' : 'Back to vendors' }}
        </a>
    </header>

    @if($errors->any())
        <div class="master-error-summary" role="alert" aria-labelledby="vendorErrorTitle">
            <strong id="vendorErrorTitle">Please review {{ $errors->count() }} {{ \Illuminate\Support\Str::plural('field', $errors->count()) }}:</strong>
            <ul>
                @foreach($errors->messages() as $field => $messages)
                    <li><a href="#vendor_{{ $field }}">{{ $messages[0] }}</a></li>
                @endforeach
            </ul>
        </div>
    @endif

    <nav class="vendor-form-nav" aria-label="Jump to vendor details">
        <a href="#vendor-basic"><i class="fa-regular fa-building" aria-hidden="true"></i> Basic</a>
        <a href="#vendor-contacts"><i class="fa-regular fa-address-card" aria-hidden="true"></i> Contacts</a>
        <a href="#vendor-addresses"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> Address</a>
        <a href="#vendor-tax-bank"><i class="fa-solid fa-file-invoice" aria-hidden="true"></i> Tax & bank</a>
        <a href="#vendor-commercial"><i class="fa-solid fa-coins" aria-hidden="true"></i> Commercial</a>
    </nav>

    <form method="POST" action="{{ $isEdit ? route('vendors.update', $vendor) : route('vendors.store') }}"
        enctype="multipart/form-data" class="master-card master-form-card" id="vendorForm">

        @csrf
        @if($isEdit) @method('PUT') @endif

        <section class="vendor-form-section" id="vendor-basic" aria-labelledby="vendor-basic-title">
            <h2 class="master-section-title" id="vendor-basic-title">Basic information</h2>
            <p class="vendor-form-section-note">Identify the supplier and set how the office should treat them.</p>
            <div class="master-detail-grid">
                <div class="master-field">
                    <label class="master-label" for="vendor_vendor_number">Vendor number</label>
                    <input class="master-input @error('vendor_number') is-invalid @enderror" id="vendor_vendor_number" name="vendor_number"
                        value="{{ old('vendor_number', $vendor->vendor_number) }}" maxlength="255"
                        placeholder="Auto-generated if left blank" autocomplete="off">
                    <div class="master-help">New vendor numbers are assigned automatically. Change only if you use an external reference.</div>
                </div>
                <div class="master-field">
                    <label class="master-label" for="vendor_vendor_name">Vendor name <span class="master-required" aria-hidden="true">*</span></label>
                    <input class="master-input @error('vendor_name') is-invalid @enderror" id="vendor_vendor_name" name="vendor_name"
                        value="{{ old('vendor_name', $vendor->vendor_name) }}" maxlength="255" required autocomplete="organization">
                </div>
                <div class="master-field">
                    <label class="master-label" for="vendor_brand_name">Brand name</label>
                    <input class="master-input @error('brand_name') is-invalid @enderror" id="vendor_brand_name" name="brand_name"
                        value="{{ old('brand_name', $vendor->brand_name) }}" maxlength="255">
                </div>
                <div class="master-field">
                    <label class="master-label" for="vendor_vendor_type">Vendor type <span class="master-required" aria-hidden="true">*</span></label>
                    <select class="master-select @error('vendor_type') is-invalid @enderror" id="vendor_vendor_type" name="vendor_type" required>
                        @foreach($typeOptions as $key => $label)
                            <option value="{{ $key }}" @selected(old('vendor_type', $vendor->vendor_type) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="master-field">
                    <label class="master-label" for="vendor_status">Status <span class="master-required" aria-hidden="true">*</span></label>
                    <select class="master-select @error('status') is-invalid @enderror" id="vendor_status" name="status" required>
                        @foreach($statusOptions as $key => $label)
                            <option value="{{ $key }}" @selected(old('status', $vendor->status) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="master-help">Blacklisted and on-hold vendors stay on file but should not be given new work.</div>
                </div>
                <div class="master-field">
                    <label class="master-label" for="vendor_category">Category</label>
                    <input class="master-input @error('category') is-invalid @enderror" id="vendor_category" name="category"
                        value="{{ old('category', $vendor->category) }}" maxlength="255"
                        placeholder="Raw material / Packaging / Service">
                </div>
                <div class="master-field">
                    <label class="master-label" for="vendor_rating">Rating</label>
                    <select class="master-select @error('rating') is-invalid @enderror" id="vendor_rating" name="rating">
                        <option value="">No rating</option>
                        @for($i = 1; $i <= 5; $i++)
                            <option value="{{ $i }}" @selected((string) old('rating', $vendor->rating) === (string) $i)>
                                {{ str_repeat('★', $i) }} ({{ $i }} of 5)
                            </option>
                        @endfor
                    </select>
                </div>
                <div class="master-field full">
                    <label class="master-label" for="vendor_vendor_image">Vendor image</label>
                    <div class="vendor-image-row">
                        @if($vendor->image_path)
                            <img class="vendor-image-preview" src="{{ asset('storage/' . $vendor->image_path) }}"
                                alt="{{ $vendor->vendor_name }}" data-vendor-image-preview>
                        @else
                            <span class="vendor-image-preview" data-vendor-image-preview aria-hidden="true">
                                <i class="fa-solid fa-industry"></i>
                            </span>
                        @endif
                        <div class="vendor-image-field">
                            <input class="master-input @error('vendor_image') is-invalid @enderror" id="vendor_vendor_image"
                                type="file" name="vendor_image" accept="image/*" data-vendor-image-input>
                            <div class="master-help">JPG, PNG, WEBP or GIF, up to 2 MB. Shown on the vendor list and record.</div>
                            @error('vendor_image')<div class="master-error">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="vendor-form-section" id="vendor-contacts" aria-labelledby="vendor-contacts-title">
            <h2 class="master-section-title" id="vendor-contacts-title">Contact & online links</h2>
            <p class="vendor-form-section-note">The person your team should reach for orders, follow-ups and payments.</p>
            <div class="master-detail-grid">
                <div class="master-field">
                    <label class="master-label" for="vendor_contact_person_name">Contact person</label>
                    <input class="master-input @error('contact_person_name') is-invalid @enderror" id="vendor_contact_person_name"
                        name="contact_person_name" value="{{ old('contact_person_name', $vendor->contact_person_name) }}"
                        maxlength="255" autocomplete="name">
                </div>
                <div class="master-field">
                    <label class="master-label" for="vendor_contact_person_email">Contact email</label>
                    <input class="master-input @error('contact_person_email') is-invalid @enderror" id="vendor_contact_person_email"
                        type="email" name="contact_person_email" value="{{ old('contact_person_email', $vendor->contact_person_email) }}"
                        maxlength="255" autocomplete="email">
                </div>
                <div class="master-field">
                    <label class="master-label" for="vendor_contact_person_mobile">Contact mobile</label>
                    <input class="master-input @error('contact_person_mobile') is-invalid @enderror" id="vendor_contact_person_mobile"
                        type="tel" name="contact_person_mobile" value="{{ old('contact_person_mobile', $vendor->contact_person_mobile) }}"
                        maxlength="40" autocomplete="tel">
                </div>
                <div class="master-field">
                    <label class="master-label" for="vendor_whatsapp_number">WhatsApp number</label>
                    <input class="master-input @error('whatsapp_number') is-invalid @enderror" id="vendor_whatsapp_number"
                        type="tel" name="whatsapp_number" value="{{ old('whatsapp_number', $vendor->whatsapp_number) }}"
                        maxlength="40" placeholder="Same as mobile if left blank">
                </div>
                <div class="master-field">
                    <label class="master-label" for="vendor_alternate_contact">Alternate contact</label>
                    <input class="master-input @error('alternate_contact') is-invalid @enderror" id="vendor_alternate_contact"
                        type="tel" name="alternate_contact" value="{{ old('alternate_contact', $vendor->alternate_contact) }}" maxlength="40">
                </div>
                <div class="master-field full">
                    <label class="master-label" for="vendor_website">Website</label>
                    <input class="master-input @error('website') is-invalid @enderror" id="vendor_website" name="website"
                        type="text" inputmode="url" value="{{ old('website', $vendor->website) }}" maxlength="255"
                        placeholder="https://vendor.com" autocomplete="url">
                </div>
                <div class="master-field full">
                    <label class="master-label" for="vendor_alibaba_link">Alibaba link</label>
                    <input class="master-input @error('alibaba_link') is-invalid @enderror" id="vendor_alibaba_link" name="alibaba_link"
                        type="text" inputmode="url" value="{{ old('alibaba_link', $vendor->alibaba_link) }}" maxlength="255"
                        placeholder="https://www.alibaba.com/…">
                </div>
            </div>
        </section>

        <section class="vendor-form-section" id="vendor-addresses" aria-labelledby="vendor-addresses-title">
            <h2 class="master-section-title" id="vendor-addresses-title">Address</h2>
            <p class="vendor-form-section-note">Where the supplier ships from — used on purchase paperwork and shipping marks.</p>
            <div class="master-detail-grid">
                <div class="master-field full">
                    <label class="master-label" for="vendor_address">Address</label>
                    <input class="master-input @error('address') is-invalid @enderror" id="vendor_address" name="address"
                        value="{{ old('address', $vendor->address) }}" autocomplete="street-address">
                </div>
                <div class="master-field">
                    <label class="master-label" for="vendor_city">City</label>
                    <input class="master-input @error('city') is-invalid @enderror" id="vendor_city" name="city"
                        value="{{ old('city', $vendor->city) }}" maxlength="255" autocomplete="address-level2">
                </div>
                <div class="master-field">
                    <label class="master-label" for="vendor_state">State / Province</label>
                    <input class="master-input @error('state') is-invalid @enderror" id="vendor_state" name="state"
                        value="{{ old('state', $vendor->state) }}" maxlength="255" autocomplete="address-level1">
                </div>
                <div class="master-field">
                    <label class="master-label" for="vendor_country">Country</label>
                    <input class="master-input @error('country') is-invalid @enderror" id="vendor_country" name="country"
                        value="{{ old('country', $vendor->country) }}" maxlength="255" autocomplete="country-name">
                </div>
                <div class="master-field">
                    <label class="master-label" for="vendor_pincode">Postal code</label>
                    <input class="master-input @error('pincode') is-invalid @enderror" id="vendor_pincode" name="pincode"
                        value="{{ old('pincode', $vendor->pincode) }}" maxlength="30" autocomplete="postal-code">
                </div>
            </div>
        </section>

        <section class="vendor-form-section" id="vendor-tax-bank" aria-labelledby="vendor-tax-bank-title">
            <h2 class="master-section-title" id="vendor-tax-bank-title">Tax & bank details</h2>
            <p class="vendor-form-section-note">Keep statutory and remittance details on the record so a payment never waits on an email.</p>

            <div class="vendor-form-subsection">
                <h3 class="vendor-form-subsection-title">Tax registrations</h3>
                <div class="master-detail-grid">
                    <div class="master-field">
                        <label class="master-label" for="vendor_gstin">GSTIN</label>
                        <input class="master-input @error('gstin') is-invalid @enderror" id="vendor_gstin" name="gstin"
                            value="{{ old('gstin', $vendor->gstin) }}" maxlength="30" autocomplete="off">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="vendor_pan">PAN</label>
                        <input class="master-input @error('pan') is-invalid @enderror" id="vendor_pan" name="pan"
                            value="{{ old('pan', $vendor->pan) }}" maxlength="20" autocomplete="off">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="vendor_tax_id">Tax ID</label>
                        <input class="master-input @error('tax_id') is-invalid @enderror" id="vendor_tax_id" name="tax_id"
                            value="{{ old('tax_id', $vendor->tax_id) }}" maxlength="255" autocomplete="off">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="vendor_import_export_code">Import / export code</label>
                        <input class="master-input @error('import_export_code') is-invalid @enderror" id="vendor_import_export_code"
                            name="import_export_code" value="{{ old('import_export_code', $vendor->import_export_code) }}"
                            maxlength="255" autocomplete="off">
                    </div>
                </div>
            </div>

            <div class="vendor-form-subsection">
                <h3 class="vendor-form-subsection-title">Bank details</h3>
                <div class="master-detail-grid">
                    <div class="master-field">
                        <label class="master-label" for="vendor_bank_name">Bank name</label>
                        <input class="master-input @error('bank_name') is-invalid @enderror" id="vendor_bank_name" name="bank_name"
                            value="{{ old('bank_name', $vendor->bank_name) }}" maxlength="255" autocomplete="off">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="vendor_account_holder_name">Account holder</label>
                        <input class="master-input @error('account_holder_name') is-invalid @enderror" id="vendor_account_holder_name"
                            name="account_holder_name" value="{{ old('account_holder_name', $vendor->account_holder_name) }}"
                            maxlength="255" autocomplete="off">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="vendor_account_number">Account number</label>
                        <input class="master-input @error('account_number') is-invalid @enderror" id="vendor_account_number"
                            name="account_number" value="{{ old('account_number', $vendor->account_number) }}"
                            maxlength="255" autocomplete="off">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="vendor_ifsc_code">IFSC code</label>
                        <input class="master-input @error('ifsc_code') is-invalid @enderror" id="vendor_ifsc_code" name="ifsc_code"
                            value="{{ old('ifsc_code', $vendor->ifsc_code) }}" maxlength="30" autocomplete="off">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="vendor_swift_code">SWIFT code</label>
                        <input class="master-input @error('swift_code') is-invalid @enderror" id="vendor_swift_code" name="swift_code"
                            value="{{ old('swift_code', $vendor->swift_code) }}" maxlength="255" autocomplete="off">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="vendor_bank_branch">Bank branch</label>
                        <input class="master-input @error('bank_branch') is-invalid @enderror" id="vendor_bank_branch" name="bank_branch"
                            value="{{ old('bank_branch', $vendor->bank_branch) }}" maxlength="255" autocomplete="off">
                    </div>
                </div>
            </div>
        </section>

        <section class="vendor-form-section" id="vendor-commercial" aria-labelledby="vendor-commercial-title">
            <h2 class="master-section-title" id="vendor-commercial-title">Commercial terms</h2>
            <p class="vendor-form-section-note">The agreed terms for this supplier. These are reference details and do not replace order-level terms.</p>
            <div class="master-detail-grid">
                <div class="master-field">
                    <label class="master-label" for="vendor_preferred_currency">Preferred currency</label>
                    <select class="master-select @error('preferred_currency') is-invalid @enderror" id="vendor_preferred_currency" name="preferred_currency">
                        @foreach($currencyOptions as $key => $label)
                            <option value="{{ $key }}" @selected(old('preferred_currency', $vendor->preferred_currency) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="master-help">The vendor-currency ledger on the record is kept in this currency.</div>
                </div>
                <div class="master-field">
                    <label class="master-label" for="vendor_lead_time_days">Lead time (days)</label>
                    <input class="master-input @error('lead_time_days') is-invalid @enderror" id="vendor_lead_time_days" type="number"
                        min="0" step="1" name="lead_time_days" value="{{ old('lead_time_days', $vendor->lead_time_days) }}" inputmode="numeric">
                </div>
                <div class="master-field">
                    <label class="master-label" for="vendor_minimum_order_value">Minimum order value</label>
                    <input class="master-input @error('minimum_order_value') is-invalid @enderror" id="vendor_minimum_order_value"
                        type="number" min="0" step="0.01" name="minimum_order_value"
                        value="{{ old('minimum_order_value', $vendor->minimum_order_value) }}" inputmode="decimal">
                </div>
                <div class="master-field">
                    <label class="master-label" for="vendor_payment_terms">Payment terms</label>
                    <input class="master-input @error('payment_terms') is-invalid @enderror" id="vendor_payment_terms" name="payment_terms"
                        value="{{ old('payment_terms', $vendor->payment_terms) }}" maxlength="255"
                        placeholder="e.g. 30 days, 30% advance, balance against BL copy">
                    <div class="master-help">A number of days here sets a bill's due date automatically when the ledger form leaves it blank.</div>
                </div>
                <div class="master-field full">
                    <label class="master-label" for="vendor_notes">Internal notes</label>
                    <textarea class="master-textarea @error('notes') is-invalid @enderror" id="vendor_notes" name="notes" rows="4"
                        placeholder="Add context for your team">{{ old('notes', $vendor->notes) }}</textarea>
                </div>
            </div>
        </section>

        <div class="master-actions is-sticky">
            <a href="{{ $isEdit ? route('vendors.show', $vendor) : route('vendors.index') }}" class="master-btn master-btn-light">Cancel</a>
            <button type="submit" class="master-btn master-btn-primary">
                <i class="fa-solid fa-check" aria-hidden="true"></i> {{ $isEdit ? 'Save changes' : 'Create vendor' }}
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
    <script src="{{ $assetVer('assets/js/vendors.js') }}"></script>
@endpush
