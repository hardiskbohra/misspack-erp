@extends('client_portal.layouts.app')

@section('title', 'Product catalogue')
@section('page-title', 'Product catalogue')

@section('content')
<div class="cp-page-head">
    <div><p class="cp-eyebrow">Your range</p><h1>Product catalogue</h1><p>Browse products connected to your published projects and quotations.</p></div>
</div>

<section class="cp-card cp-filter-card">
    <form method="GET" action="{{ route('client-portal.products.index') }}">
        <div class="core-filter-toolbar">
            <div class="master-field">
                <label class="master-label" for="portalProductSearch">Search products</label>
                <input class="master-input" id="portalProductSearch" name="search" value="{{ $search }}" placeholder="Product, SKU or category">
            </div>
            <x-filter-trigger drawer="portalProductFiltersDrawer" :count="(filled($search) ? 1 : 0) + ($category !== 'all' ? 1 : 0)" />
        </div>
        <x-drawer id="portalProductFiltersDrawer" title="Filter products" eyebrow="Product filters" subtitle="Narrow related products by category." size="medium">
            <section class="core-drawer-section">
                <h3 class="core-drawer-section-title">Product category</h3>
                <div class="core-drawer-fields"><div class="master-field"><label class="master-label" for="portalProductFilterCategory">Category</label><select class="master-select" id="portalProductFilterCategory" name="category"><option value="all">All categories</option>@foreach ($categories as $cat)<option value="{{ $cat }}" @selected($category === $cat)>{{ $cat }}</option>@endforeach</select></div></div>
            </section>
            <x-slot:footer><a class="master-btn master-btn-soft" href="{{ route('client-portal.products.index') }}">Reset</a><button class="master-btn master-btn-primary" type="submit">Apply filters</button></x-slot:footer>
        </x-drawer>
    </form>
</section>

<div class="cp-product-grid">
    @forelse($products as $product)
        @php($media = $product->primaryMedia())
        <article class="cp-card cp-product-card">
            <a class="cp-product-media" href="{{ route('client-portal.products.show', $product) }}">
                @if ($media && $media->file_path && $media->media_type === 'image')
                    <img src="{{ asset('storage/' . $media->file_path) }}" alt="{{ $product->name }}">
                @else
                    <span><i class="fa-solid fa-box-open"></i></span>
                @endif
            </a>
            <div class="cp-product-card-body">
                <span class="cp-product-number">{{ $product->product_number }}</span>
                <h2><a href="{{ route('client-portal.products.show', $product) }}">{{ $product->name }}</a></h2>
                <p>{{ $product->category ?: 'Uncategorised' }}@if($product->sku) · SKU {{ $product->sku }}@endif</p>
                <a class="cp-text-link" href="{{ route('client-portal.products.show', $product) }}">View product <i class="fa-solid fa-arrow-right"></i></a>
            </div>
        </article>
    @empty
        <div class="cp-card cp-empty cp-empty-spacious cp-grid-empty"><i class="fa-solid fa-box-open"></i><strong>No products found</strong><span>Try changing your search or category filter.</span></div>
    @endforelse
</div>
<x-pagination :items="$products" />
@endsection
