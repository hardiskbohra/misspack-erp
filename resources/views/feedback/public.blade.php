<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>How did we do? — {{ $ask->project?->name ?? 'MissPack' }}</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/feedback.css') }}">
    {{-- The shared control shape — `.master-input`, `.master-textarea`, the
         field label — lives in the shared form sheet, the same one the portal's
         own standalone login page loads. A one-page public form must not invent
         a second control of its own. --}}
    <link rel="stylesheet" href="{{ asset('assets/css/master-form.css') }}">
    @include('layouts.partials.design-system-styles')
</head>

{{-- What the client opens from the link. No app chrome, no menu, no other
     client's name anywhere: one project, one form, readable in the reader's own
     theme and on a phone in one hand. --}}
<body class="fb fb-standalone" data-ui-shell="public">
    <header class="fb-public-bar">
        <div>
            <p class="fb-public-brand">{{ $ask->project?->client?->company_name ?: \App\Models\SalesInvoice::defaultSellerDetails()['seller_company_name'] }}</p>
            <p class="fb-public-sub">{{ $ask->title() }} · {{ $ask->project?->name ?? 'your project' }}</p>
        </div>
        @if ($ask->expires_at)
            <span class="fb-public-expiry">Link open until {{ $ask->expires_at->format('d M Y') }}</span>
        @endif
    </header>

    <main class="fb-public-main">
        <div class="fb-intro">
            <h1>{{ $ask->project?->name ? 'How did '.$ask->project->name.' go?' : 'How did we do?' }}</h1>
            <p>{{ $intro }}</p>
            @if ($ask->note)
                <p class="fb-intro-note">{{ $ask->note }}</p>
            @endif
        </div>

        @include('feedback.partials.form', [
            'ask' => $ask,
            'dimensions' => $dimensions,
            'action' => $action,
            'minimumAnswers' => $minimumAnswers,
            'prefill' => $prefill,
        ])
    </main>

    <footer class="fb-public-foot">
        <p>
            {{ \App\Models\SalesInvoice::defaultSellerDetails()['seller_company_name'] }} ·
            {{ \App\Models\SalesInvoice::defaultSellerDetails()['seller_email'] }} ·
            {{ \App\Models\SalesInvoice::defaultSellerDetails()['seller_mobile'] }}
        </p>
        <p>Sent once, for this project. Reply to whoever sent you this link if something is wrong with it.</p>
    </footer>
</body>

</html>
