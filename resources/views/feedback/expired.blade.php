<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Feedback link unavailable</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="{{ asset('assets/css/feedback.css') }}">
    @include('layouts.partials.design-system-styles')
</head>

@php
    /* Four ways a link stops working, and they are not the same news. Saying
       which one it is saves the client from ringing up to ask — and saves the
       office from explaining a door that was closed on purpose. */
    $heading = match ($reason ?? 'link') {
        'revoked' => 'This feedback link was withdrawn',
        'expired' => 'This feedback link has expired',
        'answered' => 'This one has already been answered',
        default => 'This link does not open anything',
    };

    $body = match ($reason ?? 'link') {
        'revoked' => 'The sender closed this link. Ask them for a fresh one if you would still like to tell us how the project went.',
        'expired' => 'Feedback links are valid for a limited time so that the project is still fresh in mind. Ask your MissPack contact for a new link and it will open straight away.',
        'answered' => 'A reply for this project is already with us. If something has changed since, tell your MissPack contact.',
        default => 'The link may be incomplete, or it may belong to a request that no longer exists. Ask your MissPack contact and we will send a new one.',
    };
@endphp

<body class="fb fb-standalone fb-centered" data-ui-shell="public">
    <div class="fb-thanks">
        <p class="fb-thanks-mark fb-thanks-mark--muted" aria-hidden="true">🔒</p>
        <h1 class="fb-thanks-title">{{ $heading }}</h1>
        <p class="fb-thanks-text">{{ $body }}</p>

        @if (! empty($ask))
            <dl class="fb-expired-facts">
                <div>
                    <dt>Project</dt>
                    <dd>{{ $ask->project?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt>Ask</dt>
                    <dd>{{ $ask->title() }}</dd>
                </div>
                <div>
                    <dt>Issued</dt>
                    <dd>{{ $ask->created_at?->format('d M Y') }}</dd>
                </div>
                <div>
                    <dt>Status</dt>
                    <dd>{{ $ask->stateLabel() }}</dd>
                </div>
            </dl>
        @endif

        <p class="fb-thanks-foot">
            {{ \App\Models\SalesInvoice::defaultSellerDetails()['seller_company_name'] }} ·
            {{ \App\Models\SalesInvoice::defaultSellerDetails()['seller_email'] }} ·
            {{ \App\Models\SalesInvoice::defaultSellerDetails()['seller_mobile'] }}
        </p>
    </div>
</body>

</html>
