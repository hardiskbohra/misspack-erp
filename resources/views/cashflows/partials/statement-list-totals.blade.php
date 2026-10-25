{{-- One amount per currency, never an exchange-rate-free grand total. --}}
<div class="statement-currency-totals">
    @forelse(($currency === 'all' ? $totalsByCurrency : [$currency => $totals]) as $code => $amounts)
        <p class="master-stat-value statement-currency-total">
            @if($currency === 'all')<span class="master-sub">{{ $code }}</span>@endif
            {{ \App\Helpers\CommonHelper::amount(array_sum(array_map(fn ($field) => $amounts[$field], $fields)), $code) }}
        </p>
    @empty
        <span class="master-sub">No movement or balances</span>
    @endforelse
</div>
