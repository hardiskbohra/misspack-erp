@php
    /* Twelve months, newest first, each with what landed in it. The bar is the
       month's share of the year's highest month, so a year of identical pay
       reads as twelve equal lines and a bonus month stands out. */
    $peak = max(1.0, ...array_map(fn ($month) => (float) $month['credit'], $months ?: [['credit' => 1]]));
@endphp

<ul class="emp-months">
    @foreach ($months as $month)
        <li class="emp-month {{ $month['credit'] > 0 ? 'has-pay' : '' }}">
            <span class="emp-month-label">{{ $month['label'] }}</span>
            <span class="emp-month-bar" aria-hidden="true">
                <i style="width: {{ $month['credit'] > 0 ? max(3, round($month['credit'] / $peak * 100)) : 0 }}%"></i>
            </span>
            <span class="emp-month-amount">
                @if ($month['credit'] > 0)
                    {{ \App\Helpers\CommonHelper::amount($month['credit'], $currency ?? 'INR') }}
                @else
                    <span class="master-sub">—</span>
                @endif
            </span>
            <span class="emp-month-meta">
                @if ($month['payslip'])
                    <span class="emp-pill is-ok" title="Payslip issued">slip</span>
                @endif
                @if ($month['entries'] > 1)
                    <span class="master-sub">{{ $month['entries'] }} credits</span>
                @endif
            </span>
        </li>
    @endforeach
</ul>
