<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <title>Client KYC - {{ $client->company_name }}</title>
    <style>
        :root {
            --primary: #4f83f1;
            --primary2: #6366f1;
            --green: #10b981;
            --orange: #f59e0b;
            --red: #ef4770;
            --purple: #8b5cf6;
            --dark: #17233b;
            --muted: #687386;
            --border: #dfe7f3;
            --bg: #eef3ff;
            --soft: #edf5ff;
            --shadow: 0 14px 35px rgba(25, 42, 70, .08);
        }

        * {
            box-sizing: border-box
        }

        body {
            margin: 0;
            background: var(--bg);
            font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: var(--dark);
            font-size: 14px
        }

        .page {
            max-width: 1180px;
            margin: 0 auto;
            padding: 28px
        }

        .card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: var(--shadow)
        }

        .header {
            padding: 10px 28px;
            margin-bottom: 18px;
            display: flex;
            justify-content: space-between;
            gap: 18px;
            align-items: center
        }

        .brand {
            font-weight: 900;
            color: var(--primary);
            letter-spacing: .03em
        }

        .brand-title {
            font-size: 24px;
            font-weight: 900;
            color: #4f83f1;
        }

        .header h1 {
            margin: 8px 0 6px;
            font-size: 28px
        }

        .header p {
            margin: 0;
            color: var(--muted);
            font-weight: 700
        }

        .badge {
            display: inline-flex;
            border-radius: 999px;
            border:1px solid #a7f3d0;
            padding: 7px 12px;
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .03em
        }

        .status-draft {
            background: #f3f6fb;
            color: #536079
        }

        .status-under-review {
            background: #fff4e5;
            color: #d97706
        }

        .status-approved {
            background: #e8fff7;
            color: #0e9f6e
        }

        .status-rejected {
            background: #ffeaf0;
            color: #e11d48
        }

        .status-revision {
            background: #ece7ff;
            color: #7c3aed
        }

        .form-card {
            padding: 26px
        }

        .section {
            margin-bottom: 26px
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0 0 20px;
            color: #4f83f1;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase
        }

        .section-title:after {
            content: "";
            height: 1px;
            background: #4f83f1;
            flex: 1
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px
        }

        .field.full {
            grid-column: 1/-1
        }

        .label {
            display: block;
            margin-top: 10px;
            margin-bottom: 10px;
            color: #111111;
            font-size: 14px;
            font-weight: 700
        }

        .required {
            color: var(--red)
        }

        .input,
        .select,
        .textarea {
            width: 100%;
            border: 1px solid #d8e2ef;
            border-radius: 12px;
            background: #fff;
            color: var(--dark);
            font-size: 14px;
            font-weight: 400;
            outline: none
        }

        .input,
        .select {
            height: 44px;
            padding: 10px 14px
        }

        .textarea {
            min-height: 76px;
            padding: 12px 14px;
            resize: vertical
        }

        .input:focus,
        .select:focus,
        .textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 131, 241, .12)
        }

        .input[readonly],
        .textarea[readonly],
        .select:disabled {
            background: #f8fafc;
            color: #64748b
        }

        .checkbox {
            display: flex;
            gap: 9px;
            align-items: center;
            color: #536079;
            font-weight: 500;
            margin-top: 28px
        }

        .btn {
            min-height: 44px;
            border: 0;
            border-radius: 12px;
            padding: 12px 20px;
            font-size: 14px;
            font-weight: 900;
            text-decoration: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary2));
            color: #fff
        }

        .actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 26px
        }

        .alert {
            padding: 14px 16px;
            margin-bottom: 18px;
            border-radius: 14px;
            font-weight: 800
        }

        .success {
            color: #047857;
            background: #e8fff7;
            border: 1px solid #a7f3d0
        }

        .error {
            color: #be123c;
            background: #fff0f4;
            border: 1px solid #fecdd3
        }

        .note {
            padding: 14px;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: #fbfdff;
            color: red;
            font-weight: 700;
            line-height: 1.55;
            margin-bottom: 18px;
            text-align: center
        }
        
        .kyc-footer {
            margin-top: 24px;
            overflow: hidden;
            background: #D9A6A2;
        }
    
        .kyc-footer-top {
            display: grid;
            grid-template-columns: 1.35fr 1fr 1fr;
            gap: 22px;
            padding: 26px;
            background: #fff
        }
    
        .kyc-footer-brand img {
            height: 56px;
            width: auto;
            max-width: 220px;
            object-fit: contain;
            display: block;
            margin-bottom: 12px
        }
    
        .kyc-footer-brand p,
        .kyc-footer-col p {
            margin: 0;
            color: #000;
            font-weight: 400;
            line-height: 1.65
        }
    
        .kyc-footer-col h4 {
            margin: 0 0 12px;
            font-size: 14px;
            font-weight: 600;
            color: #000;
            text-transform: uppercase;
            letter-spacing: .04em
        }
    
        .kyc-footer-list {
            display: grid;
            gap: 9px
        }
    
        .kyc-footer-list a,
        .kyc-footer-list span {
            color: #000;
            text-decoration: none;
            font-weight: 400;
            line-height: 1.45
        }
    
        .kyc-footer-list a:hover {
            color: var(--primary)
        }
    
        .kyc-social {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 14px
        }
    
        .kyc-social a {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--soft);
            color: var(--primary);
            font-weight: 500;
            text-decoration: none;
            border: 1px solid var(--border)
        }
    
        .kyc-footer-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            padding: 14px 26px;
            border-top: 1px solid var(--border);
            background: #fbfdff;
            color: var(--muted);
            font-size: 12px;
            font-weight: 500
        }
    
        .kyc-footer-bottom a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500
        }
        
        .kyc-social svg {
            width: 18px;
            height: 18px;
            display: block;
            fill: currentColor;
        }

        @media (max-width: 1024px) {
            .page {
                padding: 20px;
            }
        
            .header {
                padding: 22px;
                align-items: flex-start;
            }
        
            .form-card {
                padding: 22px;
            }
        
            .grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 14px;
            }
        
            .field.full {
                grid-column: 1 / -1;
            }
        
            .section {
                margin-bottom: 22px;
            }
        
            .kyc-footer-top {
                grid-template-columns: 1fr 1fr;
                padding: 22px;
            }
        
            .kyc-footer-brand {
                grid-column: 1 / -1;
            }
        }
        
        @media (max-width: 768px) {
            .page {
                padding: 14px;
            }
        
            .header {
                flex-direction: column;
                align-items: flex-start;
                padding: 20px;
                gap: 14px;
            }
        
            .brand-logo {
                height: 52px;
            }
        
            .header h1 {
                font-size: 22px;
                line-height: 1.25;
            }
        
            .badge {
                align-self: flex-start;
            }
        
            .form-card {
                padding: 18px;
                border-radius: 16px;
            }
        
            .grid {
                grid-template-columns: 1fr;
                gap: 14px;
            }
        
            .field,
            .field.full {
                grid-column: 1 / -1;
            }
        
            .section-title {
                align-items: flex-start;
                gap: 8px;
            }
        
            .section-title:after {
                margin-top: 8px;
            }
        
            .input,
            .select {
                height: 44px;
                font-size: 14px;
            }
        
            .textarea {
                min-height: 90px;
                font-size: 14px;
            }
        
            .checkbox {
                margin-top: 4px;
                align-items: flex-start;
                line-height: 1.45;
            }
        
            .actions {
                justify-content: center;
            }
        
            .actions .btn {
                width: 100%;
                max-width: 320px;
                justify-content: center;
            }
        
            .kyc-footer-top {
                grid-template-columns: 1fr;
                padding: 20px;
                gap: 20px;
            }
        
            .kyc-footer-brand img {
                height: 50px;
                max-width: 210px;
            }
        
            .kyc-footer-bottom {
                flex-direction: column;
                align-items: flex-start;
                padding: 14px 20px;
            }
        }
        
        @media (max-width: 480px) {
            body {
                font-size: 13px;
            }
        
            .page {
                padding: 10px;
            }
        
            .header {
                padding: 16px;
                border-radius: 16px;
                align-items: center;
                justify-content: center;
                text-align: center;
            }
        
            .brand-logo {
                height: 100px;
            }
        
            .brand {
                font-size: 12px;
            }
        
            .header h1 {
                font-size: 20px;
            }
        
            .header p {
                font-size: 13px;
                line-height: 1.5;
            }
        
            .badge {
                font-size: 10px;
                padding: 6px 10px;
            }
        
            .form-card {
                padding: 14px;
            }
        
            .section {
                margin-bottom: 20px;
            }
        
            .section-title {
                font-size: 11px;
                line-height: 1.35;
            }
        
            .label {
                font-size: 12px;
            }
        
            .input,
            .select {
                height: 42px;
                padding: 9px 12px;
            }
        
            .textarea {
                min-height: 84px;
                padding: 10px 12px;
            }
        
            .btn {
                min-height: 42px;
                width: 100%;
            }
        
            .kyc-footer-top {
                grid-template-columns: 1fr;
                padding: 20px;
                text-align: center;
            }
        
            .kyc-footer-brand img {
                margin-left: auto;
                margin-right: auto;
            }
        
            .kyc-social {
                justify-content: center;
            }
        
            .kyc-footer-list {
                justify-items: center;
            }
        
            .kyc-footer-bottom {
                align-items: center;
                justify-content: center;
                text-align: center;
                flex-direction: column;
                padding: 14px 20px;
            }
        }
    </style>
</head>

<body>
    @php($statusClass = str_replace('_', '-', $client->status))
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
    document.addEventListener('DOMContentLoaded', function () {
        @if(session('success'))
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: @json(session('success')),
                confirmButtonColor: '#4f83f1'
            });
        @endif

        @if(session('error'))
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: @json(session('error')),
                confirmButtonColor: '#ef4770'
            });
        @endif

        @if($errors->any())
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: @json($errors->first()),
                confirmButtonColor: '#ef4770'
            });
        @endif
    });
    
    document.addEventListener('DOMContentLoaded', function () {
        const kycSubmitForm = document.getElementById('kycSubmitForm');

        if (!kycSubmitForm) {
            return;
        }

        kycSubmitForm.addEventListener('submit', function (event) {
            event.preventDefault();

            Swal.fire({
                title: 'Submit KYC for Review?',
                text: 'Please confirm that all details are correct. After submission, the form will go under review.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, Submit',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#4f83f1',
                cancelButtonColor: '#ef4770',
                reverseButtons: true
            }).then(function (result) {
                if (result.isConfirmed) {
                    kycSubmitForm.submit();
                }
            });
        });
    });
</script>

</html>