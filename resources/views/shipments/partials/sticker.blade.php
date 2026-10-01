@php
    /*
     | One shipping-mark sticker. Shared by the single-shipment shipping mark
     | page (copies of one shipment) and the bulk sticker sheet (one sticker
     | per open shipment), so both always print the same label.
     |
     | Expects: $shipment. Optional: $markCopy (e.g. "3 of 8").
     */
    $markCopy = $markCopy ?? '—';

    // Scanning the sticker should land on the public tracking page when the
    // shipment has a share token; otherwise it opens the internal record for
    // staff who are already signed in.
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
    <div class="mark-head">
        <div>
            <span class="mark-title">Shipping Mark</span>
            <span class="mark-sub">{{ ($modeOptions ?? [])[$shipment->shipment_mode] ?? $shipment->shipment_mode }}
                · {{ $shipment->typeLabel() }}</span>
        </div>
        <div class="mark-number">{{ $shipment->shipment_number }}</div>
    </div>

    <div class="mark-parties">
        <div class="mark-party">
            <span class="mark-label">From (Shipper)</span>
            <strong>{{ $shipment->from_name ?: '—' }}</strong>
            <p>{{ $markAddressLine('from') ?: '—' }}</p>
            @if ($markContactLine('from'))
                <p class="mark-contact">{{ implode(' · ', $markContactLine('from')) }}</p>
            @endif
        </div>
        <div class="mark-arrow">→</div>
        <div class="mark-party">
            <span class="mark-label">To (Receiver)</span>
            <strong>{{ $shipment->to_name ?: '—' }}</strong>
            <p>{{ $markAddressLine('to') ?: '—' }}</p>
            @if ($markContactLine('to'))
                <p class="mark-contact">{{ implode(' · ', $markContactLine('to')) }}</p>
            @endif
        </div>
    </div>

    <div class="mark-meta">
        <div><span>Package</span><strong>{{ $markCopy }}</strong></div>
        <div><span>Gross wt.</span><strong>{{ $shipment->gross_weight ? rtrim(rtrim(number_format((float) $shipment->gross_weight, 3), '0'), '.').' kg' : '—' }}</strong></div>
        <div><span>Tracking</span><strong>{{ $shipment->tracking_number ?: '—' }}</strong></div>
        <div><span>Logistic</span><strong>{{ $shipment->logistic_partner ?: '—' }}</strong></div>
    </div>

    <div class="mark-foot">
        <div class="mark-destination">
            <span class="mark-label">Destination</span>
            <strong>{{ trim(($shipment->to_city ?: '').($shipment->to_country ? ', '.$shipment->to_country : ''), ', ') ?: '—' }}</strong>
        </div>
        @if ($shipment->shipment_label)
            <div class="mark-label-chip">{{ $shipment->shipment_label }}</div>
        @endif
        <div class="mark-ref">
            <span class="mark-label">Ref</span>
            <strong>{{ $shipment->identity_name }}</strong>
        </div>

        @if ($markTrackingUrl)
            <div class="mark-qr">
                {{-- Filled in the browser by assets/js/qr.js — the code is
                     generated locally: no image service, no external call. --}}
                <div class="mark-qr-code" data-ship-qr="{{ $markTrackingUrl }}"
                    data-ship-qr-caption="Track {{ $shipment->shipment_number }}"></div>
                <span class="mark-qr-hint">Scan to track</span>
            </div>
        @endif
    </div>

    <div class="mark-cut">✂ - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</div>
</div>
