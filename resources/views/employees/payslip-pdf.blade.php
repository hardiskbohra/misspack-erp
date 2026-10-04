<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <title>Payslip — {{ $doc['employee']['name'] }} — {{ $doc['slip']['period'] }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/payslip.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/document-print.css') }}">
</head>

{{-- The same slip the screen shows, stripped of the app: what the printer
     prints, and what dompdf renders when it is installed. --}}
<body class="pdf-preview ps-standalone" @if (! empty($pdfFallbackMessage)) onload="setTimeout(function () { window.print(); }, 500)" @endif>
    @if (! empty($pdfFallbackMessage))
        <div class="ps-notice no-print">{{ $pdfFallbackMessage }}</div>
    @endif

    @include('employees.partials.payslip', ['doc' => $doc, 'context' => 'pdf'])

    <p class="ps-print-foot no-print">
        {{ $doc['issuer']['name'] }} — printed {{ now()->format('d M Y, h:i A') }}
    </p>
</body>

</html>
