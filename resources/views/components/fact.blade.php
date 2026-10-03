{{--
    A single label/value pair on a record view.

    <x-fact label="Pickup date" :value="$shipment->pickup_date?->format('d M Y')" />

    A missing value renders as a quiet "Not set" instead of a bare dash, so a
    half-filled record still reads as a sentence rather than as gaps. Pass
    markup through the slot when the value is not plain text (a badge, a chip,
    a link):

        <x-fact label="Status">
            <strong class="master-badge status-{{ $statusClass }}">{{ $label }}</strong>
        </x-fact>

    The label is a real <span> inside .master-info, which is what the
    master-detail.css facts grid styles.
--}}
@props(['label', 'value' => null])

@php
    $text = is_string($value) || is_numeric($value) ? trim((string) $value) : null;
    $hasText = $text !== null && $text !== '' && $text !== '-';
@endphp

<div {{ $attributes->class(['master-info']) }}>
    <span>{{ $label }}</span>

    @if (! $slot->isEmpty())
        {{ $slot }}
    @elseif ($hasText)
        <strong>{{ $text }}</strong>
    @else
        <strong class="master-empty-value">Not set</strong>
    @endif
</div>
