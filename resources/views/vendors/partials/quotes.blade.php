<section class="master-tab-panel" id="vendor-panel-quotes" role="tabpanel" aria-labelledby="vendor-tab-quotes">
    <div class="vendor-panel-head">
        <div>
            <h2 class="vendor-detail-title">Vendor quotes</h2>
            <p class="vendor-detail-help">Quotes linked to this vendor by record or by the vendor name on the quote.</p>
        </div>
        <div class="vendor-panel-meta">
            <span class="vendor-pill">{{ $vendorQuotes->count() }} {{ \Illuminate\Support\Str::plural('quote', $vendorQuotes->count()) }}</span>
            @if ($routes['vendorQuotes'] !== '#')
                <a class="master-btn master-btn-soft master-btn-sm" href="{{ $routes['vendorQuotes'] }}">
                    <i class="fa-solid fa-money-bill" aria-hidden="true"></i> All vendor quotes
                </a>
            @endif
        </div>
    </div>

    <div class="master-card master-card--flat vendor-table-card">
        <div class="master-table-wrap ui-mobile-cards">
            <table class="master-table vendor-table">
                <thead>
                    <tr>
                        <th scope="col">Quote</th>
                        <th scope="col">Product</th>
                        <th scope="col" class="is-num">Qty</th>
                        <th scope="col" class="is-num">Unit price</th>
                        <th scope="col" class="is-num">Landing cost</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="ui-mobile-secondary">Terms</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($vendorQuotes as $quote)
                        <tr>
                            <td data-label="Quote">
                                @if (\Illuminate\Support\Facades\Route::has('vendor-quotes.show'))
                                    <a class="vendor-table-link" href="{{ route('vendor-quotes.show', $quote) }}">
                                        <strong>{{ $quote->quote_number ?: 'Quote #'.$quote->id }}</strong>
                                        <span class="master-sub">{{ $quote->created_at?->format('d M Y') ?: '—' }}</span>
                                    </a>
                                @else
                                    <strong>{{ $quote->quote_number ?: 'Quote #'.$quote->id }}</strong>
                                @endif
                            </td>
                            <td data-label="Product">{{ $quote->product?->name ?: ($quote->product_name ?: '—') }}</td>
                            <td data-label="Qty" class="is-num">{{ $quote->quantity ? number_format((int) $quote->quantity) : '—' }} <span class="master-sub">{{ $quote->unit }}</span></td>
                            <td data-label="Unit price" class="is-num">{{ $quote->vendor_unit_price ? $money($quote->vendor_unit_price, $quote->currency ?: 'RMB') : '—' }}</td>
                            <td data-label="Landing cost" class="is-num">
                                @if ($quote->landing_cost_inr)
                                    <strong>{{ $money($quote->landing_cost_inr, 'INR') }}</strong>
                                    <span class="master-sub">landed, INR</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td data-label="Status"><span class="vendor-meta-chip">{{ $quote->statusLabel() }}</span></td>
                            <td data-label="Terms" class="ui-mobile-secondary">
                                {{ $quote->incoterm ?: '—' }}
                                <span class="master-sub">MOQ {{ $quote->moq ?: '—' }} · lead {{ $quote->lead_time_days !== null ? $quote->lead_time_days.' d' : '—' }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="master-list-empty">
                                    <span class="master-list-empty-icon" aria-hidden="true"><i class="fa-solid fa-money-bill"></i></span>
                                    <h3 class="master-list-empty-title">No quotes from this vendor yet</h3>
                                    <p class="master-list-empty-text">Quotes recorded against this supplier name or record will be listed here.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
