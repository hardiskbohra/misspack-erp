<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $lead->product_name ?: $lead->title }} - MissPack Product Details</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/leads-public-show.css') }}">
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
    window.leadMediaItems = @json($media);
</script>
<script src="{{ asset('assets/js/leads-public.js') }}"></script>
</body>
</html>
