{{--
    Compact warning for a list row — the table-sized sibling of x-step-banner.

        <x-step-flag tone="warning" label="Needs approval" />
        <x-step-flag compact tone="warning" label="Needs approval" />

    compact: icon only; the label is a hover tooltip.
--}}
@props([
    'tone' => 'warning',
    'label' => 'Action required',
    'icon' => null,
    'compact' => false,
])

@php
    $tone = in_array($tone, ['warning', 'danger', 'info', 'success'], true) ? $tone : 'warning';
    $icons = [
        'warning' => 'fa-solid fa-triangle-exclamation',
        'danger' => 'fa-solid fa-ban',
        'info' => 'fa-solid fa-circle-info',
        'success' => 'fa-solid fa-circle-check',
    ];
@endphp

<span {{ $attributes->class(['step-flag', 'step-flag--'.$tone, 'is-compact' => $compact]) }}
    @if ($compact) tabindex="0" aria-label="{{ $label }}" @endif>
    <i class="{{ $icon ?: $icons[$tone] }}" aria-hidden="true"></i>
    @if ($compact)
        <span class="step-flag-tip" role="tooltip">{{ $label }}</span>
    @else
        {{ $label }}
    @endif
</span>
