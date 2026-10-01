<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shipping Mark — {{ $shipment->shipment_number }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/shipping-mark.css') }}">
</head>

<body>
    @php
        $addressLine = function ($party) use ($shipment) {
            $parts = array_filter([
                $shipment->{$party.'_address'},
                $shipment->{$party.'_city'},
                $shipment->{$party.'_state'},
                trim(($shipment->{$party.'_country'} ?: '').' '.($shipment->{$party.'_pincode'} ?: '')),
            ], fn ($part) => trim((string) $part) !== '');

            return implode(', ', array_map('trim', $parts));
        };
        $contactLine = function ($party) use ($shipment) {
            return array_filter([
                $shipment->{$party.'_mobile'} ? 'Ph: '.$shipment->{$party.'_mobile'} : null,
                $shipment->{$party.'_email'} ? 'Email: '.$shipment->{$party.'_email'} : null,
            ]);
        };
    @endphp

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

        <button class="mark-btn mark-btn-primary" type="button" onclick="window.print()">🖨 Print stickers</button>
    </div>

    <div class="mark-sheet">
        @for ($copy = 1; $copy <= $copies; $copy++)
            <div class="mark">
                <div class="mark-head">
                    <div>
                        <span class="mark-title">Shipping Mark</span>
                        <span class="mark-sub">{{ $modeOptions[$shipment->shipment_mode] ?? $shipment->shipment_mode }}
                            · {{ $shipment->typeLabel() }}</span>
                    </div>
                    <div class="mark-number">{{ $shipment->shipment_number }}</div>
                </div>

                <div class="mark-parties">
                    <div class="mark-party">
                        <span class="mark-label">From (Shipper)</span>
                        <strong>{{ $shipment->from_name ?: '—' }}</strong>
                        <p>{{ $addressLine('from') ?: '—' }}</p>
                        @foreach ($contactLine('from') as $line)
                            <p class="mark-contact">{{ $line }}</p>
                        @endforeach
                    </div>
                    <div class="mark-arrow">→</div>
                    <div class="mark-party">
                        <span class="mark-label">To (Receiver)</span>
                        <strong>{{ $shipment->to_name ?: '—' }}</strong>
                        <p>{{ $addressLine('to') ?: '—' }}</p>
                        @foreach ($contactLine('to') as $line)
                            <p class="mark-contact">{{ $line }}</p>
                        @endforeach
                    </div>
                </div>

                <div class="mark-meta">
                    <div><span>Package</span><strong>{{ $copy }} of {{ $copies }}</strong></div>
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
                </div>

                <div class="mark-cut">✂ - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</div>
            </div>
        @endfor
    </div>
</body>

</html>
