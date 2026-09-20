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
    <style>
        :root {
            --primary: #4f83f1;
            --primary2: #6366f1;
            --green: #10b981;
            --orange: #f59e0b;
            --red: #ef4770;
            --dark: #17233b;
            --muted: #687386;
            --border: #dfe7f3;
            --bg: #eef3ff;
            --soft: #edf5ff;
            --white: #ffffff;
            --shadow: 0 14px 35px rgba(25, 42, 70, .08);
        }

        * { box-sizing: border-box; }

        html,
        body {
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
        }

        body {
            margin: 0;
            background: var(--bg);
            font-family: "Inter", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: var(--dark);
            font-size: 14px;
            line-height: 1.45;
        }

        .public-page {
            width: 100%;
            max-width: 1180px;
            margin: 0 auto;
            padding: 28px;
        }

        .card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: var(--shadow);
        }

        .lead-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 22px;
            padding: 28px;
            margin-bottom: 22px;
            overflow: hidden;
        }

        .brand-logo {
            height: 62px;
            width: auto;
            max-width: 230px;
            object-fit: contain;
            display: block;
            margin-bottom: 10px;
        }

        .brand-label {
            color: var(--primary);
            font-size: 12px;
            font-weight: 900;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .lead-header h1 {
            margin: 8px 0 6px;
            font-size: 28px;
            line-height: 1.18;
            font-weight: 900;
            letter-spacing: -.03em;
        }

        .lead-header p {
            margin: 0;
            color: var(--muted);
            font-weight: 700;
            max-width: 720px;
        }

        .lead-header-badge {
            flex: 0 0 auto;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 999px;
            padding: 10px 14px;
            background: var(--soft);
            color: var(--primary);
            font-weight: 900;
            white-space: nowrap;
        }

        .form-card {
            padding: 26px;
        }

        .section {
            margin-bottom: 28px;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0 0 16px;
            color: #7d8aa0;
            font-size: 12px;
            font-weight: 900;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .section-title::after {
            content: "";
            height: 1px;
            background: var(--border);
            flex: 1;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }

        .field {
            min-width: 0;
        }

        .field.full { grid-column: 1 / -1; }
        .field.two { grid-column: span 2; }

        .label {
            display: block;
            margin-bottom: 7px;
            color: #536079;
            font-size: 12px;
            font-weight: 900;
        }

        .required { color: var(--red); }

        .input,
        .select,
        .textarea {
            width: 100%;
            max-width: 100%;
            min-width: 0;
            border: 1px solid #d8e2ef;
            border-radius: 12px;
            background: #fff;
            color: var(--dark);
            font-size: 14px;
            font-weight: 600;
            outline: none;
            transition: .2s ease;
        }

        .input,
        .select {
            height: 44px;
            padding: 10px 14px;
        }

        .textarea {
            min-height: 110px;
            padding: 12px 14px;
            resize: vertical;
            overflow-wrap: anywhere;
        }

        .input:focus,
        .select:focus,
        .textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 131, 241, .12);
        }

        .help {
            margin-top: 6px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
        }

        .checkbox-row {
            display: flex;
            align-items: flex-start;
            gap: 9px;
            min-height: 44px;
            padding-top: 28px;
            color: #536079;
            font-weight: 800;
            line-height: 1.45;
        }

        .checkbox-row input {
            margin-top: 3px;
        }

        .hidden-honeypot {
            position: absolute;
            left: -9999px;
            opacity: 0;
            pointer-events: none;
        }

        .actions {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 28px;
        }

        .btn {
            min-height: 46px;
            border: 0;
            border-radius: 13px;
            padding: 12px 24px;
            font-size: 14px;
            font-weight: 900;
            text-decoration: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: .2s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary2));
            color: #fff;
        }

        .btn-primary:hover {
            filter: brightness(.98);
            transform: translateY(-1px);
            box-shadow: 0 12px 26px rgba(79, 131, 241, .24);
        }

        .btn-light {
            background: #f3f6fb;
            color: var(--dark);
        }

        .alert {
            padding: 14px 16px;
            margin-bottom: 18px;
            border-radius: 14px;
            font-weight: 800;
        }

        .alert.success { color: #047857; background: #e8fff7; border: 1px solid #a7f3d0; }
        .alert.error { color: #be123c; background: #fff0f4; border: 1px solid #fecdd3; }

        .footer {
            margin-top: 24px;
            overflow: hidden;
        }

        .footer-top {
            display: grid;
            grid-template-columns: 1.35fr 1fr 1fr;
            gap: 22px;
            padding: 26px;
            background: #fff;
        }

        .footer-brand img {
            height: 54px;
            width: auto;
            max-width: 220px;
            object-fit: contain;
            display: block;
            margin-bottom: 12px;
        }

        .footer-brand p,
        .footer-col p {
            margin: 0;
            color: var(--muted);
            font-weight: 700;
            line-height: 1.65;
        }

        .footer-col h4 {
            margin: 0 0 12px;
            font-size: 14px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .footer-list {
            display: grid;
            gap: 9px;
        }

        .footer-list a,
        .footer-list span {
            color: #536079;
            text-decoration: none;
            font-weight: 800;
            overflow-wrap: anywhere;
        }

        .footer-list a:hover { color: var(--primary); }

        .footer-bottom {
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
            font-weight: 800;
        }

        @media (max-width: 1024px) {
            .public-page { padding: 20px; }
            .lead-header { align-items: flex-start; padding: 22px; }
            .form-card { padding: 22px; }
            .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
            .field.full { grid-column: 1 / -1; }
            .footer-top { grid-template-columns: 1fr 1fr; }
            .footer-brand { grid-column: 1 / -1; }
        }

        @media (max-width: 768px) {
            .public-page { padding: 14px; }
            .lead-header { flex-direction: column; align-items: flex-start; padding: 20px; }
            .brand-logo { height: 52px; max-width: 210px; }
            .lead-header h1 { font-size: 22px; }
            .lead-header-badge { align-self: flex-start; }
            .form-card { padding: 18px; }
            .grid { grid-template-columns: 1fr; gap: 14px; }
            .field,
            .field.full,
            .field.two { grid-column: 1 / -1; }
            .section-title { align-items: flex-start; gap: 8px; }
            .section-title::after { margin-top: 8px; }
            .checkbox-row { padding-top: 0; }
            .actions .btn { width: 100%; max-width: 340px; }
            .footer-top { grid-template-columns: 1fr; padding: 20px; text-align: center; }
            .footer-brand img { margin-left: auto; margin-right: auto; }
            .footer-bottom { flex-direction: column; text-align: center; justify-content: center; }
        }

        @media (max-width: 480px) {
            body { font-size: 13px; }
            .public-page { padding: 10px; }
            .lead-header { padding: 16px; }
            .brand-logo { height: 44px; max-width: 185px; }
            .lead-header h1 { font-size: 20px; }
            .form-card { padding: 14px; }
            .input, .select { height: 42px; padding: 9px 12px; }
            .textarea { min-height: 92px; padding: 10px 12px; }
        }
    </style>
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
                <div class="field">
                    <label class="label">Company Name <span class="required">*</span></label>
                    <input class="input" name="client_company_name" value="{{ old('client_company_name') }}" required placeholder="Your company name">
                </div>
                <div class="field">
                    <label class="label">Contact Person <span class="required">*</span></label>
                    <input class="input" name="client_contact_name" value="{{ old('client_contact_name') }}" required placeholder="Your name">
                </div>
                <div class="field">
                    <label class="label">Mobile <span class="required">*</span></label>
                    <input class="input" name="client_mobile" value="{{ old('client_mobile') }}" required placeholder="Mobile / WhatsApp number">
                </div>
                <div class="field">
                    <label class="label">Email</label>
                    <input class="input" type="email" name="client_email" value="{{ old('client_email') }}" placeholder="email@company.com">
                </div>
                <div class="field">
                    <label class="label">How did you contact us?</label>
                    <select class="select" name="lead_source">
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
                <div class="field full">
                    <label class="label">Requirement Title <span class="required">*</span></label>
                    <input class="input" name="title" value="{{ old('title') }}" required placeholder="Example: Need 50ml matte bottle with one color printing">
                </div>
                <div class="field">
                    <label class="label">Product Name <span class="required">*</span></label>
                    <input class="input" name="product_name" value="{{ old('product_name') }}" required placeholder="Bottle / Jar / Cap / Tube">
                </div>
                <div class="field">
                    <label class="label">Product Image</label>
                    <input class="input" type="file" name="product_image" accept="image/*">
                    <div class="help">Upload reference product photo, if available.</div>
                </div>
                <div class="field">
                    <label class="label">Required Quantity</label>
                    <input class="input" type="number" name="required_quantity" value="{{ old('required_quantity') }}" min="0" placeholder="5000">
                </div>
                <div class="field">
                    <label class="label">Capacity</label>
                    <input class="input" type="number" step="0.001" name="capacity_value" value="{{ old('capacity_value') }}" placeholder="50">
                </div>
                <div class="field">
                    <label class="label">Capacity Unit</label>
                    <input class="input" name="capacity_unit" value="{{ old('capacity_unit', 'ml') }}" placeholder="ml / gm / oz">
                </div>
                <div class="field">
                    <label class="label">Quote Quantities</label>
                    <input class="input" name="quote_quantities_text" value="{{ old('quote_quantities_text') }}" placeholder="3000, 5000, 10000">
                    <div class="help">Comma separated quantities.</div>
                </div>
                <div class="field full">
                    <label class="label">Product Description</label>
                    <textarea class="textarea" name="product_description" placeholder="Describe product shape, material, usage, cap type, pump type, etc.">{{ old('product_description') }}</textarea>
                </div>
                <div class="field full">
                    <label class="label">Quantity Notes</label>
                    <textarea class="textarea" name="quantity_notes" placeholder="Mention yearly requirement, sample quantity, trial quantity, etc.">{{ old('quantity_notes') }}</textarea>
                </div>
            </div>
        </div>

        <div class="section">
            <h3 class="section-title">Finish, Printing & Stock</h3>
            <div class="grid">
                <div class="field">
                    <label class="label">Finish Required</label>
                    <select class="select" name="finish_required">
                        <option value="">Select finish</option>
                        @foreach($finishOptions as $key => $label)
                            <option value="{{ $key }}" {{ old('finish_required') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="label">Printing Required</label>
                    <select class="select" name="printing_required">
                        <option value="">Select printing</option>
                        @foreach($printingOptions as $key => $label)
                            <option value="{{ $key }}" {{ old('printing_required') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <label class="checkbox-row">
                    <input type="checkbox" name="ready_stock_required" value="1" {{ old('ready_stock_required') ? 'checked' : '' }}>
                    <span>Need ready stock options?</span>
                </label>
                <label class="checkbox-row">
                    <input type="checkbox" name="custom_color_required" value="1" {{ old('custom_color_required') ? 'checked' : '' }}>
                    <span>Need custom color?</span>
                </label>
                <div class="field full">
                    <label class="label">Printing Details</label>
                    <textarea class="textarea" name="printing_details" placeholder="Example: one color printing, label, embossing, foil, etc.">{{ old('printing_details') }}</textarea>
                </div>
                <div class="field full">
                    <label class="label">Ready Stock Color Requirement</label>
                    <textarea class="textarea" name="ready_stock_color_requirement" placeholder="Available stock color preference, MOQ question, urgency, etc.">{{ old('ready_stock_color_requirement') }}</textarea>
                </div>
                <div class="field full">
                    <label class="label">Custom Color Specification</label>
                    <textarea class="textarea" name="custom_color_specification" placeholder="Pantone code / color reference / finish details.">{{ old('custom_color_specification') }}</textarea>
                </div>
            </div>
        </div>

        <div class="section">
            <h3 class="section-title">Commercial & Attachments</h3>
            <div class="grid">
                <div class="field">
                    <label class="label">Target Price</label>
                    <input class="input" type="number" step="0.01" name="target_price" value="{{ old('target_price') }}" placeholder="Optional target price">
                </div>
                <div class="field">
                    <label class="label">Target Currency</label>
                    <select class="select" name="target_currency">
                        @foreach($currencyOptions as $key => $label)
                            <option value="{{ $key }}" {{ old('target_currency', 'INR') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="label">Required Delivery Date</label>
                    <input class="input" type="date" name="required_delivery_date" value="{{ old('required_delivery_date') }}">
                </div>
                <div class="field full">
                    <label class="label">Additional Requirement Notes</label>
                    <textarea class="textarea" name="sales_notes" placeholder="Mention photo/video needed, price for matte/glossy, MOQ, available colors, delivery urgency, etc.">{{ old('sales_notes') }}</textarea>
                </div>
                <div class="field full">
                    <label class="label">Upload Attachments</label>
                    <input class="input" type="file" name="attachments[]" multiple>
                    <div class="help">Upload photos, videos or documents. Max 20MB each.</div>
                </div>
                <div class="field full">
                    <label class="label">External Photo / Video Links</label>
                    <textarea class="textarea" name="attachment_links" placeholder="Paste one URL per line">{{ old('attachment_links') }}</textarea>
                </div>
            </div>
        </div>

        <div class="actions">
            <button type="submit" class="btn btn-primary" id="submitPublicLead">Submit Requirement</button>
            <button type="reset" class="btn btn-light">Reset Form</button>
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
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('publicLeadForm');

        @if(session('success'))
            Swal.fire({
                icon: 'success',
                title: 'Requirement Submitted',
                text: @json(session('success')),
                confirmButtonColor: '#4f83f1'
            });
        @endif

        @if($errors->any())
            Swal.fire({
                icon: 'error',
                title: 'Please check the form',
                text: @json($errors->first()),
                confirmButtonColor: '#ef4770'
            });
        @endif

        if (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();

                Swal.fire({
                    title: 'Submit requirement?',
                    text: 'Please confirm that your product requirement details are correct.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Submit',
                    cancelButtonText: 'Review Again',
                    confirmButtonColor: '#4f83f1',
                    cancelButtonColor: '#ef4770',
                    reverseButtons: true
                }).then(function (result) {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        }
    });
</script>
</body>
</html>
