<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <title>{{ $invoice->invoice_number }} - {{ $invoice->typeLabel() }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/sales-invoices-print.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/document-print.css') }}">
</head>

@php
    $currency = (string) ($invoice->currency ?: 'INR');
    $money = fn ($amount) => \App\Helpers\CommonHelper::amount((float) $amount, $currency);
    $invoiceItems = $invoice->items->values();
    $lineDiscountTotal = round((float) $invoiceItems->sum('discount_amount'), 2);

    /* Item tax values are saved before the invoice-wide discount. Use a
       largest-remainder allocation of the final header amounts in cents, so
       printed taxable/tax rows reconcile; the row discount includes its
       allocated share of the invoice-wide discount as well. */
    $allocateCents = static function (float $target, array $weights): array {
        $weights = array_map(static fn ($weight) => max((float) $weight, 0), array_values($weights));
        $count = count($weights);

        if ($count === 0) {
            return [];
        }

        $targetCents = max(0, (int) round($target * 100));
        if ($targetCents === 0) {
            return array_fill(0, $count, 0.0);
        }

        $weightTotal = array_sum($weights);
        if ($weightTotal <= 0) {
            return array_fill(0, $count, 0.0);
        }

        $rawCents = array_map(static fn ($weight) => $targetCents * $weight / $weightTotal, $weights);
        $allocatedCents = array_map(static fn ($cents) => (int) floor($cents), $rawCents);
        $remainingCents = $targetCents - array_sum($allocatedCents);
        $order = array_keys($rawCents);

        usort($order, static function ($left, $right) use ($rawCents) {
            $leftRemainder = $rawCents[$left] - floor($rawCents[$left]);
            $rightRemainder = $rawCents[$right] - floor($rawCents[$right]);

            return $rightRemainder <=> $leftRemainder;
        });

        for ($cent = 0; $cent < $remainingCents; $cent++) {
            $allocatedCents[$order[$cent % $count]]++;
        }

        return array_map(static fn ($cents) => $cents / 100, $allocatedCents);
    };

    $taxableWeights = $invoiceItems->map(fn ($item) => max((float) $item->taxable_amount, 0))->all();
    $lineTaxableAmountsAvailable = (float) $invoice->taxable_amount <= 0 || array_sum($taxableWeights) > 0;
    $lineTaxableAmounts = $allocateCents((float) $invoice->taxable_amount, $taxableWeights);

    $cgstWeights = $invoiceItems->map(fn ($item) => max((float) $item->cgst_amount, 0))->all();
    $sgstWeights = $invoiceItems->map(fn ($item) => max((float) $item->sgst_amount, 0))->all();
    $igstWeights = $invoiceItems->map(fn ($item) => max((float) $item->igst_amount, 0))->all();
    $lineTaxesAvailable =
        ((float) $invoice->cgst_amount <= 0 || array_sum($cgstWeights) > 0)
        && ((float) $invoice->sgst_amount <= 0 || array_sum($sgstWeights) > 0)
        && ((float) $invoice->igst_amount <= 0 || array_sum($igstWeights) > 0);
    $lineCgstAmounts = $allocateCents((float) $invoice->cgst_amount, $cgstWeights);
    $lineSgstAmounts = $allocateCents((float) $invoice->sgst_amount, $sgstWeights);
    $lineIgstAmounts = $allocateCents((float) $invoice->igst_amount, $igstWeights);
    $invoiceTaxTotal = (float) $invoice->cgst_amount + (float) $invoice->sgst_amount + (float) $invoice->igst_amount;
    $lineFiguresUnavailable =
        ((float) $invoice->taxable_amount > 0 && !$lineTaxableAmountsAvailable)
        || ($invoiceTaxTotal > 0 && !$lineTaxesAvailable);

    $printLines = $invoiceItems->map(function ($item, $index) use (
        $lineTaxableAmounts,
        $lineTaxableAmountsAvailable,
        $lineCgstAmounts,
        $lineSgstAmounts,
        $lineIgstAmounts,
        $lineTaxesAvailable
    ) {
        $lineTaxableAmount = $lineTaxableAmounts[$index] ?? 0.0;
        $lineDiscountAmount = $lineTaxableAmountsAvailable
            ? round(max((float) $item->discount_amount, 0) + max((float) $item->taxable_amount - $lineTaxableAmount, 0), 2)
            : null;

        return [
            'item' => $item,
            'discount_amount' => $lineDiscountAmount,
            'taxable_amount' => $lineTaxableAmountsAvailable ? $lineTaxableAmount : null,
            'tax_amount' => $lineTaxesAvailable
                ? ($lineCgstAmounts[$index] ?? 0.0) + ($lineSgstAmounts[$index] ?? 0.0) + ($lineIgstAmounts[$index] ?? 0.0)
                : null,
        ];
    });

    $received = $invoice->receivedAmount();
    $receivedDisplay = $received > 0 ? '-'.$money($received) : $money($received);
    $balanceDue = $invoice->balanceDue();
    $secondaryDate = $invoice->invoice_type === 'tax'
        ? ($invoice->due_date ?: $invoice->valid_until)
        : $invoice->valid_until;
    $secondaryDateLabel = $invoice->invoice_type === 'tax' && $invoice->due_date
        ? 'Due date'
        : 'Valid until';
    $billingLocation = collect([$invoice->billing_city, $invoice->billing_state, $invoice->billing_country])
        ->filter()->implode(', ');
    $shippingDetails = collect([
        $invoice->shipping_address,
        $invoice->shipping_city,
        $invoice->shipping_state,
        $invoice->shipping_country,
        $invoice->shipping_pincode,
    ])->filter();
    $hasShippingDetails = $shippingDetails->isNotEmpty();
    $shippingAddress = $hasShippingDetails ? $invoice->shipping_address : $invoice->billing_address;
    $shippingLocation = collect($hasShippingDetails
        ? [$invoice->shipping_city, $invoice->shipping_state, $invoice->shipping_country]
        : [$invoice->billing_city, $invoice->billing_state, $invoice->billing_country])
        ->filter()->implode(', ');
    $shippingPincode = $hasShippingDetails ? $invoice->shipping_pincode : $invoice->billing_pincode;
    $sellerLocation = collect([$invoice->seller_city, $invoice->seller_state, $invoice->seller_country])
        ->filter()->implode(', ');
@endphp

<body class="pdf-preview invoice-standalone">
    <div class="pdf-toolbar pdf-toolbar--spread">
        @if (!$publicMode)
            <a class="pdf-action pdf-action--secondary" href="{{ route('sales-invoices.show', $invoice) }}">Back</a>
        @else
            <span></span>
        @endif
        <button class="pdf-action pdf-action--primary" type="button" onclick="window.print()">
            Print Invoice
        </button>
    </div>

    <main class="page pdf-sheet">
        <header class="invoice-top">
            <div class="seller-block">
                <img class="brand-logo" src="{{ asset('images/logo-dark.png') }}"
                    alt="MissPack — Packed Perfect">
                <div class="seller-details">
                    <p class="brand-company">{{ $invoice->seller_company_name ?: 'MissPack India Pvt Ltd' }}</p>
                    @if ($invoice->seller_address)
                        <p class="brand-detail">{{ $invoice->seller_address }}</p>
                    @endif
                    @if ($sellerLocation || $invoice->seller_pincode)
                        <p class="brand-detail">
                            {{ collect([$sellerLocation, $invoice->seller_pincode])->filter()->implode(' · ') }}
                        </p>
                    @endif
                    @if ($invoice->seller_gstin || $invoice->seller_pan)
                        <p class="brand-detail">
                            @if ($invoice->seller_gstin) GSTIN {{ $invoice->seller_gstin }} @endif
                            @if ($invoice->seller_pan) · PAN {{ $invoice->seller_pan }} @endif
                        </p>
                    @endif
                    @if ($invoice->seller_email || $invoice->seller_mobile || $invoice->seller_website)
                        <p class="brand-detail">
                            {{ collect([$invoice->seller_email, $invoice->seller_mobile, $invoice->seller_website])->filter()->implode(' · ') }}
                        </p>
                    @endif
                </div>
            </div>

            <div class="invoice-identity">
                <span class="invoice-eyebrow">Sales document</span>
                <h1>{{ $invoice->typeLabel() }}</h1>
                <p class="invoice-number">
                    <span>Invoice no.</span>
                    <strong>{{ $invoice->invoice_number }}</strong>
                </p>
                <span class="invoice-status">{{ $invoice->statusLabel() }}</span>
            </div>
        </header>

        <section class="invoice-facts" aria-label="Invoice details">
            <div class="invoice-fact">
                <span>Invoice date</span>
                <strong>{{ optional($invoice->invoice_date)->format('d M Y') ?: '—' }}</strong>
            </div>
            <div class="invoice-fact">
                <span>Payment terms</span>
                <strong>{{ $invoice->payment_terms ?: '—' }}</strong>
            </div>
            <div class="invoice-fact">
                <span>{{ $secondaryDateLabel }}</span>
                <strong>{{ optional($secondaryDate)->format('d M Y') ?: '—' }}</strong>
            </div>
            <div class="invoice-fact">
                <span>Place of supply</span>
                <strong>{{ $invoice->place_of_supply ?: '—' }}</strong>
            </div>
            <div class="invoice-fact">
                <span>Purchase order</span>
                <strong>{{ $invoice->po_number ?: '—' }}</strong>
                @if ($invoice->po_date)
                    <small>PO dated {{ $invoice->po_date->format('d M Y') }}</small>
                @endif
            </div>
        </section>

        <section class="parties" aria-label="Billing and shipping addresses">
            <article class="party-card">
                <h2>Bill to</h2>
                <div class="party-body">
                    <p class="party-name">{{ $invoice->client_company_name ?: '—' }}</p>
                    @if ($invoice->client_brand_name)
                        <p class="party-brand">{{ $invoice->client_brand_name }}</p>
                    @endif
                    @if ($invoice->billing_address)
                        <p class="party-detail">{{ $invoice->billing_address }}</p>
                    @endif
                    @if ($billingLocation || $invoice->billing_pincode)
                        <p class="party-detail">
                            {{ collect([$billingLocation, $invoice->billing_pincode])->filter()->implode(' · ') }}
                        </p>
                    @endif
                    @if ($invoice->client_gstin || $invoice->client_pan)
                        <div class="party-tax">
                            @if ($invoice->client_gstin)<span>GSTIN {{ $invoice->client_gstin }}</span>@endif
                            @if ($invoice->client_pan)<span>PAN {{ $invoice->client_pan }}</span>@endif
                        </div>
                    @endif
                    @if ($invoice->client_contact_name || $invoice->client_mobile || $invoice->client_email)
                        <p class="party-contact">
                            {{ collect([$invoice->client_contact_name, $invoice->client_mobile, $invoice->client_email])->filter()->implode(' · ') }}
                        </p>
                    @endif
                </div>
            </article>

            <article class="party-card">
                <h2>Ship to</h2>
                <div class="party-body">
                    <p class="party-name">{{ $invoice->client_company_name ?: '—' }}</p>
                    @if ($invoice->client_brand_name)
                        <p class="party-brand">{{ $invoice->client_brand_name }}</p>
                    @endif
                    @if (!$hasShippingDetails)
                        <p class="party-hint">Same as billing address</p>
                    @endif
                    @if ($shippingAddress)
                        <p class="party-detail">{{ $shippingAddress }}</p>
                    @endif
                    @if ($shippingLocation || $shippingPincode)
                        <p class="party-detail">
                            {{ collect([$shippingLocation, $shippingPincode])->filter()->implode(' · ') }}
                        </p>
                    @endif
                    @if ($invoice->client_contact_name || $invoice->client_mobile || $invoice->client_email)
                        <p class="party-contact">
                            {{ collect([$invoice->client_contact_name, $invoice->client_mobile, $invoice->client_email])->filter()->implode(' · ') }}
                        </p>
                    @endif
                </div>
            </article>
        </section>

        <div class="items-wrap">
            <table class="items">
                <colgroup>
                    <col style="width: 31%">
                    <col style="width: 9%">
                    <col style="width: 13%">
                    <col style="width: 10%">
                    <col style="width: 17%">
                    <col style="width: 8%">
                    <col style="width: 12%">
                </colgroup>
                <thead>
                    <tr>
                        <th>Item description / HSN-SAC</th>
                        <th class="right">Qty</th>
                        <th class="right">Rate</th>
                        <th class="right">Total discount</th>
                        <th class="right">Taxable value</th>
                        <th class="right">GST rate</th>
                        <th class="right">Tax amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($printLines as $line)
                        @php
                            $item = $line['item'];
                            $quantity = rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ','), '0'), '.');
                            $effectiveGst = $invoice->gst_type === 'export' ? 0 : (float) $item->gst_percent;
                            $gstPercent = rtrim(rtrim(number_format($effectiveGst, 2, '.', ''), '0'), '.');
                        @endphp
                        <tr>
                            <td class="item-description">
                                <strong class="item-name">{{ $item->product_name }}</strong>
                                @if ($item->description)
                                    <span class="item-detail">{!! nl2br(e($item->description)) !!}</span>
                                @endif
                                @if ($item->remarks)
                                    <span class="item-detail item-remark">{!! nl2br(e($item->remarks)) !!}</span>
                                @endif
                                <span class="item-meta">HSN/SAC: {{ $item->hsn_sac ?: '—' }}</span>
                            </td>
                            <td class="right quantity-cell">
                                {{ $quantity }}@if ($item->unit) <span class="unit-label">{{ $item->unit }}</span>@endif
                            </td>
                            <td class="right">{{ $money($item->unit_price) }}</td>
                            <td class="right">
                                {{ $line['discount_amount'] !== null && $line['discount_amount'] > 0 ? '-'.$money($line['discount_amount']) : '—' }}
                            </td>
                            <td class="right item-taxable">
                                {{ $line['taxable_amount'] !== null ? $money($line['taxable_amount']) : '—' }}
                            </td>
                            <td class="right">{{ $gstPercent }}%</td>
                            <td class="right item-tax">
                                {{ $invoiceTaxTotal > 0 && $line['tax_amount'] !== null ? $money($line['tax_amount']) : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($lineFiguresUnavailable)
            <p class="line-tax-note">Some line-level figures could not be allocated for this record. The invoice totals below remain authoritative.</p>
        @endif

        <section class="invoice-bottom" aria-label="Totals and payment details">
            <div class="invoice-left">
                <div class="amount-words">
                    <span class="section-label">Amount in words</span>
                    <strong>{{ $invoice->amount_in_words ?: '—' }}</strong>
                </div>

                @if ($invoice->notes)
                    <div class="invoice-note">
                        <span class="section-label">Notes</span>
                        <p>{{ $invoice->notes }}</p>
                    </div>
                @endif

                @if ($invoice->seller_bank_name || $invoice->seller_account_holder || $invoice->seller_account_number || $invoice->seller_ifsc || $invoice->seller_branch || $invoice->seller_swift)
                    <section class="bank-panel">
                        <h2>Bank details</h2>
                        <dl class="bank-details">
                            @foreach ([
                                'Bank' => $invoice->seller_bank_name,
                                'Account holder' => $invoice->seller_account_holder,
                                'Account number' => $invoice->seller_account_number,
                                'IFSC' => $invoice->seller_ifsc,
                                'Branch' => $invoice->seller_branch,
                                'SWIFT' => $invoice->seller_swift,
                            ] as $label => $value)
                                @if ($value)
                                    <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                                @endif
                            @endforeach
                        </dl>
                    </section>
                @endif
            </div>

            <div class="invoice-right">
                <section class="summary">
                    <div class="summary-heading">
                        <h2>Invoice summary</h2>
                        <span>{{ \App\Helpers\CommonHelper::currencyLabel($currency) }}</span>
                    </div>
                    <table>
                        <tbody>
                            <tr>
                                <td>Subtotal</td>
                                <td>{{ $money($invoice->subtotal) }}</td>
                            </tr>
                            @if ($lineDiscountTotal > 0)
                                <tr>
                                    <td>Item discounts</td>
                                    <td>-{{ $money($lineDiscountTotal) }}</td>
                                </tr>
                            @endif
                            @if ((float) $invoice->discount_amount > 0)
                                <tr>
                                    <td>Invoice discount</td>
                                    <td>-{{ $money($invoice->discount_amount) }}</td>
                                </tr>
                            @endif
                            <tr class="taxable-row">
                                <td>Taxable value</td>
                                <td>{{ $money($invoice->taxable_amount) }}</td>
                            </tr>
                            @if ((float) $invoice->cgst_amount > 0)
                                <tr><td>CGST</td><td>{{ $money($invoice->cgst_amount) }}</td></tr>
                            @endif
                            @if ((float) $invoice->sgst_amount > 0)
                                <tr><td>SGST</td><td>{{ $money($invoice->sgst_amount) }}</td></tr>
                            @endif
                            @if ((float) $invoice->igst_amount > 0)
                                <tr><td>IGST</td><td>{{ $money($invoice->igst_amount) }}</td></tr>
                            @endif
                            @if ((float) $invoice->freight_amount !== 0.0)
                                <tr><td>Freight</td><td>{{ $money($invoice->freight_amount) }}</td></tr>
                            @endif
                            @if ((float) $invoice->packing_amount !== 0.0)
                                <tr><td>Packing</td><td>{{ $money($invoice->packing_amount) }}</td></tr>
                            @endif
                            @if ((float) $invoice->other_charges !== 0.0)
                                <tr><td>Other charges</td><td>{{ $money($invoice->other_charges) }}</td></tr>
                            @endif
                            @if (abs((float) $invoice->round_off) >= 0.01)
                                <tr><td>Round off</td><td>{{ $money($invoice->round_off) }}</td></tr>
                            @endif
                            <tr class="grand-total">
                                <td>Grand total</td>
                                <td>{{ $money($invoice->total_amount) }}</td>
                            </tr>
                            <tr class="settlement-row">
                                <td>Received to date</td>
                                <td>{{ $receivedDisplay }}</td>
                            </tr>
                            <tr class="balance-due">
                                <td>Balance due</td>
                                <td>{{ $money($balanceDue) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <div class="signature">
                    <strong>For {{ $invoice->seller_company_name ?: 'MissPack India Pvt Ltd' }}</strong>
                    <span class="signature-space" aria-hidden="true"></span>
                    <span class="signature-label">Authorised Signatory</span>
                </div>
            </div>
        </section>

        @if ($invoice->terms_conditions)
            <section class="terms-panel">
                <h2>Terms &amp; conditions</h2>
                <div class="terms-copy">{{ $invoice->terms_conditions }}</div>
            </section>
        @endif

        <footer class="footer-note">This is a computer-generated invoice. Please verify all details before payment.</footer>
    </main>
</body>

</html>
