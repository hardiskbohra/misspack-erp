@extends('layouts.app')
@section('title', $list->packing_number)
@section('page-title', 'Packaging List')
@section('page-actions')
<form method="POST" action="{{ route('packaging-lists.duplicate',$list) }}">@csrf<button class="master-btn master-btn-soft"><i class="fa-solid fa-copy"></i> Duplicate</button></form>
<form method="POST" action="{{ route('packaging-lists.destroy',$list) }}" onsubmit="return confirm('Delete this packaging list?');">@csrf @method('DELETE')<button class="master-btn master-btn-danger"><i class="fa-solid fa-trash"></i> Delete</button></form>
@endsection
@push('styles')<link rel="stylesheet" href="{{ $assetVer('assets/css/packaging-lists.css') }}">@endpush
@section('content')
<div class="packaging-list-show">
    <header class="master-card master-card--flat master-header"><div><p class="master-eyebrow">Packaging list</p><h1>{{ $list->packing_number }}</h1><div class="record-head-chips"><span class="master-badge pl-status-{{ $list->status }}">{{ $list->statusLabel() }}</span><span class="master-chip"><i class="fa-solid fa-plane"></i> {{ $list->modeLabel() }}</span><span class="master-chip">{{ $list->packing_date?->format('d M Y') }}</span></div></div><nav class="record-head-actions" aria-label="Packaging list actions"><a class="master-btn master-btn-light" href="{{ route('packaging-lists.index') }}"><i class="fa-solid fa-arrow-left"></i> Lists</a><a class="master-btn master-btn-soft" target="_blank" href="{{ route('packaging-lists.print',$list) }}"><i class="fa-solid fa-print"></i> Print</a><a class="master-btn master-btn-primary" href="{{ route('packaging-lists.edit',$list) }}"><i class="fa-solid fa-pen"></i> Edit</a></nav></header>
    <div class="master-stats">
        <div class="master-stat master-stat--flat blue"><span class="icon"><i class="fa-solid fa-boxes-stacked"></i></span><div><p class="master-stat-title">Cartons</p><p class="master-stat-value">{{ number_format($list->total_cartons) }}</p></div></div>
        <div class="master-stat master-stat--flat green"><span class="icon"><i class="fa-solid fa-weight-hanging"></i></span><div><p class="master-stat-title">Net weight</p><p class="master-stat-value">{{ number_format((float)$list->total_net_weight,1) }} kg</p></div></div>
        <div class="master-stat master-stat--flat orange"><span class="icon"><i class="fa-solid fa-weight-scale"></i></span><div><p class="master-stat-title">Gross weight</p><p class="master-stat-value">{{ number_format((float)$list->total_gross_weight,1) }} kg</p></div></div>
        <div class="master-stat master-stat--flat purple"><span class="icon"><i class="fa-solid fa-cube"></i></span><div><p class="master-stat-title">Total CBM</p><p class="master-stat-value">{{ number_format((float)$list->total_cbm,4) }}</p></div></div>
        <div class="master-stat master-stat--flat teal"><span class="icon"><i class="fa-solid fa-plane-departure"></i></span><div><p class="master-stat-title">Chargeable weight</p><p class="master-stat-value">{{ number_format($list->totalChargeableWeight(),1) }} kg</p></div></div>
    </div>
    <div class="master-grid is-even">
        <section class="master-card master-card--flat master-section"><h2 class="master-section-title">Document links</h2><div class="master-facts"><div class="master-info"><span>Source</span><strong>@if($list->source_type==='sales_invoice' && $list->salesInvoice)<a href="{{ route('sales-invoices.show',$list->salesInvoice) }}">{{ $list->sourceLabel() }}</a>@elseif($list->source_type==='purchase_invoice' && $list->purchaseInvoice)<a href="{{ route('purchase-invoices.show',$list->purchaseInvoice) }}">{{ $list->sourceLabel() }}</a>@elseif($list->source_type==='shipment' && $list->shipment)<a href="{{ route('shipments.show',$list->shipment) }}">{{ $list->sourceLabel() }}</a>@else{{ $list->sourceLabel() }}@endif{{ $list->auto_generated ? ' · Automated draft' : '' }}</strong></div><div class="master-info"><span>Invoice</span><strong>{{ $list->invoice_number ?: 'Not linked' }}</strong></div><div class="master-info"><span>Shipment</span><strong>@if($list->shipment)<a href="{{ route('shipments.show',$list->shipment) }}">{{ $list->shipment->shipment_number }}</a>@else — @endif</strong></div><div class="master-info"><span>Project</span><strong>@if($list->project)<a href="{{ route('projects.show',$list->project) }}">{{ $list->project->project_number }}</a>@else — @endif</strong></div><div class="master-info"><span>Created by</span><strong>{{ $list->creator?->name ?: '—' }}</strong></div></div></section>
        <section class="master-card master-card--flat master-section"><h2 class="master-section-title">Parties</h2><div class="master-facts"><div class="master-info"><span>From</span><strong>{{ $list->from_name }}</strong></div><div class="master-info"><span>Bill to</span><strong>{{ $list->bill_to_name }}</strong></div><div class="master-info"><span>Ship to</span><strong>{{ $list->ship_to_name }}</strong></div></div></section>
    </div>
    <section class="master-card master-card--flat master-table-card pl-breakdown">
        <div class="master-section-head"><div><h2 class="master-section-title">Packing breakdown</h2><p class="master-sub">{{ $productGroups->count() }} {{ \Illuminate\Support\Str::plural('product', $productGroups->count()) }} across {{ $list->cartons->count() }} carton {{ \Illuminate\Support\Str::plural('group',$list->cartons->count()) }} — a product packed in several carton sizes is listed once and added up.</p></div></div>
        <div class="master-table-wrap"><table class="master-table">
            <thead><tr><th scope="col">Product</th><th scope="col" class="is-num">Cartons</th><th scope="col" class="is-num">Qty/carton</th><th scope="col" class="is-num">Total qty</th><th scope="col">Size</th><th scope="col" class="is-num">Net weight</th><th scope="col" class="is-num">Gross weight</th><th scope="col" class="is-num">Chargeable</th><th scope="col" class="is-num">Total CBM</th></tr></thead>
            <tbody>@php($anchored = [])
            @foreach($productGroups as $group)
                <tr class="pl-product-group-row">
                    <th scope="rowgroup">Group({{ $group['label'] }})<span>{{ count($group['rows']) }} carton {{ \Illuminate\Support\Str::plural('size', count($group['rows'])) }}@if ($group['sku']) · {{ $group['sku'] }}@endif · group total</span></th>
                    <td class="is-num">{{ number_format($group['cartons']) }}</td>
                    <td></td>
                    <td class="is-num">{{ number_format($group['quantity'],0) }} {{ $group['unit'] }}</td>
                    <td></td>
                    <td class="is-num">{{ number_format($group['net'],1) }} kg</td>
                    <td class="is-num">{{ number_format($group['gross'],1) }} kg</td>
                    <td class="is-num">{{ number_format($group['chargeable'],1) }} kg</td>
                    <td class="is-num">{{ number_format($group['cbm'],4) }}</td>
                </tr>
                @foreach($group['rows'] as $row)
                    @php($anchor = ! isset($anchored[$row['carton']->id]))
                    <tr class="pl-carton-item-row" @if($anchor) id="carton-{{ $row['carton']->id }}" @endif>
                        <td data-label="Carton">
                            <strong>Carton {{ $row['carton']->carton_number ?: '—' }}</strong>
                            @if($row['carton']->carton_label && strcasecmp(trim($row['carton']->carton_label), 'Carton '.($row['carton']->carton_number ?: '—')) !== 0)
                                <span class="master-sub">{{ $row['carton']->carton_label }}</span>
                            @endif
                            @if($row['shared'])
                                <span class="master-chip pl-shared-carton-chip">Shared carton</span>
                            @endif
                        </td>
                        <td data-label="Cartons" class="is-num">{{ number_format($row['cartons']) }}</td>
                        <td data-label="Qty/carton" class="is-num">{{ number_format($row['quantity_per_carton'],0) }} {{ $group['unit'] }}</td>
                        <td data-label="Total qty" class="is-num"><strong>{{ number_format($row['quantity'],0) }} {{ $group['unit'] }}</strong></td>
                        <td data-label="Size">{{ $row['carton']->dimensionsLabel() }}</td>
                        <td data-label="Net weight" class="is-num">{{ $row['shared'] ? 'Shared' : number_format($row['net'],1).' kg' }}</td>
                        <td data-label="Gross weight" class="is-num">{{ $row['shared'] ? 'Shared' : number_format($row['gross'],1).' kg' }}</td>
                        <td data-label="Chargeable" class="is-num">{{ $row['shared'] ? 'Shared' : number_format($row['carton']->totalChargeableWeight(),1).' kg' }}</td>
                        <td data-label="Total CBM" class="is-num">
                            @if ($row['shared'])
                                Shared
                            @else
                                <span>{{ number_format($row['cbm_per_carton'],4) }}/carton</span>
                                <strong>{{ number_format($row['cbm'],4) }} total</strong>
                            @endif
                        </td>
                    </tr>
                    @php($anchored[$row['carton']->id] = true)
                @endforeach
            @endforeach
            </tbody>
            <tfoot><tr><td><strong>Total</strong><span class="master-sub">{{ $list->cartons->count() }} carton {{ \Illuminate\Support\Str::plural('group', $list->cartons->count()) }}</span></td><td class="is-num"><strong>{{ number_format($list->total_cartons) }}</strong></td><td></td><td class="is-num"><strong>{{ number_format($list->totalQuantity(),0) }}</strong></td><td></td><td class="is-num"><strong>{{ number_format((float)$list->total_net_weight,1) }} kg</strong></td><td class="is-num"><strong>{{ number_format((float)$list->total_gross_weight,1) }} kg</strong></td><td class="is-num"><strong>{{ number_format($list->totalChargeableWeight(),1) }} kg</strong></td><td class="is-num"><strong>{{ number_format((float)$list->total_cbm,4) }}</strong></td></tr></tfoot>
        </table></div>
    </section>
    <section class="master-card master-card--flat master-section pl-verification-card">
        <div class="master-section-head"><div><h2 class="master-section-title">Automatic workflow checks</h2><p class="master-sub">These checks run automatically when the list is saved, confirmed, or evaluated by the project workflow.</p></div><span class="pl-verification-status is-{{ $verificationInspection['failed'] > 0 ? 'failed' : ($verificationInspection['warnings'] > 0 ? 'warning' : 'passed') }}">{{ $verificationInspection['failed'] > 0 ? 'Issues found' : 'Ready' }}</span></div>
        <div class="pl-verification-summary"><div><span>Passed</span><strong>{{ $verificationInspection['passed'] }}</strong></div><div><span>Warnings</span><strong>{{ $verificationInspection['warnings'] }}</strong></div><div><span>Failed</span><strong>{{ $verificationInspection['failed'] }}</strong></div></div>
        <div class="pl-automated-checks">@foreach($verificationInspection['checks'] as $check)<div class="pl-check is-{{ $check['status'] }}"><i class="fa-solid {{ $check['status']==='pass' ? 'fa-circle-check' : ($check['status']==='warning' ? 'fa-triangle-exclamation' : 'fa-circle-xmark') }}"></i><div><strong>{{ $check['label'] }}</strong><span>{{ $check['message'] }}</span></div></div>@endforeach</div>
    </section>
    <section class="master-card master-card--flat master-section"><div class="master-section-head"><div><h2 class="master-section-title">Carton labels</h2><p class="master-sub">Print item-rich QR labels for an individual carton or an identical carton group.</p></div></div><div class="pl-label-list">@foreach($list->cartons as $carton)<form method="POST" target="_blank" action="{{ route('packaging-lists.cartons.label',$carton) }}" class="pl-label-row">@csrf<div><strong>{{ $carton->carton_number ?: $carton->carton_label ?: 'Carton group '.$loop->iteration }}</strong><span class="master-sub">{{ $carton->barcode }} · {{ $carton->items->count() }} item lines</span></div><div class="pl-label-controls"><label><span class="sr-only">Label format</span><select class="master-select" name="format"><option value="a4">A4 label</option><option value="a5">A5 label</option><option value="thermal">100 × 150 mm label</option></select></label><label><span class="sr-only">Copies</span><input class="master-input" type="number" name="copies" value="{{ $carton->carton_count }}" min="1" max="{{ $carton->carton_count }}" aria-label="Label count"></label><label><span class="sr-only">Print or reprint reason</span><input class="master-input" name="reason" maxlength="255" placeholder="Print / reprint reason" aria-label="Print or reprint reason"></label><button class="master-btn master-btn-soft"><i class="fa-solid fa-print"></i> Print label</button></div></form>@endforeach</div></section>
    @if($list->packing_warnings)<div class="master-alert error"><strong>Quantity warnings</strong><ul>@foreach($list->packing_warnings as $warning)<li>{{ $warning['description'] }} exceeds its source by {{ number_format((float)$warning['excess'],1) }}.</li>@endforeach</ul></div>@endif
    <section class="master-card master-card--flat master-section"><h2 class="master-section-title">Declaration and signatures</h2><p>{{ $list->declaration ?: 'No declaration recorded.' }}</p><div class="pl-signatures"><div><span>Customer signature</span><strong>{{ $list->customer_signature ?: 'Not signed' }}</strong></div><div><span>Supplier signature</span><strong>{{ $list->supplier_signature ?: 'Not signed' }}</strong></div></div></section>
</div>
@endsection
