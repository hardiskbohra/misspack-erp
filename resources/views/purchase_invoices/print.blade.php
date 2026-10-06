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
    $isImport = $invoice->gst_type === 'export';
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
        return [
            'item' => $item,
            'taxable_amount' => $lineTaxableAmountsAvailable ? ($lineTaxableAmounts[$index] ?? 0.0) : null,
            'tax_amount' => $lineTaxesAvailable
                ? ($lineCgstAmounts[$index] ?? 0.0) + ($lineSgstAmounts[$index] ?? 0.0) + ($lineIgstAmounts[$index] ?? 0.0)
                : null,
        ];
    });

    $received = $invoice->paidAmount();
    $receivedDisplay = $received > 0 ? '-'.$money($received) : $money($received);
    $balanceDue = $invoice->balanceDue();
    $secondaryDate = $invoice->invoice_type === 'bill'
        ? ($invoice->due_date ?: $invoice->expected_date)
        : ($invoice->expected_date ?: $invoice->valid_until);
    $secondaryDateLabel = $invoice->invoice_type === 'bill' && $invoice->due_date
        ? 'Due date'
        : 'Expected date';
    $billingLocation = collect([$invoice->vendor_city, $invoice->vendor_state, $invoice->vendor_country])
        ->filter()->implode(', ');
    $hasShippingDetails = false;
    $shippingAddress = $invoice->buyer_address;
    $shippingLocation = collect([$invoice->buyer_city, $invoice->buyer_state, $invoice->buyer_country])->filter()->implode(', ');
    $shippingPincode = $invoice->buyer_pincode;
    $sellerLocation = collect([$invoice->buyer_city, $invoice->buyer_state, $invoice->buyer_country])
        ->filter()->implode(', ');
    $publicMode = $public ?? false;
@endphp

