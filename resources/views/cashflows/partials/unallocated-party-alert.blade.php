@if(($unallocatedCash['count'] ?? 0) > 0)
    <section class="master-unallocated-alert" aria-labelledby="unallocated-cash-title">
        <div class="master-unallocated-alert-head">
            <span class="master-unallocated-alert-icon" aria-hidden="true"><i class="fa-solid fa-triangle-exclamation"></i></span>
            <div class="master-unallocated-alert-copy">
                <span class="master-unallocated-alert-eyebrow">Allocation required</span>
                <h2 id="unallocated-cash-title">{{ $unallocatedTitle }}</h2>
                <p>{{ $unallocatedDescription }}</p>
            </div>
            <div class="master-unallocated-alert-totals" aria-label="Unallocated totals">
                <span>Available advance</span>
                @foreach($unallocatedCash['totals'] as $currency => $total)
                    <strong>{{ \App\Helpers\CommonHelper::amount($total, $currency) }}</strong>
                @endforeach
                <small>{{ $unallocatedCash['count'] }} {{ \Illuminate\Support\Str::plural('entry', $unallocatedCash['count']) }} to allocate</small>
            </div>
        </div>
        <div class="master-unallocated-alert-rows" tabindex="0" role="region" aria-label="Unallocated entries, scroll horizontally to browse">
            @foreach($unallocatedCash['entries'] as $cashEntry)
                <a href="{{ route('cashflows.show', ['cashflow' => $cashEntry, 'allocation' => 'add']) }}" class="master-unallocated-alert-row" data-ui-tooltip="{{ $cashEntry->particular }}">
                    <span>
                        <strong>{{ $cashEntry->particular }}</strong>
                        <small>{{ optional($cashEntry->entry_date)->format('d M Y') }} · #{{ $cashEntry->id }} · {{ $cashEntry->statusLabel() }}</small>
                    </span>
                    <span class="master-unallocated-alert-amount">
                        <small>Unallocated</small>
                        <strong>{{ \App\Helpers\CommonHelper::amount($cashEntry->unallocatedAmount(), $cashEntry->currency) }}</strong>
                        <span class="master-unallocated-alert-action">Allocate now <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
                    </span>
                </a>
            @endforeach
        </div>
        @if($unallocatedCash['count'] > 1)
            <p class="master-unallocated-alert-more"><i class="fa-solid fa-arrows-left-right" aria-hidden="true"></i> Scroll to browse all {{ number_format($unallocatedCash['count']) }} entries. Select a card to allocate.</p>
        @endif
    </section>
@endif
