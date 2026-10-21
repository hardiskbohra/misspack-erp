<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $list->packing_number }} · Packaging List</title><link rel="stylesheet" href="{{ $assetVer('assets/css/packaging-lists.css') }}"></head><body class="pl-print-body">
<div class="pl-print-actions"><button onclick="window.print()">Print / save PDF</button><button onclick="window.close()">Close</button></div>
<main class="pl-document">
<header class="pl-document-head"><div><p>PACKAGING LIST</p><h1>{{ $list->packing_number }}</h1></div><dl><div><dt>Date</dt><dd>{{ $list->packing_date?->format('d M Y') }}</dd></div><div><dt>Invoice No.</dt><dd>{{ $list->invoice_number ?: '—' }}</dd></div><div><dt>Mode</dt><dd>{{ strtoupper($list->modeLabel()) }}</dd></div></dl></header>
<section class="pl-document-parties"><div><h2>FROM</h2><strong>{{ $list->from_name }}</strong><p>{!! nl2br(e($list->from_address)) !!}</p>@if($list->from_contact)<p>Contact: {{ $list->from_contact }}</p>@endif @if($list->from_email)<p>{{ $list->from_email }}</p>@endif</div><div><h2>BILL TO</h2><strong>{{ $list->bill_to_name }}</strong><p>{!! nl2br(e($list->bill_to_address)) !!}</p></div><div><h2>SHIP TO</h2><strong>{{ $list->ship_to_name }}</strong><p>{!! nl2br(e($list->ship_to_address)) !!}</p></div></section>
@php
    /* A product is regularly packed in several carton configurations at once —
       10 cartons of 336, 2 of 340, 3 of 300 are all the same bottle. Read carton
       by carton that product appears three times and nothing adds it up, so the
       document is grouped by product and each group carries its own totals. */
    $groups = $productGroups;
    $cartonNumbers = [];
    $runningCarton = 0;
    foreach ($list->cartons as $carton) {
        $cartonNumbers[$carton->id] = [(int) $runningCarton + 1, (int) $runningCarton + (int) $carton->carton_count];
        $runningCarton += (int) $carton->carton_count;
    }
    $sharedCartons = $groups->sum('shared_cartons');
@endphp
<table class="pl-document-table">
<colgroup><col class="pl-col-serial"><col class="pl-col-item"><col class="pl-col-cartons"><col class="pl-col-qty"><col class="pl-col-total-qty"><col class="pl-col-size"><col class="pl-col-nw"><col class="pl-col-net"><col class="pl-col-gw"><col class="pl-col-gross"><col class="pl-col-cbm"><col class="pl-col-total-cbm"></colgroup>
<thead><tr><th>Sr. no.</th><th>Item description</th><th>Cartons</th><th>Qty / carton</th><th>Total qty</th><th>Carton size (cm)</th><th>N.W. / carton (kg)</th><th>Net weight (kg)</th><th>G.W. / carton (kg)</th><th>Gross weight (kg)</th><th>CBM / carton</th><th>Total CBM</th></tr></thead>
<tbody>
@foreach ($groups as $group)
<tr class="pl-print-group-title"><th colspan="12"><span class="pl-print-group-heading"><span>Group({{ $group['label'] }})</span><small>{{ count($group['rows']) }} carton {{ Illuminate\Support\Str::plural('size', count($group['rows'])) }}</small></span></th></tr>
@foreach ($group['rows'] as $row)
@php($range = $cartonNumbers[$row['carton']->id] ?? null)
<tr class="pl-print-item">
<td>{{ $loop->iteration }}</td>
<td><strong>{{ $group['label'] }}</strong>@if ($range)<span class="pl-print-carton-ref">Cartons {{ $range[0] === $range[1] ? $range[0] : $range[0].'–'.$range[1] }}</span>@endif</td>
<td>{{ number_format($row['cartons']) }}</td>
<td>{{ number_format($row['quantity_per_carton'],0) }} {{ $group['unit'] }}</td>
<td>{{ number_format($row['quantity'],0) }} {{ $group['unit'] }}</td>
<td>{{ str_replace(' cm', '', $row['carton']->dimensionsLabel()) }}</td>
<td>{{ number_format($row['net_per_carton'],1) }}</td>
<td>{{ $row['shared'] ? '—' : number_format($row['net'],1) }}</td>
<td>{{ number_format($row['gross_per_carton'],1) }}</td>
<td>{{ $row['shared'] ? '—' : number_format($row['gross'],1) }}</td>
<td>{{ number_format($row['cbm_per_carton'],4) }}</td>
<td>{{ $row['shared'] ? '—' : number_format($row['cbm'],4) }}</td>
</tr>
@endforeach
@php($ownCartons = (int) $group['cartons'])
<tr class="pl-print-group-total">
<th colspan="2">Group total · {{ $ownCartons > 0 ? number_format($ownCartons).' '.Illuminate\Support\Str::plural('carton', $ownCartons) : 'shared cartons only' }}</th>
<td>{{ $ownCartons > 0 ? number_format($ownCartons) : '—' }}</td>
<td></td>
<td>{{ number_format($group['quantity'],0) }} {{ $group['unit'] }}</td>
<td></td>
<td></td>
<td>{{ $ownCartons > 0 ? number_format($group['net'],1) : '—' }}</td>
<td></td>
<td>{{ $ownCartons > 0 ? number_format($group['gross'],1) : '—' }}</td>
<td></td>
<td>{{ $ownCartons > 0 ? number_format($group['cbm'],4) : '—' }}</td>
</tr>
@endforeach
</tbody>
<tfoot><tr><th colspan="2">TOTAL</th><th>{{ number_format($list->total_cartons) }}</th><th></th><th>{{ number_format($list->totalQuantity(),0) }}</th><th></th><th></th><th>{{ number_format((float)$list->total_net_weight,1) }}</th><th></th><th>{{ number_format((float)$list->total_gross_weight,1) }}</th><th></th><th>{{ number_format((float)$list->total_cbm,4) }}</th></tr></tfoot>
</table>
@if ($sharedCartons > 0)
<p class="pl-print-note">{{ $sharedCartons }} {{ Illuminate\Support\Str::plural('carton', $sharedCartons) }} hold more than one product. Their weight and CBM are counted once in the total, not against each product.</p>
@endif
<section class="pl-declaration"><h2>DECLARATION</h2><p>{{ $list->declaration }}</p></section>
<footer class="pl-document-footer"><div><span>Customer's signature</span><strong>{{ $list->customer_signature }}</strong></div><div><span>For {{ $list->from_name }}</span><strong>{{ $list->supplier_signature }}</strong><small>Authorised signatory</small></div></footer>
</main></body></html>
