@extends('client_portal.layouts.app')

@section('title', 'Products')
@section('page-title', 'Products')

@section('content')
    <div class="master-card master-header">
        <div>
            <h1>Products</h1>
            <p style="font-size:16px;font-weight:500;">Products related to your projects.</p>
        </div>
    </div>
    <div class="master-card" style="padding:18px;margin-bottom:18px;">
        <form method="GET" class="cp-grid-4">
            <div class="cp-field">
                <label>Search</label>
                <input name="search" value="{{ $search }}" placeholder="Search product, SKU, category...">
            </div>
            <div class="cp-field">
                <label>Category</label>
                <select name="category">
                    <option value="all">All Categories</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat }}" {{ $category === $cat ? 'selected' : '' }}>{{ $cat }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div style="display:flex;align-items:end;gap:10px;">
                <button class="cp-btn cp-btn-primary">Filter</button>
                <a class="cp-btn cp-btn-light" style="padding:8px 15px;" href="{{ route('client-portal.products.index') }}">Reset</a>
            </div>
        </form>
    </div>
    <div class="cp-grid-4">
        @forelse($products as $product)
            @php($media = $product->primaryMedia())
            <div class="cp-card" style="overflow:hidden;">
                @if ($media && $media->file_path && $media->media_type === 'image')
                    <img src="{{ asset('storage/' . $media->file_path) }}"
                    style="width:100%;height:300px;object-fit:cover;display:block;">@else<div
                        style="height:230px;background:#f3f6fb;display:grid;place-items:center;font-size:44px;color:#bbb;">◈
                    </div>
                @endif
                <div style="padding:16px;">
                    <div class="cp-muted">{{ $product->product_number }}</div>
                    <h3 style="margin:5px 0;">{{ $product->name }}</h3>
                    <div class="cp-muted">{{ $product->category ?: '-' }}</div>
                    <div class="cp-muted">{{ $product->notes ?: '-' }}</div>
                    <div style="margin:12px 0;">
                        <span class="cp-badge status-{{ $product->status }}">{{ $product->statusLabel() }}</span>
                    </div>
                </div>
            </div>
        @empty
            <div class="cp-empty" style="grid-column:1/-1;">No related products found.</div>
        @endforelse
    </div>
    <x-pagination :items="$products" />
@endsection
