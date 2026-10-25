@php
    $printSettings = $list->printSettings();
    foreach (['orientation', 'header_text', 'footer_text'] as $setting) {
        $previous = old($setting);
        if ($setting !== 'orientation' && session()->hasOldInput($setting) && $previous === null) {
            $previous = '';
        }
        if (is_string($previous) && ($setting !== 'orientation' || in_array($previous, ['portrait', 'landscape'], true))) {
            $printSettings[$setting] = $previous;
        }
    }
    if (in_array(old('show_page_numbers'), ['0', '1', 0, 1, false, true], true)) {
        $printSettings['show_page_numbers'] = (bool) old('show_page_numbers');
    }
@endphp
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
    <link rel="stylesheet" href="{{ $assetVer('assets/css/packaging-list-print-settings.css') }}">
    {{-- This document owns its selected paper. Keep this last so shared A4 portrait cannot override it. --}}
    <style id="plPageStyle">
        @page {
            size: A4 {{ $printSettings['orientation'] }};
            margin: 16mm 12mm;
            @top-center {
                content: {!! \App\Support\PrintCss::text($printSettings['header_text']) !!};
                font: 9px "Inter", sans-serif;
                color: #000;
                white-space: pre-wrap;
                vertical-align: middle;
            }
            @bottom-left {
                content: {!! \App\Support\PrintCss::text($printSettings['footer_text']) !!};
                font: 9px "Inter", sans-serif;
                color: #000;
                white-space: pre-wrap;
                width: 80%;
                vertical-align: middle;
            }
            @bottom-right {
                content: @if ($printSettings['show_page_numbers']) "Page " counter(page) " of " counter(pages) @else none @endif;
                font: 9px "Inter", sans-serif;
                color: #000;
                white-space: nowrap;
                vertical-align: middle;
            }
        }
    </style>
</head>

