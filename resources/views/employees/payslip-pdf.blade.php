<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payslip — {{ $doc['employee']['name'] }} — {{ $doc['slip']['period'] }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/payslip.css') }}">
</head>

{{-- The same slip the screen shows, stripped of the app: what the printer
     prints, and what dompdf renders when it is installed. --}}
<body class="ps-standalone" @if (! empty($pdfFallbackMessage)) onload="setTimeout(function () { window.print(); }, 500)" @endif>
    @if (! empty($pdfFallbackMessage))
        <div class="ps-notice no-print">{{ $pdfFallbackMessage }}</div>
    @endif

    @include('employees.partials.payslip', ['doc' => $doc, 'context' => 'pdf'])

    <p class="ps-print-foot no-print">
        {{ $doc['issuer']['name'] }} — printed {{ now()->format('d M Y, h:i A') }}
    </p>
</body>

</html>
