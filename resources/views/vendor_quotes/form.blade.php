@extends('layouts.app')

@section('page-title', $quote->exists ? 'Edit Vendor Quote' : 'Add Vendor Quote')

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/vendors.css') }}">
@endpush
@php($isEdit = $quote->exists)
@php($productMedia = $quote->product?->primaryMedia())
@php($quoteImage = $quote->product_image_path ?: ($productMedia?->file_path ?: $quote->lead?->product_image_path))
@php($prices = old('prices') ?? $quote->prices->map(function($price){ return $price->only(['quantity','unit','finish_type','printing_type','vendor_unit_price','landing_cost_inr','selling_price_inr','moq','remarks']); })->toArray())
@if(empty($prices)) @php($prices=[['quantity'=>$quote->quantity,'unit'=>$quote->unit ?: 'pcs','finish_type'=>'','printing_type'=>'','vendor_unit_price'=>$quote->vendor_unit_price,'landing_cost_inr'=>$quote->landing_cost_inr,'selling_price_inr'=>$quote->selling_price_inr,'moq'=>$quote->moq,'remarks'=>'']]) @endif
    <div class="master-form vendor-quote-form">
        <div class="master-card master-header">
            <h1>{{ $isEdit ? 'Edit Vendor Quote' : 'Add Vendor Quote' }}</h1>
            <div class="master-breadcrumb"><a href="{{ url('/') }}">Home</a><span>•</span><a
                    href="{{ route('vendor-quotes.index') }}">Vendor Quotes</a><span>•</span><span
                    class="active">{{ $isEdit ? 'Edit' : 'Add' }}</span></div>
        </div>
        @if ($errors->any())
            <div class="master-alert">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ $isEdit ? route('vendor-quotes.update', $quote) : route('vendor-quotes.store') }}"
            enctype="multipart/form-data" class="master-card master-form-card">@csrf @if ($isEdit)
                @method('PUT')
            @endif
            <div class="master-section">
                <h3 class="master-section-title">Quote Context</h3>
                <div class="master-detail-grid">
                    <div><label class="master-label">Quote Number</label><input class="master-input" name="quote_number"
                            value="{{ old('quote_number', $quote->quote_number) }}" placeholder="Auto generated" readonly></div>
                    <div><label class="master-label">Related Lead</label><select class="master-select" name="lead_id">
                            <option value="">Standalone Quote</option>
                            @foreach ($leads as $leadOption)
                                <option value="{{ $leadOption->id }}" @selected((string) old('lead_id', $quote->lead_id) === (string) $leadOption->id)>
                                    {{ $leadOption->lead_number }} - {{ $leadOption->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div><label class="master-label">Product</label><select class="master-select" name="product_id">
                            <option value="">Manual / Lead Product</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" @selected((string) old('product_id', $quote->product_id) === (string) $product->id)>{{ $product->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div><label class="master-label">Status</label><select class="master-select" name="status">
                            @foreach ($statusOptions as $key => $label)
                                <option value="{{ $key }}" @selected(old('status', $quote->status) === $key)>{{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="master-section">
                <h3 class="master-section-title">Product Image</h3>
                <div class="master-detail-grid">
                    <div>
                        @if ($quoteImage)
                            <a href="{{ $isEdit ? route('vendor-quotes.image', $quote) : '#' }}"><img class="master-preview"
                                src="{{ asset('storage/' . $quoteImage) }}" alt="Product"></a>@else<span
                                class="master-preview">📦</span>
                        @endif
                    </div>
                    <div class="master-field two"><label class="master-label">Upload Product Image</label><input class="master-input"
                            type="file" name="product_image" accept="image/*">
                        <div style="font-size:12px;color:#687386;font-weight:500;margin-top:6px;">Optional. If blank, linked
                            lead product image can be used as reference.</div>
                    </div>
                    <div><label class="master-label">Product Name</label><input class="master-input" name="product_name"
                            value="{{ old('product_name', $quote->product_name ?: $quote->product?->name ?: $quote->lead?->product_name) }}"
                            placeholder="Auto-filled from selected product if blank"></div>
                </div>
                
            </div>
            <div class="master-section">
                <h3 class="master-section-title">Vendor & Product Details</h3>
                <div class="master-detail-grid">
                    <!--<div><label class="master-label">Vendor</label><select class="master-select" name="vendor_id">-->
                    <!--        <option value="">Manual vendor</option>-->
                    <!--        @foreach ($vendors as $vendor)-->
                    <!--            <option value="{{ $vendor->id }}" @selected((string) old('vendor_id', $quote->vendor_id) === (string) $vendor->id)>-->
                    <!--                {{ $vendor->vendor_name }}</option>-->
                    <!--        @endforeach-->
                    <!--    </select>-->
                    <!--</div>-->
                    <div><label class="master-label">Company Name</label><input class="master-input" name="vendor_name"
                            value="{{ old('vendor_name', $quote->vendor_name) }}"></div>
                    <div><label class="master-label">Contact Name</label><input class="master-input" name="vendor_contact_name"
                            value="{{ old('vendor_contact_name', $quote->vendor_contact_name) }}"></div>
                    <div><label class="master-label">Email</label><input class="master-input" type="email" name="vendor_email"
                            value="{{ old('vendor_email', $quote->vendor_email) }}"></div>
                    <div><label class="master-label">Mobile</label><input class="master-input" name="vendor_mobile"
                            value="{{ old('vendor_mobile', $quote->vendor_mobile) }}"></div>
                </div>
            </div>
            <!--<div class="master-section">-->
            <!--    <h3 class="master-section-title">Quick Pricing</h3>-->
            <!--    <div class="master-detail-grid">-->
            <!--        <div><label class="master-label">Currency</label><select class="master-select" name="currency">-->
            <!--                @foreach ($currencyOptions as $key => $label)-->
            <!--                    <option value="{{ $key }}" @selected(old('currency', $quote->currency) === $key)>{{ $label }}-->
            <!--                    </option>-->
            <!--                @endforeach-->
            <!--            </select>-->
            <!--        </div>-->
            <!--        <div><label class="master-label">Vendor Unit Price</label><input class="master-input" type="number"-->
            <!--                step="0.0001" name="vendor_unit_price"-->
            <!--                value="{{ old('vendor_unit_price', $quote->vendor_unit_price) }}"></div>-->
            <!--        <div><label class="master-label">Quantity</label><input class="master-input" type="number" name="quantity"-->
            <!--                value="{{ old('quantity', $quote->quantity) }}"></div>-->
            <!--        <div><label class="master-label">MOQ</label><input class="master-input" type="number" name="moq"-->
            <!--                value="{{ old('moq', $quote->moq) }}"></div>-->
            <!--        <div><label class="master-label">Lead Time Days</label><input class="master-input" type="number"-->
            <!--                name="lead_time_days" value="{{ old('lead_time_days', $quote->lead_time_days) }}"></div>-->
            <!--        <div><label class="master-label">Incoterm</label><select class="master-select" name="incoterm">-->
            <!--                <option value="">Select</option>-->
            <!--                @foreach ($incotermOptions as $key => $label)-->
            <!--                    <option value="{{ $key }}" @selected(old('incoterm', $quote->incoterm) === $key)>{{ $label }}-->
            <!--                    </option>-->
            <!--                @endforeach-->
            <!--            </select>-->
            <!--        </div>-->
            <!--        <div><label class="master-label">Landing Cost INR</label><input class="master-input" type="number"-->
            <!--                step="0.01" name="landing_cost_inr"-->
            <!--                value="{{ old('landing_cost_inr', $quote->landing_cost_inr) }}"></div>-->
            <!--        <div><label class="master-label">Selling Price INR</label><input class="master-input" type="number"-->
            <!--                step="0.01" name="selling_price_inr"-->
            <!--                value="{{ old('selling_price_inr', $quote->selling_price_inr) }}"></div>-->
            <!--    </div>-->
            <!--</div>-->
            <div class="master-section">
                <h3 class="master-section-title">Optional Quantity Price Breaks</h3>
                <div class="master-items">
                    <table class="master-table" id="quotePricesTable">
                        <thead>
                            <tr>
                                <th>Qty</th>
                                <th>Finish</th>
                                <th>Printing</th>
                                <th>Vendor Price</th>
                                <th>Landing INR</th>
                                <th>Selling INR</th>
                                <th>Remarks</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($prices as $i => $price)
                                <tr>
                                    <td><input class="master-input" type="number"
                                            name="prices[{{ $i }}][quantity]"
                                            value="{{ $price['quantity'] ?? '' }}"></td>
                                    <td><input class="master-input" name="prices[{{ $i }}][finish_type]"
                                            value="{{ $price['finish_type'] ?? '' }}"></td>
                                    <td><input class="master-input" name="prices[{{ $i }}][printing_type]"
                                            value="{{ $price['printing_type'] ?? '' }}"></td>
                                    <td><input class="master-input" type="number" step="0.0001"
                                            name="prices[{{ $i }}][vendor_unit_price]"
                                            value="{{ $price['vendor_unit_price'] ?? '' }}"></td>
                                    <td><input class="master-input" type="number" step="0.01"
                                            name="prices[{{ $i }}][landing_cost_inr]"
                                            value="{{ $price['landing_cost_inr'] ?? '' }}"></td>
                                    <td><input class="master-input" type="number" step="0.01"
                                            name="prices[{{ $i }}][selling_price_inr]"
                                            value="{{ $price['selling_price_inr'] ?? '' }}"></td>
                                    <td><input class="master-input" name="prices[{{ $i }}][remarks]"
                                            value="{{ $price['remarks'] ?? '' }}"></td>
                                    <td><button type="button" class="master-remove"
                                            onclick="removeQuotePriceRow(this)">×</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div><button type="button" class="master-btn master-btn-light" id="addQuotePriceRow"
                    style="margin-top:12px;">+ Add Price Break</button>
            </div>
            <div class="master-section">
                <h3 class="master-section-title">Stock & Technical Details</h3>
                <div class="master-detail-grid">
                    <div class="master-field"><label class="master-label">Sample Available</label>
                            
                    <div class="master-chip-group">

                        <label class="master-chip green-chip">
                            <input
                                type="radio"
                                name="sample_available"
                                value="1"
                                {{ old('sample_available', $quote->sample_available) == 1 ? 'checked' : '' }}
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
                                name="sample_available"
                                value="0"
                                {{ old('sample_available', $quote->sample_available) == 0 ? 'checked' : '' }}
                            >
                            <span>
                                <i class="fa-solid fa-xmark"></i>
                                No
                            </span>
                        </label>
                    
                    </div></div>
                            
                            
                    <div class="master-field"><label class="master-label">Ready Stock Available</label>
                            
                    <div class="master-chip-group">

                        <label class="master-chip green-chip">
                            <input
                                type="radio"
                                name="ready_stock_available"
                                value="1"
                                {{ old('ready_stock_available', $quote->ready_stock_available) == 1 ? 'checked' : '' }}
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
                                {{ old('ready_stock_available', $quote->ready_stock_available) == 0 ? 'checked' : '' }}
                            >
                            <span>
                                <i class="fa-solid fa-xmark"></i>
                                No
                            </span>
                        </label>
                    
                    </div></div>
                    
                    <div class="master-field"><label class="master-label">Available Colors</label>
                        <input class="master-input" name="available_colors" value="{{ old('available_colors', $quote->available_colors) }}">
                    </div>
                    <div class="master-field"><label class="master-label">Finish Options</label>
                        <input class="master-input" name="finish_options" value="{{ old('finish_options', $quote->finish_options) }}">
                    </div>
                    <div class="master-field"><label class="master-label">Printing Options</label>
                        <input class="master-input" name="printing_options" value="{{ old('printing_options', $quote->printing_options) }}">
                    </div>
                    <div class="master-field"><label class="master-label">Size / Weight / Packaging Details</label>
                        <input class="master-input" name="size_details" placeholder="Size details" value="{{ old('size_details', $quote->size_details) }}">
                    </div>
                    <div class="master-field two"><label class="master-label">Notes</label>
                        <input class="master-input" name="notes" value="{{ old('notes', $quote->notes) }}">
                    </div>
                </div>
            </div>
            <div class="master-actions"><a
                    href="{{ $isEdit ? route('vendor-quotes.show', $quote) : route('vendor-quotes.index') }}"
                    class="master-btn master-btn-light">Cancel</a><button class="master-btn master-btn-primary"
                    type="submit">{{ $isEdit ? 'Update Quote' : 'Create Quote' }}</button></div>
        </form>
    </div>
    <template id="quotePriceRowTemplate">
        <tr>
            <td><input class="master-input" type="number" name="prices[__INDEX__][quantity]"></td>
            <td><input class="master-input" name="prices[__INDEX__][finish_type]"></td>
            <td><input class="master-input" name="prices[__INDEX__][printing_type]"></td>
            <td><input class="master-input" type="number" step="0.0001" name="prices[__INDEX__][vendor_unit_price]"></td>
            <td><input class="master-input" type="number" step="0.01" name="prices[__INDEX__][landing_cost_inr]"></td>
            <td><input class="master-input" type="number" step="0.01" name="prices[__INDEX__][selling_price_inr]"></td>
            <td><input class="master-input" name="prices[__INDEX__][remarks]"></td>
            <td><button type="button" class="master-remove" onclick="removeQuotePriceRow(this)">×</button></td>
        </tr>
    </template>
@push('scripts')
    <script src="{{ asset('assets/js/vendors.js') }}"></script>
@endpush
@endsection
