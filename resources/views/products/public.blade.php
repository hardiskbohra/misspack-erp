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
    <style>
        :root {
            --p: #4f83f1;
            --d: #17233b;
            --m: #687386;
            --b: #dfe7f3;
            --bg: #eef3ff;
            --s: #edf5ff;
            --sh: 0 14px 35px rgba(25, 42, 70, .08);
            --primary: #4f83f1;
            --primary2: #6366f1;
            --green: #10b981;
            --orange: #f59e0b;
            --red: #ef4770;
            --dark: #17233b;
            --muted: #687386;
            --border: #dfe7f3;
            --bg: #eef3ff;
            --soft: #edf5ff;
            --white: #ffffff;
            --shadow: 0 14px 35px rgba(25, 42, 70, .08);
        }

        * {
            box-sizing: border-box
        }

        body {
            margin: 0;
            background: var(--bg);
            font-family: Inter, Arial, sans-serif;
            color: var(--d);
            font-size: 14px
        }

        .page {
            max-width: 1180px;
            margin: 0 auto;
            padding: 28px
        }

        .card {
            background: #fff;
            border: 1px solid var(--b);
            border-radius: 18px;
            box-shadow: var(--sh)
        }

        .header {
            padding: 10px 28px;
            margin-bottom: 18px;
            display: flex;
            justify-content: space-between;
            gap: 18px;
            align-items: center
        }

        .brand {
            font-weight: 900;
            color: var(--primary);
            letter-spacing: .03em
        }

        .brand-title {
            font-size: 24px;
            font-weight: 900;
            color: #4f83f1;
        }

        .header-bottom{
            display: flex;
            justify-content: flex-end;
            align-items: center;
        }
        
        .header-content{
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            text-align: right;
            gap: 10px;
        }
        
        .header-content h1{
            margin: 0;
            color: #ddd;
            font-size: 14px;
            font-weight: 600;
            line-height: 1.1;
        }
        
        .header-content p{
            margin: 0;
            color: #fff;
            font-size: 24px;
            font-weight: 500;
        }
        
        .badge{
            display: inline-flex;
            align-items: center;
            justify-content: center;
            align-self: flex-end; /* Keeps badge right aligned */
        
            border-radius: 999px;
            background: #D9A6A2;
        
            padding: 10px 22px;
        
            color: #fff;
            font-size: 12px;
            font-weight: 500;
            letter-spacing: .04em;
        }

        .status-new {
            background: #f3f6fb;
            color: #536079
        }

        .layout {
            display: grid;
            grid-template-columns: 1.35fr .65fr;
            gap: 22px
        }

        .gallery {
            padding: 18px
        }

        .main-media {
            min-height: 560px;
            border: 1px solid var(--b);
            border-radius: 16px;
            background: #fbfdff;
            display: grid;
            place-items: center;
            overflow: hidden;
            position: relative
        }

        .main-media img,
        .main-media video {
            width: 100%;
            max-height: 620px;
            object-fit: contain
        }

        .thumbs {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding: 14px 2px 2px
        }

        .thumb {
            width: 76px;
            height: 76px;
            border-radius: 13px;
            border: 2px solid transparent;
            background: var(--s);
            object-fit: cover;
            cursor: pointer;
            flex: 0 0 76px;
            display: flex;
            align-items: center;
            justify-content: center
        }

        .thumb.active {
            border-color: var(--p)
        }

        .arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 42px;
            height: 42px;
            border: 0;
            border-radius: 50%;
            background: #fff;
            box-shadow: 0 8px 20px rgba(15, 23, 42, .18);
            cursor: pointer
        }

        .left {
            left: 14px
        }

        .right {
            right: 14px
        }

        .section {
            padding: 22px;
            margin-bottom: 22px
        }

        .section h2 {
            margin: 0 0 16px;
            font-size: 18px;
            font-weight: 600
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px
        }

        .info {
            padding: 14px;
            border: 1px solid var(--b);
            border-radius: 14px;
            background: #fbfdff
        }

        .info span {
            display: block;
            color: #000;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 5px
        }

        .info strong {
            font-weight: 500;
        }

        .text {
            padding: 14px;
            border: 1px solid var(--b);
            border-radius: 14px;
            background: #fbfdff;
            line-height: 1.7;
            white-space: pre-line
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            min-width: 680px
        }

        .wrap {
            overflow-x: auto
        }

        .table th,
        .table td {
            padding: 12px;
            border-bottom: 1px solid var(--b);
            text-align: left
        }

        .table th {
            font-size: 13px;
            color: #000;
            font-weight: 600;
            text-transform: uppercase
        }

        .footer {
            margin-top: 24px;
            overflow: hidden;
            background: #D9A6A2;
        }

        .footer-top {
            display: grid;
            grid-template-columns: 1.35fr 1fr 1fr;
            gap: 22px;
            padding: 26px;
            background: #fff
        }

        .footer-brand img {
            height: 56px;
            width: auto;
            max-width: 220px;
            object-fit: contain;
            display: block;
            margin-bottom: 12px
        }

        .footer-brand p,
        .footer-col p {
            margin: 0;
            color: #000;
            font-weight: 400;
            line-height: 1.65
        }

        .footer-col h4 {
            margin: 0 0 12px;
            font-size: 14px;
            font-weight: 600;
            color: #000;
            text-transform: uppercase;
            letter-spacing: .04em
        }

        .footer-list {
            display: grid;
            gap: 9px
        }

        .footer-list a,
        .footer-list span {
            color: #000;
            text-decoration: none;
            font-weight: 400;
            line-height: 1.45
        }

        .footer-list a:hover {
            color: var(--primary)
        }

        .footer-social {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 14px
        }

        .footer-social a {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--soft);
            color: var(--primary);
            font-weight: 500;
            text-decoration: none;
            border: 1px solid var(--border)
        }

        .footer-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            padding: 14px 26px;
            border-top: 1px solid var(--border);
            background: #fbfdff;
            color: var(--muted);
            font-size: 12px;
            font-weight: 500
        }

        .footer-bottom a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500
        }
        
        .lightbox{
            position:fixed;
            inset:0;
            background:rgba(0,0,0,.92);
            display:none;
            align-items:center;
            justify-content:center;
            z-index:99999;
        }
        
        .lightbox.open{
            display:flex;
        }
        
        .lightbox img{
            max-width:92vw;
            max-height:92vh;
            object-fit:contain;
            border-radius:10px;
        }
        
        .lightbox-close{
            position:absolute;
            top:20px;
            right:20px;
            width:48px;
            height:48px;
            border:none;
            border-radius:50%;
            background:#fff;
            cursor:pointer;
            font-size:18px;
        }
        
        .lightbox-arrow{
            position:absolute;
            top:50%;
            transform:translateY(-50%);
            width:52px;
            height:52px;
            border:none;
            border-radius:50%;
            background:#fff;
            cursor:pointer;
            font-size:18px;
        }
        
        .lightbox-arrow.left{
            left:20px;
        }
        
        .lightbox-arrow.right{
            right:20px;
        }
        
        @media(max-width:768px){
        
            .lightbox-arrow{
                width:42px;
                height:42px;
            }
        
            .lightbox-close{
                width:42px;
                height:42px;
            }
        
        }

        @media (max-width:992px){

            .page{
                padding:20px;
            }
        
            .layout{
                grid-template-columns:1fr;
                gap:20px;
            }
        
            .header{
                padding:24px;
            }
        
            .main-media{
                min-height:420px;
            }
        
            .footer-top{
                grid-template-columns:1fr;
                gap:24px;
            }
        
            .info-grid{
                grid-template-columns:repeat(2,1fr);
            }
        
        }
        
        @media (max-width:768px){

            .page{
                padding:12px;
            }
        
            .header{
                flex-direction:column;
                align-items:center;
                text-align:center;
                padding:22px 18px;
            }
        
            .brand-logo{
                height:90px !important;
                width:auto;
            }
        
            .header-bottom{
                width:100%;
                justify-content:center;
            }
        
            .header-content{
                align-items:center;
                text-align:center;
            }
        
            .header-content h1{
                font-size:14px;
            }
        
            .header-content p{
                font-size:24px;
                line-height:1.3;
            }
        
            .badge{
                align-self:center;
                padding:8px 18px;
            }
        
            .layout{
                grid-template-columns:1fr;
            }
        
            .gallery{
                padding:14px;
            }
        
            .main-media{
                min-height:300px;
            }
        
            .main-media img,
            .main-media video{
                max-height:300px;
            }
        
            .thumb{
                width:60px;
                height:60px;
                flex:0 0 60px;
            }
        
            .section{
                padding:18px;
            }
        
            .section h2{
                font-size:18px;
            }
        
            .info-grid{
                grid-template-columns:1fr;
            }
        
            .info{
                padding:14px;
            }
        
            .wrap{
                overflow-x:auto;
                -webkit-overflow-scrolling:touch;
            }
        
            .table{
                min-width:650px;
            }
        
            .footer-top{
                grid-template-columns:1fr;
                text-align:center;
            }
        
            .footer-brand img{
                margin:0 auto 16px;
            }
        
            .footer-social{
                justify-content:center;
            }
        
            .footer-bottom{
                flex-direction:column;
                text-align:center;
            }
        
        }
        
        @media (max-width:480px){

            .brand-logo{
                height:70px !important;
            }
        
            .header-content p{
                font-size:20px;
            }
        
            .badge{
                font-size:11px;
            }
        
            .main-media{
                min-height:240px;
            }
        
            .main-media img,
            .main-media video{
                max-height:240px;
            }
        
            .thumb{
                width:52px;
                height:52px;
                flex:0 0 52px;
            }
        
            .arrow{
                width:34px;
                height:34px;
            }
        
        }
    </style>
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
    const items = @json($mediaItems);
    let current = 0;

    function render(i) {
        const main = document.getElementById('mainMedia');
        const item = items[i];
        if (!item) return;
        main.innerHTML = item.type === 'video' ? `<video src="${item.url}" controls playsinline></video>` :
            `<img src="${item.url}" alt="${item.title || 'Product'}" onclick="openLightbox()" style="cursor:zoom-in;">`;
        if (items.length > 1) main.insertAdjacentHTML('beforeend',
            '<button class="arrow left" onclick="prev()">‹</button><button class="arrow right" onclick="next()">›</button>'
            );
        document.querySelectorAll('.thumb').forEach((t, idx) => t.classList.toggle('active', idx === i));
    }

    function next() {
        current = (current + 1) % items.length;
        render(current)
    }

    function prev() {
        current = (current - 1 + items.length) % items.length;
        render(current)
    }
    document.addEventListener('DOMContentLoaded', () => {
        if (items.length) render(0);
        const t = document.getElementById('thumbs');
        if (t) {
            items.forEach((item, i) => {
                const el = document.createElement(item.type === 'image' ? 'img' : 'button');
                el.className = 'thumb' + (i === 0 ? ' active' : '');
                if (item.type === 'image') {
                    el.src = item.url
                } else {
                    el.textContent = '▶'
                }
                el.onclick = () => {
                    current = i;
                    render(i)
                };
                t.appendChild(el);
            })
        }
    });
    
    function openLightbox(){

        if(items[current].type !== 'image') return;
    
        document.getElementById('lightboxImage').src = items[current].url;
    
        document.getElementById('lightbox').classList.add('open');
    
        document.body.style.overflow = 'hidden';
    }
    
    function closeLightbox(){
    
        document.getElementById('lightbox').classList.remove('open');
    
        document.body.style.overflow = '';
    }
    
    function lightboxNext(){
    
        next();
    
        document.getElementById('lightboxImage').src = items[current].url;
    }
    
    function lightboxPrev(){
    
        prev();
    
        document.getElementById('lightboxImage').src = items[current].url;
    }
    
    document.getElementById('lightbox').addEventListener('click',function(e){
    
        if(e.target===this){
            closeLightbox();
        }
    
    });
    
    document.addEventListener('keydown',function(e){
    
        if(!document.getElementById('lightbox').classList.contains('open')) return;
    
        if(e.key==="Escape") closeLightbox();
    
        if(e.key==="ArrowRight") lightboxNext();
    
        if(e.key==="ArrowLeft") lightboxPrev();
    
    });
</script>
</body>

</html>
