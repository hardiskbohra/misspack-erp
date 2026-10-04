@php($document = $document ?? 'summary')
<div class="pdf-toolbar print-toolbar no-print">
    <a class="pdf-action pdf-action--secondary" href="{{ route('shipments.show', $shipment) }}">← Back to shipment</a>

    <span class="pdf-toolbar-group print-toolbar-links">
        @foreach (['packing-list' => 'Packing list', 'delivery-challan' => 'Challan', 'summary' => 'Summary'] as $key => $label)
            <a class="pdf-action {{ $document === $key ? 'pdf-action--selected' : '' }}"
                href="{{ route('shipments.print', [$shipment, $key]) }}">{{ $label }}</a>
        @endforeach
    </span>

    <button class="pdf-action pdf-action--primary" type="button" onclick="window.print()">Print / Save as PDF</button>
</div>

@if (! empty($pdfFallbackMessage))
    <p class="pdf-notice print-note no-print">{{ $pdfFallbackMessage }}</p>
@endif