<body class="pdf-preview invoice-standalone">
    <div class="pdf-toolbar pdf-toolbar--spread">
        @if (!$publicMode)
            <a class="pdf-action pdf-action--secondary" href="{{ route($invoice->routePrefix().'.show', $invoice) }}">Back</a>
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
                    <p class="brand-company">{{ $invoice->buyer_company_name ?: 'MissPack India Pvt Ltd' }}</p>
                    @if ($invoice->buyer_address)
                        <p class="brand-detail">{{ $invoice->buyer_address }}</p>
                    @endif
                    @if ($sellerLocation || $invoice->buyer_pincode)
                        <p class="brand-detail">
                            {{ collect([$sellerLocation, $invoice->buyer_pincode])->filter()->implode(' · ') }}
                        </p>
                    @endif
                    @if ($invoice->buyer_gstin || $invoice->buyer_pan)
                        <p class="brand-detail">
                            @if ($invoice->buyer_gstin) GSTIN {{ $invoice->buyer_gstin }} @endif
                            @if ($invoice->buyer_pan) · PAN {{ $invoice->buyer_pan }} @endif
                        </p>
                    @endif
                    @if ($invoice->buyer_email || $invoice->buyer_mobile || $invoice->buyer_website)
                        <p class="brand-detail">
                            {{ collect([$invoice->buyer_email, $invoice->buyer_mobile, $invoice->buyer_website])->filter()->implode(' · ') }}
                        </p>
                    @endif
                </div>
            </div>

            <div class="invoice-identity">
                <span class="invoice-eyebrow">Purchase document</span>
                <h1>{{ $invoice->typeLabel() }}</h1>
                <p class="invoice-number">
                    <span>Document no.</span>
                    <strong>{{ $invoice->invoice_number }}</strong>
                </p>
                <span class="invoice-status">{{ $invoice->statusLabel() }}</span>
            </div>
        </header>

        <section class="invoice-facts" aria-label="Invoice details">
            <div class="invoice-fact">
                <span>Document date</span>
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
            @unless ($isImport)
            <div class="invoice-fact">
                <span>Place of supply</span>
                <strong>{{ $invoice->place_of_supply ?: '—' }}</strong>
            </div>
            @endunless
            <div class="invoice-fact">
                <span>Vendor bill</span>
                <strong>{{ $invoice->vendor_bill_number ?: '—' }}</strong>
                @if ($invoice->vendor_bill_date)
                    <small>Dated {{ $invoice->vendor_bill_date->format('d M Y') }}</small>
                @endif
            </div>
        </section>

        <section class="parties" aria-label="Billing and shipping addresses">
            <article class="party-card">
                <h2>Vendor</h2>
                <div class="party-body">
                    <p class="party-name">{{ $invoice->vendor_company_name ?: '—' }}</p>
                    @if ($invoice->vendor_address)
                        <p class="party-detail">{{ $invoice->vendor_address }}</p>
                    @endif
                    @if ($billingLocation || $invoice->vendor_pincode)
                        <p class="party-detail">
                            {{ collect([$billingLocation, $invoice->vendor_pincode])->filter()->implode(' · ') }}
                        </p>
                    @endif
                    @if ($invoice->vendor_gstin || $invoice->vendor_pan)
                        <p class="party-detail">
                            @if ($invoice->vendor_gstin) GSTIN {{ $invoice->vendor_gstin }} @endif
                            @if ($invoice->vendor_pan) · PAN {{ $invoice->vendor_pan }} @endif
                        </p>
                    @endif
                    @if ($invoice->vendor_contact_name || $invoice->vendor_mobile || $invoice->vendor_email)
                        <p class="party-contact">
                            {{ collect([$invoice->vendor_contact_name, $invoice->vendor_mobile, $invoice->vendor_email])->filter()->implode(' · ') }}
                        </p>
                    @endif
                </div>
            </article>

            <article class="party-card">
                <h2>Ship to / Buyer</h2>
                <div class="party-body">
                    <p class="party-name">{{ $invoice->buyer_company_name ?: '—' }}</p>
                    @if ($shippingAddress)
                        <p class="party-detail">{{ $shippingAddress }}</p>
                    @endif
                    @if ($shippingLocation || $shippingPincode)
                        <p class="party-detail">
                            {{ collect([$shippingLocation, $shippingPincode])->filter()->implode(' · ') }}
                        </p>
                    @endif
                </div>
            </article>
        </section>

        <div class="items-wrap">
            <table class="items">
                <colgroup>
                    <col style="width: {{ $isImport ? '52%' : '36%' }}">
                    <col style="width: 12%">
                    <col style="width: 18%">
                    @unless ($isImport)
                        <col style="width: 16%">
                        <col style="width: 8%">
                        <col style="width: 10%">
                    @endunless
                    @if ($isImport)
                        <col style="width: 18%">
                    @endif
                </colgroup>
                <thead>
                    <tr>
                        <th>Item description / HSN-SAC</th>
                        <th class="right">Qty</th>
                        <th class="right">Rate</th>
                        @unless ($isImport)
                            <th class="right">Taxable value</th>
                            <th class="right">GST rate</th>
                            <th class="right">Tax amount</th>
                        @else
                            <th class="right">Amount</th>
                        @endunless
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
                            @unless ($isImport)
                                <td class="right item-taxable">
                                    {{ $line['taxable_amount'] !== null ? $money($line['taxable_amount']) : '—' }}
                                </td>
                                <td class="right">{{ $gstPercent }}%</td>
                                <td class="right item-tax">
                                    {{ $invoiceTaxTotal > 0 && $line['tax_amount'] !== null ? $money($line['tax_amount']) : '—' }}
                                </td>
                            @else
                                <td class="right">{{ $money($item->line_total) }}</td>
                            @endunless
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
                            @unless ($isImport)
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
                            @endunless
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
                                <td>Paid to date</td>
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
                    <strong>For {{ $invoice->buyer_company_name ?: 'MissPack India Pvt Ltd' }}</strong>
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

        <footer class="footer-note">This is a computer-generated purchase document. Please verify all details before supply or payment.</footer>
    </main>
</body>

</html>
