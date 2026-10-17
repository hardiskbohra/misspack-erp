@extends('layouts.app')
@section('title', $list->exists ? 'Edit '.$list->packing_number : 'New Packaging List')
@section('page-title', $list->exists ? 'Edit Packaging List' : 'New Packaging List')
@push('styles')<link rel="stylesheet" href="{{ $assetVer('assets/css/packaging-lists.css') }}">@endpush
@section('content')
@php
    $cartonRows = old('cartons');
    if ($cartonRows === null) {
        $cartonRows = $list->relationLoaded('cartons') ? $list->cartons->map(fn($carton) => [
            'carton_label'=>$carton->carton_label, 'packing_mode'=>$carton->packing_mode, 'carton_number'=>$carton->carton_number,
            'carton_type'=>$carton->carton_type, 'shipping_mark'=>$carton->shipping_mark, 'material'=>$carton->material,
            'pallet_number'=>$carton->pallet_number, 'seal_number'=>$carton->seal_number, 'carton_count'=>$carton->carton_count,
            'length_cm'=>$carton->length_cm, 'width_cm'=>$carton->width_cm, 'height_cm'=>$carton->height_cm,
            'net_weight_per_carton'=>$carton->net_weight_per_carton, 'gross_weight_per_carton'=>$carton->gross_weight_per_carton,
            'tare_weight'=>$carton->tare_weight, 'is_fragile'=>$carton->is_fragile, 'keep_dry'=>$carton->keep_dry,
            'this_side_up'=>$carton->this_side_up, 'is_stackable'=>$carton->is_stackable, 'is_hazardous'=>$carton->is_hazardous,
            'handling_instructions'=>$carton->handling_instructions,
            'items'=>$carton->items->map(fn($item)=>array_merge($item->only(['product_id','source_type','source_item_id','source_quantity','previously_packed_quantity','item_description','sku','customer_item_code','hs_code','batch_number','manufactured_at','expires_at','country_of_origin','unit_net_weight','unit_gross_weight','packing_instructions','remarks','quantity_per_carton','total_quantity','unit']),['serial_numbers_text'=>implode("\n",$item->serial_numbers ?? [])]))->all(),
        ])->all() : ($prefillCartons ?? []);
    }
    if (!$cartonRows) $cartonRows = [['carton_label'=>'Carton 1','packing_mode'=>'grouped','carton_number'=>'1','carton_type'=>'box','carton_count'=>1,'length_cm'=>'','width_cm'=>'','height_cm'=>'','net_weight_per_carton'=>'','gross_weight_per_carton'=>'','tare_weight'=>0,'is_stackable'=>1,'items'=>[['product_id'=>'','item_description'=>'','quantity_per_carton'=>'','total_quantity'=>'','unit'=>'pcs']]]];
