<nav class="master-tabs stock-nav" aria-label="Stock sections">
    <a class="master-tab {{ request()->routeIs('stock.index', 'stock.items.*', 'stock.movements.*') ? 'is-active' : '' }}" href="{{ route('stock.index') }}" @if(request()->routeIs('stock.index', 'stock.items.*', 'stock.movements.*')) aria-current="page" @endif>Stock register</a>
    <a class="master-tab {{ request()->routeIs('stock.counts.*') ? 'is-active' : '' }}" href="{{ route('stock.counts.index') }}" @if(request()->routeIs('stock.counts.*')) aria-current="page" @endif>Physical counts</a>
</nav>
