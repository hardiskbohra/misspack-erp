<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <title>Shipment Summary — {{ $shipment->shipment_number }}</title>
    <link rel="stylesheet" href="{{ $assetVer('assets/css/shipment-print.css') }}">
    <link rel="stylesheet" href="{{ $assetVer('assets/css/document-print.css') }}">
</head>

<body class="pdf-preview">
    @include('shipments.print.partials.toolbar', ['document' => 'summary'])

    <main class="print-sheet pdf-sheet">
        <header class="print-head">
            <div>
                <span class="print-brand">{{ config('app.name', 'MissPack') }}</span>
                <h1>Shipment Summary</h1>
            </div>
            <div class="print-head-meta">
                <strong>{{ $shipment->shipment_number }}</strong>
                <span>{{ now()->format('d M Y') }}</span>
            </div>
        </header>

        <section class="print-meta">
            <div><span>Shipment</span><strong>{{ $shipment->identity_name }}</strong></div>
            <div><span>Status</span><strong>{{ $shipment->statusLabel() }}</strong></div>
            <div><span>Type / Mode</span><strong>{{ $shipment->typeLabel() }} · {{ $modeOptions[$shipment->shipment_mode] ?? '-' }}</strong></div>
            <div><span>Pickup / Drop</span><strong>{{ optional($shipment->pickup_date)->format('d M') ?: '-' }} → {{ optional($shipment->drop_date)->format('d M Y') ?: '-' }}</strong></div>
            <div><span>ETA</span><strong>{{ optional($shipment->eta_date)->format('d M Y') ?: '-' }} {{ $shipment->eta_date ? '('.$shipment->etaLabel().')' : '' }}</strong></div>
            <div><span>Client</span><strong>{{ $shipment->client->company_name ?? '-' }}</strong></div>
            <div><span>Project</span><strong>{{ $shipment->project->project_number ?? '-' }}</strong></div>
            <div><span>Logistic / Tracking</span><strong>{{ $shipment->logistic_partner ?: '-' }} · {{ $shipment->tracking_number ?: '-' }}</strong></div>
            @if ($shipment->eway_bill_number || $shipment->eway_bill_valid_until)
                <div>
                    <span>E-way Bill</span>
                    <strong>
                        {{ $shipment->eway_bill_number ?: 'Not recorded' }}
                        @if ($shipment->eway_bill_valid_until)
                            · {{ $shipment->eway_bill_valid_until->format('d M Y') }} ({{ $shipment->ewayLabel() }})
                        @endif
                    </strong>
                </div>
            @endif
        </section>

        @include('shipments.print.partials.parties')

        <div class="print-columns">
            <section>
                <h2>Products</h2>
                <table class="print-table print-table-compact">
                    <thead>
                        <tr><th>Product</th><th class="num">Qty</th><th class="num">Value</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            <tr>
                                <td>{{ $item->product_name }}</td>
                                <td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity, 3), '0'), '.') }} {{ $item->unit }}</td>
                                <td class="num">{{ $item->declared_value ? \App\Models\Shipment::formatAmount($item->currency ?: $shipment->currency, $item->declared_value) : '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="print-empty">No products.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>

            <section>
                <h2>Freight Cost</h2>
                <table class="print-table print-table-compact">
                    <thead>
                        <tr><th>Head</th><th class="num">Amount</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($shipment->costs as $cost)
                            <tr>
                                <td>{{ $cost->headLabel() }}</td>
                                <td class="num">{{ $cost->amountLabel() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td>Shipment cost</td>
                                <td class="num">{{ $shipment->shipment_cost ? \App\Models\Shipment::formatAmount($shipment->currency, $shipment->shipment_cost) : '-' }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($costSummary['inr'] > 0)
                        <tfoot>
                            <tr>
                                <td><strong>Total (₹)</strong></td>
                                <td class="num"><strong>{{ \App\Helpers\CommonHelper::indianCurrency($costSummary['inr']) }}</strong></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>

                @if ($costSummary['invoice_total'])
                    <p class="print-fine">
                        Linked invoice {{ $shipment->salesInvoice->invoice_number ?? '' }}
                        ({{ \App\Models\Shipment::formatAmount($costSummary['invoice_currency'], $costSummary['invoice_total']) }})
                        · margin after freight {{ \App\Helpers\CommonHelper::indianCurrency($costSummary['margin']) }}
                        @if ($costSummary['margin_percent'] !== null) ({{ $costSummary['margin_percent'] }}%) @endif
                    </p>
                @endif
            </section>
        </div>

        <section class="print-block">
            <h2>Paperwork</h2>
            <ul class="print-checklist">
                @foreach ($checklist as $row)
                    @continue(! $row['required'] && ! $row['done'])
                    <li class="{{ $row['done'] ? 'is-done' : 'is-missing' }}">
                        {{ $row['done'] ? '✓' : '✗' }} {{ $row['label'] }}
                        @if ($row['required'] && ! $row['done'])<em>(required — missing)</em>@endif
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="print-block">
            <h2>Latest tracking</h2>
            <table class="print-table print-table-compact">
                <tbody>
                    @forelse ($shipment->histories->take(8) as $history)
                        <tr>
                            <td>{{ $history->event_time?->format('d M Y, H:i') ?: '-' }}</td>
                            <td><strong>{{ $statusOptions[$history->status] ?? $history->status }}</strong>
                                @if ($history->location) · {{ $history->location }} @endif
                                @if ($history->remarks)<span class="print-sub">{{ $history->remarks }}</span>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="print-empty">No tracking updates yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </main>
</body>

</html>
