@extends('layouts.app')

@section('page-title', 'Product Detail')

@section('content')
    @push('styles')
        <link rel="stylesheet" href="{{ asset('assets/css/master-media.css') }}">
    @endpush
    @php($primary = $product->primaryMedia())
    <div class="master">
        <div class="master-card master-header">
            <div class="master-head-left">
                @if ($primary && $primary->file_path && $primary->media_type === 'image')
                <img class="master-img" src="{{ asset('storage/' . $primary->file_path) }}">@else<span
                        class="master-img">📦</span>
                @endif
                <div>
                    <h1>{{ $product->name }}</h1>
                    <p>{{ $product->product_number }}</p>
                </div>
            </div>
            <div class="master-actions"><a href="{{ route('products.index') }}" class="master-btn master-btn-light">Back</a><a
                    href="{{ route('products.public', $product->public_token) }}" target="_blank"
                    class="master-btn master-btn-light">Public Link</a><a href="{{ route('products.edit', $product) }}"
                    class="master-btn master-btn-primary">Edit Product</a></div>
        </div>
        <div class="master-grid">
            <div>
                <div class="master-card master-section">
                    <h3 class="master-section-title">Product Overview</h3>
                    <div class="master-info-grid">
                        <div class="master-info"><span>Status</span><strong>{{ $product->statusLabel() }}</strong></div>
                        <div class="master-info"><span>Category</span><strong>{{ $product->category ?: '-' }}</strong></div>
                        <div class="master-info">
                            <span>Capacities</span><strong>{{ implode(', ', $product->ml_capacities ?? []) ?: '-' }}</strong>
                        </div>
                        <div class="master-info"><span>Ready
                                Stock</span><strong>{{ $product->ready_stock_available ? 'Available' : 'No' }}</strong>
                        </div>
                        <div class="master-info"><span>Ready
                                MOQ</span><strong>{{ $product->ready_stock_moq ?: '-' }}</strong></div>
                        <div class="master-info"><span>Custom
                                MOQ</span><strong>{{ $product->customisation_moq ?: '-' }}</strong></div>
                    </div>
                </div>
                <div class="master-card master-section">
                    <h3 class="master-section-title">Product Details</h3>
                    <div class="master-text">{{ $product->description ?: 'No description.' }}</div>
                </div>
                <div class="master-card master-section">
                    <h3 class="master-section-title">Price Ladder</h3>
                    <div class="master-table-wrap">
                        <table class="master-table">
                            <thead>
                                <tr>
                                    <th>Qty</th>
                                    <th>Capacity</th>
                                    <th>Finish</th>
                                    <th>Printing</th>
                                    <th>Landing</th>
                                    <th>Selling</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($product->priceLadders as $row)
                                    <tr style="line-height:1.5;">
                                        <td>{{ $row->quantity }} {{ $row->unit }}</td>
                                        <td>{{ $row->capacity ? $row->capacity . 'ml' : '-' }}</td>
                                        <td>{{ $row->finish_type ?: '-' }}</td>
                                        <td>{{ $row->printing_type ?: '-' }}</td>
                                        <td>{{ $row->landing_cost_inr ? '₹ ' . number_format((float) $row->landing_cost_inr, 2) : '-' }}
                                        </td>
                                        <td>{{ $row->selling_cost_inr ? '₹ ' . number_format((float) $row->selling_cost_inr, 2) : '-' }}
                                        </td>
                                </tr>@empty<tr>
                                        <td colspan="6">No ladder added.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="master-card master-section">
                    <h3 class="master-section-title">Vendor Quotes</h3>
                    <div class="master-table-wrap">
                        <table class="master-table">
                            <thead>
                                <tr>
                                    <th>Vendor</th>
                                    <th>Qty</th>
                                    <th>Price</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($product->vendorQuotes as $quote)
                                    <tr style="line-height:1.5;">
                                        <td>{{ $quote->vendor?->vendor_contact_name ?? ($quote->vendor_contact_name ?? '-') }}</td>
                                        <td>{!! $quote->items->isNotEmpty()
                                            ? $quote->items->map(fn($item) => number_format($item->quantity) . ' ' . $item->unit)->implode('<br>')
                                            : ($quote->quantity ? number_format($quote->quantity) . ' ' . $quote->unit : '-')
                                        !!}</td>
                                        <td>{!! $quote->items->isNotEmpty()
                                            ? $quote->items->map(fn($item) => $quote->currency . ' ' . number_format($item->vendor_unit_price, 2) . ($quote->incoterm ? ' ' . $quote->incoterm : ''))->implode('<br>')
                                            : ($quote->vendor_unit_price
                                                ? $quote->currency . ' ' . number_format((float) $quote->vendor_unit_price, 2) . ($quote->incoterm ? ' ' . $quote->incoterm : '')
                                                : '-')
                                        !!}</td>
                                        <td><a href="{{ route('vendor-quotes.show',$quote) }}">View</a></td>
                                    </tr>@empty<tr>
                                        <td colspan="6">No ladder added.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div>
                <div class="master-card master-section">
                    <h3 class="master-section-title">Specifications</h3>
                    <div class="master-text">
                        <strong>Finish:</strong><br>{{ $product->finish_details ?: '-' }}<br><br><strong>Printing:</strong><br>{{ $product->printing_details ?: '-' }}<br><br><strong>Material:</strong><br>{{ $product->material_details ?: '-' }}<br><br><strong>Size:</strong><br>{{ $product->size_measurements ?: '-' }}<br><br><strong>Weight:</strong><br>{{ $product->weight_measurements ?: '-' }}
                    </div>
                </div>
                
                <div class="master-card master-section">
                    <h3 class="master-section-title">Photos & Media</h3>
                    <div class="photo-grid">
                        @forelse($product->media as $media)
                            <a class="photo-card"
                               href="{{ asset('storage/'.$media->file_path) }}"
                               target="_blank">
                    
                                @if(Str::contains(strtolower($media->file_path), '.mp4'))
                                    <video controls preload="metadata">
                                        <source src="{{ asset('storage/'.$media->file_path) }}" type="video/mp4">
                                    </video>
                                @else
                                    <img
                                        src="{{ asset('storage/'.$media->file_path) }}"
                                        alt="{{ $media->title ?: $media->original_name }}">
                                @endif
                            </a>
                        @empty
                            <p style="color:var(--master-muted);font-weight:800;">
                                No shipment photos uploaded yet.
                            </p>
                        @endforelse
                    </div>
                </div>
                
            </div>
        </div>
    </div>
@endsection
