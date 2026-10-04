<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <title>Packing List — {{ $shipment->shipment_number }}</title>
    <link rel="stylesheet" href="{{ $assetVer('assets/css/shipment-print.css') }}">
    <link rel="stylesheet" href="{{ $assetVer('assets/css/document-print.css') }}">
</head>

<body class="pdf-preview">
    @include('shipments.print.partials.toolbar', ['document' => 'packing-list'])

    @php
        $rows = $items;
        $totalsByCurrency = $rows->groupBy(fn ($item) => $item->currency ?: $shipment->currency)
            ->map(fn ($group) => $group->sum(fn ($item) => (float) $item->declared_value));
        $totalNet = $rows->sum(fn ($item) => (float) $item->net_weight);
        $totalGross = $rows->sum(fn ($item) => (float) $item->gross_weight);
        $totalQty = $rows->sum(fn ($item) => (float) $item->quantity);
    @endphp

    <main class="print-sheet pdf-sheet">
        <header class="print-head">
            <div>
                <span class="print-brand">{{ config('app.name', 'MissPack') }}</span>
                <h1>Packing List</h1>
            </div>
            <div class="print-head-meta">
                <strong>{{ $shipment->shipment_number }}</strong>
                <span>{{ now()->format('d M Y') }}</span>
            </div>
        </header>

        <section class="print-meta">
            <div><span>Shipment</span><strong>{{ $shipment->identity_name }}</strong></div>
            <div><span>Type / Mode</span><strong>{{ $shipment->typeLabel() }} · {{ $modeOptions[$shipment->shipment_mode] ?? '-' }}</strong></div>
            <div><span>Pickup</span><strong>{{ optional($shipment->pickup_date)->format('d M Y') ?: '-' }}</strong></div>
            <div><span>ETA</span><strong>{{ optional($shipment->eta_date)->format('d M Y') ?: '-' }}</strong></div>
            <div><span>Logistic</span><strong>{{ $shipment->logistic_partner ?: '-' }}</strong></div>
            <div><span>Tracking</span><strong>{{ $shipment->tracking_number ?: '-' }}</strong></div>
            <div><span>Route</span><strong>{{ $shipment->origin_port ?: ($shipment->from_city ?: '-') }} → {{ $shipment->destination_port ?: ($shipment->to_city ?: '-') }}</strong></div>
            <div><span>Packages</span><strong>{{ $shipment->package_count ?: $rows->count() }}</strong></div>
        </section>

        @include('shipments.print.partials.parties')

        <table class="print-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>HS Code</th>
                    <th class="num">Qty</th>
                    <th class="num">Net Wt (kg)</th>
                    <th class="num">Gross Wt (kg)</th>
                    <th class="num">Declared Value</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $index => $item)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>
                            <strong>{{ $item->product_name }}</strong>
                            @if ($item->description)<span class="print-sub">{{ $item->description }}</span>@endif
                        </td>
                        <td>{{ $item->sku ?: '-' }}</td>
                        <td>{{ $item->hs_code ?: '-' }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity, 3), '0'), '.') }} {{ $item->unit }}</td>
                        <td class="num">{{ $item->net_weight ? number_format((float) $item->net_weight, 3) : '-' }}</td>
                        <td class="num">{{ $item->gross_weight ? number_format((float) $item->gross_weight, 3) : '-' }}</td>
                        <td class="num">{{ $item->declared_value ? \App\Models\Shipment::formatAmount($item->currency ?: $shipment->currency, $item->declared_value) : '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="print-empty">No products added to this shipment.</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4"><strong>Total</strong></td>
                    <td class="num"><strong>{{ rtrim(rtrim(number_format($totalQty, 3), '0'), '.') }}</strong></td>
                    <td class="num"><strong>{{ number_format($totalNet, 3) }}</strong></td>
                    <td class="num"><strong>{{ number_format($totalGross, 3) }}</strong></td>
                    <td class="num"><strong>{{ \App\Models\Shipment::formatTotals($totalsByCurrency->all()) }}</strong></td>
                </tr>
            </tfoot>
        </table>

        <div class="print-notes">
            <p><strong>Total packages:</strong> {{ $shipment->package_count ?: $rows->count() }}
                · <strong>Gross weight:</strong> {{ $shipment->gross_weight ? number_format((float) $shipment->gross_weight, 3).' kg' : '-' }}
                · <strong>Chargeable weight:</strong> {{ $shipment->chargeable_weight ? number_format((float) $shipment->chargeable_weight, 3).' kg' : '-' }}
            </p>
            @if ($shipment->notes)
                <p><strong>Notes:</strong> {{ $shipment->notes }}</p>
            @endif
            <p class="print-fine">Goods described above are packed as per the buyer's instructions. This packing list is
                issued for logistics and customs purposes and is not a tax invoice.</p>
        </div>

        <div class="print-signs">
            <div><span>Prepared by</span></div>
            <div><span>Checked by</span></div>
            <div><span>Receiver's signature &amp; date</span></div>
        </div>
    </main>
</body>

</html>
