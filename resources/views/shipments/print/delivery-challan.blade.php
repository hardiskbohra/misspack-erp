<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Challan — {{ $shipment->shipment_number }}</title>
    <link rel="stylesheet" href="{{ $assetVer('assets/css/shipment-print.css') }}">
</head>

<body>
    @include('shipments.print.partials.toolbar', ['document' => 'delivery-challan'])

    <div class="print-sheet">
        <header class="print-head">
            <div>
                <span class="print-brand">{{ config('app.name', 'MissPack') }}</span>
                <h1>Delivery Challan</h1>
            </div>
            <div class="print-head-meta">
                <strong>{{ $shipment->shipment_number }}</strong>
                <span>{{ now()->format('d M Y') }}</span>
            </div>
        </header>

        <section class="print-meta">
            <div><span>Shipment</span><strong>{{ $shipment->identity_name }}</strong></div>
            <div><span>Mode</span><strong>{{ $modeOptions[$shipment->shipment_mode] ?? '-' }}</strong></div>
            <div><span>Mode of transport</span><strong>{{ $shipment->logistic_partner ?: '-' }}</strong></div>
            <div><span>Vehicle / AWB / BL</span><strong>{{ $shipment->tracking_number ?: '-' }}</strong></div>
            <div><span>Dispatch date</span><strong>{{ optional($shipment->pickup_date)->format('d M Y') ?: '-' }}</strong></div>
            <div><span>Packages</span><strong>{{ $shipment->package_count ?: $items->count() }}</strong></div>
        </section>

        @include('shipments.print.partials.parties')

        <table class="print-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Description of goods</th>
                    <th>HS Code</th>
                    <th class="num">Quantity</th>
                    <th class="num">Weight (kg)</th>
                    <th class="num">Value</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $index => $item)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td><strong>{{ $item->product_name }}</strong>
                            @if ($item->description)<span class="print-sub">{{ $item->description }}</span>@endif
                        </td>
                        <td>{{ $item->hs_code ?: '-' }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity, 3), '0'), '.') }} {{ $item->unit }}</td>
                        <td class="num">{{ $item->gross_weight ? number_format((float) $item->gross_weight, 3) : '-' }}</td>
                        <td class="num">{{ $item->declared_value ? \App\Models\Shipment::formatAmount($item->currency ?: $shipment->currency, $item->declared_value) : '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="print-empty">No products added to this shipment.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="print-notes">
            <p><strong>Purpose:</strong> Dispatch of the goods listed above to the consignee. This is a delivery
                challan, not a tax invoice — no GST is charged on this document.</p>
            <p><strong>Total packages:</strong> {{ $shipment->package_count ?: $items->count() }}
                · <strong>Gross weight:</strong> {{ $shipment->gross_weight ? number_format((float) $shipment->gross_weight, 3).' kg' : '-' }}</p>
            @if ($shipment->notes)<p><strong>Notes:</strong> {{ $shipment->notes }}</p>@endif
        </div>

        <div class="print-signs">
            <div><span>Dispatched by</span></div>
            <div><span>Carrier / Driver</span></div>
            <div><span>Received in good condition — signature &amp; date</span></div>
        </div>
    </div>
</body>

</html>
