<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <title>{{ $list->packing_number }} · Packaging List</title>
    <link rel="stylesheet" href="{{ asset('assets/css/document-print.css') }}">
    <link rel="stylesheet" href="{{ $assetVer('assets/css/packaging-list-print.css') }}">
</head>

<body class="pdf-preview pl-standalone">
    <div class="pdf-toolbar pdf-toolbar--spread pdf-toolbar--landscape no-print">
        <a class="pdf-action pdf-action--secondary" href="{{ route('packaging-lists.show', $list) }}">Back to list</a>
        <div class="pdf-toolbar-group">
            <button class="pdf-action pdf-action--primary" type="button" onclick="window.print()">
                Print / save PDF
            </button>
        </div>
    </div>

    <main class="pdf-sheet pdf-sheet--landscape pl-sheet">
        {{-- The letterhead is the invoice's: issuer and mark on the left, document
             identity on the right. A sheet drawn from a vendor's bill is that
             vendor's document, so it prints without our logo. --}}
        <header class="pl-top">
            <div class="pl-seller{{ $onLetterhead ? '' : ' pl-seller--text' }}">
                @if ($onLetterhead)
                    <img class="pl-brand-logo" src="{{ $organisation->logoUrl('print') }}"
                        alt="{{ $organisation->legal_name }}">
                @endif
                <div class="pl-seller-details">
                    <p class="pl-company">{{ $list->from_name }}</p>
                    @if ($list->from_address)
                        <p class="pl-detail">{!! nl2br(e($list->from_address)) !!}</p>
                    @endif
                    @if ($list->from_contact || $list->from_email)
                        <p class="pl-detail">
                            {{ collect([$list->from_contact, $list->from_email])->filter()->implode(' · ') }}
                        </p>
                    @endif
                </div>
            </div>

            <div class="pl-identity">
                <span class="pl-eyebrow">Shipping document</span>
                <h1>Packaging List</h1>
                <p class="pl-number">
                    <span>Packing no.</span>
                    <strong>{{ $list->packing_number }}</strong>
                </p>
                <span class="pl-status">{{ $list->statusLabel() }}</span>
            </div>
        </header>

        <section class="pl-facts" aria-label="Packaging details">
            <div class="pl-fact">
                <span>Packing date</span>
                <strong>{{ $list->packing_date?->format('d M Y') ?: '—' }}</strong>
            </div>
            <div class="pl-fact">
                <span>Transport mode</span>
                <strong>{{ $list->modeLabel() }}</strong>
            </div>
            <div class="pl-fact">
                <span>Invoice no.</span>
                <strong>{{ $list->invoice_number ?: '—' }}</strong>
            </div>
            <div class="pl-fact">
                <span>Shipment</span>
                <strong>{{ $list->shipment?->shipment_number ?: '—' }}</strong>
            </div>
            <div class="pl-fact">
                <span>Project</span>
                <strong>{{ $list->project?->project_number ?: '—' }}</strong>
            </div>
        </section>

        <section class="pl-parties" aria-label="Packing parties">
            <article class="pl-party">
                <h2>From</h2>
                <div class="pl-party-body">
                    <p class="pl-party-name">{{ $list->from_name ?: '—' }}</p>
                    @if ($list->from_address)
                        <p class="pl-party-detail">{!! nl2br(e($list->from_address)) !!}</p>
                    @endif
                    @if ($list->from_contact || $list->from_email)
                        <p class="pl-party-contact">
                            {{ collect([$list->from_contact, $list->from_email])->filter()->implode(' · ') }}
                        </p>
                    @endif
                </div>
            </article>
            <article class="pl-party">
                <h2>Bill to</h2>
                <div class="pl-party-body">
                    <p class="pl-party-name">{{ $list->bill_to_name ?: '—' }}</p>
                    @if ($list->bill_to_address)
                        <p class="pl-party-detail">{!! nl2br(e($list->bill_to_address)) !!}</p>
                    @endif
                </div>
            </article>
            <article class="pl-party">
                <h2>Ship to</h2>
                <div class="pl-party-body">
                    <p class="pl-party-name">{{ $list->ship_to_name ?: '—' }}</p>
                    @if ($list->ship_to_address)
                        <p class="pl-party-detail">{!! nl2br(e($list->ship_to_address)) !!}</p>
                    @endif
                </div>
            </article>
        </section>

        @php
            /* A section is a heading over carton blocks; a block is one carton
               configuration — a size, a weight per carton and a count — and it may
               hold more than one item (a bottle and its hook in the same box).
               Those items share the carton, so the carton's own columns print once
               and span its lines; only the two quantity columns repeat. */
            $serial = 0;
        @endphp

        <div class="pl-items-wrap">
            <table class="pl-items">
                <colgroup>
                    <col class="pl-col-serial"><col class="pl-col-item"><col class="pl-col-cartons"><col class="pl-col-qty">
                    <col class="pl-col-total-qty"><col class="pl-col-size"><col class="pl-col-nw"><col class="pl-col-net">
                    <col class="pl-col-gw"><col class="pl-col-gross"><col class="pl-col-total-cbm">
                </colgroup>
                <thead>
                    <tr>
                        <th>Sr. no.</th>
                        <th>Item description</th>
                        <th class="right">Carton</th>
                        <th class="right">Qty / carton</th>
                        <th class="right">Total qty</th>
                        <th class="right">Carton size (cm)</th>
                        <th class="right">N.W. / carton</th>
                        <th class="right">Net weight</th>
                        <th class="right">G.W. / carton</th>
                        <th class="right">Gross weight</th>
                        <th class="right">Total CBM</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sections as $section)
                        <tr class="pl-section-band">
                            <th colspan="11">{{ $section['label'] }}</th>
                        </tr>
                        @foreach ($section['cartons'] as $carton)
                            @php($span = max(1, $carton->items->count()))
                            @foreach ($carton->items as $item)
                                @php($serial++)
                                <tr>
                                    <td class="right pl-serial">{{ $serial }}</td>
                                    <td class="pl-item-description">{{ $item->item_description }}</td>
                                    @if ($loop->first)
                                        <td class="right" rowspan="{{ $span }}">{{ number_format($carton->carton_count) }}</td>
                                    @endif
                                    <td class="right">{{ number_format((float) $item->quantity_per_carton,0) }} {{ $item->unit }}</td>
                                    <td class="right">{{ number_format((float) $item->total_quantity,0) }} {{ $item->unit }}</td>
                                    @if ($loop->first)
                                        <td class="right" rowspan="{{ $span }}">{{ str_replace(' cm', '', $carton->dimensionsLabel()) }}</td>
                                        <td class="right" rowspan="{{ $span }}">{{ number_format((float) $carton->net_weight_per_carton,1) }}</td>
                                        <td class="right" rowspan="{{ $span }}">{{ number_format($carton->totalNetWeight(),1) }}</td>
                                        <td class="right" rowspan="{{ $span }}">{{ number_format((float) $carton->gross_weight_per_carton,1) }}</td>
                                        <td class="right" rowspan="{{ $span }}">{{ number_format($carton->totalGrossWeight(),1) }}</td>
                                        <td class="right" rowspan="{{ $span }}">{{ number_format($carton->totalCbm(),4) }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        @endforeach
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="2">Total</th>
                        <th class="right">{{ number_format($list->total_cartons) }}</th>
                        <th></th>
                        <th class="right">{{ number_format($list->totalQuantity(),0) }}</th>
                        <th colspan="2"></th>
                        <th class="right">{{ number_format((float) $list->total_net_weight,1) }}</th>
                        <th></th>
                        <th class="right">{{ number_format((float) $list->total_gross_weight,1) }}</th>
                        <th class="right">{{ number_format((float) $list->total_cbm,4) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <section class="pl-bottom" aria-label="Declaration and packing summary">
            <div class="pl-bottom-left">
                <div class="pl-declaration">
                    <span class="pl-section-label">Declaration</span>
                    <p>{{ $list->declaration }}</p>
                </div>
                @if ($list->notes)
                    <div class="pl-note">
                        <span class="pl-section-label">Notes</span>
                        <p>{{ $list->notes }}</p>
                    </div>
                @endif
            </div>

            <div class="pl-bottom-right">
                <section class="pl-summary">
                    <div class="pl-summary-heading">
                        <h2>Packing summary</h2>
                        <span>{{ number_format($list->total_cartons) }} {{ \Illuminate\Support\Str::plural('carton', (int) $list->total_cartons) }}</span>
                    </div>
                    <table>
                        <tbody>
                            <tr>
                                <td>Net weight</td>
                                <td>{{ number_format((float) $list->total_net_weight,1) }} kg</td>
                            </tr>
                            <tr>
                                <td>Gross weight</td>
                                <td>{{ number_format((float) $list->total_gross_weight,1) }} kg</td>
                            </tr>
                            <tr class="pl-grand-total">
                                <td>Total CBM</td>
                                <td>{{ number_format((float) $list->total_cbm,4) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <div class="pl-signature">
                    <strong>For {{ $list->from_name ?: 'MissPack India Pvt Ltd' }}</strong>
                    <span class="pl-signature-space" aria-hidden="true"></span>
                    <span class="pl-signature-label">Authorised Signatory</span>
                </div>
            </div>
        </section>

        <footer class="pl-footer">
            This is a computer-generated packaging list. The cartons listed above are packed as stated.
        </footer>
    </main>
</body>

</html>
