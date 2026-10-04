@props([
    'drawer',
    'label' => 'Filters',
    'count' => null,
])

@php
    $filterCount = is_numeric($count) ? max(0, (int) $count) : null;
    $triggerLabel = 'Open ' . strtolower($label);
    if ($filterCount !== null) {
        $triggerLabel .= ', ' . $filterCount . ' active ' . \Illuminate\Support\Str::plural('filter', $filterCount);
    }
@endphp

<button type="button" class="master-btn master-btn-soft core-filter-trigger"
    data-drawer-open="{{ $drawer }}" aria-haspopup="dialog" aria-controls="{{ $drawer }}"
    aria-expanded="false" aria-label="{{ $triggerLabel }}">
    <i class="fa-solid fa-sliders" aria-hidden="true"></i>
    <span>{{ $label }}</span>
    @if ($filterCount !== null)
        <span class="core-filter-count" aria-hidden="true">{{ $filterCount }}</span>
    @endif
</button>
