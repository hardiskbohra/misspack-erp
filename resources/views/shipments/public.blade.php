<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shipment Tracking - {{ $shipment->shipment_number }} to {{ $shipment->to_name }}</title>
    <style>
        :root { --primary:#4f83f1; --purple:#8b5cf6; --green:#10b981; --orange:#f59e0b; --red:#ef4770; --dark:#17233b; --muted:#687386; --border:#dfe7f3; --bg:#eef3ff; --soft:#edf5ff; --shadow:0 14px 35px rgba(25,42,70,.08); }
        *{box-sizing:border-box}body{margin:0;background:var(--bg);font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:var(--dark);font-size:14px}.page{max-width:1100px;margin:0 auto;padding:28px}.card{background:#fff;border:1px solid var(--border);border-radius:18px;box-shadow:var(--shadow)}.header{padding:10px 20px;margin-bottom:22px;display:flex;justify-content:space-between;gap:18px;align-items:center}.brand{font-weight:900;color:var(--primary);letter-spacing:.03em}.header h1{margin:8px 0 6px;font-size:28px}.header p{margin:0;color:var(--muted);font-weight:700}.badge{display:inline-flex;border-radius:999px;padding:7px 12px;font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:.03em}.status-planning{background:#f3f6fb;color:#536079}.status-picked-up{background:#eaf1ff;color:#3f7cf4}.status-in-transit{background:#6b86c2;color:#FFF}.status-custom-hold{background:#fff4e5;color:#d97706}.status-delayed{background:#ffeaf0;color:#e11d48}.status-out-for-delivery{background:#90d6b1;color:#FFF}.status-delivered{background:green;color:#FFF}.status-cancelled{background:#f3f4f6;color:#4b5563}.grid{display:grid;grid-template-columns:1fr 1fr;gap:22px;margin-bottom:22px}.section{padding:24px}.section h2{font-size:18px;margin:0 0 18px;font-weight:600;color:var(--primary);}.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.info{padding:14px;border:1px solid var(--border);border-radius:14px;background:#fbfdff}.info span{display:block;color:#7d8aa0;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;margin-bottom:5px}.info strong{overflow-wrap:anywhere}.timeline{position:relative;padding-left:24px}.timeline:before{content:"";position:absolute;left:7px;top:8px;bottom:8px;width:2px;background:var(--border)}.timeline-item{position:relative;padding-bottom:22px}.timeline-item:before{content:"";position:absolute;left:-23px;top:3px;width:13px;height:13px;background:var(--primary);border-radius:50%;box-shadow:0 0 0 4px #eaf1ff}.timeline-meta{font-size:12px;color:var(--muted);font-weight:500;margin-top:6px}.timeline-remarks{margin-top:8px;font-weight:650;color:#536079;line-height:1.55}.table-wrap{overflow-x:auto}.table{width:100%;min-width:700px;border-collapse:collapse}.table th,.table td{padding:14px;border-bottom:1px solid var(--border);text-align:left}.table th{font-size:11px;color:#000;text-transform:uppercase;letter-spacing:.06em}
        
        .photo-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.photo-card{border:1px solid var(--border);border-radius:14px;overflow:hidden;background:#fbfdff;text-decoration:none}.photo-card img{width:100%;height:170px;object-fit:cover;display:block}.photo-card div{padding:10px;color:var(--dark);font-size:12px;font-weight:900;overflow-wrap:anywhere}@media(max-width:800px){.photo-grid{grid-template-columns:1fr}}
        
        .public-footer {
            margin-top: 24px;
            overflow: hidden;
            background: #D9A6A2;
        }
    
        .public-footer-top {
            display: grid;
            grid-template-columns: 1.35fr 1fr 1fr;
            gap: 22px;
            padding: 26px;
            background: #fff
        }
    
        .public-footer-brand img {
            height: 56px;
            width: auto;
            max-width: 220px;
            object-fit: contain;
            display: block;
            margin-bottom: 12px
        }
    
        .public-footer-brand p,
        .public-footer-col p {
            margin: 0;
            color: #000;
            font-weight: 400;
            line-height: 1.65
        }
    
        .public-footer-col h4 {
            margin: 0 0 12px;
            font-size: 14px;
            font-weight: 600;
            color: #000;
            text-transform: uppercase;
            letter-spacing: .04em
        }
    
        .public-footer-list {
            display: grid;
            gap: 9px
        }
    
        .public-footer-list a,
        .public-footer-list span {
            color: #000;
            text-decoration: none;
            font-weight: 400;
            line-height: 1.45
        }
    
        .public-footer-list a:hover {
            color: var(--primary)
        }
    
        .public-social {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 14px
        }
    
        .public-social a {
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
    
        .public-footer-bottom {
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
    
        .public-footer-bottom a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500
        }
        
        .public-social svg {
            width: 18px;
            height: 18px;
            display: block;
            fill: currentColor;
        }
        
        /* =========================
           TABLET
        ========================= */
        @media (max-width: 992px){
        
            .page{
                padding:20px;
            }
        
            .layout{
                grid-template-columns:1fr;
                gap:22px;
            }
        
            .header{
                padding:26px;
            }
        
            .header-top,
            .header-bottom{
                justify-content:center;
                text-align:center;
            }
        
            .header-content{
                align-items:center;
                text-align:center;
            }
        
            .brand-logo{
                height:110px !important;
                width:auto;
            }
        
            .main-media{
                min-height:420px;
            }
        
            .main-media img,
            .main-media video{
                max-height:420px;
            }
        
            .thumbs{
                justify-content:center;
            }
        
            .info-grid{
                grid-template-columns:repeat(2,minmax(0,1fr));
                gap:16px;
            }
        
            .public-footer-top{
                grid-template-columns:1fr;
                text-align:center;
            }
        
            .public-footer-brand img{
                margin:auto;
            }
        
            .public-footer-social{
                justify-content:center;
            }
        
            .public-footer-bottom{
                justify-content:center;
                text-align:center;
            }
        
        }
        
        /* =========================
           MOBILE
        ========================= */
        @media (max-width:768px){
        
            .page{
                padding:12px;
            }
        
            .card{
                border-radius:18px;
            }
        
            .header{
                padding:20px 16px;
            }
        
            .brand-logo{
                height:80px !important;
            }
        
            .header-content h1{
                font-size:14px;
            }
        
            .header-content p{
                font-size:24px;
                line-height:1.3;
            }
        
            .badge{
                margin-top:12px;
                align-self:center;
            }
        
            .layout{
                gap:16px;
            }
        
            .gallery,
            .section{
                padding:16px;
            }
        
            .main-media{
                min-height:260px;
            }
        
            .main-media img,
            .main-media video{
                max-height:260px;
            }
        
            .thumb{
                width:60px;
                height:60px;
                flex:0 0 60px;
            }
        
            .gallery-arrow{
                width:38px;
                height:38px;
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
        
            .table th,
            .table td{
                padding:10px 12px;
                white-space:nowrap;
            }
        
            .public-footer-top{
                grid-template-columns:1fr;
                text-align:center;
            }
        
            .public-footer-brand img{
                margin:0 auto 16px;
            }
        
            .public-footer-social{
                justify-content:center;
            }
        
            .public-footer-bottom{
                flex-direction:column;
                text-align:center;
            }
        
        }
        
        /* =========================
           SMALL MOBILE
        ========================= */
        @media (max-width:480px){
        
            .brand-logo{
                height:65px !important;
            }
            
            .grid{display:grid;grid-template-columns:1fr;gap:22px;margin-bottom:22px}
        
            .header-content p{
                font-size:20px;
            }
        
            .main-media{
                min-height:220px;
            }
        
            .main-media img,
            .main-media video{
                max-height:220px;
            }
        
            .thumb{
                width:50px;
                height:50px;
                flex:0 0 50px;
            }
        
            .gallery-arrow{
                width:34px;
                height:34px;
                font-size:18px;
            }
        
            .badge{
                font-size:11px;
                padding:6px 10px;
            }
        
            .table{
                min-width:300px;
            }
        
        }
    </style>
</head>
<body>
@php($statusClass = str_replace('_', '-', $shipment->status))
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
            <div class="client-info">
                <h1 style="color:white;text-align:right;">{{ $shipment->identity_name }}</h1>
                <p style="color:#bbbbbb;text-align:right;font-weight:500;">{{ $shipment->shipment_number }} · Tracking: {{ $shipment->tracking_number ?: 'Not available' }}</p>
            </div>

            <div class="status-info" style="text-align:right; margin-top: 10px;">
                <span class="badge status-{{ $statusClass }}">
                    {{ $statusOptions[$shipment->status] ?? $shipment->status }}
                </span>
            </div>
        </div>

    </div>

    <div class="grid">
        <div class="card section">
            <h2>Shipment Summary</h2>
            <div class="info-grid">
                <div class="info"><span>Pickup Date</span><strong>{{ $shipment->pickup_date ? $shipment->pickup_date->format('d M') : '-' }}</strong></div>
                <div class="info"><span>Drop Date</span><strong>{{ $shipment->drop_date ? $shipment->drop_date->format('d M') : '-' }}</strong></div>
                <div class="info"><span>Logistic Partner</span><strong>{{ $shipment->logistic_partner ?: '-' }} </strong>
                    <span style="font-weight:500;line-height:1.8">{{ $shipment->tracking_number ?: 'Not available' }}</span></div>
                <div class="info"><span>Shipment Type</span><strong>{{ $shipment->typeLabel() }}</strong></div>
                <div class="info"><span>No. of Packages</span><strong>{{ $shipment->package_count ?? '-' }} {{ $shipment->package_count > 1 ? "Boxes" : "Box" }}</strong></div>
            </div>
        </div>
        <div class="card section">
            <h2>Route</h2>
            <div class="info-grid" style="line-height:1.4">
                <div class="info"><span>From</span><strong>{{ $shipment->from_name ?: '-' }}</strong><br><br>
                <span style="text-transform:capitalize;font-weight:400;font-size:12px;color:#555;">{{ $shipment->from_address ? $shipment->from_address . "," : "" }}<br>
                            {{ $shipment->from_city ? $shipment->from_city . "," : "" }}
                            {{ $shipment->from_state ? $shipment->from_state . "," : "" }}
                            {{ $shipment->from_country ? $shipment->from_country . " - " : "" }}
                            {{ $shipment->from_pincode ?? "" }}.<br></span><br>
                            <b>Mobile:</b> {{ $shipment->from_mobile ? $shipment->from_mobile : "" }}</div>
                <div class="info"><span>To</span><strong>{{ $shipment->to_name ?: '-' }}</strong><br><br>
                <span style="text-transform:capitalize;font-weight:400;font-size:12px;color:#555;">{{ $shipment->to_address ? $shipment->to_address . "," : "" }}<br>
                            {{ $shipment->to_city ? $shipment->to_city . "," : "" }}
                            {{ $shipment->to_state ? $shipment->to_state . "," : "" }}
                            {{ $shipment->to_country ? $shipment->to_country . " - " : "" }}
                            {{ $shipment->to_pincode ?? "" }}.<br></span><br>
                            <b>Mobile:</b> {{ $shipment->to_mobile ? $shipment->to_mobile : "" }}</div>
            </div>
        </div>
    </div>
    
    @if($shipment->publicAttachments->count())
        <div class="card section" style="margin-bottom:22px;">
            <h2>Shipment Photos</h2>
            <div class="photo-grid">
                @foreach($shipment->publicAttachments as $attachment)
                    <a class="photo-card" href="{{ asset('storage/'.$attachment->file_path) }}" target="_blank" rel="noopener">
                        <img src="{{ asset('storage/'.$attachment->file_path) }}" alt="{{ $attachment->title ?: $attachment->original_name }}">
                        <div style="font-weight:600;">{{ $attachment->title ?: $attachment->original_name }}</div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="card section" style="margin-bottom:22px;">
        <h2>Tracking History</h2>
        <div class="timeline">
            @forelse($shipment->histories as $history)
                @php($historyStatusClass = str_replace('_', '-', $history->status))
                <div class="timeline-item">
                    <span class="badge status-{{ $historyStatusClass }}">{{ $statusOptions[$history->status] ?? $history->status }}</span>
                    <div class="timeline-meta">{{ $history->event_time ? $history->event_time->format('d M Y, h:i A') : '-' }} @if($history->location) · {{ $history->location }} @endif</div>
                    @if($history->remarks)<div class="timeline-remarks">{{ $history->remarks }}</div>@endif
                </div>
            @empty
                <p style="color:var(--muted);font-weight:800;">Tracking details will be available soon.</p>
            @endforelse
        </div>
    </div>

    <div class="card section">
        <h2>Shipment Items</h2>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Product</th><th>HS Code</th><th>Qty</th></tr></thead>
                <tbody>
                    @forelse($shipment->items as $item)
                        <tr><td>{{ $item->product_name }}</td><td>{{ $item->hs_code ?: '-' }}</td><td>{{ (int) $item->quantity }} {{ $item->unit }}</td></tr>
                    @empty
                        <tr><td colspan="3">No public item information.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
        
    <footer class="card public-footer">
        <div class="public-footer-top" style="background:#D9A6A2;">
            <div class="public-footer-brand">
                <img src="{{ asset('images/logo-dark.png') }}" alt="MissPack - Packed Perfect">
                <p>MissPack helps brands source, develop and manage packaging with reliable client coordination and transparent documentation.</p>
                <div class="kyc-social" aria-label="Social media links">
                    <a href="https://www.facebook.com/misspackindia" target="_blank" rel="noopener" title="Facebook" aria-label="Facebook">
                        <i class="fa-brands fa-facebook"></i>
                    </a>
                
                    <a href="https://www.instagram.com/themisspack" target="_blank" rel="noopener" title="Instagram" aria-label="Instagram">
                        <i class="fa-brands fa-instagram"></i>
                    </a>
                
                    <a href="https://www.linkedin.com/company/misspackindia/" target="_blank" rel="noopener" title="LinkedIn" aria-label="LinkedIn">
                        <i class="fa-brands fa-linkedin-in"></i>
                    </a>
                
                    <a href="https://wa.me/917048110823" target="_blank" rel="noopener" title="WhatsApp" aria-label="WhatsApp">
                        <i class="fa-brands fa-whatsapp"></i>
                    </a>
                </div>
            </div>

            <div class="public-footer-col">
                <h4>Contact Details</h4>
                <div class="public-footer-list">
                    <a href="mailto:misspackindia@gmail.com"><i class="fa-regular fa-envelope"></i> misspackindia@gmail.com</a>
                    <a href="tel:+917048110823">☎ +91 70481 10823</a>
                    <a href="https://www.themisspack.com" target="_blank" rel="noopener">🌐 www.themisspack.com</a>
                    <span>🕘 Monday to Saturday, 10:00 AM - 7:00 PM</span>
                </div>
            </div>

            <div class="public-footer-col">
                <h4>Address</h4>
                <p><span><b>MissPack India Private Limited</b></span><br>Ahmedabad, Gujarat, India</p>
                <p style="margin-top:10px;">For Shipment support, please contact our shipment or sales coordination team.</p>
            </div>
        </div>
        <div class="public-footer-bottom" style="background:#2f3a4c;color:grey;">
            <span>© {{ date('Y') }} MissPack. All rights reserved.</span>
            <span>Powered by <a href="https://themisspack.com" target="_blank" rel="noopener">MissPack</a> · Packed Perfect</span>
        </div>
    </footer>
</div>
</body>
</html>
