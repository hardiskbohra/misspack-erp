<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shipping Mark — {{ $shipment->shipment_number }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/shipping-mark.css') }}">
</head>

<body>
    <div class="mark-toolbar no-print">
        <a class="mark-btn" href="{{ route('shipments.show', $shipment) }}">← Back to shipment</a>

        <form class="mark-copies" method="GET" action="{{ route('shipments.shipping-mark', $shipment) }}">
            <label for="copies">Stickers</label>
            <input id="copies" type="number" name="copies" min="1" max="48" value="{{ $copies }}">
            <button class="mark-btn" type="submit">Apply</button>
        </form>

        <span class="mark-hint">
            {{ $copies }} sticker{{ $copies === 1 ? '' : 's' }} · {{ $shipment->package_count ? $shipment->package_count.' package(s) recorded' : 'no package count recorded' }} · {{ $perPage }} per A4 page
        </span>

        <a class="mark-btn" href="{{ route('shipments.stickers') }}">Whole open board →</a>

        <button class="mark-btn mark-btn-primary" type="button" onclick="window.print()">🖨 Print stickers</button>
    </div>

    <div class="mark-sheet">
        @for ($copy = 1; $copy <= $copies; $copy++)
            @include('shipments.partials.sticker', [
                'shipment' => $shipment,
                'markCopy' => $copy.' of '.$copies,
            ])
        @endfor
    </div>

    {{-- QR codes are generated in the browser by our own encoder (no image
         service, no external request); stickers still print fine without it. --}}
    <script src="{{ asset('assets/js/qr.js') }}"></script>
</body>

</html>
