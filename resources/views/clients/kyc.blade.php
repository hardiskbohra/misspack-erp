<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Client KYC — {{ $client->company_name ?: 'MissPack' }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@450;550;650;750;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/master-form.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/kyc-public.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/master-alert.css') }}">
    @include('layouts.partials.design-system-styles')
</head>
<body data-ui-shell="public">
@php
    $statusClass = str_replace('_', '-', $client->status);
    $statusLabel = $statusOptions[$client->status] ?? $client->status;
    $val = fn (string $name) => old($name, $client->{$name});
    $bad = fn (string $name) => $errors->has($name);
    $kycToast = [];
    if (session('success')) {
        $kycToast[] = ['type' => 'success', 'message' => session('success')];
    }
    if (session('error')) {
        $kycToast[] = ['type' => 'error', 'message' => session('error')];
    }
    $errorFields = $errors->keys();
    $errorStep = 1;
    $stepFields = [
        1 => ['company_name', 'brand_name', 'industry', 'website', 'preferred_currency'],
        2 => ['ceo_name', 'ceo_email', 'ceo_contact', 'account_person_name', 'account_person_email', 'account_person_contact', 'marketing_person_name', 'marketing_person_email', 'marketing_person_contact', 'dispatch_person_name', 'dispatch_person_email', 'dispatch_person_contact'],
        3 => ['billing_address', 'billing_city', 'billing_state', 'billing_country', 'billing_pincode', 'shipping_address', 'shipping_city', 'shipping_state', 'shipping_country', 'shipping_pincode'],
        4 => ['gstin', 'pan', 'tan', 'cin', 'msme_number', 'bank_name', 'account_holder_name', 'account_number', 'ifsc_code', 'bank_branch', 'swift_code'],
    ];
    foreach ($stepFields as $step => $names) {
        if (array_intersect($errorFields, $names)) {
            $errorStep = $step;
            break;
        }
    }
    $steps = [
        1 => ['label' => 'Company', 'hint' => 'Who you are'],
        2 => ['label' => 'People', 'hint' => 'Who we call'],
        3 => ['label' => 'Addresses', 'hint' => 'Where to send'],
        4 => ['label' => 'Tax & bank', 'hint' => 'How we pay'],
    ];
@endphp

<div class="kyc">
    <header class="kyc-top">
        <div class="kyc-brand">
            <img src="{{ asset('images/logo.png') }}" alt="MissPack">
            <div>
                <p class="kyc-eyebrow">Know Your Customer</p>
                <h1>{{ $client->company_name ?: 'Your company KYC' }}</h1>
                <p class="kyc-lead">Four short steps. Save anytime. Submit when every required field is complete.</p>
            </div>
        </div>
        <span class="kyc-badge status-{{ $statusClass }}">{{ $statusLabel }}</span>
    </header>

    @if ($client->status === 'under_review')
        <div class="kyc-banner is-wait" role="status">
            <strong>With MissPack for review.</strong>
            This link is read-only until we ask for a change.
        </div>
    @elseif ($client->status === 'approved')
        <div class="kyc-banner is-ok" role="status">
            <strong>Approved.</strong>
            These details are on file. Speak to MissPack if something has changed.
        </div>
    @elseif ($client->status === 'rejected')
        <div class="kyc-banner is-bad" role="alert">
            <strong>Not accepted.</strong>
            {{ $client->rejection_reason ?: 'Please contact MissPack for what to do next.' }}
        </div>
    @elseif ($client->status === 'revision')
        <div class="kyc-banner is-fix" role="alert">
            <strong>Please revise.</strong>
            {{ $client->revision_note ?: 'Update the highlighted details and submit again.' }}
        </div>
    @endif

    @if ($errors->any())
        <div class="kyc-banner is-bad" role="alert">
            <strong>{{ $errors->count() }} {{ \Illuminate\Support\Str::plural('field', $errors->count()) }} need attention.</strong>
            We opened the step that has the first one.
            <ul class="kyc-error-list">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <ol class="kyc-steps" data-kyc-steps aria-label="KYC steps">
        @foreach ($steps as $n => $step)
            <li>
                <button type="button" class="kyc-step {{ $n === 1 ? 'is-current' : '' }}" data-kyc-goto="{{ $n }}">
                    <span class="kyc-step-num">{{ $n }}</span>
                    <span>
                        <strong>{{ $step['label'] }}</strong>
                        <em>{{ $step['hint'] }}</em>
                    </span>
                </button>
            </li>
        @endforeach
    </ol>

    <form method="POST" action="{{ route('clients.publicKyc.submit', $client->public_token) }}"
        id="kycSubmitForm" class="kyc-form" data-kyc-form data-kyc-token="{{ $client->public_token }}"
        data-kyc-readonly="{{ $readonly ? '1' : '0' }}" data-kyc-error-step="{{ $errors->any() ? $errorStep : '' }}"
        novalidate>
        @csrf
        <input type="hidden" name="client_number" value="{{ $client->client_number }}">
        <input type="hidden" name="intent" id="kycIntent" value="submit">

        <section class="kyc-panel is-current" data-kyc-panel="1" aria-labelledby="kyc-h-1">
            <header class="kyc-panel-head">
                <h2 id="kyc-h-1">Company</h2>
                <p>Legal name as on GST / letterhead. Brand name is optional.</p>
            </header>
            <div class="kyc-grid">
                <div class="kyc-field {{ $bad('company_name') ? 'has-error' : '' }}">
                    <label for="company_name">Company name <span>*</span></label>
                    <input id="company_name" name="company_name" value="{{ $val('company_name') }}" required maxlength="255" autocomplete="organization" @readonly($readonly)>
                    @error('company_name')<p class="kyc-field-error">{{ $message }}</p>@enderror
                </div>
                <div class="kyc-field">
                    <label for="brand_name">Brand name</label>
                    <input id="brand_name" name="brand_name" value="{{ $val('brand_name') }}" maxlength="255" @readonly($readonly)>
                </div>
                <div class="kyc-field">
                    <label for="industry">Industry</label>
                    <input id="industry" name="industry" value="{{ $val('industry') }}" maxlength="255" placeholder="Beauty, FMCG, pharma…" @readonly($readonly)>
                </div>
                <div class="kyc-field">
                    <label for="website">Website</label>
                    <input id="website" name="website" value="{{ $val('website') }}" maxlength="255" inputmode="url" placeholder="https://" @readonly($readonly)>
                </div>
                <div class="kyc-field">
                    <label for="preferred_currency">Preferred currency</label>
                    <select id="preferred_currency" name="preferred_currency" @disabled($readonly)>
                        @foreach ($currencyOptions as $key => $label)
                            <option value="{{ $key }}" @selected($val('preferred_currency') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </section>

        <section class="kyc-panel" data-kyc-panel="2" hidden aria-labelledby="kyc-h-2">
            <header class="kyc-panel-head">
                <h2 id="kyc-h-2">People</h2>
                <p>CEO / director is required. Accounts, purchase and dispatch help us reach the right desk.</p>
            </header>
            @php
                $people = [
                    ['prefix' => 'ceo', 'title' => 'CEO / Director', 'required' => true],
                    ['prefix' => 'account_person', 'title' => 'Accounts', 'required' => false],
                    ['prefix' => 'marketing_person', 'title' => 'Marketing / Purchase', 'required' => false],
                    ['prefix' => 'dispatch_person', 'title' => 'Inward dispatch', 'required' => false],
                ];
            @endphp
            @foreach ($people as $person)
                @php
                    $nameKey = $person['prefix'].'_name';
                    $emailKey = $person['prefix'].'_email';
                    $phoneKey = $person['prefix'].'_contact';
                @endphp
                <div class="kyc-person">
                    <h3>{{ $person['title'] }} @if ($person['required'])<span class="kyc-need">Required</span>@endif</h3>
                    <div class="kyc-grid">
                        <div class="kyc-field {{ $bad($nameKey) ? 'has-error' : '' }}">
                            <label for="{{ $nameKey }}">Name @if ($person['required'])<span>*</span>@endif</label>
                            <input id="{{ $nameKey }}" name="{{ $nameKey }}" value="{{ $val($nameKey) }}" maxlength="255"
                                autocomplete="name" {{ $person['required'] ? 'required' : '' }} @readonly($readonly)>
                            @error($nameKey)<p class="kyc-field-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="kyc-field {{ $bad($emailKey) ? 'has-error' : '' }}">
                            <label for="{{ $emailKey }}">Email @if ($person['required'])<span>*</span>@endif</label>
                            <input id="{{ $emailKey }}" type="email" name="{{ $emailKey }}" value="{{ $val($emailKey) }}" maxlength="255"
                                autocomplete="email" {{ $person['required'] ? 'required' : '' }} @readonly($readonly)>
                            @error($emailKey)<p class="kyc-field-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="kyc-field {{ $bad($phoneKey) ? 'has-error' : '' }}">
                            <label for="{{ $phoneKey }}">Phone @if ($person['required'])<span>*</span>@endif</label>
                            <input id="{{ $phoneKey }}" name="{{ $phoneKey }}" value="{{ $val($phoneKey) }}" maxlength="40"
                                autocomplete="tel" inputmode="tel" {{ $person['required'] ? 'required' : '' }} @readonly($readonly)>
                            @error($phoneKey)<p class="kyc-field-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>
            @endforeach
        </section>

        <section class="kyc-panel" data-kyc-panel="3" hidden aria-labelledby="kyc-h-3">
            <header class="kyc-panel-head">
                <h2 id="kyc-h-3">Addresses</h2>
                <p>Billing is required. Tick the box if goods go to the same place.</p>
            </header>
            <div class="kyc-grid">
                <div class="kyc-field kyc-span-2 {{ $bad('billing_address') ? 'has-error' : '' }}">
                    <label for="billing_address">Billing address <span>*</span></label>
                    <input id="billing_address" name="billing_address" value="{{ $val('billing_address') }}" required autocomplete="street-address" @readonly($readonly)>
                    @error('billing_address')<p class="kyc-field-error">{{ $message }}</p>@enderror
                </div>
                <div class="kyc-field {{ $bad('billing_city') ? 'has-error' : '' }}">
                    <label for="billing_city">City <span>*</span></label>
                    <input id="billing_city" name="billing_city" value="{{ $val('billing_city') }}" required maxlength="255" autocomplete="address-level2" @readonly($readonly)>
                    @error('billing_city')<p class="kyc-field-error">{{ $message }}</p>@enderror
                </div>
                <div class="kyc-field {{ $bad('billing_state') ? 'has-error' : '' }}">
                    <label for="billing_state">State <span>*</span></label>
                    <input id="billing_state" name="billing_state" value="{{ $val('billing_state') }}" required maxlength="255" autocomplete="address-level1" @readonly($readonly)>
                    @error('billing_state')<p class="kyc-field-error">{{ $message }}</p>@enderror
                </div>
                <div class="kyc-field {{ $bad('billing_country') ? 'has-error' : '' }}">
                    <label for="billing_country">Country <span>*</span></label>
                    <input id="billing_country" name="billing_country" value="{{ $val('billing_country') }}" required maxlength="255" autocomplete="country-name" @readonly($readonly)>
                    @error('billing_country')<p class="kyc-field-error">{{ $message }}</p>@enderror
                </div>
                <div class="kyc-field {{ $bad('billing_pincode') ? 'has-error' : '' }}">
                    <label for="billing_pincode">Pincode <span>*</span></label>
                    <input id="billing_pincode" name="billing_pincode" value="{{ $val('billing_pincode') }}" required maxlength="30" autocomplete="postal-code" inputmode="numeric" @readonly($readonly)>
                    @error('billing_pincode')<p class="kyc-field-error">{{ $message }}</p>@enderror
                </div>
            </div>

            <label class="kyc-check">
                <input type="checkbox" name="shipping_same_as_billing" value="1" data-kyc-same-ship
                    @checked(old('shipping_same_as_billing', $client->shipping_same_as_billing)) @disabled($readonly)>
                Shipping address is the same as billing
            </label>

            <div class="kyc-grid" data-kyc-shipping>
                <div class="kyc-field kyc-span-2">
                    <label for="shipping_address">Shipping address</label>
                    <input id="shipping_address" name="shipping_address" value="{{ $val('shipping_address') }}" @readonly($readonly)>
                </div>
                <div class="kyc-field">
                    <label for="shipping_city">City</label>
                    <input id="shipping_city" name="shipping_city" value="{{ $val('shipping_city') }}" maxlength="255" @readonly($readonly)>
                </div>
                <div class="kyc-field">
                    <label for="shipping_state">State</label>
                    <input id="shipping_state" name="shipping_state" value="{{ $val('shipping_state') }}" maxlength="255" @readonly($readonly)>
                </div>
                <div class="kyc-field">
                    <label for="shipping_country">Country</label>
                    <input id="shipping_country" name="shipping_country" value="{{ $val('shipping_country') }}" maxlength="255" @readonly($readonly)>
                </div>
                <div class="kyc-field">
                    <label for="shipping_pincode">Pincode</label>
                    <input id="shipping_pincode" name="shipping_pincode" value="{{ $val('shipping_pincode') }}" maxlength="30" inputmode="numeric" @readonly($readonly)>
                </div>
            </div>
        </section>

        <section class="kyc-panel" data-kyc-panel="4" hidden aria-labelledby="kyc-h-4">
            <header class="kyc-panel-head">
                <h2 id="kyc-h-4">Tax &amp; bank</h2>
                <p>GSTIN and PAN are optional but must be the real format if you fill them. Then glance at the summary and submit.</p>
            </header>
            <div class="kyc-grid">
                <div class="kyc-field {{ $bad('gstin') ? 'has-error' : '' }}">
                    <label for="gstin">GSTIN</label>
                    <input id="gstin" name="gstin" value="{{ $val('gstin') }}" maxlength="15" data-kyc-upper placeholder="24AAAAA0000A1Z5" @readonly($readonly)>
                    @error('gstin')<p class="kyc-field-error">{{ $message }}</p>@enderror
                </div>
                <div class="kyc-field {{ $bad('pan') ? 'has-error' : '' }}">
                    <label for="pan">PAN</label>
                    <input id="pan" name="pan" value="{{ $val('pan') }}" maxlength="10" data-kyc-upper placeholder="ABCDE1234F" @readonly($readonly)>
                    @error('pan')<p class="kyc-field-error">{{ $message }}</p>@enderror
                </div>
                <div class="kyc-field">
                    <label for="tan">TAN</label>
                    <input id="tan" name="tan" value="{{ $val('tan') }}" maxlength="20" data-kyc-upper @readonly($readonly)>
                </div>
                <div class="kyc-field">
                    <label for="cin">CIN</label>
                    <input id="cin" name="cin" value="{{ $val('cin') }}" maxlength="255" data-kyc-upper @readonly($readonly)>
                </div>
                <div class="kyc-field">
                    <label for="msme_number">MSME number</label>
                    <input id="msme_number" name="msme_number" value="{{ $val('msme_number') }}" maxlength="255" @readonly($readonly)>
                </div>
                <div class="kyc-field">
                    <label for="bank_name">Bank name</label>
                    <input id="bank_name" name="bank_name" value="{{ $val('bank_name') }}" maxlength="255" @readonly($readonly)>
                </div>
                <div class="kyc-field">
                    <label for="account_holder_name">Account holder</label>
                    <input id="account_holder_name" name="account_holder_name" value="{{ $val('account_holder_name') }}" maxlength="255" @readonly($readonly)>
                </div>
                <div class="kyc-field">
                    <label for="account_number">Account number</label>
                    <input id="account_number" name="account_number" value="{{ $val('account_number') }}" maxlength="255" inputmode="numeric" @readonly($readonly)>
                </div>
                <div class="kyc-field {{ $bad('ifsc_code') ? 'has-error' : '' }}">
                    <label for="ifsc_code">IFSC</label>
                    <input id="ifsc_code" name="ifsc_code" value="{{ $val('ifsc_code') }}" maxlength="11" data-kyc-upper placeholder="HDFC0001234" @readonly($readonly)>
                    @error('ifsc_code')<p class="kyc-field-error">{{ $message }}</p>@enderror
                </div>
                <div class="kyc-field">
                    <label for="bank_branch">Branch</label>
                    <input id="bank_branch" name="bank_branch" value="{{ $val('bank_branch') }}" maxlength="255" @readonly($readonly)>
                </div>
                <div class="kyc-field">
                    <label for="swift_code">SWIFT</label>
                    <input id="swift_code" name="swift_code" value="{{ $val('swift_code') }}" maxlength="255" data-kyc-upper @readonly($readonly)>
                </div>
            </div>

            <div class="kyc-review" data-kyc-review></div>
        </section>

        <div class="kyc-nav">
            <button type="button" class="kyc-btn kyc-btn-ghost" data-kyc-prev hidden>Back</button>
            <p class="kyc-nav-hint" data-kyc-hint>Step 1 of 4</p>
            @unless ($readonly)
                <button type="button" class="kyc-btn kyc-btn-soft" data-kyc-draft>Save for later</button>
            @endunless
            <button type="button" class="kyc-btn kyc-btn-primary" data-kyc-next>Continue</button>
            @unless ($readonly)
                <button type="submit" class="kyc-btn kyc-btn-primary" data-kyc-submit hidden>Submit for review</button>
            @endunless
        </div>
    </form>

    <footer class="kyc-foot">
        <div>
            <img src="{{ asset('images/logo-dark.png') }}" alt="MissPack">
            <p>Questions? <a href="mailto:misspackindia@gmail.com">misspackindia@gmail.com</a> · <a href="tel:+917048110823">+91 70481 10823</a></p>
        </div>
        <p>© {{ date('Y') }} MissPack India Private Limited</p>
    </footer>
</div>

<script src="{{ asset('assets/js/master-alert.js') }}"></script>
<script>window.kycToast = @json($kycToast);</script>
<script src="{{ asset('assets/js/kyc-public.js') }}"></script>
</body>
</html>
