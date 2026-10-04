@php
    /*
     | One shipping-mark sticker. Shared by the single-shipment shipping mark
     | page (copies of one shipment) and the bulk sticker sheet (one sticker
     | per open shipment), so both always print the same label.
     |
     | Geometry: the label is 85 × 130 mm, one sticker per label. Everything is
     | sized in mm. The addresses are the one thing that varies by record, so
     | they are measured: a long address steps down a size rather than being
     | cut off, because the last line holds the city, the pin code and the
     | country and a courier sorts by the pin code. The shipper and receiver
     | blocks are set a size apart (the receiver larger), which is why they
     | carry their own classes.
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

    /* The free-text address usually already ends with the city, state, pin code
       and country, and the record also carries them as fields. Printing both
       stretches the line — on a 77mm label that is the difference between an
       address that fits and one whose pin code is cut off — so anything the
       address already says is not repeated. */
    $markAddressLine = function ($party) use ($shipment) {
        $address = trim((string) $shipment->{$party.'_address'});
        $fold = fn ($value) => preg_replace('/[^a-z0-9]+/', '', mb_strtolower((string) $value));

        $tail = array_filter([
            $shipment->{$party.'_city'},
            $shipment->{$party.'_state'},
            trim(($shipment->{$party.'_country'} ?: '').' '.($shipment->{$party.'_pincode'} ?: '')),
        ], fn ($part) => trim((string) $part) !== '');

        $tail = array_filter($tail, fn ($part) => mb_strpos($fold($address), $fold($part)) === false);

        return implode(', ', array_filter([$address, implode(', ', array_map('trim', $tail))],
            fn ($part) => trim((string) $part) !== ''));
    };

    /* Characters the label fits per line, measured against the 77mm content
       width: about 40 at the receiver's 10pt and 44 at the shipper's 9pt. Three
       lines are what a base-size block affords (120 and 132 characters); past
       that the address drops to 8.5pt, where four lines hold 192 characters,
       and past 192 it drops to 7.5pt, where four lines hold 216. An address
       that does not fit steps down rather than being cut off — the last line
       carries the city, the pin code and the country — and the clamp is the
       last resort beyond the last step.
       tools/checks/mark-check.cjs re-derives both boundaries from these numbers
       and fails if a boundary address stops fitting the label. */
    $markAddressCapacity = ['to' => 40, 'from' => 44, 'compact' => 48];
    $markAddressLines = ['keep' => 3, 'step' => 4];

    $markAddressClass = function ($party) use ($shipment, $markAddressCapacity, $markAddressLines) {
        $length = mb_strlen((string) $shipment->{$party.'_address'});
        $keep = $markAddressCapacity[$party] * $markAddressLines['keep'];
        $step = $markAddressCapacity['compact'] * $markAddressLines['step'];

        return $length > $step ? 'is-longer' : ($length > $keep ? 'is-compact' : '');
    };

    /* The destination strip repeats the pin code on purpose: whatever happens to
       the address block, the pin code is printed where it is read. If the
       record has no pin code field, the last six-digit number in the address is
       used — that is where it sits in a written Indian address. */
    $markDestination = function () use ($shipment) {
        $pincode = trim((string) $shipment->to_pincode);
        if ($pincode === '' && preg_match('/\b(\d{6})\b/', (string) $shipment->to_address, $match)) {
            $pincode = $match[1];
        }

        $parts = array_filter([
            $shipment->to_city,
            $shipment->to_state,
            trim(($shipment->to_country ?: '').' '.$pincode),
        ], fn ($part) => trim((string) $part) !== '');

        return implode(', ', array_map('trim', $parts)) ?: '—';
    };

    /* Escaped here because the template joins them with <br>: raw values from
       the record would otherwise be markup on a printed label. */
    $markContactLine = function ($party) use ($shipment) {
        return array_map('e', array_filter([
            $shipment->{$party.'_mobile'} ? 'Mobile: '.$shipment->{$party.'_mobile'} : null,
            $shipment->{$party.'_email'} ? 'Email: '.$shipment->{$party.'_email'} : null,
        ]));
    };
@endphp

<div class="mark">
    <header class="mark-head">
        <img class="mark-brand" src="{{ asset($brand['logo_print']) }}"
            alt="{{ $brand['name'] }} — {{ $brand['tagline'] }}">

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
        </div>
    </header>

    <div class="mark-parties">
        <div class="mark-party mark-party-to {{ $markAddressClass('to') }}">
            <span class="mark-label">To (Receiver)</span>
            <strong>{{ $shipment->to_name ?: '—' }}</strong>
            <p class="mark-address">{{ $markAddressLine('to') ?: '—' }}</p>
            @if ($markContactLine('to'))
                <p class="mark-contact">{!! implode('<br>', $markContactLine('to')) !!}</p>
            @endif
        </div>

        <div class="mark-party mark-party-from {{ $markAddressClass('from') }}">
            <span class="mark-label">From (Shipper)</span>
            <strong>{{ $shipment->from_name ?: '—' }}</strong>
            <p class="mark-address">{{ $markAddressLine('from') ?: '—' }}</p>
            @if ($markContactLine('from'))
                <p class="mark-contact">{!! implode('<br>', $markContactLine('from')) !!}</p>
            @endif
        </div>
    </div>

    <div class="mark-meta">
        <div>
            <span class="mark-label">Package</span>
            <strong>{{ $markCopy }}</strong>
        </div>
        <div class="mark-meta-destination">
            <span class="mark-label">Destination</span>
            <strong>{{ $markDestination() }}</strong>
        </div>
    </div>

    <footer class="mark-foot">
        <div class="mark-brandfoot">
            <span class="mark-website">{{ $brand['website'] }}</span>
            <span class="mark-tagline">{{ $brand['name'] }} · {{ $brand['tagline'] }}</span>

            {{-- the shipment's own handling label, e.g. FRAGILE. It sits here
                 rather than in the meta strip because the codes already set the
                 footer's height: in the strip it would cost the address a line. --}}
            @if ($shipment->shipment_label)
                <strong class="mark-label-chip">{{ $shipment->shipment_label }}</strong>
            @endif
        </div>

        <div class="mark-codes">
            <figure class="mark-qr mark-qr-social">
                <div class="mark-qr-code" data-ship-qr="{{ $brand['instagram'] }}"
                    data-ship-qr-quiet="3" data-ship-qr-dark="#000000"
                    data-ship-qr-caption="{{ $brand['name'] }} on Instagram"></div>
                <figcaption class="mark-qr-hint">{{ $brand['instagram_handle'] }}</figcaption>
            </figure>
        </div>
    </footer>
</div>
