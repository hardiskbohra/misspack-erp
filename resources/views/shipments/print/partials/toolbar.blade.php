@php($document = $document ?? 'summary')
<div class="print-toolbar no-print">
    <a class="print-btn" href="{{ route('shipments.show', $shipment) }}">← Back to shipment</a>

    <span class="print-toolbar-links">
        @foreach (['packing-list' => 'Packing list', 'delivery-challan' => 'Challan', 'summary' => 'Summary'] as $key => $label)
            <a class="print-btn {{ $document === $key ? 'is-active' : '' }}"
                href="{{ route('shipments.print', [$shipment, $key]) }}">{{ $label }}</a>
        @endforeach
    </span>

    <button class="print-btn print-btn-primary" type="button" onclick="window.print()">🖨 Print / Save as PDF</button>
</div>

@if (! empty($pdfFallbackMessage))
    <p class="print-note no-print">{{ $pdfFallbackMessage }}</p>
@endif
