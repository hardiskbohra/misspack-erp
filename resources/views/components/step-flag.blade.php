{{--
    Compact warning for a list row — the table-sized sibling of x-step-banner.

        <x-step-flag tone="warning" label="Needs approval" />
--}}
@props([
    'tone' => 'warning',
    'label' => 'Action required',
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
@endphp

<span {{ $attributes->class(['step-flag', 'step-flag--'.$tone]) }}>
    <i class="{{ $icon ?: $icons[$tone] }}" aria-hidden="true"></i>
    {{ $label }}
</span>
