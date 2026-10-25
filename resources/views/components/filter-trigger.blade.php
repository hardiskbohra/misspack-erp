@props([
    'drawer',
    'label' => 'Filters',
    'count' => null,
    'iconOnly' => false,
])

@php
    $filterCount = is_numeric($count) ? max(0, (int) $count) : null;
    $triggerLabel = 'Open ' . strtolower($label);
    if ($filterCount !== null) {
        $triggerLabel .= ', ' . $filterCount . ' active ' . \Illuminate\Support\Str::plural('filter', $filterCount);
    }
@endphp

<button type="button" class="master-btn master-btn-soft core-filter-trigger{{ $iconOnly ? ' core-filter-trigger--icon' : '' }}"
    data-drawer-open="{{ $drawer }}" aria-haspopup="dialog" aria-controls="{{ $drawer }}"
    aria-expanded="false" aria-label="{{ $triggerLabel }}" @if($iconOnly) data-ui-tooltip="{{ $label }}" @endif>
    <i class="fa-solid fa-sliders" aria-hidden="true"></i>
    @unless($iconOnly)<span>{{ $label }}</span>@endunless
    @if ($filterCount !== null)
        <span class="core-filter-count" aria-hidden="true">{{ $filterCount }}</span>
    @endif
</button>
