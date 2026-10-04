@extends('client_portal.layouts.app')

@section('title', 'Products')
@section('page-title', 'Products')

@section('content')
    <div class="master-card master-header">
        <div>
            <h1>Products</h1>
            <p style="font-size:16px;font-weight:500;">Products related to your projects.</p>
        </div>
    </div>
    <div class="master-card" style="padding:18px;margin-bottom:18px;">
        <form method="GET" action="{{ route('client-portal.products.index') }}">
            <div class="core-filter-toolbar">
                <div class="master-field">
                    <label class="master-label" for="portalProductSearch">Search</label>
                    <input class="master-input" id="portalProductSearch" name="search" value="{{ $search }}" placeholder="Search product, SKU, category...">
                </div>
                <x-filter-trigger drawer="portalProductFiltersDrawer"
                    :count="(filled($search) ? 1 : 0) + ($category !== 'all' ? 1 : 0)" />
            </div>
            <x-drawer id="portalProductFiltersDrawer" title="Filter products" eyebrow="Product filters"
                subtitle="Narrow related products by category." size="medium">
                <section class="core-drawer-section">
                    <h3 class="core-drawer-section-title">Product category</h3>
                    <div class="core-drawer-fields">
                        <div class="master-field">
                            <label class="master-label" for="portalProductFilterCategory">Category</label>
                            <select class="master-select" id="portalProductFilterCategory" name="category">
                                <option value="all">All categories</option>
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat }}" @selected($category === $cat)>{{ $cat }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </section>
                <x-slot:footer>
                    <a class="master-btn master-btn-soft" href="{{ route('client-portal.products.index') }}">Reset</a>
                    <button class="master-btn master-btn-primary" type="submit">Apply filters</button>
                </x-slot:footer>
            </x-drawer>
        </form>
    </div>
    <div class="cp-grid-4">
        @forelse($products as $product)
            @php($media = $product->primaryMedia())
            <div class="cp-card" style="overflow:hidden;">
                @if ($media && $media->file_path && $media->media_type === 'image')
                    <img src="{{ asset('storage/' . $media->file_path) }}"
                    style="width:100%;height:300px;object-fit:cover;display:block;">@else<div
                        style="height:230px;background:#f3f6fb;display:grid;place-items:center;font-size:44px;color:#bbb;">◈
                    </div>
                @endif
                <div style="padding:16px;">
                    <div class="cp-muted">{{ $product->product_number }}</div>
                    <h3 style="margin:5px 0;">{{ $product->name }}</h3>
                    <div class="cp-muted">{{ $product->category ?: '-' }}</div>
                    <div class="cp-product-context">Shared for products linked to your published projects.</div>
                </div>
            </div>
        @empty
            <div class="cp-empty" style="grid-column:1/-1;">No related products found.</div>
        @endforelse
    </div>
    <x-pagination :items="$products" />
@endsection
