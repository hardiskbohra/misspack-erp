<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <title>{{ $note->number }} — {{ $note->is_return ? 'Sales return' : $note->kindLabel() }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/sales-invoices-print.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/document-print.css') }}">
    <style>
        /* The note sheet reuses the invoice sheet's documented classes; only a
           cancelled stamp and the reason line are its own. */
        .note-cancelled-stamp {
            display: inline-block;
            margin-top: 8px;
            padding: 4px 14px;
            border: 2px solid #c0392b;
            border-radius: 6px;
            color: #c0392b;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            transform: rotate(-4deg);
        }

        .note-reason {
            margin-top: 4px;
        }
    </style>
</head>

@php
    $invoice = $note->invoice;
    $currency = (string) ($invoice->currency ?: 'INR');
    $money = fn ($amount) => \App\Helpers\CommonHelper::amount((float) $amount, $currency);
    $sellerLocation = collect([$invoice->seller_city, $invoice->seller_state])->filter()->implode(', ');
    $lines = collect($note->lines ?? []);
    $billingLocation = collect([$invoice->billing_city, $invoice->billing_state, $invoice->billing_country])
        ->filter()->implode(', ');
@endphp

<body class="pdf-preview invoice-standalone">
    <div class="pdf-toolbar pdf-toolbar--spread">
        <a class="pdf-action pdf-action--secondary" href="{{ route('sales-invoices.show', $invoice) }}">Back</a>
        <button class="pdf-action pdf-action--primary" type="button" onclick="window.print()">
            Print {{ $note->is_return ? 'return' : strtolower($note->kindLabel()) }}
        </button>
    </div>

    <main class="page pdf-sheet">
        <header class="invoice-top">
            <div class="seller-block">
                <img class="brand-logo" src="{{ asset('images/logo-dark.png') }}" alt="MissPack — Packed Perfect">
                <div class="seller-details">
                    <p class="brand-company">{{ $invoice->seller_company_name ?: 'MissPack India Pvt Ltd' }}</p>
                    @if ($invoice->seller_address)
                        <p class="brand-detail">{{ $invoice->seller_address }}</p>
                    @endif
                    @if ($sellerLocation || $invoice->seller_pincode)
                        <p class="brand-detail">{{ collect([$sellerLocation, $invoice->seller_pincode])->filter()->implode(' · ') }}</p>
                    @endif
                    @if ($invoice->seller_gstin || $invoice->seller_pan)
                        <p class="brand-detail">
                            @if ($invoice->seller_gstin) GSTIN {{ $invoice->seller_gstin }} @endif
                            @if ($invoice->seller_pan) · PAN {{ $invoice->seller_pan }} @endif
                        </p>
                    @endif
                </div>
            </div>

            <div class="invoice-identity">
                <span class="invoice-eyebrow">{{ $note->is_return ? 'Sales return' : $note->kindLabel() }}</span>
                <h1>{{ $note->is_return ? 'Sales return' : $note->kindLabel() }}</h1>
                <p class="invoice-number">
                    <span>Note no.</span>
                    <strong>{{ $note->number }}</strong>
                </p>
                @if ($note->status === 'cancelled')
                    <span class="note-cancelled-stamp">Cancelled</span>
                @endif
            </div>
        </header>

        <section class="invoice-facts" aria-label="Note details">
            <div class="invoice-fact">
                <span>Note date</span>
                <strong>{{ $note->note_date->format('d M Y') }}</strong>
            </div>
            <div class="invoice-fact">
                <span>Against invoice</span>
                <strong>{{ $invoice->invoice_number }}</strong>
                @if ($invoice->invoice_date)
                    <small>dated {{ $invoice->invoice_date->format('d M Y') }}</small>
                @endif
            </div>
            <div class="invoice-fact">
                <span>Reason</span>
                <strong>{{ $note->reason }}</strong>
                @if ($note->is_return)
                    <small>{{ $note->restock ? 'Goods restocked' : 'Goods not restocked' }}</small>
                @endif
            </div>
            @if ($note->status === 'cancelled')
                <div class="invoice-fact">
                    <span>Cancelled</span>
                    <strong>{{ $note->cancelled_at?->format('d M Y, H:i') }}</strong>
                    <small class="note-reason">{{ $note->cancel_reason }}</small>
                </div>
            @endif
        </section>

        <section class="parties" aria-label="Bill to">
            <article class="party-card">
                <h2>Bill to</h2>
                <div class="party-body">
                    <p class="party-name">{{ $invoice->client_company_name ?: '—' }}</p>
                    @if ($invoice->billing_address)
                        <p class="party-detail">{{ $invoice->billing_address }}</p>
                    @endif
                    @if ($billingLocation || $invoice->billing_pincode)
                        <p class="party-detail">{{ collect([$billingLocation, $invoice->billing_pincode])->filter()->implode(' · ') }}</p>
                    @endif
                    @if ($invoice->client_gstin || $invoice->client_pan)
                        <p class="party-contact">
                            @if ($invoice->client_gstin) GSTIN {{ $invoice->client_gstin }} @endif
                            @if ($invoice->client_pan) · PAN {{ $invoice->client_pan }} @endif
                        </p>
                    @endif
                </div>
            </article>
        </section>

        @if ($note->is_return && $lines->isNotEmpty())
            <div class="items-wrap">
                <table class="items">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item returned</th>
                            @if ($lines->contains(fn ($line) => filled($line['hsn_sac'] ?? null)))<th>HSN/SAC</th>@endif
                            <th class="right">Qty</th>
                            <th class="right">Rate</th>
                            <th class="right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lines as $line)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $line['product_name'] ?? '—' }}</td>
                                @if ($lines->contains(fn ($row) => filled($row['hsn_sac'] ?? null)))<td>{{ $line['hsn_sac'] ?? '—' }}</td>@endif
                                <td class="right">{{ rtrim(rtrim(number_format((float) $line['quantity'], 3), '0'), '.') }} {{ $line['unit'] ?? '' }}</td>
                                <td class="right">{{ $money($line['unit_price'] ?? 0) }}</td>
                                <td class="right">{{ $money($line['amount'] ?? 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <section class="invoice-bottom" aria-label="Totals and payment details">
            <div class="invoice-left">
                <div class="amount-words">
                    <span class="section-label">What this note does</span>
                    <p>
                        @if ($note->is_return)
                            Goods worth {{ $money($note->subtotal) }} before tax came back against
                            {{ $invoice->invoice_number }}{{ $note->restock ? ' and returned to stock' : '' }};
                            this credit of {{ $money($note->total) }} lowers what the client owes on it.
                        @elseif ($note->kind === 'credit')
                            This credit of {{ $money($note->total) }} lowers what the client owes on
                            {{ $invoice->invoice_number }}.
                        @else
                            This debit of {{ $money($note->total) }} raises what the client owes on
                            {{ $invoice->invoice_number }}.
                        @endif
                    </p>
                </div>

                @if ($note->creator)
                    <div class="invoice-note">
                        <span class="section-label">Issued by</span>
                        <p>{{ $note->creator->name }} · {{ $note->created_at->format('d M Y, H:i') }}</p>
                    </div>
                @endif
            </div>

            <div class="invoice-right">
                <section class="summary">
                    <div class="summary-heading">
                        <h2>Note summary</h2>
                        <span>{{ \App\Helpers\CommonHelper::currencyLabel($currency) }}</span>
                    </div>
                    <table>
                        <tbody>
                            <tr class="taxable-row">
                                <td>Value before tax</td>
                                <td>{{ $money($note->subtotal) }}</td>
                            </tr>
                            @if ((float) $note->cgst_amount > 0)
                                <tr><td>CGST</td><td>{{ $money($note->cgst_amount) }}</td></tr>
                            @endif
                            @if ((float) $note->sgst_amount > 0)
                                <tr><td>SGST</td><td>{{ $money($note->sgst_amount) }}</td></tr>
                            @endif
                            @if ((float) $note->igst_amount > 0)
                                <tr><td>IGST</td><td>{{ $money($note->igst_amount) }}</td></tr>
                            @endif
                            <tr class="grand-total">
                                <td>Note total</td>
                                <td>{{ $money($note->kind === 'credit' ? -(float) $note->total : (float) $note->total) }}</td>
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

        <footer class="footer-note">
            {{ $note->kindLabel() }} {{ $note->number }} corrects tax invoice {{ $invoice->invoice_number }};
            the invoice itself stands as issued.
        </footer>
    </main>
</body>

</html>
