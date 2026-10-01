<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Submit Product Requirement - MissPack</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/leads-public-create.css') }}">
</head>
<body>
<div class="public-page">
    <div class="card lead-header">
        <div>
            <img class="brand-logo" src="{{ asset('images/misspack-logo.png') }}" alt="MissPack - Packed Perfect">
            <div class="brand-label">Public Lead Form</div>
            <h1>Submit Your Packaging Requirement</h1>
            <p>Tell us what product you need, quantity, finish, printing and ready stock requirements. Our team will review and contact you.</p>
        </div>
        <span class="lead-header-badge">🎯 Lead Enquiry</span>
    </div>

    @if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert error">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ route('leads.public.store') }}" enctype="multipart/form-data" class="card form-card" id="publicLeadForm">
        @csrf
        <input type="text" name="website_url" class="hidden-honeypot" tabindex="-1" autocomplete="off">

        <div class="section">
            <h3 class="section-title">Client Details</h3>
            <div class="grid">
                <div class="master-field">
                    <label class="master-label">Company Name <span class="required">*</span></label>
                    <input class="master-input" name="client_company_name" value="{{ old('client_company_name') }}" required placeholder="Your company name">
                </div>
                <div class="master-field">
                    <label class="master-label">Contact Person <span class="required">*</span></label>
                    <input class="master-input" name="client_contact_name" value="{{ old('client_contact_name') }}" required placeholder="Your name">
                </div>
                <div class="master-field">
                    <label class="master-label">Mobile <span class="required">*</span></label>
                    <input class="master-input" name="client_mobile" value="{{ old('client_mobile') }}" required placeholder="Mobile / WhatsApp number">
                </div>
                <div class="master-field">
                    <label class="master-label">Email</label>
                    <input class="master-input" type="email" name="client_email" value="{{ old('client_email') }}" placeholder="email@company.com">
                </div>
                <div class="master-field">
                    <label class="master-label">How did you contact us?</label>
                    <select class="master-select" name="lead_source">
                        @foreach($sourceOptions as $key => $label)
                            <option value="{{ $key }}" {{ old('lead_source', 'website') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="section">
            <h3 class="section-title">Product Requirement</h3>
            <div class="grid">
                <div class="master-field full">
                    <label class="master-label">Requirement Title <span class="required">*</span></label>
                    <input class="master-input" name="title" value="{{ old('title') }}" required placeholder="Example: Need 50ml matte bottle with one color printing">
                </div>
                <div class="master-field">
                    <label class="master-label">Product Name <span class="required">*</span></label>
                    <input class="master-input" name="product_name" value="{{ old('product_name') }}" required placeholder="Bottle / Jar / Cap / Tube">
                </div>
                <div class="master-field">
                    <label class="master-label">Product Image</label>
                    <input class="master-input" type="file" name="product_image" accept="image/*">
                    <div class="help">Upload reference product photo, if available.</div>
                </div>
                <div class="master-field">
                    <label class="master-label">Required Quantity</label>
                    <input class="master-input" type="number" name="required_quantity" value="{{ old('required_quantity') }}" min="0" placeholder="5000">
                </div>
                <div class="master-field">
                    <label class="master-label">Capacity</label>
                    <input class="master-input" type="number" step="0.001" name="capacity_value" value="{{ old('capacity_value') }}" placeholder="50">
                </div>
                <div class="master-field">
                    <label class="master-label">Capacity Unit</label>
                    <input class="master-input" name="capacity_unit" value="{{ old('capacity_unit', 'ml') }}" placeholder="ml / gm / oz">
                </div>
                <div class="master-field">
                    <label class="master-label">Quote Quantities</label>
                    <input class="master-input" name="quote_quantities_text" value="{{ old('quote_quantities_text') }}" placeholder="3000, 5000, 10000">
                    <div class="help">Comma separated quantities.</div>
                </div>
                <div class="master-field full">
                    <label class="master-label">Product Description</label>
                    <textarea class="master-textarea" name="product_description" placeholder="Describe product shape, material, usage, cap type, pump type, etc.">{{ old('product_description') }}</textarea>
                </div>
                <div class="master-field full">
                    <label class="master-label">Quantity Notes</label>
                    <textarea class="master-textarea" name="quantity_notes" placeholder="Mention yearly requirement, sample quantity, trial quantity, etc.">{{ old('quantity_notes') }}</textarea>
                </div>
            </div>
        </div>

        <div class="section">
            <h3 class="section-title">Finish, Printing & Stock</h3>
            <div class="grid">
                <div class="master-field">
                    <label class="master-label">Finish Required</label>
                    <select class="master-select" name="finish_required">
                        <option value="">Select finish</option>
                        @foreach($finishOptions as $key => $label)
                            <option value="{{ $key }}" {{ old('finish_required') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="master-field">
                    <label class="master-label">Printing Required</label>
                    <select class="master-select" name="printing_required">
                        <option value="">Select printing</option>
                        @foreach($printingOptions as $key => $label)
                            <option value="{{ $key }}" {{ old('printing_required') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <label class="master-check">
                    <input type="checkbox" name="ready_stock_required" value="1" {{ old('ready_stock_required') ? 'checked' : '' }}>
                    <span>Need ready stock options?</span>
                </label>
                <label class="master-check">
                    <input type="checkbox" name="custom_color_required" value="1" {{ old('custom_color_required') ? 'checked' : '' }}>
                    <span>Need custom color?</span>
                </label>
                <div class="master-field full">
                    <label class="master-label">Printing Details</label>
                    <textarea class="master-textarea" name="printing_details" placeholder="Example: one color printing, label, embossing, foil, etc.">{{ old('printing_details') }}</textarea>
                </div>
                <div class="master-field full">
                    <label class="master-label">Ready Stock Color Requirement</label>
                    <textarea class="master-textarea" name="ready_stock_color_requirement" placeholder="Available stock color preference, MOQ question, urgency, etc.">{{ old('ready_stock_color_requirement') }}</textarea>
                </div>
                <div class="master-field full">
                    <label class="master-label">Custom Color Specification</label>
                    <textarea class="master-textarea" name="custom_color_specification" placeholder="Pantone code / color reference / finish details.">{{ old('custom_color_specification') }}</textarea>
                </div>
            </div>
        </div>

        <div class="section">
            <h3 class="section-title">Commercial & Attachments</h3>
            <div class="grid">
                <div class="master-field">
                    <label class="master-label">Target Price</label>
                    <input class="master-input" type="number" step="0.01" name="target_price" value="{{ old('target_price') }}" placeholder="Optional target price">
                </div>
                <div class="master-field">
                    <label class="master-label">Target Currency</label>
                    <select class="master-select" name="target_currency">
                        @foreach($currencyOptions as $key => $label)
                            <option value="{{ $key }}" {{ old('target_currency', 'INR') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="master-field">
                    <label class="master-label">Required Delivery Date</label>
                    <input class="master-input" type="date" name="required_delivery_date" value="{{ old('required_delivery_date') }}">
                </div>
                <div class="master-field full">
                    <label class="master-label">Additional Requirement Notes</label>
                    <textarea class="master-textarea" name="sales_notes" placeholder="Mention photo/video needed, price for matte/glossy, MOQ, available colors, delivery urgency, etc.">{{ old('sales_notes') }}</textarea>
                </div>
                <div class="master-field full">
                    <label class="master-label">Upload Attachments</label>
                    <input class="master-input" type="file" name="attachments[]" multiple>
                    <div class="help">Upload photos, videos or documents. Max 20MB each.</div>
                </div>
                <div class="master-field full">
                    <label class="master-label">External Photo / Video Links</label>
                    <textarea class="master-textarea" name="attachment_links" placeholder="Paste one URL per line">{{ old('attachment_links') }}</textarea>
                </div>
            </div>
        </div>

        <div class="actions">
            <button type="submit" class="master-btn master-btn-primary" id="submitPublicLead">Submit Requirement</button>
            <button type="reset" class="master-btn master-btn-light">Reset Form</button>
        </div>
    </form>

    <footer class="card footer">
        <div class="footer-top">
            <div class="footer-brand">
                <img src="{{ asset('images/misspack-logo.png') }}" alt="MissPack - Packed Perfect">
                <p>MissPack helps brands source, develop and manage packaging with reliable vendor coordination and transparent documentation.</p>
            </div>
            <div class="footer-col">
                <h4>Contact Details</h4>
                <div class="footer-list">
                    <a href="mailto:admin@misspack.com">✉ admin@misspack.com</a>
                    <a href="tel:+917048110823">☎ +91 70481 10823</a>
                    <a href="https://www.misspack.com" target="_blank" rel="noopener">🌐 www.misspack.com</a>
                </div>
            </div>
            <div class="footer-col">
                <h4>Address</h4>
                <p>MissPack<br>Ahmedabad, Gujarat, India</p>
                <p style="margin-top:10px;">Our sales team will contact you after reviewing your requirement.</p>
            </div>
        </div>
        <div class="footer-bottom">
            <span>© {{ date('Y') }} MissPack. All rights reserved.</span>
            <span>Packed Perfect</span>
        </div>
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    window.leadPublicFlash = @json([
        'success' => session('success'),
        'error' => $errors->first() ?: null,
    ]);
</script>
<script src="{{ asset('assets/js/leads-public.js') }}"></script>
</body>
</html>
