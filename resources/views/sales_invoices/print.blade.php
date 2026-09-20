<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $invoice->invoice_number }} - {{ $invoice->typeLabel() }}</title>
    <style>
        /* =========================================================
           MISSPACK INVOICE
           PRINT-FIRST A4 DESIGN
           ========================================================= */
    
        :root {
            --pink: #2f3a4c;
            --pink-dark: #d93660;
            --pink-soft: #fff1f5;
    
            --purple: #2f3a4c;
            --purple-dark: #6043c4;
            --purple-soft: #f4f1ff;
    
            --teal: #19a995;
            --teal-soft: #edfaf8;
    
            --blue-soft: #f1f7ff;
    
            --dark: #202033;
            --text: #414252;
            --muted: #74768a;
    
            --border: #dedde7;
            --border-light: #eceaf1;
    
            --background: #f3f1f6;
            --white: #ffffff;
    
            --font: Arial, Helvetica, sans-serif;
        }
    
        /* =========================================================
           RESET
           ========================================================= */
    
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }
    
        html,
        body {
            margin: 0;
            padding: 0;
        }
    
        body {
            background: var(--background);
            color: var(--text);
            font-family: var(--font);
            font-size: 11px;
            line-height: 1.4;
    
            /*
             * CRITICAL:
             * Preserve colors when Chrome prints/PDFs.
             */
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color-adjust: exact !important;
        }
    
        /* =========================================================
           TOOLBAR
           ========================================================= */
    
        .toolbar {
            width: 210mm;
            margin: 15px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }
    
        .btn {
            appearance: none;
            border: 0;
            border-radius: 7px;
    
            background: var(--purple);
            color: #fff;
    
            padding: 9px 15px;
    
            font-family: var(--font);
            font-size: 11px;
            font-weight: 700;
    
            text-decoration: none;
            cursor: pointer;
    
            box-shadow: 0 3px 10px rgba(118, 87, 217, .18);
        }
    
        .btn:hover {
            background: var(--purple-dark);
        }
    
        /* =========================================================
           A4 PAGE
           ========================================================= */
    
        .page {
            width: 210mm;
            min-height: 297mm;
    
            margin: 0 auto 20px;
            padding: 9mm;
    
            background: #fff;
    
            border: 1px solid var(--border);
            border-radius: 8px;
    
            box-shadow: 0 12px 35px rgba(30, 25, 50, .12);
    
            position: relative;
    
            overflow: hidden;
    
            /*
             * Prevent browser from creating strange layout
             * differences between screen and print.
             */
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
    
        /* Top brand strip */
        .page::before {
            content: "";
            display: block;
    
            position: absolute;
    
            top: 0;
            left: 0;
            right: 0;
    
            height: 5px;
    
            background: var(--pink);
        }
    
        /* =========================================================
           HEADER
           ========================================================= */
    
        .top {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 155px;
    
            gap: 18px;
    
            padding: 3px 0 11px;
    
            border-bottom: 1px solid var(--border);
        }
    
        .brand {
            min-width: 0;
        }
    
        .brand-logo {
            display: block;
            height: 48px;
            width: auto;
    
            object-fit: contain;
            object-position: left center;
        }
    
        .brand h1 {
            margin: 0;
    
            color: var(--dark);
    
            font-size: 25px;
            line-height: 1.1;
            font-weight: 800;
        }
    
        .brand p {
            margin: 4px 0;
    
            color: #2f3a4c;
    
            font-size: 10px;
            line-height: 1.45;
        }
    
        .brand p:first-of-type {
            margin-top: 6px;
    
            color: #2f3a4c;
    
            font-size: 14px;
            font-weight: 700;
        }
    
        /* =========================================================
           INVOICE TITLE
           ========================================================= */
    
        .title {
            text-align: right;
            align-self: start;
        }
    
        .title h2 {
            margin: 0;
    
            color: #2f3a4c;
    
            font-size: 19px !important;
            line-height: 1.15;
    
            font-weight: 800;
    
            text-transform: uppercase;
            letter-spacing: .5px;
        }
    
        .title strong {
            display: inline-block;
    
            margin-top: 7px;
            padding: 5px 9px;
    
            background: var(--pink-soft);
            color: var(--pink-dark);
    
            border: 1px solid #f8d5df;
            border-radius: 6px;
    
            font-size: 12px;
            font-weight: 800;
        }
    
        .title p {
            display: inline-block;
    
            margin: 7px 0 0;
            padding: 4px 9px;
    
            background: var(--teal-soft);
            color: #087f72;
    
            border-radius: 12px;
    
            font-size: 9px;
            font-weight: 700;
    
            text-transform: uppercase;
            letter-spacing: .3px;
        }
    
        /* =========================================================
           INVOICE META
           ========================================================= */
    
        .info-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
    
            margin-top: 10px;
    
            border: 1px solid var(--border);
            border-radius: 7px;
    
            overflow: hidden;
        }
    
        .info-grid div {
            min-width: 0;
    
            padding: 7px 8px;
    
            border-right: 1px solid var(--border);
        }
    
        .info-grid div:last-child {
            border-right: 0;
        }
    
        .info-grid div:nth-child(1) {
            background: var(--pink-soft);
        }
    
        .info-grid div:nth-child(2) {
            background: var(--purple-soft);
        }
    
        .info-grid div:nth-child(3) {
            background: var(--blue-soft);
        }
    
        .info-grid div:nth-child(4) {
            background: var(--teal-soft);
        }
    
        .info-grid span {
            display: block;
    
            margin-bottom: 2px;
    
            color: var(--muted);
    
            font-size: 8px;
            font-weight: 700;
    
            text-transform: uppercase;
            letter-spacing: .5px;
        }
    
        .info-grid strong {
            display: block;
    
            color: var(--dark);
    
            font-size: 10px;
            font-weight: 700;
        }
    
        /* =========================================================
           BILL TO / SHIP TO
           ========================================================= */
    
        .meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
    
            gap: 10px;
    
            margin-top: 10px;
        }
    
        .box {
            min-width: 0;
    
            padding: 9px;
    
            background: #fff;
    
            border: 1px solid var(--border);
            border-radius: 7px;
    
            box-shadow: none;
        }
    
        .box h3 {
            margin: -9px -9px 8px;
    
            padding: 6px 9px;
    
            background: var(--purple);
    
            color: #fff;
    
            border-radius: 6px 6px 0 0;
    
            font-size: 9px;
            font-weight: 700;
    
            text-transform: uppercase;
            letter-spacing: .6px;
        }
    
        .box strong {
            color: var(--dark);
            font-weight: 700;
        }
    
        /* =========================================================
           ITEMS TABLE
           ========================================================= */
    
        .items {
            width: 100%;
    
            margin-top: 11px;
    
            border-collapse: separate;
            border-spacing: 0;
    
            border: 1px solid var(--border);
            border-radius: 7px;
    
            overflow: hidden;
    
            table-layout: fixed;
        }
    
        .items th,
        .items td {
            padding: 6px 7px;
    
            border: 0;
            border-bottom: 1px solid var(--border-light);
    
            vertical-align: top;
    
            word-wrap: break-word;
            overflow-wrap: anywhere;
        }
    
        .items th {
            background: #f4f1f8;
    
            color: var(--dark);
    
            border-bottom: 2px solid #ded7e8;
    
            font-size: 8px;
            font-weight: 800;
    
            text-transform: uppercase;
            letter-spacing: .4px;
    
            text-align: left;
        }
    
        .items th:first-child {
            border-radius: 6px 0 0 0;
        }
    
        .items th:last-child {
            border-radius: 0 6px 0 0;
        }
    
        .items tbody tr:nth-child(even) td {
            background: #fcfbfd;
        }
    
        .items tbody tr:last-child td {
            border-bottom: 0;
        }
    
        .items td {
            font-size: 10px;
            color: var(--text);
        }
    
        .items td strong {
            color: var(--dark);
            font-size: 10px;
        }
    
        .items td em {
            color: var(--muted);
            font-size: 9px;
        }
    
        .right {
            text-align: right !important;
        }
    
        .center {
            text-align: center !important;
        }
    
        /* =========================================================
           TOTALS SECTION
           ========================================================= */
    
        .totals {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    
            gap: 10px;
    
            margin-top: 10px;
        }
    
        /* =========================================================
           AMOUNT IN WORDS
           ========================================================= */
    
        .amount-words {
            min-height: 52px;
    
            padding: 9px;
    
            background: #faf8fc;
    
            border: 1px solid var(--border);
            border-left: 4px solid var(--pink);
    
            border-radius: 6px;
    
            color: var(--dark);
    
            font-size: 10px;
            font-weight: 700;
    
            line-height: 1.5;
        }
    
        .amount-words span {
            color: var(--muted) !important;
            font-weight: 500;
        }
    
        /* =========================================================
           SUMMARY TABLE
           ========================================================= */
    
        .summary {
            border: 1px solid var(--border);
            border-radius: 7px;
    
            overflow: hidden;
        }
    
        .summary table {
            width: 100%;
    
            border-collapse: collapse;
        }
    
        .summary td {
            padding: 5px 8px;
    
            border-bottom: 1px solid var(--border-light);
    
            font-size: 9px;
        }
    
        .summary tr:last-child td {
            border-bottom: 0;
        }
    
        .summary td:first-child {
            color: var(--text);
        }
    
        .summary td:last-child {
            text-align: right;
    
            color: var(--dark);
    
            font-weight: 600;
            white-space: nowrap;
        }
    
        .summary tr:last-child td {
            padding: 8px;
    
            background: var(--purple);
    
            color: #fff;
    
            font-size: 11px;
            font-weight: 800;
        }
    
        .summary tr:last-child td:first-child {
            color: #fff;
        }
    
        /* =========================================================
           BANK + SIGNATURE
           ========================================================= */
    
        .bank-sign {
            display: grid;
            grid-template-columns: 1fr 1fr;
    
            gap: 10px;
    
            margin-top: 10px;
        }
    
        .bank-sign .box {
            background: #faf9fd;
        }
    
        .sign {
            min-height: 105px;
    
            padding: 9px;
    
            background: #fff8fa;
    
            border: 1px solid #efdce3;
            border-radius: 7px;
    
            text-align: right;
    
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: stretch;
        }
    
        .sign strong {
            color: var(--purple);
    
            font-size: 10px;
        }
    
        .sign .brand-logo {
            height: 55px;
            width: auto;
    
            margin-left: auto;
            margin-right: 0;
    
            object-position: right center;
        }
    
        .sign span {
            color: var(--muted);
    
            font-size: 9px;
            font-weight: 700;
        }
    
        /* =========================================================
           FOOTER
           ========================================================= */
    
        .footer-note {
            margin-top: 9px;
            padding-top: 7px;
    
            border-top: 1px dashed var(--border);
    
            text-align: center;
    
            color: var(--muted);
    
            font-size: 8.5px;
        }
    
        /* =========================================================
           PRINT
           ========================================================= */
    
        @media print {
    
            html,
            body {
                width: 210mm;
                min-height: 297mm;
    
                margin: 0;
                padding: 0;
    
                background: #fff !important;
    
                /*
                 * Force browser to print backgrounds.
                 */
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
    
            .toolbar {
                display: none !important;
            }
    
            .page {
                width: 210mm !important;
                min-height: 297mm !important;
    
                margin: 0 !important;
    
                padding: 8mm !important;
    
                border: 0 !important;
                border-radius: 0 !important;
    
                box-shadow: none !important;
    
                background: #fff !important;
    
                overflow: visible !important;
    
                /*
                 * Preserve print colors.
                 */
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
    
            .page::before {
                display: block !important;
    
                background: #2f3a4c !important;
    
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
    
            /*
             * Explicit print backgrounds.
             * Avoid relying on gradients.
             */
    
            .info-grid div:nth-child(1) {
                background: #fff1f5 !important;
            }
    
            .info-grid div:nth-child(2) {
                background: #f4f1ff !important;
            }
    
            .info-grid div:nth-child(3) {
                background: #f1f7ff !important;
            }
    
            .info-grid div:nth-child(4) {
                background: #edfaf8 !important;
            }
    
            .box h3 {
                background: #2f3a4c !important;
                color: #fff !important;
            }
    
            .items th {
                background: #f4f1f8 !important;
                color: #202033 !important;
            }
    
            .items tbody tr:nth-child(even) td {
                background: #fcfbfd !important;
            }
    
            .summary tr:last-child td {
                background: #2f3a4c !important;
                color: #fff !important;
            }
    
            .amount-words {
                background: #faf8fc !important;
            }
    
            .sign {
                background: #fff8fa !important;
            }
    
            .bank-sign .box {
                background: #faf9fd !important;
            }
    
            .title strong {
                background: #fff1f5 !important;
                color: #d93660 !important;
            }
    
            .title p {
                background: #edfaf8 !important;
                color: #087f72 !important;
            }
    
            /*
             * Keep important blocks together.
             */
    
            .top,
            .info-grid,
            .meta,
            .totals,
            .bank-sign,
            .amount-words,
            .summary {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
    
            /*
             * Never split an individual invoice item.
             */
    
            .items tr {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
    
            /*
             * Avoid awkward page breaks.
             */
    
            .items {
                page-break-before: auto;
                page-break-after: auto;
            }
    
            /*
             * Keep table headers with the table.
             */
    
            .items thead {
                display: table-header-group;
            }
    
            /*
             * Don't show hover effects in print.
             */
    
            .items tbody tr:hover td {
                background: inherit !important;
            }
    
            /*
             * Links should look like normal invoice text.
             */
    
            a {
                color: inherit !important;
                text-decoration: none !important;
            }
    
            /*
             * Ensure borders remain visible.
             */
    
            .box,
            .items,
            .summary,
            .info-grid,
            .amount-words,
            .sign {
                border-color: #dedde7 !important;
            }
    
            @page {
                size: A4 portrait;
                margin: 0;
            }
        }
    
        /* =========================================================
           SMALL SCREEN
           ========================================================= */
    
        @media screen and (max-width: 900px) {
    
            body {
                background: #fff;
            }
    
            .toolbar {
                width: auto;
                margin: 10px;
            }
    
            .page {
                width: 100%;
                min-height: auto;
    
                margin: 0;
                padding: 20px 15px;
    
                border-radius: 0;
                box-shadow: none;
            }
    
            .top {
                grid-template-columns: 1fr;
            }
    
            .title {
                text-align: left;
            }
    
            .info-grid {
                grid-template-columns: 1fr 1fr;
            }
    
            .info-grid div:nth-child(2) {
                border-right: 0;
            }
    
            .meta,
            .totals,
            .bank-sign {
                grid-template-columns: 1fr;
            }
    
            .items {
                font-size: 10px;
            }
        }
    </style>
</head>

<body>
    <div class="toolbar">
        @if (!$publicMode)
        <a class="btn" href="{{ route('sales-invoices.show', $invoice) }}">Back</a>@else<span></span>
        @endif
        <button class="btn" onclick="window.print()">
            Print Invoice</button>
    </div>
    <div class="page">
        <div class="top">
            <div class="brand">
                <img class="brand-logo" src="{{ asset('images/logo-dark.png') }}" height="50"
                    alt="MissPack - Packed Perfect">
                <p style="font-size:16px;font-weight:600;">{{ 'MissPack India Pvt Ltd' }}</p>
                <p>{{ $invoice->seller_address }}<br>{{ $invoice->seller_city }}, {{ $invoice->seller_state }},
                    {{ $invoice->seller_country }} - {{ $invoice->seller_pincode }}</p>
                <p>GSTIN: {{ $invoice->seller_gstin ?: '-' }} | PAN: {{ $invoice->seller_pan ?: '-' }}<br>Email:
                    {{ $invoice->seller_email ?: '-' }} | Mobile: {{ $invoice->seller_mobile ?: '-' }}</p>
            </div>
            <div class="title">
                <h2 style="font-size:19px">{{ $invoice->typeLabel() }}</h2><strong>{{ $invoice->invoice_number }}</strong>
                <p>Status: {{ $invoice->statusLabel() }}</p>
            </div>
        </div>
        <div class="info-grid">
            <div><span>Invoice
                    Date</span><strong>{{ optional($invoice->invoice_date)->format('d M Y') ?: '-' }}</strong></div>
            <div><span>Valid</span><strong>{{ optional($invoice->valid_until)->format('d M Y') ?: '-' }}</strong></div>
            <div><span>PO Number</span><strong>{{ $invoice->po_number ?: '-' }}</strong></div>
            <div><span>Place of Supply</span><strong>{{ $invoice->place_of_supply ?: '-' }}</strong></div>
        </div>
        <div class="meta">
            <div class="box">
                <h3>Bill To</h3>
                <strong>{{ $invoice->client_company_name }}</strong><br>{{ $invoice->billing_address }}<br>{{ $invoice->billing_city }},
                {{ $invoice->billing_state }}, {{ $invoice->billing_country }} -
                {{ $invoice->billing_pincode }}<br>GSTIN: {{ $invoice->client_gstin ?: '-' }} | PAN:
                {{ $invoice->client_pan ?: '-' }}<br>Contact: {{ $invoice->client_contact_name ?: '-' }}
                {{ $invoice->client_mobile ?: '' }}
            </div>
            <div class="box">
                <h3>Ship To</h3>
                <strong>{{ $invoice->client_company_name }}</strong><br>
                {{ $invoice->shipping_address }}<br>{{ $invoice->shipping_city }},
                {{ $invoice->shipping_state }}, {{ $invoice->shipping_country }} -
                {{ $invoice->shipping_pincode }}<br>Email: {{ $invoice->client_email ?: '-' }}
            </div>
        </div>
        <table class="items">
            <thead>
                <tr>
                    <th width="3%">#</th>
                    <th width="47%">Description</th>
                    <th width="16%">Qty</th>
                    <th width="16%">Rate</th>
                    <th width="16%">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->items as $item)
                    <tr>
                        <td class="center">{{ $loop->iteration }}</td>
                        <td><strong>{{ $item->product_name }}</strong><br>{!! nl2br(e($item->description)) !!}@if ($item->remarks)
                                <br><em>{!! nl2br(e($item->remarks)) !!}</em>
                            @endif<br>
                            <em>HS Code: {{ $item->hsn_sac ?: '-' }}</em>
                        </td>
                        <td class="center">{{ number_format($item->quantity, 0) }} {{ $item->unit }}</td>
                        <td class="center">{{ \App\Helpers\CommonHelper::indianCurrency($item->unit_price) }}</td>
                        <td class="right">{{ \App\Helpers\CommonHelper::indianCurrency($item->taxable_amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="totals">
            <div>
                <!--<div class="terms"><strong>Terms & Conditions</strong><br>{{ $invoice->terms_conditions }}</div>-->
                <div class="amount-words">Amount in Words: <span style="color:grey">{{ $invoice->amount_in_words }}</span></div>
            </div>
            <div class="summary">
                <table>
                    <tr>
                        <td>Subtotal</td>
                        <td>{{ \App\Helpers\CommonHelper::indianCurrency($invoice->subtotal) }}</td>
                    </tr>
                    <tr>
                        <td>Discount</td>
                        <td>- {{ \App\Helpers\CommonHelper::indianCurrency((float) $invoice->discount_amount) }}</td>
                    </tr>
                    <tr>
                        <td>Taxable</td>
                        <td>{{ \App\Helpers\CommonHelper::indianCurrency((float) $invoice->taxable_amount) }}</td>
                    </tr>
                    <tr>
                        <td>Tax</td>
                        <td>{{ \App\Helpers\CommonHelper::indianCurrency((float) $invoice->cgst_amount + $invoice->sgst_amount + $invoice->igst_amount) }}</td>
                    </tr>
                    <tr>
                        <td>Freight/Packing/Other</td>
                        <td>{{ \App\Helpers\CommonHelper::indianCurrency((float) $invoice->freight_amount + (float) $invoice->packing_amount + (float) $invoice->other_charges) }}
                        </td>
                    </tr>
                    <tr>
                        <td>Round Off</td>
                        <td>{{ \App\Helpers\CommonHelper::indianCurrency($invoice->round_off) }}</td>
                    </tr>
                    <tr>
                        <td><strong>Grand Total</strong></td>
                        <td><strong>{{ \App\Helpers\CommonHelper::indianCurrency( $invoice->total_amount) }}</strong></td>
                    </tr>
                </table>
            </div>
        </div>
        <div class="bank-sign">
            <div class="box">
                <h3>Bank Details</h3>Bank: {{ $invoice->seller_bank_name ?: '-' }}<br>A/C Holder:
                {{ $invoice->seller_account_holder ?: '-' }}<br>A/C No.:
                {{ $invoice->seller_account_number ?: '-' }}<br>IFSC: {{ $invoice->seller_ifsc ?: '-' }}
                <br>Branch: {{ $invoice->seller_branch ?: '-' }}
            </div>
            <div class="sign">
                <strong>For {{ $invoice->seller_company_name ?: 'MissPack India Pvt Ltd' }}</strong>
                <span>Authorised Signatory</span>
            </div>
        </div>
        <div class="footer-note">This is a computer-generated invoice. Please verify all details before payment.</div>
    </div>
</body>

</html>
