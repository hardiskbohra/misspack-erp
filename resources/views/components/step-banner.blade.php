{{--
    A highlighted next-step strip for gates the office must not skip:
    approve a PO, raise a bill, confirm a payment.

        <x-step-banner
            tone="warning"
            eyebrow="Pending checker"
            title="Approve this purchase order"
            body="Money cannot go out until a checker signs it off."
        >
            <x-slot:actions>
                <form method="POST" …>
                    <button class="master-btn master-btn-primary" type="submit">Approve</button>
                </form>
            </x-slot:actions>
        </x-step-banner>

    Tones: warning (default, amber), danger, info, success.
    The default slot is extra body copy (a list of documents, a note).
--}}
@props([
    'tone' => 'warning',
    'eyebrow' => 'Action required',
    'title' => null,
    'body' => null,
    'icon' => null,
])

@php
    $tone = in_array($tone, ['warning', 'danger', 'info', 'success'], true) ? $tone : 'warning';
    $icons = [
        'warning' => 'fa-solid fa-triangle-exclamation',
        'danger' => 'fa-solid fa-ban',
        'info' => 'fa-solid fa-circle-info',
        'success' => 'fa-solid fa-circle-check',
    ];
    $iconClass = $icon ?: $icons[$tone];
@endphp

<aside {{ $attributes->class(['step-banner', 'step-banner--'.$tone]) }} role="status">
    <span class="step-banner-mark" aria-hidden="true">
        <i class="{{ $iconClass }}"></i>
    </span>
    <div class="step-banner-copy">
        @if (filled($eyebrow))
            <p class="step-banner-eyebrow">{{ $eyebrow }}</p>
        @endif
        @if (filled($title))
            <h2 class="step-banner-title">{{ $title }}</h2>
        @endif
        @if (filled($body))
            <p class="step-banner-body">{{ $body }}</p>
        @endif
        @if (! $slot->isEmpty())
            <div class="step-banner-extra">{{ $slot }}</div>
        @endif
    </div>
    @if (isset($actions) && ! $actions->isEmpty())
        <div class="step-banner-actions">
            {{ $actions }}
        </div>
    @endif
</aside>
