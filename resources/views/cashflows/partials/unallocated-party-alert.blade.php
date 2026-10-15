@if(($unallocatedCash['count'] ?? 0) > 0)
    <section class="master-unallocated-alert" aria-labelledby="unallocated-cash-title">
        <div class="master-unallocated-alert-head">
            <span class="master-unallocated-alert-icon" aria-hidden="true"><i class="fa-solid fa-coins"></i></span>
            <div class="master-unallocated-alert-copy">
                <h2 id="unallocated-cash-title">{{ $unallocatedTitle }}</h2>
                <p>{{ $unallocatedDescription }}</p>
            </div>
            <div class="master-unallocated-alert-totals" aria-label="Unallocated totals">
                @foreach($unallocatedCash['totals'] as $currency => $total)
                    <strong>{{ \App\Helpers\CommonHelper::amount($total, $currency) }}</strong>
                @endforeach
                <span>{{ $unallocatedCash['count'] }} {{ \Illuminate\Support\Str::plural('entry', $unallocatedCash['count']) }}</span>
            </div>
        </div>
        <div class="master-unallocated-alert-rows">
            @foreach($unallocatedCash['entries']->take(3) as $cashEntry)
                <a href="{{ route('cashflows.show', ['cashflow' => $cashEntry, 'allocation' => 'add']) }}" class="master-unallocated-alert-row">
                    <span>
                        <strong>{{ $cashEntry->particular }}</strong>
                        <small>{{ optional($cashEntry->entry_date)->format('d M Y') }} · {{ $cashEntry->statusLabel() }} · Cashflow #{{ $cashEntry->id }}</small>
                    </span>
                    <span class="master-unallocated-alert-amount">
                        <strong>{{ \App\Helpers\CommonHelper::amount($cashEntry->unallocatedAmount(), $cashEntry->currency) }}</strong>
                        <small>Manage allocation <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></small>
                    </span>
                </a>
            @endforeach
        </div>
        @if($unallocatedCash['count'] > 3)
            <p class="master-unallocated-alert-more">+ {{ $unallocatedCash['count'] - 3 }} more unallocated {{ \Illuminate\Support\Str::plural('entry', $unallocatedCash['count'] - 3) }}</p>
        @endif
    </section>
@endif
