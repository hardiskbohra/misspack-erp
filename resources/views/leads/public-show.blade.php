<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $lead->product_name ?: $lead->title }} - MissPack Product Details</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
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

        * { box-sizing: border-box; }
        html, body { width: 100%; max-width: 100%; overflow-x: hidden; }
        body { margin: 0; background: var(--bg); font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: var(--dark); font-size: 14px; line-height: 1.45; }
        .page { max-width: 1180px; margin: 0 auto; padding: 28px; }
        .card { background: var(--white); border: 1px solid var(--border); border-radius: 18px; box-shadow: var(--shadow); }
        .header { display: flex; justify-content: space-between; align-items: center; gap: 20px; padding: 26px; margin-bottom: 22px; }
        .brand { color: var(--primary); font-size: 12px; font-weight: 900; letter-spacing: .08em; text-transform: uppercase; }
        .header h1 { margin: 8px 0 6px; font-size: 28px; line-height: 1.18; font-weight: 900; letter-spacing: -.03em; }
        .header p { margin: 0; color: var(--muted); font-weight: 700; max-width: 760px; }
        .badge { display: inline-flex; align-items: center; gap: 8px; border-radius: 999px; padding: 10px 14px; background: var(--soft); color: var(--primary); font-weight: 900; white-space: nowrap; }
        .layout { display: grid; grid-template-columns: minmax(0, 1.35fr) minmax(320px, .65fr); gap: 22px; align-items: start; }
        .gallery { padding: 18px; }
        .main-media { min-height: 560px; border: 1px solid var(--border); border-radius: 16px; background: #fbfdff; display: grid; place-items: center; overflow: hidden; position: relative; }
        .main-media img, .main-media video { width: 100%; max-height: 620px; object-fit: contain; display: block; background: #fff; }
        .main-media video { height: auto; }
        .placeholder { text-align: center; color: var(--muted); font-weight: 800; padding: 50px 20px; }
        .placeholder .icon { width: 96px; height: 96px; border-radius: 24px; background: var(--soft); color: var(--primary); display: grid; place-items: center; font-size: 40px; margin: 0 auto 16px; }
        .gallery-arrow { position: absolute; top: 50%; transform: translateY(-50%); width: 42px; height: 42px; border: 0; border-radius: 50%; background: rgba(255,255,255,.95); color: var(--dark); cursor: pointer; box-shadow: 0 8px 20px rgba(15,23,42,.18); z-index: 5; }
        .gallery-arrow.left { left: 14px; }
        .gallery-arrow.right { right: 14px; }
        .thumbs { display: flex; gap: 10px; overflow-x: auto; padding: 14px 2px 2px; }
        .thumb { width: 76px; height: 76px; border-radius: 13px; border: 2px solid transparent; background: var(--soft); object-fit: cover; cursor: pointer; flex: 0 0 76px; display: inline-flex; align-items: center; justify-content: center; color: var(--primary); font-weight: 900; }
        .thumb.active { border-color: var(--primary); }
        .section { padding: 22px; margin-bottom: 22px; }
        .section h2 { margin: 0 0 16px; font-size: 18px; font-weight: 900; }
        .info-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .info { padding: 14px; border: 1px solid var(--border); border-radius: 14px; background: #fbfdff; }
        .info span { display: block; color: #7d8aa0; font-size: 11px; font-weight: 900; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 5px; }
        .info strong { display: block; overflow-wrap: anywhere; }
        .text-box { padding: 14px; border: 1px solid var(--border); border-radius: 14px; background: #fbfdff; color: #536079; font-weight: 700; line-height: 1.7; white-space: pre-line; }
        .doc-list { display: grid; gap: 10px; }
        .doc-link { display: block; padding: 12px 14px; border: 1px solid var(--border); border-radius: 12px; background: #fbfdff; color: var(--primary); font-weight: 800; text-decoration: none; overflow-wrap: anywhere; }
        .footer { margin-top: 24px; overflow: hidden; }
        .footer-top { display: grid; grid-template-columns: 1.4fr 1fr 1fr; gap: 22px; padding: 24px; }
        .footer h4 { margin: 0 0 10px; font-size: 14px; font-weight: 900; text-transform: uppercase; letter-spacing: .04em; }
        .footer p, .footer a, .footer span { color: var(--muted); font-weight: 700; line-height: 1.65; text-decoration: none; }
        .footer a:hover { color: var(--primary); }
        .footer-bottom { display: flex; justify-content: space-between; gap: 12px; padding: 14px 24px; border-top: 1px solid var(--border); background: #fbfdff; color: var(--muted); font-size: 12px; font-weight: 800; }
        @media(max-width: 1000px) { .layout { grid-template-columns: 1fr; } .main-media { min-height: 420px; } .footer-top { grid-template-columns: 1fr 1fr; } }
        @media(max-width: 700px) { .page { padding: 14px; } .header { flex-direction: column; align-items: flex-start; padding: 20px; } .header h1 { font-size: 22px; } .badge { align-self: flex-start; } .main-media { min-height: 320px; } .gallery { padding: 12px; } .info-grid { grid-template-columns: 1fr; } .section { padding: 18px; } .footer-top { grid-template-columns: 1fr; text-align: center; } .footer-bottom { flex-direction: column; text-align: center; align-items: center; } }
    </style>
</head>
<body>
@php
    $media = [];
    $documents = [];
    $externalLinks = [];

    if ($lead->product_image_path) {
        $media[] = [
            'type' => 'image',
            'url' => asset('storage/'.$lead->product_image_path),
            'title' => $lead->product_name ?: 'Product Image',
        ];
    }

    foreach ($lead->attachments as $attachment) {
        if ($attachment->file_path) {
            $mime = (string) $attachment->mime_type;
            $url = asset('storage/'.$attachment->file_path);

            if ($attachment->attachment_type === 'photo' || strpos($mime, 'image/') === 0) {
                $media[] = ['type' => 'image', 'url' => $url, 'title' => $attachment->title ?: $attachment->original_name];
            } elseif ($attachment->attachment_type === 'video' || strpos($mime, 'video/') === 0) {
                $media[] = ['type' => 'video', 'url' => $url, 'title' => $attachment->title ?: $attachment->original_name];
            } else {
                $documents[] = ['url' => $url, 'title' => $attachment->title ?: $attachment->original_name ?: 'Attachment'];
            }
        }

        if ($attachment->external_url) {
            $externalLinks[] = ['url' => $attachment->external_url, 'title' => $attachment->title ?: $attachment->external_url];
        }
    }
@endphp
<div class="page">
    <div class="card header">
        <div>
            <div class="brand">MISSPACK PRODUCT DETAILS</div>
            <h1>{{ $lead->product_name ?: $lead->title }}</h1>
            <p>{{ $lead->title }} @if($lead->lead_number) · Ref: {{ $lead->lead_number }} @endif</p>
        </div>
        <span class="badge">📦 Product Requirement</span>
    </div>

    <div class="layout">
        <div class="card gallery">
            <div class="main-media" id="mainMedia">
                @if(count($media))
                    {{-- Rendered by JS --}}
                @else
                    <div class="placeholder"><div class="icon">📦</div><div>No image or video uploaded for this product yet.</div></div>
                @endif
            </div>
            @if(count($media) > 1)
                <div class="thumbs" id="thumbs"></div>
            @endif
        </div>

        <div>
            <div class="card section">
                <h2>Product Summary</h2>
                <div class="info-grid">
                    <div class="info"><span>Product</span><strong>{{ $lead->product_name ?: '-' }}</strong></div>
                    <div class="info"><span>Capacity</span><strong>{{ $lead->capacity_value ? $lead->capacity_value.' '.$lead->capacity_unit : '-' }}</strong></div>
                    <div class="info"><span>Required Quantity</span><strong>{{ $lead->required_quantity ? number_format($lead->required_quantity).' pcs' : '-' }}</strong></div>
                    <div class="info"><span>Quote Quantities</span><strong>{{ implode(', ', $lead->quote_quantities ?? []) ?: '-' }}</strong></div>
                    <div class="info"><span>Finish</span><strong>{{ $finishOptions[$lead->finish_required] ?? '-' }}</strong></div>
                    <div class="info"><span>Printing</span><strong>{{ $printingOptions[$lead->printing_required] ?? '-' }}</strong></div>
                    <div class="info"><span>Ready Stock</span><strong>{{ $lead->ready_stock_required ? 'Required' : 'No' }}</strong></div>
                    <div class="info"><span>Custom Color</span><strong>{{ $lead->custom_color_required ? 'Required' : 'No' }}</strong></div>
                </div>
            </div>

            <div class="card section">
                <h2>Description</h2>
                <div class="text-box">{{ $lead->product_description ?: 'No product description provided.' }}</div>
            </div>

            @if($lead->ready_stock_color_requirement || $lead->custom_color_specification || $lead->printing_details)
                <div class="card section">
                    <h2>Additional Requirements</h2>
                    @if($lead->printing_details)<div class="text-box" style="margin-bottom:10px;"><strong>Printing:</strong><br>{{ $lead->printing_details }}</div>@endif
                    @if($lead->ready_stock_color_requirement)<div class="text-box" style="margin-bottom:10px;"><strong>Ready Stock:</strong><br>{{ $lead->ready_stock_color_requirement }}</div>@endif
                    @if($lead->custom_color_specification)<div class="text-box"><strong>Custom Color:</strong><br>{{ $lead->custom_color_specification }}</div>@endif
                </div>
            @endif

            @if(count($documents) || count($externalLinks))
                <div class="card section">
                    <h2>Documents & Links</h2>
                    <div class="doc-list">
                        @foreach($documents as $document)
                            <a class="doc-link" href="{{ $document['url'] }}" target="_blank" rel="noopener">📎 {{ $document['title'] }}</a>
                        @endforeach
                        @foreach($externalLinks as $link)
                            <a class="doc-link" href="{{ $link['url'] }}" target="_blank" rel="noopener">🔗 {{ $link['title'] }}</a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    <footer class="card footer">
        <div class="footer-top">
            <div><h4>MissPack</h4><p>Packaging sourcing, development and vendor coordination for modern brands.</p></div>
            <div><h4>Contact</h4><p><a href="mailto:admin@misspack.com">admin@misspack.com</a><br><a href="tel:+917048110823">+91 70481 10823</a></p></div>
            <div><h4>Location</h4><p>Ahmedabad, Gujarat, India<br>Packed Perfect</p></div>
        </div>
        <div class="footer-bottom"><span>© {{ date('Y') }} MissPack. All rights reserved.</span><span>Public product details page</span></div>
    </footer>
</div>
<script>
    const mediaItems = @json($media);
    let currentMediaIndex = 0;

    function renderMedia(index) {
        const main = document.getElementById('mainMedia');
        const item = mediaItems[index];

        if (!main || !item) return;

        if (item.type === 'video') {
            main.innerHTML = `<video src="${item.url}" controls playsinline></video>`;
        } else {
            main.innerHTML = `<img src="${item.url}" alt="${item.title || 'Product Media'}">`;
        }

        if (mediaItems.length > 1) {
            main.insertAdjacentHTML('beforeend', `<button type="button" class="gallery-arrow left" onclick="previousMedia()">‹</button><button type="button" class="gallery-arrow right" onclick="nextMedia()">›</button>`);
        }

        document.querySelectorAll('.thumb').forEach((thumb, thumbIndex) => {
            thumb.classList.toggle('active', thumbIndex === index);
        });
    }

    function nextMedia() {
        if (!mediaItems.length) return;
        currentMediaIndex = (currentMediaIndex + 1) % mediaItems.length;
        renderMedia(currentMediaIndex);
    }

    function previousMedia() {
        if (!mediaItems.length) return;
        currentMediaIndex = (currentMediaIndex - 1 + mediaItems.length) % mediaItems.length;
        renderMedia(currentMediaIndex);
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (mediaItems.length) {
            renderMedia(0);
        }

        const thumbs = document.getElementById('thumbs');
        if (thumbs) {
            mediaItems.forEach((item, index) => {
                const thumb = document.createElement(item.type === 'image' ? 'img' : 'button');
                thumb.className = 'thumb' + (index === 0 ? ' active' : '');

                if (item.type === 'image') {
                    thumb.src = item.url;
                    thumb.alt = item.title || 'Image';
                } else {
                    thumb.type = 'button';
                    thumb.textContent = '▶';
                    thumb.title = item.title || 'Video';
                }

                thumb.addEventListener('click', function () {
                    currentMediaIndex = index;
                    renderMedia(index);
                });

                thumbs.appendChild(thumb);
            });
        }
    });
</script>
</body>
</html>
