<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $list->packing_number }} · Packaging List</title><link rel="stylesheet" href="{{ $assetVer('assets/css/packaging-lists.css') }}"></head><body class="pl-print-body">
<div class="pl-print-actions"><button onclick="window.print()">Print / save PDF</button><button onclick="window.close()">Close</button></div>
<main class="pl-document">
<header class="pl-document-head"><div><p>PACKAGING LIST</p><h1>{{ $list->packing_number }}</h1></div><dl><div><dt>Date</dt><dd>{{ $list->packing_date?->format('d M Y') }}</dd></div><div><dt>Invoice No.</dt><dd>{{ $list->invoice_number ?: '—' }}</dd></div><div><dt>Mode</dt><dd>{{ strtoupper($list->modeLabel()) }}</dd></div></dl></header>
<section class="pl-document-parties"><div><h2>FROM</h2><strong>{{ $list->from_name }}</strong><p>{!! nl2br(e($list->from_address)) !!}</p>@if($list->from_contact)<p>Contact: {{ $list->from_contact }}</p>@endif @if($list->from_email)<p>{{ $list->from_email }}</p>@endif</div><div><h2>BILL TO</h2><strong>{{ $list->bill_to_name }}</strong><p>{!! nl2br(e($list->bill_to_address)) !!}</p></div><div><h2>SHIP TO</h2><strong>{{ $list->ship_to_name }}</strong><p>{!! nl2br(e($list->ship_to_address)) !!}</p></div></section>
@php
    /* The sheet is read in sections, and a section is a set of carton blocks:
       one block is one carton configuration — a size, a weight per carton and a
       count — and it may hold more than one item (a bottle and its hook in the
       same box). Those items share the carton, so the carton's own columns are
       printed once and span its lines; only the two quantity columns repeat. */
    $serial = 0;
@endphp
<table class="pl-document-table">
<colgroup><col class="pl-col-serial"><col class="pl-col-item"><col class="pl-col-cartons"><col class="pl-col-qty"><col class="pl-col-total-qty"><col class="pl-col-size"><col class="pl-col-nw"><col class="pl-col-net"><col class="pl-col-gw"><col class="pl-col-gross"><col class="pl-col-cbm"><col class="pl-col-total-cbm"></colgroup>
<thead><tr><th>Sr. No.</th><th>Item Description</th><th>Carton</th><th>Qty/Carton</th><th>Total Qty</th><th>Carton Size</th><th>NW/Carton</th><th>Net Weight</th><th>GW/Carton</th><th>Gross Weight</th><th>CBM/Carton</th><th>Total CBM</th></tr></thead>
<tbody>
@foreach ($sections as $section)
<tr class="pl-print-group-title"><th colspan="12">{{ $section['label'] }}</th></tr>
@foreach ($section['cartons'] as $carton)
@php($span = max(1, $carton->items->count()))
@foreach ($carton->items as $item)
@php($serial++)
<tr class="pl-print-item">
<td>{{ $serial }}</td>
<td>{{ $item->item_description }}</td>
@if ($loop->first)
<td rowspan="{{ $span }}">{{ number_format($carton->carton_count) }}</td>
@endif
<td>{{ number_format((float) $item->quantity_per_carton,0) }} {{ $item->unit }}</td>
<td>{{ number_format((float) $item->total_quantity,0) }} {{ $item->unit }}</td>
@if ($loop->first)
<td rowspan="{{ $span }}">{{ str_replace(' cm', '', $carton->dimensionsLabel()) }}</td>
<td rowspan="{{ $span }}">{{ number_format((float) $carton->net_weight_per_carton,1) }}</td>
<td rowspan="{{ $span }}">{{ number_format($carton->totalNetWeight(),1) }}</td>
<td rowspan="{{ $span }}">{{ number_format((float) $carton->gross_weight_per_carton,1) }}</td>
<td rowspan="{{ $span }}">{{ number_format($carton->totalGrossWeight(),1) }}</td>
<td rowspan="{{ $span }}">{{ number_format($carton->cbmPerCarton(),4) }}</td>
<td rowspan="{{ $span }}">{{ number_format($carton->totalCbm(),4) }}</td>
@endif
</tr>
@endforeach
@endforeach
@endforeach
</tbody>
<tfoot><tr><th colspan="2">Total</th><th>{{ number_format($list->total_cartons) }}</th><th></th><th>{{ number_format($list->totalQuantity(),0) }}</th><th></th><th></th><th>{{ number_format((float)$list->total_net_weight,1) }}</th><th></th><th>{{ number_format((float)$list->total_gross_weight,1) }}</th><th></th><th>{{ number_format((float)$list->total_cbm,4) }}</th></tr></tfoot>
</table>
<section class="pl-declaration"><h2>DECLARATION</h2><p>{{ $list->declaration }}</p></section>
<footer class="pl-document-footer"><div><span>Customer's signature</span><strong>{{ $list->customer_signature }}</strong></div><div><span>For {{ $list->from_name }}</span><strong>{{ $list->supplier_signature }}</strong><small>Authorised signatory</small></div></footer>
</main></body></html>
