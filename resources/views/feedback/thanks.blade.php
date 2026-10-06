<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Thank you — MissPack</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="{{ asset('assets/css/feedback.css') }}">
    @include('layouts.partials.design-system-styles')
</head>

@php
    /* Two ways a person lands here: they just sent an answer, or they opened a
       link that somebody had already answered. The second is not a mistake —
       links get forwarded — so it is a thank-you with a sentence of explanation
       rather than an error. */
    $already = $already || $ask->hasAnswered();
    $ownerName = $ask->project?->assignedUser?->name ?: $ask->creator?->name;
@endphp

<body class="fb-standalone fb-centered" data-ui-shell="public">
    <div class="fb-thanks">
        <p class="fb-thanks-mark" aria-hidden="true">✓</p>
        <h1 class="fb-thanks-title">{{ $already ? 'This one has been answered' : 'Thank you — that has been read' }}</h1>

        <p class="fb-thanks-text">
            @if ($already)
                A reply for {{ $ask->project?->name ?? 'this project' }} is already with us, so this form is closed.
                If something has changed since, tell your MissPack contact — a fresh link takes a moment to send.
            @else
                Your answers have gone straight to the people responsible for
                {{ $ask->project?->name ?? 'your project' }}.
                @if ($ownerName)
                    {{ $ownerName }} has been named against it.
                @endif
            @endif
        </p>

        <ul class="fb-thanks-list">
            <li>A low score opens a follow-up with an owner and a two-day clock — nobody has to notice it for something to happen.</li>
            <li>If you asked us not to quote you, the words stay inside the MissPack team.</li>
            <li>A person, not a dashboard, reads every answer.</li>
        </ul>

        <p class="fb-thanks-foot">
            {{ \App\Models\SalesInvoice::defaultSellerDetails()['seller_company_name'] }} ·
            {{ \App\Models\SalesInvoice::defaultSellerDetails()['seller_email'] }}
        </p>
    </div>
</body>

</html>
