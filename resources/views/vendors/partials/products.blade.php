<section class="master-tab-panel" id="vendor-panel-products" role="tabpanel" aria-labelledby="vendor-tab-products">
    <div class="vendor-panel-head">
        <div>
            <h2 class="vendor-detail-title">Products supplied</h2>
            <p class="vendor-detail-help">Products mapped to this vendor through projects and vendor quotes.</p>
        </div>
        <div class="vendor-panel-meta">
            <span class="vendor-pill">{{ $products->count() }} {{ \Illuminate\Support\Str::plural('product', $products->count()) }}</span>
        </div>
    </div>

    @if ($products->count())
        <div class="vendor-product-grid">
            @foreach ($products as $product)
                @php($media = method_exists($product, 'primaryMedia') ? $product->primaryMedia() : null)
                <a class="master-card master-card--flat vendor-product-card" href="{{ route('products.show', $product) }}">
                    @if ($media && $media->file_path)
                        <img class="vendor-product-media" src="{{ asset('storage/' . $media->file_path) }}" alt="" loading="lazy">
                    @else
                        <span class="vendor-product-media is-empty" aria-hidden="true"><i class="fa-solid fa-box-open"></i></span>
                    @endif
                    <span class="vendor-product-copy">
                        <strong>{{ $product->name }}</strong>
                        <small>{{ $product->product_number ?? 'No product number' }} · {{ $product->category ?: 'No category' }}</small>
                    </span>
                </a>
            @endforeach
        </div>
    @else
        <div class="master-card master-card--flat">
            <div class="master-list-empty">
                <span class="master-list-empty-icon" aria-hidden="true"><i class="fa-solid fa-box-open"></i></span>
                <h3 class="master-list-empty-title">No products mapped yet</h3>
                <p class="master-list-empty-text">Products appear here once this vendor is used on a project product row or a quote.</p>
            </div>
        </div>
    @endif
</section>