<body class="pdf-preview pl-standalone" data-orientation="{{ $printSettings['orientation'] }}">
    <div class="pdf-toolbar pdf-toolbar--spread no-print">
        <a class="pdf-action pdf-action--secondary" href="{{ route('packaging-lists.show', $list) }}">Back to list</a>
        <div class="pdf-toolbar-group">
            <button class="pdf-action pdf-action--primary" type="button" data-print-document>
                Print / save PDF
            </button>
        </div>
    </div>

    <section class="pl-print-settings no-print" aria-labelledby="plPrintSettingsTitle">
        <details open>
            <summary id="plPrintSettingsTitle">Print settings</summary>
            <form method="POST" action="{{ route('packaging-lists.print-settings', $list) }}" data-print-settings>
                @csrf
                @method('PATCH')
                <p class="pl-settings-hint">Preview changes immediately. Save settings to remember them for this packaging list.</p>
                @if (session('success'))<p class="pl-settings-status" role="status">{{ session('success') }}</p>@endif
                @if ($errors->any())<div class="pl-settings-errors" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                <div class="pl-settings-grid">
                    <label>Orientation
                        <select name="orientation">
                            <option value="portrait" @selected($printSettings['orientation'] === 'portrait')>A4 · Portrait</option>
                            <option value="landscape" @selected($printSettings['orientation'] === 'landscape')>A4 · Landscape</option>
                        </select>
                    </label>
                    <label>Header text <span class="pl-settings-hint">Optional · repeats on every page</span>
                        <textarea name="header_text" rows="2" maxlength="160">{{ $printSettings['header_text'] }}</textarea>
                    </label>
                    <label>Footer text <span class="pl-settings-hint">Optional · repeats on every page</span>
                        <textarea name="footer_text" rows="2" maxlength="240">{{ $printSettings['footer_text'] }}</textarea>
                    </label>
                </div>
                <div class="pl-settings-actions">
                    <input type="hidden" name="show_page_numbers" value="0">
                    <label class="pl-page-number-toggle"><input type="checkbox" name="show_page_numbers" value="1" role="switch" @checked($printSettings['show_page_numbers'])><span class="pl-switch-track" aria-hidden="true"></span><span>Show page numbers</span></label>
                    <button type="submit" class="pdf-action pdf-action--secondary">Save settings</button>
                </div>
                <p class="pl-settings-hint">Repeating text and page numbers require a recent Chrome or Edge. In the print dialog, turn off the browser’s “Headers and footers” to avoid extra URLs and dates. The screen preview shows sample page numbering; actual numbers are calculated when printing.</p>
                <p class="pl-settings-hint" data-print-settings-status role="status" aria-live="polite"></p>
            </form>
        </details>
    </section>

    <main class="pdf-sheet pl-sheet">
        <div class="pl-running-header no-print" data-preview-header @if ($printSettings['header_text'] === '') hidden @endif>{{ $printSettings['header_text'] }}</div>
        {{-- The letterhead is the invoice's: issuer and mark on the left, document
             identity on the right. A sheet drawn from a vendor's bill is that
             vendor's document, so it prints without our logo. --}}
        <header class="pl-top">
            <div class="pl-seller">
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
            $columns = $list->selectedPrintColumns();
            $columnLabels = \App\Models\PackagingList::printColumnOptions();
            $columnWidths = $list->printColumnWidths();
            $showColumn = fn ($column) => in_array($column, $columns, true);
        @endphp

        <div class="pl-items-wrap">
            <table class="pl-items">
                <colgroup>
                    @foreach ($columns as $column)
                        <col class="pl-col-{{ str_replace('_', '-', $column) }}" style="width: {{ $columnWidths[$column] }}%">
                    @endforeach
                </colgroup>
                <thead>
                    <tr>
                        @foreach ($columns as $column)
                            <th @class(['right' => !in_array($column, ['serial', 'item'], true)])>{{ $columnLabels[$column] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sections as $section)
                        <tr class="pl-section-band">
                            <th colspan="{{ count($columns) }}">{{ $section['label'] }}</th>
                        </tr>
                        @foreach ($section['cartons'] as $carton)
                            @php($span = max(1, $carton->items->count()))
                            @foreach ($carton->items as $item)
                                @php($serial++)
                                <tr>
                                    @if ($showColumn('serial'))<td class="right pl-serial">{{ $serial }}</td>@endif
                                    @if ($showColumn('item'))<td class="pl-item-description">{{ $item->item_description }}</td>@endif
                                    @if ($loop->first)
                                        @if ($showColumn('cartons'))<td class="right" rowspan="{{ $span }}">{{ number_format($carton->carton_count) }}</td>@endif
                                    @endif
                                    @if ($showColumn('qty'))<td class="right">{{ number_format((float) $item->quantity_per_carton,0) }} {{ $item->unit }}</td>@endif
                                    @if ($showColumn('total_qty'))<td class="right">{{ number_format((float) $item->total_quantity,0) }} {{ $item->unit }}</td>@endif
                                    @if ($loop->first)
                                        @if ($showColumn('size'))<td class="right pl-carton-dimensions" rowspan="{{ $span }}">{{ str_replace(' cm', '', $carton->dimensionsLabel()) }}</td>@endif
                                        @if ($showColumn('nw'))<td class="right" rowspan="{{ $span }}">{{ number_format((float) $carton->net_weight_per_carton,1) }}</td>@endif
                                        @if ($showColumn('net'))<td class="right" rowspan="{{ $span }}">{{ number_format($carton->totalNetWeight(),1) }}</td>@endif
                                        @if ($showColumn('gw'))<td class="right" rowspan="{{ $span }}">{{ number_format((float) $carton->gross_weight_per_carton,1) }}</td>@endif
                                        @if ($showColumn('gross'))<td class="right" rowspan="{{ $span }}">{{ number_format($carton->totalGrossWeight(),1) }}</td>@endif
                                        @if ($showColumn('cbm'))<td class="right" rowspan="{{ $span }}">{{ number_format($carton->cbmPerCarton(),4) }}</td>@endif
                                        @if ($showColumn('total_cbm'))<td class="right" rowspan="{{ $span }}">{{ number_format($carton->totalCbm(),4) }}</td>@endif
                                    @endif
                                </tr>
                            @endforeach
                        @endforeach
                    @endforeach
                </tbody>
                <tfoot>
                    <tr><th colspan="{{ count($columns) }}">Total</th></tr>
                    <tr>
                        @foreach ($columns as $column)
                            <th class="right">
                                @switch($column)
                                    @case('cartons') {{ number_format($list->total_cartons) }} @break
                                    @case('total_qty') {{ number_format($list->totalQuantity(),0) }} @break
                                    @case('net') {{ number_format((float) $list->total_net_weight,1) }} @break
                                    @case('gross') {{ number_format((float) $list->total_gross_weight,1) }} @break
                                    @case('total_cbm') {{ number_format((float) $list->total_cbm,4) }} @break
                                @endswitch
                            </th>
                        @endforeach
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

        <footer class="pl-footer" data-preview-footer>
            <span data-preview-footer-text>{{ $printSettings['footer_text'] }}</span>
            <span data-preview-page-number @if (!$printSettings['show_page_numbers']) hidden @endif>Page 1 of …</span>
        </footer>
    </main>
    <script src="{{ $assetVer('assets/js/packaging-list-print.js') }}" defer></script>
</body>

</html>
