<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <title>{{ $invoice->invoice_number }} - {{ $invoice->typeLabel() }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/purchase-invoices-print.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/document-print.css') }}">
</head>

@php
    $currency = (string) ($invoice->currency ?: 'INR');
    $money = fn ($amount) => \App\Helpers\CommonHelper::amount((float) $amount, $currency);
    $isOrder = $invoice->isOrder();
    $docTitle = $isOrder ? 'Purchase Order' : 'Purchase Bill';
    $prefix = $invoice->routePrefix();
    $items = $invoice->items->values();

    /* The header's taxable and tax figures already have the document-wide
       discount folded into them; the lines carry their own before it. Spread the
       header figure back over the lines by weight so the printed columns add up
       to the printed total — a sheet whose columns do not foot is a sheet the
       vendor will query. Largest remainder, in paise, so nothing goes missing to
       rounding. */
    $allocate = static function (float $target, array $weights): array {
        $weights = array_map(static fn ($weight) => max((float) $weight, 0), array_values($weights));
        $count = count($weights);

        if ($count === 0) {
            return [];
        }

        $targetPaise = max(0, (int) round($target * 100));
        $weightTotal = array_sum($weights);

        if ($targetPaise === 0 || $weightTotal <= 0) {
            return array_fill(0, $count, 0.0);
        }

        $raw = array_map(static fn ($weight) => $targetPaise * $weight / $weightTotal, $weights);
        $paise = array_map(static fn ($value) => (int) floor($value), $raw);
        $left = $targetPaise - array_sum($paise);
        $order = array_keys($raw);
        usort($order, static fn ($a, $b) => ($raw[$b] - floor($raw[$b])) <=> ($raw[$a] - floor($raw[$a])));

        for ($i = 0; $i < $left; $i++) {
            $paise[$order[$i % $count]]++;
        }

        return array_map(static fn ($value) => $value / 100, $paise);
    };

    $taxableWeights = $items->map(fn ($item) => (float) $item->taxable_amount)->all();
    $taxableLines = $allocate((float) $invoice->taxable_amount, $taxableWeights);
    $cgstLines = $allocate((float) $invoice->cgst_amount, $items->map(fn ($item) => (float) $item->cgst_amount)->all());
    $sgstLines = $allocate((float) $invoice->sgst_amount, $items->map(fn ($item) => (float) $item->sgst_amount)->all());
    $igstLines = $allocate((float) $invoice->igst_amount, $items->map(fn ($item) => (float) $item->igst_amount)->all());

    $printedLines = $items->map(function ($item, $index) use ($taxableLines, $taxableWeights, $cgstLines, $sgstLines, $igstLines) {
        $taxable = array_sum($taxableWeights) > 0 ? ($taxableLines[$index] ?? 0.0) : null;

        return [
            'item' => $item,
            'taxable' => $taxable,
            'tax' => $taxable === null ? null : ($cgstLines[$index] ?? 0.0) + ($sgstLines[$index] ?? 0.0) + ($igstLines[$index] ?? 0.0),
        ];
    });

    $paidAmount = $invoice->isBill() ? $invoice->paidAmount() : 0.0;
    $balanceDue = $invoice->isBill() ? $invoice->balanceDue() : 0.0;

    $vendorLocation = collect([$invoice->vendor_city, $invoice->vendor_state, $invoice->vendor_country])->filter()->implode(', ');
    $buyerLocation = collect([$invoice->buyer_city, $invoice->buyer_state, $invoice->buyer_country])->filter()->implode(', ');
@endphp

<body class="pdf-preview">
    <div class="pdf-toolbar pdf-toolbar--spread">
        @if (! ($publicMode ?? false))
            <a class="pdf-action pdf-action--secondary" href="{{ route($prefix.'.show', $invoice) }}">Back</a>
        @else
            <span></span>
        @endif
        <button class="pdf-action pdf-action--primary" type="button" onclick="window.print()">
            Print {{ $isOrder ? 'Order' : 'Bill' }}
        </button>
    </div>

    <main class="page pdf-sheet pi-print">
        {{-- Our own letterhead. On a purchase document the company is the buyer,
             so the sheet is headed by us and addressed to the supplier. --}}
        <header class="pi-print-top">
            <div class="pi-print-brand">
                <img class="pi-print-logo" src="{{ asset('images/logo-dark.png') }}" alt="MissPack — Packed Perfect">
                <div>
                    <p class="pi-print-company">{{ $invoice->buyer_company_name ?: 'MissPack India Pvt Ltd' }}</p>
                    @if ($invoice->buyer_address)
                        <p class="pi-print-detail">{{ $invoice->buyer_address }}</p>
                    @endif
                    @if ($buyerLocation || $invoice->buyer_pincode)
                        <p class="pi-print-detail">{{ collect([$buyerLocation, $invoice->buyer_pincode])->filter()->implode(' · ') }}</p>
                    @endif
                    @if ($invoice->buyer_gstin || $invoice->buyer_pan)
                        <p class="pi-print-detail">
                            @if ($invoice->buyer_gstin) GSTIN {{ $invoice->buyer_gstin }} @endif
                            @if ($invoice->buyer_pan) · PAN {{ $invoice->buyer_pan }} @endif
                        </p>
                    @endif
                    @if ($invoice->buyer_email || $invoice->buyer_mobile || $invoice->buyer_website)
                        <p class="pi-print-detail">
                            {{ collect([$invoice->buyer_email, $invoice->buyer_mobile, $invoice->buyer_website])->filter()->implode(' · ') }}
                        </p>
                    @endif
                </div>
            </div>

            <div class="pi-print-identity">
                <span class="pi-print-eyebrow">Purchase document</span>
                <h1>{{ $docTitle }}</h1>
                <p class="pi-print-number">
                    <span>Document no.</span>
                    <strong>{{ $invoice->invoice_number }}</strong>
                </p>
                <span class="pi-print-status">{{ $invoice->statusLabel() }}</span>
            </div>
        </header>

        <section class="pi-print-facts" aria-label="Document details">
            <div class="pi-print-fact">
                <span>{{ $isOrder ? 'Order date' : 'Bill date' }}</span>
                <strong>{{ $invoice->invoice_date?->format('d M Y') ?: '—' }}</strong>
            </div>
            <div class="pi-print-fact">
                <span>Payment terms</span>
                <strong>{{ $invoice->payment_terms ?: '—' }}</strong>
            </div>
            <div class="pi-print-fact">
                <span>{{ $isOrder ? 'Expected by' : 'Due date' }}</span>
                <strong>{{ ($isOrder ? $invoice->expected_date : $invoice->due_date)?->format('d M Y') ?: '—' }}</strong>
            </div>
            <div class="pi-print-fact">
                <span>Place of supply</span>
                <strong>{{ $invoice->place_of_supply ?: '—' }}</strong>
            </div>
            <div class="pi-print-fact">
                <span>{{ $isOrder ? 'Valid until' : "Vendor's bill no." }}</span>
                <strong>{{ $isOrder ? ($invoice->valid_until?->format('d M Y') ?: '—') : ($invoice->vendor_bill_number ?: '—') }}</strong>
                @if (! $isOrder && $invoice->vendor_bill_date)
                    <small>Their bill dated {{ $invoice->vendor_bill_date->format('d M Y') }}</small>
                @endif
            </div>
            @if ($invoice->our_reference)
                <div class="pi-print-fact">
                    <span>Our reference</span>
                    <strong>{{ $invoice->our_reference }}</strong>
                </div>
            @endif
        </section>

        <section class="pi-print-parties" aria-label="Vendor and delivery details">
            <article class="pi-print-party">
                <h2>{{ $isOrder ? 'Order from' : 'Billed by' }}</h2>
                <div>
                    <p class="pi-print-party-name">{{ $invoice->vendor_company_name ?: '—' }}</p>
                    @if ($invoice->vendor_address)
                        <p class="pi-print-detail">{{ $invoice->vendor_address }}</p>
                    @endif
                    @if ($vendorLocation || $invoice->vendor_pincode)
                        <p class="pi-print-detail">{{ collect([$vendorLocation, $invoice->vendor_pincode])->filter()->implode(' · ') }}</p>
                    @endif
                    @if ($invoice->vendor_gstin || $invoice->vendor_pan)
                        <p class="pi-print-detail">
                            @if ($invoice->vendor_gstin) GSTIN {{ $invoice->vendor_gstin }} @endif
                            @if ($invoice->vendor_pan) · PAN {{ $invoice->vendor_pan }} @endif
                        </p>
                    @endif
                    @if ($invoice->vendor_contact_name || $invoice->vendor_mobile || $invoice->vendor_email)
                        <p class="pi-print-party-contact">
                            {{ collect([$invoice->vendor_contact_name, $invoice->vendor_mobile, $invoice->vendor_email])->filter()->implode(' · ') }}
                        </p>
                    @endif
                </div>
            </article>

            <article class="pi-print-party">
                <h2>Deliver to</h2>
                <div>
                    <p class="pi-print-party-name">{{ $invoice->buyer_company_name ?: 'MissPack India Pvt Ltd' }}</p>
                    @if ($invoice->buyer_address)
                        <p class="pi-print-detail">{{ $invoice->buyer_address }}</p>
                    @endif
                    @if ($buyerLocation || $invoice->buyer_pincode)
                        <p class="pi-print-detail">{{ collect([$buyerLocation, $invoice->buyer_pincode])->filter()->implode(' · ') }}</p>
                    @endif
                    @if ($invoice->purchase_person)
                        <p class="pi-print-party-contact">Purchase contact · {{ $invoice->purchase_person }}</p>
                    @endif
                    @if ($invoice->transport_mode)
                        <p class="pi-print-party-contact">Transport · {{ $invoice->transport_mode }}</p>
                    @endif
                </div>
            </article>
        </section>

        <div class="pi-print-items-wrap">
            <table class="pi-print-items">
                <thead>
                    <tr>
                        <th class="pi-print-col-sr">#</th>
                        <th>Item</th>
                        <th class="pi-print-col-hsn">HSN/SAC</th>
                        <th class="is-num">Qty</th>
                        <th class="is-num">Rate</th>
                        <th class="is-num">Disc</th>
                        <th class="is-num">GST</th>
                        <th class="is-num">Taxable</th>
                        <th class="is-num">Tax</th>
                        <th class="is-num">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($printedLines as $index => $line)
                        @php($item = $line['item'])
                        <tr>
                            <td class="pi-print-col-sr">{{ $index + 1 }}</td>
                            <td>
                                <strong>{{ $item->product_name }}</strong>
                                @if ($item->description)
                                    <span class="pi-print-item-note">{{ $item->description }}</span>
                                @endif
                                @if ($item->remarks)
                                    <span class="pi-print-item-note">{{ $item->remarks }}</span>
                                @endif
                            </td>
                            <td class="pi-print-col-hsn">{{ $item->hsn_sac ?: '—' }}</td>
                            <td class="is-num">{{ rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.') }} <span class="pi-print-item-note">{{ $item->unit }}</span></td>
                            <td class="is-num">{{ $money($item->unit_price) }}</td>
                            <td class="is-num">{{ (float) $item->discount_percent > 0 ? rtrim(rtrim(number_format((float) $item->discount_percent, 2, '.', ''), '0'), '.').'%' : '—' }}</td>
                            <td class="is-num">{{ rtrim(rtrim(number_format((float) $item->gst_percent, 2, '.', ''), '0'), '.') }}%</td>
                            <td class="is-num">{{ $line['taxable'] === null ? '—' : $money($line['taxable']) }}</td>
                            <td class="is-num">{{ $line['tax'] === null ? '—' : $money($line['tax']) }}</td>
                            <td class="is-num"><strong>{{ $money($item->line_total) }}</strong></td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="pi-print-empty">No items on this document.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pi-print-foot">
            <section class="pi-print-terms-block">
                @if ($invoice->amount_in_words)
                    <p class="pi-print-words"><span>In words</span> {{ $invoice->amount_in_words }}</p>
                @endif

                @if ($invoice->notes)
                    <div class="pi-print-note">
                        <h2>Notes</h2>
                        <p>{{ $invoice->notes }}</p>
                    </div>
                @endif

                @if ($invoice->delivery_terms || $invoice->dispatch_terms || $invoice->payment_terms)
                    <div class="pi-print-note">
                        <h2>Terms</h2>
                        @if ($invoice->payment_terms)<p>Payment · {{ $invoice->payment_terms }}</p>@endif
                        @if ($invoice->delivery_terms)<p>Delivery · {{ $invoice->delivery_terms }}</p>@endif
                        @if ($invoice->dispatch_terms)<p>Dispatch · {{ $invoice->dispatch_terms }}</p>@endif
                        @if ($invoice->transport_mode)<p>Transport · {{ $invoice->transport_mode }}</p>@endif
                    </div>
                @endif

                <div class="pi-print-signature">
                    <strong>For {{ $invoice->buyer_company_name ?: 'MissPack India Pvt Ltd' }}</strong>
                    <span class="pi-print-signature-space" aria-hidden="true"></span>
                    <span class="pi-print-signature-label">{{ $isOrder ? 'Authorised for purchase' : 'Received &amp; verified by' }}</span>
                </div>
            </section>

            <section class="pi-print-totals" aria-label="Amount summary">
                <table>
                    <tbody>
                        <tr>
                            <td>Subtotal</td>
                            <td>{{ $money($invoice->subtotal) }}</td>
                        </tr>
                        @if ((float) $invoice->discount_amount > 0)
                            <tr class="pi-print-discount">
                                <td>Discount{{ $invoice->discount_type === 'percent' && (float) $invoice->discount_value > 0 ? ' ('.rtrim(rtrim(number_format((float) $invoice->discount_value, 2, '.', ''), '0'), '.').'%)' : '' }}</td>
                                <td>− {{ $money($invoice->discount_amount) }}</td>
                            </tr>
                        @endif
                        <tr>
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
                        @if ((float) $invoice->freight_amount > 0)
                            <tr><td>Freight</td><td>{{ $money($invoice->freight_amount) }}</td></tr>
                        @endif
                        @if ((float) $invoice->packing_amount > 0)
                            <tr><td>Packing</td><td>{{ $money($invoice->packing_amount) }}</td></tr>
                        @endif
                        @if ((float) $invoice->other_charges > 0)
                            <tr><td>Other charges</td><td>{{ $money($invoice->other_charges) }}</td></tr>
                        @endif
                        @if ((float) $invoice->round_off != 0)
                            <tr><td>Round off</td><td>{{ $money($invoice->round_off) }}</td></tr>
                        @endif
                        <tr class="pi-print-grand">
                            <td>{{ $docTitle }} total</td>
                            <td>{{ $money($invoice->total_amount) }}</td>
                        </tr>
                        @if (! $isOrder)
                            <tr>
                                <td>Paid</td>
                                <td>{{ $paidAmount > 0 ? '− '.$money($paidAmount) : $money(0) }}</td>
                            </tr>
                            <tr class="pi-print-balance">
                                <td>Balance payable</td>
                                <td>{{ $money($balanceDue) }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>

                @if ($invoice->currency !== 'INR')
                    <p class="pi-print-rate">
                        Billed in {{ $invoice->currency }} at an exchange rate of
                        {{ rtrim(rtrim(number_format((float) $invoice->exchange_rate, 6, '.', ''), '0'), '.') ?: '1' }}
                        — every figure above is in {{ $invoice->currency }}.
                    </p>
                @endif
            </section>
        </div>

        @if ($invoice->terms_conditions)
            <section class="pi-print-terms-copy">
                <h2>Terms &amp; conditions</h2>
                <div>{{ $invoice->terms_conditions }}</div>
            </section>
        @endif

        <footer class="pi-print-footer">
            This is a computer-generated {{ strtolower($docTitle) }}. Please quote {{ $invoice->invoice_number }} on every
            document and payment.
        </footer>
    </main>
</body>

</html>
