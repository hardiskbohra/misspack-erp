@extends('layouts.app')

@section('title', 'Products')
@section('page-title', 'Product Management')

@section('content')
    @push('styles')
        <link rel="stylesheet" href="{{ asset('assets/css/products.css') }}">
    @endpush
    <div class="master product-index">
        <div class="master-card">
            <form method="GET" action="{{ route('products.index') }}">
                <div class="master-filter-row core-filter-toolbar">
                    <div class="master-search"><span aria-hidden="true">⌕</span><input class="master-input" name="search" value="{{ $search }}"
                            placeholder="Search product, SKU, material..." aria-label="Search products"></div>
                    <x-filter-trigger drawer="productFiltersDrawer" :count="(filled($search) ? 1 : 0) + ($status !== 'all' ? 1 : 0) + ($category !== 'all' ? 1 : 0)" />
                    <button type="button" class="master-btn master-btn-primary" id="openQuickProductModal">+ Quick Product</button>
                </div>
                <x-drawer id="productFiltersDrawer" title="Filter products" eyebrow="Product filters"
                    subtitle="Narrow the catalogue by availability status or category." size="medium">
                    <section class="core-drawer-section">
                        <h3 class="core-drawer-section-title">Product details</h3>
                        <div class="core-drawer-fields">
                            <div class="master-field">
                                <label class="master-label" for="productFilterStatus">Status</label>
                                <select class="master-select" id="productFilterStatus" name="status">
                                    <option value="all">All statuses</option>
                                    @foreach (\App\Models\Product::statusOptions() as $key => $label)
                                        <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="master-field">
                                <label class="master-label" for="productFilterCategory">Category</label>
                                <select class="master-select" id="productFilterCategory" name="category">
                                    <option value="all">All categories</option>
                                    @foreach ($categories as $cat)
                                        <option value="{{ $cat }}" @selected($category === $cat)>{{ $cat }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </section>
                    <x-slot:footer>
                        <a class="master-btn master-btn-soft" href="{{ route('products.index') }}">Reset</a>
                        <button class="master-btn master-btn-primary" type="submit">Apply filters</button>
                    </x-slot:footer>
                </x-drawer>
            </form>
        </div>
        <div>
            <div style="padding-top:18px;">
                <div class="master-product-grid">
                    @forelse($products as $product)
                        @php($media = $product->primaryMedia())
                
                        <div class="master-product-card">
                            <a href="{{ route('products.show',$product) }}">
                
                                {{-- Product Image --}}
                                <div class="master-product-image">
                    
                                    @if($media && $media->file_path && $media->media_type === 'image')
                                        <img src="{{ asset('storage/'.$media->file_path) }}" alt="{{ $product->name }}">
                                    @else
                                        <div class="master-no-image">
                                            <i class="fa-solid fa-box"></i>
                                        </div>
                                    @endif
                    
                                    @if($product->ready_stock_available)
                                        <span class="master-stock-badge success">
                                            <i class="fa-solid fa-check"></i>
                                            Ready Stock
                                        </span>
                                    @else
                                        <span class="master-stock-badge danger">
                                            <i class="fa-solid fa-times"></i>
                                            No Stock
                                        </span>
                                    @endif
                    
                                </div>
                            </a>
                
                            {{-- Body --}}
                            <div class="master-product-body">
                
                                
                                <a href="{{ route('products.show',$product) }}">
                                    <span class="master-product-number">
                                        {{ $product->product_number }}
                                    </span>
                    
                                    <h3>
                                        {{ $product->name }}
                                    </h3>
                    
                                    <div class="master-category">
                                        {{ $product->category ?: '-' }}
                                    </div>
                                </a>
                
                                <div class="master-product-meta">

                                    <div class="meta-item">
                                        <span>Capacity</span>
                                        <strong>
                                            {{ !empty($product->ml_capacities)
                                                ? implode(', ', array_map(fn($v) => $v.'ml', $product->ml_capacities))
                                                : '-' }}
                                        </strong>
                                    </div>
                                
                                </div>
                
                            </div>
                
                            {{-- Footer --}}
                            <div class="master-product-footer">
                
                                <a href="{{ route('products.public', $product->public_token) }}" target="_blank" class="master-btn-light">
                                    <i class="fa-solid fa-link"></i>
                                </a>
                
                                <a href="{{ route('products.edit',$product) }}" class="master-btn-light">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                
                
                                <form method="POST"
                                    action="{{ route('products.destroy',$product) }}"
                                    data-confirm="Delete this product?">
                
                                    @csrf
                                    @method('DELETE')
                
                                    <button class="master-btn-danger">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                
                                </form>
                
                            </div>
                
                        </div>
                
                    @empty
                
                        <div class="master-empty">
                            No products found.
                        </div>
                
                    @endforelse
                </div>
            </div>
            <x-pagination :items="$products" />
        </div>
        
        <div class="master-modal" id="quickProductModal">
            <div class="master-modal-card" role="dialog">
                <form method="POST" action="{{ route('products.quickStore') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="master-modal-header">
                        <div>
                            <h3 class="master-modal-title">Quick Product</h3>
                            <p class="master-modal-subtitle">Create product with basic details and optional first price row.</p>
                        </div>
    
                        <button type="button" class="master-modal-close" data-close-modal="quickProductModal">×</button>
                    </div>
                    <div class="master-modal-body">
                        <div class="master-modal-grid">
                            <div><label class="master-label">Product Media</label><input class="master-input" type="file" name="media_files[]" multiple></div>
                            <div><label class="master-label">Product Name *</label><input class="master-input" name="name" required placeholder="50ml Matte Bottle"></div>
                            <div><label class="master-label">Category</label><input class="master-input" name="category" placeholder="Bottle / Jar / Cap"></div>
                            <div><label class="master-label">ML Capacities</label><input class="master-input" name="ml_capacities_text" placeholder="50,100,200"></div>
                            
                            <div class="master-form-group">
                                <label class="master-label">Ready Stock?</label>

                                <div class="master-choice-group">
                                    <label class="master-choice-chip green-chip">
                                        <input type="radio" name="ready_stock_available" value="1" required checked>
                                        <span>
                                            <i class="fa-solid fa-check"></i>
                                            Yes
                                        </span>
                                    </label>

                                    <label class="master-choice-chip red-chip">
                                        <input type="radio" name="ready_stock_available" value="0" required>
                                        <span>
                                            <i class="fa-solid fa-times"></i>
                                            No
                                        </span>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="master-form-group">
                                <label class="master-label">Show Pricing Publicly?</label>

                                <div class="master-choice-group">
                                    <label class="master-choice-chip green-chip">
                                        <input type="radio" name="show_price_ladder_public" value="1" required>
                                        <span>
                                            <i class="fa-solid fa-check"></i>
                                            Yes
                                        </span>
                                    </label>

                                    <label class="master-choice-chip red-chip">
                                        <input type="radio" name="show_price_ladder_public" value="0" required checked>
                                        <span>
                                            <i class="fa-solid fa-times"></i>
                                            No
                                        </span>
                                    </label>
                                </div>
                            </div>
                            
                            <div><label class="master-label">Ready Stock MOQ</label><input class="master-input" type="number" name="ready_stock_moq"></div>
                            <div><label class="master-label">Customisation MOQ</label><input class="master-input" type="number" name="customisation_moq"></div>
                        </div>
                    </div>
                    <div class="master-modal-footer"><button type="button" class="master-btn master-btn-light" data-close-modal="quickProductModal">Cancel</button><button class="master-btn master-btn-primary" type="submit">Create Product</button></div>
                </form>
            </div>
        </div>
    @push('scripts')
        <script src="{{ asset('assets/js/products.js') }}"></script>
    @endpush
</div>
@endsection