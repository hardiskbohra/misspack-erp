@extends('layouts.app')

@section('page-title', 'Vendor Quote Product Image')

@section('content')
<style>
    :root {
        --img-primary: #4f83f1;
        --img-primary-2: #6366f1;
        --img-dark: #17233b;
        --img-muted: #687386;
        --img-border: #dfe7f3;
        --img-bg: #eef3ff;
        --img-soft: #edf5ff;
        --img-white: #ffffff;
        --img-shadow: 0 14px 35px rgba(25, 42, 70, 0.08);
    }

    .image-view-page, .image-view-page * { box-sizing: border-box; }
    .image-view-page {
        background: var(--img-bg);
        min-height: calc(100vh - 70px);
        padding: 28px;
        color: var(--img-dark);
        font-size: 14px;
    }

    .image-card {
        background: var(--img-white);
        border: 1px solid var(--img-border);
        border-radius: 18px;
        box-shadow: var(--img-shadow);
    }

    .image-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 22px 28px;
        margin-bottom: 24px;
    }

    .image-header h1 { margin: 0; font-size: 22px; font-weight: 900; letter-spacing: -0.02em; }
    .image-header p { margin: 5px 0 0; color: var(--img-muted); font-weight: 700; }

    .image-actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .image-btn { min-height: 42px; border: 0; border-radius: 12px; padding: 11px 18px; font-size: 14px; font-weight: 900; text-decoration: none; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; white-space: nowrap; }
    .image-btn-primary { background: linear-gradient(135deg, var(--img-primary), var(--img-primary-2)); color: #fff; }
    .image-btn-soft { background: var(--img-soft); color: var(--img-primary); }
    .image-btn-light { background: #f3f6fb; color: var(--img-dark); }

    .image-stage { padding: 24px; display: grid; place-items: center; min-height: 620px; }
    .image-frame { width: 100%; min-height: 540px; border: 1px dashed var(--img-border); border-radius: 18px; background: #fbfdff; display: grid; place-items: center; overflow: auto; padding: 18px; }
    .image-frame img { max-width: 100%; max-height: 74vh; object-fit: contain; border-radius: 14px; box-shadow: 0 18px 40px rgba(15, 23, 42, 0.14); background: #fff; }
    .image-empty { text-align: center; color: var(--img-muted); font-weight: 800; }
    .image-empty .icon { width: 88px; height: 88px; margin: 0 auto 16px; border-radius: 22px; background: var(--img-soft); color: var(--img-primary); display: grid; place-items: center; font-size: 36px; }

    .image-meta { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; padding: 0 24px 24px; }
    .image-meta-item { padding: 14px; border: 1px solid var(--img-border); border-radius: 14px; background: #fbfdff; }
    .image-meta-item span { display: block; color: #7d8aa0; font-size: 11px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 5px; }
    .image-meta-item strong { overflow-wrap: anywhere; }

    @media (max-width: 900px) { .image-meta { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 700px) { .image-view-page { padding: 14px; } .image-header { align-items: flex-start; flex-direction: column; padding: 18px; } .image-actions, .image-actions .image-btn { width: 100%; } .image-stage { padding: 16px; min-height: 420px; } .image-frame { min-height: 360px; } .image-meta { grid-template-columns: 1fr; padding: 0 16px 16px; } }
</style>

<div class="image-view-page">
    <div class="image-card image-header">
        <div>
            <h1>{{ $title }}</h1>
            <p>{{ $subtitle }}</p>
        </div>
        <div class="image-actions">
            <a href="{{ $backUrl }}" class="image-btn image-btn-light">Back</a>
            @if($imagePath)
                <a href="{{ asset('storage/'.$imagePath) }}" target="_blank" class="image-btn image-btn-soft">Open Original</a>
                <a href="{{ asset('storage/'.$imagePath) }}" download class="image-btn image-btn-primary">Download Image</a>
            @endif
        </div>
    </div>

    <div class="image-card image-stage">
        <div class="image-frame">
            @if($imagePath)
                <img src="{{ asset('storage/'.$imagePath) }}" alt="{{ $title }}">
            @else
                <div class="image-empty">
                    <div class="icon">📦</div>
                    <div>No product image uploaded for this vendor quote or linked lead.</div>
                </div>
            @endif
        </div>
    </div>

    <div class="image-meta">
        <div class="image-meta-item"><span>Quote Number</span><strong>{{ $quote->quote_number }}</strong></div>
        <div class="image-meta-item"><span>Lead</span><strong>{{ $quote->lead?->lead_number ?: '-' }}</strong></div>
        <div class="image-meta-item"><span>Vendor</span><strong>{{ $quote->vendor?->vendor_name ?? $quote->vendor_name ?? '-' }}</strong></div><div class="image-meta-item"><span>Product</span><strong>{{ $quote->product?->name ?? $quote->product_name ?? '-' }}</strong></div>
        <div class="image-meta-item"><span>Status</span><strong>{{ $quote->statusLabel() }}</strong></div>
    </div>
</div>
@endsection
