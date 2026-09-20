<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <title>Client KYC - {{ $client->company_name }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/kyc-public.css') }}">
</head>

<body>
    @php
        $statusClass = str_replace('_', '-', $client->status);
        $kycToast = [];
        @if(session('success'))
            $kycToast[] = ['type' => 'success', 'message' => session('success'), 'color' => '#4f83f1'];
        @endif
        @if(session('error'))
            $kycToast[] = ['type' => 'error', 'message' => session('error'), 'color' => '#ef4770'];
        @endif
        @if($errors->any())
            $kycToast[] = ['type' => 'validation', 'message' => $errors->first(), 'color' => '#ef4770'];
        @endif
    @endphp
    <div class="page">
        <div class="card header" style="background:#2f3a4c;">

            {{-- Row 1 --}}
            <div class="header-top">
                <div class="brand-logo-wrap">
                    <img class="brand-logo" src="{{ asset('images/logo.png') }}" height="150"
                        alt="MissPack - Packed Perfect">
                </div>
            </div>

            {{-- Row 2 --}}
            <div class="header-bottom">
                <div class="client-info">
                    <h1 style="color:white;text-align:right;">{{ $client->company_name ?: 'Client KYC Form' }}</h1>
                    <p style="color:#bbbbbb;text-align:right;font-weight:500;">Please verify and submit your company KYC details.</p>
                </div>

                <div class="status-info" style="text-align:right; margin-top: 10px;">
                    <span class="badge status-{{ $statusClass }}">
                        {{ $statusOptions[$client->status] ?? $client->status }}
                    </span>
                </div>
            </div>

        </div>

        @if($client->status === 'under_review')
            <div class="note">Your KYC form has been submitted and is currently under review. You cannot edit it until
        revision is requested.</div>@endif
        @if($client->status === 'approved')
        <div class="note">Your KYC has been approved. This form is readonly.</div>@endif
        @if($client->status === 'rejected')
            <div class="note">Your KYC was rejected. Please contact MissPack team for more details. Reason:
        {{ $client->rejection_reason ?: '-' }}</div>@endif
        @if($client->status === 'revision')
            <div class="note">Revision requested:
        {{ $client->revision_note ?: 'Please update the required information and resubmit.' }}</div>@endif

        <form method="POST" action="{{ route('clients.publicKyc.submit', $client->public_token) }}"
               id="kycSubmitForm">
            @csrf
            <input type="hidden" name="client_number" value="{{ $client->client_number }}">
            <input type="hidden" name="status" value="{{ $client->status }}">

            <div class="card form-card section">
                <h3 class="section-title">Company Information</h3>
                <div class="grid">
                    <div class="field"><label class="label">Company Name <span class="required">*</span></label><input
                            class="input" name="company_name" value="{{ old('company_name', $client->company_name) }}"
                            required @readonly($readonly)></div>
                    <div class="field"><label class="label">Brand Name</label><input class="input" name="brand_name"
                            value="{{ old('brand_name', $client->brand_name) }}" @readonly($readonly)></div>
                    
                    <div class="field"><label class="label">Industry</label><input class="input" name="industry"
                            value="{{ old('industry', $client->industry) }}" @readonly($readonly)></div>
                    <div class="field"><label class="label">Website</label><input class="input" name="website"
                            value="{{ old('website', $client->website) }}" @readonly($readonly)></div>
                    <div class="field"><label class="label">Preferred Currency</label><select class="select"
                            name="preferred_currency" @disabled($readonly)>@foreach($currencyOptions as $key => $label)
                            <option value="{{ $key }}" @selected(old('preferred_currency', $client->preferred_currency) === $key)>{{ $label }}</option>@endforeach</select></div>
                </div>
            </div>

            <div class="card form-card section">
                <h3 class="section-title">Contact Persons</h3>
                <div class="grid">
                    <div class="field"><label class="label">CEO / Director Name <span class="required">*</span></label><input class="input"
                            name="ceo_name" value="{{ old('ceo_name', $client->ceo_name) }}" required @readonly($readonly)></div>
                    <div class="field"><label class="label">CEO / Director Email <span class="required">*</span></label><input class="input"
                            type="email" name="ceo_email" value="{{ old('ceo_email', $client->ceo_email) }}"
                            required @readonly($readonly)></div>
                    <div class="field"><label class="label">CEO / Director Contact <span class="required">*</span></label><input class="input"
                            name="ceo_contact" value="{{ old('ceo_contact', $client->ceo_contact) }}"
                            required @readonly($readonly)></div>
                    <div class="field"><label class="label">Account Person Name</label><input class="input"
                            name="account_person_name"
                            value="{{ old('account_person_name', $client->account_person_name) }}" @readonly($readonly)>
                    </div>
                    <div class="field"><label class="label">Account Person Email</label><input class="input"
                            type="email" name="account_person_email"
                            value="{{ old('account_person_email', $client->account_person_email) }}"
                            @readonly($readonly)></div>
                    <div class="field"><label class="label">Account Person Contact</label><input class="input"
                            name="account_person_contact"
                            value="{{ old('account_person_contact', $client->account_person_contact) }}"
                            @readonly($readonly)></div>
                    <div class="field"><label class="label">Marketing / Purchase Name</label><input class="input"
                            name="marketing_person_name"
                            value="{{ old('marketing_person_name', $client->marketing_person_name) }}"
                            @readonly($readonly)></div>
                    <div class="field"><label class="label">Marketing / Purchase Email</label><input class="input"
                            type="email" name="marketing_person_email"
                            value="{{ old('marketing_person_email', $client->marketing_person_email) }}"
                            @readonly($readonly)></div>
                    <div class="field"><label class="label">Marketing / Purchase Contact</label><input class="input"
                            name="marketing_person_contact"
                            value="{{ old('marketing_person_contact', $client->marketing_person_contact) }}"
                            @readonly($readonly)></div>
                    <div class="field"><label class="label">Inward Dispatch Name</label><input class="input"
                            name="dispatch_person_name"
                            value="{{ old('dispatch_person_name', $client->dispatch_person_name) }}"
                            @readonly($readonly)></div>
                    <div class="field"><label class="label">Inward Dispatch Email</label><input class="input"
                            type="email" name="dispatch_person_email"
                            value="{{ old('dispatch_person_email', $client->dispatch_person_email) }}"
                            @readonly($readonly)></div>
                    <div class="field"><label class="label">Inward Dispatch Contact</label><input class="input"
                            name="dispatch_person_contact"
                            value="{{ old('dispatch_person_contact', $client->dispatch_person_contact) }}"
                            @readonly($readonly)></div>
                </div>
            </div>

            <div class="card form-card section">
                <h3 class="section-title">Billing & Shipping Address</h3>
                
                <div class="grid">
                    <div class="field full"><label class="label">Billing Address <span class="required">*</span></label><input class="input"
                            name="billing_address"
                            required @readonly($readonly) value="{{ old('billing_address', $client->billing_address) }}"></div>
                    <div class="field"><label class="label">Billing City <span class="required">*</span></label><input class="input" name="billing_city"
                            value="{{ old('billing_city', $client->billing_city) }}" required @readonly($readonly)></div>
                    <div class="field"><label class="label">Billing State <span class="required">*</span></label><input class="input"
                            name="billing_state" value="{{ old('billing_state', $client->billing_state) }}"
                            required @readonly($readonly)></div>
                    <div class="field"><label class="label">Billing Country <span class="required">*</span></label><input class="input"
                            name="billing_country" value="{{ old('billing_country', $client->billing_country) }}"
                            required @readonly($readonly)></div>
                    <div class="field"><label class="label">Billing Pincode <span class="required">*</span></label><input class="input"
                            name="billing_pincode" value="{{ old('billing_pincode', $client->billing_pincode) }}"
                            required @readonly($readonly)></div>
                    <label class="checkbox"><input type="checkbox" name="shipping_same_as_billing" value="1"
                            @checked(old('shipping_same_as_billing', $client->shipping_same_as_billing))
                            @disabled($readonly)> Shipping address same as billing</label>
                    <div class="field full"><label class="label">Shipping Address</label><input class="input"
                            name="shipping_address"
                            @readonly($readonly) value="{{ old('shipping_address', $client->shipping_address) }}">
                    </div>
                    <div class="field"><label class="label">Shipping City</label><input class="input"
                            name="shipping_city" value="{{ old('shipping_city', $client->shipping_city) }}"
                            @readonly($readonly)></div>
                    <div class="field"><label class="label">Shipping State</label><input class="input"
                            name="shipping_state" value="{{ old('shipping_state', $client->shipping_state) }}"
                            @readonly($readonly)></div>
                    <div class="field"><label class="label">Shipping Country</label><input class="input"
                            name="shipping_country" value="{{ old('shipping_country', $client->shipping_country) }}"
                            @readonly($readonly)></div>
                    <div class="field"><label class="label">Shipping Pincode</label><input class="input"
                            name="shipping_pincode" value="{{ old('shipping_pincode', $client->shipping_pincode) }}"
                            @readonly($readonly)></div>
                </div>
            </div>

            <div class="card form-card section">
                <h3 class="section-title">Tax & Bank Details</h3>
                <div class="grid">
                    <div class="field"><label class="label">GSTIN</label><input class="input" name="gstin"
                            value="{{ old('gstin', $client->gstin) }}" @readonly($readonly)></div>
                    <div class="field"><label class="label">PAN</label><input class="input" name="pan"
                            value="{{ old('pan', $client->pan) }}" @readonly($readonly)></div>
                    <div class="field"><label class="label">TAN</label><input class="input" name="tan"
                            value="{{ old('tan', $client->tan) }}" @readonly($readonly)></div>
                    <div class="field"><label class="label">CIN</label><input class="input" name="cin"
                            value="{{ old('cin', $client->cin) }}" @readonly($readonly)></div>
                    <div class="field"><label class="label">MSME Number</label><input class="input" name="msme_number"
                            value="{{ old('msme_number', $client->msme_number) }}" @readonly($readonly)></div>
                    <div class="field"><label class="label">Bank Name</label><input class="input" name="bank_name"
                            value="{{ old('bank_name', $client->bank_name) }}" @readonly($readonly)></div>
                    <div class="field"><label class="label">Account Holder Name</label><input class="input"
                            name="account_holder_name"
                            value="{{ old('account_holder_name', $client->account_holder_name) }}" @readonly($readonly)>
                    </div>
                    <div class="field"><label class="label">Account Number</label><input class="input"
                            name="account_number" value="{{ old('account_number', $client->account_number) }}"
                            @readonly($readonly)></div>
                    <div class="field"><label class="label">IFSC Code</label><input class="input" name="ifsc_code"
                            value="{{ old('ifsc_code', $client->ifsc_code) }}" @readonly($readonly)></div>
                    <div class="field"><label class="label">Bank Branch</label><input class="input" name="bank_branch"
                            value="{{ old('bank_branch', $client->bank_branch) }}" @readonly($readonly)></div>
                    <div class="field"><label class="label">SWIFT Code</label><input class="input" name="swift_code"
                            value="{{ old('swift_code', $client->swift_code) }}" @readonly($readonly)></div>
                </div>
            </div>

            <!--<div class="card form-card section">-->
            <!--    <h3 class="section-title">Commercial Details</h3>-->
            <!--    <div class="grid">-->
            <!--        <div class="field"><label class="label">Credit Limit</label><input class="input" type="number"-->
            <!--                min="0" step="0.01" name="credit_limit"-->
            <!--                value="{{ old('credit_limit', $client->credit_limit) }}" @readonly($readonly)></div>-->
            <!--        <div class="field"><label class="label">Credit Days</label><input class="input" type="number"-->
            <!--                min="0" name="credit_days" value="{{ old('credit_days', $client->credit_days) }}"-->
            <!--                @readonly($readonly)></div>-->
            <!--        <div class="field full"><label class="label">Payment Terms</label><input class="input"-->
            <!--                name="payment_terms" value="{{ old('payment_terms', $client->payment_terms) }}"-->
            <!--                @readonly($readonly)></div>-->
            <!--        <div class="field full"><label class="label">Notes</label><input class="input" name="notes"-->
            <!--                @readonly($readonly) value="{{ old('notes', $client->notes) }}"></div>-->
            <!--    </div>-->
            <!--</div>-->

            @unless($readonly)
                <div class="actions" style="justify-content: center;">
                    <button type="submit" class="btn btn-primary px-4">
                        Submit KYC for Review
                    </button>
                </div>
            @endunless
        </form>
        
        <footer class="card kyc-footer">
        <div class="kyc-footer-top" style="background:#D9A6A2;">
            <div class="kyc-footer-brand">
                <img src="{{ asset('images/logo-dark.png') }}" alt="MissPack - Packed Perfect">
                <p>MissPack helps brands source, develop and manage packaging with reliable client coordination and transparent documentation.</p>
                <div class="kyc-social" aria-label="Social media links">
                    <a href="https://www.facebook.com/misspackindia" target="_blank" rel="noopener" title="Facebook" aria-label="Facebook">
                        <i class="fa-brands fa-facebook"></i>
                    </a>
                
                    <a href="https://www.instagram.com/themisspack" target="_blank" rel="noopener" title="Instagram" aria-label="Instagram">
                        <i class="fa-brands fa-instagram"></i>
                    </a>
                
                    <a href="https://www.linkedin.com/company/misspackindia/" target="_blank" rel="noopener" title="LinkedIn" aria-label="LinkedIn">
                        <i class="fa-brands fa-linkedin-in"></i>
                    </a>
                
                    <a href="https://wa.me/917048110823" target="_blank" rel="noopener" title="WhatsApp" aria-label="WhatsApp">
                        <i class="fa-brands fa-whatsapp"></i>
                    </a>
                </div>
            </div>

            <div class="kyc-footer-col">
                <h4>Contact Details</h4>
                <div class="kyc-footer-list">
                    <a href="mailto:misspackindia@gmail.com"><i class="fa-regular fa-envelope"></i> misspackindia@gmail.com</a>
                    <a href="tel:+917048110823">☎ +91 70481 10823</a>
                    <a href="https://www.themisspack.com" target="_blank" rel="noopener">🌐 www.themisspack.com</a>
                    <span>🕘 Monday to Saturday, 10:00 AM - 7:00 PM</span>
                </div>
            </div>

            <div class="kyc-footer-col">
                <h4>Address</h4>
                <p><span><b>MissPack India Private Limited</b></span><br>Ahmedabad, Gujarat, India</p>
                <p style="margin-top:10px;">For KYC support, please contact our accounts or sales coordination team.</p>
            </div>
        </div>
        <div class="kyc-footer-bottom" style="background:#2f3a4c;color:grey;">
            <span>© {{ date('Y') }} MissPack. All rights reserved.</span>
            <span>Powered by <a href="https://themisspack.com" target="_blank" rel="noopener">MissPack</a> · Packed Perfect</span>
        </div>
    </footer>
    </div>
</body>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    window.kycToast = @json($kycToast);
</script>
<script src="{{ asset('assets/js/kyc-public.js') }}"></script>

</html>