@extends('client_portal.layouts.app')

@section('title', $product->name)
@section('page-title', 'Product details')

@section('content')
<div class="cp-page-head">
    <div><a class="cp-back-link" href="{{ route('client-portal.products.index') }}"><i class="fa-solid fa-arrow-left"></i> Product catalogue</a><p class="cp-eyebrow">{{ $product->product_number }}</p><h1>{{ $product->name }}</h1><p>{{ $product->sku ?: 'No SKU' }} · {{ $product->category ?: 'No category' }}</p></div>
</div>

<div class="cp-product-detail-grid">
    <section class="cp-card cp-section-card">
        <div class="cp-section-heading"><div><p class="cp-eyebrow">Gallery</p><h2>Product media</h2></div></div>
        <div class="cp-product-gallery">
            @forelse($product->media->where('media_type','image') as $media)
                <a href="{{ asset('storage/'.$media->file_path) }}" target="_blank" rel="noopener"><img src="{{ asset('storage/'.$media->file_path) }}" alt="{{ $product->name }} product image"></a>
            @empty
                <div class="cp-empty cp-empty-spacious cp-grid-empty"><i class="fa-regular fa-image"></i><strong>No image available</strong></div>
            @endforelse
        </div>
    </section>
    <section class="cp-card cp-section-card">
        <div class="cp-section-heading"><div><p class="cp-eyebrow">Specifications</p><h2>Product details</h2></div></div>
        <dl class="cp-fact-list cp-fact-list-stacked">
            <div><dt>Capacity</dt><dd>{{ implode(', ', $product->ml_capacities ?? []) ?: 'Not specified' }}</dd></div>
            <div><dt>Finish</dt><dd>{{ $product->finish_details ?: 'Not specified' }}</dd></div>
            <div><dt>Printing</dt><dd>{{ $product->printing_details ?: 'Not specified' }}</dd></div>
            <div><dt>Material</dt><dd>{{ $product->material_details ?: 'Not specified' }}</dd></div>
            <div><dt>Packaging</dt><dd>{{ $product->packaging_details ?: 'Not specified' }}</dd></div>
            <div><dt>Description</dt><dd>{{ $product->description ?: 'No description supplied' }}</dd></div>
        </dl>
    </section>
</div>

<section class="cp-card cp-table-card">
    <div class="cp-section-heading cp-section-heading-padded"><div><p class="cp-eyebrow">Commercials</p><h2>Price ladder</h2></div></div>
    <div class="cp-table-wrap ui-mobile-cards"><table class="cp-table"><thead><tr><th>Quantity</th><th>Unit</th><th>Capacity</th><th class="cp-number">Selling cost INR</th><th>Remarks</th></tr></thead><tbody>@forelse($product->priceLadders as $ladder)<tr><td data-label="Quantity">{{ $ladder->quantity }}</td><td data-label="Unit">{{ $ladder->unit }}</td><td data-label="Capacity">{{ $ladder->capacity ?: '—' }}</td><td data-label="Selling cost INR" class="cp-number">{{ \App\Helpers\CommonHelper::indianCurrency($ladder->selling_cost_inr) }}</td><td data-label="Remarks">{{ $ladder->remarks ?: '—' }}</td></tr>@empty<tr><td colspan="5"><div class="cp-empty">No public pricing is available for this product.</div></td></tr>@endforelse</tbody></table></div>
</section>
@endsection
