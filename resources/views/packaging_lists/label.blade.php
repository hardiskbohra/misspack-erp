<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $labelTitle ?? ($carton->barcode.' · Carton Label') }}</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="{{ $assetVer('assets/css/packaging-list-label.css') }}"><style>@page{size:{{ $format === 'thermal' ? '100mm 150mm' : ($format === 'a5' ? 'A5 landscape' : 'A4 landscape') }};margin:0}</style></head><body class="label-page format-{{ $format }}" data-auto-print="{{ ($autoPrint ?? false) ? 'true' : 'false' }}">
<div class="label-toolbar">
    <p>{{ number_format($copies) }} {{ \Illuminate\Support\Str::plural('label', $copies) }} · {{ $format === 'thermal' ? '100 × 150 mm' : strtoupper($format) }} · One label per page. Choose “Save as PDF” in the print dialog to download.</p>
    <button type="button" data-print-labels>Print / save PDF</button>
    @if (isset($backUrl))<a href="{{ $backUrl }}">Back to packaging list</a>@else<button type="button" onclick="window.close()">Close</button>@endif
    <p role="alert" data-label-print-error hidden></p>
</div>
@php($labelBatches = $labelBatches ?? [['carton' => $carton, 'copies' => $copies, 'startCartonNumber' => $startCartonNumber]])
@foreach ($labelBatches as $batch)
    @for ($copy = 0; $copy < $batch['copies']; $copy++)
        @php($cartonNumber = $batch['startCartonNumber'] + $copy)
        @include('packaging_lists.partials.carton-label', ['carton' => $batch['carton']])
    @endfor
@endforeach
<script src="{{ $assetVer('assets/js/qr.js') }}"></script><script src="{{ $assetVer('assets/js/packaging-list-label-print.js') }}" defer></script></body></html>
