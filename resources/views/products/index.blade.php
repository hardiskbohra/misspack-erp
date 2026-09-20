@extends('layouts.app')

@section('title', 'Products')
@section('page-title', 'Product Management')

@section('content')
    <style>
        :root {
            --master-primary: #4f83f1;
            --master-primary2: #6366f1;
            --master-bg: #eef3ff;
            --master-border: #dfe7f3;
            --master-muted: #687386;
            --master-dark: #17233b;
            --master-green: #10b981;
            --master-red: #ef4770;
            --master-orange: #f59e0b;
            --master-soft: #edf5ff;
            --master-shadow: 0 14px 35px rgba(25, 42, 70, .08)
        }

        .master-table-wrap {
            overflow-x: auto;
            margin-top:20px;
        }

        .master-product {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 260px
        }

        .master-img {
            width: 54px;
            height: 54px;
            border-radius: 14px;
            object-fit: cover;
            background: var(--master-soft);
            display: flex;
            align-items: center;
            justify-content: center
        }
        
        a {
            text-decoration: none;
            color: inherit;
        }
        
        .master-product-grid{
            display:grid;
            grid-template-columns:repeat(auto-fill,minmax(250px,1fr));
            gap:24px;
        }
        
        .master-product-card{
            background:#fff;
            border-radius:18px;
            overflow:hidden;
            border:1px solid #ececec;
            box-shadow:0 8px 20px rgba(0,0,0,.06);
            transition:.25s;
        }
        
        .master-product-card:hover{
            transform:translateY(-5px);
            box-shadow:0 15px 35px rgba(0,0,0,.12);
        }
        
        .master-product-image{
            position:relative;
            height:300px;
            background:#f6f7fb;
        }
        
        .master-product-image img{
            width:100%;
            height:100%;
            object-fit:cover;
        }
        
        .master-no-image{
            display:flex;
            align-items:center;
            justify-content:center;
            height:100%;
            font-size:70px;
            color:#bbb;
        }
        
        .master-stock-badge{
            position:absolute;
            left:15px;
            top:15px;
            padding:6px 12px;
            border-radius:20px;
            color:#fff;
            font-size:12px;
            font-weight:500;
        }
        
        .master-stock-badge.success{
            background:#16a34a;
        }
        
        .master-stock-badge.danger{
            background:#dc2626;
        }
        
        .master-product-body{
            padding:15px 15px 0px 15px;
            line-height:1;
        }
        
        .master-product-number{
            color:#888;
            font-size:13px;
        }
        
        .master-product-body h3{
            margin:8px 0;
            font-size:16px;
            font-weight: 600;
        }
        
        .master-product-body h3 a{
            color:#1f2937;
            text-decoration:none;
        }
        
        .master-category{
            color:#888;
            font-size: 12px;
            margin-bottom:18px;
        }
        
        .master-info{
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:10px;
        }
        
        .master-info div{
            background:#f8fafc;
            padding:12px;
            border-radius:10px;
        }
        
        .master-info small{
            display:block;
            color:#888;
            margin-bottom:4px;
        }
        
        .master-info strong{
            display:block;
        }
        
        .master-product-meta{
            display:grid;
            grid-template-columns:repeat(3,1fr);
            gap:10px;
            margin:18px 0px 0px 0px;
            padding:14px 0px 0px 0px;
            border-top:1px solid #eceff4;
        }
        
        .meta-item span{
            display:block;
            font-size:10px;
            color:#8b95a7;
            margin-bottom:4px;
            text-transform:uppercase;
            letter-spacing:.5px;
        }
        
        .meta-item strong{
            font-size:14px;
            font-weight:500;
            color:#1f2937;
        }
        
        .master-product-footer{
            display:flex;
            gap:10px;
            padding:10px 22px 10px 22px;
            border-top:1px solid #eee;
        }
        
        .master-btn-light,
        .master-btn-danger{
            width:42px;
            height:42px;
            border:none;
            border-radius:10px;
            display:flex;
            align-items:center;
            justify-content:center;
            background:#f5f6f8;
            cursor:pointer;
            color:#333;
        }
        
        .master-btn-light:hover{
            background:#2563eb;
            color:#fff;
        }
        
        .master-btn-danger{
            background:#fee2e2;
            color:#dc2626;
        }
        
        .master-btn-danger:hover{
            background:#dc2626;
            color:#fff;
        }
        
        .master-product-footer form{
            margin:0;
        }
        
        .master-product-meta{
            display:grid;
            grid-template-columns:repeat(3,1fr);
            gap:10px;
            padding:14px 0;
            border-top:1px solid #eceff4;
        }
    </style>
    <div class="master">
        <div class="master-card">
            <form method="GET" action="{{ route('products.index') }}">
                <div class="master-filter-row" style="padding-top:22px">
                    <div class="master-search"><span>⌕</span><input class="master-input" name="search" value="{{ $search }}"
                            placeholder="Search product, SKU, material..."></div>
                    
                    <select class="master-select" name="status">
                        <option value="all">All Status</option>
                        @foreach (\App\Models\Product::statusOptions() as $key => $label)
                            <option value="{{ $key }}" {{ $status === $key ? 'selected' : '' }}>{{ $label }}
                            </option>
                        @endforeach
                    </select>
                    <select class="master-select" name="category">
                        <option value="all">All Categories</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat }}" {{ $category === $cat ? 'selected' : '' }}>{{ $cat }}
                            </option>
                        @endforeach
                    </select>
                    <button class="master-btn master-btn-primary" type="submit">Filter</button>
                    <a class="master-btn master-btn-light" href="{{ route('products.index') }}">Reset</a>
                    <button type="button" class="master-btn master-btn-primary" id="openQuickProductModal">+ Quick Product</button>
                    <!--<a class="master-btn master-btn-soft" href="{{ route('products.create') }}">Detailed Form</a>-->
                </div>
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
                                
                                    <div class="meta-item">
                                        <span>Vendor Quotes</span>
                                        <strong>
                                            {{ $product->vendor_quotes_count }}
                                        </strong>
                                    </div>
                                
                                    <div class="meta-item">
                                        <span>Client Quotes</span>
                                        <strong>
                                            {{ $product->client_quotes_count ?? 0 }}
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
                
                                <button
                                    class="master-btn-light openQuickQuoteModal"
                                    data-product-id="{{ $product->id }}">
                                    <i class="fa-solid fa-indian-rupee-sign"></i>
                                </button>
                
                                <button
                                    class="master-btn-light product-quotes-btn"
                                    data-product-id="{{ $product->id }}">
                                    <i class="fa-solid fa-file-invoice-dollar"></i>
                                </button>
                
                                <form method="POST"
                                    action="{{ route('products.destroy',$product) }}"
                                    onsubmit="return confirm('Delete this product?')">
                
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

                                <div class="master-chip-group">
                                    <label class="master-chip green-chip">
                                        <input type="radio" name="ready_stock_available" value="1" required checked>
                                        <span>
                                            <i class="fa-solid fa-check"></i>
                                            Yes
                                        </span>
                                    </label>

                                    <label class="master-chip red-chip">
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

                                <div class="master-chip-group">
                                    <label class="master-chip green-chip">
                                        <input type="radio" name="show_price_ladder_public" value="1" required>
                                        <span>
                                            <i class="fa-solid fa-check"></i>
                                            Yes
                                        </span>
                                    </label>

                                    <label class="master-chip red-chip">
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
<script>document.addEventListener('DOMContentLoaded',function(){function openModal(id){document.getElementById(id)?.classList.add('open')}function closeModal(id){document.getElementById(id)?.classList.remove('open')}document.getElementById('openQuickProductModal')?.addEventListener('click',function(){openModal('quickProductModal')});document.querySelectorAll('[data-close-modal]').forEach(function(btn){btn.addEventListener('click',function(){closeModal(btn.dataset.closeModal)})});document.querySelectorAll('.master-modal').forEach(function(modal){modal.addEventListener('click',function(e){if(e.target===modal)closeModal(modal.id)})});});
</script>
</div>
@endsection