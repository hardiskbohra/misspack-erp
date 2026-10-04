<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Statement link unavailable</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="{{ asset('assets/css/statement.css') }}">
    @include('layouts.partials.design-system-styles')
</head>

@php
    /* Three ways a link stops working, and they are not the same news: a link
       that ran out of time is routine, a link that was revoked was revoked on
       purpose, and a link nobody ever issued is not a link. Saying which one it
       is saves the reader from guessing — and from ringing up to ask. */
    $heading = match ($reason ?? 'link') {
        'revoked' => 'This statement link was withdrawn',
        'party' => 'This statement is no longer available',
        default => 'This statement link has expired',
    };

    $body = match ($reason ?? 'link') {
        'revoked' => 'The sender closed this link. Ask them for a fresh one — the statement itself is unchanged and takes a moment to reissue.',
        'party' => 'The account this statement was built for is no longer on the books, so there is nothing to show.',
        default => 'Statement links are valid for a limited time. Ask the sender for a new link and it will open straight away.',
    };
@endphp

<body class="stmt-standalone stmt-centered" data-ui-shell="public">
    <div class="stmt-expired">
        <p class="stmt-expired-mark" aria-hidden="true">🔒</p>
        <h1 class="stmt-expired-title">{{ $heading }}</h1>
        <p class="stmt-expired-text">{{ $body }}</p>

        @if (! empty($share))
            <dl class="stmt-expired-facts">
                <div>
                    <dt>Party</dt>
                    <dd>{{ $share->party_name }}</dd>
                </div>
                <div>
                    <dt>Statement</dt>
                    <dd>{{ $share->title() }} · {{ \App\Helpers\CommonHelper::currencyLabel($share->party_currency) }}</dd>
                </div>
                <div>
                    <dt>Issued</dt>
                    <dd>{{ $share->created_at?->format('d M Y') }}</dd>
                </div>
                <div>
                    <dt>Status</dt>
                    <dd>{{ $share->stateLabel() }}{{ $share->revoked_at ? ' on '.$share->revoked_at->format('d M Y') : '' }}</dd>
                </div>
            </dl>
        @endif

        <p class="stmt-expired-contact">
            {{ \App\Models\SalesInvoice::defaultSellerDetails()['seller_company_name'] }} ·
            {{ \App\Models\SalesInvoice::defaultSellerDetails()['seller_email'] }} ·
            {{ \App\Models\SalesInvoice::defaultSellerDetails()['seller_mobile'] }}
        </p>
    </div>
</body>

</html>
