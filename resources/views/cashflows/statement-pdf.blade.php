<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <title>Statement of account — {{ $statement['party']['name'] }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/statement.css') }}">
</head>

{{-- The same document the screen shows, stripped of the app: what goes to the
     printer, and what dompdf renders when it is installed. --}}
<body class="stmt-standalone stmt-print" @if (! empty($pdfFallbackMessage)) onload="setTimeout(function () { window.print(); }, 500)" @endif>
    @if (! empty($pdfFallbackMessage))
        <div class="stmt-notice no-print">{{ $pdfFallbackMessage }}</div>
    @endif

    @include('cashflows.partials.statement', ['statement' => $statement, 'context' => 'pdf'])

    <p class="stmt-print-foot no-print">
        {{ $statement['issuer']['seller_company_name'] }} — printed
        {{ now()->format('d M Y, h:i A') }}
    </p>
</body>

</html>
