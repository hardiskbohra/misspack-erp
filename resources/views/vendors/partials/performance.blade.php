@php
    /**
     * What this supplier has been worth. The bars are rupees per month for the
     * last six months of bills and expenses; the figures beside them are the
     * totals the same ledger produces, so the card can never disagree with the
     * Money tab.
     */
@endphp
<section class="master-card master-card--flat vendor-detail-card vendor-detail-card--wide" aria-labelledby="vendor-performance-heading">
    <div class="vendor-card-head">
        <h2 class="vendor-detail-title" id="vendor-performance-heading">Spend &amp; performance</h2>
        <span class="vendor-card-link">{{ $vendor->rating ? str_repeat('★', $vendor->rating).' rated' : 'Not rated' }}</span>
    </div>

    <div class="vendor-spend">
        @foreach ($performance['series'] as $month)
            <div class="vendor-spend-bar" title="{{ $month['label'] }}: {{ \App\Helpers\CommonHelper::indianCurrency($month['amount']) }}">
                <span class="vendor-spend-fill" style="height: {{ max(4, (int) round(($month['amount'] / $performance['peak']) * 100)) }}%"></span>
                <span class="vendor-spend-label">{{ $month['label'] }}</span>
            </div>
        @endforeach
    </div>

    <div class="master-facts">
        <div class="master-info"><span>Billed, all time</span><strong>{{ \App\Helpers\CommonHelper::indianCurrency($performance['billed']) }}</strong></div>
        <div class="master-info"><span>Settled</span><strong class="{{ $performance['settled_percent'] >= 90 ? 'vendor-amount-credit' : '' }}">{{ $performance['settled_percent'] }}%</strong></div>
        <div class="master-info"><span>Bills raised</span><strong>{{ number_format($performance['bills']) }}</strong></div>
        <div class="master-info"><span>Payments made</span><strong>{{ number_format($performance['payments']) }}</strong></div>
        <div class="master-info"><span>Average bill</span><strong>{{ \App\Helpers\CommonHelper::indianCurrency($performance['average_bill']) }}</strong></div>
        <div class="master-info"><span>Largest bill</span><strong>{{ \App\Helpers\CommonHelper::indianCurrency($performance['largest_bill']) }}</strong></div>
        <div class="master-info"><span>Open bills</span><strong>{{ number_format($performance['open_bills']) }}</strong></div>
        <div class="master-info"><span>First business</span><strong>{{ $performance['first_entry']?->format('d M Y') ?: 'Not on file' }}</strong></div>
    </div>

    @if ($performance['last_entry'])
        <p class="vendor-detail-help">Last ledger movement {{ $performance['last_entry']->format('d M Y') }}.</p>
    @endif
</section>
