<section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-quotes-heading">
    <div class="master-section-head">
        <div>
            <h2 class="master-section-title" id="vendor-quotes-heading">Vendor quotes</h2>
            <p class="master-sub">Price comparisons and quote history associated with this supplier.</p>
        </div>
        <a class="master-btn master-btn-soft" href="{{ $routes['vendorQuotes'] }}"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> Open quote register</a>
    </div>

    @if ($vendorQuotes->isNotEmpty())
        <div class="master-table-wrap ui-mobile-cards">
            <table class="master-table vendor-detail-table vendor-quote-table">
                <thead>
                    <tr>
                        <th scope="col">Quote</th>
                        <th scope="col">Product / lead</th>
                        <th scope="col">Vendor price</th>
                        <th scope="col">Price breaks</th>
                        <th scope="col">Status</th>
                        <th scope="col">Updated</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($vendorQuotes as $quote)
                        @php
                            $relatedProductName = $quote->relationLoaded('product') ? $quote->product?->name : null;
                            $leadProductName = $quote->relationLoaded('lead') ? $quote->lead?->product_name : null;
                            $leadNumber = $quote->relationLoaded('lead') ? $quote->lead?->lead_number : null;
                            $quoteProduct = $relatedProductName ?: ($quote->product_name ?: ($leadProductName ?: 'Product not specified'));
                            $quoteReference = $leadNumber ?: 'Standalone quote';
                        @endphp
                        <tr>
                            <td data-label="Quote">
                                <a class="vendor-table-name" href="{{ \Illuminate\Support\Facades\Route::has('vendor-quotes.show') ? route('vendor-quotes.show', $quote) : $routes['vendorQuotes'] }}">{{ $quote->quote_number }}</a>
                                @if ($quote->incoterm)<span class="vendor-table-meta">{{ $quote->incoterm }}</span>@endif
                            </td>
                            <td data-label="Product / lead">
                                @if ($quote->relationLoaded('product') && $quote->product && \Illuminate\Support\Facades\Route::has('products.show'))
                                    <a class="vendor-detail-link" href="{{ route('products.show', $quote->product) }}">{{ $quoteProduct }}</a>
                                @else
                                    <strong>{{ $quoteProduct }}</strong>
                                @endif
                                <span class="vendor-table-meta">{{ $quoteReference }}</span>
                            </td>
                            <td data-label="Vendor price">
                                @if ($quote->vendor_unit_price !== null)
                                    <strong>{{ $money($quote->vendor_unit_price, $quote->currency ?: 'RMB') }}</strong>
                                    <span class="vendor-table-meta">per {{ $quote->unit ?: 'unit' }}</span>
                                @else
                                    <span class="master-empty-value">Not priced</span>
                                @endif
                            </td>
                            <td data-label="Price breaks">
                                @if ($quote->relationLoaded('prices') && $quote->prices->isNotEmpty())
                                    {{ number_format($quote->prices->count()) }} price {{ \Illuminate\Support\Str::plural('break', $quote->prices->count()) }}
                                @elseif ($quote->quantity)
                                    {{ number_format($quote->quantity) }} {{ $quote->unit }}
                                @else
                                    <span class="master-empty-value">Not set</span>
                                @endif
                            </td>
                            <td data-label="Status"><span class="master-badge vendor-quote-status vendor-quote-status-{{ str_replace('_', '-', $quote->status) }}">{{ $quote->statusLabel() }}</span></td>
                            <td data-label="Updated">{{ $quote->updated_at?->format('d M Y') ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="master-empty-state">
            <i class="fa-solid fa-file-signature" aria-hidden="true"></i>
            <p>No quotes are linked to this vendor yet. Add or compare supplier quotes from the quote register.</p>
        </div>
    @endif
</section>
