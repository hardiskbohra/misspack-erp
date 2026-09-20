@extends('layouts.app')

@section('page-title', $product->exists ? 'Edit Product' : 'Add Product')

@section('content')
    @push('styles')
        <link rel="stylesheet" href="{{ asset('assets/css/products.css') }}">
    @endpush

@php($isEdit=$product->exists)
@php($ladders=old('price_ladders') ?? $product->priceLadders->map(function($row){return $row->only(['quantity','unit','capacity','finish_type','printing_type','landing_cost_inr','selling_cost_inr','remarks']);})->toArray())
@if(empty($ladders)) @php($ladders=[['quantity'=>'','unit'=>'pcs','capacity'=>'','finish_type'=>'','printing_type'=>'','landing_cost_inr'=>'','selling_cost_inr'=>'','remarks'=>'']]) @endif
<div class="master">
    <div class="master-card master-header">
        <h1>{{ $isEdit ? 'Edit Product' : 'Add Product' }}</h1>
        <div class="master-breadcrumb"><a href="{{ url('/') }}">Home</a><span>•</span><a
                href="{{ route('products.index') }}">Products</a><span>•</span><span
                class="active">{{ $isEdit ? 'Edit' : 'Add' }}</span></div>
    </div>
    @if ($errors->any())
        <div class="master-alert">{{ $errors->first() }}</div>
    @endif
    <form method="POST" action="{{ $isEdit ? route('products.update', $product) : route('products.store') }}"
        enctype="multipart/form-data" class="master-card master-form-card">@csrf @if ($isEdit)
            @method('PUT')
        @endif
        <div class="master-section">
            <h3 class="master-section-title">Basic Details</h3>
            <div class="master-detail-grid">
                <div><label class="master-label">Product Number</label><input class="master-input" name="product_number"
                        value="{{ old('product_number', $product->product_number) }}" placeholder="Auto generated"></div>
                <div><label class="master-label">Name *</label><input class="master-input" name="name"
                        value="{{ old('name', $product->name) }}" required></div>
                <div><label class="master-label">Category</label><input class="master-input" name="category"
                        value="{{ old('category', $product->category) }}"></div>
                <div><label class="master-label">ML Capacities</label><input class="master-input"
                        name="ml_capacities_text"
                        value="{{ old('ml_capacities_text', implode(',', $product->ml_capacities ?? [])) }}"
                        placeholder="50,100,200"></div>
                        
                <div class="master-form-group">
                    <label class="master-label">Ready Stock?</label>
                    <div class="master-chip-group">
                        <label class="master-chip green-chip">
                            <input
                                type="radio"
                                name="ready_stock_available"
                                value="1"
                                {{ old('ready_stock_available', $product->ready_stock_available) == 1 ? 'checked' : '' }}
                                required
                            >
                            <span>
                                <i class="fa-solid fa-check"></i>
                                Yes
                            </span>
                        </label>
                
                        <label class="master-chip red-chip">
                            <input
                                type="radio"
                                name="ready_stock_available"
                                value="0"
                                {{ old('ready_stock_available', $product->ready_stock_available) == 0 ? 'checked' : '' }}
                                required
                            >
                            <span>
                                <i class="fa-solid fa-times"></i>
                                No
                            </span>
                        </label>
                    </div>
                </div> 
                
                <div class="master-form-group">
                    <label class="master-label">Show Price Ladder Publicly</label>
                    <div class="master-chip-group">
                        <label class="master-chip green-chip">
                            <input
                                type="radio"
                                name="show_price_ladder_public"
                                value="1"
                                {{ old('show_price_ladder_public', $product->show_price_ladder_public) == 1 ? 'checked' : '' }}
                                required
                            >
                            <span>
                                <i class="fa-solid fa-check"></i>
                                Yes
                            </span>
                        </label>
                
                        <label class="master-chip red-chip">
                            <input
                                type="radio"
                                name="show_price_ladder_public"
                                value="0"
                                {{ old('show_price_ladder_public', $product->show_price_ladder_public) == 0 ? 'checked' : '' }}
                                required
                            >
                            <span>
                                <i class="fa-solid fa-times"></i>
                                No
                            </span>
                        </label>
                    </div>
                </div>  
            </div>
        </div>
        <div class="master-section">
            <h3 class="master-section-title">Product Specifications</h3>
            <div class="master-detail-grid">
                <div><label class="master-label">Ready Stock MOQ</label><input class="master-input" type="number"
                        name="ready_stock_moq" value="{{ old('ready_stock_moq', $product->ready_stock_moq) }}"></div>
                <div><label class="master-label">Customisation MOQ</label><input class="master-input" type="number"
                        name="customisation_moq" value="{{ old('customisation_moq', $product->customisation_moq) }}">
                </div>
                <div class="master-field two"><label class="master-label">Available Stock Colors</label>
                    <input class="master-input" name="available_stock_colors" value="{{ old('available_stock_colors', $product->available_stock_colors) }}">
                </div>
                <div class="master-field two"><label class="master-label">Finish Details</label>
                    <input class="master-input" name="finish_details" value="{{ old('finish_details', $product->finish_details) }}">
                </div>
                <div class="master-field two"><label class="master-label">Printing Details</label>
                    <input class="master-input" name="printing_details" value="{{ old('printing_details', $product->printing_details) }}">
                </div>
                <div class="master-field two"><label class="master-label">Material Details</label>
                    <input class="master-input" name="material_details" value="{{ old('material_details', $product->material_details) }}">
                </div>
                <div class="master-field two"><label class="master-label">Size Measurements</label>
                    <input class="master-input" name="size_measurements" value="{{ old('size_measurements', $product->size_measurements) }}">
                </div>
                <div class="master-field two"><label class="master-label">Weight Measurements</label>
                    <input class="master-input" name="weight_measurements" value="{{ old('weight_measurements', $product->weight_measurements) }}">
                </div>
                <div class="master-field two"><label class="master-label">Description</label>
                    <input class="master-input" name="description" value="{{ old('description', $product->description) }}">
                </div>
            </div>
        </div>
        <div class="master-section">
            <h3 class="master-section-title">Media</h3>
            <div class="master-detail-grid">
                <div class="master-field two"><label class="master-label">Upload Images / Videos /
                        Documents</label><input class="master-input" type="file" name="media_files[]" multiple></div>
                <div class="master-field two"><label class="master-label">External Media Links</label>
                    <textarea class="master-textarea" name="media_links" placeholder="One URL per line"></textarea>
                </div>
            </div>
            
            @if($isEdit && $product->media->count())
                <div class="master-existing-media">
                    @foreach($product->media as $attachment)
                        <div class="master-media-card">
                            @if(Str::startsWith($attachment->mime_type, 'image/'))
                                <img src="{{ asset('storage/'.$attachment->file_path) }}"
                                     class="master-media-thumb">
                            @else
                                <div class="master-media-file">
                                    <i class="fa-solid fa-file"></i>
                                </div>
                            @endif
            
                            <div class="master-media-info">
                                <small>{{ $attachment->original_name }}</small>
                            </div>
            
                            <label class="master-remove-media">
                                <input type="checkbox"
                                       name="remove_attachments[]"
                                       value="{{ $attachment->id }}">
                                Remove
                            </label>
                        </div>
                    @endforeach
                </div>
            @endif
            
        </div>
        <div class="master-section">
            <h3 class="master-section-title">Quantity Price Ladder</h3>
            <div class="master-items">
                <table class="master-table" id="priceLadderTable">
                    <thead>
                        <tr>
                            <th>Qty</th>
                            <th>Unit</th>
                            <th>Capacity</th>
                            <th>Finish</th>
                            <th>Printing</th>
                            <th>Landing Cost</th>
                            <th>Selling Cost</th>
                            <th>Remarks</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ladders as $i => $row)
                            <tr>
                                <td><input class="master-input" type="number"
                                        name="price_ladders[{{ $i }}][quantity]"
                                        value="{{ $row['quantity'] ?? '' }}"></td>
                                <td><input class="master-input" name="price_ladders[{{ $i }}][unit]"
                                        value="{{ $row['unit'] ?? 'pcs' }}"></td>
                                <td><input class="master-input" name="price_ladders[{{ $i }}][capacity]"
                                        value="{{ $row['capacity'] ?? '' }}"></td>
                                <td><input class="master-input"
                                        name="price_ladders[{{ $i }}][finish_type]"
                                        value="{{ $row['finish_type'] ?? '' }}"></td>
                                <td><input class="master-input"
                                        name="price_ladders[{{ $i }}][printing_type]"
                                        value="{{ $row['printing_type'] ?? '' }}"></td>
                                <td><input class="master-input" type="number" step="0.01"
                                        name="price_ladders[{{ $i }}][landing_cost_inr]"
                                        value="{{ $row['landing_cost_inr'] ?? '' }}"></td>
                                <td><input class="master-input" type="number" step="0.01"
                                        name="price_ladders[{{ $i }}][selling_cost_inr]"
                                        value="{{ $row['selling_cost_inr'] ?? '' }}"></td>
                                <td><input class="master-input" name="price_ladders[{{ $i }}][remarks]"
                                        value="{{ $row['remarks'] ?? '' }}"></td>
                                <td><button type="button" class="master-btn master-remove-row"
                                        onclick="removeLadderRow(this)">×</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div><button type="button" class="master-btn master-btn-light" id="addLadderRow"
                style="margin-top:12px;">+ Add Price Row</button>
        </div>
        <div class="master-actions"><a
                href="{{ $isEdit ? route('products.show', $product) : route('products.index') }}"
                class="master-btn master-btn-light">Cancel</a><button class="master-btn master-btn-primary"
                type="submit">{{ $isEdit ? 'Update Product' : 'Create Product' }}</button></div>
    </form>
