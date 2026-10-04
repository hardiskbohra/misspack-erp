<section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-products-heading">
    <div class="master-section-head">
        <div>
            <h2 class="master-section-title" id="vendor-products-heading">Products supplied</h2>
            <p class="master-sub">{{ number_format($products->count()) }} {{ \Illuminate\Support\Str::plural('product', $products->count()) }} associated through quotes and project orders.</p>
        </div>
        @if (\Illuminate\Support\Facades\Route::has('products.index'))
            <a class="master-btn master-btn-soft" href="{{ route('products.index') }}">Open product register</a>
        @endif
    </div>

    @if ($products->isNotEmpty())
        <div class="vendor-product-grid">
            @foreach ($products as $product)
                @php($media = $product->relationLoaded('media') && method_exists($product, 'primaryMedia') ? $product->primaryMedia() : null)
                <a class="vendor-product-card" href="{{ \Illuminate\Support\Facades\Route::has('products.show') ? route('products.show', $product) : route('products.index') }}">
                    @if ($media?->file_path)
                        <img class="vendor-product-image" src="{{ asset('storage/'.$media->file_path) }}" alt="">
                    @else
                        <span class="vendor-product-image vendor-product-image--empty" aria-hidden="true"><i class="fa-solid fa-box"></i></span>
                    @endif
                    <span class="vendor-product-copy">
                        <strong>{{ $product->name }}</strong>
                        <span>{{ $product->product_number ?: 'Product record' }} · {{ $product->category ?: 'Uncategorized' }}</span>
                    </span>
                    <i class="fa-solid fa-arrow-up-right-from-square vendor-product-open" aria-hidden="true"></i>
                </a>
            @endforeach
        </div>
    @else
        <div class="master-empty-state"><i class="fa-solid fa-box-open" aria-hidden="true"></i><p>No products are linked yet. Product relationships appear here when a quote or project is associated with this vendor.</p></div>
    @endif
</section>
