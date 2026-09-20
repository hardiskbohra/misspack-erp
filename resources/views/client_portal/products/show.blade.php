@extends('client_portal.layouts.app')

@section('title', $product->name)
@section('page-title', 'Product Detail')

@section('content')
<div class="cp-page-head"><div><p class="cp-eyebrow">{{ $product->product_number }}</p><h1>{{ $product->name }}</h1><p>{{ $product->sku ?: 'No SKU' }} · {{ $product->category ?: 'No category' }}</p></div><a href="{{ route('client-portal.products.index') }}" class="cp-btn cp-btn-light">Back</a></div>
<div class="cp-grid-2"><div class="cp-card" style="padding:20px;"><p class="cp-eyebrow">Gallery</p><h2 style="margin-top:0;">Product Media</h2><div class="cp-grid-2">@forelse($product->media->where('media_type','image') as $media)<img src="{{ asset('storage/'.$media->file_path) }}" style="width:100%;height:210px;object-fit:cover;border-radius:16px;border:1px solid #dfe7f3;">@empty<div class="cp-empty" style="grid-column:1/-1;">No image available.</div>@endforelse</div></div><div class="cp-card" style="padding:20px;"><p class="cp-eyebrow">Details</p><h2 style="margin-top:0;">Specifications</h2><p><strong>Capacity:</strong> {{ implode(', ', $product->ml_capacities ?? []) ?: '-' }}</p><p><strong>Finish:</strong><br>{{ $product->finish_details ?: '-' }}</p><p><strong>Printing:</strong><br>{{ $product->printing_details ?: '-' }}</p><p><strong>Material:</strong><br>{{ $product->material_details ?: '-' }}</p><p><strong>Packaging:</strong><br>{{ $product->packaging_details ?: '-' }}</p><p><strong>Description:</strong><br>{{ $product->description ?: '-' }}</p></div></div>
@if($product->show_price_ladder_public)
<div class="cp-card" style="padding:20px;margin-top:18px;"><p class="cp-eyebrow">Public Pricing</p><h2 style="margin-top:0;">Price Ladder</h2><div class="cp-table-wrap"><table class="cp-table"><thead><tr><th>Qty</th><th>Unit</th><th>Capacity</th><th>Selling Cost INR</th><th>Remarks</th></tr></thead><tbody>@forelse($product->priceLadders as $ladder)<tr><td>{{ $ladder->quantity }}</td><td>{{ $ladder->unit }}</td><td>{{ $ladder->capacity ?: '-' }}</td><td>₹{{ number_format((float)$ladder->selling_cost_inr, 2) }}</td><td>{{ $ladder->remarks ?: '-' }}</td></tr>@empty<tr><td colspan="5"><div class="cp-empty">No public pricing available.</div></td></tr>@endforelse</tbody></table></div></div>
@endif
@endsection