@endphp
<div class="master-form packaging-list-form" data-packaging-form>
    <header class="master-card master-card--flat master-header">
        <h1>{{ $list->exists ? 'Edit Packaging List' : 'New Packaging List' }}</h1>
        <div class="master-breadcrumb"><a href="{{ url('/') }}">Home</a><span>•</span><a href="{{ route('packaging-lists.index') }}">Packaging Lists</a><span>•</span><span class="active">{{ $list->exists ? $list->packing_number : 'New' }}</span></div>
    </header>
    @if($errors->any())<div class="master-alert error"><strong>Please correct the highlighted information.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ $list->exists ? route('packaging-lists.update',$list) : route('packaging-lists.store') }}" class="master-card master-card--flat master-form-card">
        @csrf @if($list->exists) @method('PUT') @endif
        <input type="hidden" name="source_type" value="{{ old('source_type',$list->source_type) }}">
        <input type="hidden" name="source_id" value="{{ old('source_id',$list->source_id) }}">
        <input type="hidden" name="purchase_invoice_id" value="{{ old('purchase_invoice_id',$list->purchase_invoice_id) }}">
        @if($list->source_type)
            <div class="pl-source-strip"><span><i class="fa-solid fa-wand-magic-sparkles"></i> Generated from {{ $list->sourceLabel() }}</span><strong>{{ $list->invoice_number }}</strong><small>Quantities below show the original, previously packed and remaining balance.</small></div>
        @endif
        <div class="master-section">
            <h2 class="master-section-title">Document details</h2>
            <div class="master-detail-grid is-three-up">
                <div class="master-field"><label class="master-label">PL number <span class="master-required">*</span></label><input class="master-input" name="packing_number" value="{{ old('packing_number',$list->packing_number) }}" required></div>
                <div class="master-field"><label class="master-label">Date <span class="master-required">*</span></label><input class="master-input" type="date" name="packing_date" value="{{ old('packing_date',optional($list->packing_date)->format('Y-m-d')) }}" required></div>
                <div class="master-field"><label class="master-label">Invoice number</label><input class="master-input" name="invoice_number" value="{{ old('invoice_number',$list->invoice_number) }}"></div>
                <div class="master-field"><label class="master-label">Transport mode <span class="master-required">*</span></label><select class="master-select" name="transport_mode">@foreach($modeOptions as $key=>$label)<option value="{{ $key }}" @selected(old('transport_mode',$list->transport_mode)===$key)>{{ $label }}</option>@endforeach</select></div>
                <div class="master-field"><label class="master-label">Status <span class="master-required">*</span></label><select class="master-select" name="status">@foreach($statusOptions as $key=>$label)<option value="{{ $key }}" @selected(old('status',$list->status)===$key)>{{ $label }}</option>@endforeach</select></div>
                <div class="master-field"><label class="master-label">Shipment</label><select class="master-select" name="shipment_id" data-pl-shipment><option value="">No shipment</option>@foreach($shipments as $shipment)<option value="{{ $shipment->id }}" data-client="{{ $shipment->client_id }}" data-vendor="{{ $shipment->vendor_id }}" data-project="{{ $shipment->project_id }}" data-invoice="{{ $shipment->sales_invoice_id }}" data-mode="{{ $shipment->shipment_mode }}" data-from-name="{{ $shipment->from_name }}" data-from-address="{{ collect([$shipment->from_address,$shipment->from_city,$shipment->from_state,$shipment->from_pincode,$shipment->from_country])->filter()->implode(', ') }}" data-ship-name="{{ $shipment->to_name }}" data-ship-address="{{ collect([$shipment->to_address,$shipment->to_city,$shipment->to_state,$shipment->to_pincode,$shipment->to_country])->filter()->implode(', ') }}" @selected((string)old('shipment_id',$list->shipment_id)===(string)$shipment->id)>{{ $shipment->shipment_number }} · {{ $shipment->identity_name }}</option>@endforeach</select></div>
            </div>
        </div>

        <div class="master-section">
            <h2 class="master-section-title">Links</h2>
            <div class="master-detail-grid">
                <div class="master-field"><label class="master-label">Vendor / sender</label><select class="master-select" name="vendor_id" data-pl-vendor><option value="">No vendor</option>@foreach($vendors as $vendor)<option value="{{ $vendor->id }}" data-name="{{ $vendor->vendor_name }}" data-address="{{ collect([$vendor->address,$vendor->city,$vendor->state,$vendor->pincode,$vendor->country])->filter()->implode(', ') }}" data-contact="{{ collect([$vendor->contact_person_mobile,$vendor->whatsapp_number])->filter()->implode(', ') }}" data-email="{{ $vendor->contact_person_email }}" @selected((string)old('vendor_id',$list->vendor_id)===(string)$vendor->id)>{{ $vendor->vendor_number }} · {{ $vendor->vendor_name }}</option>@endforeach</select></div>
                <div class="master-field"><label class="master-label">Client</label><select class="master-select" name="client_id" data-pl-client><option value="">No client</option>@foreach($clients as $client)<option value="{{ $client->id }}" data-name="{{ $client->company_name }}" data-address="{{ collect([$client->shipping_address,$client->shipping_city,$client->shipping_state,$client->shipping_pincode,$client->shipping_country])->filter()->implode(', ') }}" @selected((string)old('client_id',$list->client_id)===(string)$client->id)>{{ $client->client_number }} · {{ $client->company_name }}</option>@endforeach</select></div>
                <div class="master-field"><label class="master-label">Project</label><select class="master-select" name="project_id"><option value="">No project</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected((string)old('project_id',$list->project_id)===(string)$project->id)>{{ $project->project_number }} · {{ $project->name }}</option>@endforeach</select></div>
                <div class="master-field"><label class="master-label">Sales invoice / proforma</label><select class="master-select" name="sales_invoice_id"><option value="">No sales document</option>@foreach($salesInvoices as $invoice)<option value="{{ $invoice->id }}" @selected((string)old('sales_invoice_id',$list->sales_invoice_id)===(string)$invoice->id)>{{ $invoice->invoice_number }}</option>@endforeach</select></div>
            </div>
        </div>

        <div class="master-section">
            <h2 class="master-section-title">Addresses</h2>
            <div class="pl-address-grid">
                <div class="pl-address-card"><h3>From</h3><div class="master-field"><label class="master-label">Name <span class="master-required">*</span></label><input class="master-input" name="from_name" value="{{ old('from_name',$list->from_name) }}" required></div><div class="master-field"><label class="master-label">Address</label><textarea class="master-textarea" name="from_address" rows="4">{{ old('from_address',$list->from_address) }}</textarea></div><div class="master-detail-grid"><div class="master-field"><label class="master-label">Contact</label><input class="master-input" name="from_contact" value="{{ old('from_contact',$list->from_contact) }}"></div><div class="master-field"><label class="master-label">Email</label><input class="master-input" type="email" name="from_email" value="{{ old('from_email',$list->from_email) }}"></div></div></div>
                <div class="pl-address-card"><h3>Bill to</h3><div class="master-field"><label class="master-label">Name <span class="master-required">*</span></label><input class="master-input" name="bill_to_name" value="{{ old('bill_to_name',$list->bill_to_name) }}" required></div><div class="master-field"><label class="master-label">Address</label><textarea class="master-textarea" name="bill_to_address" rows="4">{{ old('bill_to_address',$list->bill_to_address) }}</textarea></div></div>
                <div class="pl-address-card"><h3>Ship to</h3><div class="master-field"><label class="master-label">Name <span class="master-required">*</span></label><input class="master-input" name="ship_to_name" value="{{ old('ship_to_name',$list->ship_to_name) }}" required></div><div class="master-field"><label class="master-label">Address</label><textarea class="master-textarea" name="ship_to_address" rows="4">{{ old('ship_to_address',$list->ship_to_address) }}</textarea></div></div>
            </div>
        </div>

        <div class="master-section pl-cartons-section">
            <div class="master-section-head"><div><h2 class="master-section-title">Cartons and items</h2><p class="master-sub">Each carton group can contain multiple product lines sharing the same size and weight.</p></div><button type="button" class="master-btn master-btn-soft master-btn-sm" data-add-carton><i class="fa-solid fa-plus"></i> Add carton group</button></div>
            <div data-carton-list>
                @foreach($cartonRows as $cartonIndex=>$carton)
                    @include('packaging_lists.partials.carton-editor',['cartonIndex'=>$cartonIndex,'carton'=>$carton,'products'=>$products])
                @endforeach
            </div>
            <div class="pl-live-totals"><div><span>Total cartons</span><strong data-total-cartons>0</strong></div><div><span>Net weight</span><strong data-total-net>0.000 kg</strong></div><div><span>Gross weight</span><strong data-total-gross>0.000 kg</strong></div><div><span>Total CBM</span><strong data-total-cbm>0.0000</strong></div></div>
        </div>

        <div class="master-section">
            <h2 class="master-section-title">Certification</h2>
            <div class="master-field"><label class="master-label">Declaration</label><textarea class="master-textarea" name="declaration" rows="3">{{ old('declaration',$list->declaration) }}</textarea></div>
            <div class="master-detail-grid"><div class="master-field"><label class="master-label">Customer signature name</label><input class="master-input" name="customer_signature" value="{{ old('customer_signature',$list->customer_signature) }}"></div><div class="master-field"><label class="master-label">Supplier signature name</label><input class="master-input" name="supplier_signature" value="{{ old('supplier_signature',$list->supplier_signature) }}"></div></div>
            <div class="master-field"><label class="master-label">Internal notes</label><textarea class="master-textarea" name="notes" rows="3">{{ old('notes',$list->notes) }}</textarea></div>
        </div>
        <div class="master-form-actions"><a class="master-btn master-btn-light" href="{{ route('packaging-lists.index') }}">Cancel</a><button class="master-btn master-btn-primary" type="submit"><i class="fa-solid fa-check"></i> Save packaging list</button></div>
    </form>
</div>

<template id="plCartonTemplate">@include('packaging_lists.partials.carton-editor',['cartonIndex'=>'__CARTON__','carton'=>['carton_label'=>'','carton_count'=>1,'length_cm'=>'','width_cm'=>'','height_cm'=>'','net_weight_per_carton'=>'','gross_weight_per_carton'=>'','items'=>[['product_id'=>'','item_description'=>'','quantity_per_carton'=>'','total_quantity'=>'','unit'=>'pcs']]],'products'=>$products])</template>
<template id="plItemTemplate">@include('packaging_lists.partials.item-editor',['cartonIndex'=>'__CARTON__','itemIndex'=>'__ITEM__','item'=>['product_id'=>'','item_description'=>'','quantity_per_carton'=>'','total_quantity'=>'','unit'=>'pcs'],'products'=>$products])</template>
@endsection
@push('scripts')<script src="{{ $assetVer('assets/js/packaging-lists.js') }}"></script>@endpush