</div>
<template id="ladderTemplate">
    <tr>
        <td><input class="master-input" type="number" name="price_ladders[__INDEX__][quantity]"></td>
        <td><input class="master-input" name="price_ladders[__INDEX__][unit]" value="pcs"></td>
        <td><input class="master-input" name="price_ladders[__INDEX__][capacity]"></td>
        <td><input class="master-input" name="price_ladders[__INDEX__][finish_type]"></td>
        <td><input class="master-input" name="price_ladders[__INDEX__][printing_type]"></td>
        <td><input class="master-input" type="number" step="0.01"
                name="price_ladders[__INDEX__][landing_cost_inr]"></td>
        <td><input class="master-input" type="number" step="0.01"
                name="price_ladders[__INDEX__][selling_cost_inr]"></td>
        <td><input class="master-input" name="price_ladders[__INDEX__][remarks]"></td>
        <td><button type="button" class="master-btn master-remove-row" onclick="removeLadderRow(this)">×</button></td>
    </tr>
</template>
@push('scripts')
    <script src="{{ asset('assets/js/products.js') }}"></script>
    <script>
        let ladderIndex = {{ count($ladders) }};
        document.getElementById('addLadderRow')?.addEventListener('click', function() {
            document.querySelector('#priceLadderTable tbody').insertAdjacentHTML('beforeend', document
                .getElementById('ladderTemplate').innerHTML.replaceAll('__INDEX__', ladderIndex++));
        });
    </script>
@endpush
@endsection
