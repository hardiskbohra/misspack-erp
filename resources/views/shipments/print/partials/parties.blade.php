@php
    $addressBlock = function (string $party) use ($shipment) {
        $lines = array_filter([
            $shipment->{$party.'_address'},
            trim(implode(', ', array_filter([
                $shipment->{$party.'_city'},
                $shipment->{$party.'_state'},
            ]))),
            trim(($shipment->{$party.'_country'} ?: '').' '.($shipment->{$party.'_pincode'} ?: '')),
        ], fn ($line) => trim((string) $line) !== '');

        return implode('<br>', array_map('e', $lines));
    };
@endphp

<div class="print-parties">
    <div class="print-party">
        <span class="print-label">From (Shipper)</span>
        <strong>{{ $shipment->from_name ?: '—' }}</strong>
        <p>{!! $addressBlock('from') ?: '—' !!}</p>
        @if ($shipment->from_mobile || $shipment->from_email)
            <p class="print-contact">
                {{ $shipment->from_mobile ? 'Ph: '.$shipment->from_mobile : '' }}
                {{ $shipment->from_email ? ($shipment->from_mobile ? ' · ' : '').'Email: '.$shipment->from_email : '' }}
            </p>
        @endif
    </div>
    <div class="print-party">
        <span class="print-label">To (Consignee)</span>
        <strong>{{ $shipment->to_name ?: '—' }}</strong>
        <p>{!! $addressBlock('to') ?: '—' !!}</p>
        @if ($shipment->to_mobile || $shipment->to_email)
            <p class="print-contact">
                {{ $shipment->to_mobile ? 'Ph: '.$shipment->to_mobile : '' }}
                {{ $shipment->to_email ? ($shipment->to_mobile ? ' · ' : '').'Email: '.$shipment->to_email : '' }}
            </p>
        @endif
    </div>
</div>
