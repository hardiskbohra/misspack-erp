<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $product->product_number }} : {{ $product->name }} - MissPack Product</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/product-public.css') }}">
</head>

<body>

@php($mediaItems=[])
@foreach($product->media as $media)
    @if($media->file_path && in_array($media->media_type, ['image','video'])) @php($mediaItems[]=['type'=>$media->media_type,'url'=>asset('storage/'.$media->file_path),'title'=>$media->title ?: $media->original_name]) @endif
@endforeach
<div class="page">
    <div class="card header" style="background:#2f3a4c;">

        {{-- Row 1 --}}
        <div class="header-top">
            <div class="brand-logo-wrap">
                <img class="brand-logo" src="{{ asset('images/logo.png') }}" height="150"
                    alt="MissPack - Packed Perfect">
            </div>
        </div>

        {{-- Row 2 --}}
        <div class="header-bottom">
            <div class="header-content">
                <h1>Product Details</h1>
                <p>{{ $product->name ?: $lead->title }}</p>
        
                <span class="badge">
                    {{ $product->product_number }}
                </span>
            </div>
        </div>
    </div>
    <div class="layout">
        <div class="card gallery">
            <div class="main-media" id="mainMedia">
                @if (!count($mediaItems))
                    <div style="text-align:center;color:#687386;font-weight:900">📦<br>No media uploaded.</div>
                @endif
            </div>
            @if (count($mediaItems) > 1)
                <div class="thumbs" id="thumbs"></div>
            @endif
        </div>
        <div>
            <div class="card section">
                <h2>Product Details</h2>
                <div class="info-grid">
                    <div class="info">
                        <span>Capacities</span><strong>{{ implode(', ', array_map(fn($v) => $v.' ml', $product->ml_capacities ?? [])) ?: '-' }}</strong>
                    </div>
                    <div class="info"><span>Ready
                            Stock</span><strong>{{ $product->ready_stock_available ? 'Available' : 'No' }}</strong>
                    </div>
                    <div class="info"><span>Ready MOQ</span><strong>{{ $product->ready_stock_moq ? number_format($product->ready_stock_moq) . ' pcs' : '-' }}</strong>
                    </div>
                    <div class="info"><span>Custom MOQ</span><strong>{{ $product->customisation_moq ? number_format($product->customisation_moq) . ' pcs' : '-' }}</strong>
                    </div>
                </div>
            </div>
            <div class="card section">
                <h2>Specifications</h2>
                <div class="text"><strong>Finish:</strong>
                    {{ $product->finish_details ?: '-' }}<br><br><strong>Printing:</strong>
                    {{ $product->printing_details ?: '-' }}<br><br><strong>Material:</strong>
                    {{ $product->material_details ?: '-' }}<br><br><strong>Size:</strong>
                    {{ $product->size_measurements ?: '-' }}<br><br><strong>Weight:</strong>
                    {{ $product->weight_measurements ?: '-' }}</div>
            </div>
        </div>
        
    </div>
    @if ($product->show_price_ladder_public)
        <div class="card section" style="margin-top:20px;">
            <h2>Price Ladder</h2>
            <div class="wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Qty</th>
                            <th>Capacity</th>
                            <th>Finish</th>
                            <th>Printing</th>
                            <th>Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($product->priceLadders as $row)
                            <tr>
                                <td>{{ $row->quantity }} {{ $row->unit }}</td>
                                <td>{{ $row->capacity ? $row->capacity.' ml' : '-' }}</td>
                                <td>{{ $row->finish_type ?: '-' }}</td>
                                <td>{{ $row->printing_type ?: '-' }}</td>
                                <td>{{ $row->selling_cost_inr ? '₹ ' . number_format((float) $row->selling_cost_inr, 2) : '-' }}
                                </td>
                        </tr>@empty<tr>
                                <td colspan="5">No pricing added.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="helps" style="color:red;font-size:10px;margin-top:11px;text-align:right;">GST and Local Shipping charges will be additional to above prices.</div>
            </div>
        </div>
    @endif
    <footer class="card footer">
        <div class="footer-top" style="background:#D9A6A2;">
            <div class="footer-brand">
                <img src="{{ asset('images/logo-dark.png') }}" alt="MissPack - Packed Perfect">
                <p>MissPack helps brands source, develop and manage packaging with reliable client coordination and
                    transparent documentation.</p>
                <div class="footer-social" aria-label="Social media links">
                    <a href="https://www.facebook.com/misspackindia" target="_blank" rel="noopener" title="Facebook"
                        aria-label="Facebook">
                        <i class="fa-brands fa-facebook"></i>
                    </a>

                    <a href="https://www.instagram.com/themisspack" target="_blank" rel="noopener" title="Instagram"
                        aria-label="Instagram">
                        <i class="fa-brands fa-instagram"></i>
                    </a>

                    <a href="https://www.linkedin.com/company/misspackindia/" target="_blank" rel="noopener"
                        title="LinkedIn" aria-label="LinkedIn">
                        <i class="fa-brands fa-linkedin-in"></i>
                    </a>

                    <a href="https://wa.me/917048110823" target="_blank" rel="noopener" title="WhatsApp"
                        aria-label="WhatsApp">
                        <i class="fa-brands fa-whatsapp"></i>
                    </a>
                </div>
            </div>

            <div class="footer-col">
                <h4>Contact Details</h4>
                <div class="footer-list">
                    <a href="mailto:misspackindia@gmail.com"><i class="fa-regular fa-envelope"></i>
                        misspackindia@gmail.com</a>
                    <a href="tel:+917048110823">☎ +91 70481 10823</a>
                    <a href="https://www.themisspack.com" target="_blank" rel="noopener">🌐 www.themisspack.com</a>
                    <span>🕘 Monday to Saturday, 10:00 AM - 7:00 PM</span>
                </div>
            </div>

            <div class="footer-col">
                <h4>Address</h4>
                <p><span><b>MissPack India Private Limited</b></span><br>Ahmedabad, Gujarat, India</p>
                <p style="margin-top:10px;">For support, please contact our accounts or sales coordination team.
                </p>
            </div>
        </div>
        <div class="footer-bottom" style="background:#2f3a4c;color:grey;">
            <span>© {{ date('Y') }} MissPack. All rights reserved.</span>
            <span>Powered by <a href="https://themisspack.com" target="_blank" rel="noopener">MissPack</a> · Packed
                Perfect</span>
        </div>
    </footer>
    <div class="lightbox" id="lightbox">

        <button class="lightbox-close" onclick="closeLightbox()">
            <i class="fas fa-times"></i>
        </button>
    
        <button class="lightbox-arrow left" onclick="lightboxPrev()">
            <i class="fas fa-chevron-left"></i>
        </button>
    
        <img id="lightboxImage">
    
        <button class="lightbox-arrow right" onclick="lightboxNext()">
            <i class="fas fa-chevron-right"></i>
        </button>
    
    </div>
</div>
<script>
    window.productMediaItems = @json($mediaItems);
</script>
<script src="{{ asset('assets/js/product-public.js') }}"></script>
</body>

</html>
