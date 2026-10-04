@php
    /* Twelve months, newest first, each with what moved in it — net, because a
       month can hold a payment out and money back. The bar is the month's share
       of the year's largest movement, so a year of identical pay reads as twelve
       equal lines and a bonus month stands out. */
    $peak = max(1.0, ...array_map(fn ($month) => abs((float) $month['net']), $months ?: [['net' => 1]]));
    /* one rule for a signed figure, the same one the entries table prints
       through CashflowEntry::signedAmountLabel() */
    $money = fn (float $value) => \App\Helpers\CommonHelper::amount(abs($value), $currency ?? 'INR');
@endphp

<ul class="emp-months">
    @foreach ($months as $month)
        @php($back = $month['net'] < 0)
        <li class="emp-month {{ $month['net'] > 0 ? 'has-pay' : ($back ? 'is-back' : '') }}">
            <span class="emp-month-label">{{ $month['label'] }}</span>
            <span class="emp-month-bar" aria-hidden="true">
                <i style="width: {{ $month['net'] != 0 ? max(3, round(abs($month['net']) / $peak * 100)) : 0 }}%"></i>
            </span>
            <span class="emp-month-amount">
                @if ($month['net'] != 0)
                    {{ ($back ? '−' : '').$money((float) $month['net']) }}
                @else
                    <span class="master-sub">—</span>
                @endif
            </span>
            <span class="emp-month-meta">
                @if ($month['payslip'])
                    <span class="emp-pill is-ok" title="Payslip issued">slip</span>
                @endif
                @if ($month['payments'] > 1)
                    <span class="master-sub">{{ $month['payments'] }} payments</span>
                @endif
            </span>
        </li>
    @endforeach
</ul>
