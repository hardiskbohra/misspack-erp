@php
    // Reuse sidebar labels, icons and destinations rather than a second navigation catalog.
    $quickMenuUser = auth()->user();
    $quickMenuCatalog = collect($sidebarItems ?? [])->filter(fn ($item) => isset($item['route']))->keyBy('route');
    $quickMenuMatrix = app(\App\Services\PermissionMatrix::class);
    $quickMenuItems = $quickMenuUser?->isAdmin()
        ? collect(['shipments.index', 'cashflows.index', 'sales-invoices.index', 'purchase-invoices.index',
            'projects.index', 'packaging-lists.index', 'clients.index', 'vendors.index'])
            ->map(fn ($route) => $quickMenuCatalog->get($route))
            ->filter(function ($item) use ($quickMenuUser, $quickMenuMatrix) {
                if (! $item) return false;
                $permission = $quickMenuMatrix->requiredForRoute($item['route']);
                return $permission === null || $quickMenuUser->hasPermission($permission);
            })->values()
        : collect();
@endphp
@if($quickMenuItems->isNotEmpty())
<div class="master-dropdown topbar-quick-menu" data-quick-menu>
    <button type="button" class="master-dropdown-toggle topbar-btn" aria-label="Quick menu"
            aria-haspopup="menu" aria-expanded="false" aria-controls="topbarQuickMenuPanel">
        <i class="fa-solid fa-table-cells-large" aria-hidden="true"></i>
        <span class="topbar-quick-label">Quick menu</span>
    </button>
    <div class="master-dropdown-menu topbar-quick-panel" id="topbarQuickMenuPanel" role="menu" aria-label="Frequently used modules">
        <div class="topbar-quick-heading" role="presentation">Frequently used</div>
        @foreach($quickMenuItems as $item)
            @php($quickMenuActive = request()->routeIs($item['active']) && ! (isset($item['except']) && request()->routeIs($item['except'])))
            <a href="{{ route($item['route']) }}" role="menuitem" tabindex="-1" @if($quickMenuActive) aria-current="page" @endif>
                <i class="{{ $item['icon'] }}" aria-hidden="true"></i>
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </div>
</div>
@endif
