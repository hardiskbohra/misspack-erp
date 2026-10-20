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

<body class="pdf-preview">
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
        <header class="print-head">
            <div>
                <span class="print-brand">{{ config('app.name', 'MissPack') }}</span>
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
            <div><span>Recorded by</span><strong>{{ $receipt->recorder->name ?? 'System' }}</strong></div>
            <div><span>Evidence document</span><strong>{{ $receipt->evidence_original_name ?: '-' }}</strong></div>
            <div><span>Evidence SHA-256</span><strong>{{ $receipt->evidence_hash ? substr($receipt->evidence_hash, 0, 24).'…' : '-' }}</strong></div>
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
            @if ($receipt->content_hash)
                <p><strong>Record integrity:</strong> SHA-256 {{ $receipt->content_hash }}</p>
            @endif
        </div>

        <div class="print-signs">
            <div><span>Delivered by</span></div>
            <div><span>Received by — signature</span></div>
            <div><span>Date</span></div>
        </div>
    </main>
</body>

</html>
