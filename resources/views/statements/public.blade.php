<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Statement of account — {{ $statement['party']['name'] }}</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="{{ asset('assets/css/statement.css') }}">
    @include('layouts.partials.design-system-styles')
</head>

{{-- What the party sees. No app chrome, no menu, no other party's name on the
     page: one statement, printable, and readable in the reader's own theme. --}}
<body class="stmt-standalone" data-ui-shell="public">
    <div class="stmt-public-bar no-print">
        <div>
            <p class="stmt-public-brand">{{ $statement['issuer']['seller_company_name'] }}</p>
            <p class="stmt-public-sub">Statement of account ·
                {{ $share->periodLabel() }} ·
                {{ \App\Helpers\CommonHelper::currencyLabel($share->party_currency) }}</p>
        </div>
        <div class="stmt-public-actions">
            <button class="stmt-public-btn" type="button" onclick="window.print()">Print / Save as PDF</button>
            @if ($share->expires_at)
                <span class="stmt-public-expiry">Link expires {{ $share->expires_at->format('d M Y') }}</span>
            @endif
        </div>
    </div>

    @include('cashflows.partials.statement', ['statement' => $statement, 'context' => 'public'])

    <footer class="stmt-public-foot">
        <p>
            Sent by {{ $statement['issuer']['seller_company_name'] }} on
            {{ $share->created_at?->format('d M Y') }}.
            @if ($share->expires_at)
                This link can be opened until {{ $share->expires_at->format('d M Y') }}.
            @endif
            Questions about a figure? Reply to whoever sent you this link.
        </p>
    </footer>
</body>

</html>
