@php
    /*
     | One shipping-mark sticker. Shared by the single-shipment shipping mark
     | page (copies of one shipment) and the bulk sticker sheet (one sticker
     | per open shipment), so both always print the same label.
     |
     | Geometry: the office prints on 14 × 20 cm paper; with 6 mm margins that
     | leaves 128 × 188 mm, which is three stickers of 128 × 62.3 mm stacked.
     | Everything is sized in mm and the address blocks are clamped, so a record
     | with a long address cannot push the branding off the label or overlap the
     | row below it.
     |
     | Expects: $shipment. Optional: $markCopy (e.g. "3 of 8").
     */
    $markCopy = $markCopy ?? '—';
    $brand = config('brand');

    /* Scanning the sticker lands on the public tracking page when the shipment
       has a share token; otherwise it opens the internal record. */
    $markTrackingUrl = $markTrackingUrl ?? ($shipment->public_token
        ? route('shipments.publicTrack', $shipment->public_token)
        : route('shipments.show', $shipment));

    $markAddressLine = function ($party) use ($shipment) {
        $parts = array_filter([
            $shipment->{$party.'_address'},
            $shipment->{$party.'_city'},
            $shipment->{$party.'_state'},
            trim(($shipment->{$party.'_country'} ?: '').' '.($shipment->{$party.'_pincode'} ?: '')),
        ], fn ($part) => trim((string) $part) !== '');

        return implode(', ', array_map('trim', $parts));
    };

    $markContactLine = function ($party) use ($shipment) {
        return array_filter([
            $shipment->{$party.'_mobile'} ? 'Ph: '.$shipment->{$party.'_mobile'} : null,
            $shipment->{$party.'_email'} ? 'Email: '.$shipment->{$party.'_email'} : null,
        ]);
    };
@endphp

<div class="mark">
    <header class="mark-head">
        <img class="mark-brand" src="{{ asset($brand['logo_print']) }}"
            alt="{{ $brand['name'] }} — {{ $brand['tagline'] }}">

        <div class="mark-head-right">
            <span class="mark-title">Shipping Mark</span>
            <span class="mark-sub">
                {{ ($modeOptions ?? [])[$shipment->shipment_mode] ?? $shipment->shipment_mode }}
                · {{ $shipment->typeLabel() }}
            </span>
        </div>

        <div class="mark-number">{{ $shipment->shipment_number }}</div>
    </header>

    <div class="mark-parties">
        <div class="mark-party">
            <span class="mark-label">From (Shipper)</span>
            <strong>{{ $shipment->from_name ?: '—' }}</strong>
            <p class="mark-address">{{ $markAddressLine('from') ?: '—' }}</p>
            @if ($markContactLine('from'))
                <p class="mark-contact">{{ implode(' · ', $markContactLine('from')) }}</p>
            @endif
        </div>

        <div class="mark-arrow" aria-hidden="true">→</div>

        <div class="mark-party">
            <span class="mark-label">To (Receiver)</span>
            <strong>{{ $shipment->to_name ?: '—' }}</strong>
            <p class="mark-address">{{ $markAddressLine('to') ?: '—' }}</p>
            @if ($markContactLine('to'))
                <p class="mark-contact">{{ implode(' · ', $markContactLine('to')) }}</p>
            @endif
        </div>
    </div>

    <div class="mark-meta">
        <div>
            <span class="mark-label">Package</span>
            <strong>{{ $markCopy }}</strong>
        </div>
        <div>
            <span class="mark-label">Gross wt.</span>
            <strong>{{ $shipment->gross_weight ? rtrim(rtrim(number_format((float) $shipment->gross_weight, 3), '0'), '.').' kg' : '—' }}</strong>
        </div>
        <div class="mark-meta-destination">
            <span class="mark-label">Destination</span>
            <strong>{{ trim(($shipment->to_city ?: '').($shipment->to_country ? ', '.$shipment->to_country : ''), ', ') ?: '—' }}</strong>
        </div>
        @if ($shipment->shipment_label)
            <div class="mark-label-cell">
                <span class="mark-label">Label</span>
                <strong class="mark-label-chip">{{ $shipment->shipment_label }}</strong>
            </div>
        @endif
    </div>

    <footer class="mark-foot">
        <div class="mark-brandfoot">
            <span class="mark-website">{{ $brand['website'] }}</span>
            <span class="mark-tagline">{{ $brand['name'] }} · {{ $brand['tagline'] }}</span>
        </div>

        <div class="mark-codes">
            @if ($markTrackingUrl)
                <figure class="mark-qr">
                    {{-- Filled in the browser by assets/js/qr.js — generated
                         locally: no image service, no external request. A 3
                         module quiet zone and a white plate keep the code
                         scannable next to the address text. --}}
                    <div class="mark-qr-code" data-ship-qr="{{ $markTrackingUrl }}"
                        data-ship-qr-quiet="3" data-ship-qr-dark="#000000"
                        data-ship-qr-caption="Track {{ $shipment->shipment_number }}"></div>
                    <figcaption class="mark-qr-hint">Scan to track</figcaption>
                </figure>
            @endif

            <figure class="mark-qr mark-qr-social">
                <div class="mark-qr-code" data-ship-qr="{{ $brand['instagram'] }}"
                    data-ship-qr-quiet="3" data-ship-qr-dark="#000000"
                    data-ship-qr-caption="{{ $brand['name'] }} on Instagram"></div>
                <figcaption class="mark-qr-hint">{{ $brand['instagram_handle'] }}</figcaption>
            </figure>
        </div>
    </footer>
</div>
