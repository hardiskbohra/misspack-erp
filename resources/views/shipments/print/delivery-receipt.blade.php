@php
    $organisation = $organisation ?? \App\Models\Organisation::current();
    $issuerResolver = app(\App\Services\ShipmentPrintIssuer::class);
    $issuer = $issuerResolver->forShipment($shipment, $organisation);
@endphp
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <title>Delivery Receipt — {{ $shipment->shipment_number }}</title>
    <link rel="stylesheet" href="{{ $assetVer('assets/css/shipment-print.css') }}">
    <link rel="stylesheet" href="{{ $assetVer('assets/css/document-print.css') }}">
</head>

<body class="pdf-preview delivery-receipt">
    <div class="pdf-toolbar print-toolbar no-print">
        <a class="pdf-action pdf-action--secondary" href="{{ route('shipments.show', $shipment) }}">← Back to shipment</a>

        <span class="pdf-toolbar-group print-toolbar-links">
            <a class="pdf-action" href="{{ route('shipments.print', [$shipment, 'delivery-challan']) }}">Challan</a>
            <a class="pdf-action" href="{{ route('shipments.print', [$shipment, 'summary']) }}">Summary</a>
            <a class="pdf-action pdf-action--selected" href="{{ route('shipments.delivery-receipts.print', [$shipment, $receipt]) }}">Receipt</a>
        </span>

        <button class="pdf-action pdf-action--primary" type="button" onclick="window.print()">Print / Save as PDF</button>
    </div>

    @if (! empty($pdfFallbackMessage))
        <p class="pdf-notice print-note no-print">{{ $pdfFallbackMessage }}</p>
    @endif

    <main class="print-sheet pdf-sheet">
        <div class="receipt-letterhead">
            @if ($issuer['logo_url'])
                <img class="receipt-logo" src="{{ $issuer['logo_url'] }}" alt="{{ $issuer['name'] }}">
            @endif
            @if ($issuer['name'])<strong class="receipt-issuer-name">{{ $issuer['name'] }}</strong>@endif
            @if ($issuer['address'])<p>{{ $issuer['address'] }}</p>@endif
            @if ($issuer['mobile'] || $issuer['email'])
                <p>{{ $issuer['mobile'] ? 'Ph: '.$issuer['mobile'] : '' }}{{ $issuer['mobile'] && $issuer['email'] ? ' · ' : '' }}{{ $issuer['email'] }}</p>
            @endif
        </div>

        <header class="print-head">
            <div>
                <h1>Delivery Receipt</h1>
            </div>
            <div class="print-head-meta">
                <strong>Receipt #{{ $receipt->id }}</strong>
                <span>{{ $receipt->received_at?->format('d M Y, H:i') ?: '-' }}</span>
            </div>
        </header>

        <section class="print-meta">
            <div><span>Shipment</span><strong>{{ $shipment->shipment_number }} · {{ $shipment->identity_name }}</strong></div>
            <div><span>Receipt / POD reference</span><strong>{{ $receipt->receipt_reference ?: 'Not supplied' }}</strong></div>
            <div><span>Recipient</span><strong>{{ $receipt->recipient_name }}</strong></div>
            <div><span>Received at</span><strong>{{ $receipt->received_at?->format('d M Y, H:i') ?: '-' }}</strong></div>
            <div><span>Project / Client</span><strong>{{ $shipment->project->project_number ?? '-' }} · {{ $shipment->client->company_name ?? '-' }}</strong></div>
        </section>

        <section class="print-party receipt-consignee">
            <span class="print-label">Deliver to (Receiver)</span>
            <strong>{{ $shipment->to_name ?: '—' }}</strong>
            @if ($receiverAddress = $issuerResolver->address($shipment, 'to'))<p>{{ $receiverAddress }}</p>@endif
        </section>

        <table class="print-table print-table-compact">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th class="num">Received</th>
                    <th class="num">Accepted</th>
                    <th class="num">Short</th>
                    <th class="num">Damaged</th>
                    <th class="num">Rejected</th>
                    <th class="num">Returned</th>
                    <th class="num">Replacement</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($receipt->lines as $index => $line)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>
                            <strong>{{ $line->shipmentItem->product_name ?? 'Product #'.$line->shipment_item_id }}</strong>
                            @if ($line->shipmentItem?->unit)<span class="print-sub">Unit: {{ $line->shipmentItem->unit }}</span>@endif
                            @if ($line->discrepancy_reason)
                                <span class="print-sub">{{ $line->discrepancy_reason }}</span>
                            @endif
                        </td>
                        <td class="num">{{ number_format((float) $line->received_quantity, 3) }}</td>
                        <td class="num">{{ number_format((float) $line->accepted_quantity, 3) }}</td>
                        <td class="num">{{ number_format((float) $line->short_quantity, 3) }}</td>
                        <td class="num">{{ number_format((float) $line->damaged_quantity, 3) }}</td>
                        <td class="num">{{ number_format((float) $line->rejected_quantity, 3) }}</td>
                        <td class="num">{{ number_format((float) $line->returned_quantity, 3) }}</td>
                        <td class="num">{{ number_format((float) $line->replacement_quantity, 3) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="print-empty">No delivery lines were recorded.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="print-notes">
            <p><strong>Outcome:</strong> {{ $receipt->hasDiscrepancy() ? 'Received with discrepancies — see the line reasons above.' : 'Received in accepted condition with no discrepancy.' }}</p>
            @if ($receipt->notes)<p><strong>Notes:</strong> {{ $receipt->notes }}</p>@endif
        </div>

        <section class="receipt-acknowledgement">
            <h2>Receiver acknowledgement</h2>
            <p>I/We acknowledge receipt of the goods in the quantities and condition recorded above,
                including any discrepancies noted on this receipt.</p>
            <div class="print-signs receipt-signs">
                <div class="receipt-signature">
                    <span>Receiver signature</span>
                    <div class="receipt-signature-space"></div>
                    <p class="receipt-write-line">Name / designation:</p>
                    <p class="receipt-write-line">Date / time:</p>
                </div>
                <div class="receipt-stamp">
                    <span>Company stamp</span>
                    <div class="receipt-stamp-space"></div>
                </div>
            </div>
            <p class="receipt-delivered-by">Delivered by (name / signature): <span></span></p>
        </section>
    </main>
</body>

</html>
