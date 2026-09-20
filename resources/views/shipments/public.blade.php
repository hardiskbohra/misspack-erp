<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shipment Tracking - {{ $shipment->shipment_number }} to {{ $shipment->to_name }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/shipment-public.css') }}">
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
