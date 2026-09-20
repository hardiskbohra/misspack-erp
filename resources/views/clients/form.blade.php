@extends('layouts.app')

@section('page-title', 'Client Management')

@section('content')

    @php($isEdit = $client->exists)

    <div class="master-form">
        <div class="master-card master-header">
            <h1>{{ $isEdit ? 'Edit Client' : 'Add Client' }}</h1>
            <div class="master-breadcrumb"><a href="{{ url('/') }}">Home</a><span>•</span><a
                    href="{{ route('clients.index') }}">Clients</a><span>•</span><span
                    class="active">{{ $isEdit ? 'Edit' : 'Add' }}</span></div>
        </div>

        @if($errors->any())
        <div class="master-alert master-alert-error">Please fix the highlighted errors and try again.</div>@endif

        <form method="POST" action="{{ $isEdit ? route('clients.update', $client) : route('clients.store') }}"
            class="master-card master-form-card">
            @csrf
            @if($isEdit) @method('PUT') @endif

            <div class="master-section">
                <h3 class="master-section-title">Company Information</h3>
                <div class="master-detail-grid">
                    <div class="master-field"><label class="master-label">Client Number</label><input class="master-input"
                            name="client_number" value="{{ old('client_number', $client->client_number) }}"
                            placeholder="Auto generated if blank">@error('client_number')<div class="master-error">
                                {{ $message }}
                            </div>@enderror</div>
                    <div class="master-field"><label class="master-label">Company Name <span
                                class="master-required">*</span></label><input class="master-input" name="company_name"
                            value="{{ old('company_name', $client->company_name) }}" required>@error('company_name')<div
                            class="master-error">{{ $message }}</div>@enderror</div>
                    <div class="master-field"><label class="master-label">Brand Name</label><input class="master-input"
                            name="brand_name" value="{{ old('brand_name', $client->brand_name) }}"></div>
                    <div class="master-field"><label class="master-label">Client Type <span
                                class="master-required">*</span></label><select class="master-select" name="client_type"
                            required>@foreach($typeOptions as $key => $label)<option value="{{ $key }}"
                                @selected(old('client_type', $client->client_type) === $key)>{{ $label }}</option>
                            @endforeach</select></div>
                    <div class="master-field"><label class="master-label">Status <span
                                class="master-required">*</span></label><select class="master-select" name="status"
                            required>@foreach($statusOptions as $key => $label)<option value="{{ $key }}"
                            @selected(old('status', $client->status) === $key)>{{ $label }}</option>@endforeach</select>
                    </div>
                    <div class="master-field"><label class="master-label">Industry</label><input class="master-input"
                            name="industry" value="{{ old('industry', $client->industry) }}"></div>
                    <div class="master-field"><label class="master-label">Website</label><input class="master-input"
                            name="website" value="{{ old('website', $client->website) }}" placeholder="https://"></div>
                </div>
            </div>

            <div class="master-section">
                <h3 class="master-section-title">Contact Persons</h3>
                <div class="master-detail-grid">
                    <div class="master-field"><label class="master-label">CEO / Director Name</label><input
                            class="master-input" name="ceo_name" value="{{ old('ceo_name', $client->ceo_name) }}"></div>
                    <div class="master-field"><label class="master-label">CEO / Director Email</label><input
                            class="master-input" type="email" name="ceo_email"
                            value="{{ old('ceo_email', $client->ceo_email) }}"></div>
                    <div class="master-field"><label class="master-label">CEO / Director Contact</label><input
                            class="master-input" name="ceo_contact" value="{{ old('ceo_contact', $client->ceo_contact) }}">
                    </div>
                </div>
                    
                <div class="master-detail-grid" style="margin-top:18px;">

                    <div class="master-field"><label class="master-label">Account Person Name</label><input
                            class="master-input" name="account_person_name"
                            value="{{ old('account_person_name', $client->account_person_name) }}"></div>
                    <div class="master-field"><label class="master-label">Account Person Email</label><input
                            class="master-input" type="email" name="account_person_email"
                            value="{{ old('account_person_email', $client->account_person_email) }}"></div>
                    <div class="master-field"><label class="master-label">Account Person Contact</label><input
                            class="master-input" name="account_person_contact"
                            value="{{ old('account_person_contact', $client->account_person_contact) }}"></div>
                </div>
                    
                <div class="master-detail-grid" style="margin-top:18px;">

                    <div class="master-field"><label class="master-label">Marketing / Purchase Person Name</label><input
                            class="master-input" name="marketing_person_name"
                            value="{{ old('marketing_person_name', $client->marketing_person_name) }}"></div>
                    <div class="master-field"><label class="master-label">Marketing / Purchase Email</label><input
                            class="master-input" type="email" name="marketing_person_email"
                            value="{{ old('marketing_person_email', $client->marketing_person_email) }}"></div>
                    <div class="master-field"><label class="master-label">Marketing / Purchase Contact</label><input
                            class="master-input" name="marketing_person_contact"
                            value="{{ old('marketing_person_contact', $client->marketing_person_contact) }}"></div>
                </div>
                    
                <div class="master-detail-grid" style="margin-top:18px;">

                    <div class="master-field"><label class="master-label">Inward Dispatch Person Name</label><input
                            class="master-input" name="dispatch_person_name"
                            value="{{ old('dispatch_person_name', $client->dispatch_person_name) }}"></div>
                    <div class="master-field"><label class="master-label">Inward Dispatch Email</label><input
                            class="master-input" type="email" name="dispatch_person_email"
                            value="{{ old('dispatch_person_email', $client->dispatch_person_email) }}"></div>
                    <div class="master-field"><label class="master-label">Inward Dispatch Contact</label><input
                            class="master-input" name="dispatch_person_contact"
                            value="{{ old('dispatch_person_contact', $client->dispatch_person_contact) }}"></div>
                </div>
            </div>

            <div class="master-section">
                <h3 class="master-section-title">Billing & Shipping Address</h3>
                <div class="master-detail-grid">
                    <div class="master-field full"><label class="master-label">Billing Address</label><input
                            class="master-input"
                            name="billing_address" value="{{ old('billing_address', $client->billing_address) }}"></div>
                    <div class="master-field"><label class="master-label">Billing City</label><input class="master-input"
                            name="billing_city" value="{{ old('billing_city', $client->billing_city) }}"></div>
                    <div class="master-field"><label class="master-label">Billing State</label><input class="master-input"
                            name="billing_state" value="{{ old('billing_state', $client->billing_state) }}"></div>
                    <div class="master-field"><label class="master-label">Billing Country</label><input class="master-input"
                            name="billing_country" value="{{ old('billing_country', $client->billing_country) }}"></div>
                    <div class="master-field"><label class="master-label">Billing Pincode</label><input class="master-input"
                            name="billing_pincode" value="{{ old('billing_pincode', $client->billing_pincode) }}"></div>
                    <label class="master-checkbox" style="color:green;"><input type="checkbox" name="shipping_same_as_billing" value="1"
                            @checked(old('shipping_same_as_billing', $client->shipping_same_as_billing))> Copy to Shipping address</label>
                    <div class="master-field full"><label class="master-label">Shipping Address</label><input
                            class="master-input"
                            name="billing_address" value="{{ old('shipping_address', $client->shipping_address) }}">
                    </div>
                    <div class="master-field"><label class="master-label">Shipping City</label><input class="master-input"
                            name="shipping_city" value="{{ old('shipping_city', $client->shipping_city) }}"></div>
                    <div class="master-field"><label class="master-label">Shipping State</label><input class="master-input"
                            name="shipping_state" value="{{ old('shipping_state', $client->shipping_state) }}"></div>
                    <div class="master-field"><label class="master-label">Shipping Country</label><input
                            class="master-input" name="shipping_country"
                            value="{{ old('shipping_country', $client->shipping_country) }}"></div>
                    <div class="master-field"><label class="master-label">Shipping Pincode</label><input
                            class="master-input" name="shipping_pincode"
                            value="{{ old('shipping_pincode', $client->shipping_pincode) }}"></div>
                </div>
            </div>

            <div class="master-section">
                <h3 class="master-section-title">Tax & Bank Details</h3>
                <div class="master-detail-grid">
                    <div class="master-field"><label class="master-label">GSTIN</label><input class="master-input"
                            name="gstin" value="{{ old('gstin', $client->gstin) }}"></div>
                    <div class="master-field"><label class="master-label">PAN</label><input class="master-input" name="pan"
                            value="{{ old('pan', $client->pan) }}"></div>
                    <div class="master-field"><label class="master-label">TAN</label><input class="master-input" name="tan"
                            value="{{ old('tan', $client->tan) }}"></div>
                    <div class="master-field"><label class="master-label">CIN</label><input class="master-input" name="cin"
                            value="{{ old('cin', $client->cin) }}"></div>
                    <div class="master-field"><label class="master-label">MSME Number</label><input class="master-input"
                            name="msme_number" value="{{ old('msme_number', $client->msme_number) }}"></div>
                    <div class="master-field"><label class="master-label">Bank Name</label><input class="master-input"
                            name="bank_name" value="{{ old('bank_name', $client->bank_name) }}"></div>
                    <div class="master-field"><label class="master-label">Account Holder Name</label><input
                            class="master-input" name="account_holder_name"
                            value="{{ old('account_holder_name', $client->account_holder_name) }}"></div>
                    <div class="master-field"><label class="master-label">Account Number</label><input class="master-input"
                            name="account_number" value="{{ old('account_number', $client->account_number) }}"></div>
                    <div class="master-field"><label class="master-label">IFSC Code</label><input class="master-input"
                            name="ifsc_code" value="{{ old('ifsc_code', $client->ifsc_code) }}"></div>
                    <div class="master-field"><label class="master-label">Bank Branch</label><input class="master-input"
                            name="bank_branch" value="{{ old('bank_branch', $client->bank_branch) }}"></div>
                    <div class="master-field"><label class="master-label">SWIFT Code</label><input class="master-input"
                            name="swift_code" value="{{ old('swift_code', $client->swift_code) }}"></div>
                </div>
            </div>

            <div class="master-section">
                <h3 class="master-section-title">Commercial Details</h3>
                <div class="master-detail-grid">
                    <div class="master-field"><label class="master-label">Credit Limit</label><input class="master-input"
                            type="number" min="0" step="0.01" name="credit_limit"
                            value="{{ old('credit_limit', $client->credit_limit) }}"></div>
                    <div class="master-field"><label class="master-label">Credit Days</label><input class="master-input"
                            type="number" min="0" name="credit_days" value="{{ old('credit_days', $client->credit_days) }}">
                    </div>
                    <div class="master-field"><label class="master-label">Preferred Currency</label><select
                            class="master-select" name="preferred_currency">@foreach($currencyOptions as $key => $label)
                            <option value="{{ $key }}" @selected(old('preferred_currency', $client->preferred_currency) === $key)>{{ $label }}</option>@endforeach
                        </select></div>
                    <div class="master-field full"><label class="master-label">Payment Terms</label><input
                            class="master-input" name="payment_terms"
                            value="{{ old('payment_terms', $client->payment_terms) }}"
                            placeholder="e.g. 30 days credit / advance payment"></div>
                    <div class="master-field full"><label class="master-label">Notes</label><input
                            class="master-input" name="notes" value="{{ old('notes', $client->notes) }}"></div>
                </div>
            </div>

            <div class="master-actions">
                <a href="{{ $isEdit ? route('clients.show', $client) : route('clients.index') }}"
                    class="master-btn master-btn-light">Cancel</a>
                <button type="submit"
                    class="master-btn master-btn-primary">{{ $isEdit ? 'Update Client' : 'Create Client' }}</button>
            </div>
        </form>
    </div>
@endsection