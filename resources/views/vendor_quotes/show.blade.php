@extends('layouts.app')

@section('page-title', 'Vendor Quote Detail')

@section('content')
    @php($productMedia = $quote->product?->primaryMedia())
    @php($quoteImage = $quote->product_image_path ?: ($productMedia?->file_path ?: $quote->lead?->product_image_path))
    <div class="master">
        <div class="master-card master-header">
            <div class="master-head-left">
                @if ($quoteImage)
                    <a href="{{ route('vendor-quotes.image', $quote) }}"><img src="{{ asset('storage/' . $quoteImage) }}"
                        alt="Product" class="master-image"></a>@else<span class="master-image">📦</span>
                @endif
                <div>
                    <h1>{{ $quote->quote_number }}</h1>
                    <p>{{ $quote->lead?->lead_number ?: 'Standalone Quote' }} ·
                        {{ $quote->product?->name ?? $quote->product_name ?: $quote->lead?->product_name ?: 'Product not specified' }}
                    </p>
                </div>
            </div>
            <div class="master-actions"><a href="{{ route('vendor-quotes.index') }}"
                    class="master-btn master-btn-light">Back</a><a href="{{ route('vendor-quotes.edit', $quote) }}"
                    class="master-btn master-btn-primary">Edit Quote</a>
                @if ($quote->lead)
                    <a href="{{ route('leads.show', $quote->lead) }}" class="master-btn master-btn-soft">View Lead</a>
                @endif
            </div>
        </div>
        <div class="master-grid">
            <div>
                <div class="master-card master-section">
                    <h3>Quote Overview</h3>
                    <div class="master-info-grid">
                        <div class="master-info"><span>Status</span><strong
                                    class="master-badge status-{{ $quote->status }}">{{ $quote->statusLabel() }}</strong>
                        </div>  
                        <div class="master-info">
                            <span>Mapped Product</span>
                            <strong>
                                @if ($quote->product)
                                <a href="{{ route('products.show', $quote->product) }}">{{ $quote->product?->name ?? ($quote->product_name ?? '-') }}</a>
                                @endif
                            </strong>
                        </div>
                        <div class="master-info">
                            <span>Vendor</span>
                            <strong>{{ $quote->vendor?->vendor_name ?? ($quote->vendor_name ?? '-') }}</strong>
                            <span class="master-sub" style="text-transform:capitalize;font-weight:500;">{{ $quote->vendor_email }}</span>
                            <span class="master-sub" style="font-weight:500;">{{ $quote->vendor_mobile }}</span>
                        </div>
                              
                        <!--<div class="master-info"><span>Incoterm / Lead-->
                        <!--        Time</span><strong>{{ $quote->incoterm ?: '-' }}</strong><span-->
                        <!--        class="master-sub">{{ $quote->lead_time_days ?: '-' }} days</span></div>-->
                        <!--<div class="master-info"><span>Quantity /-->
                        <!--        MOQ</span><strong>{{ $quote->quantity ? number_format($quote->quantity) . ' ' . $quote->unit : '-' }}</strong><span-->
                        <!--        class="master-sub">MOQ: {{ $quote->moq ?: '-' }}</span></div>-->
                        <!--<div class="master-info"><span>Vendor-->
                        <!--        Price</span><strong>{{ $quote->vendor_unit_price ? $quote->currency . ' ' . number_format((float) $quote->vendor_unit_price, 4) : '-' }}</strong>-->
                        <!--</div>-->
                        <div class="master-info"><span>Ready
                                Stock</span><strong>{{ $quote->ready_stock_available ? 'Available' : 'No' }}</strong><span
                                class="master-sub" style="text-transform:capitalize;font-weight:500;">{{ $quote->available_colors ?: '-' }}</span></div>
                        <!--<div class="master-info">-->
                        <!--    <span>Sample</span><strong>{{ $quote->sample_available ? 'Available' : 'No' }}</strong></div>-->
                    </div>
                </div>
                <div class="master-card master-section">
                    <h3>Price Breaks</h3>
                    <div class="master-wrap">
                        <table class="master-table">
                            <thead>
                                <tr>
                                    <th>Qty</th>
                                    <th>Finish</th>
                                    <th>Printing</th>
                                    <th>Vendor Price</th>
                                    <th>Landing INR</th>
                                    <th>Selling INR</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($quote->prices as $price)
                                    <tr>
                                        <td>{{ $price->quantity }} {{ $price->unit }}</td>
                                        <td>{{ $price->finish_type ?: '-' }}</td>
                                        <td>{{ $price->printing_type ?: '-' }}</td>
                                        <td>{{ $price->vendor_unit_price ? $quote->currency . ' ' . number_format((float) $price->vendor_unit_price, 4) : '-' }}
                                        </td>
                                        <td>{{ $price->landing_cost_inr ? '₹ ' . number_format((float) $price->landing_cost_inr, 2) : '-' }}
                                        </td>
                                        <td>{{ $price->selling_price_inr ? '₹ ' . number_format((float) $price->selling_price_inr, 2) : '-' }}
                                        </td>
                                        <td>{{ $price->remarks ?: '-' }}</td>
                                </tr>@empty<tr>
                                        <td colspan="7">No price breaks added.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="master-card master-section">
                    <h3>Technical Details</h3>
                    <div class="master-info-grid">
                        <div class="master-info"><span>Finish
                                Options</span><strong>{{ $quote->finish_options ?: '-' }}</strong></div>
                        <div class="master-info"><span>Printing
                                Options</span><strong>{{ $quote->printing_options ?: '-' }}</strong></div>
                        <div class="master-info"><span>Size
                                Details</span><strong>{{ $quote->size_details ?: '-' }}</strong></div>
                        <div class="master-info"><span>Weight
                                Details</span><strong>{{ $quote->weight_details ?: '-' }}</strong></div>
                        <div class="master-info">
                            <span>Packaging</span><strong>{{ $quote->packaging_details ?: '-' }}</strong></div>
                        <div class="master-info"><span>Photo /
                                Video</span><strong>{{ $quote->photo_video_notes ?: '-' }}</strong></div>
                    </div>
                </div>
            </div>
            <div>
                <div class="master-card master-section">
                    <h3>Manual INR Pricing</h3>
                    <div class="price">
                        {{ $quote->selling_price_inr ? '₹ ' . number_format((float) $quote->selling_price_inr, 2) : '-' }}
                    </div>
                    <p style="color:#687386;font-weight:800">Selling Price</p>
                    <div class="landing">Landing Cost:
                        {{ $quote->landing_cost_inr ? '₹ ' . number_format((float) $quote->landing_cost_inr, 2) : '-' }}</div>
                </div>
                <div class="master-card master-section">
                    <h3>Notes</h3>
                    <p style="color:#536079;font-weight:700;line-height:1.6">{{ $quote->notes ?: 'No notes added.' }}</p>
                </div>
            </div>
        </div>
    </div>
@endsection
