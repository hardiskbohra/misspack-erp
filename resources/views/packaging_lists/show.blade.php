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
        <div class="master-section-head"><div><h2 class="master-section-title">Packing breakdown</h2><p class="master-sub">{{ $sections->count() }} packing {{ \Illuminate\Support\Str::plural('group', $sections->count()) }} across {{ $list->cartons->count() }} carton {{ \Illuminate\Support\Str::plural('block', $list->cartons->count()) }}. Lines in the same carton share its size and weight, so those columns appear once.</p></div></div>
        {{-- Row-spanned block cells do not survive the mobile card layout, so
             this table scrolls instead. --}}
        <div class="master-table-wrap"><table class="master-table">
            <thead><tr><th scope="col">Item description</th><th scope="col" class="is-num">Carton</th><th scope="col" class="is-num">Qty/carton</th><th scope="col" class="is-num">Total qty</th><th scope="col">Carton size</th><th scope="col" class="is-num">Net weight</th><th scope="col" class="is-num">Gross weight</th><th scope="col" class="is-num">Chargeable</th><th scope="col" class="is-num">Total CBM</th></tr></thead>
            <tbody>
            @foreach ($sections as $section)
                <tr class="pl-section-row"><th scope="rowgroup" colspan="9">{{ $section['label'] }}<span>{{ number_format($section['cartons_count']) }} {{ \Illuminate\Support\Str::plural('carton', (int) $section['cartons_count']) }} · {{ number_format($section['quantity'],0) }} units · {{ number_format($section['gross'],1) }} kg gross</span></th></tr>
                @foreach ($section['cartons'] as $carton)
                    @php($span = max(1, $carton->items->count()))
                    @foreach ($carton->items as $item)
                        <tr class="pl-carton-item-row" @if ($loop->first) id="carton-{{ $carton->id }}" @endif>
                            <td data-label="Item description">
                                <strong>{{ $item->item_description }}</strong>
                                @if ($loop->first && $carton->carton_label && strcasecmp(trim($carton->carton_label), trim($section['label'])) !== 0)
                                    <span class="master-sub">{{ $carton->carton_label }}</span>
                                @endif
                                @if ($loop->first && $span > 1)
                                    <span class="master-sub">Shares this carton with {{ $span - 1 }} more {{ \Illuminate\Support\Str::plural('line', $span - 1) }}</span>
                                @endif
                            </td>
                            @if ($loop->first)
                                <td data-label="Carton" rowspan="{{ $span }}" class="is-num">{{ number_format($carton->carton_count) }}</td>
                            @endif
                            <td data-label="Qty/carton" class="is-num">{{ number_format((float) $item->quantity_per_carton,0) }} {{ $item->unit }}</td>
                            <td data-label="Total qty" class="is-num"><strong>{{ number_format((float) $item->total_quantity,0) }} {{ $item->unit }}</strong></td>
                            @if ($loop->first)
                                <td data-label="Carton size" rowspan="{{ $span }}">{{ $carton->dimensionsLabel() }}</td>
                                <td data-label="Net weight" rowspan="{{ $span }}" class="is-num">{{ number_format($carton->totalNetWeight(),1) }} kg<span class="master-sub">{{ number_format((float) $carton->net_weight_per_carton,1) }} / carton</span></td>
                                <td data-label="Gross weight" rowspan="{{ $span }}" class="is-num">{{ number_format($carton->totalGrossWeight(),1) }} kg<span class="master-sub">{{ number_format((float) $carton->gross_weight_per_carton,1) }} / carton</span></td>
                                <td data-label="Chargeable" rowspan="{{ $span }}" class="is-num">{{ number_format($carton->totalChargeableWeight(),1) }} kg</td>
                                <td data-label="Total CBM" rowspan="{{ $span }}" class="is-num"><span>{{ number_format($carton->cbmPerCarton(),4) }}/carton</span><strong>{{ number_format($carton->totalCbm(),4) }} total</strong></td>
                            @endif
                        </tr>
                    @endforeach
                @endforeach
            @endforeach
            </tbody>
            <tfoot><tr><td><strong>Total</strong><span class="master-sub">{{ $list->cartons->count() }} carton {{ \Illuminate\Support\Str::plural('block', $list->cartons->count()) }}</span></td><td class="is-num"><strong>{{ number_format($list->total_cartons) }}</strong></td><td></td><td class="is-num"><strong>{{ number_format($list->totalQuantity(),0) }}</strong></td><td></td><td class="is-num"><strong>{{ number_format((float)$list->total_net_weight,1) }} kg</strong></td><td class="is-num"><strong>{{ number_format((float)$list->total_gross_weight,1) }} kg</strong></td><td class="is-num"><strong>{{ number_format($list->totalChargeableWeight(),1) }} kg</strong></td><td class="is-num"><strong>{{ number_format((float)$list->total_cbm,4) }}</strong></td></tr></tfoot>
        </table></div>
    </section>
    <section class="master-card master-card--flat master-section pl-verification-card">
        <div class="master-section-head"><div><h2 class="master-section-title">Automatic workflow checks</h2><p class="master-sub">These checks run automatically when the list is saved, confirmed, or evaluated by the project workflow.</p></div><span class="pl-verification-status is-{{ $verificationInspection['failed'] > 0 ? 'failed' : ($verificationInspection['warnings'] > 0 ? 'warning' : 'passed') }}">{{ $verificationInspection['failed'] > 0 ? 'Issues found' : 'Ready' }}</span></div>
        <div class="pl-verification-summary"><div><span>Passed</span><strong>{{ $verificationInspection['passed'] }}</strong></div><div><span>Warnings</span><strong>{{ $verificationInspection['warnings'] }}</strong></div><div><span>Failed</span><strong>{{ $verificationInspection['failed'] }}</strong></div></div>
        <div class="pl-automated-checks">@foreach($verificationInspection['checks'] as $check)<div class="pl-check is-{{ $check['status'] }}"><i class="fa-solid {{ $check['status']==='pass' ? 'fa-circle-check' : ($check['status']==='warning' ? 'fa-triangle-exclamation' : 'fa-circle-xmark') }}"></i><div><strong>{{ $check['label'] }}</strong><span>{{ $check['message'] }}</span></div></div>@endforeach</div>
    </section>
    <section class="master-card master-card--flat master-section"><div class="master-section-head"><div><h2 class="master-section-title">Carton labels</h2><p class="master-sub">Print item-rich QR labels for an individual carton or an identical carton group.</p></div></div><form method="POST" action="{{ route('packaging-lists.labels', $list) }}" target="_blank" rel="noopener" class="pl-label-batch">
        @csrf
        <div><strong>All carton labels</strong><p class="master-sub">One numbered label per physical carton, across every group. Print or save one PDF.</p></div>
        <div class="pl-label-batch-controls">
            <label for="plBatchLabelFormat">Page type<select class="master-select" id="plBatchLabelFormat" name="format"><option value="a4">A4 label</option><option value="a5">A5 label</option><option value="thermal">100 × 150 mm label</option></select></label>
            <label for="plBatchLabelReason">Print / reprint reason <span class="master-sub">(optional)</span><input class="master-input" id="plBatchLabelReason" name="reason" maxlength="255"></label>
            <button class="master-btn master-btn-primary" @disabled($list->cartons->isEmpty())><i class="fa-solid fa-print"></i> Print / download all labels</button>
        </div>
    </form><div class="pl-label-list">@foreach($list->cartons as $carton)<form method="POST" target="_blank" action="{{ route('packaging-lists.cartons.label',$carton) }}" class="pl-label-row">@csrf<div><strong>{{ $carton->carton_number ?: $carton->carton_label ?: 'Carton group '.$loop->iteration }}</strong><span class="master-sub">{{ $carton->barcode }} · {{ $carton->items->count() }} item lines</span></div><div class="pl-label-controls"><label><span class="sr-only">Label format</span><select class="master-select" name="format"><option value="a4">A4 label</option><option value="a5">A5 label</option><option value="thermal">100 × 150 mm label</option></select></label><label><span class="sr-only">Copies</span><input class="master-input" type="number" name="copies" value="{{ $carton->carton_count }}" min="1" max="{{ $carton->carton_count }}" aria-label="Label count"></label><label><span class="sr-only">Print or reprint reason</span><input class="master-input" name="reason" maxlength="255" placeholder="Print / reprint reason" aria-label="Print or reprint reason"></label><button class="master-btn master-btn-soft"><i class="fa-solid fa-print"></i> Print label</button></div></form>@endforeach</div></section>
    @if($list->packing_warnings)<div class="master-alert error"><strong>Quantity warnings</strong><ul>@foreach($list->packing_warnings as $warning)<li>{{ $warning['description'] }} exceeds its source by {{ number_format((float)$warning['excess'],1) }}.</li>@endforeach</ul></div>@endif
    <section class="master-card master-card--flat master-section"><h2 class="master-section-title">Declaration and signatures</h2><p>{{ $list->declaration ?: 'No declaration recorded.' }}</p><div class="pl-signatures"><div><span>Customer signature</span><strong>{{ $list->customer_signature ?: 'Not signed' }}</strong></div><div><span>Supplier signature</span><strong>{{ $list->supplier_signature ?: 'Not signed' }}</strong></div></div></section>
</div>
@endsection
