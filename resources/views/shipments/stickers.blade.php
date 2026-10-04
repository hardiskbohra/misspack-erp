<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sticker Sheet — Open Shipments</title>
    <link rel="stylesheet" href="{{ $assetVer('assets/css/shipping-mark.css') }}">
</head>

<body>
    <div class="mark-toolbar no-print">
        <a class="mark-btn" href="{{ route('shipments.index') }}">← Back to shipments</a>

        <form class="mark-copies" method="GET" action="{{ route('shipments.stickers') }}">
            <label for="copies">Stickers each</label>
            <input id="copies" type="number" name="copies" min="1" max="8" value="{{ $copies }}">

            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">All open</option>
                @foreach ($statusOptions as $value => $label)
                    @if (! in_array($value, \App\Models\Shipment::closedStatuses(), true))
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endif
                @endforeach
            </select>

            <button class="mark-btn" type="submit">Apply</button>
        </form>

        <span class="mark-hint">
            {{ $shipments->count() }} open shipment{{ $shipments->count() === 1 ? '' : 's' }}
            · {{ $copies }} sticker{{ $copies === 1 ? '' : 's' }} each
            · {{ $shipments->count() * $copies }} sticker{{ $shipments->count() * $copies === 1 ? '' : 's' }} total
            · {{ $perPage }} per label (85 × 130 mm)
            @if ($shipments->count() >= $limit)
                · showing the first {{ $limit }}
            @endif
        </span>

        <button class="mark-btn mark-btn-primary" type="button" onclick="window.print()">🖨 Print stickers</button>
    </div>

    <div class="mark-sheet">
        @forelse ($shipments as $shipment)
            @for ($copy = 1; $copy <= $copies; $copy++)
                @include('shipments.partials.sticker', [
                    'shipment' => $shipment,
                    'markCopy' => $shipment->package_count
                        ? $copy.' of '.max($copies, (int) $shipment->package_count)
                        : $copy.' of '.$copies,
                ])
            @endfor
        @empty
            <p class="mark-empty">No open shipments to print stickers for.</p>
        @endforelse
    </div>

    {{-- Same local QR encoder as the single-shipment mark page. --}}
    <script src="{{ $assetVer('assets/js/qr.js') }}"></script>
</body>

</html>
