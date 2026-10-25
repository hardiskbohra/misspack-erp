<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vendor KYC — {{ $vendor->vendor_name ?: 'MissPack' }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/master-form.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/master-alert.css') }}">
    @include('layouts.partials.design-system-styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/kyc-public.css') }}">
</head>
<body data-ui-shell="public">
@php
    $groups = [
        1=>['Company','Who you are',['vendor_name'=>'Legal / business name','brand_name'=>'Brand name','vendor_type'=>'Vendor type','website'=>'Website']],
        2=>['People','Who we call',['contact_person_name'=>'Primary contact','contact_person_email'=>'Email address','contact_person_mobile'=>'Phone number','whatsapp_number'=>'WhatsApp number','alternate_contact'=>'Alternate phone']],
        3=>['Addresses','Where you work',['address'=>'Registered / business address','city'=>'City','state'=>'State / province','country'=>'Country','pincode'=>'Postal code']],
        4=>['Tax & bank','How we pay',['gstin'=>'GSTIN (India)','pan'=>'PAN (India)','tax_id'=>'Overseas / other tax ID','import_export_code'=>'Import / export code','bank_name'=>'Bank name','account_holder_name'=>'Account holder','account_number'=>'Account number','ifsc_code'=>'IFSC (India)','swift_code'=>'SWIFT / BIC','bank_branch'=>'Bank branch','bank_address'=>'Bank Address','beneficiary_address'=>'Beneficiary Address']],
    ];
    $required=['vendor_name','vendor_type','contact_person_name','contact_person_email','contact_person_mobile','address','city','country'];
    $indian=\App\Services\VendorKyc::isIndian(old('country',$profile['country']??''));
    $availableEvidence=\App\Services\VendorKyc::availableEvidence($vendor);
    $indianFields=['gstin','account_number','ifsc_code'];
    $errorStep=1;
    foreach ($groups as $n=>$group) foreach ($errors->keys() as $key) {
        if (array_key_exists($key,$group[2]) || ($n===4 && (str_starts_with($key,'documents') || in_array($key,['gst_certificate','pan_document'],true)))) { $errorStep=$n; break 2; }
    }
    $status=$vendor->kyc_status ?: 'draft';
    $kycToast=session('success')?[['type'=>'success','message'=>session('success')]]:[];
@endphp
<div class="kyc">
    <header class="kyc-hero">
        <div class="kyc-hero-brand">
            <img class="kyc-logo" src="{{ asset('images/logo.png') }}" alt="MissPack — Packed Perfect">
            <p class="kyc-eyebrow">Vendor KYC</p>
        </div>
        <div class="kyc-hero-copy">
            <h1>{{ $profile['vendor_name'] ?? $vendor->vendor_name }}</h1>
            <p>Four short steps. Save anytime. Submit when every required field is complete.</p>
        </div>
        <span class="kyc-badge status-{{ str_replace('_','-',$status) }}">{{ $vendor->kycStatusLabel() }}</span>
    </header>
    @if ($status === 'under_review')
        <div class="kyc-banner is-wait" role="status"><strong>With MissPack for review.</strong> This link is read-only until we ask for a change. Submitted details do not update your vendor record until approved.</div>
    @elseif ($status === 'approved')
        <div class="kyc-banner is-ok" role="status"><strong>Approved.</strong> These details are on file. Contact MissPack if something has changed.</div>
    @elseif ($status === 'revision')
        <div class="kyc-banner is-fix" role="alert"><strong>Please revise.</strong> {{ $vendor->kyc_revision_note }}</div>
    @elseif ($status === 'rejected')
        <div class="kyc-banner is-bad" role="alert"><strong>Not accepted.</strong> {{ $vendor->kyc_rejection_reason }} Contact MissPack for help.</div>
    @endif
    @if ($errors->any())
        <div class="kyc-banner is-bad" role="alert" data-kyc-error-banner>
            <strong>Please check these details.</strong> We opened the step with the first field error.
            <ul class="kyc-error-list">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
        </div>
    @endif
    <ol class="kyc-steps" data-kyc-steps aria-label="KYC steps">
        @foreach ($groups as $n=>$group)
            <li><button type="button" class="kyc-step {{ $n===1?'is-current':'' }}" data-kyc-goto="{{ $n }}">
                <span class="kyc-step-num">{{ $n }}</span><span><strong>{{ $group[0] }}</strong><em>{{ $group[1] }}</em></span>
            </button></li>
        @endforeach
    </ol>
    <form method="POST" action="{{ route('vendors.publicKyc.submit',$vendor->kyc_public_token) }}" enctype="multipart/form-data"
        id="kycSubmitForm" class="kyc-form" data-kyc-form data-kyc-token="vendor-{{ $vendor->kyc_public_token }}" data-kyc-persist="0"
        data-kyc-readonly="{{ $readonly?'1':'0' }}" data-kyc-error-step="{{ $errors->any()?$errorStep:'' }}" novalidate>
        @csrf
        <input type="hidden" name="intent" id="kycIntent" value="submit">
        <input type="hidden" name="kyc_version" value="{{ old('kyc_version',$vendor->kyc_version) }}">
        @foreach ($groups as $n=>$group)
            <section class="kyc-panel {{ $n===1?'is-current':'' }}" data-kyc-panel="{{ $n }}" aria-labelledby="kyc-h-{{ $n }}" @if($n!==1) hidden @endif>
                <header class="kyc-panel-head"><h2 id="kyc-h-{{ $n }}">{{ $group[0] }}</h2>
                    <p>{{ [1=>'Legal name as on registration or letterhead. Individuals and overseas providers are welcome.',2=>'A contact who can confirm your company and payment details.',3=>'Your registered office or business address.',4=>'Indian identifiers are optional for overseas vendors. Supply the tax and bank details applicable to you.'][ $n ] }}</p>
                </header>
                <fieldset class="kyc-grid kyc-fields" @disabled($readonly) aria-label="{{ $group[0] }} details">
                    @foreach ($group[2] as $name=>$label)
                        <div class="kyc-field {{ $errors->has($name)?'has-error':'' }}">
                            <label for="{{ $name }}">{{ $label }} @if(in_array($name,$required,true))<span>*</span>@endif @if(in_array($name,$indianFields,true))<span data-kyc-indian-star @if(!$indian) hidden @endif>*</span>@endif</label>
                            @if ($name==='vendor_type')
                                <select id="{{ $name }}" name="{{ $name }}" required>
                                    <option value="">Select vendor type</option>
                                    @foreach (\App\Models\Vendor::typeOptions() as $key=>$text)
                                        <option value="{{ $key }}" @selected(old($name,$profile[$name]??'')===$key)>{{ $text }}</option>
                                    @endforeach
                                </select>
                            @elseif (in_array($name,['address','bank_address','beneficiary_address'],true))
                                <textarea id="{{ $name }}" name="{{ $name }}" rows="3" maxlength="3000" @if($name==='address') required @endif>{{ old($name,$profile[$name]??'') }}</textarea>
                            @else
                                <input id="{{ $name }}" name="{{ $name }}" type="{{ $name==='contact_person_email'?'email':($name==='website'?'url':'text') }}"
                                    value="{{ old($name,$profile[$name]??'') }}" maxlength="{{ in_array($name,['contact_person_mobile','whatsapp_number','alternate_contact'],true)?40:($name==='pincode'?30:255) }}"
                                    @if(in_array($name,$required,true) || ($indian && in_array($name,$indianFields,true))) required @endif
                                    @if(in_array($name,$indianFields,true)) data-kyc-indian-required @endif
                                    @if(in_array($name,['gstin','pan','ifsc_code','swift_code'],true)) data-kyc-upper @endif>
                            @endif
                            @error($name)<p class="kyc-field-error">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </fieldset>
                @if ($n===4)
                    <section class="kyc-documents" aria-labelledby="kyc-documents-title">
                        <h3 id="kyc-documents-title">KYC documents</h3>
                        <p>Indian vendors must upload a GST Certificate and PAN. Additional supporting documents are optional. PDF, JPG, PNG or WebP; up to 5 files per save, 5 MB each, 20 files total. Documents are private to your KYC review.</p>
                        @foreach (['gst_certificate'=>'GST Certificate','pan_document'=>'PAN'] as $category=>$label)
                            <div class="kyc-field {{ $errors->has($category)?'has-error':'' }}">
                                <label for="{{ $category }}">{{ $label }} <span data-kyc-indian-star @if(!$indian) hidden @endif>*</span></label>
                                @if ($availableEvidence[$category]??false)<p>Already uploaded. You do not need to upload again unless it needs updating.</p>@endif
                                @unless ($readonly)
                                    <input id="{{ $category }}" name="{{ $category }}" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" data-kyc-indian-required data-kyc-uploaded="{{ ($availableEvidence[$category]??false)?'1':'0' }}" @if($indian && !($availableEvidence[$category]??false)) required @endif>
                                @endunless
                                @error($category)<p class="kyc-field-error">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                        <p data-kyc-indian-notice @if(!$indian) hidden @endif>GST number, bank account number, IFSC, GST Certificate and PAN uploads are mandatory for Indian vendors.</p>
                        @unless ($readonly)
                            <div class="kyc-field {{ $errors->has('documents')?'has-error':'' }}">
                                <label for="documents">Additional supporting documents (optional)</label>
                                <input id="documents" type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp">
                                @error('documents')<p class="kyc-field-error">{{ $message }}</p>@enderror
                            </div>
                        @endunless
                        @if ($documents->isNotEmpty())
                            <ul>@foreach ($documents as $doc)<li><a href="{{ route('vendors.publicKyc.documents',[$vendor->kyc_public_token,$doc->id]) }}">{{ $doc->categoryLabel() }} — {{ $doc->original_name }}</a></li>@endforeach</ul>
                        @endif
                    </section>
                @endif
            </section>
        @endforeach
        <div class="kyc-nav">
            <button type="button" class="master-btn master-btn-light" data-kyc-prev hidden>Back</button>
            <p class="kyc-nav-hint" data-kyc-hint>Step 1 of 4</p>
            @unless ($readonly)<button type="button" class="master-btn master-btn-soft" data-kyc-draft>Save for later</button>@endunless
            <button type="button" class="master-btn master-btn-primary" data-kyc-next>Continue</button>
            @unless ($readonly)<button type="submit" class="master-btn master-btn-primary" data-kyc-submit hidden>Submit for review</button>@endunless
        </div>
    </form>
    <footer class="kyc-foot">
        <div class="kyc-foot-main">
            <div class="kyc-foot-brand">
                <img src="{{ asset('images/logo-dark.png') }}" alt="MissPack — Packed Perfect">
                <p>MissPack helps brands source, develop and manage packaging with reliable vendor coordination.</p>
                <div class="kyc-social">
                    <a href="https://www.facebook.com/misspackindia" target="_blank" rel="noopener" aria-label="Facebook"><i class="fa-brands fa-facebook"></i></a>
                    <a href="https://www.instagram.com/themisspack" target="_blank" rel="noopener" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
                    <a href="https://www.linkedin.com/company/misspackindia/" target="_blank" rel="noopener" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a>
                    <a href="https://wa.me/917048110823" target="_blank" rel="noopener" aria-label="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
                </div>
            </div>
            <div>
                <h4>Contact</h4>
                <a href="mailto:misspackindia@gmail.com">misspackindia@gmail.com</a>
                <a href="tel:+917048110823">+91 70481 10823</a>
                <a href="https://www.themisspack.com" target="_blank" rel="noopener">www.themisspack.com</a>
                <span>Monday–Saturday, 10:00 AM – 7:00 PM</span>
            </div>
            <div>
                <h4>Address</h4>
                <p><strong>MissPack India Private Limited</strong><br>Ahmedabad, Gujarat, India</p>
                <p>For KYC support, write to accounts or your purchase coordinator.</p>
            </div>
        </div>
        <div class="kyc-foot-bar">
            <span>© {{ date('Y') }} MissPack. All rights reserved.</span>
            <span>Packed Perfect</span>
        </div>
    </footer>
</div>

<script src="{{ asset('assets/js/master-alert.js') }}"></script>
<script>window.kycToast = @json($kycToast);</script>
<script src="{{ $assetVer('assets/js/kyc-public.js') }}"></script>
</body>
</html>
